<?php
declare(strict_types=1);


namespace OpenPublicMedia\PbsMembershipVault\Query;

use Countable;
use Generator;
use IteratorAggregate;
use OpenPublicMedia\PbsMembershipVault\Response\PagedResponse;

/**
 * Generator over the "objects" property from a Membership Vault API response.
 *
 * @package OpenPublicMedia\PbsMembershipVault\Query
 */
class Results implements IteratorAggregate, Countable
{
    private PagedResponse $pagedResponse;

    /**
     * ObjectsResponse constructor.
     *
     * @param PagedResponse $pagedResponse
     */
    public function __construct(PagedResponse $pagedResponse)
    {
        $this->pagedResponse = $pagedResponse;
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Generator
    {
        foreach ($this->pagedResponse as $response) {
            foreach ($response->objects as $object) {
                yield $object->membership_id => $object;
            }
        }
    }

    /**
     * Gets the response object being iterated.
     *
     * @return PagedResponse
     */
    public function getResponse(): PagedResponse
    {
        return $this->pagedResponse;
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return $this->pagedResponse->getTotalItemsCount();
    }
}
