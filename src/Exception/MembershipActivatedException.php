<?php


namespace OpenPublicMedia\PbsMembershipVault\Exception;

/**
 * Indicates a membership has already been activated by another PBS Account.
 *
 * @package OpenPublicMedia\PbsMembershipVault\Exception
 */
final class MembershipActivatedException extends ActivationConflictException
{
}
