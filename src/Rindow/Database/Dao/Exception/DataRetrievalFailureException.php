<?php
namespace Rindow\Database\Dao\Exception;

use Interop\Lenient\Dao\Exception\DataRetrievalFailureException as DataRetrievalFailureExceptionInterface;

class DataRetrievalFailureException
extends NonTransientDataAccessException
implements DataRetrievalFailureExceptionInterface
{}
