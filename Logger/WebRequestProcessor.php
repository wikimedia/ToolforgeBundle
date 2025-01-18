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
     * @param mixed[]|LogRecord $record
     * @return mixed[]|LogRecord
     */
    public function __invoke(mixed $record): mixed
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request) {
            if ($record instanceof LogRecord) {
                $record->offsetSet('extra', [
                    'host' => $request->getHost(),
                    'uri' => $request->getUri(),
                ]);
            } else {
                $record['extra']['host'] = $request->getHost();
                $record['extra']['uri'] = $request->getUri();
            }
        }

        return $record;
    }
}
