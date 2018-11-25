<?php
namespace Rindow\Database\Dao\Exception;

use Interop\Lenient\Dao\Exception\TransientDataAccessException as TransientDataAccessExceptionInterface;

class TransientDataAccessException
extends \RuntimeException
implements TransientDataAccessExceptionInterface
{}
