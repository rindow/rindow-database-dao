<?php
namespace Rindow\Database\Dao\Support;

use Rindow\Container\ConfigurationFactory;
/*use Rindow\Container\ServiceLocator;*/
use Rindow\Database\Dao\Exception;

class ConnectionFactory
{
    public static function factory(/*ServiceLocator*/ $serviceManager,$componentName,$factoryArgs)
    {
    	if(isset($factoryArgs['config']))
    		$config = ConfigurationFactory::factory($serviceManager,$componentName,$factoryArgs);
    	else
	    	$config = $factoryArgs;
    	if(!isset($config['dataSource']))
    		throw new Exception\DomainException('dataSource is not specified for "'.$componentName.'"');
    	$dataSource = $serviceManager->get($config['dataSource']);
    	$username = $password = null;
    	if(isset($config['username']))
    		$username = $config['username'];
    	if(isset($config['password']))
    		$password = $config['password'];
    	return $dataSource->getConnection($username,$password);
    }
}