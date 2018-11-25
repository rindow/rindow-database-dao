<?php
namespace RindowTest\Database\Dao\GenericSqlRepositoryTest;

use PHPUnit\Framework\TestCase;
use Interop\Lenient\Dao\Repository\DataMapper;
use Interop\Lenient\Dao\Resource\DataSource;
use Interop\Lenient\Dao\Query\Cursor;
use Rindow\Database\Dao\Repository\GenericSqlRepository;
//use Rindow\Database\Dao\Repository\GenericSqlRepositoryFactory;
//use Rindow\Database\Dao\Repository\PreparedRepositoryFactory;
use Rindow\Database\Dao\Sql\Connection;
use Rindow\Database\Dao\Sql\TableTemplate;
use Rindow\Database\Dao\Support\ResultList;
use Rindow\Database\Dao\Support\QueryBuilder;
use Rindow\Transaction\Support\TransactionBoundary;
use Rindow\Container\Container;
use IteratorAggregate;
use ArrayObject;

class TestConnection implements Connection
{
	public $sql;
	public $params;
	public $fetchClass;
	public $driverName;
	public $lastInsertId;
	public $resultList;
	public $updateCount = 1;

	public function __construct($resultList=null,$driverName=null,$lastInsertId=null)
	{
		$this->resultList = $resultList;
		$this->driverName = $driverName;
		$this->lastInsertId = $lastInsertId;
	}

	public function setData($data)
	{
		$cursor = new TestCursor($data);
		$this->resultList = new ResultList(array($cursor,'fetch'),array($cursor,'close'));
	}

    public function executeQuery($sql,array $params=null,
        $fetchMode=null,$fetchClass=null,array $constructorArgs=null,
        /* ResultList */ $resultList=null)
    {
    	$this->sql = $sql;
    	$this->params = $params;
    	$this->fetchClass = $fetchClass;
    	return $this->resultList;
    }

