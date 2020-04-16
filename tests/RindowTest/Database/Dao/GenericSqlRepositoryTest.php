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
    public $cascadesql = array();
    public $cascadeparams = array();
	public $fetchClass;
	public $driverName;
	public $lastInsertId;
	public $resultList;
    public $cascadeResultList = array();
	public $updateCount = 1;

	public function __construct($resultList=null,$driverName=null,$lastInsertId=null)
	{
		$this->resultList = $resultList;
		$this->driverName = $driverName;
		$this->lastInsertId = $lastInsertId;
	}

	public function setData($data,$cascadeData=null)
	{
		$cursor = new TestCursor($data);
		$this->resultList = new ResultList(array($cursor,'fetch'),array($cursor,'close'));
        if($cascadeData) {
            foreach ($cascadeData as $table => $data) {
                $cursor = new TestCursor($data);
        		$this->cascadeResultList[$table] = new ResultList(array($cursor,'fetch'),array($cursor,'close'));
            }
        }
	}

    public function executeQuery($sql,array $params=null,
        $fetchMode=null,$fetchClass=null,array $constructorArgs=null,
        /* ResultList */ $resultList=null)
    {
        if(strpos($sql,'cascadetable')===false) {
            $this->sql = $sql;
        	$this->params = $params;
            $resultList = $this->resultList;
        } else {
            $this->cascadesql[] = $sql;
        	$this->cascadeparams[] = $params;
            preg_match('/cascadetable[A-Za-z0-9]*/',$sql,$matches);
            $resultList = $this->cascadeResultList[$matches[0]];
        }
    	$this->fetchClass = $fetchClass;
    	return $resultList;
    }

    public function executeUpdate($sql,array $params=null)
    {
        if(strpos($sql,'cascadetable')===false) {
            $this->sql = $sql;
        	$this->params = $params;
        } else {
            $this->cascadesql[] = $sql;
        	$this->cascadeparams[] = $params;
        }
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
        $newData = array();
        foreach ($data as $key => $value) {
            if($key=='b') {
                $b = new \stdClass();
                $b->value = $value;
                $value = $b;
            } elseif($key=='multivalue1') {
                $values = $value;
                $value = array();
                foreach($values as $v) {
                    $o = new \stdClass();
                    $o->value = $v;
                    $value[] = $o;
                }
            }
            $newData[$key] = $value;
        }
		return (object)$newData;
	}

	public function demap($entity)
	{
		$entity = get_object_vars($entity);
        $newEntity = array();
        foreach ($entity as $key => $value) {
            if($key=='multivalue1') {
                $values = $value;
                $value = array();
                foreach ($values as $v) {
                    $value[] = $v->value;
                }
            } else {
                if(is_object($value)) {
                    $value = $value->value;
                }
            }
            $newEntity[$key] = $value;
        }
        return $newEntity;
	}

	public function fillId($entity,$id)
	{
        if(is_object($entity)) {
            $entity->id = $id;
        } elseif (is_array($entity)) {
            $entity['id'] = $id;
        }
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
        if(is_object($entity)) {
            $entity->id = $id;
        } elseif (is_array($entity)) {
            $entity[$this->keyName] = $id;
        }
		return $entity;
	}

	public function getFetchClass()
	{
		return null;
	}
}

class TestCascadeSqlRepository extends GenericSqlRepository
{
	public function preMap($data)
	{
        $data = parent::preMap($data);
        $data = $this->attachCascaedField($data,
            'multivalue','cascadetable',
            'masterid','value');
        return $data;
	}

	public function postDemap($entity)
	{
        $entity = parent::postDemap($entity);
        $entity = $this->detachCascaedField($entity,'multivalue');
        return $entity;
	}

    protected function postCreate($entity)
    {
        parent::postCreate($entity);
        $this->createCascadedField(
            $entity,'multivalue','cascadetable','masterid','value');
    }

    protected function postUpdate($entity)
    {
        parent::postUpdate($entity);
        $this->updateCascadedField(
            $entity,'multivalue','cascadetable','masterid','value');
    }

    protected function preDelete($filter)
    {
        parent::preDelete($filter);
        $this->deleteCascadedField($filter,'cascadetable','masterid');
    }
}

