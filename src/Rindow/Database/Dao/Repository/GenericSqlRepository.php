<?php
namespace Rindow\Database\Dao\Repository;

use Interop\Lenient\Dao\Repository\CrudRepository;
use Interop\Lenient\Dao\Repository\DataMapper;
use Rindow\Database\Dao\Exception;

class GenericSqlRepository implements CrudRepository,DataMapper
{
    //protected $repositoryFactory;
    protected $tableOperations;
    protected $keyName = 'id';
    protected $tableName;
    protected $dataMapper;
    protected $activeRepository = false;
    protected $fetchClassSupported;
    protected $transactionBoundary;
    protected $compiledCascadedFieldConfig;

    public function __construct($tableOperations=null,$tableName=null,$keyName=null,$dataMapper=null)
    {
        //if($repositoryFactory)
        //    $this->setRepositoryFactory($repositoryFactory);
        if($tableOperations)
            $this->setTableOperations($tableOperations);
        if($tableName)
            $this->setTableName($tableName);
        if($keyName)
            $this->setKeyName($keyName);
        if($dataMapper)
            $this->setDataMapper($dataMapper);
    }

    //protected function setRepositoryFactory($repositoryFactory)
    //{
    //    $this->repositoryFactory = $repositoryFactory;
    //}

    //public function getRepositoryFactory()
    //{
    //    return $this->repositoryFactory;
    //}

    public function setTableOperations($tableOperations)
    {
        $this->tableOperations = $tableOperations;
    }

    public function getTableOperations()
    {
        return $this->tableOperations;
    }

    public function setTableName($tableName)
    {
        $this->tableName = $tableName;
    }

    public function setKeyName($keyName)
    {
        $this->keyName = $keyName;
        return $this;
    }

    public function setDataMapper(DataMapper $dataMapper)
    {
        $this->dataMapper = $dataMapper;
    }

    public function setTransactionBoundary($transactionBoundary)
    {
        $this->transactionBoundary = $transactionBoundary;
    }

    public function getTransactionBoundary()
    {
        return $this->transactionBoundary;
    }

    protected function getLastInsertId()
    {
        return $this->tableOperations->getLastInsertId($this->tableName,$this->keyName);
    }

    public function isFetchClassSupported()
    {
        if($this->fetchClassSupported===null) {
            $this->fetchClassSupported = $this->tableOperations->isFetchClassSupported() ? true : false;
        }
        return $this->fetchClassSupported;
    }

    public function assertTableName()
    {
        if($this->tableName==null)
            throw new Exception\DomainException('tableName is not specified.');
    }

    public function getQueryBuilder()
    {
        return $this->tableOperations->getQueryBuilder();
    }

    protected function makeDocument($entity)
    {
        if($this->dataMapper) {
            $entity = $this->dataMapper->demap($entity);
            if(!is_array($entity))
                throw new Exception\InvalidArgumentException('mapped document must be array.');
        }
        $document = $this->demap($entity);
        if(!is_array($document))
            throw new Exception\InvalidArgumentException('the entity must be array.');
        return $document;
    }

    public function save($entity)
    {
        if(!$this->transactionBoundary)
            return $this->doSave($entity);
        return $this->transactionBoundary->required(function($manager,$entity){
            return $manager->doSave($entity);
        },array($this,$entity));
    }

    public function doSave($entity)
    {
        $this->assertTableName();
        $document = $this->makeDocument($entity);

        $values = $this->postDemap($document);
        if(isset($values[$this->keyName])) {
            $this->preUpdate($document);
            $modified = $this->update($values);
            if(!$modified) {
                try {
                    $this->create($values);
                } catch(Exception\RuntimeException $e) {
                    if($e->getCode()!=ExceptionInterface::ALREADY_EXISTS)
                        throw $e;
                }
            }
            $this->postUpdate($document);
        } else {
            $this->preCreate($document);
            $this->create($values);
            $id = $this->getLastInsertId();
            if($this->dataMapper) {
                $entity = $this->dataMapper->fillId($entity,$id);
                if($document!==$entity)
                    $document =  $this->fillId($document,$id);
            } else {
                $entity   =  $this->fillId($entity,$id);
                if($document!==$entity)
                    $document =  $this->fillId($document,$id);
            }
            $this->postCreate($document);
        }
        return $entity;
    }

    protected function create(array $values)
    {
        if(!isset($values[$this->keyName]))
            unset($values[$this->keyName]);
        $this->tableOperations->insert($this->tableName,$values);
    }

    protected function update(array $values)
    {
        $filter = array($this->keyName=>$values[$this->keyName]);
        unset($values[$this->keyName]);
        return $this->tableOperations->update($this->tableName,$filter,$values);
    }