    public function executeUpdate($sql,array $params=null)
    {
    	$this->sql = $sql;
    	$this->params = $params;
    	return $this->updateCount;
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

class TestCursor implements Cursor
{
	public $data;

	public function __construct(array $data)
	{
		$this->data = $data;
	}

	public function fetch()
	{
		return array_shift($this->data);
	}
    public function close()
    {
    }
}

class TestDataMapper implements DataMapper
{
	public function map($data)
	{
		return (object)$data;
	}

	public function demap($entity)
	{
		return get_object_vars($entity);
	}

	public function fillId($entity,$id)
	{
		$entity->id = $id;
		return $entity;
	}

	public function getFetchClass()
	{
		return null;
	}
}


class TestSqlRepository extends GenericSqlRepository
{
	public function map($data)
	{
		return (object)$data;
	}

	public function demap($entity)
	{
		return get_object_vars($entity);
	}

	public function fillId($entity,$id)
	{
		$entity->id = $id;
		return $entity;
	}

	public function getFetchClass()
	{
		return null;
	}
}

class Test extends TestCase
{
	public function getRepository($tableName,$className=null,$keyName=null,$dataMapper=null)
	{
		$connection = new TestConnection();
		$connection->lastInsertId = 10;
		$dataSource = new TestDataSource($connection);
		$queryBuilder = new QueryBuilder();
        $tableOperations = new TableTemplate($dataSource,$queryBuilder);
        if($className) {
        	$repository = new $className($tableOperations,$tableName,$keyName,$dataMapper);
        } else {
			$repository = new GenericSqlRepository($tableOperations,$tableName,$keyName,$dataMapper);
        }
		return array($repository,$connection);
	}

	public function testCreateNormal()
	{
		list($store,$conn) = $this->getRepository('foo');
		$doc = $store->save(array('a'=>'a1','b'=>'b2'));
		$this->assertEquals("INSERT INTO `foo` (`a`,`b`) VALUES (:a,:b);",$conn->sql);
		$this->assertEquals(array(':a'=>'a1',':b'=>'b2'),$conn->params);
		$this->assertEquals(array('a'=>'a1','b'=>'b2','id'=>10),$doc);

		// null id
		$doc = $store->save(array('id'=>null,'a'=>'a1','b'=>'b2'));
		$this->assertEquals("INSERT INTO `foo` (`a`,`b`) VALUES (:a,:b);",$conn->sql);
		$this->assertEquals(array(':a'=>'a1',':b'=>'b2'),$conn->params);
		$this->assertEquals(array('a'=>'a1','b'=>'b2','id'=>10),$doc);

        // Customized Key
        $store->setKeyName('xid');
		$doc = $store->save(array('a'=>'a1','b'=>'b2'));
		$this->assertEquals("INSERT INTO `foo` (`a`,`b`) VALUES (:a,:b);",$conn->sql);
		$this->assertEquals(array(':a'=>'a1',':b'=>'b2'),$conn->params);
		$this->assertEquals(array('a'=>'a1','b'=>'b2','xid'=>10),$doc);
	}

    /**
     * @expectedException        Rindow\Database\Dao\Exception\DomainException
     * @expectedExceptionMessage No valid fields found.
     */
	public function testCreateNoField()
	{
		list($store,$conn) = $this->getRepository('foo');
		$store->save(array());
	}

	public function testUpdateNormal()
	{
		list($store,$conn) = $this->getRepository('foo');
		$doc = $store->save(array('id'=>1,'a'=>'a1','b'=>'b2'));
		$this->assertEquals("UPDATE `foo` SET `a`=:a,`b`=:b WHERE id=:id_0",$conn->sql);
		$this->assertEquals(array(':a'=>'a1',':b'=>'b2',':id_0'=>1),$conn->params);
		$this->assertEquals(array('id'=>1,'a'=>'a1','b'=>'b2'),$doc);

        // Customized Key
        $store->setKeyName('xid');
		$doc = $store->save(array('xid'=>1,'a'=>'a1','b'=>'b2'));
		$this->assertEquals("UPDATE `foo` SET `a`=:a,`b`=:b WHERE xid=:xid_0",$conn->sql);
		$this->assertEquals(array(':a'=>'a1',':b'=>'b2',':xid_0'=>1),$conn->params);
		$this->assertEquals(array('xid'=>1,'a'=>'a1','b'=>'b2'),$doc);
        $store->setKeyName('id');

        // Upsert
        $conn->updateCount = 0;
		$doc = $store->save(array('id'=>1,'a'=>'a1','b'=>'b2'));
		$this->assertEquals("INSERT INTO `foo` (`id`,`a`,`b`) VALUES (:id,:a,:b);",$conn->sql);
		$this->assertEquals(array(':id'=>1,':a'=>'a1',':b'=>'b2'),$conn->params);
		$this->assertEquals(array('id'=>1,'a'=>'a1','b'=>'b2'),$doc);
	}

    /**
     * @expectedException        Rindow\Database\Dao\Exception\DomainException
     * @expectedExceptionMessage No valid fields found.
     */
	public function testUpdateNoField()
	{
		list($store,$conn) = $this->getRepository('foo');
		$store->save(array('id'=>1));
	}

	public function testDeleteNormal()
	{
		list($store,$conn) = $this->getRepository('foo');
		$store->deleteById(1);
		$this->assertEquals("DELETE FROM `foo` WHERE id=:id_0",$conn->sql);
		$this->assertEquals(array(':id_0'=>1),$conn->params);

		$store->delete(array('id'=>1,'name'=>'boo'));
		$this->assertEquals("DELETE FROM `foo` WHERE id=:id_0",$conn->sql);
		$this->assertEquals(array(':id_0'=>1),$conn->params);

		$store->deleteAll(array('id'=>1,'name'=>'boo'));
		$this->assertEquals("DELETE FROM `foo` WHERE id=:id_0 AND name=:name_1",$conn->sql);
		$this->assertEquals(array(':id_0'=>1,':name_1'=>'boo'),$conn->params);

        // Customized Key
        $store->setKeyName('xid');
		$store->delete(array('xid'=>1,'name'=>'boo'));
		$this->assertEquals("DELETE FROM `foo` WHERE xid=:xid_0",$conn->sql);
		$this->assertEquals(array(':xid_0'=>1),$conn->params);
	}

    /**
     * @expectedException        Rindow\Database\Dao\Exception\DomainException
     * @expectedExceptionMessage No valid id fields found.
     */
	public function testDeleteNoIdField()
	{
		list($store,$conn) = $this->getRepository('foo');
		$store->delete(array('xid'=>1));
	}

	public function testFindAllNormal()
	{
		list($store,$conn) = $this->getRepository('foo');
		$this->assertInstanceof('Rindow\Database\Dao\Support\QueryBuilder',$store->getQueryBuilder());
		$conn->setData(array());
		$results = $store->findAll(array('a'=>'a1','b'=>'b2'));
		$this->assertEquals("SELECT * FROM `foo` WHERE a=:a_0 AND b=:b_1;",$conn->sql);
		$this->assertEquals(array(':a_0'=>'a1',':b_1'=>'b2'),$conn->params);
		$this->assertEquals($conn->resultList,$results);

		$conn->setData(array());
		$results = $store->findAll(array('a'=>'a1','b'=>'b2'),array('a'=>1));
		$this->assertEquals("SELECT * FROM `foo` WHERE a=:a_0 AND b=:b_1 ORDER BY `a`;",$conn->sql);
		$this->assertEquals(array(':a_0'=>'a1',':b_1'=>'b2'),$conn->params);

		$conn->setData(array());
		$results = $store->findAll(array('a'=>'a1','b'=>'b2'),array('a'=>-1));
		$this->assertEquals("SELECT * FROM `foo` WHERE a=:a_0 AND b=:b_1 ORDER BY `a` DESC;",$conn->sql);
		$this->assertEquals(array(':a_0'=>'a1',':b_1'=>'b2'),$conn->params);

		$conn->setData(array());
		$results = $store->findAll(array('a'=>'a1','b'=>'b2'),null,10);
		$this->assertEquals("SELECT * FROM `foo` WHERE a=:a_0 AND b=:b_1 LIMIT 10;",$conn->sql);
		$this->assertEquals(array(':a_0'=>'a1',':b_1'=>'b2'),$conn->params);

		$conn->setData(array());
		$results = $store->findAll(array('a'=>'a1','b'=>'b2'),null,null,2);
		$this->assertEquals("SELECT * FROM `foo` WHERE a=:a_0 AND b=:b_1 OFFSET 2;",$conn->sql);
		$this->assertEquals(array(':a_0'=>'a1',':b_1'=>'b2'),$conn->params);
	}

	public function testFindOneNormal()
	{
		list($store,$conn) = $this->getRepository('foo');
		$conn->setData(array(array('id'=>1,'a'=>'a1')));
		$doc = $store->findOne(array('a'=>'a1'));
		$this->assertEquals("SELECT * FROM `foo` WHERE a=:a_0 LIMIT 1;",$conn->sql);
		$this->assertEquals(array(':a_0'=>'a1'),$conn->params);
		$this->assertEquals(array('id'=>1,'a'=>'a1'),$doc);
	}

	public function testFindByIdNormal()
	{
		list($store,$conn) = $this->getRepository('foo');
		$conn->setData(array(array('id'=>1,'a'=>'a1')));
		$doc = $store->findById(1);
		$this->assertEquals("SELECT * FROM `foo` WHERE id=:id_0 LIMIT 1;",$conn->sql);
		$this->assertEquals(array(':id_0'=>1),$conn->params);
		$this->assertEquals(array('id'=>1,'a'=>'a1'),$doc);
	}

	public function testCountNormal()
	{
		list($store,$conn) = $this->getRepository('foo');
		$conn->setData(array(array('count'=>1)));
		$count = $store->count();
		$this->assertEquals('SELECT COUNT(*) AS `count` FROM `foo`',$conn->sql);
		$this->assertEquals(array(),$conn->params);
		$this->assertEquals(1,$count);

		$conn->setData(array(array('count'=>1)));
		$count = $store->count(array('boo'=>'R'));
		$this->assertEquals('SELECT COUNT(*) AS `count` FROM `foo` WHERE boo=:boo_0',$conn->sql);
		$this->assertEquals(array(':boo_0'=>'R'),$conn->params);
		$this->assertEquals(1,$count);
	}

	public function testExistsByIdNormal()
	{
		list($store,$conn) = $this->getRepository('foo');
		$conn->setData(array(array('count'=>1)));
		$this->assertTrue($store->existsById(1));

		$conn->setData(array(array('count'=>0)));
		$this->assertFalse($store->existsById(1));
	}

	public function testDataMapper()
	{
		list($store,$conn) = $this->getRepository('foo');
		$store->setDataMapper(new TestDataMapper());

		$entity = new \stdClass();
		$entity->a = 'a1';
		$entity2 = $store->save($entity);
		$this->assertEquals(array('a'=>'a1','id'=>10),get_object_vars($entity));
		$this->assertEquals($entity,$entity2);

		$entity = new \stdClass();
		$entity->id = 1;
		$entity->field = 'boo';
		$entity2 = $store->save($entity);
		$this->assertEquals(array('id'=>1,'field'=>'boo'),get_object_vars($entity));
		$this->assertEquals($entity,$entity2);

		$conn->setData(array(array('id'=>1,'a'=>'a1')));
		$results = $store->findAll();
		$count=0;
		foreach ($results as $entity) {
			$r = new \stdClass();
			$r->id = 1;
			$r->a = 'a1';
			$this->assertEquals($r,$entity);
			$count++;
		}
		$this->assertEquals(1,$count);
	}

	public function testCustomizeForClassMapping()
	{
		list($store,$conn) = $this->getRepository('foo',__NAMESPACE__.'\\TestSqlRepository');
		$entity = new \stdClass();
		$entity->a = 'a1';
		$entity2 = $store->save($entity);
		$this->assertEquals(array('a'=>'a1','id'=>10),get_object_vars($entity));
		$this->assertEquals($entity,$entity2);

		$entity = new \stdClass();
		$entity->id = 1;
		$entity->field = 'boo';
		$entity2 = $store->save($entity);
		$this->assertEquals(array('id'=>1,'field'=>'boo'),get_object_vars($entity));
		$this->assertEquals($entity,$entity2);

		$conn->setData(array(array('id'=>1,'a'=>'a1')));
		$results = $store->findAll();
		$count=0;
		foreach ($results as $entity) {
			$r = new \stdClass();
			$r->id = 1;
			$r->a = 'a1';
			$this->assertEquals($r,$entity);
			$count++;
		}
		$this->assertEquals(1,$count);
	}
/*
	public function testPreparedRepositoryFactory()
	{
		$serviceLocator = new Container();
		$testFactory = new TestFactory(__NAMESPACE__.'\TestRepository');
		$serviceLocator->setInstance(__NAMESPACE__.'\TestFactory',$testFactory);
		$testDataMapper = new TestDataMapper();
		$serviceLocator->setInstance(__NAMESPACE__.'\TestDataMapper',$testDataMapper);
		$config = array('repository'=>array(
			'factory'=>__NAMESPACE__.'\TestFactory',
			'reference'=>'foo',
			'dataMapper'=>__NAMESPACE__.'\TestDataMapper',
		));
		$serviceLocator->setInstance('config',$config);
		$componentName = 'test';
		$args = array('config'=>'repository');
		$store = PreparedRepositoryFactory::factory($serviceLocator,$componentName,$args);

		$this->assertInstanceof(__NAMESPACE__.'\TestRepository',$store);
		$this->assertInstanceof(__NAMESPACE__.'\TestDataMapper',$store->getDataMapper());
		$this->assertEquals('foo',$store->getTableName());
	}
*/
}

