<?php

declare(strict_types=1);

namespace Wikimedia\ToolforgeBundle\Twig;

use NumberFormatter;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Process\Process;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Wikimedia\ToolforgeBundle\Service\Intuition;

class Extension extends AbstractExtension
{

    /** @var Intuition */
    protected $intuition;

    /** @var Session */
    protected $session;

    /** @var string */
    protected $domain;

    /** @var NumberFormatter Used in localizing numbers in the `num_format` filter. */
    protected $numberFormatter;

    public function __construct(
        Intuition $intuition,
        RequestStack $requestStack,
        string $domain
    ) {
        $this->intuition = $intuition;
        if ($requestStack->getCurrentRequest() && $requestStack->getCurrentRequest()->hasSession()) {
            $this->session = $requestStack->getCurrentRequest()->getSession();
        }
        $this->domain = $domain;
    }

    /**
     * Get all functions that this extension provides.
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        $rawHtml = ['is_safe' => ['html']];
        return [
            new TwigFunction('logged_in_user', [$this, 'getLoggedInUser']),
            new TwigFunction('msg', [$this, 'msg'], $rawHtml),
            new TwigFunction('bdi', [$this, 'bdi'], $rawHtml),
            new TwigFunction('msg_exists', [$this, 'msgExists'], $rawHtml),
            new TwigFunction('msg_if_exists', [$this, 'msgIfExists'], $rawHtml),
            new TwigFunction('lang', [$this, 'getLang']),
            new TwigFunction('lang_name', [$this, 'getLangName']),
            new TwigFunction('all_langs', [$this, 'getAllLangs']),
            new TwigFunction('is_rtl', [$this, 'isRtl']),
            new TwigFunction('git_tag', [$this, 'gitTag']),
            new TwigFunction('git_branch', [$this, 'gitBranch']),
            new TwigFunction('git_hash', [$this, 'gitHash']),
            new TwigFunction('git_hash_short', [$this, 'gitHashShort']),
        ];
    }

    /**
     * Get the currently logged in user's details, as returned by \MediaWiki\OAuthClient\Client::identify() when the
     * user logged in.
     * @return string[]|bool
     */
    public function getLoggedInUser()
    {
        if (!$this->session) {
            return false;
        }
        return $this->session->get('logged_in_user');
    }

    /**
     * Get an i18n message if the key exists, otherwise treat as plain text.
     * @param string $message
     * @param string[] $vars
     * @return mixed|null|string
     */
    public function msgIfExists(string $message = '', array $vars = [])
    {
        $exists = $this->msgExists($message, $vars);
        if ($exists) {
            return $this->msg($message, $vars);
        }
        return $message;
    }

    /**
     * See if a given i18n message exists.
     * If this returns false it means msg() would return "[message-key]"
     *
     * @param string $message The message.
     * @param string[] $vars
     * @return bool
     */
    public function msgExists(string $message = '', array $vars = []): bool
    {
        foreach ($this->intuition->getDomains() as $domain) {
            $exists = $this->intuition->msgExists($message, [
                'domain' => $domain,
                'variables' => is_array($vars) ? $vars : [],
            ]);
            if ($exists) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get an i18n message, searching all registered domains.
     * @param string $message
     * @param string[] $vars
     * @return mixed|null|string
     */
    public function msg(string $message = '', array $vars = [])
    {
        // Check all domains for this message.
        foreach ($this->intuition->getDomains() as $domain) {
            if ($this->intuition->msgExists($message, ['domain' => $domain])) {
                return $this->intuition->msg($message, [
                    'domain' => $domain,
                    'variables' => $vars,
                ]);
            }
        }
        return $this->intuition->bracketMsg($message);
    }

    /**
     * Wrap text with bdi tags for bidirectional isolation
     * @param string $text Text to be wrapped
     * @return string Text wrapped with bdi tags, or empty string
     *  if an empty string was originally given
     */
    public function bdi(string $text = ''): string
    {
        if (!empty($text)) {
            return '<bdi>'.$text.'</bdi>';
        }
        return '';
    }

    /**
     * Get the current language code.
     * @return string
     */
    public function getLang(): string
    {
        return $this->intuition->getLang();
    }

    /**
     * Get the current language name (defaults to 'English').
     * @return string
     */
    public function getLangName(?string $lang = null): string
    {
        if ($lang) {
            return $this->intuition->getLangName($lang);
        }
        return $this->intuition->getLangName($this->intuition->getLang());
    }

    /**
     * Get all available languages in the i18n directory.
     * @return string[] Associative array of langKey => langName
     */
    public function getAllLangs(): array
    {
        $domainInfo = $this->intuition->getDomainInfo($this->domain);
        $messageFiles = glob($domainInfo['dir'].'/*.json');
        $languages = array_values(array_unique(array_map(
            function ($filename) {
                return basename($filename, '.json');
            },
            $messageFiles
        )));
        $availableLanguages = [];
        foreach ($languages as $lang) {
            $availableLanguages[$lang] = $this->intuition->getLangName($lang);
        }
        ksort($availableLanguages);
        return $availableLanguages;
    }

    /**
     * Whether the current (or specified) language is right-to-left.
     * @param string|bool $lang Language code (if false, will use current language).
     * @return bool
     */
    public function isRtl($lang = false): bool
    {
        if ($lang) {
            return $this->intuition->isRtl($lang);
        }
        return $this->intuition->isRtl($this->intuition->getLang());
    }

    /**
     * Get the current Git tag, or the short hash if there's no tags.
     * @return string
     */
    public function gitTag(): string
    {
        $process = new Process(['git', 'describe', '--tags', '--always']);
        $process->run();
        if (!$process->isSuccessful()) {
            return $this->gitHashShort();
        }
        return trim($process->getOutput());
    }

    /**
     * Get the currently checked-out Git branch.
     * @return string
     */
    public function gitBranch(): string
    {
        $process = new Process(['git', 'rev-parse', '--symbolic-full-name', '--abbrev-ref', 'HEAD']);
        $process->run();
        return trim($process->getOutput());
    }

    /**
     * Get the full hash of the currently checked-out Git commit.
     * @return string
     */
    public function gitHash(): string
    {
        $process = new Process(['git', 'rev-parse', 'HEAD']);
        $process->run();
        return trim($process->getOutput());
    }

    /**
     * Get the short hash of the currently checked-out Git commit.
     * @return string
     */
    public function gitHashShort(): string
    {
        $process = new Process(['git', 'rev-parse', '--short', 'HEAD']);
        $process->run();
        return trim($process->getOutput());
    }

    /**
     * Get all filters that this extension provides.
     * @return TwigFilter[]
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('num_format', [$this, 'numberFormat']),
            new TwigFilter('list_format', [$this, 'listFormat']),
        ];
    }

    /**
     * Format a number based on language settings.
     * @param int|float $number
     * @param int $decimals Number of decimals to format to.
     * @return string
     */
    public function numberFormat($number, int $decimals = 0): string
    {
        if (!isset($this->numberFormatter)) {
            $this->numberFormatter = new NumberFormatter($this->intuition->getLang(), NumberFormatter::DECIMAL);
        }
        $this->numberFormatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
        return $this->numberFormatter->format($number);
    }

    /**
     * Format a list of values. In English this is a comma-separated list with the last item separated with 'and'.
     * @param string[] $list The list items.
     * @return string
     */
    public function listFormat(array $list): string
    {
        return $this->intuition->listToText($list);
    }
}
