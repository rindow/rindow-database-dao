<?php
namespace Rindow\Database\Dao\Exception;

use Interop\Lenient\Dao\Exception\RecoverableDataAccessException as RecoverableDataAccessExceptionInterface;

class RecoverableDataAccessException
extends \RuntimeException
implements RecoverableDataAccessExceptionInterface
{}
