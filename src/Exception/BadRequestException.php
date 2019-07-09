<?php


namespace OpenPublicMedia\PbsMembershipVault\Exception;

use GuzzleHttp\Psr7\Response;
use OpenPublicMedia\PbsMembershipVault\Client;
use InvalidArgumentException;
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
class BadRequestException extends InvalidArgumentException
{
    public function __construct(Response $response, $code = 0, Throwable $previous = null)
    {
        $message = 'Unknown error';
        $json = json_decode($response->getBody()->getContents());

        if (!empty($json) && isset($json->errors)) {
            if (is_string($json->errors)) {
                $message = $json->errors;
            } elseif (is_object($json->errors)) {
                $message = json_encode($json->errors);
            }
        } else {
            $message = $response->getReasonPhrase();
        }

        parent::__construct($message, $code, $previous);
    }
}
