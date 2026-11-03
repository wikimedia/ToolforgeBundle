<?php

declare(strict_types=1);

namespace Wikimedia\ToolforgeBundle\Service;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ReplicasClient
{

    /** @var string Prefix URL for where the dblists live. Will be followed by i.e. 's1.dblist' */
    public const DBLISTS_URL = 'https://noc.wikimedia.org/conf/dblists/';

    /** @var int DateInterval duration for how long the dblist should be cached. */
    public const DBLIST_CACHE_DURATION = 60 * 60 * 24 * 7; // 1 week

    /**
     * @var string[] Wikis whose links tables are on a section of their own. Keys are the database
     *   name (without the _p suffix), values are the section. Commons moved its links tables to
     *   x4 (T398709). These sections have no dblist at noc.wikimedia.org, so they are listed here.
     *
     *   A links section holds the links tables (pagelinks, templatelinks, categorylinks,
     *   imagelinks, iwlinks, langlinks, externallinks, globalimagelinks, existencelinks,
     *   linktarget and collation), and copies of `page` and `redirect`. It holds no other table
     *   of the wiki. A query on this section can join the links tables against `page` and
     *   `redirect`. A query that also needs `revision`, `actor` or any other table must be
     *   split: query each section, then join the results in PHP.
     */
    public const LINKS_SLICES = [
        'commonswiki' => 'x4',
    ];

    /**
     * @var string The wiki replicas expose a links section under an alias of this form, followed
     *   by the database name, the service and the host suffix. See SshCommand::HOST_SUFFIX.
     */
    public const LINKS_HOST_PREFIX = 'links';

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
     *
     * Some wikis keep their links tables on a section of their own, which this connection does
     * not reach. Use getLinksConnection() for those tables. See self::LINKS_SLICES.
     *
     * @param string $db The desired database to connect to, with or without the _p suffix.
     * @param bool $useDb Whether to set the connection to USE the given $db. This usually costs
     *   about 20-50ms, so for cross-wiki tools it is best practice to prefix the table name
     *   with the database in your SQL, and call this method with $useDb set to false.
     * @return Connection
     */
    public function getConnection(string $db, bool $useDb = true): Connection
    {
        $db = $this->normalizeDbName($db);
        return $this->getConnectionForSlice($this->getDbList()[$db], $db, $useDb);
    }

    /**
     * Get a Doctrine Connection instance for the links tables of the given database.
     *
     * This is the same connection as getConnection() unless the wiki keeps its links tables on a
     * section of their own, such as Commons on x4. That section also holds copies of `page` and
     * `redirect`, so a query on this connection can join the links tables against those two. It
     * holds no other table of the wiki. For `revision`, `actor` or anything else, run a second
     * query on getConnection(), then join the results in PHP.
     *
     * @param string $db The desired database to connect to, with or without the _p suffix.
     * @param bool $useDb Whether to set the connection to USE the given $db. See getConnection().
     * @return Connection
     */
    public function getLinksConnection(string $db, bool $useDb = true): Connection
    {
        $db = $this->normalizeDbName($db);
        return $this->getConnectionForSlice($this->getLinksSlice($db), $db, $useDb);
    }

    /**
     * Whether the links tables of the given database are on a section of their own.
     * Use this to decide if a query must be split in two. See getLinksConnection().
     * @param string $db With or without the _p suffix.
     * @return bool
     */
    public function hasSplitLinks(string $db): bool
    {
        return isset(self::LINKS_SLICES[$this->normalizeDbName($db)]);
    }

    /**
     * Get the section that holds the links tables of the given database. This is the wiki's own
     * section unless the links tables are split out. See self::LINKS_SLICES.
     * @param string $db With or without the _p suffix.
     * @return string Such as 'x4' or 's4'.
     */
    public function getLinksSlice(string $db): string
    {
        $db = $this->normalizeDbName($db);
        return self::LINKS_SLICES[$db] ?? $this->getDbList()[$db];
    }

    /**
     * Get the links sections that the application has a Doctrine connection for. Sections with no
     * connection are left out, because the application does not query them. This keeps
     * toolforge:ssh working for tools that have not added the new connection yet.
     * @return string[] Keys are the section (i.e. 'x4'), values are the hostname prefix used by
     *   the wiki replicas (i.e. 'links.commonswiki').
     */
    public function getConfiguredLinksSlices(): array
    {
        $slices = [];
        $connectionNames = $this->driver->getConnectionNames();

        foreach (self::LINKS_SLICES as $db => $slice) {
            if (isset($connectionNames['toolforge_'.$slice])) {
                $slices[$slice] = self::LINKS_HOST_PREFIX.'.'.$db;
            }
        }

        return $slices;
    }

    /**
     * Get the Connection for the given section, optionally switched to the given database.
     * @param string $slice Such as 's1' or 'x4'.
     * @param string $db Database name without the _p suffix.
     * @param bool $useDb Whether to USE the given $db.
     * @return Connection
     */
    private function getConnectionForSlice(string $slice, string $db, bool $useDb): Connection
    {
        /** @var Connection $conn */
        $conn = $this->driver->getConnection('toolforge_'.$slice);

        if ($useDb) {
            $conn->executeQuery('USE '.$db.'_p');
        }

        return $conn;
    }

    /**
     * Remove the _p suffix from the given database name, if it has one. Only the suffix is
     * removed, because some database names contain '_p' (i.e. 'arbcom_plwiki').
     * @param string $db
     * @return string
     */
    private function normalizeDbName(string $db): string
    {
        return preg_replace('/_p$/', '', $db);
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
