<?php
namespace RindowTest\Database\Dao\TableTemplateTest;

use PHPUnit\Framework\TestCase;
use Interop\Lenient\Dao\Query\Expression;
use Interop\Lenient\Dao\Resource\DataSource;
use Rindow\Database\Dao\Sql\Connection;
use Rindow\Database\Dao\Sql\TableTemplate;
use Rindow\Database\Dao\Support\ResultList;
use Rindow\Database\Dao\Support\QueryBuilder;
//use Rindow\Database\Dao\Support\Parameter;
use Rindow\Transaction\Support\TransactionBoundary;
use Rindow\Container\Container;
use PDO;

class TestConnection implements Connection
{
	public $sql;
	public $params;
	public $fetchClass;
	public $fetchMode;
	public $driverName;
	public $lastInsertId;
	public $resultList;

	public function __construct($resultList=null,$driverName=null,$lastInsertId=null)
	{
		$this->resultList = $resultList;
		$this->driverName = $driverName;
		$this->lastInsertId = $lastInsertId;
	}

    public function executeQuery($sql,array $params=null,
        $fetchMode=null,$fetchClass=null,array $constructorArgs=null,
        /* ResultList */ $resultList=null)
    {
    	$this->sql = $sql;
    	$this->params = $params;
    	$this->fetchClass = $fetchClass;
    	$this->fetchMode = $fetchMode;
    	return $this->resultList;
    }

    public function executeUpdate($sql,array $params=null)
    {
    	$this->sql = $sql;
    	$this->params = $params;
    	return 1;
    }

    public function isFetchClassSupported()
    {
    	return true;
    }

    public function getDriverName()
    {
    	return $this->driverName;
    }

    public function getLastInsertId($table=null,$field=null)
    {
    	$this->lastTable = $table;
    	$this->lastField = $field;
    	return $this->lastInsertId;
    }

    public function getRawConnection()
    {}

    public function close()
    {}
}


class TestDataSource implements DataSource
{
	public $connection;

	public function __construct($connection = null)
	{
		$this->connection = $connection;
	}

	public function getConnection($username = NULL, $password = NULL)
	{
		return $this->connection;
	}
}

class TestCursor
{
	public $data;

	public function __construct(array $data)
	{
		$this->data = $data;
	}

	public function current()
	{
		return current($this->data);
	}

	public function fetch()
	{
		return next($this->data);
	}
}

class Test extends TestCase
{
	public function testInsertNormal()
	{
		$connection = new TestConnection();
		$datasource = new TestDataSource($connection);
		$template = new TableTemplate ($datasource);
		$this->assertEquals(1,$template->insert('foo',array('a'=>'a1','b'=>'b2')));
		$this->assertEquals("INSERT INTO `foo` (`a`,`b`) VALUES (:a,:b);",$connection->sql);
		$this->assertEquals(array(':a'=>'a1',':b'=>'b2'),$connection->params);

		//
	    // The Parameter feature is rejected.
		//
		//$pa = new Parameter('pa','a1');
		//$pb = new Parameter('pb','b2');
		//$this->assertEquals(1,$template->insert('foo',array('a'=>$pa,'b'=>$pb)));
		//$this->assertEquals("INSERT INTO `foo` (`a`,`b`) VALUES (:pa,:pb);",$connection->sql);
		//$this->assertEquals(array(':pa'=>'a1',':pb'=>'b2'),$connection->params);
	}

    /**
     * @expectedException        Rindow\Database\Dao\Exception\DomainException
     * @expectedExceptionMessage No valid fields found.
     */
	public function testInsertNoField()
	{
		$connection = new TestConnection();
		$datasource = new TestDataSource($connection);
		$template = new TableTemplate ($datasource);
		$template->insert('foo',array());
	}

