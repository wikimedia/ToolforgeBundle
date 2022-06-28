<?php

declare(strict_types=1);

namespace Wikimedia\ToolforgeBundle\Service;

use Krinkle\Intuition\Intuition as KrinkleIntuition;
use Symfony\Component\HttpFoundation\RequestStack;

class Intuition extends KrinkleIntuition
{

    /**
     * @param RequestStack $requestStack
     * @param string $projectDir Root filesystem directory of the application.
     * @param string $domain The i18n domain.
     * @return Intuition
     */
    public static function serviceFactory(
        RequestStack $requestStack,
        string $projectDir,
        string $domain
    ): Intuition {
        // Default language.
        $useLang = 'en';

        // Current request doesn't exist in unit tests, in which case we'll fall back to English.
        if (null !== $requestStack->getCurrentRequest()) {
            $currentRequest = $requestStack->getCurrentRequest();
            // Use lang from the 'lang' query parameter or the 'lang' session variable.
            $queryLang = false;
            if ($currentRequest->query->has('uselang')) {
                $queryLang = $currentRequest->query->has('uselang');
                if (!empty($queryLang)) {
                    $useLang = $queryLang;
                }
            }
            $sessionLang = false;
            if ($currentRequest->hasSession()) {
                $session = $currentRequest->getSession();
                $sessionLang = $session->get('lang');
                if (!empty($sessionLang)) {
                    $useLang = $sessionLang;
                }
                // Save the language to the session.
                if ($session->get('lang') !== $useLang) {
                    $session->set('lang', $useLang);
                }
            }
        }

        // Set up Intuition, using the selected language.
        $intuition = new static(['domain' => $domain]);
        $intuition->registerDomain($domain, $projectDir.'/i18n');
        $intuition->registerDomain('toolforge', dirname(__DIR__).'/Resources/i18n');
        $intuition->setLang(strtolower($useLang));

        // Also add US English, so we can access the locale information (e.g. for date formatting).
        $intuition->addAvailableLang('en-us', 'US English');

        return $intuition;
    }

    /**
     * Get names of all registered domains.
     *
     * @return string[]
     */
    public function getDomains(): array
    {
        return array_keys($this->domainInfos);
    }
}
