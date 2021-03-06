<?php
namespace Rindow\Database\Dao\Exception;

use Interop\Lenient\Dao\Exception\EmptyResultDataAccessException as EmptyResultDataAccessExceptionInterface;

class EmptyResultDataAccessException
extends IncorrectResultSizeDataAccessException
implements EmptyResultDataAccessExceptionInterface
{}
