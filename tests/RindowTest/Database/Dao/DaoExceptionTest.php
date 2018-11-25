<?php
namespace RindowTest\Database\Dao\DaoExceptionTest;

use PHPUnit\Framework\TestCase;
use Rindow\Database\Dao\Exception\ExceptionInterface as PEARCode;
use Rindow\Database\Dao\Exception\DomainException as PearException;
use Rindow\Database\Dao\Exception\DuplicateKeyException;
use Rindow\Database\Dao\Sql\DaoExceptionAdvisor;


class Test extends TestCase
{
	const InteropException = 'Interop\Lenient\Dao\Exception';
	const RindowException = 'Rindow\Database\Dao\Exception';

	public function testCreateExceptions()
	{
		$className = self::RindowException.'\\DataAccessException';
		$this->assertFalse(class_exists($className));

		$exceptions = array(
			array('CannotAcquireLockException','Transident'),
			array('CannotSerializeTransactionException','Transident'),
			array('CleanupFailureDataAccessException','NonTransident'),
			array('ConcurrencyFailureException','Transident'),
			array('DataAccessResourceFailureException','NonTransident'),
			array('DataIntegrityViolationException','NonTransident'),
			array('DataRetrievalFailureException','NonTransident'),
			array('DeadlockLoserDataAccessException','Transident'),
			array('DuplicateKeyException','NonTransident'),
			array('EmptyResultDataAccessException','NonTransident'),
			array('IncorrectResultSizeDataAccessException','NonTransident'),
			array('IncorrectUpdateSemanticsDataAccessException','NonTransident'),
			array('InvalidDataAccessApiUsageException','NonTransident'),
			array('InvalidDataAccessResourceUsageException','NonTransident'),
			array('NonTransientDataAccessException','NonTransident'),
			array('NonTransientDataAccessResourceException','NonTransident'),
			array('OptimisticLockingFailureException','Transident'),
			array('PermissionDeniedDataAccessException','NonTransident'),
			array('PessimisticLockingFailureException','Transident'),
			array('QueryTimeoutException','Transident'),
			array('RecoverableDataAccessException','Recoverable'),
			array('TransientDataAccessException','Transident'),
			array('TransientDataAccessResourceException','Transident'),
			array('TypeMismatchDataAccessException','NonTransident'),
			array('UncategorizedDataAccessException','NonTransident'),
		);
		$this->assertCount(25,$exceptions);

		foreach ($exceptions as $element) {
			list($name,$type) = $element;
			$className = self::RindowException.'\\'.$name;
			$e = new $className();
			$this->assertInstanceof(self::InteropException.'\\'.$name, $e);
			$this->assertInstanceof(self::InteropException.'\\'.'DataAccessException', $e);
			if($type=='NonTransident') {
				$this->assertInstanceof(self::InteropException.'\\'.'NonTransientDataAccessException', $e);
				$this->assertNotInstanceof(self::RindowException.'\\'.'ExceptionInterface', $e);
				$this->assertInstanceof('DomainException', $e);
			} else if($type=='Transident'){
				$this->assertInstanceof(self::InteropException.'\\'.'TransientDataAccessException', $e);
				$this->assertNotInstanceof(self::RindowException.'\\'.'ExceptionInterface', $e);
				$this->assertInstanceof('RuntimeException', $e);
			} else if($type=='Recoverable'){
				$this->assertInstanceof(self::InteropException.'\\'.'RecoverableDataAccessException', $e);
				$this->assertNotInstanceof(self::RindowException.'\\'.'ExceptionInterface', $e);
				$this->assertInstanceof('RuntimeException', $e);
			} else {
				$this->assertTrue(false);
			}
		}
	}

	public function testTranslatePEARStyleToDaoStyle()
	{
		$pearErrorCodes = array(
    		PEARCode::ERROR               => 'UncategorizedDataAccessException',
    		PEARCode::SYNTAX              => 'InvalidDataAccessResourceUsageException',
    		PEARCode::CONSTRAINT          => 'DataIntegrityViolationException',
    		PEARCode::NOT_FOUND           => 'DataAccessResourceFailureException',
    		PEARCode::ALREADY_EXISTS      => 'DuplicateKeyException',
    		PEARCode::UNSUPPORTED         => 'InvalidDataAccessResourceUsageException',
    		PEARCode::MISMATCH            => 'TypeMismatchDataAccessException',
    		PEARCode::INVALID             => 'InvalidDataAccessResourceUsageException',
    		PEARCode::NOT_CAPABLE         => 'InvalidDataAccessResourceUsageException',
    		PEARCode::TRUNCATED           => 'EmptyResultDataAccessException',
    		PEARCode::INVALID_NUMBER      => 'InvalidDataAccessApiUsageException',
    		PEARCode::INVALID_DATE        => 'InvalidDataAccessApiUsageException',
    		PEARCode::DIVZERO             => 'InvalidDataAccessApiUsageException',
    		PEARCode::NODBSELECTED        => 'InvalidDataAccessResourceUsageException',
    		PEARCode::CANNOT_CREATE       => 'PermissionDeniedDataAccessException',
    		PEARCode::CANNOT_DROP         => 'PermissionDeniedDataAccessException',
    		PEARCode::NOSUCHTABLE         => 'InvalidDataAccessResourceUsageException',
    		PEARCode::NOSUCHFIELD         => 'InvalidDataAccessResourceUsageException',
    		PEARCode::NEED_MORE_DATA      => 'IncorrectResultSizeDataAccessException',
    		PEARCode::NOT_LOCKED          => 'ConcurrencyFailureException',
    		PEARCode::VALUE_COUNT_ON_ROW  => 'DataRetrievalFailureException',
    		PEARCode::INVALID_DSN         => 'DataAccessResourceFailureException',
    		PEARCode::CONNECT_FAILED      => 'DataAccessResourceFailureException',
    		PEARCode::EXTENSION_NOT_FOUND => 'DataAccessResourceFailureException',
    		PEARCode::ACCESS_VIOLATION    => 'PermissionDeniedDataAccessException',
    		PEARCode::NOSUCHDB            => 'DataAccessResourceFailureException',
    		PEARCode::CONSTRAINT_NOT_NULL => 'DataIntegrityViolationException',
    		PEARCode::LOGIN_FAILED        => 'DataAccessResourceFailureException',
		);
		$advisor = new DaoExceptionAdvisor();

		$this->assertCount(28,$pearErrorCodes);

		foreach ($pearErrorCodes as $code => $name) {
			$pearException = new PearException($name,$code);
			$daoException = $advisor->translatePearException($pearException);
			$this->assertInstanceof(self::InteropException.'\\'.$name, $daoException);
			$this->assertEquals($name,$daoException->getMessage());
			$this->assertEquals($code,$daoException->getCode());
			$this->assertEquals($pearException,$daoException->getPrevious());
		}

		// Other type exception.
		$e = new \Exception('test',PEARCode::LOGIN_FAILED);
		$this->assertEquals($e,$advisor->translatePearException($e));

		// Already be the dao exception.
		$e = new DuplicateKeyException('test',PEARCode::LOGIN_FAILED);
		$this->assertEquals($e,$advisor->translatePearException($e));

		// unknown pear code.
		$e = new PearException('test',1234567);
		$this->assertEquals($e,$advisor->translatePearException($e));
	}
}