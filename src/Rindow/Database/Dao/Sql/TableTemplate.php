<?php
namespace Rindow\Database\Dao\Sql;

use Interop\Lenient\Dao\Query\Expression;
use Interop\Lenient\Dao\Query\Parameter;
use Rindow\Database\Dao\Exception;
use PDO;

class TableTemplate implements TableOperations
{
    static protected $operatorStrings = array(
        Expression::EQUAL => '=',
        Expression::GREATER_THAN => '>',
        Expression::GREATER_THAN_OR_EQUAL => '>=',
        Expression::LESS_THAN => '<',
        Expression::LESS_THAN_OR_EQUAL => '<=',
        Expression::NOT_EQUAL => '<>',
    );
    protected $dataSource;
    protected $queryBuilder;

    public function __construct($dataSource = null,$queryBuilder = null)
    {
        if($dataSource)
            $this->setDataSource($dataSource);
        if($queryBuilder)
            $this->setQueryBuilder($queryBuilder);
    }

    public function setDataSource($dataSource)
    {
        $this->dataSource = $dataSource;
    }

    public function getDataSource()
    {
        return $this->dataSource;
    }

    public function setQueryBuilder($queryBuilder)
    {
        return $this->queryBuilder = $queryBuilder;
    }

    public function getQueryBuilder()
    {
        return $this->queryBuilder;
    }

    public function getConnection()
    {
        return $this->dataSource->getConnection();
    }

    protected function extractValue($propertyName,$value,$p=null)
    {
        if($value instanceof Parameter) {
            $parameterName = $value->getName();
            $value = $value->getValue();
        } else {
            if($p===null)
                $parameterName = $propertyName;
            else
                $parameterName = $propertyName.'_'.$p;
        }
        //if($p===null)
        //    $parameterName = $propertyName;
        //else
        //    $parameterName = $propertyName.'_'.$p;
        return array($parameterName,$value);
    }

    protected function makeParams(array $values)
    {
        $fields = $params = array();
        foreach ($values as $key => $value) {
            list($paramName,$value) = $this->extractValue($key,$value);
            $fields[] = $key;
            $params[':'.$paramName] = $value;
        }
        return array($fields,$params);
    }

    protected function buildSqlExpression(&$p,$propertyName,$value)
    {
        if($value instanceof Expression) {
            if($value->getPropertyName())
                $propertyName = $value->getPropertyName();
            $operator = $value->getOperator();
            $value = $value->getValue();
        } else {
            $operator = Expression::EQUAL;
        }
        if(is_array($value) && $operator!=Expression::IN)
            throw new Exception\RuntimeException('Normally expression must not include array value.');
        $params = array();
        switch($operator) {
            case Expression::EQUAL:
            case Expression::GREATER_THAN:
            case Expression::GREATER_THAN_OR_EQUAL:
            case Expression::LESS_THAN:
            case Expression::LESS_THAN_OR_EQUAL:
            case Expression::NOT_EQUAL:
                $operatorString = self::$operatorStrings[$operator];
                list($parameterName,$value) = $this->extractValue($propertyName,$value,$p);
                $sql = "${propertyName}${operatorString}:${parameterName}";
                $params[":${parameterName}"] = $value;
                $p++;
                break;
            case Expression::IN:
                $sql = "${propertyName} IN (";
                $cnt=0;
                foreach ($value as $v) {
                    list($parameterName,$v) = $this->extractValue($propertyName,$v,$p);
                    if($cnt!=0)
                        $sql .= ",";
                    $sql .= ":${parameterName}";
                    $params[":${parameterName}"] = $v;
                    $cnt++;
                    $p++;
                }
                $sql .= ")";
                break;
            case Expression::BEGIN_WITH:
                list($parameterName,$value) = $this->extractValue($propertyName,$value,$p);
                $sql = "${propertyName} LIKE :${parameterName}";
                $params[":${parameterName}"] = $value.'%';
                $p++;
                break;
            default:
                throw new Exception\InvalidArgumentException('Unkown operator code in a filter.: '.$operator);
        }
        return array($sql,$params);
    }

    protected function buildSqlWhereSection(array $filter=null)
    {
        if(!$filter)
            return null;
        $sql = '';
        $params = array();
        $pidx = 0;
        foreach ($filter as $key => $value) {
            list($s,$p) = $this->buildSqlExpression($pidx,$key,$value);
            if($sql!='')
                $sql .= ' AND ';
            $sql .= $s;
            $params = array_merge($params,$p);
        }
        if($s=='')
            return array(null,array());
        return array($sql,$params);
    }

    protected function assertTableName($tableName)
    {
        if(!is_string($tableName) || empty($tableName))
            throw new Exception\InvalidArgumentException('the tableName must be string.');
    }

