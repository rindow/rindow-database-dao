<?php
namespace Rindow\Database\Dao\Support;

use Rindow\Database\Dao\Exception;
use Interop\Lenient\Dao\Query\Expression as ExpressionInterface;

class Expression implements ExpressionInterface
{
    protected $propertyName;
    protected $operator;
    protected $value;

    public function __construct($propertyName=null,$operator=null,$value=null)
    {
        $this->propertyName = $propertyName;
        $this->operator = $operator;
        $this->value = $value;
    }

    public function setPropertyName($propertyName)
    {
        $this->propertyName = $propertyName;
    }

    public function getPropertyName()
    {
        return $this->propertyName;
    }

    public function setOperator($operator)
    {
        $this->operator = $operator;
    }

    public function getOperator()
    {
        return $this->operator;
    }

    public function setValue($value)
    {
        $this->value = $value;
    }

    public function getValue()
    {
        if(ExpressionInterface::IN != $this->operator && is_array($this->value)) {
            throw new Exception\RuntimeException('Normally expression must not include array value.');
        }
        return $this->value;
    }
}
