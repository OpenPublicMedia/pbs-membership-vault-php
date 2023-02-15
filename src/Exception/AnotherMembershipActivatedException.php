<?php


namespace OpenPublicMedia\PbsMembershipVault\Exception;

/**
 * Indicates a PBS Account has already activated another membership.
 *
 * @package OpenPublicMedia\PbsMembershipVault\Exception
 */
final class AnotherMembershipActivatedException extends ActivationConflictException
{
}
