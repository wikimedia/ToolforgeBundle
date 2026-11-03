<?php

declare(strict_types=1);

namespace Wikimedia\ToolforgeBundle\Tests\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpClient\HttpClient;
use Wikimedia\ToolforgeBundle\Service\ReplicasClient;

class ReplicasClientTest extends TestCase
{

    /** @var ReplicasClient */
    protected $client;

    public function setUp(): void
    {
        parent::setUp();
        $this->client = $this->getClient();
    }

    /**
     * Build a client whose Doctrine registry knows about the given connections.
     * @param string[] $connections Keys are connection names, values are service ids.
     * @return ReplicasClient
     */
    private function getClient(array $connections = []): ReplicasClient
    {
        return new ReplicasClient(
            HttpClient::create(),
            new FilesystemAdapter(),
            new Registry(new Container(), $connections, [], '', '')
        );
    }

    /**
     * @covers ReplicasRepository::fetchDbLists()
     */
    public function testGetDbList(): void
    {
        $dbList = $this->client->getDbList();

        $this->assertArrayHasKey('enwiki', $dbList);

        // This should be future-proof.
        $this->assertEquals('s1', $dbList['enwiki']);
    }

    /**
     * @covers ReplicasClient::hasSplitLinks()
     */
    public function testHasSplitLinks(): void
    {
        $this->assertTrue($this->client->hasSplitLinks('commonswiki'));
        $this->assertTrue($this->client->hasSplitLinks('commonswiki_p'));
        $this->assertFalse($this->client->hasSplitLinks('enwiki'));
        // The _p suffix is removed, but '_p' inside the name is not.
        $this->assertFalse($this->client->hasSplitLinks('arbcom_plwiki'));
    }

    /**
     * @covers ReplicasClient::getLinksSlice()
     */
    public function testGetLinksSlice(): void
    {
        // Commons keeps its links tables on x4, not on s4 where the rest of the wiki lives.
        $this->assertEquals('x4', $this->client->getLinksSlice('commonswiki'));
        $this->assertEquals('s4', $this->client->getDbList()['commonswiki']);

        // Wikis with no split fall back to their own section.
        $this->assertEquals('s1', $this->client->getLinksSlice('enwiki'));

        // A name that contains '_p' resolves to its own section, and is not mangled.
        $this->assertEquals(
            $this->client->getDbList()['arbcom_plwiki'],
            $this->client->getLinksSlice('arbcom_plwiki')
        );
    }

    /**
     * @covers ReplicasClient::getConfiguredLinksSlices()
     */
    public function testGetConfiguredLinksSlices(): void
    {
        // A tool that has not added the connection sees no links sections, so that
        // toolforge:ssh keeps working for it.
        $this->assertSame([], $this->getClient()->getConfiguredLinksSlices());

        $client = $this->getClient(['toolforge_x4' => 'doctrine.dbal.toolforge_x4_connection']);
        $this->assertSame(['x4' => 'links.commonswiki'], $client->getConfiguredLinksSlices());
    }
}
