<?php
namespace Rindow\Database\Dao\Repository;

use Interop\Lenient\Dao\Repository\CrudRepository;
use Rindow\Stdlib\Paginator\PaginatorAdapter;
use IteratorAggregate;

class GenericRepositoryPaginatorAdapter implements PaginatorAdapter,IteratorAggregate
{
    public function __construct(/*CrudRepository*/ $repository=null,array $filter=null,array $sort=null)
    {
        $this->repository = $repository;
        $this->filter = $filter;
        $this->sort = $sort;
    }

    public function setLoader($callback)
    {
        $this->loader = $callback;
        if(!is_callable($callback))
            throw new Exception\InvalidArgumentException('loader is not callable.');
        return $this;
    }

    public function getItems($offset, $itemMaxPerPage)
    {
        if($this->repository===null)
            throw new Exception\DomainException('repository is not specified.');
        return $this->repository->findAll($this->filter,$this->sort,$itemMaxPerPage,$offset);
    }
    
    public function count()
    {
        if($this->repository===null)
            throw new Exception\DomainException('repository is not specified.');
        return $this->repository->count($this->filter);
    }

    public function getIterator()
    {
        if($this->repository===null)
            throw new Exception\DomainException('repository is not specified.');
        return $this->repository->findAll($this->filter,$this->sort);
    }
}