class TestAutoCascadeSqlRepository extends GenericSqlRepository
{
    protected function cascadedFieldConfig()
    {
        $config = parent::cascadedFieldConfig();
        array_push($config,array(
                'property'=>'multivalue1',
                'tableName'=>'cascadetable1',
                'masterIdName'=>'masterid1',
                'fieldName'=>'value1',
        ));
        return $config;
    }
}

class TestSubAutoCascadeSqlRepository extends TestAutoCascadeSqlRepository
{
    protected function cascadedFieldConfig()
    {
        $config = parent::cascadedFieldConfig();
        array_push($config,array(
                'property'=>'multivalue2',
                'tableName'=>'cascadetable2',
                'masterIdName'=>'masterid2',
                'fieldName'=>'value2',
        ));
        return $config;
    }
}

class TestAutoCascadeWithSelfMapper extends GenericSqlRepository
{
    protected function cascadedFieldConfig()
    {
        $config = parent::cascadedFieldConfig();
        array_push($config,array(
                'property'=>'multivalue',
                'tableName'=>'cascadetable',
                'masterIdName'=>'masterid',
                'fieldName'=>'value',
        ));
        return $config;
    }

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
        if(is_object($entity)) {
            $entity->id = $id;
        } elseif (is_array($entity)) {
            $entity[$this->keyName] = $id;
        }
		return $entity;
	}

	public function getFetchClass()
	{
		return null;
	}
}

class TestAutoCascadeRawModeWithSelfMapper extends GenericSqlRepository
{
    protected function cascadedFieldConfig()
    {
        $config = parent::cascadedFieldConfig();
        array_push($config,array(
                'property'=>'multivalue',
                'tableName'=>'cascadetable',
                'masterIdName'=>'masterid',
                'fieldName'=>'value',
                'rawmode' => true,
        ));
        return $config;
    }

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
        if(is_object($entity)) {
            $entity->id = $id;
        } elseif (is_array($entity)) {
            $entity[$this->keyName] = $id;
        }
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
        $b = new \stdClass();
        $b->value = 'b1';
        $entity->b = $b;
		$entity2 = $store->save($entity);
		$this->assertEquals(array('a'=>'a1','b'=> $b,'id'=>10),get_object_vars($entity));
		$this->assertEquals($entity,$entity2);
        $this->assertEquals(array(':a'=>'a1',':b'=> 'b1'),$conn->params);
        $this->assertEquals('INSERT INTO `foo` (`a`,`b`) VALUES (:a,:b);',$conn->sql);

		$entity = new \stdClass();
		$entity->id = 1;
		$entity->field = 'boo';
        $entity->b = $b;
		$entity2 = $store->save($entity);
		$this->assertEquals(array('id'=>1,'field'=>'boo','b'=> $b),get_object_vars($entity));
		$this->assertEquals($entity,$entity2);
        $this->assertEquals(array(':field'=>'boo',':b'=> 'b1',':id_0'=>1),$conn->params);
        $this->assertEquals('UPDATE `foo` SET `field`=:field,`b`=:b WHERE id=:id_0',$conn->sql);