    public function delete($entity)
    {
        $this->assertTableName();
        $document = $this->makeDocument($entity);
        if(!isset($document[$this->keyName]))
            throw new Exception\DomainException('No valid id fields found.');

        return $this->deleteById($document[$this->keyName]);
    }

    public function deleteById($id)
    {
        $this->assertTableName();
        $filter = array($this->keyName=>$id);
        $result = $this->deleteAll($filter);
        return $result;
    }

    public function deleteAll(array $filter=null)
    {
        if(!$this->transactionBoundary)
            return $this->doDeleteAll($filter);
        return $this->transactionBoundary->required(function($manager,$filter){
            return $manager->doDeleteAll($filter);
        },array($this,$filter));
    }

    public function doDeleteAll(array $filter=null)
    {
        $this->assertTableName();
        $this->preDelete($filter);
        $result = $this->tableOperations->delete($this->tableName,$filter);
        $this->postDelete($filter);
        return $result;
    }

    public function findAll(array $filter=null,array $sort=null,$limit=null,$offset=null)
    {
        if(!$this->transactionBoundary) {
            $results = $this->doFindAll($filter,$sort,$limit,$offset);
            return $results;
        } else {
            return $this->transactionBoundary->required(function($manager,$filter,$sort,$limit,$offset){
                return $manager->doFindAll($filter,$sort,$limit,$offset);
            },array($this,$filter,$sort,$limit,$offset));
        }
    }

    public function doFindAll(array $filter=null,array $sort=null,$limit=null,$offset=null)
    {
        $this->assertTableName();
        $fetchClass = null;
        if($this->dataMapper)
            $fetchClass = $this->dataMapper->getFetchClass();
        if($fetchClass==null)
            $fetchClass = $this->getFetchClass();
        if($fetchClass && !$this->isFetchClassSupported())
            throw new Exception\DomainException('The fetchClass ability is not supported.');
        $results = $this->doFindAllTableOperation($this->tableName,$filter,$sort,$limit,$offset,$fetchClass);
        $results->addFilter(array($this,'preMap'));
        $results->addFilter(array($this,'map'));
        if($this->dataMapper)
            $results->addFilter(array($this->dataMapper,'map'));
        return $results;
    }

    protected function doFindAllTableOperation($tableName,$filter,$sort,$limit,$offset,$fetchClass)
    {
        return $this->tableOperations->find($tableName,$filter,$sort,$limit,$offset,$fetchClass);
    }

    public function findOne(array $filter=null,array $sort=null,$offset=null)
    {
        $limit = 1;
        $results = $this->findAll($filter,$sort,$limit,$offset);
        $entity = null;
        foreach ($results as $result) {
            $entity = $result;
        }
        return $entity;
    }

    public function findById($id)
    {
        $filter = array($this->keyName=>$id);
        return $this->findOne($filter);
    }

    public function count(array $filter=null)
    {
        $this->assertTableName();
        return $this->tableOperations->count($this->tableName,$filter);
    }

    public function existsById($id)
    {
        $filter = array($this->keyName=>$id);
        $count = $this->count($filter);
        return $count ? true : false;
    }

    protected function createCascadedField($entity,$property,$tableName,$masterIdName,$fieldName)
    {
        if(!isset($entity[$property]))
            return;
        $dataList = $entity[$property];
        $masterId = $entity[$this->keyName];
        $tableOperations = $this->getTableOperations();
        foreach ($dataList as $data) {
            if(is_scalar($data)) {
                $data = array($masterIdName=>$masterId,$fieldName=>$data);
            } elseif(is_array($data)) {
                $data[$masterIdName] = $masterId;
            } elseif(is_object($data)) {
                $data = get_object_vars($data);
                $data[$masterIdName] = $masterId;
            }
            $tableOperations->insert($tableName,$data);
        }
    }

    protected function deleteCascadedField($filter,$cascadedTableName,$masterIdName)
    {
        $tableName = $this->tableName;
        $keyName = $this->keyName;
        $where = '';
        foreach ($filter as $key => $value) {
            if($where=='')
                $where .=' WHERE ';
            else
                $where .=' AND ';
            $where .= "${tableName}.${key} = :${key}";
            $params[':'.$key] = $value;
        }
        $sql = "DELETE FROM ${cascadedTableName} WHERE ${cascadedTableName}.${masterIdName} IN (SELECT ${tableName}.${keyName} FROM ${tableName}".$where.")";
        $this->getTableOperations()->executeUpdate($sql,$params);
    }

