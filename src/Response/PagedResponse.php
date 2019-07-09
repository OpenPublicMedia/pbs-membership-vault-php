<?php
declare(strict_types=1);


namespace OpenPublicMedia\PbsMembershipVault\Response;

use Countable;
use GuzzleHttp\Psr7\Response;
use Iterator;
use OpenPublicMedia\PbsMembershipVault\Client;
use stdClass;

/**
 * Page-traversable response data from the Membership Vault API in JSON format.
 *
 * @package OpenPublicMedia\PbsMembershipVault\Response
 */
class PagedResponse implements Iterator, Countable
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var string
     */
    private $endpoint;

    /**
     * @var array
     */
    private $query;

    /**
     * @var int
     */
    private $first;

    /**
     * @var int
     */
    private $page;

    /**
     * @var int
     */
    private $count;

    /**
     * @var int
     */
    private $totalItemsCount;

    /**
     * @var array
     */
    private $response;

    /**
     * PagedResponse constructor.
     *
     * @param Client $client
     *   API client used for requests.
     * @param string $endpoint
     *   Endpoint to query.
     * @param array $query
     *   Additional API query parameters.
     * @param int $page
     *   Starting page. This also acts as the first page for the Iterator so
     *   "first" may not necessarily mean page 1.
     */
    public function __construct(Client $client, $endpoint, $query = [], $page = 1)
    {
        $this->client = $client;
        $this->endpoint = $endpoint;
        $this->query = $query;
        $this->first = $page;
        $this->page = $page;

        // Execute the initial query to init count data.
        $this->response = $this->execute();
    }

    /**
     * Executes an API query and update count data for the iterator.
     *
     * @return object
     *   The full API response as an object.
     */
    private function execute(): stdClass
    {
        $query = $this->query;
        if ($this->page > 1) {
            $query = $query + ['page' => $this->page];
        }
        $response = $this->client->request('get', $this->endpoint, ['query' => $query]);

        // Create a fake "empty" response for "Not Found" response.
        if ($response->getStatusCode() === 404) {
            $response = new Response(200, [], json_encode(['objects' => []]));
        }

        $json = json_decode($response->getBody()->getContents());

        // Some endpoints return a flat array of results on a single page (e.g.
        // "Membership:list_uid") so reformatting is needed.
        if (is_array($json)) {
            $objects = $json;
            $json = new stdClass();
            $json->objects = $objects;
            $json->collection_info = new stdClass();
            $json->collection_info->total_items_count = count($objects);
            $json->collection_info->items_per_page = $json->collection_info->total_items_count;
            $json->collection_info->current_page_number = 1;
        }

        // Update page and item totals (for Countable support).
        if (isset($json->collection_info)) {
            $this->totalItemsCount = $json->collection_info->total_items_count;
            $this->count = (int) ceil($this->totalItemsCount/$json->collection_info->items_per_page);
        } else {
            $this->totalItemsCount = 0;
            $this->count = 0;
        }

        return $json;
    }

    /**
     * @inheritDoc
     */
    public function current(): stdClass
    {
        // Only run the API query if necessary.
        if ($this->response->collection_info->current_page_number != $this->page) {
            $this->response = $this->execute();
        }
        return $this->response;
    }

    /**
     * @inheritDoc
     */
    public function next(): void
    {
        $this->page = $this->client::getNextPage($this->response);
    }

    /**
     * @inheritDoc
     */
    public function key(): int
    {
        return $this->page;
    }

    /**
     * @inheritDoc
     */
    public function valid(): bool
    {
        // Verifies that a next page is available (defaults to "1") and there
        // is more than one total result.
        return isset($this->page) && $this->totalItemsCount > 0;
    }

    /**
     * @inheritDoc
     */
    public function rewind(): void
    {
        $this->page = $this->first;
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return $this->count;
    }

    /**
     * @return int
     *   Number of objects (not pages) in the result set.
     */
    public function getTotalItemsCount(): int
    {
        return $this->totalItemsCount;
    }
}