	public function testUpdateNormal()
	{
		$connection = new TestConnection();
		$datasource = new TestDataSource($connection);
		$template = new TableTemplate ($datasource);
		$result = $template->update('foo',array('id'=>10,'c'=>'c1'),array('a'=>'a1','b'=>'b2'));
		$this->assertEquals(1,$result);
		$this->assertEquals("UPDATE `foo` SET `a`=:a,`b`=:b WHERE id=:id_0 AND c=:c_1",$connection->sql);
		$this->assertEquals(array(':a'=>'a1',':b'=>'b2',':id_0'=>10,':c_1'=>'c1'),$connection->params);

		$connection = new TestConnection();
		$datasource = new TestDataSource($connection);
		$template = new TableTemplate ($datasource);
		$result = $template->update('foo',array(),array('a'=>'a1','b'=>'b2'));
		$this->assertEquals(1,$result);
		$this->assertEquals("UPDATE `foo` SET `a`=:a,`b`=:b",$connection->sql);
		$this->assertEquals(array(':a'=>'a1',':b'=>'b2'),$connection->params);


		//
	    // The Parameter feature is rejected.
		//
		//$connection = new TestConnection();
		//$datasource = new TestDataSource($connection);
		//$template = new TableTemplate ($datasource);
		//$pa = new Parameter('pa','a1');
		//$pb = new Parameter('pb','b2');
		//$pcid = new Parameter('pcid',10);
		//$pcc = new Parameter('pcc','c1');
		//$result = $template->update('foo',array('id'=>$pcid,'c'=>$pcc),array('a'=>$pa,'b'=>$pb));
		//$this->assertEquals(1,$result);
		//$this->assertEquals("UPDATE `foo` SET `a`=:pa,`b`=:pb WHERE `id`=:pcid AND `c`=:pcc",$connection->sql);
		//$this->assertEquals(array(':pa'=>'a1',':pb'=>'b2',':pcid'=>10,':pcc'=>'c1'),$connection->params);
	}

    /**
     * @expectedException        Rindow\Database\Dao\Exception\DomainException
     * @expectedExceptionMessage No valid fields found.
     */
	public function testUpdateNoField()
	{
		$connection = new TestConnection();
		$datasource = new TestDataSource($connection);
		$template = new TableTemplate ($datasource);
		$result = $template->update('foo',array(),array());
	}

	public function testDeleteNormal()
	{
		$connection = new TestConnection();
		$datasource = new TestDataSource($connection);
		$template = new TableTemplate ($datasource);
		$result = $template->delete('foo',array('id'=>10,'c'=>'c1'));
		$this->assertEquals(1,$result);
		$this->assertEquals("DELETE FROM `foo` WHERE id=:id_0 AND c=:c_1",$connection->sql);
		$this->assertEquals(array(':id_0'=>10,':c_1'=>'c1'),$connection->params);

		//
	    // The Parameter feature is rejected.
		//
		//$connection = new TestConnection();
		//$datasource = new TestDataSource($connection);
		//$template = new TableTemplate ($datasource);
		//$pcid = new Parameter('pcid',10);
		//$pcc = new Parameter('pcc','c1');
		//$result = $template->delete('foo',array('id'=>$pcid,'c'=>$pcc));
		//$this->assertEquals(1,$result);
		//$this->assertEquals("DELETE FROM `foo` WHERE `id`=:pcid AND `c`=:pcc",$connection->sql);
		//$this->assertEquals(array(':pcid'=>10,':pcc'=>'c1'),$connection->params);
	}

