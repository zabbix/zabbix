<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
	const GRAPH_WIDTH_MIN = 20;
	const GRAPH_HEIGHT_MIN = 20;
	const legendOffsetY = 90;

	public function __construct($type = GRAPH_TYPE_NORMAL) {
		parent::__construct($type);
		$this->yaxismin = null;
		$this->yaxismax = null;
		$this->triggers = [];
		$this->ymin_type = GRAPH_YAXIS_TYPE_CALCULATED;
		$this->ymax_type = GRAPH_YAXIS_TYPE_CALCULATED;
		$this->yaxis = [
			GRAPH_YAXIS_SIDE_LEFT => false,
			GRAPH_YAXIS_SIDE_RIGHT => false
		];
		$this->skipLeftScale = 0; // in case if left axis should be drawn but doesn't contain any data
		$this->skipRightScale = 0; // in case if right axis should be drawn but doesn't contain any data
		$this->ymin_itemid = 0;
		$this->ymax_itemid = 0;
		$this->percentile = [
			GRAPH_YAXIS_SIDE_LEFT => [
				'percent' => 0, // draw percentage line
				'value' => 0 // calculated percentage value left y axis
			],
			GRAPH_YAXIS_SIDE_RIGHT => [
				'percent' => 0, // draw percentage line
				'value' => 0 // calculated percentage value right y axis
			]
		];
		$this->outer = false;
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
		$this->axis_valuetype = []; // overal items type (int/float)
		$this->drawItemsLegend = false; // draw items legend
		$this->drawExLegend = false; // draw percentile and triggers legend
	}

	/********************************************************************************************************/
	// PRE CONFIG:	ADD / SET / APPLY
	/********************************************************************************************************/
	public function showWorkPeriod($value) {
		$this->m_showWorkPeriod = ($value == 1) ? 1 : 0;
	}

	public function showTriggers($value) {
		$this->m_showTriggers = ($value == 1) ? 1 : 0;
	}

	/**
	 * Add single item object to graph. If invalid 'delay' interval passed method will interrupt current request with
	 * error message.
	 *
	 * @param array  $item                  Array of graph item properties.
	 * @param string $item['itemid']        Item id.
	 * @param string $item['type']          Item type.
	 * @param string $item['name']          Item host display name.
	 * @param string $item['hostname']      Item hostname.
	 * @param string $item['key_']          Item key_ field value.
	 * @param string $item['value_type']    Item value type.
	 * @param string $item['history']       Item history field value.
	 * @param string $item['trends']        Item trends field value.
	 * @param string $item['delay']         Item delay.
	 * @param string $item['master_itemid'] Master item id for item of type ITEM_TYPE_DEPENDENT.
	 * @param string $item['units']         Item units value.
	 * @param string $item['hostid']        Item host id.
	 * @param string $item['hostname']      Item host name.
	 * @param string $item['color']         Item presentation color.
	 * @param int    $item['drawtype']      Item presentation draw type, could be one of GRAPH_ITEM_DRAWTYPE_* constants.
	 * @param int    $item['yaxisside']     Item axis side, could be one of GRAPH_YAXIS_SIDE_* constants.
	 * @param int    $item['calc_fnc']      Item calculation function, could be one of CALC_FNC_* constants.
	 * @param int    $item['calc_type']     Item graph presentation calculation type, GRAPH_ITEM_SIMPLE or GRAPH_ITEM_SUM.
	 *
	 */
	public function addItem(array $graph_item) {
		if ($this->type == GRAPH_TYPE_STACKED) {
			$graph_item['drawtype'] = GRAPH_ITEM_DRAWTYPE_FILLED_REGION;
		}
		$update_interval_parser = new CUpdateIntervalParser(['usermacros' => true]);

		if ($update_interval_parser->parse($graph_item['delay']) != CParser::PARSE_SUCCESS) {
			show_error_message(_s('Incorrect value for field "%1$s": %2$s.', 'delay', _('invalid delay')));
			exit;
		}

		// Set graph item safe default values.
		$graph_item += [
			'color' => 'Dark Green',
			'drawtype' => GRAPH_ITEM_DRAWTYPE_LINE,
			'yaxisside' => GRAPH_YAXIS_SIDE_DEFAULT,
			'calc_fnc' => CALC_FNC_AVG,
			'calc_type' => GRAPH_ITEM_SIMPLE
		];
		$this->items[$this->num] = $graph_item;

		$this->yaxis[$graph_item['yaxisside']] = true;

		$this->num++;
	}

	public function setGraphOrientation($value, $yaxisside) {
		if ($value < 0) {
			$this->graphOrientation[$yaxisside] = '-';
		}
		elseif (zbx_empty($this->graphOrientation[$yaxisside]) && $value > 0) {
			$this->graphOrientation[$yaxisside] = '+';
		}
		return $this->graphOrientation[$yaxisside];
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
		$this->percentile[GRAPH_YAXIS_SIDE_LEFT]['percent'] = $percentile;
	}

	public function setRightPercentage($percentile) {
		$this->percentile[GRAPH_YAXIS_SIDE_RIGHT]['percent'] = $percentile;
	}

	public function setOuter($outer) {
		$this->outer = $outer;
	}

	protected function selectData() {
		$this->data = [];
		$now = time();

		if (!isset($this->stime)) {
			$this->stime = $now - $this->period;
		}

		$this->diffTZ = (date('Z', $this->stime) - date('Z', $this->stime + $this->period));
		$this->from_time = $this->stime; // + timeZone offset
		$this->to_time = $this->stime + $this->period; // + timeZone offset

		$p = $this->to_time - $this->from_time; // graph size in time
		$x = $this->sizeX; // graph size in px

		$this->itemsHost = null;

		$config = select_config();
		$items = [];

		for ($i = 0; $i < $this->num; $i++) {
			$item = $this->items[$i];

			if ($this->itemsHost === null) {
				$this->itemsHost = $item['hostid'];
			}
			elseif ($this->itemsHost != $item['hostid']) {
				$this->itemsHost = false;
			}

			if (!isset($this->axis_valuetype[$item['yaxisside']])) {
				$this->axis_valuetype[$item['yaxisside']] = $item['value_type'];
			}
			elseif ($this->axis_valuetype[$item['yaxisside']] != $item['value_type']) {
				$this->axis_valuetype[$item['yaxisside']] = ITEM_VALUE_TYPE_FLOAT;
			}

			$type = $item['calc_type'];
			$to_resolve = [];

			// Override item history setting with housekeeping settings, if they are enabled in config.
			if ($config['hk_history_global']) {
				$item['history'] = timeUnitToSeconds($config['hk_history']);
			}
			else {
				$to_resolve[] = 'history';
			}

			if ($config['hk_trends_global']) {
				$item['trends'] = timeUnitToSeconds($config['hk_trends']);
			}
			else {
				$to_resolve[] = 'trends';
			}

			// Otherwise, resolve user macro and parse the string. If successful, convert to seconds.
			if ($to_resolve) {
				$item = CMacrosResolverHelper::resolveTimeUnitMacros([$item], $to_resolve)[0];

				$simple_interval_parser = new CSimpleIntervalParser();

				if (!$config['hk_history_global']) {
					if ($simple_interval_parser->parse($item['history']) != CParser::PARSE_SUCCESS) {
						show_error_message(_s('Incorrect value for field "%1$s": %2$s.', 'history',
							_('invalid history storage period')
						));
						exit;
					}
					$item['history'] = timeUnitToSeconds($item['history']);
				}

				if (!$config['hk_trends_global']) {
					if ($simple_interval_parser->parse($item['trends']) != CParser::PARSE_SUCCESS) {
						show_error_message(_s('Incorrect value for field "%1$s": %2$s.', 'trends',
							_('invalid trend storage period')
						));
						exit;
					}
					$item['trends'] = timeUnitToSeconds($item['trends']);
				}
			}

			$item['source'] = ($item['trends'] == 0 || ($item['history'] > time() - ($this->from_time + $this->period / 2)
					&& $this->period / $this->sizeX <= ZBX_MAX_TREND_DIFF / ZBX_GRAPH_MAX_SKIP_CELL))
					? 'history' : 'trends';

			$items[] = $item;
		}

		$results = Manager::History()->getGraphAggregation($items, $this->from_time, $this->to_time, $x);

		foreach ($items as $item) {
			if (!array_key_exists($item['itemid'], $this->data)) {
				$this->data[$item['itemid']] = [];
			}

			if (!array_key_exists($type, $this->data[$item['itemid']])) {
				$this->data[$item['itemid']][$type] = [];
			}

			$curr_data = &$this->data[$item['itemid']][$type];

			$curr_data['count'] = null;
			$curr_data['min'] = null;
			$curr_data['max'] = null;
			$curr_data['avg'] = null;
			$curr_data['clock'] = null;

			if (array_key_exists($item['itemid'], $results)) {
				$result = $results[$item['itemid']];
				$this->dataFrom = $result['source'];

				foreach ($result['data'] as $row) {
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

				unset($result);
			}
			else {
				$this->dataFrom = $item['source'];
			}

			$loc_min = is_array($curr_data['min']) ? min($curr_data['min']) : null;
			$this->setGraphOrientation($loc_min, $item['yaxisside']);

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

		unset($items);
		unset($results);

		// calculate shift for stacked graphs
		if ($this->type == GRAPH_TYPE_STACKED) {
			for ($i = 1; $i < $this->num; $i++) {
				$item1 = $this->items[$i];

				$curr_data = &$this->data[$item1['itemid']][$item1['calc_type']];

				if (!isset($curr_data)) {
					continue;
				}

				for ($j = $i - 1; $j >= 0; $j--) {
					$item2 = $this->items[$j];

					if ($item2['yaxisside'] != $item1['yaxisside']) {
						continue;
					}

					$prev_data = &$this->data[$item2['itemid']][$item2['calc_type']];

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

	protected function selectTriggers() {
		$this->triggers = [];
		if ($this->m_showTriggers != 1) {
			return;
		}

		$max = 3;
		$cnt = 0;

		foreach ($this->items as $item) {
			$db_triggers = DBselect(
				'SELECT DISTINCT h.host,tr.description,tr.triggerid,tr.expression,tr.priority,tr.value'.
				' FROM triggers tr,functions f,items i,hosts h'.
				' WHERE tr.triggerid=f.triggerid'.
					" AND f.name IN ('last','min','avg','max')".
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

				if (!preg_match(
					'/^\{([0-9]+)\}\s*?([<>=]|[<>][=])\s*?([\-0-9\.]+)(['.ZBX_BYTE_SUFFIXES.ZBX_TIME_SUFFIXES.']?)$/',
						$trigger['expression'], $arr)) {
					continue;
				}

				$constant = $arr[3].$arr[4];

				$this->triggers[] = [
					'yaxisside' => $item['yaxisside'],
					'val' => convert($constant),
					'color' => getSeverityColor($trigger['priority']),
					'description' => _('Trigger').NAME_DELIMITER.CMacrosResolverHelper::resolveTriggerName($trigger),
					'constant' => '['.$arr[2].' '.$constant.']'
				];
				++$cnt;
			}
		}
	}

	/********************************************************************************************************/
	// CALCULATIONS
	/********************************************************************************************************/
	// calculates percentages for left & right Y axis
	protected function calcPercentile() {
		if ($this->type != GRAPH_TYPE_NORMAL) {
			return ;
		}

		$values = [
			GRAPH_YAXIS_SIDE_LEFT => [],
			GRAPH_YAXIS_SIDE_RIGHT=> []
		];

		$maxX = $this->sizeX;

		// for each metric
		for ($item = 0; $item < $this->num; $item++) {
			$data = &$this->data[$this->items[$item]['itemid']][$this->items[$item]['calc_type']];

			if (!isset($data)) {
				continue;
			}

			// for each X
			for ($i = 0; $i < $maxX; $i++) { // new point
				if ($data['count'][$i] == 0) {
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

				$values[$this->items[$item]['yaxisside']][] = $value;
			}
		}

		foreach ($this->percentile as $side => $percentile) {
			if ($percentile['percent'] > 0 && $values[$side]) {
				sort($values[$side]);
				// Using "Nearest Rank" method: http://en.wikipedia.org/wiki/Percentile#Definition_of_the_Nearest_Rank_method
				$percent = (int) ceil($percentile['percent'] / 100 * count($values[$side]));
				$this->percentile[$side]['value'] = $values[$side][$percent - 1];
			}
		}
	}

	// calculation of minimum Y axis
	protected function calculateMinY($side) {
		if ($this->ymin_type == GRAPH_YAXIS_TYPE_FIXED) {
			return $this->yaxismin;
		}

		if ($this->ymin_type == GRAPH_YAXIS_TYPE_ITEM_VALUE && $this->ymin_itemid != 0) {
			$item = get_item_by_itemid($this->ymin_itemid);
			if ($item) {
				$history = Manager::History()->getLastValues([$item]);
				if (isset($history[$item['itemid']])) {
					return $history[$item['itemid']][0]['value'];
				}
			}
		}

		$minY = null;
		for ($i = 0; $i < $this->num; $i++) {
			if ($this->items[$i]['yaxisside'] != $side) {
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

		if ($this->ymax_type == GRAPH_YAXIS_TYPE_ITEM_VALUE && $this->ymax_itemid != 0) {
			$item = get_item_by_itemid($this->ymax_itemid);
			if ($item) {
				$history = Manager::History()->getLastValues([$item]);
				if (isset($history[$item['itemid']])) {
					return $history[$item['itemid']][0]['value'];
				}
			}
		}

		$maxY = null;
		for ($i = 0; $i < $this->num; $i++) {
			if ($this->items[$i]['yaxisside'] != $side) {
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
				$this->zero[$side] = $this->sizeY + $this->shiftY - abs(bcdiv($this->m_minY[$side],
					$this->unit2px[$side]
				));
				$this->oxy[$side] = 0;
			}
		}
	}

	protected function calcMinMaxInterval() {
		$intervals = [];
		foreach ([1, 2, 3, 4] as $num) {
			$dec = pow(0.1, $num);
			foreach ([1, 2, 5] as $int) {
				$intervals[] = bcmul($int, $dec);
			}
		}

		// Check if items use B or Bps units.
		$leftBase1024 = false;
		$rightBase1024 = false;

		for ($item = 0; $item < $this->num; $item++) {
			if ($this->items[$item]['units'] == 'B' || $this->items[$item]['units'] == 'Bps') {
				if ($this->items[$item]['yaxisside'] == GRAPH_YAXIS_SIDE_LEFT) {
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

		// Invert sides and its bases, if left side doesn't exist.
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

		// Calculate interval.
		$columnInterval = bcdiv(bcmul($this->gridPixelsVert, (bcsub($this->m_maxY[$side], $this->m_minY[$side]))), $this->sizeY);

		$dist = bcmul(5, bcpow(10, 18));

		$interval = 0;
		foreach ($intervals as $int) {
			// We must get a positive number.
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

		// Calculate interval, if left side use B or Bps.
		if ($leftBase1024) {
			$interval = getBase1024Interval($interval, $this->m_minY[$side], $this->m_maxY[$side]);
		}

		$columnInterval = bcdiv(bcmul($this->gridPixelsVert, bcsub($this->m_maxY[$other_side], $this->m_minY[$other_side])), $this->sizeY);

		$dist = bcmul(5, bcpow(10, 18));

		$interval_other_side = 0;
		foreach ($intervals as $int) {
			// We must get a positive number.
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

		// Calculate interval, if right side use B or Bps.
		if ($rightBase1024) {
			$interval_other_side = getBase1024Interval($interval_other_side, $this->m_minY[$other_side],
				$this->m_maxY[$other_side]);
		}

		// Save original min and max items values.
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

		// Correcting MIN & MAX.
		$this->m_minY[$side] = bcmul(bcfloor(bcdiv($this->m_minY[$side], $interval)), $interval);
		$this->m_maxY[$side] = bcmul(bcceil(bcdiv($this->m_maxY[$side], $interval)), $interval);
		$this->m_minY[$other_side] = bcmul(bcfloor(bcdiv($this->m_minY[$other_side], $interval_other_side)), $interval_other_side);
		$this->m_maxY[$other_side] = bcmul(bcceil(bcdiv($this->m_maxY[$other_side], $interval_other_side)), $interval_other_side);

		// Add intervals so min/max Y wouldn't be too close to graph's top/bottom edges.
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

		// Calculate interval count for main and other side.
		$this->gridLinesCount[$side] = bcceil(bcdiv(bcsub($this->m_maxY[$side], $this->m_minY[$side]), $interval));
		$this->gridLinesCount[$other_side] = bcceil(bcdiv(bcsub($this->m_maxY[$other_side], $this->m_minY[$other_side]), $interval_other_side));

		$this->m_maxY[$side] = bcadd($this->m_minY[$side], bcmul($interval, $this->gridLinesCount[$side]));
		$this->gridStep[$side] = $interval;

		if (isset($this->axis_valuetype[$other_side])) {
			// Other side correction.
			$dist = bcsub($this->m_maxY[$other_side], $this->m_minY[$other_side]);
			$interval = 1;

			foreach ($intervals as $int) {
				if (bccomp($dist, bcmul($this->gridLinesCount[$side], $int)) == -1) {
					$interval = $int;
					break;
				}
			}

			// Correcting MIN & MAX on other side Y axis.
			$this->m_minY[$other_side] = bcmul(bcfloor(bcdiv($this->m_minY[$other_side], $interval)), $interval);
			$this->m_maxY[$other_side] = bcmul(bcceil(bcdiv($this->m_maxY[$other_side], $interval)), $interval);

			// Do recalculation in case if calculated min value is greater than calculated max value.
			if (bccomp($tmp_maxY[$other_side], $this->m_maxY[$other_side]) == 1 || bccomp($tmp_minY[$other_side], $this->m_minY[$other_side]) == -1) {
				$dist = bcsub($this->m_maxY[$other_side], $this->m_minY[$other_side]);
				$interval = 0;
				foreach ($intervals as $int) {
					if (bccomp($dist, bcmul($this->gridLinesCount[$side], $int)) == -1) {
						$interval = $int;
						break;
					}
				}

				// Correcting MIN & MAX values on other side Y axis.
				$this->m_minY[$other_side] = bcmul(bcfloor(bcdiv($this->m_minY[$other_side], $interval)), $interval);
				$this->m_maxY[$other_side] = bcmul(bcceil(bcdiv($this->m_maxY[$other_side], $interval)), $interval);
			}

			// Calculate interval, if right side use B or Bps.
			if (isset($rightBase1024)) {
				$interval = getBase1024Interval($interval, $this->m_minY[$side], $this->m_maxY[$side]);
				// Correcting MIN & MAX values on other side Y axis.
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

		// Get diff between min/max Y values and fix potential division by zero.
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
		$gbColor = $this->getColor($this->graphtheme['gridbordercolor'], 0);

		if ($this->yaxis[GRAPH_YAXIS_SIDE_LEFT]) {
			zbx_imageline(
				$this->im,
				$this->shiftXleft + $this->shiftXCaption,
				$this->shiftY - 5,
				$this->shiftXleft + $this->shiftXCaption,
				$this->sizeY + $this->shiftY + 4,
				$gbColor
			);

			imagefilledpolygon(
				$this->im,
				[
					$this->shiftXleft + $this->shiftXCaption - 3, $this->shiftY - 5,
					$this->shiftXleft + $this->shiftXCaption + 3, $this->shiftY - 5,
					$this->shiftXleft + $this->shiftXCaption, $this->shiftY - 10,
				],
				3,
				$this->getColor('White')
			);

			/* draw left axis triangle */
			zbx_imageline($this->im, $this->shiftXleft + $this->shiftXCaption - 3, $this->shiftY - 5,
					$this->shiftXleft + $this->shiftXCaption + 3, $this->shiftY - 5,
					$gbColor);
			zbx_imagealine($this->im, $this->shiftXleft + $this->shiftXCaption - 3, $this->shiftY - 5,
					$this->shiftXleft + $this->shiftXCaption, $this->shiftY - 10,
					$gbColor);
			zbx_imagealine($this->im, $this->shiftXleft + $this->shiftXCaption + 3, $this->shiftY - 5,
					$this->shiftXleft + $this->shiftXCaption, $this->shiftY - 10,
					$gbColor);
		}
		else {
			dashedLine(
				$this->im,
				$this->shiftXleft + $this->shiftXCaption,
				$this->shiftY,
				$this->shiftXleft + $this->shiftXCaption,
				$this->sizeY + $this->shiftY,
				$this->getColor($this->graphtheme['gridcolor'], 0)
			);
		}

		if ($this->yaxis[GRAPH_YAXIS_SIDE_RIGHT]) {
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
				[
					$this->sizeX + $this->shiftXleft + $this->shiftXCaption - 3, $this->shiftY - 5,
					$this->sizeX + $this->shiftXleft + $this->shiftXCaption + 3, $this->shiftY - 5,
					$this->sizeX + $this->shiftXleft + $this->shiftXCaption, $this->shiftY - 10,
				],
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
		else {
			dashedLine(
				$this->im,
				$this->sizeX + $this->shiftXleft + $this->shiftXCaption,
				$this->shiftY,
				$this->sizeX + $this->shiftXleft + $this->shiftXCaption,
				$this->sizeY + $this->shiftY,
				$this->getColor($this->graphtheme['gridcolor'], 0)
			);
		}

		zbx_imageline(
			$this->im,
			$this->shiftXleft + $this->shiftXCaption - 3,
			$this->sizeY + $this->shiftY + 1,
			$this->sizeX + $this->shiftXleft + $this->shiftXCaption + 5,
			$this->sizeY + $this->shiftY + 1,
			$gbColor
		);

		imagefilledpolygon(
			$this->im,
			[
				$this->sizeX + $this->shiftXleft + $this->shiftXCaption + 5, $this->sizeY + $this->shiftY - 2,
				$this->sizeX + $this->shiftXleft + $this->shiftXCaption + 5, $this->sizeY + $this->shiftY + 4,
				$this->sizeX + $this->shiftXleft + $this->shiftXCaption + 10, $this->sizeY + $this->shiftY + 1
			],
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
		$yAxis = $this->yaxis[GRAPH_YAXIS_SIDE_LEFT] ? GRAPH_YAXIS_SIDE_LEFT : GRAPH_YAXIS_SIDE_RIGHT;

		$stepY = $this->gridStepX[$yAxis];

		if ($this->gridLinesCount[$yAxis] < round($this->sizeY / $this->gridPixels)) {
			$stepY = $stepY / 2;
		}

		$xLeft = $this->shiftXleft;
		$xRight = $this->shiftXleft + $this->sizeX;
		$lineColor = $this->getColor($this->graphtheme['gridcolor'], 0);

		for ($y = $this->shiftY + $this->sizeY - $stepY; $y > $this->shiftY; $y -= $stepY) {
			dashedLine($this->im, $xLeft, $y, $xRight, $y, $lineColor);
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
		imageText(
			$this->im,
			8,
			90,
			$this->shiftXleft + $position + round($element['width'] / 2),
			$this->sizeY + $this->shiftY + $element['height'] + 6,
			$this->getColor($this->graphtheme['highlightcolor'], 0),
			$point
		);
	}

	/**
	 * Draw main period label in red color with 8px font size under X axis and a 2px dashed gray vertical line
	 * according to that label.
	 *
	 * @param string $value     Readable timestamp.
	 * @param int    $position  Position on X axis.
	 */
	private function drawMainPeriod($value, $position) {
		$dims = imageTextSize(8, 90, $value);

		imageText(
			$this->im,
			8,
			90,
			$this->shiftXleft + $position + round($dims['width'] / 2),
			$this->sizeY + $this->shiftY + $dims['height'] + 6,
			$this->getColor($this->graphtheme['highlightcolor'], 0),
			$value
		);

		dashedLine(
			$this->im,
			$this->shiftXleft + $position,
			$this->shiftY,
			$this->shiftXleft + $position,
			$this->sizeY + $this->shiftY,
			$this->getColor($this->graphtheme['maingridcolor'], 0)
		);
	}

	/**
	 * Draw main period label in black color with 7px font size under X axis and a 1px dashed gray vertical line
	 * according to that label.
	 *
	 * @param strimg $value     Readable timestamp.
	 * @param int    $position  Position on X axis.
	 */
	private function drawSubPeriod($value, $position) {
		$element = imageTextSize(7, 90, $value);

		imageText(
			$this->im,
			7,
			90,
			$this->shiftXleft + $position + round($element['width'] / 2),
			$this->sizeY + $this->shiftY + $element['height'] + 6,
			$this->getColor($this->graphtheme['textcolor'], 0),
			$value
		);

		dashedLine(
			$this->im,
			$this->shiftXleft + $position,
			$this->shiftY,
			$this->shiftXleft + $position,
			$this->sizeY + $this->shiftY,
			$this->getColor($this->graphtheme['gridcolor'], 0)
		);
	}

	/**
	 * Calculates the optimal size of time interval.
	 */
	private function calculateTimeInterval() {
		$time_interval = ($this->gridPixels * $this->period) / $this->sizeX;
		$intervals = [
			['main' => SEC_PER_MIN, 'sub' => SEC_PER_MIN / 60],			// minute and 1 second
			['main' => SEC_PER_MIN, 'sub' => SEC_PER_MIN / 12],			// minute and 5 seconds
			['main' => SEC_PER_MIN, 'sub' => SEC_PER_MIN / 6],			// 1 minute and 10 seconds
			['main' => SEC_PER_MIN, 'sub' => SEC_PER_MIN / 2],			// 1 minute and 30 seconds
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
			['main' => SEC_PER_WEEK, 'sub' => SEC_PER_DAY * 3],			// 1 week and 3 days
			['main' => SEC_PER_MONTH, 'sub' => SEC_PER_WEEK],			// 1 month and 1 week
			['main' => SEC_PER_MONTH, 'sub' => SEC_PER_WEEK * 2],		// 1 month and 2 weeks
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
		$this->grid['horizontal']['main']['interval'] = 0;
		$this->grid['horizontal']['sub']['interval'] = 0;

		foreach ($intervals as $interval) {
			$time = abs($interval['sub'] - $time_interval);

			if ($time < $distance) {
				$distance = $time;
				$this->grid['horizontal']['main']['interval'] = $interval['main'];
				$this->grid['horizontal']['sub']['interval'] = $interval['sub'];
			}
		}
	}

	/**
	 * Draw date and time intervals under the X axis.
	 */
	private function drawDateTimeIntervals() {
		$interval['sub'] = $this->grid['horizontal']['sub']['interval'];
		$interval['main'] = $this->grid['horizontal']['main']['interval'];

		// Sub interval title size.
		$element_size = imageTextSize(7, 90, 'WWW');

		$position = 0;
		$dt = [];
		$modifier = [];
		$format = [];

		foreach (['main', 'sub'] as $type) {
			$dt[$type] = new DateTime();
			$dt[$type]->setTimestamp($this->stime);

			if ($interval[$type] >= SEC_PER_YEAR) {
				$years = $interval[$type] / SEC_PER_YEAR;
				$year = (int) $dt[$type]->format('Y');
				$dt[$type]->modify('first day of January this year 00:00:00 -'.($year % $years).' year');
				$modifier[$type] = '+ '.$years.' year';
				$format[$type] = _x('Y', DATE_FORMAT_CONTEXT);
			}
			elseif ($interval[$type] >= SEC_PER_MONTH) {
				$months = $interval[$type] / SEC_PER_MONTH;
				$month = (int) $dt[$type]->format('m');
				$dt[$type]->modify('first day of this month 00:00:00 -'.(($month - 1) % $months).' month');
				$modifier[$type] = '+ '.$months.' month';
				$format[$type] = ($type == 'main') ? _('m-d') : _('M');
			}
			elseif ($interval[$type] >= SEC_PER_WEEK) {
				$weeks = $interval[$type] / SEC_PER_WEEK;
				$week = (int) $dt[$type]->format('W');
				$day_of_week = (int) $dt[$type]->format('w');
				$dt[$type]->modify('today -'.(($week - 1) % $weeks).' week -'.$day_of_week.' day');
				$modifier[$type] = '+ '.$weeks.' week';
				$format[$type] = _('m-d');
			}
			elseif ($interval[$type] >= SEC_PER_DAY) {
				$days = $interval[$type] / SEC_PER_DAY;
				$day = (int) $dt[$type]->format('d');
				$dt[$type]->modify('today -'.(($day - 1) % $days).' day');
				$modifier[$type] = '+ '.$days.' day';
				$format[$type] = _('m-d');
			}
			elseif ($interval[$type] >= SEC_PER_HOUR) {
				$hours = $interval[$type] / SEC_PER_HOUR;
				$hour = (int) $dt[$type]->format('H');
				$minute = (int) $dt[$type]->format('i');
				$second = (int) $dt[$type]->format('s');
				$dt[$type]->modify('-'.($hour % $hours).' hour -'.$minute.' minute -'.$second.' second');
				$modifier[$type] = '+ '.$hours.' hour';
				$format[$type] = TIME_FORMAT;
			}
			elseif ($interval[$type] >= SEC_PER_MIN) {
				$minutes = $interval[$type] / SEC_PER_MIN;
				$minute = (int) $dt[$type]->format('i');
				$second = (int) $dt[$type]->format('s');
				$dt[$type]->modify('-'.($minute % $minutes).' minute -'.$second.' second');
				$modifier[$type] = '+ '.$minutes.' min';
				$format[$type] = ($type == 'main') ? _('H:i:s') : TIME_FORMAT;
			}
			else {
				$seconds = $interval[$type];
				$second = (int) $dt[$type]->format('s');
				$dt[$type]->modify('-'.($second % $seconds).' second');
				$modifier[$type] = '+ '.$seconds.' second';
				$format[$type] = _('H:i:s');
			}
		}

		// It is necessary to align the X axis after the jump from winter to summer time.
		$prev_dst = (bool) $dt['sub']->format('I');
		$dst_offset = $dt['sub']->getOffset();
		$do_align = false;

		$prev_time = $this->stime;
		if ($interval['main'] == SEC_PER_MONTH) {
			$dt_start = new DateTime();
			$dt_start->setTimestamp($this->stime);
			$prev_month = (int) $dt_start->format('m');
		}

		while (true) {
			$dt['sub']->modify($modifier['sub']);

			if (SEC_PER_HOUR < $interval['sub'] && $interval['sub'] < SEC_PER_DAY) {
				if ($do_align) {
					$hours = $interval['sub'] / SEC_PER_HOUR;
					$hour = (int) $dt['sub']->format('H');
					if ($hour % $hours) {
						$dt['sub']->modify($dst_offset.' second');
					}

					$do_align = false;
				}

				$dst = (bool) $dt['sub']->format('I');

				if ($dst && $prev_dst != $dst) {
					$dst_offset -= $dt['sub']->getOffset();
					$do_align = $interval['sub'] > abs($dst_offset);
					$prev_dst = $dst;
				}
			}

			if ($dt['main'] < $dt['sub']) {
				$dt['main']->modify($modifier['main']);
			}

			if ($interval['main'] == SEC_PER_MONTH) {
				$month = (int) $dt['sub']->format('m');

				$draw_main = ($month != $prev_month);
				$prev_month = $month;
			}
			else {
				$draw_main = ($dt['main'] == $dt['sub']);
			}
			$time = $dt['sub']->format('U');

			$delta_x = bcsub($time, $prev_time) * $this->sizeX / $this->period;
			$position += $delta_x;

			// First element overlaping check.
			if ($prev_time != $this->stime || $delta_x > $element_size['width']) {
				// Last element overlaping check.
				if ($position > $this->sizeX - $element_size['width']) {
					break;
				}

				if ($draw_main) {
					$this->drawMainPeriod($dt['sub']->format($format['main']), $position);
				}
				else {
					$this->drawSubPeriod($dt['sub']->format($format['sub']), $position);
				}
			}

			$prev_time = $time;
		}
	}

	private function drawSides() {
		if (isset($this->axis_valuetype[GRAPH_YAXIS_SIDE_RIGHT])
				&& ($this->yaxis[GRAPH_YAXIS_SIDE_RIGHT] || $this->skipRightScale != 1)) {
			$sides[] = GRAPH_YAXIS_SIDE_RIGHT;
		}

		if (((isset($this->axis_valuetype[GRAPH_YAXIS_SIDE_LEFT]))
				&& ($this->yaxis[GRAPH_YAXIS_SIDE_LEFT] || $this->skipLeftScale != 1)) || !isset($sides)) {
			$sides[] = GRAPH_YAXIS_SIDE_LEFT;
		}

		foreach ($sides as $side) {
			$minY = $this->m_minY[$side];
			$maxY = $this->m_maxY[$side];
			$units = null;
			$unitsLong = null;
			$byteStep = false;

			for ($item = 0; $item < $this->num; $item++) {
				if ($this->items[$item]['yaxisside'] == $side) {
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
					if ($this->items[$item]['yaxisside'] == $side && !empty($this->items[$item]['unitsLong'])) {
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
				$maxYPow = convertToBase1024($maxY, ZBX_KIBIBYTE);
				$minYPow = convertToBase1024($minY, ZBX_KIBIBYTE);
				$powStep = ZBX_KIBIBYTE;
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
			$this->sizeX + $this->shiftXleft - 1, // -2 border
			$this->sizeY + $this->shiftY,
			$this->getColor($this->graphtheme['graphcolor'], 0)
		);

		if ($this->m_showWorkPeriod != 1) {
			return;
		}
		if ($this->period > 8035200) { // 31*24*3600*3 (3*month*3)
			return;
		}

		$config = select_config();
		$config = CMacrosResolverHelper::resolveTimeUnitMacros([$config], ['work_period'])[0];

		$periods = parse_period($config['work_period']);
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
			$this->to_time = $now;
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
			return;
		}

		foreach ($this->percentile as $side => $percentile) {
			if ($percentile['percent'] > 0 && $percentile['value']) {
				$minY = $this->m_minY[$side];
				$maxY = $this->m_maxY[$side];

				$color = ($side == GRAPH_YAXIS_SIDE_LEFT)
					? $this->graphtheme['leftpercentilecolor']
					: $this->graphtheme['rightpercentilecolor'];

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

		$oppColor = $this->getColor(GRAPH_TRIGGER_LINE_OPPOSITE_COLOR);

		foreach ($this->triggers as $trigger) {
			$minY = $this->m_minY[$trigger['yaxisside']];
			$maxY = $this->m_maxY[$trigger['yaxisside']];

			if ($minY >= $trigger['val'] || $trigger['val'] >= $maxY) {
				continue;
			}

			$y = $this->sizeY - (($trigger['val'] - $minY) / ($maxY - $minY)) * $this->sizeY + $this->shiftY;
			$triggerColor = $this->getColor($trigger['color']);
			$lineStyle = [$triggerColor, $triggerColor, $triggerColor, $triggerColor, $triggerColor, $oppColor, $oppColor, $oppColor];

			dashedLine( $this->im, $this->shiftXleft, $y, $this->sizeX + $this->shiftXleft, $y, $lineStyle);
			dashedLine( $this->im, $this->shiftXleft, $y + 1, $this->sizeX + $this->shiftXleft, $y + 1, $lineStyle);
		}
	}

	protected function drawLegend() {
		// if graph is small, we are not drawing legend
		if (!$this->drawItemsLegend) {
			return true;
		}

		$leftXShift = 15;
		$units = [GRAPH_YAXIS_SIDE_LEFT => 0, GRAPH_YAXIS_SIDE_RIGHT => 0];

		// draw item legend
		$legend = new CImageTextTable($this->im, $leftXShift - 5, $this->sizeY + $this->shiftY + self::legendOffsetY);
		$legend->color = $this->getColor($this->graphtheme['textcolor'], 0);
		$legend->rowheight = 14;
		$legend->fontsize = 9;

		// item legend table header
		$row = [
			['text' => '', 'marginRight' => 5],
			['text' => ''],
			['text' => ''],
			['text' => _('last'), 'align' => 1, 'fontsize' => 9],
			['text' => _('min'), 'align' => 1, 'fontsize' => 9],
			['text' => _('avg'), 'align' => 1, 'fontsize' => 9],
			['text' => _('max'), 'align' => 1, 'fontsize' => 9]
		];

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
				if ($this->items[$i]['yaxisside'] == GRAPH_YAXIS_SIDE_LEFT) {
					$units[GRAPH_YAXIS_SIDE_LEFT] = $this->items[$i]['units'];
				}
				else {
					$units[GRAPH_YAXIS_SIDE_RIGHT] = $this->items[$i]['units'];
				}

				$legend->addCell($rowNum, ['image' => $colorSquare, 'marginRight' => 5]);
				$legend->addCell($rowNum, ['text' => $itemCaption]);
				$legend->addCell($rowNum, ['text' => '['.$fncRealName.']']);
				$legend->addCell($rowNum, [
					'text' => convert_units([
						'value' => $this->getLastValue($i),
						'units' => $this->items[$i]['units'],
						'convert' => ITEM_CONVERT_NO_UNITS
					]),
					'align' => 2
				]);
				$legend->addCell($rowNum, [
					'text' => convert_units([
						'value' => min($data['min']),
						'units' => $this->items[$i]['units'],
						'convert' => ITEM_CONVERT_NO_UNITS
					]),
					'align' => 2
				]);
				$legend->addCell($rowNum, [
					'text' => convert_units([
						'value' => $data['avg_orig'],
						'units' => $this->items[$i]['units'],
						'convert' => ITEM_CONVERT_NO_UNITS
					]),
					'align' => 2
				]);
				$legend->addCell($rowNum, [
					'text' => convert_units([
						'value' => max($data['max']),
						'units' => $this->items[$i]['units'],
						'convert' => ITEM_CONVERT_NO_UNITS
					]),
					'align' => 2
				]);
			}
			// draw legend of an item without data
			else {
				$legend->addCell($rowNum, ['image' => $colorSquare, 'marginRight' => 5]);
				$legend->addCell($rowNum, ['text' => $itemCaption]);
				$legend->addCell($rowNum, ['text' => '['._('no data').']']);
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
		if (!$this->drawExLegend) {
			return true;
		}

		$legend = new CImageTextTable(
			$this->im,
			$leftXShift + 10,
			$this->sizeY + $this->shiftY + 14 * $rowNum + self::legendOffsetY
		);
		$legend->color = $this->getColor($this->graphtheme['textcolor'], 0);
		$legend->rowheight = 14;
		$legend->fontsize = 9;

		// draw percentile
		if ($this->type == GRAPH_TYPE_NORMAL) {
			foreach ($this->percentile as $side => $percentile) {
				if ($percentile['percent'] > 0 && $this->yaxis[$side]) {
					$percentile['percent'] = (float) $percentile['percent'];
					$convertedUnit = $percentile['value']
						? convert_units([
							'value' => $percentile['value'],
							'units' => $units[$side]
						])
						: '-';
					$side_str = ($side == GRAPH_YAXIS_SIDE_LEFT) ? _('left') : _('right');
					$legend->addCell($rowNum, [
						'text' => $percentile['percent'].'th percentile: '.$convertedUnit.' ('.$side_str.')',
						ITEM_CONVERT_NO_UNITS
					]);
					$color = ($side == GRAPH_YAXIS_SIDE_LEFT)
						? $this->graphtheme['leftpercentilecolor']
						: $this->graphtheme['rightpercentilecolor'];

					imagefilledpolygon(
						$this->im,
						[
							$leftXShift + 5, $this->sizeY + $this->shiftY + 14 * $rowNum + self::legendOffsetY,
							$leftXShift - 5, $this->sizeY + $this->shiftY + 14 * $rowNum + self::legendOffsetY,
							$leftXShift, $this->sizeY + $this->shiftY + 14 * $rowNum + self::legendOffsetY - 10
						],
						3,
						$this->getColor($color)
					);

					imagepolygon(
						$this->im,
						[
							$leftXShift + 5, $this->sizeY + $this->shiftY + 14 * $rowNum + self::legendOffsetY,
							$leftXShift - 5, $this->sizeY + $this->shiftY + 14 * $rowNum + self::legendOffsetY,
							$leftXShift, $this->sizeY + $this->shiftY + 14 * $rowNum + self::legendOffsetY - 10
						],
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
			$this->sizeY + $this->shiftY + 14 * $rowNum + self::legendOffsetY + 5
		);
		$legend->color = $this->getColor($this->graphtheme['textcolor'], 0);
		$legend->rowheight = 14;
		$legend->fontsize = 9;

		// draw triggers
		foreach ($this->triggers as $trigger) {
			imagefilledellipse(
				$this->im,
				$leftXShift,
				$this->sizeY + $this->shiftY + 14 * $rowNum + self::legendOffsetY,
				10,
				10,
				$this->getColor($trigger['color'])
			);

			imageellipse(
				$this->im,
				$leftXShift,
				$this->sizeY + $this->shiftY + 14 * $rowNum + self::legendOffsetY,
				10,
				10,
				$this->getColor('Black No Alpha')
			);

			$legend->addRow([
				['text' => $trigger['description']],
				['text' => $trigger['constant']]
			]);
			$rowNum++;
		}

		$legend->draw();
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

	protected function drawElement(&$data, $from, $to, $minX, $maxX, $minY, $maxY, $drawtype, $max_color, $avg_color, $min_color, $minmax_color, $calc_fnc, $yaxisside) {
		if (!isset($data['max'][$from]) || !isset($data['max'][$to])) {
			return;
		}

		$oxy = $this->oxy[$yaxisside];
		$zero = $this->zero[$yaxisside];
		$unit2px = $this->unit2px[$yaxisside];

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
					$style = [$avg_color, $avg_color, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT];
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

	private function calcSides() {
		$sides = [];

		if (array_key_exists(GRAPH_YAXIS_SIDE_RIGHT, $this->axis_valuetype)) {
			$sides[] = GRAPH_YAXIS_SIDE_RIGHT;
		}
		if (array_key_exists(GRAPH_YAXIS_SIDE_LEFT, $this->axis_valuetype) || !$sides) {
			$sides[] = GRAPH_YAXIS_SIDE_LEFT;
		}

		foreach ($sides as $side) {
			$this->m_minY[$side] = $this->calculateMinY($side);
			$this->m_maxY[$side] = $this->calculateMaxY($side);

			if ($this->m_minY[$side] === null) {
				$this->m_minY[$side] = 0;
			}
			if ($this->m_maxY[$side] === null) {
				$this->m_maxY[$side] = 1;
			}

			if ($this->m_minY[$side] == $this->m_maxY[$side]) {
				if ($this->graphOrientation[$side] == '-') {
					$this->m_maxY[$side] = 0;
				}
				elseif ($this->m_minY[$side] == 0) {
					$this->m_maxY[$side] = 1;
				}
				else {
					$this->m_minY[$side] = 0;
				}
			}
			elseif ($this->m_minY[$side] > $this->m_maxY[$side]) {
				if ($this->graphOrientation[$side] == '-') {
					$this->m_minY[$side] = bcmul($this->m_maxY[$side], 0.2);
				}
				else {
					$this->m_minY[$side] = 0;
				}
			}

			// If max Y-scale bigger min Y-scale only for 10% or less, then we don't allow Y-scale duplicate
			if ($this->m_maxY[$side] && $this->m_minY[$side]) {
				if ($this->m_minY[$side] < 0) {
					$absMinY = bcmul($this->m_minY[$side], '-1');
				}
				else {
					$absMinY = $this->m_minY[$side];
				}
				if ($this->m_maxY[$side] < 0) {
					$absMaxY = bcmul($this->m_maxY[$side], '-1');
				}
				else {
					$absMaxY = $this->m_maxY[$side];
				}

				if ($absMaxY < $absMinY) {
					$oldAbMaxY = $absMaxY;
					$absMaxY = $absMinY;
					$absMinY = $oldAbMaxY;
				}
			}
		}
	}

	private function calcDimentions() {
		$this->shiftXleft = $this->yaxis[GRAPH_YAXIS_SIDE_LEFT] ? 85 : 30;
		$this->shiftXright = $this->yaxis[GRAPH_YAXIS_SIDE_RIGHT] ? 85 : 30;

		$x_offsets = $this->shiftXleft + $this->shiftXright + 1;
		$y_offsets = $this->shiftY + self::legendOffsetY;

		if (!$this->with_vertical_padding) {
			$y_offsets -= ($this->m_showTriggers && count($this->triggers) > 0)
				? static::DEFAULT_TOP_BOTTOM_PADDING / 2
				: static::DEFAULT_TOP_BOTTOM_PADDING;
		}

		$this->fullSizeX = $this->sizeX;
		$this->fullSizeY = $this->sizeY;

		if ($this->drawLegend) {
			// Reserve N+1 item rows, last row is used as padding for legend.
			$h_legend_items = 14 * $this->num + 14;
			$h_legend_triggers = 14 * count($this->triggers);
			$h_legend_percentile = 0;

			foreach ($this->percentile as $side => $percentile) {
				if ($percentile['percent'] > 0 && $this->yaxis[$side]) {
					$h_legend_percentile += 14;
				}
			}
		}

		if ($this->outer) {
			$this->sizeX = $this->fullSizeX - $x_offsets;
			$this->sizeY = $this->fullSizeY - $y_offsets;

			if ($this->drawLegend) {
				if ($this->sizeY - $h_legend_items >= self::GRAPH_HEIGHT_MIN) {
					$this->sizeY -= $h_legend_items;
					$this->drawItemsLegend = true;

					if ($this->sizeY - $h_legend_triggers - $h_legend_percentile >= self::GRAPH_HEIGHT_MIN) {
						$this->sizeY -= $h_legend_triggers + $h_legend_percentile;
						$this->drawExLegend = true;
					}
				}
			}
		}
		else {
			$this->fullSizeX += $x_offsets;
			$this->fullSizeY += $y_offsets;

			if ($this->drawLegend) {
				$this->fullSizeY += $h_legend_items;
				$this->drawItemsLegend = true;

				if ($this->sizeY >= ZBX_GRAPH_LEGEND_HEIGHT) {
					$this->fullSizeY += $h_legend_triggers + $h_legend_percentile;
					$this->drawExLegend = true;
				}
			}
		}
	}

	public function getMinDimensions() {
		$min_dimentions = [
			'width' => self::GRAPH_WIDTH_MIN,
			'height' => self::GRAPH_HEIGHT_MIN
		];

		if ($this->outer) {
			$min_dimentions['width'] += $this->yaxis[GRAPH_YAXIS_SIDE_LEFT] ? 85 : 30;
			$min_dimentions['width'] += $this->yaxis[GRAPH_YAXIS_SIDE_RIGHT] ? 85 : 30;
			$min_dimentions['width'] ++;
			$min_dimentions['height'] += $this->shiftY + self::legendOffsetY;
		}

		return $min_dimentions;
	}

	/**
	 * Expands graph item objects data: macros in item name, time units, dependent item
	 *
	 */
	private function expandItems() {
		$items_cache = zbx_toHash($this->items, 'itemid');
		$items = $this->items;

		do {
			$master_itemids = [];

			foreach ($items as $item) {
				if ($item['type'] == ITEM_TYPE_DEPENDENT && !array_key_exists($item['master_itemid'], $items_cache)) {
					$master_itemids[$item['master_itemid']] = true;
				}
				$items_cache[$item['itemid']] = $item;
			}
			$master_itemids = array_keys($master_itemids);

			$items = API::Item()->get([
				'output' => ['itemid', 'type', 'master_itemid', 'delay'],
				'itemids' => $master_itemids
			]);
		} while ($items);

		$update_interval_parser = new CUpdateIntervalParser();

		foreach ($this->items as &$graph_item) {
			if ($graph_item['type'] == ITEM_TYPE_DEPENDENT) {
				$master_item = $graph_item;

				while ($master_item && $master_item['type'] == ITEM_TYPE_DEPENDENT) {
					$master_item = $items_cache[$master_item['master_itemid']];
				}
				$graph_item['type'] = $master_item['type'];
				$graph_item['delay'] = $master_item['delay'];
			}

			$graph_items = CMacrosResolverHelper::resolveItemNames([$graph_item]);
			$graph_items = CMacrosResolverHelper::resolveTimeUnitMacros($graph_items, ['delay']);
			$graph_item = reset($graph_items);
			$graph_item['name'] = $graph_item['name_expanded'];
			// getItemDelay will internally convert delay and flexible delay to seconds.
			$update_interval_parser->parse($graph_item['delay']);
			$graph_item['delay'] = getItemDelay($update_interval_parser->getDelay(),
				$update_interval_parser->getIntervals(ITEM_DELAY_FLEXIBLE)
			);
			$graph_item['has_scheduling_intervals']
				= (bool) $update_interval_parser->getIntervals(ITEM_DELAY_SCHEDULING);

			if (strpos($graph_item['units'], ',') === false) {
				$graph_item['unitsLong'] = '';
			}
			else {
				list($graph_item['units'], $graph_item['unitsLong']) = explode(',', $graph_item['units']);
			}
		}
		unset($graph_item);
	}

	/**
	 * Calculate graph dimensions and draw 1x1 pixel image placeholder.
	 */
	public function drawDimensions() {
		set_image_header();

		$this->calculateTopPadding();
		$this->selectTriggers();
		$this->calcDimentions();

		if (function_exists('imagecolorexactalpha') && function_exists('imagecreatetruecolor')
				&& @imagecreatetruecolor(1, 1)
		) {
			$this->im = imagecreatetruecolor(1, 1);
		}
		else {
			$this->im = imagecreate(1, 1);
		}

		$this->initColors();

		imageOut($this->im);
	}

	public function draw() {
		$debug_mode = CWebUser::getDebugMode();
		if ($debug_mode) {
			$start_time = microtime(true);
		}

		set_image_header();
		$this->calculateTopPadding();

		// $this->sizeX is required for selectData() method
		$this->expandItems();
		$this->selectTriggers();
		$this->calcDimentions();
		$this->selectData();

		$this->calcSides();
		$this->calcPercentile();
		$this->calcMinMaxInterval();
		$this->calcZero();

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
		$this->drawXYAxisScale();

		$maxX = $this->sizeX;

		if ($this->dataFrom === 'trends') {
			// Correct item 'delay' field value when graph data requested for trends.
			foreach ($this->items as &$item) {
				if (!$item['has_scheduling_intervals'] || $item['delay'] != 0) {
					$item['delay'] = max($item['delay'], SEC_PER_HOUR);
				}
			}
			unset($item);
		}

		// for each metric
		for ($item = 0; $item < $this->num; $item++) {
			$minY = $this->m_minY[$this->items[$item]['yaxisside']];
			$maxY = $this->m_maxY[$this->items[$item]['yaxisside']];

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
			$prevDraw = true;
			for ($i = 1, $j = 0; $i < $maxX; $i++) { // new point
				if ($data['count'][$i] == 0 && $i != ($maxX - 1)) {
					continue;
				}

				$delay = $this->items[$item]['delay'];

				if ($this->items[$item]['type'] == ITEM_TYPE_TRAPPER
						|| ($this->items[$item]['has_scheduling_intervals'] && $delay == 0)) {
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
						$this->items[$item]['yaxisside']
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

		if ($debug_mode) {
			$str = sprintf('%0.2f', microtime(true) - $start_time);
			imageText($this->im, 6, 90, $this->fullSizeX - 2, $this->fullSizeY - 5, $this->getColor('Gray'),
				_s('Data from %1$s. Generated in %2$s sec.', $this->dataFrom, $str)
			);
		}

		unset($this->items, $this->data);

		imageOut($this->im);
	}
}
