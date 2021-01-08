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
        $this->client = new ReplicasClient(
            HttpClient::create(),
            new FilesystemAdapter(),
            new Registry(new Container(), [], [], '', '')
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
        $this->assertEquals($dbList['enwiki'], 's1');
    }
}
