<?php
namespace Rindow\Database\Dao\Support;

use Interop\Lenient\Dao\Query\Parameter as ParameterInterface;

class Parameter implements ParameterInterface
{
    public function __construct($name=null,$value=null)
    {
        if($name)
            $this->setName($name);
        if($value)
            $this->setValue($value);
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function setValue($value)
    {
        $this->value = $value;
    }
}
