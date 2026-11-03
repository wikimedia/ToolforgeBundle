<?php

declare(strict_types=1);

namespace Wikimedia\ToolforgeBundle\Tests\Controller;

use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\Token;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Wikimedia\ToolforgeBundle\Controller\AuthController;

class AuthControllerTest extends TestCase
{

    /**
     * The toolforge.oauth.logged_in_user parameter must log the given user in
     * without a request to the OAuth wiki.
     */
    public function testLoginWithDevelopmentUser(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $controller = $this->getController('TestUser');

        // The OAuth handshake must not start.
        $client = $this->createMock(Client::class);
        $client->expects(static::never())->method('initiate');

        $response = $controller->loginAction(new Request(), $client, $session);

        static::assertSame('/home', $response->getTargetUrl());
        static::assertSame('TestUser', $session->get('logged_in_user')->username);
    }

    /**
     * The development login must obey the redirect of the callback URL,
     * the same as the OAuth callback does.
     */
    public function testLoginWithDevelopmentUserAndCallback(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $controller = $this->getController('TestUser');
        $request = new Request([
            'callback' => 'https://tool.example.org/oauth_callback'
                .'?redirect=https%3A%2F%2Ftool.example.org%2Fmy-page%3Ffoo%3Dbar',
        ]);

        $response = $controller->loginAction($request, $this->createMock(Client::class), $session);

        static::assertSame('https://tool.example.org/my-page?foo=bar', $response->getTargetUrl());
        static::assertSame('TestUser', $session->get('logged_in_user')->username);
    }

    /**
     * A callback URL that does not point back to this application must be ignored.
     */
    public function testLoginWithDevelopmentUserAndForeignCallback(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $controller = $this->getController('TestUser');
        $request = new Request([
            'callback' => 'https://evil.example.com/oauth_callback?redirect=https%3A%2F%2Fevil.example.com',
        ]);

        $response = $controller->loginAction($request, $this->createMock(Client::class), $session);

        static::assertSame('/home', $response->getTargetUrl());
    }

    /**
     * An empty toolforge.oauth.logged_in_user parameter must start the normal OAuth handshake.
     */
    public function testLoginWithoutDevelopmentUser(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $controller = $this->getController('');

        $token = new Token('token_key', 'token_secret');
        $client = $this->createMock(Client::class);
        $client->expects(static::once())
            ->method('initiate')
            ->willReturn([ 'https://meta.example.org/authorize', $token ]);

        $response = $controller->loginAction(new Request(), $client, $session);

        static::assertSame('https://meta.example.org/authorize', $response->getTargetUrl());
        static::assertNull($session->get('logged_in_user'));
        static::assertSame($token, $session->get('oauth.request_token'));
    }

    /**
     * The OAuth handshake must only use a callback that points back to this application.
     * @dataProvider provideCallbacks
     * @param string $callback The 'callback' query parameter.
     * @param string|null $expected The expected callback given to the OAuth client, or null for none.
     */
    public function testLoginCallbackPrefix(string $callback, ?string $expected): void
    {
        $client = $this->createMock(Client::class);
        if (null === $expected) {
            $client->expects(static::never())->method('setCallback');
        } else {
            $client->expects(static::once())->method('setCallback')->with($expected);
        }
        $client->method('initiate')
            ->willReturn([ 'https://meta.example.org/authorize', new Token('token_key', 'token_secret') ]);

        $this->getController('')->loginAction(
            new Request([ 'callback' => $callback ]),
            $client,
            new Session(new MockArraySessionStorage())
        );
    }

    /**
     * @return mixed[][]
     */
    public function provideCallbacks(): array
    {
        return [
            'none' => [ '', null ],
            'own callback route' => [
                'https://tool.example.org/oauth_callback?redirect=https%3A%2F%2Ftool.example.org%2Fmy-page',
                'https://tool.example.org/oauth_callback?redirect=https%3A%2F%2Ftool.example.org%2Fmy-page',
            ],
            'other host' => [ 'https://evil.example.com/oauth_callback', null ],
            'other path' => [ 'https://tool.example.org/elsewhere', null ],
        ];
    }

    /**
     * Get an AuthController with the minimum set of services that AbstractController needs.
     * @param string $loggedInUser Value of the toolforge.oauth.logged_in_user parameter.
     */
    private function getController(string $loggedInUser): AuthController
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')
            ->willReturnCallback(static function (string $route, array $params, int $type): string {
                $prefix = UrlGeneratorInterface::ABSOLUTE_URL === $type ? 'https://tool.example.org' : '';
                return $prefix.'/'.str_replace('toolforge_', '', $route);
            });
        $container = new Container(new ParameterBag([
            'toolforge.oauth.logged_in_user' => $loggedInUser,
        ]));
        $controller = new AuthController();
        $controller->setContainer(new ServiceLocator([
            'parameter_bag' => static function () use ($container): ContainerBag {
                return new ContainerBag($container);
            },
            'router' => static function () use ($router): RouterInterface {
                return $router;
            },
        ]));
        return $controller;
    }
}