	public function testFindNormal()
	{
		$connection = new TestConnection();
		$datasource = new TestDataSource($connection);
		$template = new TableTemplate ($datasource);
		$results = $template->find('foo');
		$this->assertEquals("SELECT * FROM `foo`;",$connection->sql);
		$this->assertEquals(array(),$connection->params);

		$connection = new TestConnection();
		$datasource = new TestDataSource($connection);
		$template = new TableTemplate ($datasource);
		$results = $template->find('foo',array('a'=>'a1','b'=>'b2'));
		$this->assertEquals("SELECT * FROM `foo` WHERE a=:a_0 AND b=:b_1;",$connection->sql);
		$this->assertEquals(array(':a_0'=>'a1',':b_1'=>'b2'),$connection->params);

		$connection = new TestConnection();
		$datasource = new TestDataSource($connection);
		$template = new TableTemplate ($datasource);
		$results = $template->find('foo',array('a'=>'a1','b'=>'b2'),array('a'=>1,'b'=>1));
		$this->assertEquals("SELECT * FROM `foo` WHERE a=:a_0 AND b=:b_1 ORDER BY `a`,`b`;",$connection->sql);
		$this->assertEquals(array(':a_0'=>'a1',':b_1'=>'b2'),$connection->params);

		$connection = new TestConnection();
		$datasource = new TestDataSource($connection);
		$template = new TableTemplate ($datasource);
		$results = $template->find('foo',array('a'=>'a1','b'=>'b2'),array('a'=>0,'b'=>1));
		$this->assertEquals("SELECT * FROM `foo` WHERE a=:a_0 AND b=:b_1 ORDER BY `a` DESC,`b`;",$connection->sql);
		$this->assertEquals(array(':a_0'=>'a1',':b_1'=>'b2'),$connection->params);

		$connection = new TestConnection();
		$datasource = new TestDataSource($connection);
		$template = new TableTemplate ($datasource);
		$results = $template->find('foo',array('a'=>'a1','b'=>'b2'),null,10);
		$this->assertEquals("SELECT * FROM `foo` WHERE a=:a_0 AND b=:b_1 LIMIT 10;",$connection->sql);
		$this->assertEquals(array(':a_0'=>'a1',':b_1'=>'b2'),$connection->params);

		$connection = new TestConnection();
		$datasource = new TestDataSource($connection);
		$template = new TableTemplate ($datasource);
		$results = $template->find('foo',array('a'=>'a1','b'=>'b2'),null,null,2);
		$this->assertEquals("SELECT * FROM `foo` WHERE a=:a_0 AND b=:b_1 OFFSET 2;",$connection->sql);
		$this->assertEquals(array(':a_0'=>'a1',':b_1'=>'b2'),$connection->params);

		$connection = new TestConnection();
		$datasource = new TestDataSource($connection);
		$template = new TableTemplate ($datasource);
		$results = $template->find('foo',array('a'=>'a1','b'=>'b2'),null,10,2);
		$this->assertEquals("SELECT * FROM `foo` WHERE a=:a_0 AND b=:b_1 LIMIT 10 OFFSET 2;",$connection->sql);
		$this->assertEquals(array(':a_0'=>'a1',':b_1'=>'b2'),$connection->params);

		$connection = new TestConnection();
		$datasource = new TestDataSource($connection);
		$queryBuilder = new QueryBuilder();
		$template = new TableTemplate ($datasource,$queryBuilder);
		$filter = array();
		$filter[] = $template->getQueryBuilder()->createExpression('a',Expression::EQUAL,'a1');
		$filter[] = $template->getQueryBuilder()->createExpression('b',Expression::EQUAL,'b2');
		$results = $template->find('foo',$filter,null,10,2);
		$this->assertEquals("SELECT * FROM `foo` WHERE a=:a_0 AND b=:b_1 LIMIT 10 OFFSET 2;",$connection->sql);
		$this->assertEquals(array(':a_0'=>'a1',':b_1'=>'b2'),$connection->params);

		$connection = new TestConnection();
		$datasource = new TestDataSource($connection);
		$queryBuilder = new QueryBuilder();
		$template = new TableTemplate ($datasource,$queryBuilder);
		$filter = array();
		$filter[] = $template->getQueryBuilder()->createExpression('a',Expression::GREATER_THAN,'a1');
		$filter[] = $template->getQueryBuilder()->createExpression('b',Expression::GREATER_THAN_OR_EQUAL,'b2');
		$results = $template->find('foo',$filter,null,10,2);
		$this->assertEquals("SELECT * FROM `foo` WHERE a>:a_0 AND b>=:b_1 LIMIT 10 OFFSET 2;",$connection->sql);
		$this->assertEquals(array(':a_0'=>'a1',':b_1'=>'b2'),$connection->params);

		$connection = new TestConnection();
		$datasource = new TestDataSource($connection);
		$queryBuilder = new QueryBuilder();
		$template = new TableTemplate ($datasource,$queryBuilder);
		$filter = array();
		$filter[] = $template->getQueryBuilder()->createExpression('a',Expression::LESS_THAN,'a1');
		$filter[] = $template->getQueryBuilder()->createExpression('b',Expression::LESS_THAN_OR_EQUAL,'b2');
		$results = $template->find('foo',$filter,null,10,2);
		$this->assertEquals("SELECT * FROM `foo` WHERE a<:a_0 AND b<=:b_1 LIMIT 10 OFFSET 2;",$connection->sql);
		$this->assertEquals(array(':a_0'=>'a1',':b_1'=>'b2'),$connection->params);

		$connection = new TestConnection();
		$datasource = new TestDataSource($connection);
		$queryBuilder = new QueryBuilder();
		$template = new TableTemplate ($datasource,$queryBuilder);
		$filter = array();
		$filter[] = $template->getQueryBuilder()->createExpression('a',Expression::BEGIN_WITH,'a1');
		$filter[] = $template->getQueryBuilder()->createExpression('b',Expression::NOT_EQUAL,'b2');
		$results = $template->find('foo',$filter,null,10,2);
		$this->assertEquals("SELECT * FROM `foo` WHERE a LIKE :a_0 AND b<>:b_1 LIMIT 10 OFFSET 2;",$connection->sql);
		$this->assertEquals(array(':a_0'=>'a1%',':b_1'=>'b2'),$connection->params);

		$connection = new TestConnection();
		$datasource = new TestDataSource($connection);
		$queryBuilder = new QueryBuilder();
		$template = new TableTemplate ($datasource,$queryBuilder);
		$filter = array();
		$filter[] = $template->getQueryBuilder()->createExpression('a',Expression::IN,array('a1','b2'));
		$results = $template->find('foo',$filter,null,10,2);
		$this->assertEquals("SELECT * FROM `foo` WHERE a IN (:a_0,:a_1) LIMIT 10 OFFSET 2;",$connection->sql);
		$this->assertEquals(array(':a_0'=>'a1',':a_1'=>'b2'),$connection->params);

		$connection = new TestConnection();
		$datasource = new TestDataSource($connection);
		$queryBuilder = new QueryBuilder();
		$template = new TableTemplate ($datasource,$queryBuilder);
		$filter = array();
		$filter[] = $template->getQueryBuilder()->createExpression('x',Expression::GREATER_THAN_OR_EQUAL,1);
		$filter[] = $template->getQueryBuilder()->createExpression('x',Expression::LESS_THAN_OR_EQUAL,10);
		$filter[] = $template->getQueryBuilder()->createExpression('y',Expression::GREATER_THAN_OR_EQUAL,-1);
		$filter[] = $template->getQueryBuilder()->createExpression('y',Expression::LESS_THAN_OR_EQUAL,-10);
		$results = $template->find('foo',$filter,null,10,2);
		$this->assertEquals("SELECT * FROM `foo` WHERE x>=:x_0 AND x<=:x_1 AND y>=:y_2 AND y<=:y_3 LIMIT 10 OFFSET 2;",$connection->sql);
		$this->assertEquals(array(':x_0'=>1,':x_1'=>10,':y_2'=>-1,':y_3'=>-10),$connection->params);

		//
	    // The Parameter feature is rejected.
		//
		//$connection = new TestConnection();
		//$datasource = new TestDataSource($connection);
		//$template = new TableTemplate ($datasource);
		//$pca = new Parameter('pca','a1');
		//$pcb = new Parameter('pcb','b2');
		//$results = $template->find('foo',array('a'=>$pca,'b'=>$pcb));
		//$this->assertEquals("SELECT * FROM `foo` WHERE `a`=:pca AND `b`=:pcb;",$connection->sql);
		//$this->assertEquals(array(':pca'=>'a1',':pcb'=>'b2'),$connection->params);

		//$connection = new TestConnection();
		//$datasource = new TestDataSource($connection);
		//$queryBuilder = new QueryBuilder();
		//$template = new TableTemplate ($datasource,$queryBuilder);
		//$pca = new Parameter('pca','a1');
		//$pcb = new Parameter('pcb','b2');
		//$filter = array();
		//$filter[] = $template->getQueryBuilder()->createExpression('a',Expression::EQUAL,$pca);
		//$filter[] = $template->getQueryBuilder()->createExpression('b',Expression::EQUAL,$pcb);
		//$results = $template->find('foo',$filter,null,10,2);
		//$this->assertEquals("SELECT * FROM `foo` WHERE `a`=:pca AND `b`=:pcb LIMIT 10 OFFSET 2;",$connection->sql);
		//$this->assertEquals(array(':pca'=>'a1',':pcb'=>'b2'),$connection->params);
	}

