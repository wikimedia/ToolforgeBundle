<?php

declare(strict_types = 1);

namespace Wikimedia\ToolforgeBundle\Asset;

use CSSJanus;
use Krinkle\Intuition\Intuition;
use Symfony\Component\Asset\Context\ContextInterface;
use Symfony\Component\Asset\PathPackage;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;
use Symfony\Component\Filesystem\Filesystem;

class CssJanusPackage extends PathPackage
{

    /** @var Intuition */
    protected $intuition;

    /** @var string */
    protected $kernelProjectDir;

    public function __construct(
        VersionStrategyInterface $versionStrategy,
        Intuition $intuition,
        string $kernelProjectDir,
        ?ContextInterface $context = null
    ) {
        parent::__construct('', $versionStrategy, $context);
        $this->kernelProjectDir = $kernelProjectDir;
        $this->intuition = $intuition;
    }

    /**
     * Returns an absolute or root-relative public path.
     *
     * @param string $path A path
     *
     * @return string The public path
     */
    public function getUrl($path) :string // @codingStandardsIgnoreLine
    {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $isRtl = $this->intuition->isRtl();
        $ltrCssUrl = parent::getUrl($path);
        if ('css' !== $ext || !$isRtl) {
            // Do no further processing if this isn't CSS and RTL.
            return $ltrCssUrl;
        }

        // Get the LTR filename.
        $versionedPath = $this->getVersionStrategy()->applyVersion($path);
        $ltrCssFilename = $this->kernelProjectDir.'/public/'.$versionedPath;

        // Construct the RTL URL and filename.
        $rtlSuffix = '_rtl.css';
        $rtlCssUrl = substr($ltrCssUrl, 0, -4).$rtlSuffix;
        $rtlCssFilename = substr($ltrCssFilename, 0, -4).$rtlSuffix;

        // If the RTL CSS file already exists, return its URL.
        if (file_exists($rtlCssFilename)) {
            return $rtlCssUrl;
        }

        // Otherwise, generate the RTL CSS file.
        // The file generation could also have been done externally by the Encore processor.
        $fileSystem = new Filesystem();
        $ltrCss = file_get_contents($ltrCssFilename);
        $rtlCss = CSSJanus::transform($ltrCss);
        $fileSystem->dumpFile($rtlCssFilename, $rtlCss);

        // Return the new RTL CSS URL.
        return $rtlCssUrl;
    }
}
