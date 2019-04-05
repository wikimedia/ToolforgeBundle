<?php

declare(strict_types=1);

namespace Wikimedia\ToolforgeBundle\Twig;

use Krinkle\Intuition\Intuition;
use NumberFormatter;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Process\Process;
use Twig\Extension\AbstractExtension;
use Twig_Filter;
use Twig_Function;
use Twig_SimpleFilter;

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
        Session $session,
        string $domain
    ) {
        $this->intuition = $intuition;
        $this->session = $session;
        $this->domain = $domain;
    }

    /**
     * Get all functions that this extension provides.
     * @return Twig_Function[]
     */
    public function getFunctions(): array
    {
        $options = ['is_safe' => ['html']];
        return [
            new Twig_Function('logged_in_user', [$this, 'getLoggedInUser'], $options),
            new Twig_Function('msg', [$this, 'msg'], $options),
            new Twig_Function('bdi', [$this, 'bdi'], $options),
            new Twig_Function('msg_exists', [$this, 'msgExists'], $options),
            new Twig_Function('msg_if_exists', [$this, 'msgIfExists'], $options),
            new Twig_Function('lang', [$this, 'getLang'], $options),
            new Twig_Function('lang_name', [$this, 'getLangName'], $options),
            new Twig_Function('all_langs', [$this, 'getAllLangs']),
            new Twig_Function('is_rtl', [$this, 'isRtl']),
            new Twig_Function('git_branch', [$this, 'gitBranch']),
            new Twig_Function('git_hash', [$this, 'gitHash']),
            new Twig_Function('git_hash_short', [$this, 'gitHashShort']),
        ];
    }

    /**
     * Get the currently logged in user's details, as returned by \MediaWiki\OAuthClient\Client::identify() when the
     * user logged in.
     * @return string[]|bool
     */
    public function getLoggedInUser()
    {
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
     * Parameters the same as msg(), except $fail which is overwritten.
     * @param string $message The message.
     * @param string[] $vars
     * @return bool
     */
    public function msgExists(string $message = '', array $vars = []): bool
    {
        return $this->intuition->msgExists($message, [
            'domain' => $this->domain,
            'variables' => is_array($vars) ? $vars : [],
        ]);
    }

    /**
     * Get an i18n message.
     * @param string $message
     * @param string[] $vars
     * @return mixed|null|string
     */
    public function msg(string $message = '', array $vars = [])
    {
        return $this->intuition->msg($message, [
            'domain' => $this->domain,
            'variables' => $vars,
        ]);
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
     * @return Twig_Filter[]
     */
    public function getFilters(): array
    {
        return [
            new Twig_SimpleFilter('num_format', [$this, 'numberFormat']),
            new Twig_SimpleFilter('list_format', [$this, 'listFormat']),
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
