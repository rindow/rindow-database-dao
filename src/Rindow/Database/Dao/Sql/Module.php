<?php
namespace Rindow\Database\Dao\Sql;

class Module
{
    public function getConfig()
    {
        return array(
            'container' => array(
                'aliases' => array(
                    //'Rindow\\Database\\Dao\\DefaultSqlDataSource' => 'your_sql_data_source',
                ),
                'components' => array(
                    'Rindow\\Database\\Dao\\Repository\\AbstractSqlRepository' => array(
                        'class' => 'Rindow\\Database\\Dao\\Repository\\GenericSqlRepository',
                        'properties' => array(
                            // SQL table name for repository
                            //'tableName' => array('value' => 'your_table_name'),
                            'tableOperations' => array('ref' => 'Rindow\\Database\\Dao\\Sql\\DefaultTableOperations'),
                        ),
                    ),
                    'Rindow\\Database\\Dao\\Sql\\DefaultTableOperations' => array(
                        'class' => 'Rindow\\Database\\Dao\\Sql\\TableTemplate',
                        'properties' => array(
                            'dataSource' => array('ref' => 'Rindow\\Database\\Dao\\DefaultSqlDataSource'),
                            'queryBuilder' => array('ref' => 'Rindow\\Database\\Sql\\DefaultQueryBuilder'),
                        ),
                    ),
                    'Rindow\\Database\\Sql\\DefaultQueryBuilder' => array(
                        'class' => 'Rindow\\Database\\Dao\\Support\\QueryBuilder',
                    ),

                    /*
                     * Interop DAO Exception Advisor for SQL
                     */
                    'Rindow\\Database\\Sql\\DefaultDaoExceptionAdvisor'=>array(
                        'class' => 'Rindow\\Database\\Dao\\Sql\\DaoExceptionAdvisor',
                    ),
                ),
            ),
            'aop' => array(
                'intercept_to' => array(
                    'Rindow\\Database\\Dao\\Repository\\GenericSqlRepository' => true,
                ),
                'pointcuts' => array(
                    'Rindow\\Database\\Dao\\Repository\\GenericSqlRepository'=> 
                        'execution(Rindow\\Database\\Dao\\Repository\\GenericSqlRepository::'.
                            '(save|delete|deleteById)())',
                ),
                'aspects' => array(
                    'Rindow\\Database\\Sql\\DefaultDaoExceptionAdvisor' => array(
                        'advices' => array(
                            'afterThrowingAdvice' => array(
                                'type' => 'after-throwing',
                                'pointcut_ref' => array(
                                    'Rindow\\Database\\Dao\\Repository\\GenericSqlRepository'=>true,
                                ),
                            ),
                        ),
                    ),
                ),
                'aspectOptions' => array(
                    'Rindow\\Transaction\\DefaultTransactionAdvisor' => array(
                        'advices' => array(
                            'required' => array(
                                'pointcut_ref' => array(
                                    'Rindow\\Database\\Dao\\Repository\\GenericSqlRepository' => true,
                                ),
                            ),
                        ),
                    ),
                ),
            ),
        );
    }
}
