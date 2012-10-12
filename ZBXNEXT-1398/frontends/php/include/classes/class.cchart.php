<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CChart extends CGraphDraw {

	public function __construct($type = GRAPH_TYPE_NORMAL) {
		parent::__construct($type);
		$this->yaxismin = null;
		$this->yaxismax = null;
		$this->triggers = array();
		$this->ymin_type = GRAPH_YAXIS_TYPE_CALCULATED;
		$this->ymax_type = GRAPH_YAXIS_TYPE_CALCULATED;
		$this->yaxisright = 0;
		$this->yaxisleft = 0;
		$this->skipLeftScale = 0; // in case if left axis should be drawn but doesn't contain any data
		$this->skipRightScale = 0; // in case if right axis should be drawn but doesn't contain any data
		$this->ymin_itemid = 0;
		$this->ymax_itemid = 0;
		$this->legendOffsetY = 90;
		$this->percentile = array(
			'left' => array(
				'percent' => 0, // draw percentage line
				'value' => 0 // calculated percentage value left y axis
			),
			'right' => array(
				'percent' => 0, // draw percentage line
				'value' => 0 // calculated percentage value right y axis
			)
		);
		$this->m_showWorkPeriod = 1;
		$this->m_showTriggers = 1;
		$this->zero = array();
		$this->graphOrientation = array(
			GRAPH_YAXIS_SIDE_LEFT => '',
			GRAPH_YAXIS_SIDE_RIGHT => ''
		);
		$this->grid = array(); // vertical & horizontal grids params
		$this->gridLinesCount = array(); // How many grids to draw
		$this->gridStep = array(); // grid step
		$this->gridPixels = 25; // optimal grid size
		$this->gridPixelsVert = 40;
	}

	/********************************************************************************************************/
	// PRE CONFIG:	ADD / SET / APPLY
	/********************************************************************************************************/
	public function updateShifts() {
		if ($this->yaxisleft == 1 && $this->yaxisright == 1) {
			$this->shiftXleft = 85;
			$this->shiftXright = 85;
		}
		elseif ($this->yaxisleft == 1) {
			$this->shiftXleft = 85;
			$this->shiftXright = 30;
		}
		elseif ($this->yaxisright == 1) {
			$this->shiftXleft = 30;
			$this->shiftXright = 85;
		}
	}

	public function getShifts() {
		$shifts = array();
		$shifts['shiftXleft'] = $this->shiftXleft;
		$shifts['shiftXright'] = $this->shiftXright;
		$shifts['shiftY'] = $this->shiftY;
		$shifts['height'] = $this->sizeY;
		$shifts['width'] = $this->sizeX;
		return $shifts;
	}

	public function showWorkPeriod($value) {
		$this->m_showWorkPeriod = ($value == 1) ? 1 : 0;
	}

	public function showTriggers($value) {
		$this->m_showTriggers = ($value == 1) ? 1 : 0;
	}

	public function addItem($itemid, $axis = GRAPH_YAXIS_SIDE_DEFAULT, $calc_fnc = CALC_FNC_AVG, $color = null, $drawtype = null, $type = null) {
		if ($this->type == GRAPH_TYPE_STACKED) {
			$drawtype = GRAPH_ITEM_DRAWTYPE_FILLED_REGION;
		}

		$item = get_item_by_itemid($itemid);
		$this->items[$this->num] = $item;
		$this->items[$this->num]['name'] = itemName($item);
		$this->items[$this->num]['delay'] = getItemDelay($item['delay'], $item['delay_flex']);

		if (strpos($item['units'], ',') !== false) {
			list($this->items[$this->num]['units'], $this->items[$this->num]['unitsLong']) = explode(',', $item['units']);
		}
		else {
			$this->items[$this->num]['unitsLong'] = '';
		}

		$host = get_host_by_hostid($item['hostid']);

		$this->items[$this->num]['hostname'] = $host['name'];
		$this->items[$this->num]['color'] = is_null($color) ? 'Dark Green' : $color;
		$this->items[$this->num]['drawtype'] = is_null($drawtype) ? GRAPH_ITEM_DRAWTYPE_LINE : $drawtype;
		$this->items[$this->num]['axisside'] = is_null($axis) ? GRAPH_YAXIS_SIDE_DEFAULT : $axis;
		$this->items[$this->num]['calc_fnc'] = is_null($calc_fnc) ? CALC_FNC_AVG : $calc_fnc;
		$this->items[$this->num]['calc_type'] = is_null($type) ? GRAPH_ITEM_SIMPLE : $type;

		if ($this->items[$this->num]['axisside'] == GRAPH_YAXIS_SIDE_LEFT) {
			$this->yaxisleft = 1;
		}

		if ($this->items[$this->num]['axisside'] == GRAPH_YAXIS_SIDE_RIGHT) {
			$this->yaxisright = 1;
		}
		$this->num++;
	}

	public function setGraphOrientation($value, $axisside) {
		if ($value < 0) {
			$this->graphOrientation[$axisside] = '-';
		}
		elseif (zbx_empty($this->graphOrientation[$axisside]) && $value > 0) {
			$this->graphOrientation[$axisside] = '+';
		}
		return $this->graphOrientation[$axisside];
	}

	public function setYMinAxisType($yaxistype) {
		$this->ymin_type = $yaxistype;
	}

	public function setYMaxAxisType($yaxistype) {
		$this->ymax_type = $yaxistype;
	}

	public function setYAxisMin($yaxismin) {
		$this->yaxismin = $yaxismin;
	}

	public function setYAxisMax($yaxismax) {
		$this->yaxismax = $yaxismax;
	}

	public function setYMinItemId($itemid) {
		$this->ymin_itemid = $itemid;
	}

	public function setYMaxItemId($itemid) {
		$this->ymax_itemid = $itemid;
	}

	public function setLeftPercentage($percentile) {
		$this->percentile['left']['percent'] = $percentile;
	}

	public function setRightPercentage($percentile) {
		$this->percentile['right']['percent'] = $percentile;
	}

	protected function selectData() {
		$this->data = array();
		$now = time(null);

		if (!isset($this->stime)) {
			$this->stime = $now - $this->period;
		}

		$this->diffTZ = (date('Z', $this->stime) - date('Z', $this->stime + $this->period));
		$this->from_time = $this->stime; // + timeZone offset
		$this->to_time = $this->stime + $this->period; // + timeZone offset

		$p = $this->to_time - $this->from_time; // graph size in time
		$z = $p - $this->from_time % $p; // graphsize - mod(from_time,p) for Oracle...
		$x = $this->sizeX; // graph size in px

		$this->itemsHost = null;
		for ($i = 0; $i < $this->num; $i++) {
			$real_item = get_item_by_itemid($this->items[$i]['itemid']);
			if (is_null($this->itemsHost)) {
				$this->itemsHost = $real_item['hostid'];
			}
			elseif ($this->itemsHost != $real_item['hostid']) {
				$this->itemsHost = false;
			}

			if (!isset($this->axis_valuetype[$this->items[$i]['axisside']])) {
				$this->axis_valuetype[$this->items[$i]['axisside']] = $real_item['value_type'];
			}
			elseif ($this->axis_valuetype[$this->items[$i]['axisside']] != $real_item['value_type']) {
				$this->axis_valuetype[$this->items[$i]['axisside']] = ITEM_VALUE_TYPE_FLOAT;
			}

			$type = $this->items[$i]['calc_type'];
			$from_time = $this->from_time;
			$to_time = $this->to_time;
			$calc_field = 'round('.$x.'*'.zbx_sql_mod(zbx_dbcast_2bigint('clock').'+'.$z, $p).'/('.$p.'),0)'; // required for 'group by' support of Oracle

			$sql_arr = array();

			if (ZBX_HISTORY_DATA_UPKEEP > -1) {
				$real_item['history'] = ZBX_HISTORY_DATA_UPKEEP;
			}

			if (($real_item['history'] * SEC_PER_DAY) > (time() - ($this->from_time + $this->period / 2)) // should pick data from history or trends
					&& ($this->period / $this->sizeX) <= (ZBX_MAX_TREND_DIFF / ZBX_GRAPH_MAX_SKIP_CELL)) { // is reasonable to take data from history?
				$this->dataFrom = 'history';
				array_push($sql_arr,
					'SELECT itemid,'.$calc_field.' AS i,'.
						'COUNT(*) AS count,AVG(value) AS avg,MIN(value) as min,'.
						'MAX(value) AS max,MAX(clock) AS clock'.
					' FROM history '.
					' WHERE itemid='.$this->items[$i]['itemid'].
						' AND clock>='.$from_time.
						' AND clock<='.$to_time.
					' GROUP BY itemid,'.$calc_field
					,
					'SELECT itemid,'.$calc_field.' AS i,'.
						'COUNT(*) AS count,AVG(value) AS avg,MIN(value) AS min,'.
						'MAX(value) AS max,MAX(clock) AS clock'.
					' FROM history_uint '.
					' WHERE itemid='.$this->items[$i]['itemid'].
						' AND clock>='.$from_time.
						' AND clock<='.$to_time.
					' GROUP BY itemid,'.$calc_field
				);
			}
			else {
				$this->dataFrom = 'trends';
				array_push($sql_arr,
					'SELECT itemid,'.$calc_field.' AS i,'.
						'SUM(num) AS count,AVG(value_avg) AS avg,MIN(value_min) AS min,'.
						'MAX(value_max) AS max,MAX(clock) AS clock'.
					' FROM trends'.
					' WHERE itemid='.$this->items[$i]['itemid'].
						' AND clock>='.$from_time.
						' AND clock<='.$to_time.
					' GROUP BY itemid,'.$calc_field
					,
					'SELECT itemid,'.$calc_field.' AS i,'.
						'SUM(num) AS count,AVG(value_avg) AS avg,MIN(value_min) AS min,'.
						'MAX(value_max) AS max,MAX(clock) AS clock'.
					' FROM trends_uint '.
					' WHERE itemid='.$this->items[$i]['itemid'].
						' AND clock>='.$from_time.
						' AND clock<='.$to_time.
					' GROUP BY itemid,'.$calc_field
				);

				$this->items[$i]['delay'] = max($this->items[$i]['delay'], SEC_PER_HOUR);
			}

			if (!isset($this->data[$this->items[$i]['itemid']])) {
				$this->data[$this->items[$i]['itemid']] = array();
			}

			if (!isset($this->data[$this->items[$i]['itemid']][$type])) {
				$this->data[$this->items[$i]['itemid']][$type] = array();
			}

			$curr_data = &$this->data[$this->items[$i]['itemid']][$type];

			$curr_data['count'] = null;
			$curr_data['min'] = null;
			$curr_data['max'] = null;
			$curr_data['avg'] = null;
			$curr_data['clock'] = null;

			foreach ($sql_arr as $sql) {
				$result = DBselect($sql);
				while ($row = DBfetch($result)) {
					$idx = $row['i'] - 1;
					if ($idx < 0) {
						continue;
					}

					/* --------------------------------------------------
						We are taking graph on 1px more than we need,
						and here we are skiping first px, because of MOD (in SELECT),
						it combines prelast point (it would be last point if not that 1px in begining)
						and first point, but we still losing prelast point :(
						but now we've got the first point.
					--------------------------------------------------*/
					$curr_data['count'][$idx] = $row['count'];
					$curr_data['min'][$idx] = $row['min'];
					$curr_data['max'][$idx] = $row['max'];
					$curr_data['avg'][$idx] = $row['avg'];
					$curr_data['clock'][$idx] = $row['clock'];
					$curr_data['shift_min'][$idx] = 0;
					$curr_data['shift_max'][$idx] = 0;
					$curr_data['shift_avg'][$idx] = 0;
				}

				$loc_min = is_array($curr_data['min']) ? min($curr_data['min']) : null;
				$this->setGraphOrientation($loc_min, $this->items[$i]['axisside']);
				unset($row);
			}
			$curr_data['avg_orig'] = zbx_avg($curr_data['avg']);

			// calculate missed points
			$first_idx = 0;

			/*
				first_idx - last existing point
				ci - current index
				cj - count of missed in one go
				dx - offset to first value (count to last existing point)
			*/
			for ($ci = 0, $cj = 0; $ci < $this->sizeX; $ci++) {
				if (!isset($curr_data['count'][$ci]) || ($curr_data['count'][$ci] == 0)) {
					$curr_data['count'][$ci] = 0;
					$curr_data['shift_min'][$ci] = 0;
					$curr_data['shift_max'][$ci] = 0;
					$curr_data['shift_avg'][$ci] = 0;
					$cj++;
					continue;
				}

				if ($cj == 0) {
					continue;
				}

				$dx = $cj + 1;
				$first_idx = $ci - $dx;

				if ($first_idx < 0) {
					$first_idx = $ci; // if no data from start of graph get current data as first data
				}

				for(; $cj > 0; $cj--) {
					if ($dx < ($this->sizeX / 20) && $this->type == GRAPH_TYPE_STACKED) {
						$curr_data['count'][$ci - ($dx - $cj)] = 1;
					}

					foreach (array('clock', 'min', 'max', 'avg') as $var_name) {
						$var = &$curr_data[$var_name];

						if ($first_idx == $ci && $var_name == 'clock') {
							$var[$ci - ($dx - $cj)] = $var[$first_idx] - (($p / $this->sizeX) * ($dx - $cj));
							continue;
						}

						$dy = $var[$ci] - $var[$first_idx];
						$var[$ci - ($dx - $cj)] = bcadd($var[$first_idx] , bcdiv(($cj * $dy) , $dx));
					}
				}
			}

			if ($cj > 0 && $ci > $cj) {
				$dx = $cj + 1;
				$first_idx = $ci - $dx;

				for(;$cj > 0; $cj--) {
					foreach (array('clock', 'min', 'max', 'avg') as $var_name) {
						$var = &$curr_data[$var_name];

						if ($var_name == 'clock') {
							$var[$first_idx + ($dx - $cj)] = $var[$first_idx] + (($p / $this->sizeX) * ($dx - $cj));
							continue;
						}
						$var[$first_idx + ($dx - $cj)] = $var[$first_idx];
					}
				}
			}
			// end of missed points calculation
		}

		// calculte shift for stacked graphs
		if ($this->type == GRAPH_TYPE_STACKED) {
			for ($i = 1; $i < $this->num; $i++) {
				$curr_data = &$this->data[$this->items[$i]['itemid']][$this->items[$i]['calc_type']];

				if (!isset($curr_data)) {
					continue;
				}

				for ($j = $i - 1; $j >= 0; $j--) {
					if ($this->items[$j]['axisside'] != $this->items[$i]['axisside']) {
						continue;
					}

					$prev_data = &$this->data[$this->items[$j]['itemid']][$this->items[$j]['calc_type']];

					if (!isset($prev_data)) {
						continue;
					}

					for ($ci = 0; $ci < $this->sizeX; $ci++) {
						foreach (array('min', 'max', 'avg') as $var_name) {
							$shift_var_name = 'shift_'.$var_name;
							$curr_shift = &$curr_data[$shift_var_name];
							$curr_var = &$curr_data[$var_name];
							$prev_shift = &$prev_data[$shift_var_name];
							$prev_var = &$prev_data[$var_name];
							$curr_shift[$ci] = $prev_var[$ci] + $prev_shift[$ci];
						}
					}
					break;
				}
			}
		}
		// end calculation of stacked graphs
	}

	/********************************************************************************************************/
	// CALCULATIONS
	/********************************************************************************************************/
	protected function calcTriggers() {
		$this->triggers = array();
		if ($this->m_showTriggers != 1) {
			return;
		}

		$max = 3;
		$cnt = 0;

		foreach ($this->items as $inum => $item) {
			$db_triggers = DBselect(
				'SELECT DISTINCT h.host,tr.description,tr.triggerid,tr.expression,tr.priority,tr.value'.
				' FROM triggers tr,functions f,items i,hosts h'.
				' WHERE tr.triggerid=f.triggerid'.
					" AND f.function IN ('last','min','avg','max')".
					' AND tr.status='.TRIGGER_STATUS_ENABLED.
					' AND i.itemid=f.itemid'.
					' AND h.hostid=i.hostid'.
					' AND f.itemid='.$item['itemid'].
				' ORDER BY tr.priority'
			);
			while (($trigger = DBfetch($db_triggers)) && $cnt < $max) {
				$db_fnc_cnt = DBselect('SELECT COUNT(*) AS cnt FROM functions f WHERE f.triggerid='.$trigger['triggerid']);
				$fnc_cnt = DBfetch($db_fnc_cnt);

				if ($fnc_cnt['cnt'] != 1) {
					continue;
				}

				$trigger = API::UserMacro()->resolveTrigger($trigger);
				if (!preg_match('/\{([0-9]{1,})\}\s*?([\<\>\=]{1})\s*?([0-9\.]{1,})([K|M|G]{0,1})/i', $trigger['expression'], $arr)) {
					continue;
				}

				$val = $arr[3];
				if (strcasecmp($arr[4],'K') == 0) {
					$val *= 1024;
				}
				elseif (strcasecmp($arr[4], 'M') == 0) {
					$val *= 1048576; //1024*1024;
				}
				elseif (strcasecmp($arr[4], 'G') == 0) {
					$val *= 1073741824; //1024*1024*1024;
				}

				$minY = $this->m_minY[$this->items[$inum]['axisside']];
				$maxY = $this->m_maxY[$this->items[$inum]['axisside']];

				array_push($this->triggers, array(
					'skipdraw' => ($val <= $minY || $val >= $maxY),
					'y' => $this->sizeY - (($val - $minY) / ($maxY - $minY)) * $this->sizeY + $this->shiftY,
					'color' => getSeverityColor($trigger['priority']),
					'description' => _('Trigger').': '.CTriggerHelper::expandDescription($trigger),
					'constant' => '['.$arr[2].' '.$arr[3].$arr[4].']'
				));
				++$cnt;
			}
		}
	}

	// calculates percentages for left & right Y axis
	protected function calcPercentile() {
		if ($this->type != GRAPH_TYPE_NORMAL) {
			return ;
		}

		$values = array(
			'left' => array(),
			'right'=> array()
		);

		$maxX = $this->sizeX;

		// for each metric
		for ($item = 0; $item < $this->num; $item++) {
			$data = &$this->data[$this->items[$item]['itemid']][$this->items[$item]['calc_type']];

			if (!isset($data)) {
				continue;
			}

			// for each X
			for ($i = 0; $i < $maxX; $i++) { // new point
				if ($data['count'][$i] == 0 && $i != ($maxX - 1)) {
					continue;
				}

				$min = $data['min'][$i];
				$max = $data['max'][$i];
				$avg = $data['avg'][$i];

				switch ($this->items[$item]['calc_fnc']) {
					case CALC_FNC_MAX:
						$value = $max;
						break;
					case CALC_FNC_MIN:
						$value = $min;
						break;
					case CALC_FNC_ALL:
					case CALC_FNC_AVG:
					default:
						$value = $avg;
				}

				if ($this->items[$item]['axisside'] == GRAPH_YAXIS_SIDE_LEFT) {
					$values['left'][] = $value;
				}
				else {
					$values['right'][] = $value;
				}
			}
		}

		foreach ($this->percentile as $side => $percentile) {
			if ($percentile['percent'] > 0 && !empty($values[$side])) {
				sort($values[$side]);
				$percent = (int) ((count($values[$side]) * $percentile['percent'] / 100) + 0.5);
				$this->percentile[$side]['value'] = $values[$side][$percent];
				unset($values[$side]);
			}
		}
	}

	// calculation of minimum Y axis
	protected function calculateMinY($side) {
		if ($this->ymin_type == GRAPH_YAXIS_TYPE_FIXED) {
			return $this->yaxismin;
		}
		if ($this->ymin_type == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
			$item = get_item_by_itemid($this->ymin_itemid);
			if ($item && isset($item['lastvalue']) && !is_null($item['lastvalue'])) {
				return $item['lastvalue'];
			}
		}

		$minY = null;
		for ($i = 0; $i < $this->num; $i++) {
			if ($this->items[$i]['axisside'] != $side) {
				continue;
			}

			if (!isset($this->data[$this->items[$i]['itemid']][GRAPH_ITEM_SIMPLE])) {
				continue;
			}

			$data = &$this->data[$this->items[$i]['itemid']][GRAPH_ITEM_SIMPLE];

			if (!isset($data)) {
				continue;
			}

			$calc_fnc = $this->items[$i]['calc_fnc'];

			switch ($calc_fnc) {
				case CALC_FNC_ALL:
				case CALC_FNC_MIN:
					$val = $data['min'];
					$shift_val = $data['shift_min'];
					break;
				case CALC_FNC_MAX:
					$val = $data['max'];
					$shift_val = $data['shift_max'];
					break;
				case CALC_FNC_AVG:
				default:
					$val = $data['avg'];
					$shift_val = $data['shift_avg'];
			}

			if (!isset($val)) {
				continue;
			}

			if ($this->type == GRAPH_TYPE_STACKED) {
				$min_val_shift = min(count($val), count($shift_val));
				for ($ci = 0; $ci < $min_val_shift; $ci++) {
					if ($shift_val[$ci] < 0) {
						$val[$ci] += bcadd($shift_val[$ci], $val[$ci]);
					}
				}
			}

			if (!isset($minY)) {
				if (isset($val) && count($val) > 0) {
					$minY = min($val);
				}
			}
			else {
				$minY = min($minY, min($val));
			}
		}
		return $minY;
	}

	// calculation of maximum Y of a side (left/right)
	protected function calculateMaxY($side) {
		if ($this->ymax_type == GRAPH_YAXIS_TYPE_FIXED) {
			return $this->yaxismax;
		}
		if ($this->ymax_type == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
			$item = get_item_by_itemid($this->ymax_itemid);
			if ($item && isset($item['lastvalue']) && !is_null($item['lastvalue'])) {
				 return $item['lastvalue'];
			}
		}

		$maxY = null;
		for ($i = 0; $i < $this->num; $i++) {
			if ($this->items[$i]['axisside'] != $side) {
				continue;
			}

			if (!isset($this->data[$this->items[$i]['itemid']][GRAPH_ITEM_SIMPLE])) {
				continue;
			}

			$data = &$this->data[$this->items[$i]['itemid']][GRAPH_ITEM_SIMPLE];

			if (!isset($data)) {
				continue;
			}

			$calc_fnc = $this->items[$i]['calc_fnc'];

			switch ($calc_fnc) {
				case CALC_FNC_ALL:
				case CALC_FNC_MAX:
					$val = $data['max'];
					$shift_val = $data['shift_max'];
					break;
				case CALC_FNC_MIN:
					$val = $data['min'];
					$shift_val = $data['shift_min'];
					break;
				case CALC_FNC_AVG:
				default:
					$val = $data['avg'];
					$shift_val = $data['shift_avg'];
			}

			if (!isset($val)) {
				continue;
			}

			for ($ci = 0; $ci < min(count($val), count($shift_val)); $ci++) {
				if ($data['count'][$ci] == 0) {
					continue;
				}

				$val[$ci] = bcadd($shift_val[$ci], $val[$ci]);
			}

			if (!isset($maxY)) {
				if (isset($val) && count($val) > 0) {
					$maxY = max($val);
				}
			}
			else {
				$maxY = max($maxY, max($val));
			}
		}
		return $maxY;
	}

	protected function calcZero() {
		$sides = array(GRAPH_YAXIS_SIDE_LEFT, GRAPH_YAXIS_SIDE_RIGHT);

		foreach ($sides as $num => $side) {
			$this->unit2px[$side] = ($this->m_maxY[$side] - $this->m_minY[$side]) / $this->sizeY;
			if ($this->unit2px[$side] == 0) {
				$this->unit2px[$side] = 1;
			}

			if ($this->m_minY[$side] > 0) {
				$this->zero[$side] = $this->sizeY + $this->shiftY;
				$this->oxy[$side] = min($this->m_minY[$side], $this->m_maxY[$side]);
			}
			elseif ($this->m_maxY[$side] < 0) {
				$this->zero[$side] = $this->shiftY;
				$this->oxy[$side] = max($this->m_minY[$side], $this->m_maxY[$side]);
			}
			else {
				$this->zero[$side] = $this->sizeY + $this->shiftY - (int) abs($this->m_minY[$side] / $this->unit2px[$side]);
				$this->oxy[$side] = 0;
			}
		}
	}

	protected function calcMinMaxInterval() {
		// init intervals
		$intervals = array();
		foreach (array(1, 2, 3, 4) as $num) {
			$dec = pow(0.1, $num);
			foreach (array(1, 2, 5) as $int) {
				$intervals[] = bcmul($int, $dec);
			}
		}

		foreach (array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18) as $num) {
			$dec = bcpow(10, $num);
			foreach (array(1, 2, 5) as $int) {
				$intervals[] = bcmul($int, $dec);
			}
		}

		$sides = array(GRAPH_YAXIS_SIDE_LEFT, GRAPH_YAXIS_SIDE_RIGHT);
		foreach ($sides as $snum => $side) {
			if (!isset($this->axis_valuetype[$side])) {
				continue;
			}

			if ($this->type == GRAPH_TYPE_STACKED) {
				$this->m_minY[$side] = min($this->m_minY[$side], 0);
				continue;
			}

			if ($this->ymax_type == GRAPH_YAXIS_TYPE_FIXED) {
				$this->m_maxY[$side] = $this->yaxismax;
				$this->m_minY[$side] = 0;
			}

			if ($this->ymin_type == GRAPH_YAXIS_TYPE_FIXED) {
				$this->m_minY[$side] = $this->yaxismin;

				if (($this->ymax_type == GRAPH_YAXIS_TYPE_CALCULATED) && bccomp($this->m_maxY[$side], $this->m_minY[$side]) == -1) {
					$this->m_maxY[$side] = bcmul($this->m_minY[$side], 1.2);
				}
			}
		}

		// sides
		$side = GRAPH_YAXIS_SIDE_LEFT;
		$other_side = GRAPH_YAXIS_SIDE_RIGHT;
		if (!isset($this->axis_valuetype[GRAPH_YAXIS_SIDE_LEFT])) {
			$side = GRAPH_YAXIS_SIDE_RIGHT;
			$other_side = GRAPH_YAXIS_SIDE_LEFT;
		}

		$tmp_minY = array();
		$tmp_maxY = array();
		$tmp_minY[GRAPH_YAXIS_SIDE_LEFT] = $this->m_minY[GRAPH_YAXIS_SIDE_LEFT];
		$tmp_minY[GRAPH_YAXIS_SIDE_RIGHT] = $this->m_minY[GRAPH_YAXIS_SIDE_RIGHT];
		$tmp_maxY[GRAPH_YAXIS_SIDE_LEFT] = $this->m_maxY[GRAPH_YAXIS_SIDE_LEFT];
		$tmp_maxY[GRAPH_YAXIS_SIDE_RIGHT] = $this->m_maxY[GRAPH_YAXIS_SIDE_RIGHT];

		// calc interval
		$columnInterval = bcdiv(bcmul($this->gridPixelsVert, (bcsub($this->m_maxY[$side], $this->m_minY[$side]))), $this->sizeY);

		$dist = bcmul(5, bcpow(10, 18));

		$interval = 0;
		foreach ($intervals as $int) {
			// we must get a positive number
			if (bccomp($int, $columnInterval) == -1) {
				$t = bcsub($columnInterval, $int);
			}
			else {
				$t = bcsub($int, $columnInterval);
			}

			if (bccomp($t, $dist) == -1) {
				$dist = $t;
				$interval = $int;
			}
		}

		$columnInterval = bcdiv(bcmul($this->gridPixelsVert, bcsub($this->m_maxY[$other_side], $this->m_minY[$other_side])), $this->sizeY);

		$dist = bcmul(5, bcpow(10, 18));

		$interval_other_side = 0;
		foreach ($intervals as $int) {
			// we must get a positive number
			if (bccomp($int, $columnInterval) == -1) {
				$t = bcsub($columnInterval, $int);
			}
			else {
				$t = bcsub($int, $columnInterval);
			}

			if (bccomp($t,$dist) == -1) {
				$dist = $t;
				$interval_other_side = $int;
			}
		}

		// correcting MIN & MAX
		$this->m_minY[$side] = bcmul(bcfloor(bcdiv($this->m_minY[$side], $interval)), $interval);
		$this->m_maxY[$side] = bcmul(bcceil(bcdiv($this->m_maxY[$side], $interval)), $interval);
		$this->m_minY[$other_side] = bcmul(bcfloor(bcdiv($this->m_minY[$other_side], $interval_other_side)), $interval_other_side);
		$this->m_maxY[$other_side] = bcmul(bcceil(bcdiv($this->m_maxY[$other_side], $interval_other_side)), $interval_other_side);

		$this->gridLinesCount[$side] = bcceil(bcdiv(bcsub($this->m_maxY[$side], $this->m_minY[$side]), $interval));

		// we add 1 interval so max Y wouldn't be at the top
		if (bccomp($this->m_maxY[$side], $tmp_maxY[$side], 2) == 0) {
			$this->gridLinesCount[$side]++;
		}

		$this->m_maxY[$side] = bcadd($this->m_minY[$side], bcmul($interval, $this->gridLinesCount[$side]));
		$this->gridStep[$side] = $interval;

		if (isset($this->axis_valuetype[$other_side])) {
			$dist = bcsub($this->m_maxY[$other_side], $this->m_minY[$other_side]);
			$interval = 1;

			foreach ($intervals as $int) {
				if (bccomp($dist, bcmul($this->gridLinesCount[$side], $int)) == -1) {
					$interval = $int;
					break;
				}
			}

			// correcting MIN & MAX
			$this->m_minY[$other_side] = bcmul(bcfloor(bcdiv($this->m_minY[$other_side], $interval)), $interval);
			$this->m_maxY[$other_side] = bcmul(bcceil(bcdiv($this->m_maxY[$other_side], $interval)), $interval);

			// if we lowered min more than highed max - need additional recalculating
			if (bccomp($tmp_maxY[$other_side], $this->m_maxY[$other_side]) == 1 || bccomp($tmp_minY[$other_side], $this->m_minY[$other_side]) == -1) {
				$dist = bcsub($this->m_maxY[$other_side], $this->m_minY[$other_side]);
				$interval = 0;
				foreach ($intervals as $int) {
					if (bccomp($dist, bcmul($this->gridLinesCount[$side], $int)) == -1) {
						$interval = $int;
						break;
					}
				}

				// recorrecting MIN & MAX
				$this->m_minY[$other_side] = bcmul(bcfloor(bcdiv($this->m_minY[$other_side], $interval)), $interval);
				$this->m_maxY[$other_side] = bcmul(bcceil(bcdiv($this->m_maxY[$other_side], $interval)), $interval);
			}

			$this->gridLinesCount[$other_side] = $this->gridLinesCount[$side];
			$this->m_maxY[$other_side] = bcadd($this->m_minY[$other_side], bcmul($interval, $this->gridLinesCount[$other_side]));
			$this->gridStep[$other_side] = $interval;
		}

		$sides = array(GRAPH_YAXIS_SIDE_LEFT, GRAPH_YAXIS_SIDE_RIGHT);
		foreach ($sides as $graphSide) {
			if (!isset($this->axis_valuetype[$graphSide])) {
				continue;
			}

			if ($this->type == GRAPH_TYPE_STACKED) {
				$this->m_minY[$graphSide] = bccomp($tmp_minY[GRAPH_YAXIS_SIDE_LEFT], 0) == -1 ? $tmp_minY[GRAPH_YAXIS_SIDE_LEFT] : 0;
			}

			if ($this->ymax_type == GRAPH_YAXIS_TYPE_FIXED) {
				$this->m_maxY[$graphSide] = $this->yaxismax;
				$this->m_minY[$graphSide] = 0;
			}
			elseif ($this->ymax_type == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
				$this->m_maxY[$graphSide] = $tmp_maxY[$graphSide];
			}

			if ($this->ymin_type == GRAPH_YAXIS_TYPE_FIXED) {
				$this->m_minY[$graphSide] = $this->yaxismin;
			}
			elseif ($this->ymin_type == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
				$this->m_minY[$graphSide] = $tmp_minY[$graphSide];
			}
		}

		// division by zero
		$diff_val = bcsub($this->m_maxY[$side], $this->m_minY[$side]);
		if (bccomp($diff_val, 0) == 0) {
			$diff_val = 1;
		}
		$this->gridStepX[$side] = bcdiv(bcmul($this->gridStep[$side], $this->sizeY), $diff_val);

		if (isset($this->axis_valuetype[$other_side])) {
			$diff_val = bcsub($this->m_maxY[$other_side], $this->m_minY[$other_side]);
			if (bccomp($diff_val, 0) == 0) {
				$diff_val = 1;
			}
			$this->gridStepX[$other_side] = bcdiv(bcmul($this->gridStep[$other_side], $this->sizeY), $diff_val);
		}
	}

	private function calcTimeInterval() {
		$this->grid['horizontal'] = array('sub' => array(), 'main' => array());

		// align to the closest human time interval
		$raw_time_interval = ($this->gridPixels*$this->period)/$this->sizeX;
		$intervals = array(
			array('main' => 3600, 'sub' => 60),			// 1 minute
			array('main' => 3600, 'sub' => 120),		// 5 minutes
			array('main' => 3600, 'sub' => 300),		// 5 minutes
			array('main' => 3600, 'sub' => 900),		// 15 minutes
			array('main' => 3600, 'sub' => 1800),		// 30 minutes
			array('main' => 86400, 'sub' => 3600),		// 1 hour
			array('main' => 86400, 'sub' => 10800),		// 3 hours
			array('main' => 86400, 'sub' => 21600),		// 6 hours
			array('main' => 86400, 'sub' => 43200),		// 12 hours
			array('main' => 604800, 'sub' => 86400),	// 1 day
			array('main' => 1209600, 'sub' => 604800),	// 1 week
			array('main' => 2419200, 'sub' => 1209600),	// 2 weeks
			array('main' => 4838400, 'sub' => 2419200),	// 4 weeks
			array('main' => 9676800, 'sub' => 4838400),	// 8 weeks
			array('main' => 19353600, 'sub' => 9676800)	// 16 weeks
		);

		$dist = 19353600; //def week;
		$sub_interval = 0;
		$main_interval = 0;

		foreach ($intervals as $int) {
			$t = abs($int['sub'] - $raw_time_interval);

			if ($t < $dist) {
				$dist = $t;
				$sub_interval = $int['sub'];
				$main_interval = $int['main'];
			}
		}

		// sub
		$intervalX = ($sub_interval * $this->sizeX) / $this->period;

		if ($sub_interval > SEC_PER_DAY) {
			$offset = (7 - date('w', $this->from_time)) * SEC_PER_DAY;
			$offset += $this->diffTZ;

			$next = $this->from_time + $offset;

			$offset = mktime(0, 0, 0, date('m', $next), date('d', $next), date('Y', $next)) - $this->from_time;
			$offsetX = $offset * ($this->sizeX / $this->period);
		}
		else {
			$offset = $sub_interval - (($this->from_time + date('Z', $this->from_time)) % $sub_interval);
			$offsetX = ($offset * $this->sizeX) / $this->period;
		}

		$vline_count = floor(($this->period-$offset) / $sub_interval);

		$start_i = 0;
		if ($offsetX < 12) {
			$start_i++;
		}

		while (($this->sizeX - ($offsetX + ($vline_count*$intervalX))) < 12) {
			$vline_count--;
		}

		$sub = &$this->grid['horizontal']['sub'];
		$sub['interval'] = $sub_interval;
		$sub['linecount'] = $vline_count;
		$sub['intervalx'] = $intervalX;
		$sub['offset'] = $offset;
		$sub['offsetx'] = $offsetX;
		$sub['start'] = $start_i;

		// main
		$intervalX = ($main_interval * $this->sizeX) / $this->period;

		if ($main_interval > SEC_PER_DAY) {
			$offset = (7 - date('w', $this->from_time)) * SEC_PER_DAY;
			$offset += $this->diffTZ;
			$next = $this->from_time + $offset;

			$offset = mktime(0, 0, 0, date('m', $next), date('d', $next), date('Y', $next)) - $this->from_time;
			$offsetX = $offset * ($this->sizeX / $this->period);
		}
		else {
			$offset = $main_interval - (($this->from_time + (date('Z', $this->from_time))) % $main_interval);
			$offset += $this->diffTZ;
			$offsetX = $offset * ($this->sizeX / $this->period);
		}

		$vline_count = floor(($this->period-$offset) / $main_interval);

		$start_i = 0;
		if ($offsetX < 12) {
			$start_i++;
		}

		while (($this->sizeX - ($offsetX + ($vline_count*$intervalX))) < 12) {
			$vline_count--;
		}

		$main = &$this->grid['horizontal']['main'];
		$main['interval'] = $main_interval;
		$main['linecount'] = $vline_count;
		$main['intervalx'] = $intervalX;
		$main['offset'] = $offset;
		$main['offsetx'] = $offsetX;
		$main['start'] = $start_i;
	}

	/********************************************************************************************************/
	// DRAW ELEMENTS
	/********************************************************************************************************/
	public function drawXYAxisScale() {
		dashedRectangle(
			$this->im,
			$this->shiftXleft + $this->shiftXCaption - 1,
			$this->shiftY - 1,
			$this->sizeX + $this->shiftXleft + $this->shiftXCaption,
			$this->sizeY + $this->shiftY + 1,
			$this->getColor($this->graphtheme['gridcolor'], 0)
		);

		if ($this->yaxisleft) {
			imageline(
				$this->im,
				$this->shiftXleft + $this->shiftXCaption - 1,
				$this->shiftY - 5,
				$this->shiftXleft + $this->shiftXCaption - 1,
				$this->sizeY + $this->shiftY + 4,
				$this->getColor($this->graphtheme['gridbordercolor'], 0)
			);

			imagefilledpolygon(
				$this->im,
				array(
					$this->shiftXleft + $this->shiftXCaption - 4, $this->shiftY - 5,
					$this->shiftXleft + $this->shiftXCaption + 2, $this->shiftY - 5,
					$this->shiftXleft + $this->shiftXCaption - 1, $this->shiftY - 10,
				),
				3,
				$this->getColor('White')
			);

			imagepolygon(
				$this->im,
				array(
					$this->shiftXleft + $this->shiftXCaption - 4, $this->shiftY - 5,
					$this->shiftXleft + $this->shiftXCaption + 2, $this->shiftY - 5,
					$this->shiftXleft + $this->shiftXCaption - 1, $this->shiftY - 10,
				),
				3,
				$this->getColor($this->graphtheme['gridbordercolor'], 0)
			);
		}

		if ($this->yaxisright) {
			$color = $this->getColor($this->graphtheme['gridbordercolor'], 0);

			imageline(
				$this->im,
				$this->sizeX + $this->shiftXleft + $this->shiftXCaption,
				$this->shiftY - 5,
				$this->sizeX + $this->shiftXleft + $this->shiftXCaption,
				$this->sizeY + $this->shiftY + 4,
				$color
			);

			imagefilledpolygon(
				$this->im,
				array(
					$this->sizeX + $this->shiftXleft + $this->shiftXCaption - 3, $this->shiftY - 5,
					$this->sizeX + $this->shiftXleft + $this->shiftXCaption + 3, $this->shiftY - 5,
					$this->sizeX + $this->shiftXleft + $this->shiftXCaption, $this->shiftY - 10,
				),
				3,
				$this->getColor('White')
			);

			imagepolygon(
				$this->im,
				array(
					$this->sizeX + $this->shiftXleft + $this->shiftXCaption - 3, $this->shiftY - 5,
					$this->sizeX + $this->shiftXleft + $this->shiftXCaption + 3, $this->shiftY - 5,
					$this->sizeX + $this->shiftXleft + $this->shiftXCaption, $this->shiftY - 10,
				),
				3,
				$color
			);
		}

		imageline(
			$this->im,
			$this->shiftXleft + $this->shiftXCaption - 4,
			$this->sizeY + $this->shiftY + 1,
			$this->sizeX + $this->shiftXleft + $this->shiftXCaption + 5,
			$this->sizeY + $this->shiftY + 1,
			$this->getColor($this->graphtheme['gridbordercolor'], 0)
		);

		imagefilledpolygon(
			$this->im,
			array(
				$this->sizeX + $this->shiftXleft + $this->shiftXCaption + 5, $this->sizeY + $this->shiftY - 2,
				$this->sizeX + $this->shiftXleft + $this->shiftXCaption + 5, $this->sizeY + $this->shiftY + 4,
				$this->sizeX + $this->shiftXleft + $this->shiftXCaption + 10, $this->sizeY + $this->shiftY + 1
			),
			3,
			$this->getColor('White')
		);

		imagepolygon(
			$this->im,
			array(
				$this->sizeX + $this->shiftXleft + $this->shiftXCaption + 5, $this->sizeY + $this->shiftY - 2,
				$this->sizeX + $this->shiftXleft + $this->shiftXCaption + 5, $this->sizeY + $this->shiftY + 4,
				$this->sizeX + $this->shiftXleft + $this->shiftXCaption + 10, $this->sizeY + $this->shiftY + 1
			),
			3,
			$this->getColor($this->graphtheme['gridbordercolor'], 0)
		);
	}

	/**
	 * Draws Y scale grid.
	 */
	private function drawVerticalGrid() {
		$hline_count = round($this->sizeY / $this->gridPixels);

		$yAxis = ($this->yaxisleft) ? GRAPH_YAXIS_SIDE_LEFT : GRAPH_YAXIS_SIDE_RIGHT;

		$tmp_hlines = $this->gridLinesCount[$yAxis];
		$stepX = $this->gridStepX[$yAxis];

		if ($tmp_hlines < $hline_count) {
			$hline_count = $tmp_hlines * 2;
			$stepX = $stepX / 2;
		}
		else {
			$hline_count = $tmp_hlines;
		}

		for ($i = 1; $i < $hline_count; $i++) {
			$yOffset = $stepX * $i - $this->getYStepMarkerPosOffset($yAxis, $i);
			if ($yOffset >= $this->sizeY || $yOffset <= 0) {
				continue;
			}

			$y = $this->sizeY - $yOffset + $this->shiftY;

			dashedLine(
				$this->im,
				$this->shiftXleft,
				$y,
				$this->sizeX + $this->shiftXleft,
				$y,
				$this->getColor($this->graphtheme['maingridcolor'], 0)
			);
		}
	}

	private function drawTimeGrid() {
		$this->calcTimeInterval();
		$this->drawSubTimeGrid();
	}

	private function drawSubTimeGrid() {
		$main_interval = $this->grid['horizontal']['main']['interval'];
		$main_intervalX = $this->grid['horizontal']['main']['intervalx'];
		$main_offset = $this->grid['horizontal']['main']['offset'];

		$sub = &$this->grid['horizontal']['sub'];
		$interval = $sub['interval'];
		$vline_count = $sub['linecount'];
		$intervalX = $sub['intervalx'];

		$offset = $sub['offset'];
		$offsetX = $sub['offsetx'];
		$start_i = $sub['start'];

		if ($interval == $main_interval) {
			return;
		}

		$test_dims = imageTextSize(7, 90, 'WWW');
		for ($i = $start_i; $i <= $vline_count; $i++) {
			$new_time = $this->from_time + $i * $interval + $offset;
			$new_pos = $i * $intervalX + $offsetX;

			// dayLightSave
			if ($interval > SEC_PER_HOUR) {
				$tz = date('Z', $this->from_time) - date('Z', $new_time);
				$new_time += $tz;
			}

			// main interval checks
			if ($interval < SEC_PER_HOUR && date('i', $new_time) == 0) {
				$this->drawMainPeriod($new_time, $new_pos);
				continue;
			}

			if ($interval >= SEC_PER_HOUR && $interval < SEC_PER_DAY && date('H', $new_time) == '00') {
				$this->drawMainPeriod($new_time, $new_pos);
				continue;
			}

			if ($interval == SEC_PER_DAY && date('N', $new_time) == 7) {
				$this->drawMainPeriod($new_time, $new_pos);
				continue;
			}

			if ($interval > SEC_PER_DAY && ($i * $interval % $main_interval + $offset) == $main_offset) {
				$this->drawMainPeriod($new_time, $new_pos);
				continue;
			}

			dashedLine(
				$this->im,
				$this->shiftXleft + $new_pos,
				$this->shiftY,
				$this->shiftXleft + $new_pos,
				$this->sizeY + $this->shiftY,
				$this->getColor($this->graphtheme['gridcolor'], 0)
			);

			if ($main_intervalX < floor(($main_interval / $interval) * $intervalX)) {
				continue;
			}
			elseif ($main_intervalX < (ceil($main_interval / $interval + 1) * $test_dims['width'])) {
				continue;
			}

			if ($interval == SEC_PER_DAY) {
				$date_format = _('D');
			}
			elseif ($interval > SEC_PER_DAY) {
				$date_format = _('d.m');
			}
			elseif ($interval < SEC_PER_DAY) {
				$date_format = _('H:i');
			}

			$str = zbx_date2str($date_format, $new_time);
			$dims = imageTextSize(7, 90, $str);

			imageText(
				$this->im,
				7,
				90,
				$this->shiftXleft + $new_pos+round($dims['width'] / 2),
				$this->sizeY + $this->shiftY + $dims['height'] + 6,
				$this->getColor($this->graphtheme['textcolor'], 0),
				$str
			);
		}

		// first && last
		// start
		$str = zbx_date2str(_('d.m H:i'), $this->stime);
		$dims = imageTextSize(8, 90, $str);
		imageText(
			$this->im,
			8,
			90,
			$this->shiftXleft + round($dims['width'] / 2),
			$this->sizeY + $this->shiftY + $dims['height'] + 6,
			$this->getColor($this->graphtheme['highlightcolor'], 0),
			$str
		);

		// end
		$endtime = $this->to_time;

		$str = zbx_date2str(_('d.m H:i'), $endtime);
		$dims = imageTextSize(8, 90, $str);
		imageText(
			$this->im,
			8,
			90,
			$this->sizeX + $this->shiftXleft + round($dims['width'] / 2),
			$this->sizeY + $this->shiftY + $dims['height'] + 6,
			$this->getColor($this->graphtheme['highlightcolor'], 0),
			$str
		);
	}

	private function drawMainPeriod($new_time, $new_pos) {
		if (date('H',$new_time) == 0) {
			if (date('Hi', $new_time) == 0) {
				$date_format = _('d.m');
			}
			else {
				$date_format = _('d.m H:i');
			}

			$color = $this->graphtheme['highlightcolor'];
		}
		else {
			$date_format = _('H:i');
			$color = $this->graphtheme['highlightcolor'];
		}

		$str = zbx_date2str($date_format, $new_time);
		$dims = imageTextSize(8, 90, $str);

		imageText(
			$this->im,
			8,
			90,
			$this->shiftXleft + $new_pos + round($dims['width'] / 2),
			$this->sizeY + $this->shiftY + $dims['height'] + 6,
			$this->getColor($color, 0),
			$str
		);

		dashedLine(
			$this->im,
			$this->shiftXleft + $new_pos,
			$this->shiftY,
			$this->shiftXleft + $new_pos,
			$this->sizeY + $this->shiftY,
			$this->getColor($this->graphtheme['maingridcolor'], 0)
		);
	}

	private function drawLeftSide() {
		if ($this->yaxisleft == 0 || $this->skipLeftScale == 1) {
			return;
		}

		$minY = $this->m_minY[GRAPH_YAXIS_SIDE_LEFT];
		$maxY = $this->m_maxY[GRAPH_YAXIS_SIDE_LEFT];

		$units = null;
		$unitsLong = null;
		for ($item = 0; $item < $this->num; $item++) {
			if ($this->items[$item]['axisside'] == GRAPH_YAXIS_SIDE_LEFT) {
				if (is_null($units)) {
					$units = $this->items[$item]['units'];
				}
				elseif ($this->items[$item]['units'] != $units) {
					$units = false;
				}
			}
		}

		if (is_null($units) || $units === false) {
			$units = '';
		}
		else {
			for ($item = 0; $item < $this->num; $item++) {
				if ($this->items[$item]['axisside'] == GRAPH_YAXIS_SIDE_LEFT && !empty($this->items[$item]['unitsLong'])) {
					$unitsLong = $this->items[$item]['unitsLong'];
					break;
				}
			}
		}

		if (!empty($unitsLong)) {
			$dims = imageTextSize(9, 90, $unitsLong);

			$tmpY = $this->sizeY / 2 + $this->shiftY+$dims['height'] / 2;
			if ($tmpY < $dims['height']) {
				$tmpY = $dims['height'] + 6;
			}

			imageText(
				$this->im,
				9,
				90,
				$dims['width'] + 8,
				$tmpY,
				$this->getColor($this->graphtheme['textcolor'], 0),
				$unitsLong
			);
		}

		$step = $this->gridStep[GRAPH_YAXIS_SIDE_LEFT];
		$hstr_count = $this->gridLinesCount[GRAPH_YAXIS_SIDE_LEFT];

		for ($i = 0; $i <= $hstr_count; $i++) {
			// division by zero
			$hstr_count = ($hstr_count == 0) ? 1 : $hstr_count;

			// using bc library, incase of large numbers
			$val = bcadd(bcmul($i, $step), $minY);
			$val = bcsub($val, $this->getYStepMarkerValueOffset(GRAPH_YAXIS_SIDE_LEFT, $i));
			if (bccomp(bcadd($val, bcdiv($step,2)), $maxY) == 1) {
				continue;
			}
			$str = convert_units($val, $units, ITEM_CONVERT_NO_UNITS);

			$dims = imageTextSize(8, 0, $str);

			// marker Y coordinate
			$posY = $this->getYStepMarkerPosY(GRAPH_YAXIS_SIDE_LEFT, $i);

			// only draw the marker if it doesn't overlay the previous one
			if (($posY + $dims['height']) < $this->getYStepMarkerPosY(GRAPH_YAXIS_SIDE_LEFT, $i - 1)) {
				imageText(
					$this->im,
					8,
					0,
					$this->shiftXleft - $dims['width'] - 9,
					$posY,
					$this->getColor($this->graphtheme['textcolor'], 0),
					$str
				);
			}
		}

		$str = convert_units($maxY, $units, ITEM_CONVERT_NO_UNITS);
		$dims = imageTextSize(8, 0, $str);
		imageText(
			$this->im,
			8,
			0,
			$this->shiftXleft - $dims['width'] - 9,
			$this->shiftY  + 4,
			$this->getColor($this->graphtheme['textcolor'], 0),
			$str
		);

		if ($this->zero[GRAPH_YAXIS_SIDE_LEFT] != ($this->sizeY + $this->shiftY) && $this->zero[GRAPH_YAXIS_SIDE_LEFT] != $this->shiftY) {
			imageline(
				$this->im,
				$this->shiftXleft,
				$this->zero[GRAPH_YAXIS_SIDE_LEFT],
				$this->shiftXleft + $this->sizeX,
				$this->zero[GRAPH_YAXIS_SIDE_LEFT],
				$this->getColor(GRAPH_ZERO_LINE_COLOR_LEFT)
			);
		}
	}

	private function drawRightSide() {
		if ($this->yaxisright == 0 || $this->skipRightScale == 1) {
			return;
		}

		$minY = $this->m_minY[GRAPH_YAXIS_SIDE_RIGHT];
		$maxY = $this->m_maxY[GRAPH_YAXIS_SIDE_RIGHT];

		$units = null;
		$unitsLong = null;
		for ($item = 0; $item < $this->num; $item++) {
			if ($this->items[$item]['axisside'] == GRAPH_YAXIS_SIDE_RIGHT) {
				if (is_null($units)) {
					$units = $this->items[$item]['units'];
				}
				elseif ($this->items[$item]['units'] != $units) {
					$units = false;
				}
			}
		}

		if (is_null($units) || $units === false) {
			$units = '';
		}
		else {
			for ($item = 0; $item < $this->num; $item++) {
				if ($this->items[$item]['axisside'] == GRAPH_YAXIS_SIDE_RIGHT && !empty($this->items[$item]['unitsLong'])) {
					$unitsLong = $this->items[$item]['unitsLong'];
					break;
				}
			}
		}

		if (!empty($unitsLong)) {
			$dims = imageTextSize(9, 90, $unitsLong);

			$tmpY = $this->sizeY / 2 + $this->shiftY + $dims['height'] / 2;
			if ($tmpY < $dims['height']) {
				$tmpY = $dims['height'] + 6;
			}

			imageText(
				$this->im,
				9,
				90,
				$this->fullSizeX - $dims['width'],
				$tmpY,
				$this->getColor($this->graphtheme['textcolor'], 0),
				$unitsLong
			);
		}

		$step = $this->gridStep[GRAPH_YAXIS_SIDE_RIGHT];
		$hstr_count = $this->gridLinesCount[GRAPH_YAXIS_SIDE_RIGHT];
		for ($i = 0; $i <= $hstr_count; $i++) {
			if ($hstr_count == 0) {
				continue;
			}

			// using bc module in case of large numbers
			$val = bcadd(bcmul($i, $step), $minY);
			$val = bcsub($val, $this->getYStepMarkerValueOffset(GRAPH_YAXIS_SIDE_RIGHT, $i));
			if (bccomp(bcadd($val, bcdiv($step, 2)), $maxY) == 1) {
				continue;
			}

			$str = convert_units($val, $units, ITEM_CONVERT_NO_UNITS);

			// marker Y coordinate
			$dims = imageTextSize(8, 0, $str);
			$posY = $this->getYStepMarkerPosY(GRAPH_YAXIS_SIDE_RIGHT, $i);

			// only draw the marker if it doesn't overlay the previous one
			if (($posY + $dims['height']) <= $this->getYStepMarkerPosY(GRAPH_YAXIS_SIDE_RIGHT, $i - 1)) {
				imageText(
					$this->im,
					8,
					0,
					$this->sizeX + $this->shiftXleft + 12,
					$posY,
					$this->getColor($this->graphtheme['textcolor'], 0),
					$str
				);
			}
		}

		$str = convert_units($maxY, $units, ITEM_CONVERT_NO_UNITS);
		imageText(
			$this->im,
			8,
			0,
			$this->sizeX + $this->shiftXleft + 12,
			$this->shiftY + 4,
			$this->getColor($this->graphtheme['textcolor'], 0),
			$str
		);

		if ($this->zero[GRAPH_YAXIS_SIDE_RIGHT] != $this->sizeY + $this->shiftY
				&& $this->zero[GRAPH_YAXIS_SIDE_RIGHT] != $this->shiftY) {
			imageline(
				$this->im,
				$this->shiftXleft,
				$this->zero[GRAPH_YAXIS_SIDE_RIGHT],
				$this->shiftXleft + $this->sizeX,
				$this->zero[GRAPH_YAXIS_SIDE_RIGHT],
				$this->getColor(GRAPH_ZERO_LINE_COLOR_RIGHT)
			);
		}
	}

	/**
	 * Calculates the Y coordinate of the Y scale marker value label.
	 *
	 * @param $yAxis
	 * @param $stepNumber
	 *
	 * @return float|int
	 */
	protected function getYStepMarkerPosY($yAxis, $stepNumber) {
		$posY = $this->sizeY - $this->gridStepX[$yAxis] * $stepNumber + $this->shiftY + 4;
		$posY += $this->getYStepMarkerPosOffset($yAxis, $stepNumber);

		return $posY;
	}

	/**
	 * Calculates and returns the offset of the value caused by using a fixed min Y scale value, e.g., will
	 * transform markers like 0.3, 1.3, 2.3 into 0.3, 1.0, 2.0.
	 *
	 * @param $yAxis
	 * @param $stepNumber
	 *
	 * @return int|string
	 */
	protected function getYStepMarkerValueOffset($yAxis, $stepNumber) {
		$step = $this->gridStep[$yAxis];
		$minY = abs($this->m_minY[$yAxis]);

		$offset = 0;
		if ($stepNumber > 0 && $minY) {
			$offset = ($minY > $step) ? bcfmod($minY, $step) : $minY;
		}

		return $offset;
	}

	/**
	 * Calculates and returns the position of the Y scale markers caused by the use of a fixed min Y scale value.
	 *
	 * @param $yAxis
	 * @param $stepNumber
	 *
	 * @return float
	 */
	protected function getYStepMarkerPosOffset($yAxis, $stepNumber) {
		$offset = bcdiv($this->gridStepX[$yAxis], $this->gridStep[$yAxis]);
		$offset = bcmul($offset, $this->getYStepMarkerValueOffset($yAxis, $stepNumber));

		return $offset;
	}

	protected function drawWorkPeriod() {
		imagefilledrectangle($this->im,
			$this->shiftXleft + 1,
			$this->shiftY,
			$this->sizeX + $this->shiftXleft-2, // -2 border
			$this->sizeY + $this->shiftY,
			$this->getColor($this->graphtheme['graphcolor'], 0)
		);

		if ($this->m_showWorkPeriod != 1) {
			return;
		}
		if ($this->period > 8035200) { // 31*24*3600*3 (3*month*3)
			return;
		}

		$db_work_period = DBselect('SELECT c.work_period FROM config c');
		$work_period = DBfetch($db_work_period);
		if (!$work_period) {
			return;
		}

		$periods = parse_period($work_period['work_period']);
		if (!$periods) {
			return;
		}

		imagefilledrectangle(
			$this->im,
			$this->shiftXleft + 1,
			$this->shiftY,
			$this->sizeX + $this->shiftXleft - 2, // -2 border
			$this->sizeY + $this->shiftY,
			$this->getColor($this->graphtheme['nonworktimecolor'], 0)
		);

		$now = time();
		if (isset($this->stime)) {
			$this->from_time = $this->stime;
			$this->to_time = $this->stime + $this->period;
		}
		else {
			$this->to_time = $now - SEC_PER_HOUR * $this->from;
			$this->from_time = $this->to_time - $this->period;
		}

		$from = $this->from_time;
		$max_time = $this->to_time;

		$start = find_period_start($periods, $from);
		$end = -1;
		while ($start < $max_time && $start > 0) {
			$end = find_period_end($periods, $start, $max_time);

			$x1 = round((($start - $from) * $this->sizeX) / $this->period) + $this->shiftXleft;
			$x2 = ceil((($end - $from) * $this->sizeX) / $this->period) + $this->shiftXleft;

			// draw rectangle
			imagefilledrectangle(
				$this->im,
				$x1,
				$this->shiftY,
				$x2-2, // -2 border
				$this->sizeY + $this->shiftY,
				$this->getColor($this->graphtheme['graphcolor'], 0)
			);

			$start = find_period_start($periods, $end);
		}
	}

	protected function drawPercentile() {
		if ($this->type != GRAPH_TYPE_NORMAL) {
			return ;
		}

		foreach ($this->percentile as $side => $percentile) {
			if ($percentile['percent'] > 0 && $percentile['value']) {
				if ($side == 'left') {
					$minY = $this->m_minY[GRAPH_YAXIS_SIDE_LEFT];
					$maxY = $this->m_maxY[GRAPH_YAXIS_SIDE_LEFT];

					$color = $this->graphtheme['leftpercentilecolor'];
				}
				else {
					$minY = $this->m_minY[GRAPH_YAXIS_SIDE_RIGHT];
					$maxY = $this->m_maxY[GRAPH_YAXIS_SIDE_RIGHT];

					$color = $this->graphtheme['rightpercentilecolor'];
				}

				$y = $this->sizeY - (($percentile['value'] - $minY) / ($maxY - $minY)) * $this->sizeY + $this->shiftY;
				imageline(
					$this->im,
					$this->shiftXleft,
					$y,
					$this->sizeX + $this->shiftXleft,
					$y,
					$this->getColor($color)
				);
			}
		}
	}

	protected function drawTriggers() {
		if ($this->m_showTriggers != 1) {
			return;
		}

		$opposite = hex2rgb(GRAPH_TRIGGER_LINE_OPPOSITE_COLOR);
		$oppColor = imagecolorallocate($this->im, $opposite[0], $opposite[1], $opposite[2]);
		foreach ($this->triggers as $tnum => $trigger) {
			if ($trigger['skipdraw']) {
				continue;
			}

			$triggerColor = $this->getColor($trigger['color']);
			$lineStyle = array($triggerColor, $triggerColor, $triggerColor, $triggerColor, $triggerColor, $oppColor, $oppColor, $oppColor);

			dashedLine(
				$this->im,
				$this->shiftXleft,
				$trigger['y'],
				$this->sizeX + $this->shiftXleft,
				$trigger['y'],
				$lineStyle
			);

			dashedLine(
				$this->im,
				$this->shiftXleft,
				$trigger['y'] + 1,
				$this->sizeX + $this->shiftXleft,
				$trigger['y'] + 1,
				$lineStyle
			);
		}
	}

	protected function drawLegend() {
		$leftXShift = 20;
		$units = array('left' => 0, 'right' => 0);

		$legend = new CImageTextTable($this->im, $leftXShift + 10, $this->sizeY + $this->shiftY + $this->legendOffsetY);
		$legend->color = $this->getColor($this->graphtheme['textcolor'], 0);
		$legend->rowheight = 14;
		$legend->fontsize = 9;

		$row = array(
			array('text' => ''),
			array('text' => ''),
			array('text' => _('last'), 'align' => 1, 'fontsize' => 9),
			array('text' => _('min'), 'align' => 1, 'fontsize' => 9),
			array('text' => _('avg'), 'align' => 1, 'fontsize' => 9),
			array('text' => _('max'), 'align' => 1, 'fontsize' => 9)
		);

		$legend->addRow($row);
		$colNum = $legend->getNumRows();

		$i = ($this->type == GRAPH_TYPE_STACKED) ? $this->num - 1 : 0;
		while ($i >= 0 && $i < $this->num) {
			$color = $this->getColor($this->items[$i]['color'], GRAPH_STACKED_ALFA);
			switch ($this->items[$i]['calc_fnc']) {
				case CALC_FNC_MIN:
					$fnc_name = _('min');
					break;
				case CALC_FNC_MAX:
					$fnc_name = _('max');
					break;
				case CALC_FNC_ALL:
					$fnc_name = _('all');
					break;
				case CALC_FNC_AVG:
				default:
					$fnc_name = _('avg');
			}

			$data = &$this->data[$this->items[$i]['itemid']][$this->items[$i]['calc_type']];

			if ($this->itemsHost) {
				$item_caption = $this->items[$i]['name'];
			}
			else {
				$item_caption = $this->items[$i]['hostname'].': '.$this->items[$i]['name'];
			}

			if (isset($data) && isset($data['min'])) {
				if ($this->items[$i]['axisside'] == GRAPH_YAXIS_SIDE_LEFT) {
					$units['left'] = $this->items[$i]['units'];
				}
				else {
					$units['right'] = $this->items[$i]['units'];
				}

				$legend->addCell($colNum, array('text' => $item_caption));
				$legend->addCell($colNum, array('text' => '['.$fnc_name.']'));
				$legend->addCell($colNum, array('text' => convert_units($this->getLastValue($i), $this->items[$i]['units'], ITEM_CONVERT_NO_UNITS), 'align' => 2));
				$legend->addCell($colNum, array('text' => convert_units(min($data['min']), $this->items[$i]['units'], ITEM_CONVERT_NO_UNITS), 'align' => 2));
				$legend->addCell($colNum, array('text' => convert_units($data['avg_orig'], $this->items[$i]['units'], ITEM_CONVERT_NO_UNITS), 'align' => 2));
				$legend->addCell($colNum, array('text' => convert_units(max($data['max']), $this->items[$i]['units'], ITEM_CONVERT_NO_UNITS), 'align' => 2));
			}
			else {
				$legend->addCell($colNum, array('text' => $item_caption));
				$legend->addCell($colNum, array('text' => '[ '._('no data').' ]'));
			}

			imagefilledrectangle(
				$this->im,
				$leftXShift - 5,
				$this->sizeY + $this->shiftY + 14 * $colNum + $this->legendOffsetY - 10,
				$leftXShift + 5,
				$this->sizeY + $this->shiftY + 14 * $colNum + $this->legendOffsetY,
				$color
			);

			imagerectangle(
				$this->im,
				$leftXShift - 5,
				$this->sizeY + $this->shiftY + 14 * $colNum + $this->legendOffsetY - 10,
				$leftXShift + 5,
				$this->sizeY + $this->shiftY + 14 * $colNum + $this->legendOffsetY,
				$this->getColor('Black')
			);

			$colNum++;

			if ($this->type == GRAPH_TYPE_STACKED) {
				$i--;
			}
			else {
				$i++;
			}
		}
		$legend->draw();

		// if graph is small, we are not drawing percent line and trigger legends
		if ($this->sizeY < ZBX_GRAPH_LEGEND_HEIGHT) {
			return true;
		}

		$legend = new CImageTextTable(
			$this->im,
			$leftXShift + 10,
			$this->sizeY + $this->shiftY + 14 * $colNum + $this->legendOffsetY
		);
		$legend->color = $this->getColor($this->graphtheme['textcolor'], 0);
		$legend->rowheight = 14;
		$legend->fontsize = 9;

		// draw percentile
		if ($this->type == GRAPH_TYPE_NORMAL) {
			foreach ($this->percentile as $side => $percentile) {
				if ($percentile['percent'] > 0 && $percentile['value']) {
					$percentile['percent'] = (float) $percentile['percent'];
					$legend->addCell($colNum, array(
						'text' => $percentile['percent'].'th percentile: '.convert_units($percentile['value'], $units[$side]).' ('.$side.')',
						ITEM_CONVERT_NO_UNITS
					));
					if ($side == 'left') {
						$color = $this->graphtheme['leftpercentilecolor'];
					}
					else {
						$color = $this->graphtheme['rightpercentilecolor'];
					}

					imagefilledpolygon(
						$this->im,
						array(
							$leftXShift + 5, $this->sizeY + $this->shiftY + 14 * $colNum + $this->legendOffsetY,
							$leftXShift - 5, $this->sizeY + $this->shiftY + 14 * $colNum + $this->legendOffsetY,
							$leftXShift, $this->sizeY + $this->shiftY + 14 * $colNum + $this->legendOffsetY - 10
						),
						3,
						$this->getColor($color)
					);

					imagepolygon(
						$this->im,
						array(
							$leftXShift + 5, $this->sizeY + $this->shiftY + 14 * $colNum + $this->legendOffsetY,
							$leftXShift - 5, $this->sizeY + $this->shiftY + 14 * $colNum + $this->legendOffsetY,
							$leftXShift, $this->sizeY + $this->shiftY + 14 * $colNum + $this->legendOffsetY - 10
						),
						3,
						$this->getColor('Black No Alpha')
					);
					$colNum++;
				}
			}
		}

		$legend->draw();

		$legend = new CImageTextTable(
			$this->im,
			$leftXShift + 10,
			$this->sizeY + $this->shiftY + 14 * $colNum + $this->legendOffsetY + 5
		);
		$legend->color = $this->getColor($this->graphtheme['textcolor'], 0);
		$legend->rowheight = 14;
		$legend->fontsize = 9;

		// draw triggers
		foreach ($this->triggers as $trigger) {
			imagefilledellipse(
				$this->im,
				$leftXShift,
				$this->sizeY + $this->shiftY + 14 * $colNum + $this->legendOffsetY,
				10,
				10,
				$this->getColor($trigger['color'])
			);

			imageellipse(
				$this->im,
				$leftXShift,
				$this->sizeY + $this->shiftY + 14 * $colNum + $this->legendOffsetY,
				10,
				10,
				$this->getColor('Black No Alpha')
			);

			$legend->addRow(array(
				array('text' => $trigger['description']),
				array('text' => $trigger['constant'])
			));
			$colNum++;
		}

		$legend->draw();
	}

	protected function limitToBounds(&$value1, &$value2, $min, $max, $drawtype) {
		// fixes graph out of bounds problem
		if ((($value1 > ($max + $min)) && ($value2 > ($max + $min))) || ($value1 < $min && $value2 < $min)) {
			if (!in_array($drawtype, array(GRAPH_ITEM_DRAWTYPE_FILLED_REGION, GRAPH_ITEM_DRAWTYPE_GRADIENT_LINE))) {
				return false;
			}
		}

		$y_first = $value1 > ($max + $min) || $value1 < $min;
		$y_second = $value2 > ($max + $min) || $value2 < $min;

		if ($y_first) {
			$value1 = ($value1 > ($max + $min)) ? $max + $min : $min;
		}

		if ($y_second) {
			$value2 = ($value2 > ($max + $min)) ? $max + $min : $min;
		}

		return true;
	}

	protected function drawElement(&$data, $from, $to, $minX, $maxX, $minY, $maxY, $drawtype, $max_color, $avg_color, $min_color, $minmax_color, $calc_fnc, $axisside) {
		if (!isset($data['max'][$from]) || !isset($data['max'][$to])) {
			return;
		}

		$oxy = $this->oxy[$axisside];
		$zero = $this->zero[$axisside];
		$unit2px = $this->unit2px[$axisside];

		$shift_min_from = $shift_min_to = 0;
		$shift_max_from = $shift_max_to = 0;
		$shift_avg_from = $shift_avg_to = 0;

		if (isset($data['shift_min'][$from])) {
			$shift_min_from = $data['shift_min'][$from];
		}
		if (isset($data['shift_min'][$to])) {
			$shift_min_to = $data['shift_min'][$to];
		}

		if (isset($data['shift_max'][$from])) {
			$shift_max_from = $data['shift_max'][$from];
		}
		if (isset($data['shift_max'][$to])) {
			$shift_max_to = $data['shift_max'][$to];
		}

		if (isset($data['shift_avg'][$from])) {
			$shift_avg_from = $data['shift_avg'][$from];
		}
		if (isset($data['shift_avg'][$to])) {
			$shift_avg_to = $data['shift_avg'][$to];
		}

		$min_from = $data['min'][$from] + $shift_min_from;
		$min_to = $data['min'][$to] + $shift_min_to;

		$max_from = $data['max'][$from] + $shift_max_from;
		$max_to = $data['max'][$to] + $shift_max_to;

		$avg_from = $data['avg'][$from] + $shift_avg_from;
		$avg_to = $data['avg'][$to] + $shift_avg_to;

		$x1 = $from + $this->shiftXleft - 1;
		$x2 = $to + $this->shiftXleft;

		$y1min = $zero - ($min_from - $oxy) / $unit2px;
		$y2min = $zero - ($min_to - $oxy) / $unit2px;

		$y1max = $zero - ($max_from - $oxy) / $unit2px;
		$y2max = $zero - ($max_to - $oxy) / $unit2px;

		$y1avg = $zero - ($avg_from - $oxy) / $unit2px;
		$y2avg = $zero - ($avg_to - $oxy) / $unit2px;

		switch ($calc_fnc) {
			case CALC_FNC_MAX:
				$y1 = $y1max;
				$y2 = $y2max;
				$shift_from = $shift_max_from;
				$shift_to = $shift_max_to;
				break;
			case CALC_FNC_MIN:
				$y1 = $y1min;
				$y2 = $y2min;
				$shift_from = $shift_min_from;
				$shift_to = $shift_min_to;
				break;
			case CALC_FNC_ALL:
				// max
				$y1x = (($y1max > ($this->sizeY + $this->shiftY)) || $y1max < $this->shiftY);
				$y2x = (($y2max > ($this->sizeY + $this->shiftY)) || $y2max < $this->shiftY);

				if ($y1x) {
					$y1max = ($y1max > ($this->sizeY + $this->shiftY)) ? $this->sizeY + $this->shiftY : $this->shiftY;
				}
				if ($y2x) {
					$y2max = ($y2max > ($this->sizeY + $this->shiftY)) ? $this->sizeY + $this->shiftY : $this->shiftY;
				}

				// min
				$y1n = (($y1min > ($this->sizeY + $this->shiftY)) || $y1min < $this->shiftY);
				$y2n = (($y2min > ($this->sizeY + $this->shiftY)) || $y2min < $this->shiftY);

				if ($y1n) {
					$y1min = ($y1min > ($this->sizeY + $this->shiftY)) ? $this->sizeY + $this->shiftY : $this->shiftY;
				}
				if ($y2n) {
					$y2min = ($y2min > ($this->sizeY + $this->shiftY)) ? $this->sizeY + $this->shiftY : $this->shiftY;
				}

				$a[0] = $x1;
				$a[1] = $y1max;
				$a[2] = $x1;
				$a[3] = $y1min;
				$a[4] = $x2;
				$a[5] = $y2min;
				$a[6] = $x2;
				$a[7] = $y2max;

			// don't use break, avg must be drawed in this statement
			case CALC_FNC_AVG:

			// don't use break, avg must be drawed in this statement
			default:
				$y1 = $y1avg;
				$y2 = $y2avg;
				$shift_from = $shift_avg_from ;
				$shift_to = $shift_avg_to;
		}

		$shift_from -= ($shift_from != 0) ? $oxy : 0;
		$shift_to -= ($shift_to != 0) ? $oxy : 0;

		$y1_shift = $zero - $shift_from / $unit2px;
		$y2_shift = $zero - $shift_to / $unit2px;

		if (!$this->limitToBounds($y1, $y2, $this->shiftY, $this->sizeY, $drawtype)) {
			return true;
		}
		if (!$this->limitToBounds($y1_shift, $y2_shift, $this->shiftY, $this->sizeY, $drawtype)) {
			return true;
		}

		// draw main line
		switch ($drawtype) {
			case GRAPH_ITEM_DRAWTYPE_BOLD_LINE:
				if ($calc_fnc == CALC_FNC_ALL) {
					imagefilledpolygon($this->im, $a, 4, $minmax_color);
					if (!$y1x || !$y2x) {
						imageline($this->im, $x1 + 1, $y1max, $x2 + 1, $y2max, $max_color);
						imageline($this->im, $x1, $y1max, $x2, $y2max, $max_color);
					}

					if (!$y1n || !$y2n) {
						imageline($this->im, $x1 - 1, $y1min, $x2 - 1, $y2min, $min_color);
						imageline($this->im, $x1, $y1min, $x2, $y2min, $min_color);
					}
				}

				imageline($this->im, $x1, $y1 + 1, $x2, $y2 + 1, $avg_color);
				imageline($this->im, $x1, $y1, $x2, $y2, $avg_color);
				break;
			case GRAPH_ITEM_DRAWTYPE_LINE:
				if ($calc_fnc == CALC_FNC_ALL) {
					imagefilledpolygon($this->im, $a, 4, $minmax_color);
					if (!$y1x || !$y2x) {
						imageline($this->im, $x1, $y1max, $x2, $y2max, $max_color);
					}
					if (!$y1n || !$y2n) {
						imageline($this->im, $x1, $y1min, $x2, $y2min, $min_color);
					}
				}

				imageline($this->im, $x1, $y1, $x2, $y2, $avg_color);
				break;
			case GRAPH_ITEM_DRAWTYPE_FILLED_REGION:
				$a[0] = $x1;
				$a[1] = $y1;
				$a[2] = $x1;
				$a[3] = $y1_shift;
				$a[4] = $x2;
				$a[5] = $y2_shift;
				$a[6] = $x2;
				$a[7] = $y2;

				imagefilledpolygon($this->im, $a, 4, $avg_color);
				break;
			case GRAPH_ITEM_DRAWTYPE_DOT:
				imagefilledrectangle($this->im, $x1 - 1, $y1 - 1, $x1, $y1, $avg_color);
				break;
			case GRAPH_ITEM_DRAWTYPE_BOLD_DOT:
				imagefilledrectangle($this->im, $x2 - 1, $y2 - 1, $x2 + 1, $y2 + 1, $avg_color);
				break;
			case GRAPH_ITEM_DRAWTYPE_DASHED_LINE:
				if (function_exists('imagesetstyle')) {
					// use imagesetstyle+imageline instead of bugged imagedashedline
					$style = array($avg_color, $avg_color, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT);
					imagesetstyle($this->im, $style);
					imageline($this->im, $x1, $y1, $x2, $y2, IMG_COLOR_STYLED);
				}
				else {
					imagedashedline($this->im, $x1, $y1, $x2, $y2, $avg_color);
				}
				break;
			case GRAPH_ITEM_DRAWTYPE_GRADIENT_LINE:
				imageLine($this->im, $x1, $y1, $x2, $y2, $avg_color); // draw the initial line
				imageLine($this->im, $x1, $y1 - 1, $x2, $y2 - 1, $avg_color);

				$bitmask = 255;
				$blue = $avg_color & $bitmask;

				// $blue_diff = 255 - $blue;
				$bitmask = $bitmask << 8;
				$green = ($avg_color & $bitmask) >> 8;

				// $green_diff = 255 - $green;
				$bitmask = $bitmask << 8;
				$red = ($avg_color & $bitmask) >> 16;
				// $red_diff = 255 - $red;

				// note: though gradients on the chart looks ok, the formula used is completely incorrect
				// if you plan to fix something here, it would be better to start from scratch
				$maxAlpha = 110;
				$startAlpha = 50;
				$alphaRatio = $maxAlpha / ($this->sizeY - $startAlpha);

				$diffX = $x1 - $x2;
				for ($i = 0; $i <= $diffX; $i++) {
					$Yincr = ($diffX > 0) ? (abs($y2 - $y1) / $diffX) : 0;

					$gy = ($y1 > $y2) ? ($y2 + $Yincr * $i) : ($y2 - $Yincr * $i);
					$steps = $this->sizeY + $this->shiftY - $gy + 1;

					for ($j = 0; $j < $steps; $j++) {
						if (($gy + $j) < ($this->shiftY + $startAlpha)) {
							$alpha = 0;
						}
						else {
							$alpha = 127 - abs(127 - ($alphaRatio * ($gy + $j - $this->shiftY - $startAlpha)));
						}

						$color = imagecolorexactalpha($this->im, $red, $green, $blue, $alpha);
						imagesetpixel($this->im, $x2 + $i, $gy + $j, $color);
					}
				}
			break;
		}
	}

	public function draw() {
		$start_time = microtime(true);

		set_image_header();

		$this->selectData();

		$this->m_minY[GRAPH_YAXIS_SIDE_LEFT] = $this->calculateMinY(GRAPH_YAXIS_SIDE_LEFT);
		$this->m_minY[GRAPH_YAXIS_SIDE_RIGHT] = $this->calculateMinY(GRAPH_YAXIS_SIDE_RIGHT);
		$this->m_maxY[GRAPH_YAXIS_SIDE_LEFT] = $this->calculateMaxY(GRAPH_YAXIS_SIDE_LEFT);
		$this->m_maxY[GRAPH_YAXIS_SIDE_RIGHT] = $this->calculateMaxY(GRAPH_YAXIS_SIDE_RIGHT);

		if ($this->m_minY[GRAPH_YAXIS_SIDE_LEFT] == $this->m_maxY[GRAPH_YAXIS_SIDE_LEFT]) {
			if ($this->graphOrientation[GRAPH_YAXIS_SIDE_LEFT] == '-') {
				$this->m_maxY[GRAPH_YAXIS_SIDE_LEFT] = 0;
			}
			elseif ($this->m_minY[GRAPH_YAXIS_SIDE_LEFT] == 0) {
				$this->m_maxY[GRAPH_YAXIS_SIDE_LEFT] = 1;
			}
			else {
				$this->m_minY[GRAPH_YAXIS_SIDE_LEFT] = 0;
			}
		}
		elseif ($this->m_minY[GRAPH_YAXIS_SIDE_LEFT] > $this->m_maxY[GRAPH_YAXIS_SIDE_LEFT]) {
			if ($this->graphOrientation[GRAPH_YAXIS_SIDE_LEFT] == '-') {
				$this->m_minY[GRAPH_YAXIS_SIDE_LEFT] = 0.2 * $this->m_maxY[GRAPH_YAXIS_SIDE_LEFT];
			}
			else {
				$this->m_minY[GRAPH_YAXIS_SIDE_LEFT] = 0;
			}
		}

		if ($this->m_minY[GRAPH_YAXIS_SIDE_RIGHT] == $this->m_maxY[GRAPH_YAXIS_SIDE_RIGHT]) {
			if ($this->graphOrientation[GRAPH_YAXIS_SIDE_RIGHT] == '-') {
				$this->m_maxY[GRAPH_YAXIS_SIDE_RIGHT] = 0;
			}
			elseif ($this->m_minY[GRAPH_YAXIS_SIDE_RIGHT] == 0) {
				$this->m_maxY[GRAPH_YAXIS_SIDE_RIGHT] = 1;
			}
			else {
				$this->m_minY[GRAPH_YAXIS_SIDE_RIGHT] = 0;
			}
		}
		elseif ($this->m_minY[GRAPH_YAXIS_SIDE_RIGHT] > $this->m_maxY[GRAPH_YAXIS_SIDE_RIGHT]) {
			if ($this->graphOrientation[GRAPH_YAXIS_SIDE_RIGHT] == '-') {
				$this->m_minY[GRAPH_YAXIS_SIDE_RIGHT] = 0.2 * $this->m_maxY[GRAPH_YAXIS_SIDE_RIGHT];
			}
			else {
				$this->m_minY[GRAPH_YAXIS_SIDE_RIGHT] = 0;
			}
		}

		$this->calcMinMaxInterval();
		$this->updateShifts();
		$this->calcTriggers();
		$this->calcZero();
		$this->calcPercentile();

		$this->fullSizeX = $this->sizeX + $this->shiftXleft + $this->shiftXright + 1;
		$this->fullSizeY = $this->sizeY + $this->shiftY + $this->legendOffsetY;

		if ($this->drawLegend) {
			$this->fullSizeY += 14 * ($this->num + 1 + (($this->sizeY < 120) ? 0 : count($this->triggers))) + 8;
		}

		// if graph height is big enough, we reserve space for percent line legend
		if ($this->sizeY >= ZBX_GRAPH_LEGEND_HEIGHT) {
			foreach ($this->percentile as $percentile) {
				if ($percentile['percent'] > 0 && $percentile['value']) {
					$this->fullSizeY += 14;
				}
			}
		}

		if (function_exists('imagecolorexactalpha') && function_exists('imagecreatetruecolor') && @imagecreatetruecolor(1, 1)) {
			$this->im = imagecreatetruecolor($this->fullSizeX, $this->fullSizeY);
		}
		else {
			$this->im = imagecreate($this->fullSizeX, $this->fullSizeY);
		}

		$this->initColors();
		$this->drawRectangle();
		$this->drawHeader();
		$this->drawWorkPeriod();
		$this->drawTimeGrid();
		$this->drawVerticalGrid();
		$this->drawXYAxisScale($this->graphtheme['gridbordercolor']);

		$maxX = $this->sizeX;

		// for each metric
		for ($item = 0; $item < $this->num; $item++) {
			$minY = $this->m_minY[$this->items[$item]['axisside']];
			$maxY = $this->m_maxY[$this->items[$item]['axisside']];

			$data = &$this->data[$this->items[$item]['itemid']][$this->items[$item]['calc_type']];

			if (!isset($data)) {
				continue;
			}

			if ($this->type == GRAPH_TYPE_STACKED) {
				$drawtype = $this->items[$item]['drawtype'];
				$max_color = $this->getColor('ValueMax', GRAPH_STACKED_ALFA);
				$avg_color = $this->getColor($this->items[$item]['color'], GRAPH_STACKED_ALFA);
				$min_color = $this->getColor('ValueMin', GRAPH_STACKED_ALFA);
				$minmax_color = $this->getColor('ValueMinMax', GRAPH_STACKED_ALFA);

				$calc_fnc = $this->items[$item]['calc_fnc'];
			}
			else {
				$drawtype = $this->items[$item]['drawtype'];
				$max_color = $this->getColor('ValueMax', GRAPH_STACKED_ALFA);
				$avg_color = $this->getColor($this->items[$item]['color'], GRAPH_STACKED_ALFA);
				$min_color = $this->getColor('ValueMin', GRAPH_STACKED_ALFA);
				$minmax_color = $this->getColor('ValueMinMax', GRAPH_STACKED_ALFA);

				$calc_fnc = $this->items[$item]['calc_fnc'];
			}

			// for each X
			$draw = true;
			$prevDraw = true;
			for ($i = 1, $j = 0; $i < $maxX; $i++) { // new point
				if ($data['count'][$i] == 0 && $i != ($maxX - 1)) {
					continue;
				}

				$diff = abs($data['clock'][$i] - $data['clock'][$j]);
				$cell = ($this->to_time - $this->from_time) / $this->sizeX;
				$delay = $this->items[$item]['delay'];

				if ($cell > $delay) {
					$draw = (boolean) ($diff < (ZBX_GRAPH_MAX_SKIP_CELL * $cell));
				}
				else {
					$draw = (boolean) ($diff < (ZBX_GRAPH_MAX_SKIP_DELAY * $delay));
				}

				if ($this->items[$item]['type'] == ITEM_TYPE_TRAPPER) {
					$draw = true;
				}

				if (!$draw && !$prevDraw) {
					$draw = true;
					$valueDrawType = GRAPH_ITEM_DRAWTYPE_BOLD_DOT;
				}
				else {
					$valueDrawType = $drawtype;
					$prevDraw = $draw;
				}

				if ($draw) {
					$this->drawElement(
						$data,
						$i,
						$j,
						0,
						$this->sizeX,
						$minY,
						$maxY,
						$valueDrawType,
						$max_color,
						$avg_color,
						$min_color,
						$minmax_color,
						$calc_fnc,
						$this->items[$item]['axisside']
					);
				}

				$j = $i;
			}
		}

		$this->drawLeftSide();
		$this->drawRightSide();

		if ($this->drawLegend) {
			$this->drawTriggers();
			$this->drawPercentile();
			$this->drawLegend();
		}

		$this->drawLogo();

		$str = sprintf('%0.2f', microtime(true) - $start_time);
		$str = _s('Data from %1$s. Generated in %2$s sec.', $this->dataFrom, $str);
		$strSize = imageTextSize(6, 0, $str);
		imageText($this->im, 6, 0, $this->fullSizeX - $strSize['width'] - 5, $this->fullSizeY - 5, $this->getColor('Gray'), $str);

		unset($this->items, $this->data);

		imageOut($this->im);
	}
}
