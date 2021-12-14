<?php


namespace OpenPublicMedia\PbsMembershipVault\Exception;

use GuzzleHttp\Psr7\Response;
use OpenPublicMedia\PbsMembershipVault\Client;
use Throwable;

/**
 * Handle API response errors manually, as the API passes error information back
 * in JSON format in the response body. The Membership Vault Client passes the
 * option "http_errors" to Guzzle to prevent Guzzle from throwing HTTP errors.
 *
 * @see Client::__construct()
 *
 * @package OpenPublicMedia\PbsMembershipVault\Exception
 */
class BadRequestException extends PbsMembershipVaultException
{
    /**
     * Throws error data as an array (encoded JSON) keyed by field names and
     * specific errors and/or the general field name "__all__" for general
     * errors.
     *
     * @param Response $response
     *   The API response.
     * @param int $code
     *   Error code. This value will only be used if it is not the default (0).
     *   In most cases, the $response HTTP status code will be used.
     * @param Throwable|null $previous
     *   Previous exception to chain.
     *
     * TODO: Expand this to evaluate results and throw specific exceptions.
     */
    public function __construct(Response $response, int $code = 0, Throwable $previous = null)
    {
        $json = json_decode($response->getBody()->getContents());
        if (!empty($json) && isset($json->errors)) {
            $message = $json->errors;
        } else {
            // Follows the API construct of using "__all__" for general errors.
            $message = ['__all__' => $response->getReasonPhrase()];
        }

        // Use the HTTP status code if $code is not set.
        if (empty($code)) {
            $code = $response->getStatusCode();
        }

        parent::__construct($message, $code, $previous);
    }
}
