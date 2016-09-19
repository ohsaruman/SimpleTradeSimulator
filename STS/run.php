<?php
/**
 *
 */
//レバレッジの実装は後回し　たぶん不要
// ロットサイズも後回し　オーダー時に自分で調整すれ
////
/**
 *  Broker Class
 */
class Broker
{
	private $spread;
	public $positions;
	private $pos_serial;
	public $deposit;
	public $orders;
	public $error;
	public function __construct($spread,$deposit)
	{
		$this->spread =$spread;
		$this->pos_serial=0;
		$this->positions=array();
		$this->deposit=$deposit;
		$this->orders=array();
		$this->error='';
	}
	/**
	 * 指定の値段で買えますか nullで成り行き
	 * @param float $p    order price or null
	 * @param float $r    record tick price
	 * @return float or null
	 */
	function Can_I_Buy($p,$r){
		if($p){
			if($p>$r['low']){
				if($p>$r['high']){// orer price higher than high price
					$ret=$r['high'];
				}else{
					$ret=$p; //order price
				}
			}else {
				$ret=false;
			}
		}else{
			$ret=$r['open'];
		}
		return $ret;//market price
	}
	/**
	 * 指定の値段で売れますか nullで成り行き
	 * @param float $p    order price or null
	 * @param float $r    record tick price
	 * @return float or null
	 */
	function Can_I_Sell($p,$r){
		if($p){
			if($p<$r['high']){
				if($p<$r['low']){// orer price lower than low price
					$ret=$r['low'];
				}else{
					$ret=$p; //order price
				}
			}else {
				$ret=false;
			}
		}else{
			$ret=$r['open'];//market price worst case
		}
		return $ret;
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
		if($order['operate'] == 'BUY'){
			if($price = $this->Can_I_Buy($order['price'],$record)){
				$buy_size=$order['amount'];
				if(($price+$this->spread)*$buy_size < $this->deposit){
					$pos=array();
					$pos['entry_price']=$price+$this->spread;//スプレッド分購入価格に上乗せ
					$pos['dir']='LONG';
					$pos['size']=$buy_size;
					$pos['entry_time']=$record['dt'];
					$pos['valid']=true;
					$this->positions[$this->pos_serial++]=$pos;
					$this->deposit -=  $pos['entry_price']*$pos['size'];
					return true;
				}else{
					$this->error=sprintf( "Out of Money");
				}
			}
		}elseif($order['operate']=='SELL'){
			if($price = $this->Can_I_Sell($order['price'],$record)){
				$sell_size=$order['amount'];
				if(($price+$this->spread)*$sell_size < $this->deposit){
					$pos=array();
					$pos['entry_price']=$price-$this->spread;//スプレッド分購入価格から引く
					$pos['dir']='SHORT';
					$pos['size']=$sell_size;
					$pos['entry_time']=$record['dt'];
					$pos['valid']=true;
					$this->positions[$this->pos_serial++]=$pos;
					$this->deposit -=  $pos['entry_price']*$pos['size'];
					return true;
				}else{
					$this->error=sprintf( "Out of Money");
				}
			}
		}
		return false;
	}
	function search_unsettled_positions($dir)
	{
		$pids=array();
		foreach($this->positions as $pid=>$pos){
			if($pos['dir']==$dir and $pos['valid']){
				$pids[]=$pid;
			}
		}
		return $pids;
	}
	function count_unclosed_position()
	{
		$count=0;
		foreach($this->positions as $pos){
			if($pos['valid']){
				$count++;
			}
		}
		return $count;

	}
	/**
	 * [close_position description]
	 * @param  [type] $record [description]
	 * @return [type]         [description]
	 */
	function close_position($pid,$price,$amount,$record)
	{
		if(!isset($this->positions[$pid])){
			print "BAD PID $pid\n";
			return;
		}
		$pos=$this->positions[$pid];
		if(!$pos['valid'])return false;
		$exit_price=0;
		if($pos['dir']=='LONG'){ //LONG POSITIONS CLOSE
				if($price){
						if($price<$record['high']){
							if($price<$record['low']){
								$exit_price=$record['low'];
							}else{
								$exit_price=$price;
							}
						}
				}else{//without limietd order
						$exit_price=$record['open'];
				}
		}	else{
			if($price){
					if($price>$record['low']){
						if($price>$record['high']){
							$exit_price=$record['high'];
						}else{
							$exit_price=$price;
						}
					}
			}else{//without limietd order
					$exit_price=$record['open'];
			}
		}
		$exit_size=0;
		//position size
		if($exit_price){
			if($pos['size']==$amount){//All agreement
				$exit_size=$amount;
				$this->positions[$pid]['valid']=false;
				$this->positions[$pid]['exit_time']=$record['dt'];
				$this->positions[$pid]['exit_price']=$exit_price;
			}elseif($pos['size']>$amount){
				$this->positions[$pid]['size']-=$amount;
				$exit_size=$amount;
			}else{
				$exit_size=$pos['size'];//part of agreement
				$this->positions[$pid]['valid']=false;
				$this->positions[$pid]['exit_time']=$record['dt'];
				$this->positions[$pid]['exit_price']=$exit_price;
			}
			$this->deposit +=  $exit_price*$exit_size;
		}
		return $exit_size;
	}

