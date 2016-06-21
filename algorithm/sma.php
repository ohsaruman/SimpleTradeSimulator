<?php
/**
 * Trade Class Sma
 * call from run.php
 */
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
	/**
	 * [init description]
	 * @return [type] [description]
	 */
	function init()
	{
		print "init\n";
	}
	/**
	 * [entry description]
	 * @param  [type] $r [description]
	 * @return [type]    [description]
	 */
	function entry($r)
	{
		if($r['low']<120.0){
			return 'Buy';
		}
		return null;
	}
	/**
	 * [take_profit description]
	 * @param  [type] $r [description]
	 * @return [type]    [description]
	 */
	function take_profit($r)
	{
	//	$pos['valid']=false;
	}
}
