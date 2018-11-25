<?php
namespace Rindow\Database\Dao\Exception;

use Interop\Lenient\Dao\Exception\NonTransientDataAccessException as NonTransientDataAccessExceptionInterface;

class NonTransientDataAccessException
extends \DomainException
implements NonTransientDataAccessExceptionInterface
{}
