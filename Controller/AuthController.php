<?php

declare(strict_types=1);

namespace Wikimedia\ToolforgeBundle\Controller;

use MediaWiki\OAuthClient\Client;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AuthController extends AbstractController
{

    /**
     * Redirect to Meta for Oauth authentication.
     * @return RedirectResponse
     */
    #[Route("/login", name:"toolforge_login")]
    public function loginAction(Request $request, Client $oauthClient, Session $session): RedirectResponse
    {
        // Get the callback URL if it is given and if it matches the required prefix.
        // The prefixed callback is used to redirect back to target page after logging in.
        $callback = $this->getValidCallback($request);

        // Automatically log in a development user if defined.
        $loggedInUser = $this->getParameter('toolforge.oauth.logged_in_user');
        if ($loggedInUser) {
            $session->set('logged_in_user', (object)[
                'username' => $loggedInUser,
            ]);
            // Go to the same page that the OAuth callback sends the user to.
            $redirect = $this->getRedirectFromCallback($callback);
            if ('' !== $redirect) {
                return $this->redirect($redirect);
            }
            return $this->redirectToRoute('home');
        }

        // Set the callback URL. If there's no prefix registered with the consumer
        // then we can't supply our own callback.
        if ('' !== $callback) {
            $oauthClient->setCallback($callback);
        }

        // Continue with OAuth authentication.
        [$next, $token] = $oauthClient->initiate();

        // Save the request token to the session.
        $session->set('oauth.request_token', $token);

        // Send the user to Meta Wiki.
        return new RedirectResponse($next);
    }

    /**
     * Receive authentication credentials back from the OAuth wiki.
     * @param Request $request The HTTP request.
     * @return RedirectResponse
     */
    #[Route("/oauth_callback", name:"toolforge_oauth_callback")]
    public function oauthCallbackAction(Request $request, Session $session, Client $client): RedirectResponse
    {
        // Give up if the required GET params or stored request token don't exist.
        if (!$request->get('oauth_verifier')) {
            throw $this->createNotFoundException('No OAuth verifier given.');
        }
        if (!$session->has('oauth.request_token')) {
            throw $this->createNotFoundException('No request token found. Please try logging in again.');
        }

        // Complete authentication.
        $token = $session->get('oauth.request_token');
        $verifier = $request->get('oauth_verifier');
        $accessToken = $client->complete($token, $verifier);

        // Store access token, and remove request token.
        $session->set('oauth.access_token', $accessToken);
        $session->remove('oauth.request_token');

        // Regenerate session ID.
        $session->migrate();

        // Store user identity.
        $ident = $client->identify($accessToken);
        $session->set('logged_in_user', $ident);

        // Redirect to callback, if given.
        if ($request->query->get('redirect')) {
            return $this->redirect($request->query->get('redirect'));
        }

        // Otherwise send to homepage.
        return $this->redirectToRoute('home');
    }

    /**
     * Log out the user and return to the homepage.
     * @return RedirectResponse
     */
    #[Route("/logout", name:"toolforge_logout")]
    public function logoutAction(Session $session): RedirectResponse
    {
        $session->invalidate();
        return $this->redirectToRoute('home');
    }

    /**
     * Get the callback URL from the login request.
     * The URL must start with the URL of the toolforge_oauth_callback route.
     * This makes sure that the callback points back to this application.
     * @param Request $request The HTTP request.
     * @return string The callback URL, or an empty string if there is no usable callback.
     */
    private function getValidCallback(Request $request): string
    {
        $callback = (string)$request->query->get('callback', '');
        if ('' === $callback) {
            return '';
        }
        $callbackPrefix = $this->generateUrl('toolforge_oauth_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
        if (!str_starts_with($callback, $callbackPrefix)) {
            return '';
        }
        return $callback;
    }

    /**
     * Get the value of the 'redirect' query parameter of a callback URL.
     * This is the page that oauthCallbackAction() sends the user to after login.
     * @param string $callback The callback URL from getValidCallback().
     * @return string The redirect URL, or an empty string if the callback has no redirect.
     */
    private function getRedirectFromCallback(string $callback): string
    {
        $query = parse_url($callback, PHP_URL_QUERY);
        if (!is_string($query)) {
            return '';
        }
        parse_str($query, $params);
        if (!isset($params['redirect']) || !is_string($params['redirect'])) {
            return '';
        }
        return $params['redirect'];
    }
}
