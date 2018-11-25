<?php
namespace Rindow\Database\Dao;

interface XAConnection
{
	/**
	*  @return XAResource
	*/
    public function getXAResource();

	/**
	*  @return Object
	*/
	public function getConnection();

	/**
	*  @return void
	*/
	public function close();
}