<?php

declare(strict_types=1);

namespace Wikimedia\ToolforgeBundle\Logger;

use Monolog\LogRecord;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * WebRequestProcessor extends information included in error reporting.
 */
class WebRequestProcessor
{
    /** @var RequestStack The request stack. */
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * Adds extra information to the log entry.
     * @see https://symfony.com/doc/current/logging/processors.html
     * @param array|LogRecord $record
     * @return array|LogRecord
     */
    public function __invoke($record): mixed
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request) {
            $record['extra']['host'] = $request->getHost();
            $record['extra']['uri'] = $request->getUri();
        }

        return $record;
    }
}
