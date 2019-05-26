<?php

declare(strict_types=1);

namespace Wikimedia\ToolforgeBundle\Controller;

use MediaWiki\OAuthClient\Client;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;

class AuthController extends AbstractController
{

    /**
     * Redirect to Meta for Oauth authentication.
     * @Route("/login", name="toolforge_login")
     * @return RedirectResponse
     */
    public function loginAction(Request $request, Client $oauthClient, Session $session): RedirectResponse
    {
        // Automatically log in a development user if defined.
        $loggedInUser = $this->getParameter('toolforge.oauth.logged_in_user');
        if ($loggedInUser) {
            $this->get('session')->set('logged_in_user', (object)[
                'username' => $loggedInUser,
            ]);
            return $this->redirectToRoute('home');
        }

        // Set the callback URL if given. Used to redirect back to target page after logging in.
        if ($request->query->get('callback')) {
            $oauthClient->setCallback($request->query->get('callback'));
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
     * @Route("/oauth_callback", name="toolforge_oauth_callback")
     * @param Request $request The HTTP request.
     * @return RedirectResponse
     */
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
     * @Route("/logout", name="toolforge_logout")
     * @return RedirectResponse
     */
    public function logoutAction(Session $session): RedirectResponse
    {
        $session->invalidate();
        return $this->redirectToRoute('home');
    }
}
