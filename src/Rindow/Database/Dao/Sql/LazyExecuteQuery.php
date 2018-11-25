<?php
namespace Rindow\Database\Dao\Sql;

use Rindow\Database\Dao\Exception;
use IteratorAggregate;

class LazyExecuteQuery implements IteratorAggregate
{
    protected $dataSource;
    protected $sql;
    protected $params;
    protected $fetchMode;
    protected $fetchClass;
    protected $constructorArgs;
    protected $resultList;
    protected $filters=array();

    public function __construct(/*DataSource*/$dataSource)
    {
        $this->dataSource = $dataSource;
    }

    public function executeQuery($sql,array $params=null,
        $fetchMode=null,$fetchClass=null,array $constructorArgs=null,
        /* ResultList */ $resultList=null)
    {
        $this->sql = $sql;
        $this->params = $params;
        $this->fetchMode = $fetchMode;
        $this->fetchClass = $fetchClass;
        $this->constructorArgs = $constructorArgs;
        $this->resultList = $resultList;
        return $this;
    }

    public function addFilter(/*callable*/$filter)
    {
        if($this->resultList==null) {
            $this->filters[] = $filter;
            return;
        }
        $this->resultList->addFilter($filter);
    }

    public function setFilters(array $filters)
    {
        if($this->resultList==null) {
            $this->filters = $filters;
            return;
        }
        $this->resultList->setFilter($filters);
    }

    public function getFilters()
    {
        if($this->resultList==null) {
            return $this->filters;
        }
        return $this->resultList->getFilters();
    }

    public function getIterator()
    {
        if($this->dataSource==null)
            throw new Exception\DomainException('dataSource is not specified.');
            
        $resultList = $this->dataSource->getConnection()->executeQuery(
            $this->sql,
            $this->params,
            $this->fetchMode,
            $this->fetchClass,
            $this->constructorArgs,
            $this->resultList
        );
        if($this->resultList==null) {
            foreach ($this->filters as $filter) {
                $resultList->addFilter($filter);
            }
        }
        return $resultList;
    }

    public function toArray()
    {
        $values = array();
        $iterator = $this->getIterator();
        foreach ($iterator as $value) {
            $values[] = $value;
        }
        return $values;
    }
}
