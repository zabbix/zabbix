<?php

class CassandraHistory {

	private static $instance = null;

	private $pool = null;
	private $metric = null;
	private $itemidIndex = null;

	private function __construct(){
		global $DB;

		if(isset($DB['USE_CASSANDRA'])){
			$this->pool = new ConnectionPool($DB['CASSANDRA_KEYSPACE'], $DB['CASSANDRA_IP']);
			$this->metric = new ColumnFamily($this->pool, 'metric');
			$this->itemidIndex = new ColumnFamily($this->pool, 'metric_by_parameter');
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
			$idx = ($pxSize * (($clock + ($timeSize - $from % $timeSize)) % $timeSize)) / $timeSize;
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

	public function getAggregate($itemid, $timestamp, $function){
		$result = null;

		$data = $this->getData($itemid, $timestamp, time());

		if(!empty($data)){
			switch($function){
				case 'min':
					$result = min($data);
					break;
				case 'max':
					$result = max($data);
					break;
				case 'avg':
					$result = zbx_avg($data);
					break;
			}
		}

		return $result;
	}

	public function getData($itemid, $from = null, $to = null, $limit = null, $order = ZBX_SORT_UP){
		$result = array();

		$tzOffset = date('Z');

		if ($from === null) {
			$from = 0;
			$keyFrom = '';
		}
		else {
			$keyFrom = $this->_packCompositeKey($itemid, bcmul(strtotime('midnight', $from) + $tzOffset, 1000));
		}

		if ($to === null) {
			$to = time();
			$keyTo = '';
		}
		else {
			$keyTo = $this->_packCompositeKey($itemid, bcmul($to + $tzOffset, 1000));
		}

		if ($order == ZBX_SORT_DOWN) {
			$tmp = $keyFrom;
			$keyFrom = $keyTo;
			$keyTo = $tmp;
		}

		try {
			$keys = $this->itemidIndex->get($itemid, null, $keyFrom, $keyTo, ($order == ZBX_SORT_DOWN));
		}
		catch (cassandra_NotFoundException $e) {
			// no records found
			$keys = array();
		}
		$keys = array_keys($keys);

		$rows = $this->metric->multiget($keys, null, '', '', ($order == ZBX_SORT_DOWN));

		$count = 0;
		foreach ($rows as $key => $column) {
			/* key format: ..<itemid>...< time >.
			 * x - skip one byte
			 * N - uint32 value
			 */
			$unpackedKey = unpack('x13/N2/x/', $key);

			$time = bcmul($unpackedKey['1'], 4294967296);
			$time = bcadd($time, $unpackedKey['2']);
			$time = bcdiv($time, 1000, 0);

			foreach ($column as $timeOffset => $value) {
				// maybe in future we will need to handle milliseconds here.
				$clock = round($timeOffset / 1000, 0) + $time;

				if ($clock >= $from && $clock <= $to) {
					$result[$clock] = $value;
					$count++;
					if ($limit !== null && $count >= $limit) {
						break 2;
					}
				}
			}
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
		if (PHP_INT_SIZE == 4) {
			$neg = $value < 0;

			if ($neg) {
				$value *= -1;
			}

			$hi = (int) ($value / 4294967296);
			$lo = $value - $hi * 4294967296;

			if ($neg) {
				$hi = ~$hi;
				$lo = ~$lo;
				if (($lo & (int) 0xffffffff) == (int) 0xffffffff) {
					$lo = 0;
					$hi++;
				}
				else {
					$lo++;
				}
			}
		}
		else {
			$hi = $value >> 32;
			$lo = $value & 0xFFFFFFFF;
		}

		$data = pack('xC1N2x', 8, $hi, $lo);

		return $data;
	}

}