    protected function updateCascadedField($entity,$property,$tableName,$masterIdName,$fieldName)
    {
        if(isset($entity[$property]))
            $dataList = $entity[$property];
        else
            $dataList = array();
        $dataListNew = array();
        $dataListRaw = array();
        foreach ($dataList as $row) {
            if(is_array($row)) {
                $data = $row[$fieldName];
            } elseif(is_object($row)) {
                $data = $row->$fieldName;
            } else {
                $data = $row;
            }
            $dataListNew[] = $data;
            $dataListRaw[] = $row;
        }
        $dataList = $dataListNew;
        $tableOperations = $this->getTableOperations();
        $cursor = $tableOperations->find(
            $tableName,
            array($masterIdName=>$entity[$this->keyName]));
        $delete = array();
        foreach($cursor as $row) {
            $data = $row[$fieldName];
            if(!in_array($data, $dataList))
                $delete[] = $data;
            $storedDataList[$data] = true;
        }
        foreach ($delete as $data) {
            $tableOperations->delete($tableName,
                array($masterIdName=>$entity[$this->keyName],$fieldName=>$data));
        }
        foreach ($dataListRaw as $data) {
            if(is_array($data)) {
                $row = $data;
                $data = $data[$fieldName];
            } elseif(is_object($data)) {
                $row = get_object_vars($data);
                $row[$masterIdName] = $entity[$this->keyName];
                $data = $data->$fieldName;
            } else {
                $row = array($fieldName=>$data);
            }
            $row[$masterIdName] = $entity[$this->keyName];
            if(!isset($storedDataList[$data])) {
                $tableOperations->insert($tableName,$row);
            }
        }
    }

    protected function detachCascaedField($entity,$property)
    {
        unset($entity[$property]);
        return $entity;
    }

    protected function attachCascaedField($data,$property,$tableName,$masterIdName,$fieldName,$rawmode=null)
    {
        if($rawmode) {
            $cursor = $this->getTableOperations()->find($tableName,array($masterIdName=>$data[$this->keyName]),null,null,null,null,$lazy=true);
            $data[$property] = $cursor;
            return $data;
        }
        $cursor = $this->getTableOperations()->find($tableName,array($masterIdName=>$data[$this->keyName]));
        $data[$property] = array();
        foreach($cursor as $row) {
            $data[$property][] = $row[$fieldName];
        }
        return $data;
    }

    protected function getCascadedFieldConfig()
    {
        if($this->compiledCascadedFieldConfig===null) {
            $this->compiledCascadedFieldConfig =
                $this->cascadedFieldConfig();
        }
        return $this->compiledCascadedFieldConfig;
    }

    public function getFetchClass()
    {
        return null;
    }

    public function fillId($values,$id)
    {
        $values[$this->keyName] = $id;
        return $values;
    }

    public function demap($entity)
    {
        return $entity;
    }

    public function map($entity)
    {
        return $entity;
    }

    protected function cascadedFieldConfig()
    {
        // $config = parent::cascadedFieldConfig();
        // array_push($config,
        //    array(
        //          'property'=>'cascadedFieldName',
        //          'tableName'=>'externalTableName',
        //          'masterIdName'=>'masterIdNameOnExternalTable',
        //          'fieldName'=>'fieldNameOfcascatedDataOnExternalTable',
        //    )
        // );
        // return $config;
        return array();
    }

    protected function postDemap($entity)
    {
        $config = $this->getCascadedFieldConfig();
        if(!empty($config)) {
            foreach ($config as $info) {
                $entity = $this->detachCascaedField($entity,$info['property']);
            }
        }
        return $entity;
    }

    public function preMap($entity)
    {
        $config = $this->getCascadedFieldConfig();
        if(!empty($config)) {
            foreach ($config as $info) {
                if(isset($info['rawmode'])&&$info['rawmode']) {
                    $rawmode = true;
                } else {
                    $rawmode = false;
                }
                $entity = $this->attachCascaedField($entity,
                    $info['property'],$info['tableName'],
                    $info['masterIdName'],$info['fieldName'],
                    $rawmode);
            }
        }
        return $entity;
    }

    protected function preCreate($entity)
    {
    }

    protected function postCreate($entity)
    {
        $config = $this->getCascadedFieldConfig();
        if(!empty($config)) {
            foreach ($config as $info) {
                $this->createCascadedField($entity,
                    $info['property'],$info['tableName'],
                    $info['masterIdName'],$info['fieldName']);
            }
        }
    }

    protected function preUpdate($entity)
    {
    }

    protected function postUpdate($entity)
    {
        $config = $this->getCascadedFieldConfig();
        if(!empty($config)) {
            foreach ($config as $info) {
                $this->updateCascadedField($entity,
                    $info['property'],$info['tableName'],
                    $info['masterIdName'],$info['fieldName']);
            }
        }
    }

    protected function preDelete($filter)
    {
        $config = $this->getCascadedFieldConfig();
        if(!empty($config)) {
            foreach ($config as $info) {
                $this->deleteCascadedField($filter,
                    $info['tableName'],$info['masterIdName']);
            }
        }
    }

    protected function postDelete($filter)
    {
    }
}
