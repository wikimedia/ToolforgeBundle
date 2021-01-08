<?php
declare(strict_types=1);

namespace Wikimedia\ToolforgeBundle\Service;

use Doctrine\DBAL\Connection;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ReplicasClient
{

    /** @var string Prefix URL for where the dblists live. Will be followed by i.e. 's1.dblist' */
    public const DBLISTS_URL = 'https://noc.wikimedia.org/conf/dblists/';

    /** @var string DateInterval duration for how long the dblist should be cached. */
    public const DBLIST_CACHE_DURATION = 60 * 60 * 24 * 7; // 1 week

    /** @var HttpClientInterface The HTTP client. */
    private $httpClient;

    /** @var ManagerRegistry */
    protected $driver;

    /** @var CacheInterface */
    protected $cache;

    /**
     * ReplicasClient constructor.
     * @param HttpClientInterface $httpClient
     * @param CacheInterface $cache
     * @param ManagerRegistry $driver
     */
    public function __construct(
        HttpClientInterface $httpClient,
        CacheInterface $cache,
        ManagerRegistry $driver
    ) {
        $this->httpClient = $httpClient;
        $this->cache = $cache;
        $this->driver = $driver;
    }

    /**
     * Fetch and concatenate all the dblists into one array.
     * @return string[] Keys are the db name (i.e. 'enwiki'), values are the slice (i.e. 's1')
     */
    public function getDbList(): array
    {
        return $this->cache->get('toolforge.dblists', function (ItemInterface $item) {
            $item->expiresAfter(self::DBLIST_CACHE_DURATION);

            $dbList = [];
            $exists = true;
            $i = 0;

            while ($exists) {
                $i += 1;
                $response = $this->httpClient->request('GET', self::DBLISTS_URL."s$i.dblist");
                $exists = in_array(
                    $response->getStatusCode(),
                    [Response::HTTP_OK, Response::HTTP_NOT_MODIFIED]
                ) && $i < 50; // Safeguard

                if (!$exists) {
                    break;
                }

                $lines = explode("\n", $response->getContent());
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (1 !== preg_match('/^#/', $line) && '' !== $line) {
                        // Skip comments and blank lines.
                        $dbList[$line] = "s$i";
                    }
                }
            }

            return $dbList;
        });
    }

    /**
     * Get a Doctrine Connection instance for the given database.
     * Any databases that live on the same shard will share the same Connection instance.
     * @param string $db The desired database to connect to, with or without the _p suffix.
     * @param bool $useDb Whether to set the connection to USE the given $db. This usually costs
     *   about 20-50ms, so for cross-wiki tools it is best practice to prefix the table name
     *   with the database in your SQL, and call this method with $useDb set to false.
     * @return Connection
     */
    public function getConnection(string $db, bool $useDb = true): Connection
    {
        // Remove _p if given.
        $db = str_replace('_p', '', $db);
        $slice = $this->getDbList()[$db];

        /** @var Connection $conn */
        $conn = $this->driver->getConnection('toolforge_'.$slice);

        if ($useDb) {
            $conn->executeQuery('USE '.$db.'_p');
        }

        return $conn;
    }

    /**
     * Get the port number that should be used for the given slice.
     * @param string $slice Such as 's1', 's2', etc.
     * @return int
     */
    public function getPortForSlice(string $slice): int
    {
        $conn = $this->driver->getConnection('toolforge_'.$slice);
        return (int)$conn->getParams()['port'];
    }
}
