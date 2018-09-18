<?php

namespace Wikimedia\ToolforgeBundle\Twig;

use Krinkle\Intuition\Intuition;
use Twig\Extension\AbstractExtension;
use Twig_Function;

class Extension extends AbstractExtension {

    /** @var Intuition */
    protected $intuition;

    /** @var string Full filesystem path to the `i18n/` directory. */
    protected $i18nDir;

    public function __construct(Intuition $intuition, $rootDir, $domain)
    {
        $this->intuition = $intuition;
        $this->i18nDir = $rootDir.'/i18n/';
        $this->domain = $domain;
    }

    /**
     * Get all functions that this class provides.
     * @return array
     */
    public function getFunctions()
    {
        $options = ['is_safe' => ['html']];
        return [
            new Twig_Function('msg', [$this, 'msg'], $options),
            new Twig_Function('lang', [$this, 'getLang'], $options),
            new Twig_Function('lang_name', [$this, 'getLangName'], $options),
            new Twig_Function('all_langs', [$this, 'getAllLangs']),
            new Twig_Function('is_rtl', [$this, 'isRtl']),
        ];
    }

    /**
     * Get an i18n message.
     * @param string $message
     * @param array $vars
     * @return mixed|null|string
     */
    public function msg($message = '', $vars = [])
    {
        return $this->intuition->msg($message, [
            'domain' => $this->domain,
            'variables' => $vars
        ]);
    }

    /**
     * Get the current language code.
     * @return string
     */
    public function getLang()
    {
        return $this->intuition->getLang();
    }

    /**
     * Get the current language name (defaults to 'English').
     * @return string
     */
    public function getLangName($lang = false)
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
    public function getAllLangs()
    {
        $messageFiles = glob($this->i18nDir.'/*.json');
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
        asort($availableLanguages);
        return $availableLanguages;
    }

    /**
     * Whether the current (or specified) language is right-to-left.
     * @param string|bool $lang Language code (if false, will use current language).
     * @return bool
     */
    public function isRtl($lang = false)
    {
        if ($lang) {
            return $this->intuition->isRtl($lang);
        }
        return $this->intuition->isRtl($this->intuition->getLang());
    }
}
