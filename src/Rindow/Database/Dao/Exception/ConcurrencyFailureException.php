<?php
namespace Rindow\Database\Dao\Exception;

use Interop\Lenient\Dao\Exception\ConcurrencyFailureException as ConcurrencyFailureExceptionInterface;

class ConcurrencyFailureException
extends TransientDataAccessException
implements ConcurrencyFailureExceptionInterface
{}
