<?php
namespace Rindow\Database\Dao\Support;

use Interop\Lenient\Dao\Query\QueryBuilder as QueryBuilderInterface;
use Interop\Lenient\Dao\Query\Expression as ExpressionInterface;
use Rindow\Database\Dao\Support\Expression;
use Rindow\Database\Dao\Support\Parameter;

class QueryBuilder implements QueryBuilderInterface
{
    static protected $operators = array(
        ExpressionInterface::BEGIN_WITH => 'Bw',
        ExpressionInterface::EQUAL => 'Eq',
        ExpressionInterface::GREATER_THAN => 'Gt',
        ExpressionInterface::GREATER_THAN_OR_EQUAL => 'Gte',
        ExpressionInterface::IN => 'In',
        ExpressionInterface::LESS_THAN => 'Lt',
        ExpressionInterface::LESS_THAN_OR_EQUAL => 'Lte',
        ExpressionInterface::NOT_EQUAL => 'Neq',
    );

    public function bw()
    {
        return ExpressionInterface::BEGIN_WITH;
    }

    public function eq()
    {
        return ExpressionInterface::EQUAL;
    }

    public function gt()
    {
        return ExpressionInterface::GREATER_THAN;
    }

    public function gte()
    {
        return ExpressionInterface::GREATER_THAN_OR_EQUAL;
    }

    public function in()
    {
        return ExpressionInterface::IN;
    }

    public function lt()
    {
        return ExpressionInterface::LESS_THAN;
    }

    public function lte()
    {
        return ExpressionInterface::LESS_THAN_OR_EQUAL;
    }

    public function neq()
    {
        return ExpressionInterface::NOT_EQUAL;
    }

    public function createExpression($propertyName=null,$operator=null,$value=null)
    {
        return new Expression($propertyName,$operator,$value);
    }

    public function createParameter($parameterName=null,$value=null)
    {
        return new Parameter($parameterName,$value);
    }

    public function buildNamedQueryString(array $filter=null,array $sort=null)
    {
        list($namedQueryString,$values) = $this->buildFindString($filter);
        if(!empty($sort)) {
            $namedQueryString .= $this->buildSortString($sort);
        }
        return array($namedQueryString,$values);
    }

    protected function buildFindString(array $filter=null)
    {
        if(empty($filter))
            return array('All',array());
        $values = array();
        $namedQueryString = '';
        $p = 0;
        foreach ($filter as $propertyName => $value) {
            if($value instanceof ExpressionInterface) {
                if($value->getPropertyName())
                    $propertyName = $value->getPropertyName();
                $operator = $value->getOperator();
                $value = $value->getValue();
            } else {
                $operator = ExpressionInterface::EQUAL;
            }
            if(!array_key_exists($operator, self::$operators))
                throw new Exception\InvalidArgumentException('unknown operator code: "'.$operator.'"');
            if($namedQueryString!='')
                $namedQueryString .= 'And';
            $namedQueryString .= ucfirst($propertyName).self::$operators[$operator];
            $values[$propertyName.'_'.$p] = $value;
            $p++;
        }
        $namedQueryString = 'By'.$namedQueryString;
        return array($namedQueryString,$values);
    }

    protected function buildSortString($sort)
    {
        $name = '';
        foreach ($sort as $field => $direction) {
            if($name!='')
                $name .= 'And';
            $name .= ucfirst($field).(($direction>0) ? 'Asc' : 'Desc');
        }
        return 'OrderBy'.$name;
    }
}