    public function insert($tableName,array $values)
    {
        $this->assertTableName($tableName);
        list($fields,$params) = $this->makeParams($values);
        if(!count($fields))
            throw new Exception\DomainException('No valid fields found.');
        $sql = "INSERT INTO `${tableName}` (".implode(',', array_map(function($field){return "`$field`";},$fields)).")".
                " VALUES (".implode(',', array_keys($params)).");";
        return $this->getConnection()->executeUpdate($sql,$params);
    }

    public function update($tableName,array $filter,array $values)
    {
        $this->assertTableName($tableName);
        list($fields,$params) = $this->makeParams($values);
        if(!count($fields))
            throw new Exception\DomainException('No valid fields found.');
        $sql = "UPDATE `${tableName}`".
               " SET ".implode(',', array_map(function($field,$param){return "`${field}`=${param}";},
                                                $fields,array_keys($params)));
        if(!empty($filter)) {
            list($where,$filterParams) = $this->buildSqlWhereSection($filter);
            $sql .= " WHERE ".$where;
            $params = array_merge($params,$filterParams);
        }
        return $this->getConnection()->executeUpdate($sql,$params);
    }

    public function delete($tableName,array $filter)
    {
        $this->assertTableName($tableName);
        $sql = "DELETE FROM `${tableName}`";
        if(!empty($filter)) {
            list($where,$params) = $this->buildSqlWhereSection($filter);
            $sql .= " WHERE ".$where;
        } else {
            $params = array();
        }
        return $this->getConnection()->executeUpdate($sql,$params);
    }

    public function find($tableName,array $filter=null,array $orderBy=null,$limit=null,$offset=null,$fetchClass=null,$lazy=null,$customQuery=null)
    {
        $this->assertTableName($tableName);
        $sql = "SELECT * FROM `${tableName}`";
        if(!empty($filter)) {
            /*
            list($fields,$params) = $this->makeParams($filter);
            if(!count($fields))
                throw new Exception\DomainException('No valid fields found.');
            $sql .= " WHERE ".implode(' AND ', array_map(
                function($field){
                    return $field."=:".$field;
                },
                $fields));
            */
            list($where,$params) = $this->buildSqlWhereSection($filter);
            if($customQuery)
                $sql = sprintf($customQuery,$where);
            else
                $sql .= " WHERE ".$where;
        } else {
            $params = array();            
        }
        if(!empty($orderBy)) {
            $sql .= " ORDER BY ".implode(',', array_map(
                function($field,$direction){
                    return "`${field}`".(($direction>0) ? "":" DESC");
                },
                array_keys($orderBy),$orderBy));
        }
        if($limit) {
            $sql .= " LIMIT ".intval($limit);
        }
        if($offset) {
            $sql .= " OFFSET ".intval($offset);
        }
        $sql .= ";";

        if($fetchClass)
            $fetchMode = PDO::FETCH_CLASS;
        else
            $fetchMode = null;
        if(!$lazy) {
            return $this->getConnection()->executeQuery($sql,$params,$fetchMode,$fetchClass);
        }
        $lazyQuery = new LazyExecuteQuery($this->getDataSource());
        return $lazyQuery->executeQuery($sql,$params,$fetchMode,$fetchClass);
    }

    public function count($tableName,array $filter=null,$limit=null,$offset=null)
    {
        $this->assertTableName($tableName);
        $sql = "SELECT COUNT(*) AS `count` FROM `${tableName}`";
        if(!empty($filter)) {
            //list($fields,$params) = $this->makeParams($filter);
            //$sql .= " WHERE ".implode(' AND ', array_map(function($field){return $field."=:".$field;},$fields));
            list($where,$params) = $this->buildSqlWhereSection($filter);
            $sql .= " WHERE ".$where;
        } else {
            $params = array();
        }
        if($limit) {
            $sql .= " LIMIT ".intval($limit);
        }
        if($offset) {
            $sql .= " OFFSET ".intval($offset);
        }
        $results = $this->getConnection()->executeQuery($sql,$params);
        $count = $results->current();
        return $count['count'];
    }

    public function getLastInsertId($tableName=null,$field=null)
    {
        return $this->getConnection()->getLastInsertId($tableName,$field);
    }

    public function executeQuery($sql,array $params=null,
        $fetchMode=null,$fetchClass=null,array $constructorArgs=null,
        /* ResultList */ $resultList=null,$lazy=null)
    {
        if(!$lazy)
            return $this->getConnection()->executeQuery($sql,$params,$fetchMode,$fetchClass,$constructorArgs,$resultList);
        $lazyQuery = new LazyExecuteQuery($this->getDataSource());
        return $lazyQuery->executeQuery($sql,$params,$fetchMode,$fetchClass,$constructorArgs,$resultList);
    }

    public function executeUpdate($sql,array $params=null)
    {
        return $this->getConnection()->executeUpdate($sql,$params);
    }

    public function isFetchClassSupported()
    {
        return $this->getConnection()->isFetchClassSupported();
    }
}
