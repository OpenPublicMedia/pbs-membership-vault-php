<?php
declare(strict_types=1);


namespace OpenPublicMedia\PbsMembershipVault;

use DateTime;
use DateTimeZone;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use League\Uri\Components\Query;
use League\Uri\Uri;
use OpenPublicMedia\PbsMembershipVault\Exception\BadRequestException;
use OpenPublicMedia\PbsMembershipVault\Exception\MembershipActivatedException;
use OpenPublicMedia\PbsMembershipVault\Exception\AnotherMembershipActivatedException;
use OpenPublicMedia\PbsMembershipVault\Exception\MembershipNotFoundException;
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
     */
    protected GuzzleClient $client;

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
    public function __construct(
        string $key,
        string $secret,
        string $station_id,
        string $base_uri = self::LIVE,
        array $options = []
    ) {
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
     *
     * @throws BadRequestException
     * @throws RuntimeException
     */
    public function request(string $method, string $endpoint, array $options = []): ResponseInterface
    {
        if (isset($options['query'])) {
            $options['query'] = self::buildQuery($options['query']);
        }

        try {
            // The trailing slash is added automatically to all endpoints and
            // is particularly important for PUT/PATCH requests that will lose
            // payload if it is missing.
            $response = $this->client->request($method, $endpoint . '/', $options);
        } catch (GuzzleException $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        /** @url https://docs.pbs.org/display/MV/Membership+Vault+API#MembershipVaultAPI-HTTPResponseStatusCodes */
        switch ($response->getStatusCode()) {
            case 200:
            case 204:
                break;
            case 400:
            case 401:
            case 403:
            case 409:
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
     * @param string $endpoint
     *   URL to query.
     * @param array $query
     *   Additional query parameters in the form `param => value`.
     *
     * @return Results
     *   Generator of the API query results.
     *
     * @throws BadRequestException
     */
    public function get(string $endpoint, array $query = []): Results
    {
        $response = new PagedResponse($this, $endpoint, $query);
        return new Results($response);
    }

    /**
     * Gets a single object from the API.
     *
     * @param string $endpoint
     *   URL to query.
     * @param array $query
     *   Additional query parameters in the form `param => value`.
     *
     * @return null|object
     *   Object representation of the JSON response data or NULL if none is
     *   found.
     *
     * @throws BadRequestException
     */
    public function getOne(string $endpoint, array $query = []): ?stdClass
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
     * Searches a JSON response for a link containing next page information.
     *
     * @param stdClass $object
     *   A full response from the API.
     *
     * @return int|null
     *   The number of the next page or null if there is not a next page.
     */
    public static function getNextPage(stdClass $object): ?int
    {
        $page = null;
        if (isset($object->collection_info)
            && isset($object->collection_info->next_page_url)) {
            $uri = Uri::createFromString($object->collection_info->next_page_url);
            $params = Query::createFromUri($uri);
            $page = (int) $params->get('page');
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
    private static function buildQuery(array $parameters): string
    {
        // Remove empty/null values.
        $parameters = array_filter($parameters, [__CLASS__, 'notEmptyOrNull']);
        $query = Query::createFromParams($parameters)->withoutEmptyPairs();
        return (string) $query;
    }

    /**
     * Checks if the provided value is not null or empty.
     *
     * This method is meant to be used with array_filter and accepts "0" (string
     * or integer) as valid.
     *
     * @param mixed $value
     *   Value to check.
     *
     * @return bool
     *   TRUE if $value is not empty or null, FALSE otherwise.
     */
    private static function notEmptyOrNull(mixed $value): bool
    {
        return $value !== null && $value !== '';
    }

    /**
     * Finds DatTime objects in an array of key => value data and converts them
     * to strings expected by the API.
     *
     * @param array $data
     *   Data in a key => value format.
     *
     * @url https://docs.pbs.org/display/MV/Membership+Vault+API#MembershipVaultAPI-DateandTime
     * @url https://docs.pbs.org/display/MV/Membership+Vault+API#MembershipVaultAPI-Datetime
     */
    private static function dateTimesToStrings(array &$data): void
    {
        // Convert DateTime objects to expected strings.
        foreach ($data as $key => $value) {
            if ($value instanceof DateTime) {
                $value->setTimezone(new DateTimeZone('UTC'));
                $data[$key] = $value->format(self::DATETIME_FORMAT);
            }
        }
    }

    /**
     * Adds a new membership.
     *
     * In order to guarantee that this is an add operation, this method also
     * verifies by ID that the membership does not already exist in the API.
     * Otherwise, the API would automatically update an existing membership.
     *
     * @param string $id
     *   Membership ID.
     * @param string $first_name
     *   First name.
     * @param string $last_name
     *   Last name.
     * @param string $offer
     *   Offer key. Currently, this information is only accessible from the
     *   (Membership Vault console)[https://mvault.console.pbs.org/].
     * @param DateTime $start_date
     *   Membership start date.
     * @param DateTime $expire_date
     *   Membership expire date.
     * @param string|null $email
     *   Email address.
     * @param string|null $notes
     *   Notes.
     * @param string|null $status
     *  Status. Either "On" or "Off".
     * @param bool|null $provisional
     *   Whether the membership is provisional.
     * @param array|null $additional_metadata
     *   Additional information (to be JSON encoded).
     *
     * @return stdClass
     *   Data for the newly created Membership.
     *
     * @throws BadRequestException
     */
    public function addMembership(
        string $id,
        string $first_name,
        string $last_name,
        string $offer,
        DateTime $start_date,
        DateTime $expire_date,
        ?string $email = null,
        ?string $notes = null,
        ?string $status = null,
        ?bool $provisional = null,
        ?array $additional_metadata = null
    ): stdClass {
        try {
            $this->getMembershipById($id);
            // Simulates existing API response.
            throw new BadRequestException(
                new Response(400, [], json_encode(['errors' => ['__all__' =>
                    ['Membership with this Station call sign and Membership ID already exists.']
                ]]))
            );
        } catch (MembershipNotFoundException) {
            // Continue execution, as this exception is desired.
        }
        $data = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'offer' => $offer,
            'notes' => $notes,
            'start_date' => $start_date,
            'expire_date' => $expire_date,
            'status' => $status,
            'provisional' => $provisional,
            'additional_metadata' => $additional_metadata,
        ];
        self::dateTimesToStrings($data);
        // Remove null/empty values.
        $data = array_filter($data, [__CLASS__, 'notEmptyOrNull']);
        $response = $this->request('put', 'memberships/' . $id, ['json' => $data]);
        return json_decode($response->getBody()->getContents());
    }

    /**
     * Updates an existing membership.
     *
     * @param string $id
     *   Membership ID.
     * @param array $data
     *   Updates to be made, keyed by field machine names.
     *
     * @return bool
     *   TRUE if the update succeeded, FALSE otherwise (though theoretically
     *   this should never happen as exceptions handle other cases).
     *
     * @throws BadRequestException
     * @throws MembershipNotFoundException
     */
    public function updateMembership(string $id, array $data): bool
    {
        $endpoint = 'memberships/' . $id;
        self::dateTimesToStrings($data);
        $response = $this->request('patch', $endpoint, ['json' => $data]);
        if ($response->getStatusCode() === 404) {
            throw new MembershipNotFoundException('id', $id);
        }
        return $response->getStatusCode() === 200;
    }

    /**
     * Attempts to activate a membership.
     *
     * The API returns a number of different payloads indicating errors for this
     * process. It is left up to the implementor to decide how those errors
     * should be handled. In all cases other than success, this method should
     * throw a BadRequestException containing detailed information. At the time
     * of writing, PBS documents four possible error payloads:
     *
     * - Invalid PBS Account UID (400)
     * - PBS Account UID already activated a different Membership (409)
     * - Membership was already activated by a different PBS Account UID (409)
     * - Activation service is down (500)
     *
     * Attempting to activate a Membership with a PBS Account UID that has
     * already activated the Membership should still return a success response.
     *
     * Because of the ambiguity of these error states, this method does not make
     * any attempt to resolve these errors and/or handle each specifically.
     * Implementors are advised to verify the state of a UID and Membership
     * _before_ attempting activation. E.g. --
     *
     * ```php
     * $membership_id = '123ABC';
     * $uid = '123e4567-e89b-12d3-a456-426614174000';
     *
     * $client = new Client(...);
     *
     * try {
     *     $membership = $client->getMembershipById($membership_id);
     * } catch (MembershipNotFoundException $e) {
     * } catch (AnotherMembershipActivatedException $e) {
     * } catch (MembershipActivatedException $e) {
     * }
     *
     * $memberships = $client->getMembershipsByUid($uid);
     * $activated = false;
     *
     * foreach ($memberships as $membership) {
     *     if ($membership->current_state->has_access) {
     *         $activated = $membership;
     *         break;
     *     }
     * }
     *
     * if (!$activated) {
     *     // Woo!
     *     $client->activateMembership($membership_id, $uid);
     * }
     * else {
     *     // The UID has already activated a different Membership ($activated)!
     * }
     * ```
     *
     * @url https://docs.pbs.org/display/MV/Membership+Vault+API#MembershipVaultAPI-memberid
     *
     * @param string $id
     *   ID of Membership to activate.
     * @param string $uid
     *   UID of PBS account to activate with.
     *
     * @return bool
     *   TRUE on success, FALSE otherwise.
     *
     * @throws BadRequestException
     * @throws MembershipActivatedException
     * @throws MembershipNotFoundException
     * @throws AnotherMembershipActivatedException
     */
    public function activateMembership(string $id, string $uid): bool
    {
        try {
            return $this->updateMembership($id, ['uid' => $uid]);
        } catch (BadRequestException $e) {
            if ($e->getCode() === 409) {
                $messages = json_decode($e->getMessage(), true)['__all__'] ?? [];
                foreach ($messages as $message) {
                    preg_match(
                        pattern: '/^The UID (.+) has already activated membership (.+)/i',
                        subject: $message,
                        matches: $matches
                    );
                    if (count($matches) === 3) {
                        throw new AnotherMembershipActivatedException(
                            $matches[0],
                            $matches[2],
                            $matches[1],
                            $e->getCode(),
                            $e
                        );
                    }
                    preg_match(
                        pattern: '/^The membership (.+) was already activated with UID (.+)/i',
                        subject: $message,
                        matches: $matches
                    );
                    if (count($matches) === 3) {
                        throw new MembershipActivatedException(
                            $matches[0],
                            $matches[1],
                            $matches[2],
                            $e->getCode(),
                            $e
                        );
                    }
                }
            }
            throw $e;
        }
    }

    /**
     * @url https://docs.pbs.org/display/MV/Membership+Vault+API#MembershipVaultAPI-delete
     *
     * @param string $id
     *   ID of Membership to be deleted.
     *
     * @return bool
     *   TRUE on success, FALSE otherwise.
     *
     * @throws BadRequestException
     * @throws MembershipNotFoundException
     */
    public function deleteMembership(string $id): bool
    {
        $response = $this->request('delete', 'memberships/' . $id);
        if ($response->getStatusCode() === 404) {
            throw new MembershipNotFoundException('id', $id);
        }
        return $response->getStatusCode() === 204;
    }

    /**
     * Queries the membership endpoint with various filters/params.
     *
     * @param string|null $filter
     *   Filter to apply to query.
     * @param array $query
     *   Query parameters to include.
     *
     * @return Results
     *   Generator of Memberships.
     *
     * @throws BadRequestException
     */
    protected function getMemberships(string $filter = null, array $query = []): Results
    {
        $endpoint = 'memberships';
        if ($filter) {
            $endpoint .= '/filter/' . $filter;
        }
        self::dateTimesToStrings($query);
        return $this->get($endpoint, $query);
    }

    /**
     * @url https://docs.pbs.org/display/MV/Membership+Vault+API#MembershipVaultAPI-get
     *
     * @param string $id
     *   ID to query for.
     *
     * @return stdClass|null
     *   The Membership object is one is found, null otherwise.
     *
     * @throws BadRequestException
     * @throws MembershipNotFoundException
     */
    public function getMembershipById(string $id): ?stdClass
    {
        $response = $this->getOne('memberships/' . $id);
        if (empty($response)) {
            throw new MembershipNotFoundException('id', $id);
        }
        return $response;
    }

    /**
     * @url https://docs.pbs.org/display/MV/Membership+Vault+API#MembershipVaultAPI-list_token
     *
     * Despite the "list" naming for the endpoint, only a single object is
     * returned.
     *
     * @param string $token
     *   Token to query for.
     *
     * @return stdClass|null
     *   The Membership object is one is found, null otherwise.
     *
     * @throws BadRequestException
     * @throws MembershipNotFoundException
     */
    public function getMembershipByToken(string $token): ?stdClass
    {
        $response = $this->getOne('memberships/filter/token/' . $token);
        if (empty($response)) {
            throw new MembershipNotFoundException('token', $token);
        }
        return $response;
    }

    /**
     * @url https://docs.pbs.org/display/MV/Membership+Vault+API#MembershipVaultAPI-get
     *
     * @param DateTime|null $since
     *   Minimum date to apply to query.
     *
     * @return Results
     *   Generator of Memberships.
     *
     * @throws BadRequestException
     */
    public function getAllMemberships(DateTime $since = null): Results
    {
        return $this->getMemberships(null, ['last_updated_since' => $since]);
    }

    /**
     * @url https://docs.pbs.org/display/MV/Membership+Vault+API#MembershipVaultAPI-email
     *
     * This endpoint returns _only_ active Memberships. A membership in its
     * grace period, for example, will not be returned even though it is still
     * usable.
     *
     * @param string|null $email
     *   Email address to filter for.
     * @param DateTime|null $start_date
     *   Minimum date to apply to query.
     * @param DateTime|null $end_date
     *   Maximum date to apply to query.
     *
     * @return Results
     *   Generator of active Memberships.
     *
     * @throws BadRequestException
     */
    public function getActiveMemberships(
        string $email = null,
        DateTime $start_date = null,
        DateTime $end_date = null
    ): Results {
        return $this->getMemberships('active', [
            'email' => $email,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);
    }

    /**
     * @url https://docs.pbs.org/display/MV/Membership+Vault+API#MembershipVaultAPI-list_uid
     *
     * @param string $email
     *   Email address to filter for.
     *
     * @return Results
     *   Generator of Memberships with the provided email address.
     *
     * @throws BadRequestException
     */
    public function getMembershipsByEmail(string $email): Results
    {
        return $this->getMemberships('email/' . $email);
    }

    /**
     * @url https://docs.pbs.org/display/MV/Membership+Vault+API#MembershipVaultAPI-list_uid
     *
     * @param string $uid
     *   UID to filter for.
     *
     * @return Results
     *   Generator of Memberships with the provided UID.
     *
     * @throws BadRequestException
     */
    public function getMembershipsByUid(string $uid): Results
    {
        return $this->getMemberships('uid/' . $uid);
    }

    /**
     * @url https://docs.pbs.org/display/MV/Membership+Vault+API#MembershipVaultAPI-listactivated
     *
     * @param DateTime|null $since
     *   Minimum date to apply to query.
     *
     * @return Results
     *   Generator of activated Memberships.
     *
     * @throws BadRequestException
     */
    public function getActivatedMemberships(DateTime $since = null): Results
    {
        return $this->getMemberships('activated', ['since' => $since]);
    }

    /**
     * @url https://docs.pbs.org/display/MV/Membership+Vault+API#MembershipVaultAPI-listprovisional
     *
     * @param DateTime|null $since
     *   Minimum date to apply to query.
     *
     * @return Results
     *   Generator of provisional Memberships.
     *
     * @throws BadRequestException
     */
    public function getProvisionalMemberships(DateTime $since = null): Results
    {
        return $this->getMemberships('provisional', ['since' => $since]);
    }

    /**
     * @url https://docs.pbs.org/display/MV/Membership+Vault+API#MembershipVaultAPI-list_grace_period
     *
     * @return Results
     *   Generator of Memberships in grace period.
     *
     * @throws BadRequestException
     */
    public function getGracePeriodMemberships(): Results
    {
        return $this->getMemberships('grace_period');
    }

    /**
     * @url https://docs.pbs.org/display/MV/Membership+Vault+API#MembershipVaultAPI-delete
     *
     * @param DateTime|null $since
     *   Minimum date to apply to query.
     *
     * @return Results
     *   Generator of "deleted" Memberships.
     *
     * @throws BadRequestException
     */
    public function getDeletedMemberships(DateTime $since = null): Results
    {
        return $this->getMemberships('deleted', ['since' => $since]);
    }
}
