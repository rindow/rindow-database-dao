<?php
namespace Rindow\Database\Dao\Sql;

use Rindow\Database\Dao\Exception\ExceptionInterface as PEARCode;
use Rindow\Database\Dao\Exception\ExceptionInterface as PearException;
use Interop\Lenient\Dao\Exception\DataAccessException as DaoException;

class DaoExceptionAdvisor
{
    const DaoException = 'Rindow\\Database\\Dao\\Exception';

    protected $pearErrorCodes = array(
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

    public function translatePearException($pearException)
    {
        if(!($pearException instanceof PearException))
            return $pearException;
        if($pearException instanceof DaoException)
            return $pearException;
        $code = $pearException->getCode();
        if(!isset($this->pearErrorCodes[$code]))
            return $pearException;
        $className = self::DaoException.'\\'.$this->pearErrorCodes[$code];
        $daoException = new $className($pearException->getMessage(),$pearException->getCode(),$pearException);
        return $daoException;
    }

    public function afterThrowingAdvice(/*JoinPointInterface*/ $joinPoint)
    {
        $e = $joinPoint->getThrowing();
        if($e) {
            $e = $this->translatePearException($e);
            $joinPoint->setThrowing($e);
        }
    }
}
