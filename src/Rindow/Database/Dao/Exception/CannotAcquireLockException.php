<?php
namespace Rindow\Database\Dao\Exception;

use Interop\Lenient\Dao\Exception\CannotAcquireLockException as CannotAcquireLockExceptionInterface;

class CannotAcquireLockException
extends PessimisticLockingFailureException
implements CannotAcquireLockExceptionInterface
{}