    /**
     * @expectedException        Rindow\Database\Dao\Exception\InvalidArgumentException
     * @expectedExceptionMessage Unkown operator code in a filter.: XXX
     */
	public function testUnkownFilterCode()
	{
		$connection = new TestConnection();
		$datasource = new TestDataSource($connection);
		$queryBuilder = new QueryBuilder();
		$template = new TableTemplate ($datasource,$queryBuilder);
		$filter = array();
		$filter[] = $template->getQueryBuilder()->createExpression('a','XXX','a1');
		$results = $template->find('foo',$filter,null,10,2);
	}

    /**
     * @expectedException        Rindow\Database\Dao\Exception\RuntimeException
     * @expectedExceptionMessage Normally expression must not include array value.
     */
	public function testIllegalArrayValue1()
	{
		$connection = new TestConnection();
		$datasource = new TestDataSource($connection);
		$queryBuilder = new QueryBuilder();
		$template = new TableTemplate ($datasource,$queryBuilder);
		$filter['a'] = array('a1','b1');
		$results = $template->find('foo',$filter,null,10,2);
	}

    /**
     * @expectedException        Rindow\Database\Dao\Exception\RuntimeException
     * @expectedExceptionMessage Normally expression must not include array value.
     */
	public function testIllegalArrayValue2()
	{
		$connection = new TestConnection();
		$datasource = new TestDataSource($connection);
		$queryBuilder = new QueryBuilder();
		$template = new TableTemplate ($datasource,$queryBuilder);
		$filter = array();
		$filter[] = $template->getQueryBuilder()->createExpression('a',Expression::EQUAL,array('a1'));
		$results = $template->find('foo',$filter,null,10,2);
	}