		$conn->setData(array(array('id'=>1,'a'=>'a1','b'=>'b2')));
		$results = $store->findAll();
		$count=0;
		foreach ($results as $entity) {
			$r = new \stdClass();
			$r->id = 1;
			$r->a = 'a1';
            $r->b = (object)array('value'=>'b2');
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

        public function testCascadeSqlRepositoryOldMode()
	{
		list($store,$conn) = $this->getRepository('foo',__NAMESPACE__.'\\TestCascadeSqlRepository');

        // create
		$doc = $store->save(array('a'=>'a1','b'=>'b2','multivalue'=>array(1,2)));
		$this->assertEquals("INSERT INTO `foo` (`a`,`b`) VALUES (:a,:b);",$conn->sql);
        $this->assertEquals(array(
            "INSERT INTO `cascadetable` (`masterid`,`value`) VALUES (:masterid,:value);",
            "INSERT INTO `cascadetable` (`masterid`,`value`) VALUES (:masterid,:value);",
        ),$conn->cascadesql);
		$this->assertEquals(array(':a'=>'a1',':b'=>'b2'),$conn->params);
        $this->assertEquals(array(
            array(':masterid'=>10,':value'=>1),
            array(':masterid'=>10,':value'=>2),
        ),$conn->cascadeparams);

		$this->assertEquals(array('a'=>'a1','b'=>'b2','id'=>10,'multivalue'=>array(1,2)),$doc);

        // find
        $conn->cascadesql = array();
        $conn->cascadeparams = array();
        $conn->setData(array(array('id'=>10,'a'=>'a1')),array(
            'cascadetable'=>array(
                array('id'=>1,'masterid'=>10,'value'=>1),array('id'=>2,'masterid'=>10,'value'=>2)
            )
        ));
        $doc = $store->findById(10);
		$this->assertEquals("SELECT * FROM `foo` WHERE id=:id_0 LIMIT 1;",$conn->sql);
        $this->assertEquals(array(
            "SELECT * FROM `cascadetable` WHERE masterid=:masterid_0;",
        ),$conn->cascadesql);
		$this->assertEquals(array(':id_0'=>10),$conn->params);
        $this->assertEquals(array(
            array(':masterid_0'=>10),
        ),$conn->cascadeparams);
		$this->assertEquals(array('id'=>10,'a'=>'a1','multivalue'=>array(1,2)),$doc);

        // update
        $conn->cascadesql = array();
        $conn->cascadeparams = array();
        $conn->setData(array(),array(
            'cascadetable'=>array(
                array('id'=>1,'masterid'=>10,'value'=>1),array('id'=>2,'masterid'=>10,'value'=>2)
            )
        ));
        $doc = $store->save(array('id'=>10,'a'=>'a2','b'=>'b2','multivalue'=>array(3,4)));
		$this->assertEquals("UPDATE `foo` SET `a`=:a,`b`=:b WHERE id=:id_0",$conn->sql);
		$this->assertEquals(array(':a'=>'a2',':b'=>'b2',':id_0'=>10),$conn->params);
		$this->assertEquals(array('id'=>10,'a'=>'a2','b'=>'b2','multivalue'=>array(3,4)),$doc);
        $this->assertEquals(array(
            "SELECT * FROM `cascadetable` WHERE masterid=:masterid_0;",
            "DELETE FROM `cascadetable` WHERE masterid=:masterid_0 AND value=:value_1",
            "DELETE FROM `cascadetable` WHERE masterid=:masterid_0 AND value=:value_1",
            "INSERT INTO `cascadetable` (`value`,`masterid`) VALUES (:value,:masterid);",
            "INSERT INTO `cascadetable` (`value`,`masterid`) VALUES (:value,:masterid);",
        ),$conn->cascadesql);
        $this->assertEquals(array(
            array(':masterid_0'=>10),
            array(':masterid_0'=>10,':value_1'=>1),
            array(':masterid_0'=>10,':value_1'=>2),
            array(':masterid'=>10,':value'=>3),
            array(':masterid'=>10,':value'=>4),
        ),$conn->cascadeparams);

        // delete
        $conn->cascadesql = array();
        $conn->cascadeparams = array();
        $store->deleteById(10);
		$this->assertEquals("DELETE FROM `foo` WHERE id=:id_0",$conn->sql);
		$this->assertEquals(array(':id_0'=>10),$conn->params);
        $this->assertEquals(array(
            "DELETE FROM cascadetable WHERE cascadetable.masterid IN (SELECT foo.id FROM foo WHERE foo.id = :id)",
        ),$conn->cascadesql);
        $this->assertEquals(array(
            array(':id'=>10),
        ),$conn->cascadeparams);
    }

    public function testCascadedFieldConfig()
	{
		list($store,$conn) = $this->getRepository('foo',__NAMESPACE__.'\\TestSubAutoCascadeSqlRepository');

        // create
		$doc = $store->save(array('a'=>'a1','b'=>'b2','multivalue1'=>array(1,2),'multivalue2'=>array('A','B')));
		$this->assertEquals("INSERT INTO `foo` (`a`,`b`) VALUES (:a,:b);",$conn->sql);
        $this->assertEquals(array(
            "INSERT INTO `cascadetable1` (`masterid1`,`value1`) VALUES (:masterid1,:value1);",
            "INSERT INTO `cascadetable1` (`masterid1`,`value1`) VALUES (:masterid1,:value1);",
            "INSERT INTO `cascadetable2` (`masterid2`,`value2`) VALUES (:masterid2,:value2);",
            "INSERT INTO `cascadetable2` (`masterid2`,`value2`) VALUES (:masterid2,:value2);",
        ),$conn->cascadesql);
		$this->assertEquals(array(':a'=>'a1',':b'=>'b2'),$conn->params);
        $this->assertEquals(array(
            array(':masterid1'=>10,':value1'=>1),
            array(':masterid1'=>10,':value1'=>2),
            array(':masterid2'=>10,':value2'=>'A'),
            array(':masterid2'=>10,':value2'=>'B'),
        ),$conn->cascadeparams);

		$this->assertEquals(array('a'=>'a1','b'=>'b2','id'=>10,'multivalue1'=>array(1,2),'multivalue2'=>array('A','B')),$doc);

        // find
        $conn->cascadesql = array();
        $conn->cascadeparams = array();
        $conn->setData(array(array('id'=>10,'a'=>'a1','b'=>'b2')),array(
            'cascadetable1'=>array(
                array('id'=>1,'masterid1'=>10,'value1'=>1),array('id'=>2,'masterid1'=>10,'value1'=>2)
            ),
            'cascadetable2'=>array(
                array('id'=>1,'masterid2'=>10,'value2'=>'A'),array('id'=>2,'masterid2'=>10,'value2'=>'B')
            ),
        ));
        $doc = $store->findById(10);
		$this->assertEquals("SELECT * FROM `foo` WHERE id=:id_0 LIMIT 1;",$conn->sql);
        $this->assertEquals(array(
            "SELECT * FROM `cascadetable1` WHERE masterid1=:masterid1_0;",
            "SELECT * FROM `cascadetable2` WHERE masterid2=:masterid2_0;",
        ),$conn->cascadesql);
		$this->assertEquals(array(':id_0'=>10),$conn->params);
        $this->assertEquals(array(
            array(':masterid1_0'=>10),
            array(':masterid2_0'=>10),
        ),$conn->cascadeparams);
        $this->assertEquals(array('a'=>'a1','b'=>'b2','id'=>10,'multivalue1'=>array(1,2),'multivalue2'=>array('A','B')),$doc);

        // update
        $conn->cascadesql = array();
        $conn->cascadeparams = array();
        $conn->setData(array(),array(
            'cascadetable1'=>array(
                array('id'=>1,'masterid1'=>10,'value1'=>1),array('id'=>2,'masterid1'=>10,'value1'=>2),
            ),
            'cascadetable2'=>array(
                array('id'=>1,'masterid2'=>10,'value2'=>'A'),array('id'=>2,'masterid2'=>10,'value2'=>'B'),
            ),
        ));

        $doc = $store->save(array('id'=>10,'a'=>'a2','b'=>'b2','multivalue1'=>array(3,4),'multivalue2'=>array('C','D')));
		$this->assertEquals("UPDATE `foo` SET `a`=:a,`b`=:b WHERE id=:id_0",$conn->sql);
		$this->assertEquals(array(':a'=>'a2',':b'=>'b2',':id_0'=>10),$conn->params);
		$this->assertEquals(array('id'=>10,'a'=>'a2','b'=>'b2','multivalue1'=>array(3,4),'multivalue2'=>array('C','D')),$doc);
        $this->assertEquals(array(
            "SELECT * FROM `cascadetable1` WHERE masterid1=:masterid1_0;",
            "DELETE FROM `cascadetable1` WHERE masterid1=:masterid1_0 AND value1=:value1_1",
            "DELETE FROM `cascadetable1` WHERE masterid1=:masterid1_0 AND value1=:value1_1",
            "INSERT INTO `cascadetable1` (`value1`,`masterid1`) VALUES (:value1,:masterid1);",
            "INSERT INTO `cascadetable1` (`value1`,`masterid1`) VALUES (:value1,:masterid1);",
            "SELECT * FROM `cascadetable2` WHERE masterid2=:masterid2_0;",
            "DELETE FROM `cascadetable2` WHERE masterid2=:masterid2_0 AND value2=:value2_1",
            "DELETE FROM `cascadetable2` WHERE masterid2=:masterid2_0 AND value2=:value2_1",
            "INSERT INTO `cascadetable2` (`value2`,`masterid2`) VALUES (:value2,:masterid2);",
            "INSERT INTO `cascadetable2` (`value2`,`masterid2`) VALUES (:value2,:masterid2);",
        ),$conn->cascadesql);
        $this->assertEquals(array(
            array(':masterid1_0'=>10),
            array(':masterid1_0'=>10,':value1_1'=>1),
            array(':masterid1_0'=>10,':value1_1'=>2),
            array(':masterid1'=>10,':value1'=>3),
            array(':masterid1'=>10,':value1'=>4),
            array(':masterid2_0'=>10),
            array(':masterid2_0'=>10,':value2_1'=>'A'),
            array(':masterid2_0'=>10,':value2_1'=>'B'),
            array(':masterid2'=>10,':value2'=>'C'),
            array(':masterid2'=>10,':value2'=>'D'),
        ),$conn->cascadeparams);

        // delete
        $conn->cascadesql = array();
        $conn->cascadeparams = array();
        $store->deleteById(10);
		$this->assertEquals("DELETE FROM `foo` WHERE id=:id_0",$conn->sql);
		$this->assertEquals(array(':id_0'=>10),$conn->params);
        $this->assertEquals(array(
            "DELETE FROM cascadetable1 WHERE cascadetable1.masterid1 IN (SELECT foo.id FROM foo WHERE foo.id = :id)",
            "DELETE FROM cascadetable2 WHERE cascadetable2.masterid2 IN (SELECT foo.id FROM foo WHERE foo.id = :id)",
        ),$conn->cascadesql);
        $this->assertEquals(array(
            array(':id'=>10),
            array(':id'=>10),
        ),$conn->cascadeparams);
    }

    public function testCascadeWithDataMapper()
	{
		list($store,$conn) = $this->getRepository('foo',__NAMESPACE__.'\\TestAutoCascadeSqlRepository');
        $store->setDataMapper(new TestDataMapper());

        // create
		$doc = $store->save((object)array('a'=>'a1','multivalue1'=>array((object)array('value'=>1),(object)array('value'=>2))));
		$this->assertEquals("INSERT INTO `foo` (`a`) VALUES (:a);",$conn->sql);
        $this->assertEquals(array(
            "INSERT INTO `cascadetable1` (`masterid1`,`value1`) VALUES (:masterid1,:value1);",
            "INSERT INTO `cascadetable1` (`masterid1`,`value1`) VALUES (:masterid1,:value1);",
        ),$conn->cascadesql);
		$this->assertEquals(array(':a'=>'a1'),$conn->params);
        $this->assertEquals(array(
            array(':masterid1'=>10,':value1'=>1),
            array(':masterid1'=>10,':value1'=>2),
        ),$conn->cascadeparams);

		$this->assertEquals((object)array('a'=>'a1','id'=>10,'multivalue1'=>array((object)array('value'=>1),(object)array('value'=>2))),$doc);

        // find
        $conn->cascadesql = array();
        $conn->cascadeparams = array();
        $conn->setData(array(array('id'=>10,'a'=>'a1')),array(
            'cascadetable1'=>array(
                array('id'=>1,'masterid1'=>10,'value1'=>1),array('id'=>2,'masterid1'=>10,'value1'=>2)
            ),
        ));
        $doc = $store->findById(10);
		$this->assertEquals("SELECT * FROM `foo` WHERE id=:id_0 LIMIT 1;",$conn->sql);
        $this->assertEquals(array(
            "SELECT * FROM `cascadetable1` WHERE masterid1=:masterid1_0;",
        ),$conn->cascadesql);
		$this->assertEquals(array(':id_0'=>10),$conn->params);
        $this->assertEquals(array(
            array(':masterid1_0'=>10),
        ),$conn->cascadeparams);
        $this->assertEquals((object)array('a'=>'a1','id'=>10,
            'multivalue1'=>array((object)array('value'=>1),(object)array('value'=>2))),$doc);

        // update
        $conn->cascadesql = array();
        $conn->cascadeparams = array();
        $conn->setData(array(),array(
            'cascadetable1'=>array(
                array('id'=>1,'masterid1'=>10,'value1'=>1),array('id'=>2,'masterid1'=>10,'value1'=>2)
            )
        ));
        $doc = $store->save((object)array('id'=>10,'a'=>'a2','multivalue1'=>array((object)array('value'=>3),(object)array('value'=>4))));
		$this->assertEquals("UPDATE `foo` SET `a`=:a WHERE id=:id_0",$conn->sql);
		$this->assertEquals(array(':a'=>'a2',':id_0'=>10),$conn->params);
		$this->assertEquals((object)array('id'=>10,'a'=>'a2','multivalue1'=>array((object)array('value'=>3),(object)array('value'=>4))),$doc);
        $this->assertEquals(array(
            "SELECT * FROM `cascadetable1` WHERE masterid1=:masterid1_0;",
            "DELETE FROM `cascadetable1` WHERE masterid1=:masterid1_0 AND value1=:value1_1",
            "DELETE FROM `cascadetable1` WHERE masterid1=:masterid1_0 AND value1=:value1_1",
            "INSERT INTO `cascadetable1` (`value1`,`masterid1`) VALUES (:value1,:masterid1);",
            "INSERT INTO `cascadetable1` (`value1`,`masterid1`) VALUES (:value1,:masterid1);",
        ),$conn->cascadesql);
        $this->assertEquals(array(
            array(':masterid1_0'=>10),
            array(':masterid1_0'=>10,':value1_1'=>1),
            array(':masterid1_0'=>10,':value1_1'=>2),
            array(':masterid1'=>10,':value1'=>3),
            array(':masterid1'=>10,':value1'=>4),
        ),$conn->cascadeparams);

        // delete
        $conn->cascadesql = array();
        $conn->cascadeparams = array();
        $store->deleteById(10);
		$this->assertEquals("DELETE FROM `foo` WHERE id=:id_0",$conn->sql);
		$this->assertEquals(array(':id_0'=>10),$conn->params);
        $this->assertEquals(array(
            "DELETE FROM cascadetable1 WHERE cascadetable1.masterid1 IN (SELECT foo.id FROM foo WHERE foo.id = :id)",
        ),$conn->cascadesql);
        $this->assertEquals(array(
            array(':id'=>10),
        ),$conn->cascadeparams);
    }

    public function testCascadeWithSelfMapper()
	{
		list($store,$conn) = $this->getRepository('foo',__NAMESPACE__.'\\TestAutoCascadeWithSelfMapper');

        // create
		$doc = $store->save((object)array('a'=>'a1','multivalue'=>array(1,2)));
		$this->assertEquals("INSERT INTO `foo` (`a`) VALUES (:a);",$conn->sql);
        $this->assertEquals(array(
            "INSERT INTO `cascadetable` (`masterid`,`value`) VALUES (:masterid,:value);",
            "INSERT INTO `cascadetable` (`masterid`,`value`) VALUES (:masterid,:value);",
        ),$conn->cascadesql);
		$this->assertEquals(array(':a'=>'a1'),$conn->params);
        $this->assertEquals(array(
            array(':masterid'=>10,':value'=>1),
            array(':masterid'=>10,':value'=>2),
        ),$conn->cascadeparams);

		$this->assertEquals((object)array('a'=>'a1','id'=>10,'multivalue'=>array(1,2)),$doc);

        // find
        $conn->cascadesql = array();
        $conn->cascadeparams = array();
        $conn->setData(array(array('id'=>10,'a'=>'a1')),array(
            'cascadetable'=>array(
                array('id'=>1,'masterid'=>10,'value'=>1),array('id'=>2,'masterid'=>10,'value'=>2)
            )
        ));
        $doc = $store->findById(10);
		$this->assertEquals("SELECT * FROM `foo` WHERE id=:id_0 LIMIT 1;",$conn->sql);
        $this->assertEquals(array(
            "SELECT * FROM `cascadetable` WHERE masterid=:masterid_0;",
        ),$conn->cascadesql);
		$this->assertEquals(array(':id_0'=>10),$conn->params);
        $this->assertEquals(array(
            array(':masterid_0'=>10),
        ),$conn->cascadeparams);
		$this->assertEquals((object)array('id'=>10,'a'=>'a1','multivalue'=>array(1,2)),$doc);

        // update
        $conn->cascadesql = array();
        $conn->cascadeparams = array();
        $conn->setData(array(),array(
            'cascadetable'=>array(
                array('id'=>1,'masterid'=>10,'value'=>1),array('id'=>2,'masterid'=>10,'value'=>2)
            )
        ));
        $doc = $store->save((object)array('id'=>10,'a'=>'a2','multivalue'=>array(3,4)));
		$this->assertEquals("UPDATE `foo` SET `a`=:a WHERE id=:id_0",$conn->sql);
		$this->assertEquals(array(':a'=>'a2',':id_0'=>10),$conn->params);
		$this->assertEquals((object)array('id'=>10,'a'=>'a2','multivalue'=>array(3,4)),$doc);
        $this->assertEquals(array(
            "SELECT * FROM `cascadetable` WHERE masterid=:masterid_0;",
            "DELETE FROM `cascadetable` WHERE masterid=:masterid_0 AND value=:value_1",
            "DELETE FROM `cascadetable` WHERE masterid=:masterid_0 AND value=:value_1",
            "INSERT INTO `cascadetable` (`value`,`masterid`) VALUES (:value,:masterid);",
            "INSERT INTO `cascadetable` (`value`,`masterid`) VALUES (:value,:masterid);",
        ),$conn->cascadesql);
        $this->assertEquals(array(
            array(':masterid_0'=>10),
            array(':masterid_0'=>10,':value_1'=>1),
            array(':masterid_0'=>10,':value_1'=>2),
            array(':masterid'=>10,':value'=>3),
            array(':masterid'=>10,':value'=>4),
        ),$conn->cascadeparams);

        // delete
        $conn->cascadesql = array();
        $conn->cascadeparams = array();
        $store->delete($doc);
		$this->assertEquals("DELETE FROM `foo` WHERE id=:id_0",$conn->sql);
		$this->assertEquals(array(':id_0'=>10),$conn->params);
        $this->assertEquals(array(
            "DELETE FROM cascadetable WHERE cascadetable.masterid IN (SELECT foo.id FROM foo WHERE foo.id = :id)",
        ),$conn->cascadesql);
        $this->assertEquals(array(
            array(':id'=>10),
        ),$conn->cascadeparams);
    }


    public function testCascadeRawModeWithSelfMapper()
	{
		list($store,$conn) = $this->getRepository('foo',__NAMESPACE__.'\\TestAutoCascadeRawModeWithSelfMapper');

        // create
        $doc = $store->save((object)array('a'=>'a1','multivalue'=>array((object)array('value'=>1,'opt'=>10),(object)array('value'=>2,'opt'=>20))));
		$this->assertEquals("INSERT INTO `foo` (`a`) VALUES (:a);",$conn->sql);
        $this->assertEquals(array(
            "INSERT INTO `cascadetable` (`value`,`opt`,`masterid`) VALUES (:value,:opt,:masterid);",
            "INSERT INTO `cascadetable` (`value`,`opt`,`masterid`) VALUES (:value,:opt,:masterid);",
        ),$conn->cascadesql);
		$this->assertEquals(array(':a'=>'a1'),$conn->params);
        $this->assertEquals(array(
            array(':masterid'=>10,':value'=>1,':opt'=>10),
            array(':masterid'=>10,':value'=>2,':opt'=>20),
        ),$conn->cascadeparams);

        $this->assertEquals((object)array('id'=>10,'a'=>'a1','multivalue'=>array((object)array('value'=>1,'opt'=>10),(object)array('value'=>2,'opt'=>20))),$doc);

        // find
        $conn->cascadesql = array();
        $conn->cascadeparams = array();
        $conn->setData(array(array('id'=>10,'a'=>'a1')),array(
            'cascadetable'=>array(
                array('id'=>1,'masterid'=>10,'value'=>1,'opt'=>10),array('id'=>2,'masterid'=>10,'value'=>2,'opt'=>20),
            )
        ));
        $doc = $store->findById(10);
		$this->assertEquals("SELECT * FROM `foo` WHERE id=:id_0 LIMIT 1;",$conn->sql);
        $this->assertEquals(array(),$conn->cascadesql);
		$this->assertEquals(array(':id_0'=>10),$conn->params);
        $this->assertEquals(array(),$conn->cascadeparams);
        $this->assertEquals(10,   $doc->id);
        $this->assertEquals('a1', $doc->a);
        $this->assertInstanceof('Rindow\Database\Dao\Sql\LazyExecuteQuery', $doc->multivalue);
        $count=0;
        foreach($doc->multivalue as $value) {
            if($value['id']==1) {
                $this->assertEquals(array('id'=>1,'masterid'=>10,'value'=>1,'opt'=>10),$value);
            } elseif($value['id']==2) {
                $this->assertEquals(array('id'=>2,'masterid'=>10,'value'=>2,'opt'=>20),$value);
            }
            $count++;
        }
        $this->assertEquals(2,$count);
        $this->assertEquals(array(
            "SELECT * FROM `cascadetable` WHERE masterid=:masterid_0;",
        ),$conn->cascadesql);
        $this->assertEquals(array(
            array(':masterid_0'=>10),
        ),$conn->cascadeparams);

        // update
        $conn->cascadesql = array();
        $conn->cascadeparams = array();
        $conn->setData(array(),array(
            'cascadetable'=>array(
                array('id'=>1,'masterid'=>10,'value'=>1,'opt'=>10),array('id'=>2,'masterid'=>10,'value'=>2,'opt'=>20)
            )
        ));
        $doc = $store->save((object)array('id'=>10,'a'=>'a2','multivalue'=>array((object)array('value'=>3,'opt'=>30),(object)array('value'=>4,'opt'=>40))));
		$this->assertEquals("UPDATE `foo` SET `a`=:a WHERE id=:id_0",$conn->sql);
		$this->assertEquals(array(':a'=>'a2',':id_0'=>10),$conn->params);
        $this->assertEquals((object)array('id'=>10,'a'=>'a2','multivalue'=>array((object)array('value'=>3,'opt'=>30),(object)array('value'=>4,'opt'=>40))),$doc);
        $this->assertEquals(array(
            "SELECT * FROM `cascadetable` WHERE masterid=:masterid_0;",
            "DELETE FROM `cascadetable` WHERE masterid=:masterid_0 AND value=:value_1",
            "DELETE FROM `cascadetable` WHERE masterid=:masterid_0 AND value=:value_1",
            "INSERT INTO `cascadetable` (`value`,`opt`,`masterid`) VALUES (:value,:opt,:masterid);",
            "INSERT INTO `cascadetable` (`value`,`opt`,`masterid`) VALUES (:value,:opt,:masterid);",
        ),$conn->cascadesql);
        $this->assertEquals(array(
            array(':masterid_0'=>10),
            array(':masterid_0'=>10,':value_1'=>1),
            array(':masterid_0'=>10,':value_1'=>2),
            array(':masterid'=>10,':value'=>3,':opt'=>30),
            array(':masterid'=>10,':value'=>4,':opt'=>40),
        ),$conn->cascadeparams);

        // delete
        $conn->cascadesql = array();
        $conn->cascadeparams = array();
        $store->delete($doc);
		$this->assertEquals("DELETE FROM `foo` WHERE id=:id_0",$conn->sql);
		$this->assertEquals(array(':id_0'=>10),$conn->params);
        $this->assertEquals(array(
            "DELETE FROM cascadetable WHERE cascadetable.masterid IN (SELECT foo.id FROM foo WHERE foo.id = :id)",
        ),$conn->cascadesql);
        $this->assertEquals(array(
            array(':id'=>10),
        ),$conn->cascadeparams);
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
