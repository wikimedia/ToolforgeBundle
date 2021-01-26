<?php

declare(strict_types=1);

namespace Wikimedia\ToolforgeBundle\Service;

class Util
{

    /**
     * MediaWiki's version of urlencode(), which doesn't encode these: ;:@&=$-_.+!*'(),
     *
     * Logic is copied from MediaWiki's includes/GlobalFunctions.php file:
     * https://gerrit.wikimedia.org/r/plugins/gitiles/mediawiki/core/+/c8b1375fb9883f076c44fdd41508236c60fe8465/includes/GlobalFunctions.php#312
     *
     * @param string $str
     * @return string
     */
    public static function wfUrlencode(string $str): string
    {
        return str_ireplace(
            ['%3B', '%40', '%24', '%21', '%2A', '%28', '%29', '%2C', '%2F', '%7E', '%3A'],
            [';', '@', '$', '!', '*', '(', ')', ',', '/', '~', ':'],
            urlencode($str)
        );
    }
}
