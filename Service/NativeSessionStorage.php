<?php

declare(strict_types=1);

namespace Wikimedia\ToolforgeBundle\Service;

use SessionHandlerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage as SymfonyNativeSessionStorage;

class NativeSessionStorage extends SymfonyNativeSessionStorage
{

    /**
     * @param string[] $options Session configuration options
     * @param SessionHandlerInterface|null $handler
     * @param MetadataBag $metaBag MetadataBag
     * @param MetadataBag|null $metaBag
     * @param RequestStack|null $requestStack
     */
    public function __construct(
        ?array $options = array(),
        ?SessionHandlerInterface $handler = null,
        ?MetadataBag $metaBag = null,
        ?RequestStack $requestStack = null
    ) {
        $masterRequest = $requestStack->getMasterRequest();
        if ($masterRequest) {
            // If we're in a request, use the base URL as the cookie path.
            $options['cookie_path'] = $masterRequest->getBaseUrl().'/';
        }
        parent::__construct($options, $handler, $metaBag);
    }
}
