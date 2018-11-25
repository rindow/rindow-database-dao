<?php
namespace RindowTest\Database\Dao\GenericPagingRepositoryTest;

use PHPUnit\Framework\TestCase;
use Interop\Lenient\Dao\Repository\CrudRepository;
use Rindow\Database\Dao\Repository\GenericPagingRepository;
use ArrayObject;

class TestCrudRepository implements CrudRepository
{
	protected $datas = array();
	protected $findRequest;
	protected $countRequest;

	public function getFindRequest()
	{
		return $this->findRequest;
	}

	public function getCountRequest()
	{
		return $this->countRequest;
	}

	public function setData(array $datas)
	{
		$this->datas = $datas;
	}

    public function save($entity){ throw new \Exception('Illegal Operation'); }
    public function findById($id){ throw new \Exception('Illegal Operation'); }
    public function findAll(array $filter=null,array $sort=null,$limit=null,$offset=null)
    {
    	if(empty($filter))
    		$filter = array();
    	if(empty($sort))
    		$sort = array();
    	$this->findRequest = "filter:".implode(',',array_map(function($key,$value){return "$key=$value";}, array_keys($filter),$filter));
    	$this->findRequest .= " sort:".implode(',',array_map(function($key,$value){return "$key=$value";}, array_keys($sort),$sort));
    	$this->findRequest .= " limit:".$limit;
    	$this->findRequest .= " offset:".$offset;
    	return new ArrayObject($this->datas);
    }
    public function findOne(array $filter=null,array $sort=null,$offset=null){}
    public function delete($entity){ throw new \Exception('Illegal Operation'); }
    public function deleteById($id){ throw new \Exception('Illegal Operation'); }
    public function deleteAll(array $filter=null){ throw new \Exception('Illegal Operation'); }
    public function existsById($id){ throw new \Exception('Illegal Operation'); }
    public function count(array $filter=null)
    {
    	if(empty($filter))
    		$filter = array();
    	$this->countRequest = "count:".implode(',',array_map(function($key,$value){return "$key=$value";}, array_keys($filter),$filter));
    	return count($this->datas);
    }
    public function getQueryBuilder(){ throw new \Exception('Illegal Operation'); }
}


class Test extends TestCase
{
	public function getRepository()
	{
		$repository = new TestCrudRepository();
		$pagingRepository = new GenericPagingRepository($repository);
		return array($pagingRepository,$repository);
	}

	public function testNormal()
	{
		list($repository,$connection) = $this->getRepository('foo');
		$connection->setData(array(
			array('id'=>1,'text'=>'one'),
			array('id'=>2,'text'=>'two'),
			array('id'=>3,'text'=>'three'),
			array('id'=>4,'text'=>'four'),
			array('id'=>5,'text'=>'five'),
			array('id'=>6,'text'=>'six'),
		));

		$paginator = $repository->findAll(array('text'=>'foo'),array('id'=>1));
		$paginator->setPage(1);
		$paginator->setItemMaxPerPage(3);
		foreach ($paginator as $row) {
			;
		}
		$paginator->getTotalItems();
		$this->assertEquals('filter:text=foo sort:id=1 limit:3 offset:0',
			$connection->getFindRequest());
		$this->assertEquals('count:text=foo',
			$connection->getCountRequest());

		$paginator = $repository->findAll(array('text'=>'foo'),array('id'=>1));
		$paginator->setPage(2);
		$paginator->setItemMaxPerPage(3);
		foreach ($paginator as $row) {
			;
		}
		$paginator->getTotalItems();
		$this->assertEquals('filter:text=foo sort:id=1 limit:3 offset:3',
			$connection->getFindRequest());
		$this->assertEquals('count:text=foo',
			$connection->getCountRequest());
	}
}
