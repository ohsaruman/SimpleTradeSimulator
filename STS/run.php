<?php
/**
 *
 */
if(count($argv)<7){
	die("
		NOT enough paramaters
		Require args like this:
			code from_date to_date deposit spread entry_steratgy exit_sterategy
\n");
}else{
	main($argv);
}

/**
 *  Broker Class
 */
class Broker
{
	private $spread;
	public $positions;
	private $pos_serial;
	public $deposit;
	public $lot_size;
	public $orders;
	public function __construct($spread,$deposit,$lot_size)
	{
		$this->spread =$spread;
		$this->pos_serial=0;
		$this->positions=array();
		$this->deposit=$deposit;
		$this->lot_size=$lot_size;
		$this->orders=array();
	}
	/**
	 * 指定の値段で買えますか
	 * @param [type] $p    [description]
	 * @param [type] $low  [description]
	 * @param [type] $high [description]
	 */
	function Can_I_Buy($p,$low,$high){
		if($p){
			if($p>$low){
				if($p>$high){//そこまで高くなくても買える
					return $high;
				}else{
					return $p;//指定した値段で買える
				}
			}else {
				print "FALSE";
				return false;
			}
		}else{
			return $high; //成り行き
		}
	}
	/**
	 * open new position
	 * @param  string $order  Order data
	 * @param  mixed $record current price
	 * @return bool  result
	 * @todo support ask bid price data
	 */
	function open_position($order,$record)
	{
		if($order['operate'] == 'Buy'){
			if($price = $this->Can_I_Buy($order['price'],$record['low'],$record['high'])){
				$buy_size=$order['lot']*$this->lot_size;
				if(($price+$this->spread)*$buy_size < $this->deposit){
					$pos=array();
					$pos['entry_price']=$price+$this->spread;//スプレッド分購入価格に上乗せ
					$pos['lc_price']=$price-$order['lc_price'];
					$pos['dir']='L';
					$pos['size']=$buy_size;
					$pos['open_time']=$record['dt'];
					$pos['valid']=true;
					$this->positions[$this->pos_serial++]=$pos;
					$this->deposit -=  $pos['entry_price']*$pos['size'];
					return true;
				}else{
					printf( "I want to buy but NO MONEY,PRICE %f DEPOSIT %f\n",($price+$this->spread),$this->deposit);
				}
			}
		}elseif($operation=='Sell'){
		}
		return false;

	}
	/**
	 * [loss_cut_position description]
	 * @param  [type] $record [description]
	 * @return [type]         [description]
	 */
	function loss_cut_position($record)
	{
		foreach($this->positions as $k=>$p){
			if($p['valid']){
				if($p['dir']=='L' and $p['lc_price']>$record['high'] ){
					$this->deposit += $p['size']*($p['lc_price']-$this->spread);//Unlock money
					$this->positions[$k]['close_time']=$record['dt'];
					$this->positions[$k]['valid']=false;
				}elseif($p['dir']=='S' and $p['lc_price']<$record['low'] ){
					$this->deposit += $p['size']*($p['entry_price'] - $p['lc_price']+$this->spread);//Unlock money
					$this->positions[$k]['close_time']=$record['dt'];
					$this->positions[$k]['valid']=false;
				}
			}
		}

	}
	function close_position($order_price)
	{
			$positions=array($order_price);
	}
}

/**
 * main program
 * @param  mixed $argv [description]
 * @return [type]       [description]
 */
function main($argv){
	require_once('.config'); //database connect param
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
	    while($r=$stmt->fetch()){
				//最初にロスカットに該当するかチェック
				$bloker->loss_cut_position($r);
				//現在保有中のポジションをクローズするかチェック
				foreach($bloker->positions as &$p){
					if($p['valid']){
						if($profit=$algorithm->take_profit($r)){
							$p['valid']=false;
						}
					}
				}
				//新規オーダーの処理
				foreach($bloker->orders as &$o){
						if($o['valid']){
							if($bloker->open_position($o,$r)){
									$o['valid']=false;
							}
							$o['until']--;
							if($o['until']<=0){
								$o['valid']=false;
							}
						}
				}
				//新たにポジションを持つ場合は、次の足でエントリーするためのオーダーを入れる
				if($operate=$algorithm->entry($r)){
					$bloker->orders[]=array('valid'=>true,'operate'=>$operate,'price'=>120.0,'lc_price'=>0.5,'lot'=>1,'until'=>1);
				}
	    }
	}catch (PODException $e){
	    error_log($e->getMessage());
	    die($e->getMessage());
	}catch (RuntimeException $e){
	    error_log($e->getMessage());
	    die($e->getMessage());
	}
}
