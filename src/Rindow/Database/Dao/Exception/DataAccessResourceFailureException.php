<?php
namespace Rindow\Database\Dao\Exception;

use Interop\Lenient\Dao\Exception\DataAccessResourceFailureException as DataAccessResourceFailureExceptionInterface;

class DataAccessResourceFailureException
extends NonTransientDataAccessResourceException
implements DataAccessResourceFailureExceptionInterface
{}
