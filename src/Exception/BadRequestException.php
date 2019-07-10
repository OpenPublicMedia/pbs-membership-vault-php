<?php


namespace OpenPublicMedia\PbsMembershipVault\Exception;

use GuzzleHttp\Psr7\Response;
use OpenPublicMedia\PbsMembershipVault\Client;
use Throwable;

/**
 * Handle API response errors manually, as the API passes error information back
 * in JSON format in the response body. The Membership Vault Client passes the
 * option "http_errors" to the Guzzle client to prevent Guzzle from throwing for
 * HTTP errors.
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
     *   Error code.
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
        parent::__construct($message, $code, $previous);
    }
}
