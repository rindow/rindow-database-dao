<?php
namespace Rindow\Database\Dao\Repository;

use Interop\Lenient\Dao\Repository\PagingRepository;
use Rindow\Stdlib\Paginator\Paginator;

class GenericPagingRepository implements PagingRepository
{
    protected $repository;

    public function __construct($repository=null)
    {
        if($repository)
            $this->setRepository($repository);
    }

    public function setRepository($repository)
    {
        $this->repository = $repository;
    }

    public function findAll(array $filter=null,array $sort=null)
    {
        $adapter = new GenericRepositoryPaginatorAdapter(
            $this->repository,$filter,$sort
        );

        return new Paginator($adapter);
    }
}
