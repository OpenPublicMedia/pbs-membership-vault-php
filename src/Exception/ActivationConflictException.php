<?php


namespace OpenPublicMedia\PbsMembershipVault\Exception;

use Throwable;

/**
 * Base exception for conflicts preventing membership activation.
 *
 * @package OpenPublicMedia\PbsMembershipVault\Exception
 */
abstract class ActivationConflictException extends PbsMembershipVaultException
{
    protected string $membershipId;
    protected string $pbsAccountUid;

    /**
     * {@inheritdoc}
     */
    public function __construct(string $message, string $id, string $uid, int $code = 0, Throwable $previous = null)
    {
        $this->membershipId = $id;
        $this->pbsAccountUid = $uid;
        parent::__construct(['message' => $message, 'id'=> $id, 'uid' => $uid], $code, $previous);
    }

    public function getMembershipId(): string
    {
        return $this->membershipId;
    }

    public function getPbsAccountUid(): string
    {
        return $this->pbsAccountUid;
    }
}
