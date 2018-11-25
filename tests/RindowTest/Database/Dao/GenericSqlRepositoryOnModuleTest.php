<?php
namespace RindowTest\Database\Pdo\Repository\GenericSqlRepositoryOnModuleTest;

use PHPUnit\Framework\TestCase;
use Rindow\Container\ModuleManager;
use Rindow\Database\Dao\Sql\Connection;
use Rindow\Database\Dao\Support\ResultList;
use Rindow\Database\Dao\Exception\DomainException as DatabaseException;
use Rindow\Database\Dao\Exception\ExceptionInterface as DatabaseExceptionInterface;
use Interop\Lenient\Transaction\ResourceManager;
use Interop\Lenient\Dao\Resource\DataSource;
use Interop\Lenient\Dao\Query\Cursor;

class TestLogger
{
    public $log = array();

    public function logging($message)
    {
        $this->log[] = $message;
    }

    public function getLog()
    {
        return $this->log;
    }
}

class TestConnection implements Connection
{
    public $sql;
    public $params;
    public $fetchClass;
    public $driverName;
    public $updateCount = 1;
    public $resourceManager;
    public $data = array();
    public $logger;

    public function __construct($logger=null, $driverName=null)
    {
        $this->logger = $logger;
        $this->driverName = $driverName;
    }

    public function setData(array $data)
    {
        $this->data = $data;
    }

    public function executeQuery($sql,array $params=null,
        $fetchMode=null,$fetchClass=null,array $constructorArgs=null,
        /* ResultList */ $resultList=null)
    {
        $this->sql = $sql;
        $this->params = $params;
        $this->fetchClass = $fetchClass;
        $this->logger->logging($sql);

        $cursor = new TestCursor($this->data);
        $resultList = new ResultList(array($cursor,'fetch'),array($cursor,'close'));
        return $resultList;
    }

    public function executeUpdate($sql,array $params=null)
    {
        $this->sql = $sql;
        $this->params = $params;
        $this->logger->logging($sql);
        if(strpos($sql, 'INSERT')===0) {
            if(isset($params[':name']) && $params[':name'] == 'Duplicate') {
                throw new DatabaseException("Duplicate", DatabaseExceptionInterface::ALREADY_EXISTS);
            }
            $this->data[] = $params;
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
        $this->logger->logging('getLastInsertId');
        if(count($this->data)==0)
            return -1;
        return max(array_keys($this->data));
    }

    public function getRawConnection()
    {}

    public function close()
    {}

    public function getResourceManager()
    {
        if(!$this->resourceManager) {
            $this->resourceManager = new TestResourceManager($this->logger);
        }
        return $this->resourceManager;
    }
}

class TestResourceManager implements ResourceManager
{
    public $logger;

    public function __construct($logger = null)
    {
        $this->logger = $logger;
    }
    public function setTimeout($seconds){}
    public function isNestedTransactionAllowed()
    {
        return true;
    }
    public function getName(){}
    public function beginTransaction($definition=null)
    {
        $this->logger->logging('beginTransaction');
    }
    public function commit()
    {
        $this->logger->logging('commit');
    }
    public function rollback()
    {
        $this->logger->logging('rollback');
    }
    public function suspend()
    {
        $this->logger->logging('suspend');
    }
    public function resume($txObject)
    {
        $this->logger->logging('resume');
    }
}

class TestDataSource implements DataSource
{
    public $connection;

    public function __construct($connection = null,$transactionManager = null)
    {
        $this->connection = $connection;
        $this->transactionManager = $transactionManager;
    }

    public function getConnection($username = NULL, $password = NULL)
    {
        $transaction = $this->transactionManager->getTransaction();
        if($transaction) {
            $transaction->enlistResource($this->connection->getResourceManager());
        }
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

class Test extends TestCase
{
    public function setUp()
    {
        usleep( RINDOW_TEST_CLEAR_CACHE_INTERVAL );
        \Rindow\Stdlib\Cache\CacheFactory::clearCache();
        usleep( RINDOW_TEST_CLEAR_CACHE_INTERVAL );
    }

    public function getConfig()
    {
    	$config = array(
    		'module_manager' => array(
    			'modules' => array(
    				'Rindow\Aop\Module' => true,
    				'Rindow\Database\Dao\Sql\Module' => true,
    				'Rindow\Transaction\Local\Module' => true,
    			),
    		),
    		'container' => array(
                'aliases' => array(
                    'Rindow\\Database\\Dao\\DefaultSqlDataSource' => __NAMESPACE__.'\\TestDataSource',
                ),
    			'components' => array(
    				__NAMESPACE__.'\TestDbRepository' => array(
    					'parent' => 'Rindow\\Database\\Dao\\Repository\\AbstractSqlRepository',
    					'properties' => array(
                            'tableName' => array('value' => 'testdb'),
    					),
    				),
                    __NAMESPACE__.'\\TestLogger' => array(
                    ),
                    __NAMESPACE__.'\\TestConnection' => array(
                        'constructor_args' => array(
                            'logger' => array('ref' => __NAMESPACE__.'\\TestLogger'),
                        ),
                    ),
                    __NAMESPACE__.'\\TestDataSource' => array(
                        'constructor_args' => array(
                            'connection' => array('ref' => __NAMESPACE__.'\\TestConnection'),
                            'transactionManager' => array('ref' => __NAMESPACE__.'\\DefaultTransactionManager'),
                        ),
                    ),
                    __NAMESPACE__.'\\DefaultTransactionManager' => array(
                        'class'=>'Rindow\\Transaction\\Local\\TransactionManager',
                        'proxy' => 'disable',
                    ),
    			),
    		),
            'aop' => array(
                'plugins' => array(
                    'Rindow\\Transaction\\Support\\AnnotationHandler'=>true,
                ),
                'transaction' => array(
                    'defaultTransactionManager' => __NAMESPACE__.'\\DefaultTransactionManager',
                    'managers' => array(
                        __NAMESPACE__.'\\DefaultTransactionManager' => array(
                            'transactionManager' => __NAMESPACE__.'\\DefaultTransactionManager',
                            'advisorClass' => 'Rindow\\Transaction\\Support\\TransactionAdvisor',
                        ),
                    ),
                ),
            ),
    	);

    	return $config;
    }

    public function testSaveAndFind()
    {
    	$mm = new ModuleManager($this->getConfig());
        $logger = $mm->getServiceLocator()->get(__NAMESPACE__.'\\TestLogger');
    	$repository = $mm->getServiceLocator()->get(__NAMESPACE__.'\\TestDbRepository');
    	$entity = array('name'=>'test','day'=>'2015/01/01','ser'=>1);
    	$entity = $repository->save($entity);
    	$entity2 = $repository->findById($entity['id']);
        $this->assertEquals(array(
            'beginTransaction',
            'INSERT INTO `testdb` (`name`,`day`,`ser`) VALUES (:name,:day,:ser);',
            'getLastInsertId',
            'commit',
            'SELECT * FROM `testdb` WHERE id=:id_0 LIMIT 1;',
        ),$logger->getLog());

    	$this->assertEquals('test',$entity2[':name']);
    }

    /**
     * @expectedException        Rindow\Database\Dao\Exception\DuplicateKeyException
     * @expectedExceptionCode    -5
     */
    public function testThrowDuplicateKeyException()
    {
    	$mm = new ModuleManager($this->getConfig());
    	$repository = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestDbRepository');
    	$entity = array('name'=>'test','day'=>'2015/01/01','ser'=>1);
    	$repository->save($entity);
    	$entity = array('name'=>'Duplicate','day'=>'2015/01/01','ser'=>1);
    	$repository->save($entity);
    }
}
