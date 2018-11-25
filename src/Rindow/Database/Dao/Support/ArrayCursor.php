<?php
namespace Rindow\Database\Dao\Support;

use Interop\Lenient\Dao\Query\Cursor;

use IteratorAggregate;

class ArrayCursor implements Cursor,IteratorAggregate
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function fetch()
    {
        $value = current($this->data);
        next($this->data);
        return $value;
    }

    public function close()
    {
        $this->data = null;
    }

    public function getIterator()
    {
        return $this->data;
    }
}