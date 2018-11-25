<?php
namespace Rindow\Database\Dao\Paginator;

use Rindow\Database\Dao\Paginator\AbstractSqlAdapter;
use Rindow\Database\Dao\Support\ResultList;
use Rindow\Database\Dao\Sql\Connection;
use PDO;

class GenericSqlAdapter extends AbstractSqlAdapter
{
    protected $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    protected function executeQuery($sql,array $params=null,$assoc=false,$queryMappingClass=null,array $constructorArgs=null)
    {
        if($assoc) {
            $fetchMode = PDO::FETCH_ASSOC;
        } else {
            if($queryMappingClass===null)
                $fetchMode = null;
            else
                $fetchMode = PDO::FETCH_CLASS;
        }
        return $this->connection->executeQuery($sql,$params,$fetchMode,$queryMappingClass,$constructorArgs);
    }
}
