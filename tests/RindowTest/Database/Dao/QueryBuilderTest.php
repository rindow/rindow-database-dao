<?php
namespace RindowTest\Database\Dao\QueryBuilderTest;

use PHPUnit\Framework\TestCase;
use Interop\Lenient\Dao\Query\Expression as ExpressionInterface;
use Rindow\Database\Dao\Support\QueryBuilder;

class Test extends TestCase
{
	public function testCreateExpression()
	{
		$bld = new QueryBuilder();
		$expression = $bld->createExpression($propertyName='foo',$operator=$bld->eq(),$value=123);
		$this->assertEquals('foo',$expression->getPropertyName());
		$this->assertEquals(ExpressionInterface::EQUAL,$expression->getOperator());
		$this->assertEquals(123,$expression->getValue());
	}

	//
    // The Parameter feature is rejected.
	//
	//public function testCreateParameter()
	//{
	//	$builder = new QueryBuilder();
	//	$parameter = $bld->createParameter($name='foo',$value=123);
	//	$this->assertEquals('foo',$parameter->getName());
	//	$this->assertEquals(123,$parameter->getValue());
	//}

	public function testBuildNamedQueryFilterString()
	{
		$bld = new QueryBuilder();

		list($namedQueryString,$values) = $bld->buildNamedQueryString();
		$this->assertEquals('All',$namedQueryString);
		$this->assertEquals(array(),$values);

		$filter = array('foo'=>123);
		list($namedQueryString,$values) = $bld->buildNamedQueryString($filter);
		$this->assertEquals('ByFooEq',$namedQueryString);
		$this->assertEquals(array('foo_0'=>123),$values);

		$expression = $bld->createExpression($propertyName='foo',$operator=$bld->eq(),$value=123);
		$filter = array($expression);
		list($namedQueryString,$values) = $bld->buildNamedQueryString($filter);
		$this->assertEquals('ByFooEq',$namedQueryString);
		$this->assertEquals(array('foo_0'=>123),$values);

		$expression = $bld->createExpression($propertyName='foo',$operator=$bld->gt(),$value=123);
		$filter = array($expression);
		list($namedQueryString,$values) = $bld->buildNamedQueryString($filter);
		$this->assertEquals('ByFooGt',$namedQueryString);
		$this->assertEquals(array('foo_0'=>123),$values);

		$expression = $bld->createExpression($propertyName='foo',$operator=$bld->gte(),$value=123);
		$filter = array($expression);
		list($namedQueryString,$values) = $bld->buildNamedQueryString($filter);
		$this->assertEquals('ByFooGte',$namedQueryString);
		$this->assertEquals(array('foo_0'=>123),$values);

		$filter = array('foo'=>123,'boo'=>456);
		list($namedQueryString,$values) = $bld->buildNamedQueryString($filter);
		$this->assertEquals('ByFooEqAndBooEq',$namedQueryString);
		$this->assertEquals(array('foo_0'=>123,'boo_1'=>456),$values);

		$filter = array();
		$filter[] = $bld->createExpression($propertyName='foo',$operator=$bld->gt(),$value=123);
		$filter[] = $bld->createExpression($propertyName='foo',$operator=$bld->lt(),$value=456);
		list($namedQueryString,$values) = $bld->buildNamedQueryString($filter);
		$this->assertEquals('ByFooGtAndFooLt',$namedQueryString);
		$this->assertEquals(array('foo_0'=>123,'foo_1'=>456),$values);

		$filter = array();
		$filter[] = $bld->createExpression($propertyName='foo',$operator=$bld->gt(),$value=123);
		$filter[] = $bld->createExpression($propertyName='boo',$operator=$bld->eq(),$value=456);
		list($namedQueryString,$values) = $bld->buildNamedQueryString($filter);
		$this->assertEquals('ByFooGtAndBooEq',$namedQueryString);
		$this->assertEquals(array('foo_0'=>123,'boo_1'=>456),$values);

	}

	public function testBuildNamedQuerySortString()
	{
		$bld = new QueryBuilder();

		$sort = array();
		list($namedQueryString,$values) = $bld->buildNamedQueryString(null,$sort);
		$this->assertEquals('All',$namedQueryString);
		$this->assertEquals(array(),$values);

		$sort = array('id'=>1);
		list($namedQueryString,$values) = $bld->buildNamedQueryString(null,$sort);
		$this->assertEquals('AllOrderByIdAsc',$namedQueryString);
		$this->assertEquals(array(),$values);

		$sort = array('id'=>-1);
		list($namedQueryString,$values) = $bld->buildNamedQueryString(null,$sort);
		$this->assertEquals('AllOrderByIdDesc',$namedQueryString);
		$this->assertEquals(array(),$values);

		$sort = array('id'=>-1,'name'=>1);
		list($namedQueryString,$values) = $bld->buildNamedQueryString(null,$sort);
		$this->assertEquals('AllOrderByIdDescAndNameAsc',$namedQueryString);
		$this->assertEquals(array(),$values);


		$filter = array('class'=>'A');
		$sort = array('id'=>-1,'name'=>1);
		list($namedQueryString,$values) = $bld->buildNamedQueryString($filter,$sort);
		$this->assertEquals('ByClassEqOrderByIdDescAndNameAsc',$namedQueryString);
		$this->assertEquals(array('class_0'=>'A'),$values);
	}

	public function testOperator()
	{
		$bld = new QueryBuilder();
		$this->assertEquals(ExpressionInterface::BEGIN_WITH,$bld->bw());
		$this->assertEquals(ExpressionInterface::EQUAL,$bld->eq());
		$this->assertEquals(ExpressionInterface::GREATER_THAN,$bld->gt());
		$this->assertEquals(ExpressionInterface::GREATER_THAN_OR_EQUAL,$bld->gte());
		$this->assertEquals(ExpressionInterface::IN,$bld->in());
		$this->assertEquals(ExpressionInterface::GREATER_THAN_OR_EQUAL,$bld->gte());
		$this->assertEquals(ExpressionInterface::LESS_THAN,$bld->lt());
		$this->assertEquals(ExpressionInterface::LESS_THAN_OR_EQUAL,$bld->lte());
		$this->assertEquals(ExpressionInterface::NOT_EQUAL,$bld->neq());
	}
}