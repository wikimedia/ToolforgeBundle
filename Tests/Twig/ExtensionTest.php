<?php

declare(strict_types=1);

namespace Wikimedia\ToolforgeBundle\Tests\Twig;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Wikimedia\ToolforgeBundle\Service\Intuition;
use Wikimedia\ToolforgeBundle\Twig\Extension;

class ExtensionTest extends TestCase
{

    /** @var Extension */
    protected $extension;

    public function setUp(): void
    {
        parent::setUp();
        $session = new Session();
        $domain = 'toolforge';
        $rootDir = dirname(__DIR__, 2);
        $intuition = Intuition::serviceFactory(new RequestStack(), $session, $rootDir, $domain);
        $this->extension = new Extension($intuition, new Session(), $domain);
    }

    public function testBasics(): void
    {
        static::assertEquals('en', $this->extension->getLang());
        static::assertEquals('English', $this->extension->getLangName());

        $allLangs = $this->extension->getAllLangs();

        // There should be a bunch.
        static::assertGreaterThan(0, count($allLangs));

        // Keys should be the language codes, with name as the values.
        static::assertArrayHasKey('en', $allLangs);
        static::assertSame('English', $allLangs['en']);
        static::assertArrayHasKey('de', $allLangs);
        static::assertSame('Deutsch', $allLangs['de']);

        // Testing if the language is RTL.
        static::assertFalse($this->extension->isRtl('en'));
        static::assertTrue($this->extension->isRtl('ar'));
    }

    public function testBdi(): void
    {
        static::assertEquals('<bdi>Foo</bdi>', $this->extension->bdi('Foo'));
        static::assertEquals('', $this->extension->bdi(''));
    }

    /**
     * Format a number.
     */
    public function testNumberFormat(): void
    {
        static::assertEquals('1,234', $this->extension->numberFormat(1234));
        static::assertEquals('1,234.32', $this->extension->numberFormat(1234.316, 2));
        static::assertEquals('50', $this->extension->numberFormat(50.0000, 4));
    }

    /**
     * Methods that fetch data about the git repository.
     */
    public function testGitMethods(): void
    {
        static::assertEquals(7, strlen($this->extension->gitHashShort()));
        static::assertEquals(40, strlen($this->extension->gitHash()));
    }
}
