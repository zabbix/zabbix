<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
		$this->triggers = [];
		$this->ymin_type = GRAPH_YAXIS_TYPE_CALCULATED;
		$this->ymax_type = GRAPH_YAXIS_TYPE_CALCULATED;
		$this->yaxisright = 0;
		$this->yaxisleft = 0;
		$this->skipLeftScale = 0; // in case if left axis should be drawn but doesn't contain any data
		$this->skipRightScale = 0; // in case if right axis should be drawn but doesn't contain any data
		$this->ymin_itemid = 0;
		$this->ymax_itemid = 0;
		$this->legendOffsetY = 90;
		$this->percentile = [
			'left' => [
				'percent' => 0, // draw percentage line
				'value' => 0 // calculated percentage value left y axis
			],
			'right' => [
				'percent' => 0, // draw percentage line
				'value' => 0 // calculated percentage value right y axis
			]
		];
		$this->m_showWorkPeriod = 1;
		$this->m_showTriggers = 1;
		$this->zero = [];
		$this->graphOrientation = [
			GRAPH_YAXIS_SIDE_LEFT => '',
			GRAPH_YAXIS_SIDE_RIGHT => ''
		];
		$this->grid = []; // vertical & horizontal grids params
		$this->gridLinesCount = []; // How many grids to draw
		$this->gridStep = []; // grid step
		$this->gridPixels = 30; // optimal grid size
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
		$this->sizeX = $this->fullSizeX - $this->shiftXleft - $this->shiftXright - 1;
		$this->sizeY = $this->fullSizeY - $this->shiftY - $this->legendOffsetY;

		if ($this->drawLegend) {
			$this->sizeY -= 14 * ($this->num + 1 + (($this->fullSizeY < 120) ? 0 : count($this->triggers))) + 8;
		}

		// if graph height is big enough, we reserve space for percent line legend
		if ($this->fullSizeY >= ZBX_GRAPH_LEGEND_HEIGHT) {
			foreach ($this->percentile as $percentile) {
				if ($percentile['percent'] > 0 && $percentile['value']) {
					$this->sizeY -= 14;
				}
			}
		}
	}

	public function showWorkPeriod($value) {
		$this->m_showWorkPeriod = ($value == 1) ? 1 : 0;
	}

	public function showTriggers($value) {
		$this->m_showTriggers = ($value == 1) ? 1 : 0;
	}

	public function addGraphItem($itemid, $axis = GRAPH_YAXIS_SIDE_DEFAULT, $calc_fnc = CALC_FNC_AVG, $color = null, $drawtype = null, $type = null) {
		if ($this->type == GRAPH_TYPE_STACKED) {
			$drawtype = GRAPH_ITEM_DRAWTYPE_FILLED_REGION;
		}

		// TODO: graphs shouldn't retrieve items and resolve macros themselves
		// all of the data must be passed as parameters
		$items = CMacrosResolverHelper::resolveItemNames([get_item_by_itemid($itemid)]);
		$item = reset($items);

		$item['name'] = $item['name_expanded'];

		$this->graph_items[$this->num] = $item;

		$parser = new CItemDelayFlexParser($item['delay_flex']);
		$this->graph_items[$this->num]['delay'] = getItemDelay($item['delay'], $parser->getFlexibleIntervals());
		$this->graph_items[$this->num]['intervals'] = $parser->getIntervals();

		if (strpos($item['units'], ',') === false) {
			$this->graph_items[$this->num]['unitsLong'] = '';
		}
		else {
			list($this->graph_items[$this->num]['units'], $this->graph_items[$this->num]['unitsLong']) = explode(',', $item['units']);
		}

		$host = get_host_by_hostid($item['hostid']);

		$this->graph_items[$this->num]['host'] = $host['host'];
		$this->graph_items[$this->num]['hostname'] = $host['name'];
		$this->graph_items[$this->num]['color'] = is_null($color) ? 'Dark Green' : $color;
		$this->graph_items[$this->num]['drawtype'] = is_null($drawtype) ? GRAPH_ITEM_DRAWTYPE_LINE : $drawtype;
		$this->graph_items[$this->num]['axisside'] = is_null($axis) ? GRAPH_YAXIS_SIDE_DEFAULT : $axis;
		$this->graph_items[$this->num]['calc_fnc'] = is_null($calc_fnc) ? CALC_FNC_AVG : $calc_fnc;
		$this->graph_items[$this->num]['calc_type'] = is_null($type) ? GRAPH_ITEM_SIMPLE : $type;

		if ($this->graph_items[$this->num]['axisside'] == GRAPH_YAXIS_SIDE_LEFT) {
			$this->yaxisleft = 1;
		}

		if ($this->graph_items[$this->num]['axisside'] == GRAPH_YAXIS_SIDE_RIGHT) {
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
		$this->data = [];
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
			$item = get_item_by_itemid($this->graph_items[$i]['itemid']);

			if ($this->itemsHost === null) {
				$this->itemsHost = $item['hostid'];
			}
			elseif ($this->itemsHost != $item['hostid']) {
				$this->itemsHost = false;
			}

			if (!isset($this->axis_valuetype[$this->graph_items[$i]['axisside']])) {
				$this->axis_valuetype[$this->graph_items[$i]['axisside']] = $item['value_type'];
			}
			elseif ($this->axis_valuetype[$this->graph_items[$i]['axisside']] != $item['value_type']) {
				$this->axis_valuetype[$this->graph_items[$i]['axisside']] = ITEM_VALUE_TYPE_FLOAT;
			}

			$type = $this->graph_items[$i]['calc_type'];
			$from_time = $this->from_time;
			$to_time = $this->to_time;
			$calc_field = 'round('.$x.'*'.zbx_sql_mod(zbx_dbcast_2bigint('clock').'+'.$z, $p).'/('.$p.'),0)'; // required for 'group by' support of Oracle

			// override item history setting with housekeeping settings
			if ($config['hk_history_global']) {
				$item['history'] = $config['hk_history'];
			}

			$trendsEnabled = $config['hk_trends_global'] ? ($config['hk_trends'] > 0) : ($item['trends'] > 0);

			if (!$trendsEnabled
					|| (($item['history'] * SEC_PER_DAY) > (time() - ($this->from_time + $this->period / 2))
						&& ($this->period / $this->sizeX) <= (ZBX_MAX_TREND_DIFF / ZBX_GRAPH_MAX_SKIP_CELL))) {
				$this->dataFrom = 'history';

				$sql_select = 'COUNT(*) AS count,AVG(value) AS avg,MIN(value) AS min,MAX(value) AS max';
				$sql_from = ($item['value_type'] == ITEM_VALUE_TYPE_UINT64) ? 'history_uint' : 'history';
			}
			else {
				$this->dataFrom = 'trends';

				if (!$this->hasSchedulingIntervals($this->graph_items[$i]['intervals']) || $this->graph_items[$i]['delay'] != 0) {
					$this->graph_items[$i]['delay'] = max($this->graph_items[$i]['delay'], SEC_PER_HOUR);
				}

				$sql_select = 'SUM(num) AS count,AVG(value_avg) AS avg,MIN(value_min) AS min,MAX(value_max) AS max';
				$sql_from = ($item['value_type'] == ITEM_VALUE_TYPE_UINT64) ? 'trends_uint' : 'trends';
			}

			if (!isset($this->data[$this->graph_items[$i]['itemid']])) {
				$this->data[$this->graph_items[$i]['itemid']] = [];
			}

			if (!isset($this->data[$this->graph_items[$i]['itemid']][$type])) {
				$this->data[$this->graph_items[$i]['itemid']][$type] = [];
			}

			$curr_data = &$this->data[$this->graph_items[$i]['itemid']][$type];

			$curr_data['count'] = null;
			$curr_data['min'] = null;
			$curr_data['max'] = null;
			$curr_data['avg'] = null;
			$curr_data['clock'] = null;

			$result = DBselect(
				'SELECT itemid,'.$calc_field.' AS i,'.$sql_select.',MAX(clock) AS clock'.
				' FROM '.$sql_from.
				' WHERE itemid='.zbx_dbstr($this->graph_items[$i]['itemid']).
					' AND clock>='.zbx_dbstr($from_time).
					' AND clock<='.zbx_dbstr($to_time).
				' GROUP BY itemid,'.$calc_field
			);
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
			$this->setGraphOrientation($loc_min, $this->graph_items[$i]['axisside']);
			unset($row);

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

					foreach (['clock', 'min', 'max', 'avg'] as $var_name) {
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
					foreach (['clock', 'min', 'max', 'avg'] as $var_name) {
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
				$curr_data = &$this->data[$this->graph_items[$i]['itemid']][$this->graph_items[$i]['calc_type']];

				if (!isset($curr_data)) {
					continue;
				}

				for ($j = $i - 1; $j >= 0; $j--) {
					if ($this->graph_items[$j]['axisside'] != $this->graph_items[$i]['axisside']) {
						continue;
					}

					$prev_data = &$this->data[$this->graph_items[$j]['itemid']][$this->graph_items[$j]['calc_type']];

					if (!isset($prev_data)) {
						continue;
					}

					for ($ci = 0; $ci < $this->sizeX; $ci++) {
						foreach (['min', 'max', 'avg'] as $var_name) {
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
		$this->triggers = [];
		if ($this->m_showTriggers != 1) {
			return;
		}

		$max = 3;
		$cnt = 0;

		foreach ($this->graph_items as $inum => $item) {
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

				$minY = $this->m_minY[$this->graph_items[$inum]['axisside']];
				$maxY = $this->m_maxY[$this->graph_items[$inum]['axisside']];

				$this->triggers[] = [
					'skipdraw' => ($val <= $minY || $val >= $maxY),
					'y' => $this->sizeY - (($val - $minY) / ($maxY - $minY)) * $this->sizeY + $this->shiftY,
					'color' => getSeverityColor($trigger['priority']),
					'description' => _('Trigger').NAME_DELIMITER.CMacrosResolverHelper::resolveTriggerName($trigger),
					'constant' => '['.$arr[2].' '.$arr[3].$arr[4].']'
				];
				++$cnt;
			}
		}
	}

	// calculates percentages for left & right Y axis
	protected function calcPercentile() {
		if ($this->type != GRAPH_TYPE_NORMAL) {
			return ;
		}

		$values = [
			'left' => [],
			'right'=> []
		];

		$maxX = $this->sizeX;

		// for each metric
		for ($item = 0; $item < $this->num; $item++) {
			$data = &$this->data[$this->graph_items[$item]['itemid']][$this->graph_items[$item]['calc_type']];

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

				switch ($this->graph_items[$item]['calc_fnc']) {
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

				if ($this->graph_items[$item]['axisside'] == GRAPH_YAXIS_SIDE_LEFT) {
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
			$history = Manager::History()->getLast([$item]);
			if (isset($history[$item['itemid']])) {
				return $history[$item['itemid']][0]['value'];
			}
		}

		$minY = null;
		for ($i = 0; $i < $this->num; $i++) {
			if ($this->graph_items[$i]['axisside'] != $side) {
				continue;
			}

			if (!isset($this->data[$this->graph_items[$i]['itemid']][GRAPH_ITEM_SIMPLE])) {
				continue;
			}

			$data = &$this->data[$this->graph_items[$i]['itemid']][GRAPH_ITEM_SIMPLE];

			if (!isset($data)) {
				continue;
			}

			$calc_fnc = $this->graph_items[$i]['calc_fnc'];

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
			$history = Manager::History()->getLast([$item]);
			if (isset($history[$item['itemid']])) {
				return $history[$item['itemid']][0]['value'];
			}
		}

		$maxY = null;
		for ($i = 0; $i < $this->num; $i++) {
			if ($this->graph_items[$i]['axisside'] != $side) {
				continue;
			}

			if (!isset($this->data[$this->graph_items[$i]['itemid']][GRAPH_ITEM_SIMPLE])) {
				continue;
			}

			$data = &$this->data[$this->graph_items[$i]['itemid']][GRAPH_ITEM_SIMPLE];

			if (!isset($data)) {
				continue;
			}

			$calc_fnc = $this->graph_items[$i]['calc_fnc'];

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
		$intervals = [];
		foreach ([1, 2, 3, 4] as $num) {
			$dec = pow(0.1, $num);
			foreach ([1, 2, 5] as $int) {
				$intervals[] = bcmul($int, $dec);
			}
		}

		// check if items use B or Bps units
		$leftBase1024 = false;
		$rightBase1024 = false;

		for ($item = 0; $item < $this->num; $item++) {
			if ($this->graph_items[$item]['units'] == 'B' || $this->graph_items[$item]['units'] == 'Bps') {
				if ($this->graph_items[$item]['axisside'] == GRAPH_YAXIS_SIDE_LEFT) {
					$leftBase1024 = true;
				}
				else {
					$rightBase1024 = true;
				}
			}
		}

		foreach ([0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18] as $num) {
			$dec = bcpow(10, $num);
			foreach ([1, 2, 5] as $int) {
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

	/********************************************************************************************************/
	// DRAW ELEMENTS
	/********************************************************************************************************/
	public function drawXYAxisScale() {
		if ($this->yaxisleft) {
			/* draw left Y axis */
			$this->addItem([
				new CLine(
					$this->shiftXleft + $this->shiftXCaption,
					$this->shiftY - 5,
					$this->shiftXleft + $this->shiftXCaption,
					$this->sizeY + $this->shiftY + 4,
					'#'.$this->graphtheme['gridbordercolor']
				),
				(new CPolygon([
						[$this->shiftXleft + $this->shiftXCaption - 3, $this->shiftY - 5],
						[$this->shiftXleft + $this->shiftXCaption + 3, $this->shiftY - 5],
						[$this->shiftXleft + $this->shiftXCaption, $this->shiftY - 10],
					]))
					->setWidth(1)
					->setStrokeColor('#'.$this->graphtheme['gridbordercolor'])
					->setFillColor('white')]
			);
		}
		else {
			$this->addItem(
				(new CLine(
					$this->shiftXleft + $this->shiftXCaption,
					$this->shiftY,
					$this->shiftXleft + $this->shiftXCaption,
					$this->sizeY + $this->shiftY,
					'#'.$this->graphtheme['gridcolor']))
				->setDashed()
			);
		}

		if ($this->yaxisright) {
			/* draw right Y axis */
			$this->addItem([
				new CLine(
					$this->sizeX + $this->shiftXleft + $this->shiftXCaption,
					$this->shiftY - 5,
					$this->sizeX + $this->shiftXleft + $this->shiftXCaption,
					$this->sizeY + $this->shiftY + 4,
					'#'.$this->graphtheme['gridbordercolor']
				),
				(new CPolygon([
						[$this->sizeX + $this->shiftXleft + $this->shiftXCaption - 3, $this->shiftY - 5],
						[$this->sizeX + $this->shiftXleft + $this->shiftXCaption + 3, $this->shiftY - 5],
						[$this->sizeX + $this->shiftXleft + $this->shiftXCaption, $this->shiftY - 10],
					]))
					->setWidth(1)
					->setStrokeColor('#'.$this->graphtheme['gridbordercolor'])
					->setFillColor('white')]
			);
		}
		else {
			$this->addItem(
				(new CLine(
					$this->sizeX + $this->shiftXleft + $this->shiftXCaption,
					$this->shiftY,
					$this->sizeX + $this->shiftXleft + $this->shiftXCaption,
					$this->sizeY + $this->shiftY,
					'#'.$this->graphtheme['gridcolor']))
				->setDashed()
			);
		}

		/* draw X axis */
		$this->addItem([
			new CLine(
				$this->shiftXleft + $this->shiftXCaption - 3,
				$this->sizeY + $this->shiftY + 1,
				$this->sizeX + $this->shiftXleft + $this->shiftXCaption + 5,
				$this->sizeY + $this->shiftY + 1,
				'#'.$this->graphtheme['gridbordercolor']),
			(new CPolygon([
					[$this->sizeX + $this->shiftXleft + $this->shiftXCaption + 5, $this->sizeY + $this->shiftY - 2],
					[$this->sizeX + $this->shiftXleft + $this->shiftXCaption + 5, $this->sizeY + $this->shiftY + 4],
					[$this->sizeX + $this->shiftXleft + $this->shiftXCaption + 10, $this->sizeY + $this->shiftY + 1]
				]))
				->setWidth(1)
				->setStrokeColor('#'.$this->graphtheme['gridbordercolor'])
				->setFillColor('white')]
		);
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

		for ($y = $this->shiftY + $this->sizeY - $stepY; $y > $this->shiftY; $y -= $stepY) {
			$this->addItem(
				(new CLine($xLeft, $y, $xRight, $y, '#'.$this->graphtheme['gridcolor']))
					->setDashed()
			);
		}
	}

	private function drawTimeGrid() {
		$time_format = (date('Y', $this->stime) != date('Y', $this->to_time))
			? DATE_FORMAT
			: DATE_TIME_FORMAT_SHORT;

		// Draw start date (and time) label.
		$this->drawStartEndTimePeriod($this->stime, $time_format, 0);

		$this->calculateTimeInterval();
		$this->drawDateTimeIntervals();

		// Draw end date (and time) label.
		$this->drawStartEndTimePeriod($this->to_time, $time_format, $this->sizeX);
	}

	/**
	 * Draw start or end date (and time) label.
	 *
	 * @param int $value		Unix time.
	 * @param sring $format		Date time format.
	 * @param int $position		Position on X axis.
	 */
	private function drawStartEndTimePeriod($value, $format, $position) {
		$point = zbx_date2str(_($format), $value);
		$element = imageTextSize(8, 90, $point);
		$this->addItem(
			(new CText(
				$this->shiftXleft + $position + round($element['width'] / 2),
				$this->sizeY + $this->shiftY + $element['height'] + 6,
				$point,
				'#'.$this->graphtheme['highlightcolor']))
			->setAngle(-90)
		);
	}

	/**
	 * Draw main period label in red color with 8px font size under X axis and a 2px dashed gray vertical line
	 * according to that label.
	 *
	 * @param int $value		Unix time.
	 * @param sring $format		Date time format.
	 * @param int $position		Position on X axis.
	 */
	private function drawMainPeriod($value, $format, $position) {
		$str = zbx_date2str($format, $value);
		$dims = imageTextSize(8, 90, $str);

		$this->addItem(
			(new CText(
				$this->shiftXleft + $position + round($dims['width'] / 2),
				$this->sizeY + $this->shiftY + $dims['height'] + 6,
				$str,
				'#'.$this->graphtheme['highlightcolor']))
			->setAngle(-90)
		);

		$this->addItem(
			(new CLine(
				$this->shiftXleft + $position,
				$this->shiftY,
				$this->shiftXleft + $position,
				$this->sizeY + $this->shiftY,
				'#'.$this->graphtheme['maingridcolor']))
			->setDashed()
		);
	}

	/**
	 * Draw main period label in black color with 7px font size under X axis and a 1px dashed gray vertical line
	 * according to that label.
	 *
	 * @param int $value		Unix time.
	 * @param sring $format		Date time format.
	 * @param int $position		Position on X axis.
	 */
	private function drawSubPeriod($value, $format, $position) {
		$point = zbx_date2str($format, $value);
		$element = imageTextSize(7, 90, $point);

		$this->addItem(
			(new CText(
				$this->shiftXleft + $position + round($element['width'] / 2),
				$this->sizeY + $this->shiftY + $element['height'] + 6,
				$point,
				'#'.$this->graphtheme['textcolor']))
			->setAngle(-90)
		);

		$this->addItem(
			(new CLine(
				$this->shiftXleft + $position,
				$this->shiftY,
				$this->shiftXleft + $position,
				$this->sizeY + $this->shiftY,
				'#'.$this->graphtheme['gridcolor']))
			->setDashed()
		);
	}

	/**
	 * Calculates the optimal size of time interval.
	 */
	private function calculateTimeInterval() {
		$time_interval = ($this->gridPixels * $this->period) / $this->sizeX;
		$intervals = [
			['main' => SEC_PER_MIN / 2, 'sub' => SEC_PER_MIN / 60],		// 30 seconds and 1 second
			['main' => SEC_PER_MIN, 'sub' => SEC_PER_MIN / 12],			// 60 seconds and 5 seconds
			['main' => SEC_PER_MIN * 5, 'sub' => SEC_PER_MIN / 6],		// 5 minutes and 10 seconds
			['main' => SEC_PER_MIN * 15, 'sub' => SEC_PER_MIN / 2],		// 15 minutes and 30 seconds
			['main' => SEC_PER_HOUR, 'sub' => SEC_PER_MIN],				// 1 hour and 1 minute
			['main' => SEC_PER_HOUR, 'sub' => SEC_PER_MIN * 2],			// 1 hour and 2 minutes
			['main' => SEC_PER_HOUR, 'sub' => SEC_PER_MIN * 5],			// 1 hour and 5 minutes
			['main' => SEC_PER_HOUR, 'sub' => SEC_PER_MIN * 15],		// 1 hour and 15 minutes
			['main' => SEC_PER_HOUR, 'sub' => SEC_PER_MIN * 30],		// 1 hour and 30 minutes
			['main' => SEC_PER_DAY, 'sub' => SEC_PER_HOUR],				// 1 day and 1 hours
			['main' => SEC_PER_DAY, 'sub' => SEC_PER_HOUR * 3],			// 1 day and 3 hours
			['main' => SEC_PER_DAY, 'sub' => SEC_PER_HOUR * 6],			// 1 day and 6 hours
			['main' => SEC_PER_DAY, 'sub' => SEC_PER_HOUR * 12],		// 1 day and 12 hours
			['main' => SEC_PER_WEEK, 'sub' => SEC_PER_DAY],				// 1 week and 1 day
			['main' => SEC_PER_WEEK * 2, 'sub' => SEC_PER_WEEK],		// 2 weeks and 1 week
			['main' => SEC_PER_MONTH, 'sub' => SEC_PER_DAY * 15],		// 30 days and 15 days
			['main' => SEC_PER_MONTH * 6, 'sub' => SEC_PER_MONTH],		// half year and 30 days
			['main' => SEC_PER_YEAR, 'sub' => SEC_PER_MONTH],			// 1 year and 30 days
			['main' => SEC_PER_YEAR, 'sub' => SEC_PER_MONTH * 3],		// 1 year and 90 days
			['main' => SEC_PER_YEAR, 'sub' => SEC_PER_MONTH * 4],		// 1 year and 120 days
			['main' => SEC_PER_YEAR, 'sub' => SEC_PER_MONTH * 6],		// 1 year and 180 days
			['main' => SEC_PER_YEAR * 5, 'sub' => SEC_PER_YEAR],		// 5 years and 1 year
			['main' => SEC_PER_YEAR * 10, 'sub' => SEC_PER_YEAR * 2],	// 10 years and 2 years
			['main' => SEC_PER_YEAR * 15, 'sub' => SEC_PER_YEAR * 3],	// 15 years and 3 years
			['main' => SEC_PER_YEAR * 20, 'sub' => SEC_PER_YEAR * 5],	// 20 years and 5 years
			['main' => SEC_PER_YEAR * 30, 'sub' => SEC_PER_YEAR * 10],	// 30 years and 10 years
			['main' => SEC_PER_YEAR * 40, 'sub' => SEC_PER_YEAR * 20],	// 40 years and 20 years
			['main' => SEC_PER_YEAR * 60, 'sub' => SEC_PER_YEAR * 30],	// 60 years and 30 years
			['main' => SEC_PER_YEAR * 80, 'sub' => SEC_PER_YEAR * 40]	// 80 years and 40 years
		];

		// Default inteval values.
		$distance = SEC_PER_YEAR * 5;
		$main_interval = 0;
		$sub_interval = 0;

		foreach ($intervals as $interval) {
			$time = abs($interval['sub'] - $time_interval);

			if ($time < $distance) {
				$distance = $time;
				$sub_interval = $interval['sub'];
				$main_interval = $interval['main'];
			}
		}

		// Calculate sub interval.
		$interval_x = ($sub_interval * $this->sizeX) / $this->period;

		if ($sub_interval > SEC_PER_DAY) {
			$offset = (7 - date('w', $this->from_time)) * SEC_PER_DAY;
			$offset += $this->diffTZ;

			$next = $this->from_time + $offset;

			$offset = mktime(0, 0, 0, date('m', $next), date('d', $next), date('Y', $next)) - $this->from_time;
		}
		else {
			$offset = $sub_interval - (($this->from_time + date('Z', $this->from_time)) % $sub_interval);
		}

		$sub = &$this->grid['horizontal']['sub'];
		$sub['interval'] = $sub_interval;
		$sub['interval_x'] = $interval_x;
		$sub['offset'] = $offset;

		// Calculate main interval.
		$interval_x = ($main_interval * $this->sizeX) / $this->period;

		if ($main_interval > SEC_PER_DAY) {
			$offset = (7 - date('w', $this->from_time)) * SEC_PER_DAY;
			$offset += $this->diffTZ;
			$next = $this->from_time + $offset;

			$offset = mktime(0, 0, 0, date('m', $next), date('d', $next), date('Y', $next)) - $this->from_time;
		}
		else {
			$offset = $main_interval - (($this->from_time + (date('Z', $this->from_time))) % $main_interval);
			$offset += $this->diffTZ;
		}

		$main = &$this->grid['horizontal']['main'];
		$main['interval'] = $main_interval;
		$main['interval_x'] = $interval_x;
		$main['offset'] = $offset;
	}

	/**
	 * Draw date and time intervals under the X axis.
	 */
	private function drawDateTimeIntervals() {
		$sub_interval = $this->grid['horizontal']['sub']['interval'];
		$sub_interval_x = $this->grid['horizontal']['sub']['interval_x'];
		$sub_offset = $this->grid['horizontal']['sub']['offset'];
		$main_interval = $this->grid['horizontal']['main']['interval'];
		$main_interval_x = $this->grid['horizontal']['main']['interval_x'];
		$main_offset = $this->grid['horizontal']['main']['offset'];

		// Infinite loop checks.
		if ($sub_interval == $main_interval
				|| ($main_interval_x < floor(($main_interval / $sub_interval) * $sub_interval_x))) {
			return;
		}

		// Sub interval title size.
		$element_size = imageTextSize(7, 90, 'WWW');

		// Main interval title size.
		$end_element_size = imageTextSize(8, 90, 'WWW');

		$position = 0;
		$i = 0;

		// Calculate the next date and time, postion and determines label type (main or sub) for label placement.
		while ($this->stime + $i * $sub_interval + $sub_offset < $this->to_time) {
			// Next step calculation by interval.

			$previous_time = isset($new_time) ? $new_time : $this->stime;

			// Every 40 years.
			if ($sub_interval == SEC_PER_YEAR * 40) {
				$new_time = mktime(0, 0, 0, 1, 1, date('Y', $previous_time) + 40);
			}
			// Every 30 years.
			elseif ($sub_interval == SEC_PER_YEAR * 30) {
				$new_time = mktime(0, 0, 0, 1, 1, date('Y', $previous_time) + 30);
			}
			// Every 20 years.
			elseif ($sub_interval == SEC_PER_YEAR * 20) {
				$new_time = mktime(0, 0, 0, 1, 1, date('Y', $previous_time) + 20);
			}
			// Every 10 years.
			elseif ($sub_interval == SEC_PER_YEAR * 10) {
				$new_time = mktime(0, 0, 0, 1, 1, date('Y', $previous_time) + 10);
			}
			// Every 5 years.
			elseif ($sub_interval == SEC_PER_YEAR * 5) {
				$new_time = mktime(0, 0, 0, 1, 1, date('Y', $previous_time) + 5);
			}
			// Every 3 years.
			elseif ($sub_interval == SEC_PER_YEAR * 3) {
				$new_time = mktime(0, 0, 0, 1, 1, date('Y', $previous_time) + 3);
			}
			// Every 2 years.
			elseif ($sub_interval == SEC_PER_YEAR * 2) {
				$new_time = mktime(0, 0, 0, 1, 1, date('Y', $previous_time) + 2);
			}
			// Every year.
			elseif ($sub_interval == SEC_PER_YEAR) {
				$new_time = mktime(0, 0, 0, 1, 1, date('Y', $previous_time) + 1);
			}
			// Every 6 months.
			elseif ($sub_interval == SEC_PER_MONTH * 6) {
				// First step calculation.
				if ($i == 0) {
					// If month > July, then chanage it to 1st of January of the next year.
					if (date('m', $this->stime) > 7) {
						$new_time = mktime(0, 0, 0, 1, 1, date('Y', $previous_time) + 1);
					}
					// Otherwise set 1st of July of the same year as the next step.
					else {
						$new_time = mktime(0, 0, 0, 7, 1, date('Y', $previous_time));
					}
				}
				// Other steps calculation.
				else {
					// If month = January, then change it to 1st July of the same year.
					if (date('m', $previous_time) == 1) {
						$new_time = mktime(0, 0, 0, 7, 1, date('Y', $previous_time));
					}
					// Otherwise set 1st of January of the next year as the next step.
					else {
						$new_time = mktime(0, 0, 0, 1, 1, date('Y', $previous_time) + 1);
					}
				}
			}
			// Every 4 months.
			elseif ($sub_interval == SEC_PER_MONTH * 4) {
				// First step calculation.
				if ($i == 0) {
					// If month > September, then chanage it to 1st of January of the next year.
					if (date('m', $this->stime) > 9) {
						$new_time = mktime(0, 0, 0, 1, 1, date('Y', $previous_time) + 1);
					}
					// If month > May, then change it to 1st of September of the same year.
					elseif (date('m', $this->stime) > 5) {
						$new_time = mktime(0, 0, 0, 9, 1, date('Y', $previous_time));
					}
					// Otherwise set 1st of May of the same year as next step.
					else {
						$new_time = mktime(0, 0, 0, 5, 1, date('Y', $previous_time));
					}
				}
				// Other steps calculation.
				else {
					// If month = September, then change it to 1st of January of the next year.
					if (date('m', $previous_time) == 9) {
						$new_time = mktime(0, 0, 0, 1, 1, date('Y', $previous_time) + 1);
					}
					// If month = May, then change it to 1st of September of the same year.
					elseif (date('m', $previous_time) == 5) {
						$new_time = mktime(0, 0, 0, 9, 1, date('Y', $previous_time));
					}
					// Otherwise set 1st of May of the same year as next step.
					else {
						$new_time = mktime(0, 0, 0, 5, 1, date('Y', $previous_time));
					}
				}
			}
			// Every 3 months.
			elseif ($sub_interval == SEC_PER_MONTH * 3) {
				// First step calculation.
				if ($i == 0) {
					// If month > October, then change it to 1st of January of the next year.
					if (date('m', $this->stime) > 10) {
						$new_time = mktime(0, 0, 0, 1, 1, date('Y', $previous_time) + 1);
					}
					// If month > July, then change it to 1st of October of the same year.
					elseif (date('m', $this->stime) > 7) {
						$new_time = mktime(0, 0, 0, 10, 1, date('Y', $previous_time));
					}
					// If month > April, then change it to 1st of July of the same year.
					elseif (date('m', $this->stime) > 4) {
						$new_time = mktime(0, 0, 0, 7, 1, date('Y', $previous_time));
					}
					// Otherwise set 1st of April of the same year as next step.
					else {
						$new_time = mktime(0, 0, 0, 4, 1, date('Y', $previous_time));
					}
				}
				// Other steps calculation.
				else {
					// If month = October, then change it to 1st of January of the next year.
					if (date('m', $previous_time) == 10) {
						$new_time = mktime(0, 0, 0, 1, 1, date('Y', $previous_time) + 1);
					}
					// If month = July, then change it to 1st of October of the same year.
					elseif (date('m', $previous_time) == 7) {
						$new_time = mktime(0, 0, 0, 10, 1, date('Y', $previous_time));
					}
					// If month = April, then change it to 1st of July of the same year.
					elseif (date('m', $previous_time) == 4) {
						$new_time = mktime(0, 0, 0, 7, 1, date('Y', $previous_time));
					}
					// Otherwise set 1st of April of the same year as next step.
					else {
						$new_time = mktime(0, 0, 0, 4, 1, date('Y', $previous_time));
					}
				}
			}
			// Every month.
			elseif ($sub_interval == SEC_PER_MONTH) {
				$new_time = mktime(0, 0, 0, date('m', $previous_time) + 1, 1, date('Y', $previous_time));
			}
			// Every 15 days (about half of a month).
			elseif ($sub_interval == SEC_PER_DAY * 15) {
				// First step calculation.
				if ($i == 0) {
					// If day > 16, then change it to the 1st day of the next month.
					if (date('d', $this->stime) > 16) {
						$new_time = mktime(0, 0, 0, date('m', $previous_time) + 1, 1, date('Y', $previous_time));
					}
					// Otherwise set 16th day of the same month.
					else {
						$new_time = mktime(0, 0, 0, date('m', $previous_time), 16, date('Y', $previous_time));
					}
				}
				// Other steps calculation.
				else {
					// If 1st day of the month, then change it to 16th day of the same month.
					if (date('d', $previous_time) == 1) {
						$new_time = mktime(0, 0, 0, date('m', $previous_time), 16, date('Y', $previous_time));
					}
					// Otherwise set 1st day of the next month.
					else {
						$new_time = mktime(0, 0, 0, date('m', $previous_time) + 1, 1, date('Y', $previous_time));
					}
				}
			}
			// Less than 15 days.
			else {
				$new_time = $this->from_time + $i * $sub_interval + $sub_offset;
			}

			// Draw until year 2038.
			if ($new_time < $this->stime && $i != 0) {
				break;
			}

			$timeInterval = $new_time - $previous_time;

			$timeIntervalX = ($timeInterval * $this->sizeX) / $this->period;

			$position += $timeIntervalX;

			// Start drawing after year 1970.
			if ($new_time < 0) {
				$i++;
				continue;
			}

			// First element overlaping checks.
			if (($i == 0 && $position < $element_size['width']) || $new_time >= $this->to_time) {
				$i++;
				continue;
			}

			// Last element overlaping check.
			if ($position > $this->sizeX - $end_element_size['width'] / 2 - 2) {
				break;
			}

			$i++;

			// What time format to display.
			if (date('d', $new_time) == 1 && date('m', $new_time) == 1 && date('H', $new_time) == 0
					&& date('i', $new_time) == 0) {
				$format = _x('Y', DATE_FORMAT_CONTEXT);
			}
			elseif (date('d', $new_time) == 1 && date('H', $new_time) == 0 && date('i', $new_time) == 0
					&& ($sub_interval == SEC_PER_MONTH || $sub_interval == SEC_PER_MONTH * 3
						|| $sub_interval == SEC_PER_MONTH * 4 || $sub_interval == SEC_PER_MONTH * 6)) {
				$format = _('M');
			}
			elseif ((date('H', $new_time) == 0 && date('i', $new_time) == 0) || $sub_interval > SEC_PER_HOUR * 12) {
				$format = _('m-d');
			}
			elseif (date('s', $new_time) == 0 && $sub_interval >= 60) {
				$format = TIME_FORMAT;
			}
			else {
				$format = _('H:i:s');
			}

			// Check if main or sub interval and then draw it.
			if ((!($new_time % $main_interval) && $main_interval < SEC_PER_DAY)
					|| ($sub_interval < SEC_PER_MIN && date('s', $new_time) == 0)
					|| ($main_interval == SEC_PER_DAY && date('H', $new_time) == 0 && date('i', $new_time) == 0)
					|| ($main_interval == SEC_PER_WEEK && date('N', $new_time) == 7)
					|| ($main_interval == SEC_PER_MONTH && date('d', $new_time) == 1)
					|| ($main_interval == SEC_PER_WEEK * 2 && date('m', $new_time) != date('m', $previous_time))
					|| $format == _x('Y', DATE_FORMAT_CONTEXT)) {
				$this->drawMainPeriod($new_time, $format, $position);
				continue;
			}

			$this->drawSubPeriod($new_time, $format, $position);
		}
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
				if ($this->graph_items[$item]['axisside'] == $side) {
					// check if items use B or Bps units
					if ($this->graph_items[$item]['units'] == 'B' || $this->graph_items[$item]['units'] == 'Bps') {
						$byteStep = true;
					}
					if (is_null($units)) {
						$units = $this->graph_items[$item]['units'];
					}
					elseif ($this->graph_items[$item]['units'] != $units) {
						$units = '';
					}
				}
			}

			if (is_null($units) || $units === false) {
				$units = '';
			}
			else {
				for ($item = 0; $item < $this->num; $item++) {
					if ($this->graph_items[$item]['axisside'] == $side && !empty($this->graph_items[$item]['unitsLong'])) {
						$unitsLong = $this->graph_items[$item]['unitsLong'];
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

				$this->addItem(
					(new CText(
						$tmpX,
						$tmpY,
						$unitsLong,
						'#'.$this->graphtheme['textcolor']))
					->setAngle(-90)
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
				$calcValues = [];
				for ($i = 0; $i <= $hstr_count; $i++) {
					$hstr_count = ($hstr_count == 0) ? 1 : $hstr_count;

					$val = bcadd(bcmul($i, $step), $minY);

					if (bccomp(bcadd($val, bcdiv($step,2)), $maxY) == 1) {
						continue;
					}

					$calcValues[] = convert_units([
						'value' => $val,
						'convert' => ITEM_CONVERT_NO_UNITS,
						'byteStep' => $byteStep,
						'pow' => $newPow
					]);
				}

				$calcValues[] = convert_units([
					'value' => $maxY,
					'convert' => ITEM_CONVERT_NO_UNITS,
					'byteStep' => $byteStep,
					'pow' => $newPow
				]);

				$maxLength = calcMaxLengthAfterDot($calcValues);
			}

			for ($i = 0; $i <= $hstr_count; $i++) {
				$hstr_count = ($hstr_count == 0) ? 1 : $hstr_count;

				$val = bcadd(bcmul($i, $step), $minY);

				if (bccomp(bcadd($val, bcdiv($step, 2)), $maxY) == 1) {
					continue;
				}

				$str = convert_units([
					'value' => $val,
					'units' => $units,
					'convert' => ITEM_CONVERT_NO_UNITS,
					'byteStep' => $byteStep,
					'pow' => $newPow,
					'ignoreMillisec' => $ignoreMillisec,
					'length' => $maxLength
				]);

				if ($side == GRAPH_YAXIS_SIDE_LEFT) {
					$dims = imageTextSize(8, 0, $str);
					$posX = $this->shiftXleft - $dims['width'] - 9;
				}
				else {
					$posX = $this->sizeX + $this->shiftXleft + 12;
				}

				// marker Y coordinate
				$posY = $this->sizeY + $this->shiftY - $this->gridStepX[$side] * $i + 4;

				$this->addItem(new CText($posX, $posY, $str, '#'.$this->graphtheme['textcolor']));
			}

			$str = convert_units([
				'value' => $maxY,
				'units' => $units,
				'convert' => ITEM_CONVERT_NO_UNITS,
				'byteStep' => $byteStep,
				'pow' => $newPow,
				'ignoreMillisec' => $ignoreMillisec,
				'length' => $maxLength
			]);

			if ($side == GRAPH_YAXIS_SIDE_LEFT) {
				$dims = imageTextSize(8, 0, $str);
				$posX = $this->shiftXleft - $dims['width'] - 9;
				$color_hex = GRAPH_ZERO_LINE_COLOR_LEFT;
			}
			else {
				$posX = $this->sizeX + $this->shiftXleft + 12;
				$color_hex = GRAPH_ZERO_LINE_COLOR_RIGHT;
			}

			$this->addItem(new CText($posX, $this->shiftY + 4, $str, '#'.$this->graphtheme['textcolor']));

			if ($this->zero[$side] != $this->sizeY + $this->shiftY && $this->zero[$side] != $this->shiftY) {
				$this->addItem(
					new CLine(
						$this->shiftXleft,
						$this->zero[$side],
						$this->shiftXleft + $this->sizeX,
						$this->zero[$side],
						'#'.$color_hex
					)
				);
			}
		}
	}

	protected function drawWorkPeriod() {
		$this->addItem(
			(new CRect($this->shiftXleft + 1, $this->shiftY, $this->sizeX, $this->sizeY))
			->setStrokeWidth(1)
			->setFillColor('#'.$this->graphtheme['graphcolor'])
			->setStrokeColor('#'.$this->graphtheme['graphcolor'])
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

		$this->addItem(
			(new CRect($this->shiftXleft + 1, $this->shiftY, $this->sizeX, $this->sizeY))
			->setStrokeWidth(1)
			->setFillColor('#'.$this->graphtheme['nonworktimecolor'])
			->setStrokeColor('#'.$this->graphtheme['nonworktimecolor'])
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
			$this->addItem(
				(new CRect($x1, $this->shiftY, $x2-$x1-1, $this->sizeY))
				->setStrokeWidth(1)
				->setFillColor('#'.$this->graphtheme['graphcolor'])
				->setStrokeColor('#'.$this->graphtheme['graphcolor'])
			);

			$start = find_period_start($periods, $end);
		}
	}

	protected function drawPercentileLines() {
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
				$this->addItem(
					new CLine(
						$this->shiftXleft,
						$y,
						$this->sizeX + $this->shiftXleft,
						$y,
						'#'.$color
					)
				);
			}
		}
	}

	protected function drawTriggerLines() {
		if ($this->m_showTriggers != 1) {
			return;
		}

		foreach ($this->triggers as $tnum => $trigger) {
			if ($trigger['skipdraw']) {
				continue;
			}

			$this->addItem([
				(new CLine(
					$this->shiftXleft,
					$trigger['y'],
					$this->sizeX + $this->shiftXleft,
					$trigger['y'],
					'#'.$trigger['color']))
				->setDashed(),
				(new CLine(
					$this->shiftXleft,
					$trigger['y'] + 1,
					$this->sizeX + $this->shiftXleft,
					$trigger['y'] + 1,
					'#'.$trigger['color']))
				->setDashed()]
			);
		}
	}

	private function drawTriggerLegend($x, $y, $rowNum) {
		if ($this->sizeY < ZBX_GRAPH_LEGEND_HEIGHT) {
			return $rowNum;
		}

		$text_color = $this->graphtheme['textcolor'];

		foreach ($this->triggers as $trigger) {
			$this->addItem(
				(new CTag('ellipse', true))
					->setAttribute('cx', $x + 5)
					->setAttribute('cy', $y + 5 + 14 * $rowNum)
					->setAttribute('rx', 5)
					->setAttribute('ry', 5)
					->setAttribute('stroke', 'black')
					->setAttribute('fill', '#'.$trigger['color'])
			);

			$text = $trigger['description'];
			$this->addItem(new CText($x + 20, $y + 9 + 14 * $rowNum, $text, 0, '#'.$text_color));
			$text = $trigger['constant'];
			$this->addItem(new CText($x + 380, $y + 9 + 14 * $rowNum, $text, 0, '#'.$text_color));

			$rowNum++;
		}

		return $rowNum;
	}

	private function drawPercentileLegend($x, $y, $rowNum) {
		if ($this->sizeY < ZBX_GRAPH_LEGEND_HEIGHT) {
			return $rowNum;
		}

		$text_color = $this->graphtheme['textcolor'];
		$units = ['left' => 0, 'right' => 0];

		if ($this->type == GRAPH_TYPE_NORMAL) {
			foreach ($this->percentile as $side => $percentile) {
				if ($percentile['percent'] > 0 && $percentile['value']) {
					$percentile['percent'] = (float) $percentile['percent'];
					$convertedUnit = convert_units([
						'value' => $percentile['value'],
						'units' => $units[$side]
					]);
					$text = $percentile['percent'].'th percentile: '.$convertedUnit.' ('.$side.')';

					$this->addItem(new CText($x + 20, $y + 9 + 14 * $rowNum, $text, 0, '#'.$text_color));

					if ($side == 'left') {
						$color = $this->graphtheme['leftpercentilecolor'];
					}
					else {
						$color = $this->graphtheme['rightpercentilecolor'];
					}

					$this->addItem(
						(new CPolygon([
								[$x + 10, $y + 10 + 14 * $rowNum],
								[$x, $y + 10 + 14 * $rowNum],
								[$x + 5, $y + 14 * $rowNum]
							]))
							->setWidth(1)
							->setStrokeColor('black')
							->setFillColor('#'.$color)
					);
					$rowNum++;
				}
			}
		}
		return $rowNum;
	}

	private function drawItemLegend($x, $y, $rowNum) {
		$text_color = $this->graphtheme['textcolor'];
		$units = ['left' => 0, 'right' => 0];

		$this->addItem([
			(new CText($x + 320, $y - 5, _('last'), 0, '#'.$text_color))
				->setAttribute('text-anchor', 'end'),
			(new CText($x + 380, $y - 5, _('min'), 0, '#'.$text_color))
				->setAttribute('text-anchor', 'end'),
			(new CText($x + 440, $y - 5, _('avg'), 0, '#'.$text_color))
				->setAttribute('text-anchor', 'end'),
			(new CText($x + 500, $y - 5, _('max'), 0, '#'.$text_color))
				->setAttribute('text-anchor', 'end')]
		);

		$i = ($this->type == GRAPH_TYPE_STACKED) ? $this->num - 1 : 0;
		while ($i >= 0 && $i < $this->num) {
			switch ($this->graph_items[$i]['calc_fnc']) {
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

			$data = &$this->data[$this->graph_items[$i]['itemid']][$this->graph_items[$i]['calc_type']];

			// draw color square
			$this->addItem(
				(new CRect($x, $y + 14 * $i, 10, 10))
					->setStrokeWidth(1)
					->setStrokeColor('black')
					->setFillColor('#'.$this->graph_items[$i]['color'])
			);

			// caption
			$itemCaption = $this->itemsHost
				? $this->graph_items[$i]['name_expanded']
				: $this->graph_items[$i]['hostname'].NAME_DELIMITER.$this->graph_items[$i]['name_expanded'];

			// draw legend of an item with data
			if (isset($data) && isset($data['min'])) {
				if ($this->graph_items[$i]['axisside'] == GRAPH_YAXIS_SIDE_LEFT) {
					$units['left'] = $this->graph_items[$i]['units'];
				}
				else {
					$units['right'] = $this->graph_items[$i]['units'];
				}

				$this->addItem([
					new CText($x + 20, $y + 9 + 14 * $i, $itemCaption, 0, '#'.$text_color),
					new CText($x + 240, $y + 9 + 14 * $i, '['.$fncRealName.']', 0, '#'.$text_color)]
				);
				$text = convert_units([
					'value' => $this->getLastValue($i),
					'units' => $this->graph_items[$i]['units'],
					'convert' => ITEM_CONVERT_NO_UNITS
				]);

				$this->addItem(
					(new CText($x + 320, $y + 9 +  14 * $i, $text, 0, '#'.$text_color))
						->setAttribute('text-anchor', 'end')
				);

				$text = convert_units([
					'value' => min($data['min']),
					'units' => $this->graph_items[$i]['units'],
					'convert' => ITEM_CONVERT_NO_UNITS
				]);

				$this->addItem(
					(new CText($x + 380, $y + 9 + 14 * $i, $text, 0, '#'.$text_color))
						->setAttribute('text-anchor', 'end')
				);

				$text = convert_units([
					'value' => $data['avg_orig'],
					'units' => $this->graph_items[$i]['units'],
					'convert' => ITEM_CONVERT_NO_UNITS
				]);

				$this->addItem(
					(new CText($x + 440, $y + 9 + 14 * $i, $text, 0, '#'.$text_color))
						->setAttribute('text-anchor', 'end')
				);

				$text = convert_units([
					'value' => max($data['max']),
					'units' => $this->graph_items[$i]['units'],
					'convert' => ITEM_CONVERT_NO_UNITS
				]);

				$this->addItem(
					(new CText($x + 500, $y + 9 + 14 * $i, $text, 0, '#'.$text_color))
						->setAttribute('text-anchor', 'end')
				);
			}
			// draw legend of an item without data
			else {
				$text = '['._('no data').']';

				$this->addItem(new CText($x + 20, $y + 9 + 14 * $i, $itemCaption, 0, '#'.$text_color));
				$this->addItem(new CText($x + 240, $y + 9 + 14 * $i, $text, 0, '#'.$text_color));
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

		return $rowNum;
	}

	private function drawLegend() {
		$rowNum = 0;
		$x = 20;
		$y = $this->sizeY + $this->shiftY + $this->legendOffsetY;

		$rowNum = $this->drawItemLegend($x, $y, $rowNum);
		$rowNum = $this->drawPercentileLegend($x, $y, $rowNum);
		$this->drawTriggerLegend($x, $y, $rowNum);
	}

	protected function limitToBounds(&$value1, &$value2, $min, $max, $drawtype) {
		// fixes graph out of bounds problem
		if ((($value1 > ($max + $min)) && ($value2 > ($max + $min))) || ($value1 < $min && $value2 < $min)) {
			if (!in_array($drawtype, [GRAPH_ITEM_DRAWTYPE_FILLED_REGION, GRAPH_ITEM_DRAWTYPE_GRADIENT_LINE])) {
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

	protected function drawElement(&$data, $from, $to, $drawtype, $max_color, $avg_color_hex, $min_color, $minmax_color_hex, $calc_fnc, $axisside) {
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
					$this->addItem(
						(new CPolygon([
								[$a[0], $a[1]],
								[$a[2], $a[3]],
								[$a[4], $a[5]],
								[$a[6], $a[7]]
							]))
							->setWidth(1)
							->setFillColor('#'.$minmax_color_hex)
							->setStrokeColor('#'.$minmax_color_hex)
					);
					if (!$y1x || !$y2x) {
						$this->addItem(
							new CLine(
								$x1,
								$y1max,
								$x2,
								$y2max,
								'Black' // $max_color, bold
							)
						);
					}

					if (!$y1n || !$y2n) {
						$this->addItem(
							new CLine(
								$x1,
								$y1mix,
								$x2,
								$y2min,
								'Black' // min_color, bold
							)
						);
					}
				}

				$this->addItem(
					(new CLine(
						$x1,
						$y1,
						$x2,
						$y2,
						'#'.$avg_color_hex
					))->setWidth(2)
				);
				break;
			case GRAPH_ITEM_DRAWTYPE_LINE:
				if ($calc_fnc == CALC_FNC_ALL) {
					$this->addItem(
						(new CPolygon([
								[$a[0], $a[1]],
								[$a[2], $a[3]],
								[$a[4], $a[5]],
								[$a[6], $a[7]]
							]))
							->setWidth(1)
							->setFillColor('#'.$minmax_color_hex)
							->setStrokeColor('#'.$minmax_color_hex)
					);
					if (!$y1x || !$y2x) {
						$this->addItem(
							new CLine(
								$x1,
								$y1max,
								$x2,
								$y2max,
								'Black'// max_color
							)
						);
					}
					if (!$y1n || !$y2n) {
						$this->addItem(
							new CLine(
								$x1,
								$y1min,
								$x2,
								$y2min,
								'Black' // min_color
							)
						);
					}
				}

				$this->addItem(
					(new CLine(
						$x1,
						$y1,
						$x2,
						$y2,
						'#'.$avg_color_hex
					))
						->setWidth(4)
						->setAttribute('opacity', 0.5)
				);
				break;
			case GRAPH_ITEM_DRAWTYPE_FILLED_REGION:
				$this->addItem(
					(new CPolygon([
							[$x1, $y1],
							[$x1, $y1_shift],
							[$x2, $y2_shift],
							[$x2, $y2]
						]))
						->setWidth(1)
						->setStrokeColor('#'.$avg_color_hex)
						->setFillColor('#'.$avg_color_hex)
				);
				break;
			case GRAPH_ITEM_DRAWTYPE_DOT:
				$this->addItem(
					(new CTag('ellipse', true))
						->setAttribute('cx', $x1)
						->setAttribute('cy', $y1)
						->setAttribute('rx', 4)
						->setAttribute('ry', 4)
						->setAttribute('stroke', '#'.$avg_color_hex)
				);
				break;
			case GRAPH_ITEM_DRAWTYPE_BOLD_DOT:
				$this->addItem(
					(new CTag('ellipse', true))
						->setAttribute('cx', $x1)
						->setAttribute('cy', $y1)
						->setAttribute('rx', 4)
						->setAttribute('ry', 4)
						->setAttribute('stroke', '#'.$avg_color_hex)
				);
				break;
			case GRAPH_ITEM_DRAWTYPE_DASHED_LINE:
				$this->addItem(
					(new CLine(
						$x1,
						$y1,
						$x2,
						$y2,
						'#'.$avg_color_hex
					))->setAttribute('stroke-dasharray', '5')->setWidth(5)->setAttribute('opacity', '0.5')
				);
				break;
			case GRAPH_ITEM_DRAWTYPE_GRADIENT_LINE:
				$this->addItem(
					(new CPolygon([
							[$x1, $y1],
							[$x1, $y1_shift],
							[$x2, $y2_shift],
							[$x2, $y2]
					]))
						->setWidth(1)
						->setStrokeColor('#'.$avg_color_hex)
						->setFillColor('#'.$avg_color_hex)
						->setAttribute('opacity', '0.5')
				);
				$this->addItem(
					(new CLine(
						$x1,
						$y1,
						$x2,
						$y2,
						'#'.$avg_color_hex
					))->setWidth(3)
				);
			break;
		}
	}

	public function draw() {
		$start_time = microtime(true);

		$this->updateShifts();

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
		$this->calcTriggers();
		$this->calcZero();
		$this->calcPercentile();

		$this->im = imagecreate($this->fullSizeX, $this->fullSizeY);

		$this->drawHeader();
		$this->drawWorkPeriod();
		$this->drawTimeGrid();
		$this->drawHorizontalGrid();
		$this->drawXYAxisScale();

		$maxX = $this->sizeX;

		// for each metric
		for ($item = 0; $item < $this->num; $item++) {
			$data = &$this->data[$this->graph_items[$item]['itemid']][$this->graph_items[$item]['calc_type']];

			if (!isset($data)) {
				continue;
			}

			if ($this->type == GRAPH_TYPE_STACKED) {
				$drawtype = $this->graph_items[$item]['drawtype'];
				$max_color = $this->getColor('ValueMax', GRAPH_STACKED_ALFA);
				$avg_color_hex = $this->graph_items[$item]['color'];
				$min_color = $this->getColor('ValueMin', GRAPH_STACKED_ALFA);
				$minmax_color_hex = 'FFFF96'; // ValueMinMax

				$calc_fnc = $this->graph_items[$item]['calc_fnc'];
			}
			else {
				$drawtype = $this->graph_items[$item]['drawtype'];
				$max_color = $this->getColor('ValueMax', GRAPH_STACKED_ALFA);
				$avg_color_hex = $this->graph_items[$item]['color'];
				$min_color = $this->getColor('ValueMin', GRAPH_STACKED_ALFA);
				$minmax_color_hex = 'FFFF96';

				$calc_fnc = $this->graph_items[$item]['calc_fnc'];
			}

			// for each X
			$prevDraw = true;

			for ($i = 1, $j = 0; $i < $maxX; $i++) { // new point
				if ($data['count'][$i] == 0 && $i != ($maxX - 1)) {
					continue;
				}

				$delay = $this->graph_items[$item]['delay'];

				if ($this->graph_items[$item]['type'] == ITEM_TYPE_TRAPPER
						|| ($this->hasSchedulingIntervals($this->graph_items[$item]['intervals']) && $delay == 0)) {
					$draw = true;
				}
				else {
					$diff = abs($data['clock'][$i] - $data['clock'][$j]);
					$cell = ($this->to_time - $this->from_time) / $this->sizeX;

					if ($cell > $delay) {
						$draw = ($diff < (ZBX_GRAPH_MAX_SKIP_CELL * $cell));
					}
					else {
						$draw = ($diff < (ZBX_GRAPH_MAX_SKIP_DELAY * $delay));
					}
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
						$valueDrawType,
						$max_color,
						$avg_color_hex,
						$min_color,
						$minmax_color_hex,
						$calc_fnc,
						$this->graph_items[$item]['axisside']
					);
				}

				$j = $i;
			}
		}

		$this->drawSides();

		if ($this->drawLegend) {
			$this->drawTriggerLines();
			$this->drawPercentileLines();
			$this->drawLegend();
		}

		$this->drawLogo();

		$str = sprintf('%0.2f', microtime(true) - $start_time);
		$str = _s('Data from %1$s. Generated in %2$s sec.', $this->dataFrom, $str);
		$this->addItem(
			(new CText($this->fullSizeX - 5, $this->fullSizeY - 5, $str, 0, 'Gray'))
				->setAttribute('text-anchor', 'end')
				->setAttribute('opacity', '0.2')
		);

		unset($this->graph_items, $this->data);

		$this->show();
	}

	/**
	 * Checks if item intervals has at least one scheduling interval.
	 *
	 * @param array $intervals
	 *
	 * @return bool
	 */
	private function hasSchedulingIntervals($intervals) {
		foreach ($intervals as $interval) {
			if ($interval['type'] == ITEM_DELAY_FLEX_TYPE_SCHEDULING) {
				return true;
			}
		}

		return false;
	}
}
