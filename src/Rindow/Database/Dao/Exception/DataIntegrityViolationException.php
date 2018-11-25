<?php
namespace Rindow\Database\Dao\Exception;

use Interop\Lenient\Dao\Exception\DataIntegrityViolationException as DataIntegrityViolationExceptionInterface;

class DataIntegrityViolationException
extends NonTransientDataAccessException
implements DataIntegrityViolationExceptionInterface
{}
