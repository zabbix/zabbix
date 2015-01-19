<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CLineGraphDraw extends CGraphDraw {

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

		// TODO: graphs shouldn't retrieve items and resolve macros themselves
		// all of the data must be passed as parameters
		$items = CMacrosResolverHelper::resolveItemNames(array(get_item_by_itemid($itemid)));
		$item = reset($items);

		$item['name'] = $item['name_expanded'];

		$this->items[$this->num] = $item;
		$this->items[$this->num]['delay'] = getItemDelay($item['delay'], $item['delay_flex']);

		if (strpos($item['units'], ',') === false) {
			$this->items[$this->num]['unitsLong'] = '';
		}
		else {
			list($this->items[$this->num]['units'], $this->items[$this->num]['unitsLong']) = explode(',', $item['units']);
		}

		$host = get_host_by_hostid($item['hostid']);

		$this->items[$this->num]['host'] = $host['host'];
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

		$config = select_config();

		for ($i = 0; $i < $this->num; $i++) {
			$item = get_item_by_itemid($this->items[$i]['itemid']);

			if ($this->itemsHost === null) {
				$this->itemsHost = $item['hostid'];
			}
			elseif ($this->itemsHost != $item['hostid']) {
				$this->itemsHost = false;
			}

			if (!isset($this->axis_valuetype[$this->items[$i]['axisside']])) {
				$this->axis_valuetype[$this->items[$i]['axisside']] = $item['value_type'];
			}
			elseif ($this->axis_valuetype[$this->items[$i]['axisside']] != $item['value_type']) {
				$this->axis_valuetype[$this->items[$i]['axisside']] = ITEM_VALUE_TYPE_FLOAT;
			}

			$type = $this->items[$i]['calc_type'];
			$from_time = $this->from_time;
			$to_time = $this->to_time;
			$calc_field = 'round('.$x.'*'.zbx_sql_mod(zbx_dbcast_2bigint('clock').'+'.$z, $p).'/('.$p.'),0)'; // required for 'group by' support of Oracle

			$sql_arr = array();

			// override item history setting with housekeeping settings
			if ($config['hk_history_global']) {
				$item['history'] = $config['hk_history'];
			}

			$trendsEnabled = $config['hk_trends_global'] ? ($config['hk_trends'] > 0) : ($item['trends'] > 0);

			if (!$trendsEnabled
					|| (($item['history'] * SEC_PER_DAY) > (time() - ($this->from_time + $this->period / 2))
						&& ($this->period / $this->sizeX) <= (ZBX_MAX_TREND_DIFF / ZBX_GRAPH_MAX_SKIP_CELL))) {
				$this->dataFrom = 'history';

				array_push($sql_arr,
					'SELECT itemid,'.$calc_field.' AS i,'.
						'COUNT(*) AS count,AVG(value) AS avg,MIN(value) as min,'.
						'MAX(value) AS max,MAX(clock) AS clock'.
					' FROM history '.
					' WHERE itemid='.zbx_dbstr($this->items[$i]['itemid']).
						' AND clock>='.zbx_dbstr($from_time).
						' AND clock<='.zbx_dbstr($to_time).
					' GROUP BY itemid,'.$calc_field
					,
					'SELECT itemid,'.$calc_field.' AS i,'.
						'COUNT(*) AS count,AVG(value) AS avg,MIN(value) AS min,'.
						'MAX(value) AS max,MAX(clock) AS clock'.
					' FROM history_uint '.
					' WHERE itemid='.zbx_dbstr($this->items[$i]['itemid']).
						' AND clock>='.zbx_dbstr($from_time).
						' AND clock<='.zbx_dbstr($to_time).
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
					' WHERE itemid='.zbx_dbstr($this->items[$i]['itemid']).
						' AND clock>='.zbx_dbstr($from_time).
						' AND clock<='.zbx_dbstr($to_time).
					' GROUP BY itemid,'.$calc_field
					,
					'SELECT itemid,'.$calc_field.' AS i,'.
						'SUM(num) AS count,AVG(value_avg) AS avg,MIN(value_min) AS min,'.
						'MAX(value_max) AS max,MAX(clock) AS clock'.
					' FROM trends_uint '.
					' WHERE itemid='.zbx_dbstr($this->items[$i]['itemid']).
						' AND clock>='.zbx_dbstr($from_time).
						' AND clock<='.zbx_dbstr($to_time).
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
			$curr_data['avg_orig'] = is_array($curr_data['avg']) ? zbx_avg($curr_data['avg']) : null;

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
		}

		// calculate shift for stacked graphs
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
					' AND f.itemid='.zbx_dbstr($item['itemid']).
				' ORDER BY tr.priority'
			);
			while (($trigger = DBfetch($db_triggers)) && $cnt < $max) {
				$db_fnc_cnt = DBselect(
					'SELECT COUNT(*) AS cnt'.
					' FROM functions f'.
					' WHERE f.triggerid='.zbx_dbstr($trigger['triggerid'])
				);
				$fnc_cnt = DBfetch($db_fnc_cnt);

				if ($fnc_cnt['cnt'] != 1) {
					continue;
				}

				$trigger['expression'] = CMacrosResolverHelper::resolveTriggerExpressionUserMacro($trigger);

				if (!preg_match('/^\{([0-9]+)\}\s*?([\<\>\=]{1})\s*?([\-0-9\.]+)([TGMKsmhdw]?)$/', $trigger['expression'], $arr)) {
					continue;
				}

				$val = convert($arr[3].$arr[4]);

				$minY = $this->m_minY[$this->items[$inum]['axisside']];
				$maxY = $this->m_maxY[$this->items[$inum]['axisside']];

				$this->triggers[] = array(
					'skipdraw' => ($val <= $minY || $val >= $maxY),
					'y' => $this->sizeY - (($val - $minY) / ($maxY - $minY)) * $this->sizeY + $this->shiftY,
					'color' => getSeverityColor($trigger['priority']),
					'description' => _('Trigger').NAME_DELIMITER.CMacrosResolverHelper::resolveTriggerName($trigger),
					'constant' => '['.$arr[2].' '.$arr[3].$arr[4].']'
				);
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
				// Using "Nearest Rank" method: http://en.wikipedia.org/wiki/Percentile#Definition_of_the_Nearest_Rank_method
				$percent = (int) ceil($percentile['percent'] / 100 * count($values[$side]));
				// - 1 is necessary because array starts with the 0 index
				$this->percentile[$side]['value'] = $values[$side][$percent - 1];
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
			$history = Manager::History()->getLast(array($item));
			if (isset($history[$item['itemid']])) {
				return $history[$item['itemid']][0]['value'];
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
			$history = Manager::History()->getLast(array($item));
			if (isset($history[$item['itemid']])) {
				return $history[$item['itemid']][0]['value'];
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

	/**
	 * Check if Y axis min value is larger than Y axis max value. Show error instead of graph if true.
	 *
	 * @param float $min		Y axis min value
	 * @param float $max		Y axis max value
	 */
	protected function validateMinMax($min, $max) {
		if (bccomp($min, $max) == 0 || bccomp($min, $max) == 1) {
			show_error_message(_('Y axis MAX value must be greater than Y axis MIN value.'));
			exit;
		}
	}

	protected function calcZero() {
		if (isset($this->axis_valuetype[GRAPH_YAXIS_SIDE_RIGHT])) {
			$sides[] = GRAPH_YAXIS_SIDE_RIGHT;
		}

		if (isset($this->axis_valuetype[GRAPH_YAXIS_SIDE_LEFT]) || !isset($sides)) {
			$sides[] = GRAPH_YAXIS_SIDE_LEFT;
		}

		foreach ($sides as $num => $side) {
			$this->unit2px[$side] = ($this->m_maxY[$side] - $this->m_minY[$side]) / $this->sizeY;
			if ($this->unit2px[$side] == 0) {
				$this->unit2px[$side] = 1;
			}

			if ($this->m_minY[$side] > 0) {
				$this->zero[$side] = $this->sizeY + $this->shiftY;
				if (bccomp($this->m_minY[$side], $this->m_maxY[$side]) == 1) {
					$this->oxy[$side] = $this->m_maxY[$side];
				}
				else {
					$this->oxy[$side] = $this->m_minY[$side];
				}
			}
			elseif ($this->m_maxY[$side] < 0) {
				$this->zero[$side] = $this->shiftY;
				if (bccomp($this->m_minY[$side], $this->m_maxY[$side]) == 1) {
					$this->oxy[$side] = $this->m_minY[$side];
				}
				else {
					$this->oxy[$side] = $this->m_maxY[$side];
				}
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

		// check if items use B or Bps units
		$leftBase1024 = false;
		$rightBase1024 = false;

		for ($item = 0; $item < $this->num; $item++) {
			if ($this->items[$item]['units'] == 'B' || $this->items[$item]['units'] == 'Bps') {
				if ($this->items[$item]['axisside'] == GRAPH_YAXIS_SIDE_LEFT) {
					$leftBase1024 = true;
				}
				else {
					$rightBase1024 = true;
				}
			}
		}

		foreach (array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18) as $num) {
			$dec = bcpow(10, $num);
			foreach (array(1, 2, 5) as $int) {
				$intervals[] = bcmul($int, $dec);
			}
		}

		if (isset($this->axis_valuetype[GRAPH_YAXIS_SIDE_RIGHT])) {
			$sides[] = GRAPH_YAXIS_SIDE_RIGHT;
		}

		if (isset($this->axis_valuetype[GRAPH_YAXIS_SIDE_LEFT]) || !isset($sides)) {
			$sides[] = GRAPH_YAXIS_SIDE_LEFT;
		}

		foreach ($sides as $snum => $side) {
			if (!isset($this->axis_valuetype[$side])) {
				continue;
			}

			if (($this->ymin_type != GRAPH_YAXIS_TYPE_FIXED || $this->ymax_type != GRAPH_YAXIS_TYPE_CALCULATED)
					&& $this->type == GRAPH_TYPE_STACKED) {
				$this->m_minY[$side] = min($this->m_minY[$side], 0);
				$this->validateMinMax($this->m_minY[$side], $this->m_maxY[$side]);

				continue;
			}

			if ($this->ymax_type == GRAPH_YAXIS_TYPE_FIXED) {
				$this->m_maxY[$side] = $this->yaxismax;
				if ($this->ymin_type == GRAPH_YAXIS_TYPE_CALCULATED
						&& ($this->m_minY[$side] == null || bccomp($this->m_maxY[$side], $this->m_minY[$side]) == 0
								|| bccomp($this->m_maxY[$side], $this->m_minY[$side]) == -1)) {
					if ($this->m_maxY[$side] == 0) {
						$this->m_minY[$side] = -1;
					}
					elseif ($this->m_maxY[$side] > 0) {
						$this->m_minY[$side] = bcmul($this->m_maxY[$side], 0.8);
					}
					else {
						$this->m_minY[$side] = bcmul($this->m_maxY[$side], 1.2);
					}
				}
			}

			if ($this->ymin_type == GRAPH_YAXIS_TYPE_FIXED) {
				$this->m_minY[$side] = $this->yaxismin;
				if ($this->ymax_type == GRAPH_YAXIS_TYPE_CALCULATED
						&& ($this->m_maxY[$side] == null || bccomp($this->m_maxY[$side], $this->m_minY[$side]) == 0
								|| bccomp($this->m_maxY[$side], $this->m_minY[$side]) == -1)) {
					if ($this->m_minY[$side] > 0) {
						$this->m_maxY[$side] = bcmul($this->m_minY[$side], 1.2);
					}
					else {
						$this->m_maxY[$side] = bcmul($this->m_minY[$side], 0.8);
					}
				}
			}

			$this->validateMinMax($this->m_minY[$side], $this->m_maxY[$side]);
		}

		$side = GRAPH_YAXIS_SIDE_LEFT;
		$other_side = GRAPH_YAXIS_SIDE_RIGHT;

		// invert sides and it bases, if left side not exist
		if (!isset($this->axis_valuetype[GRAPH_YAXIS_SIDE_LEFT])) {
			$side = GRAPH_YAXIS_SIDE_RIGHT;
			$other_side = GRAPH_YAXIS_SIDE_LEFT;
			$tempBase = $leftBase1024;
			$leftBase1024 = $rightBase1024;
			$rightBase1024 = $tempBase;
		}

		if (!isset($this->m_minY[$side])) {
			$this->m_minY[$side] = 0;
		}
		if (!isset($this->m_maxY[$side])) {
			$this->m_maxY[$side] = 0;
		}

		if (!isset($this->m_minY[$other_side])) {
			$this->m_minY[$other_side] = 0;
		}
		if (!isset($this->m_maxY[$other_side])) {
			$this->m_maxY[$other_side] = 0;
		}

		$tmp_minY = $this->m_minY;
		$tmp_maxY = $this->m_maxY;

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

		// calculate interval, if left side use B or Bps
		if ($leftBase1024) {
			$interval = getBase1024Interval($interval, $this->m_minY[$side], $this->m_maxY[$side]);
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

		// calculate interval, if right side use B or Bps
		if ($rightBase1024) {
			$interval_other_side = getBase1024Interval($interval_other_side, $this->m_minY[$other_side],
				$this->m_maxY[$other_side]);
		}

		// save original min and max items values
		foreach ($sides as $graphSide) {
			$minY[$graphSide] = $this->m_minY[$graphSide];
			$maxY[$graphSide] = $this->m_maxY[$graphSide];
		}

		if (!isset($minY[$side])) {
			$minY[$side] = 0;
		}
		if (!isset($maxY[$side])) {
			$maxY[$side] = 0;
		}

		// correcting MIN & MAX
		$this->m_minY[$side] = bcmul(bcfloor(bcdiv($this->m_minY[$side], $interval)), $interval);
		$this->m_maxY[$side] = bcmul(bcceil(bcdiv($this->m_maxY[$side], $interval)), $interval);
		$this->m_minY[$other_side] = bcmul(bcfloor(bcdiv($this->m_minY[$other_side], $interval_other_side)), $interval_other_side);
		$this->m_maxY[$other_side] = bcmul(bcceil(bcdiv($this->m_maxY[$other_side], $interval_other_side)), $interval_other_side);

		// add intervals so min/max Y wouldn't be at the top
		foreach ($sides as $graphSide) {
			if ($graphSide == $side) {
				$tmpInterval = $interval;
			}
			else {
				$tmpInterval = $interval_other_side;
			}

			if (bccomp($this->m_minY[$graphSide], $minY[$side]) == 0
					&& $this->m_minY[$graphSide] != null && $this->m_minY[$graphSide] != 0) {
				$this->m_minY[$graphSide] = bcsub($this->m_minY[$graphSide], $tmpInterval);
			}

			if (bccomp($this->m_maxY[$graphSide], $maxY[$graphSide]) == 0
					&& $this->m_maxY[$graphSide] != null && $this->m_maxY[$graphSide] != 0) {
				$this->m_maxY[$graphSide] = bcadd($this->m_maxY[$graphSide], $tmpInterval);
			}
		}

		// calculate interval count for main and other side
		$this->gridLinesCount[$side] = bcceil(bcdiv(bcsub($this->m_maxY[$side], $this->m_minY[$side]), $interval));
		$this->gridLinesCount[$other_side] = bcceil(bcdiv(bcsub($this->m_maxY[$other_side], $this->m_minY[$other_side]), $interval_other_side));

		$this->m_maxY[$side] = bcadd($this->m_minY[$side], bcmul($interval, $this->gridLinesCount[$side]));
		$this->gridStep[$side] = $interval;

		if (isset($this->axis_valuetype[$other_side])) {
			// other side correction
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

			// calculate interval, if right side use B or Bps
			if (isset($rightBase1024)) {
				$interval = getBase1024Interval($interval, $this->m_minY[$side], $this->m_maxY[$side]);
				// recorrecting MIN & MAX
				$this->m_minY[$other_side] = bcmul(bcfloor(bcdiv($this->m_minY[$other_side], $interval)), $interval);
				$this->m_maxY[$other_side] = bcmul(bcceil(bcdiv($this->m_maxY[$other_side], $interval)), $interval);
			}

			$this->gridLinesCount[$other_side] = $this->gridLinesCount[$side];
			$this->m_maxY[$other_side] = bcadd($this->m_minY[$other_side], bcmul($interval, $this->gridLinesCount[$other_side]));
			$this->gridStep[$other_side] = $interval;
		}

		foreach ($sides as $graphSide) {
			if (!isset($this->axis_valuetype[$graphSide])) {
				continue;
			}

			if ($this->type == GRAPH_TYPE_STACKED) {
				$this->m_minY[$graphSide] = bccomp($tmp_minY[GRAPH_YAXIS_SIDE_LEFT], 0) == -1 ? $tmp_minY[GRAPH_YAXIS_SIDE_LEFT] : 0;
			}

			if ($this->ymax_type == GRAPH_YAXIS_TYPE_FIXED) {
				$this->m_maxY[$graphSide] = $this->yaxismax;
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

			$this->validateMinMax($this->m_minY[$graphSide], $this->m_maxY[$graphSide]);
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
		$gbColor = $this->getColor($this->graphtheme['gridbordercolor'], 0);

		dashedRectangle(
			$this->im,
			$this->shiftXleft + $this->shiftXCaption - 1,
			$this->shiftY - 1,
			$this->sizeX + $this->shiftXleft + $this->shiftXCaption,
			$this->sizeY + $this->shiftY + 1,
			$this->getColor($this->graphtheme['gridcolor'], 0)
		);

		if ($this->yaxisleft) {
			zbx_imageline(
				$this->im,
				$this->shiftXleft + $this->shiftXCaption - 1,
				$this->shiftY - 5,
				$this->shiftXleft + $this->shiftXCaption - 1,
				$this->sizeY + $this->shiftY + 4,
				$gbColor
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

			/* draw left axis triangle */
			zbx_imageline($this->im, $this->shiftXleft + $this->shiftXCaption - 4, $this->shiftY - 5,
					$this->shiftXleft + $this->shiftXCaption + 2, $this->shiftY - 5,
					$gbColor);
			zbx_imagealine($this->im, $this->shiftXleft + $this->shiftXCaption - 4, $this->shiftY - 5,
					$this->shiftXleft + $this->shiftXCaption - 1, $this->shiftY - 10,
					$gbColor);
			zbx_imagealine($this->im, $this->shiftXleft + $this->shiftXCaption + 2, $this->shiftY - 5,
					$this->shiftXleft + $this->shiftXCaption - 1, $this->shiftY - 10,
					$gbColor);
		}

		if ($this->yaxisright) {
			zbx_imageline(
				$this->im,
				$this->sizeX + $this->shiftXleft + $this->shiftXCaption,
				$this->shiftY - 5,
				$this->sizeX + $this->shiftXleft + $this->shiftXCaption,
				$this->sizeY + $this->shiftY + 4,
				$gbColor
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

			/* draw right axis triangle */
			zbx_imageline($this->im, $this->sizeX + $this->shiftXleft + $this->shiftXCaption - 3, $this->shiftY - 5,
				$this->sizeX + $this->shiftXleft + $this->shiftXCaption + 3, $this->shiftY - 5,
				$gbColor);
			zbx_imagealine($this->im, $this->sizeX + $this->shiftXleft + $this->shiftXCaption + 3, $this->shiftY - 5,
				$this->sizeX + $this->shiftXleft + $this->shiftXCaption, $this->shiftY - 10,
				$gbColor);
			zbx_imagealine($this->im, $this->sizeX + $this->shiftXleft + $this->shiftXCaption - 3, $this->shiftY - 5,
				$this->sizeX + $this->shiftXleft + $this->shiftXCaption, $this->shiftY - 10,
				$gbColor);
		}

		zbx_imageline(
			$this->im,
			$this->shiftXleft + $this->shiftXCaption - 4,
			$this->sizeY + $this->shiftY + 1,
			$this->sizeX + $this->shiftXleft + $this->shiftXCaption + 5,
			$this->sizeY + $this->shiftY + 1,
			$gbColor
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

		/* draw X axis triangle */
		zbx_imageline($this->im, $this->sizeX + $this->shiftXleft + $this->shiftXCaption + 5, $this->sizeY + $this->shiftY - 2,
			$this->sizeX + $this->shiftXleft + $this->shiftXCaption + 5, $this->sizeY + $this->shiftY + 4,
			$gbColor);
		zbx_imagealine($this->im, $this->sizeX + $this->shiftXleft + $this->shiftXCaption + 5, $this->sizeY + $this->shiftY + 4,
			$this->sizeX + $this->shiftXleft + $this->shiftXCaption + 10, $this->sizeY + $this->shiftY + 1,
			$gbColor);
		zbx_imagealine($this->im, $this->sizeX + $this->shiftXleft + $this->shiftXCaption + 10, $this->sizeY + $this->shiftY + 1,
			$this->sizeX + $this->shiftXleft + $this->shiftXCaption + 5, $this->sizeY + $this->shiftY - 2,
			$gbColor);
	}

	/**
	 * Draws Y scale grid.
	 */
	private function drawHorizontalGrid() {
		$yAxis = $this->yaxisleft ? GRAPH_YAXIS_SIDE_LEFT : GRAPH_YAXIS_SIDE_RIGHT;

		$stepY = $this->gridStepX[$yAxis];

		if ($this->gridLinesCount[$yAxis] < round($this->sizeY / $this->gridPixels)) {
			$stepY = $stepY / 2;
		}

		$xLeft = $this->shiftXleft;
		$xRight = $this->shiftXleft + $this->sizeX;
		$lineColor = $this->getColor($this->graphtheme['maingridcolor'], 0);

		for ($y = $this->shiftY + $this->sizeY - $stepY; $y > $this->shiftY; $y -= $stepY) {
			dashedLine($this->im, $xLeft, $y, $xRight, $y, $lineColor);
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

	private function drawSides() {
		if (isset($this->axis_valuetype[GRAPH_YAXIS_SIDE_RIGHT])
				&& ($this->yaxisright != 0 || $this->skipRightScale != 1)) {
			$sides[] = GRAPH_YAXIS_SIDE_RIGHT;
		}

		if (((isset($this->axis_valuetype[GRAPH_YAXIS_SIDE_LEFT]))
				&& ($this->yaxisleft != 0 || $this->skipLeftScale != 1)) || !isset($sides)) {
			$sides[] = GRAPH_YAXIS_SIDE_LEFT;
		}

		foreach ($sides as $side) {
			$minY = $this->m_minY[$side];
			$maxY = $this->m_maxY[$side];
			$units = null;
			$unitsLong = null;
			$byteStep = false;

			for ($item = 0; $item < $this->num; $item++) {
				if ($this->items[$item]['axisside'] == $side) {
					// check if items use B or Bps units
					if ($this->items[$item]['units'] == 'B' || $this->items[$item]['units'] == 'Bps') {
						$byteStep = true;
					}
					if (is_null($units)) {
						$units = $this->items[$item]['units'];
					}
					elseif ($this->items[$item]['units'] != $units) {
						$units = '';
					}
				}
			}

			if (is_null($units) || $units === false) {
				$units = '';
			}
			else {
				for ($item = 0; $item < $this->num; $item++) {
					if ($this->items[$item]['axisside'] == $side && !empty($this->items[$item]['unitsLong'])) {
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

				$tmpX = $side == GRAPH_YAXIS_SIDE_LEFT ? $dims['width'] + 8 : $this->fullSizeX - $dims['width'];

				imageText(
					$this->im,
					9,
					90,
					$tmpX,
					$tmpY,
					$this->getColor($this->graphtheme['textcolor'], 0),
					$unitsLong
				);
			}

			$step = $this->gridStep[$side];
			$hstr_count = $this->gridLinesCount[$side];

			// ignore milliseconds if  -1 <= maxY => 1 or -1 <= minY => 1
			$ignoreMillisec = (bccomp($maxY, -1) <= 0 || bccomp($maxY, 1) >= 0
					|| bccomp($minY, -1) <= 0 || bccomp($minY, 1) >= 0);

			$newPow = false;
			if ($byteStep) {
				$maxYPow = convertToBase1024($maxY, 1024);
				$minYPow = convertToBase1024($minY, 1024);
				$powStep = 1024;
			} else {
				$maxYPow = convertToBase1024($maxY);
				$minYPow = convertToBase1024($minY);
				$powStep = 1000;
			}

			if (abs($maxYPow['pow']) > abs($minYPow['pow']) && $maxYPow['value'] != 0) {
				$newPow = $maxYPow['pow'];
				if (abs(bcdiv($minYPow['value'], bcpow($powStep, $maxYPow['pow']))) > 1000) {
					$newPow = $minYPow['pow'];
				}
			}
			if (abs($maxYPow['pow']) < abs($minYPow['pow']) && $minYPow['value'] != 0) {
				$newPow = $minYPow['pow'];
				if (abs(bcdiv($maxYPow['value'], bcpow($powStep, $minYPow['pow']))) > 1000) {
					$newPow = $maxYPow['pow'];
				}
			}
			if ($maxYPow['pow'] == $minYPow['pow']) {
				$newPow = $maxYPow['pow'];
			}

			$maxLength = false;
			// get all values in y-axis if units != 's'
			if ($units != 's') {
				$calcValues = array();
				for ($i = 0; $i <= $hstr_count; $i++) {
					$hstr_count = ($hstr_count == 0) ? 1 : $hstr_count;

					$val = bcadd(bcmul($i, $step), $minY);

					if (bccomp(bcadd($val, bcdiv($step,2)), $maxY) == 1) {
						continue;
					}

					$calcValues[] = convert_units(array(
						'value' => $val,
						'convert' => ITEM_CONVERT_NO_UNITS,
						'byteStep' => $byteStep,
						'pow' => $newPow
					));
				}

				$calcValues[] = convert_units(array(
					'value' => $maxY,
					'convert' => ITEM_CONVERT_NO_UNITS,
					'byteStep' => $byteStep,
					'pow' => $newPow
				));

				$maxLength = calcMaxLengthAfterDot($calcValues);
			}

			for ($i = 0; $i <= $hstr_count; $i++) {
				$hstr_count = ($hstr_count == 0) ? 1 : $hstr_count;

				$val = bcadd(bcmul($i, $step), $minY);

				if (bccomp(bcadd($val, bcdiv($step, 2)), $maxY) == 1) {
					continue;
				}

				$str = convert_units(array(
					'value' => $val,
					'units' => $units,
					'convert' => ITEM_CONVERT_NO_UNITS,
					'byteStep' => $byteStep,
					'pow' => $newPow,
					'ignoreMillisec' => $ignoreMillisec,
					'length' => $maxLength
				));

				if ($side == GRAPH_YAXIS_SIDE_LEFT) {
					$dims = imageTextSize(8, 0, $str);
					$posX = $this->shiftXleft - $dims['width'] - 9;
				}
				else {
					$posX = $this->sizeX + $this->shiftXleft + 12;
				}

				// marker Y coordinate
				$posY = $this->sizeY + $this->shiftY - $this->gridStepX[$side] * $i + 4;

				imageText(
					$this->im,
					8,
					0,
					$posX,
					$posY,
					$this->getColor($this->graphtheme['textcolor'], 0),
					$str
				);
			}

			$str = convert_units(array(
				'value' => $maxY,
				'units' => $units,
				'convert' => ITEM_CONVERT_NO_UNITS,
				'byteStep' => $byteStep,
				'pow' => $newPow,
				'ignoreMillisec' => $ignoreMillisec,
				'length' => $maxLength
			));

			if ($side == GRAPH_YAXIS_SIDE_LEFT) {
				$dims = imageTextSize(8, 0, $str);
				$posX = $this->shiftXleft - $dims['width'] - 9;
				$color = $this->getColor(GRAPH_ZERO_LINE_COLOR_LEFT);
			}
			else {
				$posX = $this->sizeX + $this->shiftXleft + 12;
				$color = $this->getColor(GRAPH_ZERO_LINE_COLOR_RIGHT);
			}

			imageText(
				$this->im,
				8,
				0,
				$posX,
				$this->shiftY + 4,
				$this->getColor($this->graphtheme['textcolor'], 0),
				$str
			);

			if ($this->zero[$side] != $this->sizeY + $this->shiftY && $this->zero[$side] != $this->shiftY) {
				zbx_imageline(
					$this->im,
					$this->shiftXleft,
					$this->zero[$side],
					$this->shiftXleft + $this->sizeX,
					$this->zero[$side],
					$color
				);
			}
		}
	}

	protected function drawWorkPeriod() {
		imagefilledrectangle($this->im,
			$this->shiftXleft + 1,
			$this->shiftY,
			$this->sizeX + $this->shiftXleft-1, // -2 border
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
			$this->sizeX + $this->shiftXleft - 1, // -1 border
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
				$x2 - 1, // -1 border
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
				zbx_imageline(
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

		// draw item legend
		$legend = new CImageTextTable($this->im, $leftXShift - 5, $this->sizeY + $this->shiftY + $this->legendOffsetY);
		$legend->color = $this->getColor($this->graphtheme['textcolor'], 0);
		$legend->rowheight = 14;
		$legend->fontsize = 9;

		// item legend table header
		$row = array(
			array('text' => '', 'marginRight' => 5),
			array('text' => ''),
			array('text' => ''),
			array('text' => _('last'), 'align' => 1, 'fontsize' => 9),
			array('text' => _('min'), 'align' => 1, 'fontsize' => 9),
			array('text' => _('avg'), 'align' => 1, 'fontsize' => 9),
			array('text' => _('max'), 'align' => 1, 'fontsize' => 9)
		);

		$legend->addRow($row);
		$rowNum = $legend->getNumRows();

		$i = ($this->type == GRAPH_TYPE_STACKED) ? $this->num - 1 : 0;
		while ($i >= 0 && $i < $this->num) {
			$color = $this->getColor($this->items[$i]['color'], GRAPH_STACKED_ALFA);
			switch ($this->items[$i]['calc_fnc']) {
				case CALC_FNC_MIN:
					$fncRealName = _('min');
					break;
				case CALC_FNC_MAX:
					$fncRealName = _('max');
					break;
				case CALC_FNC_ALL:
					$fncRealName = _('all');
					break;
				case CALC_FNC_AVG:
				default:
					$fncRealName = _('avg');
			}

			$data = &$this->data[$this->items[$i]['itemid']][$this->items[$i]['calc_type']];

			// draw color square
			if (function_exists('imagecolorexactalpha') && function_exists('imagecreatetruecolor') && @imagecreatetruecolor(1, 1)) {
				$colorSquare = imagecreatetruecolor(11, 11);
			}
			else {
				$colorSquare = imagecreate(11, 11);
			}

			imagefill($colorSquare, 0, 0, $this->getColor($this->graphtheme['backgroundcolor'], 0));
			imagefilledrectangle($colorSquare, 0, 0, 10, 10, $color);
			imagerectangle($colorSquare, 0, 0, 10, 10, $this->getColor('Black'));

			// caption
			$itemCaption = $this->itemsHost
				? $this->items[$i]['name_expanded']
				: $this->items[$i]['hostname'].NAME_DELIMITER.$this->items[$i]['name_expanded'];

			// draw legend of an item with data
			if (isset($data) && isset($data['min'])) {
				if ($this->items[$i]['axisside'] == GRAPH_YAXIS_SIDE_LEFT) {
					$units['left'] = $this->items[$i]['units'];
				}
				else {
					$units['right'] = $this->items[$i]['units'];
				}

				$legend->addCell($rowNum, array('image' => $colorSquare, 'marginRight' => 5));
				$legend->addCell($rowNum, array('text' => $itemCaption));
				$legend->addCell($rowNum, array('text' => '['.$fncRealName.']'));
				$legend->addCell($rowNum, array(
					'text' => convert_units(array(
						'value' => $this->getLastValue($i),
						'units' => $this->items[$i]['units'],
						'convert' => ITEM_CONVERT_NO_UNITS
					)),
					'align' => 2
				));
				$legend->addCell($rowNum, array(
					'text' => convert_units(array(
						'value' => min($data['min']),
						'units' => $this->items[$i]['units'],
						'convert' => ITEM_CONVERT_NO_UNITS
					)),
					'align' => 2
				));
				$legend->addCell($rowNum, array(
					'text' => convert_units(array(
						'value' => $data['avg_orig'],
						'units' => $this->items[$i]['units'],
						'convert' => ITEM_CONVERT_NO_UNITS
					)),
					'align' => 2
				));
				$legend->addCell($rowNum, array(
					'text' => convert_units(array(
						'value' => max($data['max']),
						'units' => $this->items[$i]['units'],
						'convert' => ITEM_CONVERT_NO_UNITS
					)),
					'align' => 2
				));
			}
			// draw legend of an item without data
			else {
				$legend->addCell($rowNum, array('image' => $colorSquare, 'marginRight' => 5));
				$legend->addCell($rowNum, array('text' => $itemCaption));
				$legend->addCell($rowNum, array('text' => '['._('no data').']'));
			}

			$rowNum++;

			// legends for stacked graphs are written in reverse order so that the order of items
			// matches the order of lines on the graphs
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
			$this->sizeY + $this->shiftY + 14 * $rowNum + $this->legendOffsetY
		);
		$legend->color = $this->getColor($this->graphtheme['textcolor'], 0);
		$legend->rowheight = 14;
		$legend->fontsize = 9;

		// draw percentile
		if ($this->type == GRAPH_TYPE_NORMAL) {
			foreach ($this->percentile as $side => $percentile) {
				if ($percentile['percent'] > 0 && $percentile['value']) {
					$percentile['percent'] = (float) $percentile['percent'];
					$convertedUnit = convert_units(array(
						'value' => $percentile['value'],
						'units' => $units[$side]
					));
					$legend->addCell($rowNum, array(
						'text' => $percentile['percent'].'th percentile: '.$convertedUnit.' ('.$side.')',
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
							$leftXShift + 5, $this->sizeY + $this->shiftY + 14 * $rowNum + $this->legendOffsetY,
							$leftXShift - 5, $this->sizeY + $this->shiftY + 14 * $rowNum + $this->legendOffsetY,
							$leftXShift, $this->sizeY + $this->shiftY + 14 * $rowNum + $this->legendOffsetY - 10
						),
						3,
						$this->getColor($color)
					);

					imagepolygon(
						$this->im,
						array(
							$leftXShift + 5, $this->sizeY + $this->shiftY + 14 * $rowNum + $this->legendOffsetY,
							$leftXShift - 5, $this->sizeY + $this->shiftY + 14 * $rowNum + $this->legendOffsetY,
							$leftXShift, $this->sizeY + $this->shiftY + 14 * $rowNum + $this->legendOffsetY - 10
						),
						3,
						$this->getColor('Black No Alpha')
					);
					$rowNum++;
				}
			}
		}

		$legend->draw();

		$legend = new CImageTextTable(
			$this->im,
			$leftXShift + 10,
			$this->sizeY + $this->shiftY + 14 * $rowNum + $this->legendOffsetY + 5
		);
		$legend->color = $this->getColor($this->graphtheme['textcolor'], 0);
		$legend->rowheight = 14;
		$legend->fontsize = 9;

		// draw triggers
		foreach ($this->triggers as $trigger) {
			imagefilledellipse(
				$this->im,
				$leftXShift,
				$this->sizeY + $this->shiftY + 14 * $rowNum + $this->legendOffsetY,
				10,
				10,
				$this->getColor($trigger['color'])
			);

			imageellipse(
				$this->im,
				$leftXShift,
				$this->sizeY + $this->shiftY + 14 * $rowNum + $this->legendOffsetY,
				10,
				10,
				$this->getColor('Black No Alpha')
			);

			$legend->addRow(array(
				array('text' => $trigger['description']),
				array('text' => $trigger['constant'])
			));
			$rowNum++;
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

			// don't use break, avg must be drawn in this statement
			case CALC_FNC_AVG:

			// don't use break, avg must be drawn in this statement
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
						zbx_imagealine($this->im, $x1, $y1max, $x2, $y2max, $max_color, LINE_TYPE_BOLD);
					}

					if (!$y1n || !$y2n) {
						zbx_imagealine($this->im, $x1, $y1min, $x2, $y2min, $min_color, LINE_TYPE_BOLD);
					}
				}

				zbx_imagealine($this->im, $x1, $y1, $x2, $y2, $avg_color, LINE_TYPE_BOLD);
				break;
			case GRAPH_ITEM_DRAWTYPE_LINE:
				if ($calc_fnc == CALC_FNC_ALL) {
					imagefilledpolygon($this->im, $a, 4, $minmax_color);
					if (!$y1x || !$y2x) {
						zbx_imagealine($this->im, $x1, $y1max, $x2, $y2max, $max_color);
					}
					if (!$y1n || !$y2n) {
						zbx_imagealine($this->im, $x1, $y1min, $x2, $y2min, $min_color);
					}
				}

				zbx_imagealine($this->im, $x1, $y1, $x2, $y2, $avg_color);
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
					zbx_imageline($this->im, $x1, $y1, $x2, $y2, IMG_COLOR_STYLED);
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

		if (isset($this->axis_valuetype[GRAPH_YAXIS_SIDE_RIGHT])) {
			$sides[] = GRAPH_YAXIS_SIDE_RIGHT;
		}

		if (isset($this->axis_valuetype[GRAPH_YAXIS_SIDE_LEFT]) || !isset($sides)) {
			$sides[] = GRAPH_YAXIS_SIDE_LEFT;
		}

		foreach ($sides as $graphSide) {
			$this->m_minY[$graphSide] = $this->calculateMinY($graphSide);
			$this->m_maxY[$graphSide] = $this->calculateMaxY($graphSide);

			if ($this->m_minY[$graphSide] === null) {
				$this->m_minY[$graphSide] = 0;
			}
			if ($this->m_maxY[$graphSide] === null) {
				$this->m_maxY[$graphSide] = 1;
			}

			if ($this->m_minY[$graphSide] == $this->m_maxY[$graphSide]) {
				if ($this->graphOrientation[$graphSide] == '-') {
					$this->m_maxY[$graphSide] = 0;
				}
				elseif ($this->m_minY[$graphSide] == 0) {
					$this->m_maxY[$graphSide] = 1;
				}
				else {
					$this->m_minY[$graphSide] = 0;
				}
			}
			elseif ($this->m_minY[$graphSide] > $this->m_maxY[$graphSide]) {
				if ($this->graphOrientation[$graphSide] == '-') {
					$this->m_minY[$graphSide] = bcmul($this->m_maxY[$graphSide], 0.2);
				}
				else {
					$this->m_minY[$graphSide] = 0;
				}
			}

			// If max Y-scale bigger min Y-scale only for 10% or less, then we don't allow Y-scale duplicate
			if ($this->m_maxY[$graphSide] && $this->m_minY[$graphSide]) {
				if ($this->m_minY[$graphSide] < 0) {
					$absMinY = bcmul($this->m_minY[$graphSide], '-1');
				}
				else {
					$absMinY = $this->m_minY[$graphSide];
				}
				if ($this->m_maxY[$graphSide] < 0) {
					$absMaxY = bcmul($this->m_maxY[$graphSide], '-1');
				}
				else {
					$absMaxY = $this->m_maxY[$graphSide];
				}

				if ($absMaxY < $absMinY) {
					$oldAbMaxY = $absMaxY;
					$absMaxY = $absMinY;
					$absMinY = $oldAbMaxY;
				}

				if (bcdiv((bcsub($absMaxY, $absMinY)), $absMaxY) <= 0.1) {
					if ($this->m_minY[$graphSide] > 0) {
						$this->m_minY[$graphSide] = bcmul($this->m_minY[$graphSide], 0.95);
					}
					else {
						$this->m_minY[$graphSide] = bcmul($this->m_minY[$graphSide], 1.05);
					}
					if ($this->m_maxY[$graphSide] > 0) {
						$this->m_maxY[$graphSide] = bcmul($this->m_maxY[$graphSide], 1.05);
					}
					else {
						$this->m_maxY[$graphSide] = bcmul($this->m_maxY[$graphSide], 0.95);
					}
				}
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
		$this->drawHorizontalGrid();
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

		$this->drawSides();

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
