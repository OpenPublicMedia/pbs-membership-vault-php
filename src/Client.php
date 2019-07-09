<?php
declare(strict_types=1);


namespace OpenPublicMedia\PbsMembershipVault;

use DateTime;
use DateTimeZone;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use League\Uri\Components\Query;
use League\Uri\Parser;
use League\Uri\Parser\QueryString;
use OpenPublicMedia\PbsMembershipVault\Exception\BadRequestException;
use OpenPublicMedia\PbsMembershipVault\Query\Results;
use OpenPublicMedia\PbsMembershipVault\Response\PagedResponse;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use stdClass;

/**
 * PBS Membership Vault API Client.
 *
 * @url https://docs.pbs.org/display/MV/Membership+Vault+API
 *
 * @package OpenPublicMedia\PbsMembershipVault
 */
class Client
{
    /**
     * Live base URL for the API.
     *
     * @url https://docs.pbs.org/display/MV/Membership+Vault+API#MembershipVaultAPI-Endpoints
     */
    const LIVE = "https://mvault.services.pbs.org/api/";

    /**
     * Staging base URL for the API.
     *
     * @url https://docs.pbs.org/display/MV/Membership+Vault+API#MembershipVaultAPI-Endpoints
     */
    const STAGING = "https://mvault-staging.services.pbs.org/api/";

    /**
     * Date string format expected by the API.
     *
     * @url https://docs.pbs.org/display/MV/Membership+Vault+API#MembershipVaultAPI-DateandTime
     */
    const DATETIME_FORMAT = "Y-m-d\TH:i:s\Z";

    /**
     * Client for handling API requests
     *
     * @var GuzzleClient
     */
    protected $client;

    /**
     * Client constructor.
     *
     * @param string $key
     *   API client key.
     * @param string $secret
     *   API client secret.
     * @param $station_id
     *   GUID of the target station.
     * @param string $base_uri
     *   Base API URI.
     * @param array $options
     *   Additional options to pass to Guzzle client.
     */
    public function __construct($key, $secret, $station_id, $base_uri = self::LIVE, array $options = [])
    {
        $options = [
            'base_uri' => $base_uri . $station_id . '/',
            'auth' => [$key, $secret],
            'http_errors' => false
        ] + $options;
        $this->client = new GuzzleClient($options);
    }

