<?php
namespace Rindow\Database\Dao\Support;

use Interop\Lenient\Dao\Query\ResultList as ResultListInterface;
use Rindow\Database\Dao\Exception;

class ResultList implements ResultListInterface
{
    protected $fetchFunction;
    protected $closeFunction;
    protected $filters = array();
    protected $currentRow;
    protected $endOfCursor;
    protected $pos = 0;
    protected $loaded;

    public function __construct($fetchFunction=null,$closeFunction=null)
    {
        if($fetchFunction!==null)
            $this->setFetchFunction($fetchFunction);
        if($closeFunction!==null)
            $this->setCloseFunction($closeFunction);
    }

    //public function __destruct()
    //{
    //    //$name = isset($this->name) ? $this->name : '';
    //    //fputs(STDERR,"[R::d($name)]");
    //    $this->close();
    //}

    public function setCursorFactory($cursorFactory)
    {
        $this->cursorFactory = $cursorFactory;
    }

    public function setFetchFunction($fetchFunction)
    {
        $this->fetchFunction = $fetchFunction;
        if(!is_callable($fetchFunction))
            throw new Exception\InvalidArgumentException('fetchFunction must be callable.');
        return $this;
    }

    public function setCloseFunction($closeFunction)
    {
        $this->closeFunction = $closeFunction;
        if(!is_callable($closeFunction))
            throw new Exception\InvalidArgumentException('closeFunction must be callable.');
        return $this;
    }

    public function getFetchFunction()
    {
        return $this->fetchFunction;
    }

    public function getCloseFunction()
    {
        return $this->closeFunction;
    }

    public function addFilter($filter)
    {
        if(!is_callable($filter))
            throw new Exception\InvalidArgumentException('filter must be callable.');
        $this->filters[] = $filter;
        return $this;
    }

    public function getFilters()
    {
        return $this->filters;
    }

    public function setFilters(array $filters)
    {
        $this->filters = $filters;
    }

    public function fetch()
    {
        if($this->fetchFunction==null) {
            if($this->cursorFactory==null)
                throw new Exception\DomainException('A cursor is not specified.');
            $cursor = call_user_func($this->cursorFactory);
            $this->fetchFunction = array($cursor,'fetch');
            $this->closeFunction = array($cursor,'close');
        }
        $row = call_user_func($this->fetchFunction);
        if(!$row)
            return $row;
        foreach ($this->filters as $filter) {
            $row = call_user_func($filter,$row);
        }
        return $row;
    }

    public function close()
    {
        // *** MUST UNLINK a statement object ***
        // If a statement object is live then the nextRowset function will be failed.
        if($this->closeFunction)
            call_user_func($this->closeFunction);
        $this->fetchFunction = null;
        $this->closeFunction = null;
        $this->filters = array();
        //$name = isset($this->name) ? $this->name : '';
        //fputs(STDERR,"[R::c($name)]");
    }

    public function rewind()
    {
        if($this->pos != 0)
            throw new Exception\DomainException('it can not rewind.(pos='.$this->pos.')');
        $this->pos = 0;
    }

    public function valid()
    {
        if($this->endOfCursor)
            return false;
        if($this->loaded)
            return true;
        $this->currentRow = $this->fetch();
        if($this->currentRow) {
            $this->loaded = true;
            return true;
        }
        $this->endOfCursor = true;
        return false;
    }

    public function current()
    {
        if(!$this->valid())
            return null;
        return $this->currentRow;
    }

    public function key()
    {
        return $this->pos;
    }

    public function next()
    {
        if($this->endOfCursor)
            return;
        $this->pos++;
        $this->loaded = null;
    }
}