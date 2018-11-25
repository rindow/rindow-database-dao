<?php
namespace Rindow\Database\Dao\Exception;

use Interop\Lenient\Dao\Exception\DuplicateKeyException as DuplicateKeyExceptionInterface;

class DuplicateKeyException
extends DataIntegrityViolationException
implements DuplicateKeyExceptionInterface
{}
