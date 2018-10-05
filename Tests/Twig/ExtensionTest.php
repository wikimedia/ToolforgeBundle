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
        static::assertArraySubset(['en' => 'English'], $allLangs);
        static::assertArraySubset(['de' => 'Deutsch'], $allLangs);

        // Testing if the language is RTL.
        static::assertFalse($this->extension->isRtl('en'));
        static::assertTrue($this->extension->isRtl('ar'));
    }

    public function testBdi(): void
    {
        static::assertEquals('<bdi>Foo</bdi>',$this->extension->bdi('Foo'));
        static::assertEquals('',$this->extension->bdi(''));
    }
}
