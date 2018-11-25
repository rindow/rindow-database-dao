<?php
namespace Rindow\Database\Dao\Paginator;

use IteratorAggregate;
use Rindow\Stdlib\Paginator\PaginatorAdapter;
use Rindow\Database\Dao\Exception;
use Rindow\Database\Dao\Support\ResultList;
use Interop\Lenient\Dao\Query\ResultList as ResultListInterface;

abstract class AbstractSqlAdapter implements PaginatorAdapter,IteratorAggregate
{
    protected $querySql;
    protected $queryParams;
    protected $queryMappingClass;
    protected $countSql;
    protected $countParams;
    protected $loader;

    abstract protected function executeQuery($sql,array $params=null,$assoc=false,$queryMappingClass=null,array $constructorArgs=null);

    protected function getResultList($cursor)
    {
        if($cursor instanceof ResultListInterface)
            return $cursor;
        return new ResultList($cursor);
    }

    public function setQuery($sql,array $params=array(),$className=null)
    {
        $this->querySql = $sql;
        $this->queryParams = $params;
        $this->queryMappingClass = $className;
        return $this;
    }

    public function setCountQuery($sql,array $params=array())
    {
        $this->countSql = $sql;
        $this->countParams = $params;
        return $this;
    }

    public function setLoader($callback)
    {
        $this->loader = $callback;
        if(!is_callable($callback))
            throw new Exception\InvalidArgumentException('loader is not callable.');
        return $this;
    }

    public function count()
    {
        if($this->countSql===null)
            throw new Exception\DomainException('CountSql is not specified.');
        $result = $this->getResultList($this->executeQuery($this->countSql,$this->countParams,true));
        if($result===null)
            return 0;
        if(!($count = $result->current()))
            return $this->rowCount = 0;
        if(!array_key_exists('count', $count))
            throw new Exception\DomainException('CountQuery must include "count" column.');
        return $count['count'];
    }

    public function getItems($offset, $itemMaxPerPage)
    {
        if($this->querySql===null)
            throw new Exception\DomainException('QuerySql is not specified.');
        $sql = $this->querySql.sprintf(" LIMIT %d OFFSET %d",$itemMaxPerPage,$offset);
        $result = $this->getResultList($this->executeQuery($sql,$this->queryParams,false,$this->queryMappingClass));
        if($result && $this->loader)
            $result->addFilter($this->loader);
        return $result;
    }

    public function getIterator()
    {
        if($this->querySql===null)
            throw new Exception\DomainException('QuerySql is not specified.');
        $sql = $this->querySql;
        $result = $this->getResultList($this->executeQuery($sql,$this->queryParams,false,$this->queryMappingClass));
        if($result && $this->loader)
            $result->addFilter($this->loader);
        return $result;
    }
}
