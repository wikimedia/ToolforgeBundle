<?php

namespace Wikimedia\ToolforgeBundle\Controller;

use Exception;
use MediaWiki\OAuthClient\Client;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;

class AuthController extends Controller {

    /**
     * Redirect to Meta for Oauth authentication.
     * @Route("/login", name="login")
     * @return RedirectResponse
     * @throws Exception If initialization fails.
     * @codeCoverageIgnore
     */
    public function loginAction(Client $oauthClient, Session $session)
    {
        $config = $this->getParameter('toolforge.oauth.logged_in_user');

        if (isset($config['oauth']['logged_in_user']) && $config['oauth']['logged_in_user']) {
            $this->get('session')->set('logged_in_user', (object) [
                'username' => $config['oauth']['logged_in_user'],
            ]);
            return new RedirectResponse($config['oauth']['redirect_to']);
        }

        list($next, $token) = $oauthClient->initiate();

        // Save the request token to the session.
        $session->set('oauth.request_token', $token);

        return new RedirectResponse($next);
    }

    /**
     * Receive authentication credentials back from the OAuth wiki.
     * @Route("/oauth_callback", name="OAuthCallback")
     * @param Request $request The HTTP request.
     * @return RedirectResponse
     */
    public function oauthCallbackAction(Request $request, Session $session, Client $client)
    {
        // Give up if the required GET params don't exist.
        if (!$request->get('oauth_verifier')) {
            throw $this->createNotFoundException('No OAuth verifier given.');
        }

        // Complete authentication.
        $token = $session->get('oauth.request_token');
        $verifier = $request->get('oauth_verifier');
        $accessToken = $client->complete($token, $verifier);

        // Store access token, and remove request token.
        $session->set('oauth.access_token', $accessToken);
        $session->remove('oauth.request_token');

        // Store user identity.
        $ident = $client->identify($accessToken);
        $session->set('logged_in_user', $ident);

        // Send to homepage.
        return $this->redirectToRoute('home');
    }

    /**
     * Log out the user and return to the homepage.
     * @Route("/logout", name="logout")
     * @return RedirectResponse
     */
    public function logoutAction(Session $session)
    {
        $session->invalidate();
        return $this->redirectToRoute('home');
    }

}