	public function testCountNormal()
	{
		$connection = new TestConnection(new TestCursor(array(array('count'=>10))));
		$datasource = new TestDataSource($connection);
		$template = new TableTemplate ($datasource);
		$this->assertEquals(10,$template->count('foo'));
		$this->assertEquals('SELECT COUNT(*) AS `count` FROM `foo`',$connection->sql);
		$this->assertEquals(array(),$connection->params);

		$connection = new TestConnection(new TestCursor(array(array('count'=>10))));
		$datasource = new TestDataSource($connection);
		$template = new TableTemplate ($datasource);
		$this->assertEquals(10,$template->count('foo',array('boo'=>'R')));
		$this->assertEquals('SELECT COUNT(*) AS `count` FROM `foo` WHERE boo=:boo_0',$connection->sql);
		$this->assertEquals(array(':boo_0'=>'R'),$connection->params);
	}

	public function testFindWithFetchClass()
	{
		$connection = new TestConnection();
		$datasource = new TestDataSource($connection);
		$template = new TableTemplate ($datasource);
		$results = $template->find('foo',$filter=null,$orderBy=null,$limit=null,$offset=null,$fetchClass='stdClass');
		$this->assertEquals("SELECT * FROM `foo`;",$connection->sql);
		$this->assertEquals(array(),$connection->params);
		$this->assertEquals('stdClass',$connection->fetchClass);
		$this->assertEquals(PDO::FETCH_CLASS,$connection->fetchMode);
	}

	public function testFindWithLazyMode()
	{
		$cursor = new TestCursor(array('foo'));
		$connection = new TestConnection();
		$connection->resultList = new ResultList(array($cursor,'fetch'));
		$datasource = new TestDataSource($connection);
		$template = new TableTemplate ($datasource);
		$results = $template->find('foo',$filter=null,$orderBy=null,$limit=null,$offset=null,$fetchClass=null,$lazy=true);
		$results->addFilter(function($data){return 'hello '.$data;});

		$this->assertNull($connection->sql);
		$this->assertNull($connection->params);
		foreach ($results as $row) {
			$this->assertEquals('hello foo',$row);
		}
		$this->assertEquals("SELECT * FROM `foo`;",$connection->sql);
		$this->assertEquals(array(),$connection->params);
	}
}