	function dump_position()
	{
		foreach($this->positions as $k=>$p){
			if(!$p['valid']){
				printf("$k :CLOSED %s %s %s %f %s %f\n"
				,$p['dir'],$p['size'],
				$p['entry_time'],$p['entry_price'],
				$p['exit_time'],$p['exit_price']);
			}else{
				printf("$k :ACTIVE %s %s %s %f\n"
				,$p['dir'],$p['size'],
				$p['entry_time'],$p['entry_price']);
			}
		}
	}
	/**
	 * [execute_order description]
	 * @param  [type] $order  [description]
	 * @param  [type] $record [description]
	 * @return [type]         [description]
	 */
	 //両建てなし　(w/o straddling )
	function execute_order($record)
	{
		foreach($this->orders as &$o){
			if($o['valid']){
					if($o['method']=='NOLIMIT'){
						$o['price']=null;
					}elseif($o['method']=='OCO'){
						if($o['operate']=='SELL'){
							$o['price']=$o['high_price'];//default Take profit
							if($record['low']<$o['low_price']){//loss cut
								$o['price']=$o['low_price'];
							}
						}else{
							$o['price']=$o['low_price'];//default Take profit
							if($record['high']>$o['high_price']){//loss cut
								$o['price']=$o['high_price'];
							}
						}
					}
					printf( "\nORDER %s %d %f",$o['operate'],$o['amount'],$o['price']);
					//Alreadey Have Position
					if($pids=$this->search_unsettled_positions(($o['operate']=='BUY')?'SHORT':'LONG')){
						$amount=$o['amount'];
						foreach($pids as $pid){
								$amount -= $this->close_position($pid,$o['price'],$amount,$record);
								if($amount<=0){//This Order Executed
									$o['valid']=false;
									break;
								}
						}
					}else{
						if($this->open_position($o,$record)){
								$o['valid']=false;
						}
					}
					if(!$o['valid']){
						if($o['method']=='IFD'){
							if($o['operate']=='BUY'){
								$o['price']=$o['high_price'];
								$o['method']='IF';
								$o['operate']='SELL';
							}else{
								$o['price']=$o['low_price'];
								$o['method']='IF';
								$o['operate']='BUY';
							}
							$o['valid']=true; //re-use order
						}elseif($o['method']=='IFO'){
							print "IFO";
							if($o['operate']=='BUY'){
								$o['operate']='SELL';
							}else{
								$o['operate']='BUY';
							}
							$o['method']='OCO';
							$o['valid']=true; //re-use order
						}
					}
			}
		}
		unset($o);
	}
	/**
	 * count order
	 * @return int valid order count
	 */
	 function count_order()
 	{
 		$ret=0;
 		foreach($this->orders as $o){
 			if($o['valid']){
 				$ret++;
 			}
 		}
 		print "Current Order count $ret";
 		return $ret;
 	}
	function dump_order()
	{
		print "\n";
		foreach($this->orders as $o){
			printf("%s %s %s %f %f %f %d \n",
				$o['valid']?'VALID':'----',
				$o['operate'],
				$o['method'],
				$o['price'],
				$o['low_price'],
				$o['high_price'],
				$o['amount']
			);
		}
	}
}

function test_order()
{
	 return array(
		'valid'=>true,
		'operate'=>'SELL',//BUY SELL
		'method'=>'IFO', // IF IFD IFDrel IFO IFOrel  NOLIMIT
		'price'=>120.4,
		'low_price'=>120.41, // OCO LOW
		'high_price'=>120.6,// OCO HIGH
		'amount'=>1);
}

/**
 * Minimum Execute unit tick
 * @param  [type] $r [description]
 * @return [type]    [description]
 */
function tick($r,$broker,$algorithm)
{
	static $n=0;
	printf( "%s ",$r['dt']);
	$broker->execute_order($r);
	//Order for next tick
	$order=test_order();
	if($n==0) {
		$order['operate']='SELL';
		$order['price']=null;
		$broker->orders[]=$order;
	}/*
	if($n==1) {
		$order['operate']='BUY';
		$order['method']='OCO';
		$broker->orders[]=$order;
	}*/
	$n++;
//	$broker->dump_order();
//	$order=test_order();
//	$broker->open_position($order,$r);
/*
	if($order=$algorithm->entry($r)){
		$broker->open_order($order,$r);
	}
*/
	print "\n";
	$broker->dump_position();
}
if(count($argv)<7){
	die("
		NOT enough paramaters
		Require args like this:
			code from_date to_date deposit spread entry_steratgy exit_sterategy
\n");
}else{
	main_program($argv);
}

/**
 * main program
 * @param  mixed $argv [description]
 * @return [type]       [description]
 */
function main_program($argv){
	require_once('.config'); //database connect param
	$code=$argv[1];
	$from_date=$argv[2];
	$to_date=$argv[3];
	$strategy=split('/',$argv[6]);

	$broker=new Broker($argv[5],$argv[4]);//spread,deposit

	require_once("algorithm/{$strategy[0]}.php");
	try {
	    $pdo = new PDO($database['dsn'], $database['user'],$database['pass'],$database['opt']);
			$algorithm= new Trade($pdo,$strategy);
			$algorithm->init();
	    $sql="SELECT code,dt,open,high,low,close
						FROM {$database['table']}
						WHERE code=? AND dt>=? AND dt<=? ORDER BY dt";
	    $stmt = $pdo->prepare($sql);
	    $stmt->execute(array($code,$from_date,$to_date));
	    while($r=$stmt->fetch()){
				tick($r,$broker,$algorithm);
	    }
	}catch (PODException $e){
	    error_log($e->getMessage());
	    die($e->getMessage());
	}catch (RuntimeException $e){
	    error_log($e->getMessage());
	    die($e->getMessage());
	}
}
