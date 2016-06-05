<?php

require_once('.config');

if(count($argv)<7){
	die("
		NOT enough paramaters
		Require args like this:
			code from_date to_date deposit spread entry_steratgy exit_sterategy
\n");
}
class Broker
{
	private $spread;
	public $positions;
	private $pos_serial;
	public $deposit;
	public $lot_size;
	public function __construct($spread,$deposit,$lot_size)
	{
		$this->spread =$spread;
		$this->pos_serial=0;
		$this->positions=array();
		$this->deposit=$deposit;
		$this->lot_size=$lot_size;
	}
	function OpenPosition($operation,$price,$lc_price,$record)
	{
		if($operation == 'Buy'){
			$price+=$this->spread;
			if($record['high']>$price and $record['low']<$price){
				$buy_size=$this->lot_size;
				if($price*$buy_size < $this->deposit){
					$pos=array();
					$pos['entry_price']=$price;
					$pos['lc_price']=$lc_price;
					$pos['dir']='L';
					$pos['size']=$buy_size;
					$pos['open_time']=$record['dt'];
					$pos['valid']=true;
					$this->positions[$this->pos_serial++]=$pos;
					$this->deposit -=  $pos['entry_price']*$pos['size'];
					return $pos;
				}
			}
		}elseif($operation=='Sell'){
		}
		return false;

	}
	function LossCutPosition($record)
	{
		foreach($this->positions as $k=>$p){
			if($p['valid']){
				if($p['dir']=='L' and $p['lc_price']>$record['high'] ){
					$this->deposit += $p['size']*($p['lc_price']-$spread);//Unlock money
					$this->positions[$k]['close_time']=$record['dt'];
					$this->positions[$k]['valid']=false;
				}elseif($p['dir']=='S' and $p['lc_price']<$record['low'] ){
					$this->deposit += $p['size']*($p['entry_price'] - $p['lc_price']+$spread);//Unlock money
					$this->positions[$k]['close_time']=$record['dt'];
					$this->positions[$k]['valid']=false;
				}
			}
		}

	}
	function ClosePosition($order_price)
	{
			$positions=array($order_price);
	}
}

/*
 main  program
*/
$code=$argv[1];
$from_date=$argv[2];
$to_date=$argv[3];
$strategy=split('/',$argv[6]);
$lot_size = 1000;

$bloker=new Broker($argv[5],$argv[4],$lot_size);//spread,deposit.lot_size

require_once("algorithm/{$strategy[0]}.php");
try {
    $pdo = new PDO($database['dsn'], $database['user'],$database['pass'],$database['opt']);
		$algorithm= new Trade($pdo,$strategy,$lot_size);
		$algorithm->init();
    /*
      Back test
    */
    $sql="select code,dt,high,low  from {$database['table']} where code=? and dt>=? and dt<=? order by dt";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($code,$from_date,$to_date));
		$positions=array();
    while($r=$stmt->fetch()){
			$bloker->LossCutPosition($r);
			foreach($bloker->positions as $k=>$p){
				if($p['valid']){
					if($profit=$algorithm->take_profit($r)){
					}
				}
			}
			if($order=$algorithm->entry($r)){
				$bloker->OpenPosition($order,120.0,119.5,$r);
			}
    }
}catch (PODException $e){
    error_log($e->getMessage());
    echo($e->getMessage());
    die();
}catch (RuntimeException $e){
    error_log($e->getMessage());
    echo($e->getMessage());
    die();
}
