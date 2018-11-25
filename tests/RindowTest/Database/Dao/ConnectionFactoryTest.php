<?php
namespace RindowTest\Database\Dao\ConnectionFactoryTest;

use PHPUnit\Framework\TestCase;
use Interop\Lenient\Dao\Resource\DataSource as DataSourceInterface;
use Rindow\Container\ModuleManager;

class TestDataSource implements DataSourceInterface
{
    public function getConnection($username=null,$password=null)
    {
        $params['username'] = $username;
        $params['password'] = $password;
        return $params;
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

    public function testNormal()
    {
        $config = array(
            'module_manager' => array(
                'modules' => array(
                ),
            ),
            'container' => array(
                'components' => array(
                    'test.test' => array(
                        'factory' => 'Rindow\Database\Dao\Support\ConnectionFactory::factory',
                        'factory_args' => array(
                            'dataSource' => __NAMESPACE__.'\TestDataSource'
                        ),
                    ),
                    __NAMESPACE__.'\TestDataSource'=>array(
                    ),
                ),
            ),
        );
        $manager = new ModuleManager($config);
        $result = array(
            'username' => null,
            'password' => null,
        );
        $this->assertEquals(
            $result,
            $manager->getServiceLocator()->get('test.test')
        );
    }

    public function testUsernameAndPassword()
    {
        $config = array(
            'module_manager' => array(
                'modules' => array(
                ),
            ),
            'container' => array(
                'components' => array(
                    'test.test' => array(
                        'factory' => 'Rindow\Database\Dao\Support\ConnectionFactory::factory',
                        'factory_args' => array(
                            'dataSource' => __NAMESPACE__.'\TestDataSource',
                            'username' => 'fooname',
                            'password' => 'foopass',
                        ),
                    ),
                    __NAMESPACE__.'\TestDataSource'=>array(
                    ),
                ),
            ),
        );
        $manager = new ModuleManager($config);
        $result = array(
            'username' => 'fooname',
            'password' => 'foopass',
        );
        $this->assertEquals(
            $result,
            $manager->getServiceLocator()->get('test.test')
        );
    }

    public function testConfig()
    {
        $config = array(
            'module_manager' => array(
                'modules' => array(
                ),
            ),
            'container' => array(
                'components' => array(
                    'test.test' => array(
                        'factory' => 'Rindow\Database\Dao\Support\ConnectionFactory::factory',
                        'factory_args' => array('config'=>'test.test.test'),
                    ),
                    __NAMESPACE__.'\TestDataSource'=>array(
                    ),
                ),
            ),
            'test.test.test' => array(
                'dataSource' => __NAMESPACE__.'\TestDataSource',
                'username' => 'fooname',
                'password' => 'foopass',
            ),
        );
        $manager = new ModuleManager($config);
        $result = array(
            'username' => 'fooname',
            'password' => 'foopass',
        );
        $this->assertEquals(
            $result,
            $manager->getServiceLocator()->get('test.test')
        );
    }

    /**
     * @expectedException        Rindow\Database\Dao\Exception\DomainException
     * @expectedExceptionMessage dataSource is not specified for "test.test"
     */
    public function testNoneDataSource()
    {
        $config = array(
            'module_manager' => array(
                'modules' => array(
                ),
            ),
            'container' => array(
                'components' => array(
                    'test.test' => array(
                        'factory' => 'Rindow\Database\Dao\Support\ConnectionFactory::factory',
                    ),
                ),
            ),
        );
        $manager = new ModuleManager($config);
        $result = array(
            'username' => null,
            'password' => null,
        );
        $this->assertEquals(
            $result,
            $manager->getServiceLocator()->get('test.test')
        );
    }
}