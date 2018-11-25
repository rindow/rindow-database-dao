<?php
namespace Rindow\Database\Dao\Sql;

use Interop\Lenient\Dao\Query\ResultList;

interface Connection
{
    /**
     * @param  String $sql              SQL Query String
     * @param  array  $params           SQL Parameter Sets
     * @param  integer $fetchMode       PDO fetch mode
     * @param  string $fetchClass       Mapping class for fetch result values
     * @param  array  $constructorArgs  Constractor arguments for mapping class
     * @param  ResultList $resultList   Pre-created ResultList instance
     * @return ResultList $resultList   Results
     */
    public function executeQuery($sql,array $params=null,
        $fetchMode=null,$fetchClass=null,array $constructorArgs=null,
        /* ResultList */ $resultList=null);

    /**
     * @param  String $sql              SQL Qurery String
     * @param  array  $params           SQL Parameter Sets
     * @return Integer $updated         Update row count
     */
    public function executeUpdate($sql,array $params=null);

    /**
     * @return boolean                  Is the FetchClass Supported in the executeQuery
     */
    public function isFetchClassSupported();

    /**
     * @return String $driverName       PDO driver name
     */
    public function getDriverName();

    /**
     * @param  String $table            Table name (It need when PostgreSQL)
     * @param  String $field            auto-incrementation field(It need when PostgreSQL)
     * @return integer $id              incremented insert id
     */
    public function getLastInsertId($table=null,$field=null);

    /**
     * @return Object
     */
    public function getRawConnection();

    /**
     * @return void
     */
    public function close();
}
