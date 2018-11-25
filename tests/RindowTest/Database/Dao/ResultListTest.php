<?php
namespace RindowTest\Database\Dao\ResultListTest;

use PHPUnit\Framework\TestCase;
use Rindow\Database\Dao\Support\ResultList;

class TestCursor
{
	protected $count = 0;
	protected $opened = true;
	public function fetch()
	{
		if($this->count >= 3)
			return null;
		$this->count++;
		return array('id'=>$this->count,'name'=>'item-'.$this->count);
	}
	public function close()
	{
		$this->opened = false;
	}
	public function isOpened()
	{
		return $this->opened;
	}
}
class Loader
{
	public function load($row)
	{
		$row['opt'] = 'opt';
		return $row;
	}
}

class Test extends TestCase
{
    public function getItems($resultset)
    {
    	$results = array();
    	foreach ($resultset as $value) {
    		$results[] = $value;
    	}
    	return $results;
    }

	public function testNormal()
	{
		$cursor = new TestCursor();
		$results = new ResultList();
		$results->setFetchFunction(array($cursor,'fetch'));
		$results->setCloseFunction(array($cursor,'close'));
		$this->assertEquals(array(
				array('id'=>1,'name'=>'item-1'),
				array('id'=>2,'name'=>'item-2'),
				array('id'=>3,'name'=>'item-3'),
			),
			$this->getItems($results));
		$this->assertTrue($cursor->isOpened());
		$results->close();
		$this->assertFalse($cursor->isOpened());
	}

	public function testLoader()
	{
		$cursor = new TestCursor();
		$results = new ResultList(array($cursor,'fetch'),array($cursor,'close'));
		$results->addFilter(array(new Loader(),'load'));
		$filters = $results->getFilters();
		$this->assertInstanceof(__NAMESPACE__.'\Loader',$filters[0][0]);
		$this->assertEquals(array(
				array('id'=>1,'name'=>'item-1','opt'=>'opt'),
				array('id'=>2,'name'=>'item-2','opt'=>'opt'),
				array('id'=>3,'name'=>'item-3','opt'=>'opt'),
			),
			$this->getItems($results));
		$this->assertTrue($cursor->isOpened());
		$results->close();
		$this->assertFalse($cursor->isOpened());
	}

	public function testCurrent()
	{
		$results = new ResultList();
		$results->setFetchFunction(array(new TestCursor(),'fetch'));
		$results->addFilter(array(new Loader(),'load'));
		$this->assertEquals(array('id'=>1,'name'=>'item-1','opt'=>'opt'),$results->current());
		$results->next();
		$this->assertEquals(array('id'=>2,'name'=>'item-2','opt'=>'opt'),$results->current());
	}
}