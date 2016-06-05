<?php

class Trade
{
	private $pdo;
	private $table;
	private $param;
	private $lot_size;
	public function __construct($pdo,$param,$lot_size)
	{
		$this->pdo =$pdo;
		$this->param=$param;
		$this->lot_size=$lot_size;
	}
	function init()
	{
		print "init\n";
	}
	function entry($r)
	{
		if($r['low']<120.0){
			return 'Buy';
		}
		return null;
	}
	function take_profit($r)
	{
	//	$pos['valid']=false;
	}
}
