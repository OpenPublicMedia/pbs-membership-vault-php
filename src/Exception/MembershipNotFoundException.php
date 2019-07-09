<?php


namespace OpenPublicMedia\PbsMembershipVault\Exception;

use Throwable;

/**
 * Indicates that a membership with a specific ID is not known to the API.
 *
 * @package OpenPublicMedia\PbsMembershipVault\Exception
 */
class MembershipNotFoundException extends PbsMembershipVaultException
{
    /**
     * Throws error data as an array (encoded JSON) with the "type" (id or
     * token) used to query for the membership and the "value".
     *
     * @param string $type
     *   Type used for query ("id" or "token").
     * @param string $value
     *   Value used for query.
     * @param int $code
     *   Error code.
     * @param Throwable|null $previous
     *   Previous exception to chain.
     */
    public function __construct(string $type, string $value, $code = 0, Throwable $previous = null)
    {
        parent::__construct(['type'=> $type, 'value' => $value], $code, $previous);
    }
}