    /**
     * @param string $method
     *   Request method (e.g. 'get', 'post', 'put', etc.).
     * @param string $endpoint
     *   API endpoint to query.
     * @param array $options
     *   Options to pass to the Guzzle client request.
     *
     * @return ResponseInterface
     *   Response data from the API.
     */
    public function request($method, $endpoint, array $options = []): ResponseInterface
    {
        if (isset($options['query'])) {
            $options['query'] = self::buildQuery($options['query']);
        }

        try {
            $response = $this->client->request($method, $endpoint, $options);
        } catch (GuzzleException $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        /** @url https://docs.pbs.org/display/MV/Membership+Vault+API#MembershipVaultAPI-HTTPResponseStatusCodes */
        switch ($response->getStatusCode()) {
            case 200:
                break;
            case 400:
            case 401:
            case 403:
                throw new BadRequestException($response);
            case 404:
                // "Not found" is allowed to fall through and should be handled
                // by implementors.
                break;
            default:
                throw new RuntimeException($response->getReasonPhrase(), $response->getStatusCode());
        }

        return $response;
    }

    /**
     * Gets an iterator for paging through API responses.
     *
     * @param $endpoint
     *   URL to query.
     * @param array $query
     *   Additional query parameters in the form `param => value`.
     *
     * @return Results
     *   Generator of the API query results.
     */
    public function get($endpoint, array $query = []): Results
    {
        $response = new PagedResponse($this, $endpoint, $query);
        return new Results($response);
    }

    /**
     * Gets a single object from the API.
     *
     * @param $endpoint
     *   URL to query.
     * @param array $query
     *   Additional query parameters in the form `param => value`.
     *
     * @return null|object
     *   Object representation of the JSON response data or NULL if none is
     *   found.
     */
    public function getOne($endpoint, array $query = []): ?stdClass
    {
        $response = $this->request('get', $endpoint, ['query' => $query]);
        if ($response->getStatusCode() === 404) {
            $json = null;
        } else {
            $json = json_decode($response->getBody()->getContents());
        }

        return $json;
    }

    /**
     * Gets a complete list of objects by paging through all results.
     *
     * @param $endpoint
     *   URL to query.
     * @param array $query
     *   Additional query parameters in the form `param => value`.
     *
     * @return array
     *   All data returned from the API.
     */
    public function getAll($endpoint, array $query = []): array
    {
        $results = [];
        $response = new PagedResponse($this, $endpoint, $query);
        foreach ($response as $page) {
            array_push($results, ...$page->data);
        }
        return $results;
    }

    /**
     * Searches a JSON response for a link containing next page information.
     *
     * @param $json
     *   A full response from the API.
     *
     * @return int|null
     *   The number of the next page or null if there is no next page.
     */
    public static function getNextPage($json): ?int
    {
        $page = null;
        if (isset($json->collection_info)
            && isset($json->collection_info->next_page_url)) {
            $parser = new Parser();
            $query = $parser($json->collection_info->next_page_url)['query'];
            $page = (int) QueryString::extract($query)['page'];
        }
        return $page;
    }

    /**
     * Creates a query string from an array of parameters.
     *
     * @param array $parameters
     *   Query parameters keyed to convert to "key=value". Note: empty/null
     *   values are ignored.
     *
     * @return string
     *   All parameters as a string.
     */
    public static function buildQuery(array $parameters): string
    {
        // Remove empty/null values.
        $parameters = array_filter($parameters, [__CLASS__, 'notEmptyOrNull']);
        $query = Query::createFromPairs($parameters)->withoutEmptyPairs();
        return (string) $query;
    }

    /**
     * Checks if the provided string is not null or empty.
     *
     * This method is meant to be used with array_filter and accepts "0" (string
     * or integer) as valid.
     *
     * @param string $value
     *   Value to check.
     *
     * @return bool
     *   TRUE if $value is not empty or null, FALSE otherwise.
     */
    protected static function notEmptyOrNull($value): bool
    {
        return $value !== null && $value !== '';
    }

    protected function getMemberships($filter = null, array $query = []): Results
    {
        $endpoint = 'memberships';
        if ($filter) {
            $endpoint .= '/filter/' . $filter;
        }

        // Support DateTime objects for date-based query parameters.
        foreach ($query as $key => $value) {
            if ($value instanceof DateTime) {
                $value->setTimezone(new DateTimeZone('UTC'));
                $query[$key] = $value->format(self::DATETIME_FORMAT);
            }
        }

        return $this->get($endpoint, $query);
    }

    public function getMembershipById($id): ?stdClass
    {
        $response = $this->getOne('memberships/' . $id);
        if (empty($response)) {
            throw new MembershipNotFoundException('id', $id);
        }
        return $response;
    }

    public function getMembershipByToken($token): ?stdClass
    {
        $response = $this->getOne('memberships/filter/token/' . $token);
        if (empty($response)) {
            throw new MembershipNotFoundException('token', $token);
        }
        return $response;
    }

    public function getAllMemberships(DateTime $since = null): Results
    {
        return $this->getMemberships(null, ['last_updated_since' => $since]);
    }

    public function getActiveMemberships(
        $email = null,
        DateTime $start_date = null,
        DateTime $end_date = null
    ): Results {
        return $this->getMemberships('active', [
            'email' => $email,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);
    }

    public function getMembershipsByEmail($email): Results
    {
        return $this->getMemberships('email/' . $email);
    }

    public function getMembershipsByUid($uid): Results
    {
        return $this->getMemberships('uid/' . $uid);
    }

    public function getActivatedMemberships(DateTime $since = null): Results
    {
        return $this->getMemberships('activated', ['since' => $since]);
    }

    public function getProvisionalMemberships(DateTime $since = null): Results
    {
        return $this->getMemberships('provisional', ['since' => $since]);
    }

    public function getGracePeriodMemberships(): Results
    {
        return $this->getMemberships('grace_period');
    }

    public function getDeletedMemberships(DateTime $since = null): Results
    {
        return $this->getMemberships('deleted', ['since' => $since]);
    }
}
