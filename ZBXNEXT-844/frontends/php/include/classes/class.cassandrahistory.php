<?php

class CassandraHistory {

	private static $instance = null;

	private $pool = null;
	private $cf = null;

	private function __construct(){
		global $DB;

		if(isset($DB['USE_CASSANDRA'])){
			$this->pool = new ConnectionPool($DB['CASSANDRA_KEYSPACE'], $DB['CASSANDRA_IP']);
			$this->cf = new ColumnFamily($this->pool, 'metric');
		}
	}

	public static function i(){
		if(self::$instance === null){
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * @return bool
	 */
	public function enabled(){
		return $this->pool !== null;
	}

	public function getDataForGraph($itemid, $from, $to, $timeSize, $pxSize){
		$result = $groupedData = $maxClocks = array();

		$data = $this->getData($itemid, $from, $to);

		foreach($data as $clock => $value){
			$idx = round(($pxSize * (($clock + ($timeSize - $from % $timeSize)) % $timeSize)) / $timeSize, 0);
			$groupedData[$idx][] = $value;

			if(!isset($maxClocks[$idx]) || $maxClocks[$idx] < $clock){
				$maxClocks[$idx] = $clock;
			}
		}

		foreach($groupedData as $idx => $values){
			$result[$idx]['count'] = count($values);
			$result[$idx]['min'] = min($values);
			$result[$idx]['max'] = max($values);
			$result[$idx]['avg'] = zbx_avg($values);
			$result[$idx]['clock'] = $maxClocks[$idx];
		}

		return $result;
	}

	public function getData($itemid, $from = null, $to = null, $limit = null){
		$result = array();

//sdi($itemid);
//sdi($from);
//sdi($to);
		if(null === $from){
			$from = 0;
		}
		if(null === $to){
			$to = time();
		}

		$tzOffset = date('Z');
		$time = strtotime('midnight', $from) + $tzOffset;



		$count = 0;
		while($time < $to){
			try{
				$dayData = $this->cf->get($this->_packCompositeKey($itemid, $time));

				foreach($dayData as $timeOffset => $value){

					$clock = bcround(bcdiv($timeOffset, 1000));

					if(($clock > $from) && ($clock < $to)){
						$result[$clock] = $value;
						$count++;
						if(null !== $limit && $count > $limit){
							break 2;
						}
					}
				}
			}
			catch(cassandra_NotFoundException $e){}

			$time = strtotime('tomorrow', $time) + $tzOffset;
		}

		return $result;
	}



	/**
	 * @param $itemid
	 * @param $timestamp
	 * @return string
	 */
	private function _packCompositeKey($itemid, $timestamp){
		return $this->_packLong($itemid) . $this->_packLong($timestamp);
	}

	/**
	 * @param $value
	 * @return string
	 */
	private function _packLong($value) {
		// If we are on a 32bit architecture we have to explicitly deal with
		// 64-bit twos-complement arithmetic since PHP wants to treat all ints
		// as signed and any int over 2^31 - 1 as a float
		if(PHP_INT_SIZE == 4){
			$neg = $value < 0;

			if($neg){
				$value *= -1;
			}

			$hi = (int) ($value / 4294967296);
			$lo = (int) $value;

			if($neg){
				$hi = ~$hi;
				$lo = ~$lo;
				if(($lo & (int) 0xffffffff) == (int) 0xffffffff){
					$lo = 0;
					$hi++;
				}
				else{
					$lo++;
				}
			}
		}
		else{
			$hi = $value >> 32;
			$lo = $value & 0xFFFFFFFF;
		}

		$data = pack('xC1N2x', 8, $hi, $lo);

		return $data;
	}

}
