<?php
namespace Rindow\Database\Dao\Sql;

use Rindow\Database\Dao\ExpressionFactory;

interface TableOperations
{
    /**
     * @param  String  $tableName  Table name
     * @param  array   $values     Sets of field and value
     * @param  array   $projection List of extracted fields, or projection sets of real field name and array key name.
     * @return Integer $updates    Update rows (maybe 1)
     */
    public function insert($tableName,array $values);

    /**
     * @param  String  $tableName  Table name
     * @param  array   $filter     Filter sets of field and value 
     * @param  array   $values     Update data sets of field and value
     * @param  array   $projection List of extracted fields, or projection sets of real field name and array key name.
     * @return Integer $updates    Updated rows
     */
    public function update($tableName,array $filter,array $values);

    /**
     * @param  String $tableName   Table name
     * @param  array  $filter      Filter sets of field and value 
     * @return Integer $updates    Deleted rows
     */
    public function delete($tableName,array $filter);

    /**
     * @param  String $tableName   Table name
     * @param  array  $filter      Filter sets of field and value 
     * @param  array  $orderBy     Sort order sets of field and descending
     * @param  array  $limit       Limit of finding
     * @param  array  $offset      Starting cursor location
     * @param  String $class       Mapping object class name
     * @return ResultList $results Results cursor
     */
    public function find($tableName,array $filter=null,array $orderBy=null,$limit=null,$offset=null,$fetchClass=null,$lazy=null,$customQuery=null);

    /**
     * @param  String $tableName   Table name
     * @param  array  $filter      Filter sets of field and value 
     * @param  array  $limit       Limit of finding
     * @param  array  $offset      Starting cursor location
     * @return Integer $count      number of rows
     */
    public function count($tableName,array $filter=null,$limit=null,$offset=null);

    /**
     * @param  String $tableName        Table name (It need when PostgreSQL)
     * @param  String $field            auto-incrementation field(It need when PostgreSQL)
     * @return integer $id              incremented insert id
     */
    public function getLastInsertId($tableName=null,$field=null);

    /**
     * @return Connection          connection
     */
    public function getConnection();

    /**
     * @param  String $sql              SQL Qurery String
     * @param  array  $params           SQL Parameter Sets
     * @param  integer $fetchMode       PDO fetch mode
     * @param  string $fetchClass       Mapping class for fetch result values
     * @param  array  $constructorArgs  Constractor arguments for mapping class
     * @param  ResultList $resultList   Pre-created ResultList instance
     * @return ResultList $resultList   Results
     */
    public function executeQuery($sql,array $params=null,
        $fetchMode=null,$fetchClass=null,array $constructorArgs=null,
        /* ResultList */ $resultList=null,$lazy=null);

    /**
     * @param  String $sql              SQL Qurery String
     * @param  array  $params           SQL Parameter Sets
     * @return Integer $updated         Update row count
     */
    public function executeUpdate($sql,array $params=null);

    /**
     * @return boolean                  Is the FetchClass Supported in the find and executeQuery
     */
    public function isFetchClassSupported();

    /**
     * @return QueryBuilder             Query builder
     */
    public function getQueryBuilder();
}
