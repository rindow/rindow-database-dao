<?php
namespace Rindow\Database\Dao\Exception;

use Interop\Lenient\Dao\Exception\QueryTimeoutException as QueryTimeoutExceptionInterface;

class QueryTimeoutException
extends TransientDataAccessException
implements QueryTimeoutExceptionInterface
{}
