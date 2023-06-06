<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
	const LEGEND_OFFSET_Y = 90;

	private $cell_height_min;
	private $cell_width;
	private $drawExLegend;
	private $drawItemsLegend;
	private $intervals;
	private $is_binary;
	private $itemsHost;
	private $outer;
	private $oxy;
	private $percentile;
	private $power;
	private $show_triggers;
	private $show_work_period;
	private $triggers;
	private $unit2px;
	private $yaxis;
	private $yaxismin;
	private $yaxismax;
	private $ymin_itemid;
	private $ymax_itemid;
	private $ymin_type;
	private $ymax_type;
	private $zero;

	public function __construct($type = GRAPH_TYPE_NORMAL) {
		parent::__construct($type);

		$this->triggers = [];

		$this->yaxis = [
			GRAPH_YAXIS_SIDE_LEFT => false,
			GRAPH_YAXIS_SIDE_RIGHT => false
		];

		$this->ymin_type = GRAPH_YAXIS_TYPE_CALCULATED;
		$this->ymax_type = GRAPH_YAXIS_TYPE_CALCULATED;

		$this->yaxismin = null;
		$this->yaxismax = null;

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

		$this->show_work_period = true;
		$this->show_triggers = true;

		$this->zero = [];

		$this->cell_width = 30;
		$this->cell_height_min = 30;

		$this->intervals = [];
		$this->power = [];
		$this->is_binary = [];

		$this->drawItemsLegend = false; // draw items legend
		$this->drawExLegend = false; // draw percentile and triggers legend
	}

	public function showWorkPeriod($value) {
		$this->show_work_period = $value;
	}

	public function showTriggers($value) {
		$this->show_triggers = $value;
	}

	/**
	 * Add single item object to graph. If invalid 'delay' interval passed method will interrupt current request with
	 * error message.
	 *
	 * @param array  $graph_item                   Array of graph item properties.
	 * @param string $graph_item['itemid']         Item id.
	 * @param string $graph_item['type']           Item type.
	 * @param string $graph_item['name']           Item host display name.
	 * @param string $graph_item['hostname']       Item hostname.
	 * @param string $graph_item['key_']           Item key_ field value.
	 * @param string $graph_item['value_type']     Item value type.
	 * @param string $graph_item['history']        Item history field value.
	 * @param string $graph_item['trends']         Item trends field value.
	 * @param string $graph_item['delay']          Item delay.
	 * @param string $graph_item['master_itemid']  Master item id for item of type ITEM_TYPE_DEPENDENT.
	 * @param string $graph_item['units']          Item units value.
	 * @param string $graph_item['hostid']         Item host id.
	 * @param string $graph_item['hostname']       Item host name.
	 * @param string $graph_item['color']          Item presentation color.
	 * @param int    $graph_item['drawtype']       Item presentation draw type, could be one of
	 *                                             GRAPH_ITEM_DRAWTYPE_* constants.
	 * @param int    $graph_item['yaxisside']      Item axis side, could be one of GRAPH_YAXIS_SIDE_* constants.
	 * @param int    $graph_item['calc_fnc']       Item calculation function, could be one of CALC_FNC_* constants.
	 * @param int    $graph_item['calc_type']      Item graph presentation calculation type, GRAPH_ITEM_SIMPLE or
	 *                                             GRAPH_ITEM_SUM.
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

	/**
	 * Interpret width/height as image size; or as the graph size, if the argument is false.
	 *
	 * @param bool $outer
	 */
	public function setOuter($outer) {
		$this->outer = $outer;
	}

	/**
	 * Get list of vertical scales in use, starting from the main one.
	 *
	 * @return array
	 */
	private function getVerticalScalesInUse() {
		return array_keys(array_filter($this->yaxis));
	}

	protected function selectData() {
		$this->data = [];

		$time_now = time();

		if ($this->stime === null) {
			$this->stime = $time_now - $this->period;
		}

		$this->from_time = $this->stime;
		$this->to_time = $this->stime + $this->period;

		$this->itemsHost = null;

		$items = [];

		for ($i = 0; $i < $this->num; $i++) {
			$item = $this->items[$i];

			if ($this->itemsHost === null) {
				// Select item host for graph caption.
				$this->itemsHost = $item['hostid'];
			}
			elseif ($this->itemsHost != $item['hostid']) {
				// Do not select any item host for graph caption, if more than one item host.
				$this->itemsHost = false;
			}

			$to_resolve = [];

			// Override item history setting with housekeeping settings, if they are enabled in config.
			if (CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_GLOBAL)) {
				$item['history'] = timeUnitToSeconds(CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY));
			}
			else {
				$to_resolve[] = 'history';
			}

			if (CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS_GLOBAL)) {
				$item['trends'] = timeUnitToSeconds(CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS));
			}
			else {
				$to_resolve[] = 'trends';
			}

			// Otherwise, resolve user macro and parse the string. If successful, convert to seconds.
			if ($to_resolve) {
				$item = CMacrosResolverHelper::resolveTimeUnitMacros([$item], $to_resolve)[0];

				$simple_interval_parser = new CSimpleIntervalParser();

				if (!CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_GLOBAL)) {
					if ($simple_interval_parser->parse($item['history']) != CParser::PARSE_SUCCESS) {
						show_error_message(_s('Incorrect value for field "%1$s": %2$s.', 'history',
							_('invalid history storage period')
						));
						exit;
					}
					$item['history'] = timeUnitToSeconds($item['history']);
				}

				if (!CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS_GLOBAL)) {
					if ($simple_interval_parser->parse($item['trends']) != CParser::PARSE_SUCCESS) {
						show_error_message(_s('Incorrect value for field "%1$s": %2$s.', 'trends',
							_('invalid trend storage period')
						));
						exit;
					}
					$item['trends'] = timeUnitToSeconds($item['trends']);
				}
			}

			$item['source'] = ($item['trends'] == 0 || (($time_now - $item['history']) < $this->from_time
					&& ($this->period / $this->sizeX) <= (ZBX_MAX_TREND_DIFF / ZBX_GRAPH_MAX_SKIP_CELL)))
				? 'history'
				: 'trends';

			$this->items[$i]['source'] = $item['source'];

			$items[] = $item;
		}

		$results = Manager::History()->getGraphAggregationByWidth($items, $this->from_time, $this->to_time,
			$this->sizeX
		);

		foreach ($items as $item) {
			$data = [
				'count' => [],
				'min' => [],
				'max' => [],
				'avg' => [],
				'clock' => []
			];

			if (array_key_exists($item['itemid'], $results)) {
				$result = $results[$item['itemid']];

				foreach ($result['data'] as $data_row) {
					$idx = $data_row['i'];

					$data['count'][$idx] = $data_row['count'];
					$data['min'][$idx] = (float) $data_row['min'];
					$data['max'][$idx] = (float) $data_row['max'];
					$data['avg'][$idx] = (float) $data_row['avg'];
					$data['clock'][$idx] = $data_row['clock'];
					$data['shift_min'][$idx] = 0;
					$data['shift_max'][$idx] = 0;
					$data['shift_avg'][$idx] = 0;
				}
			}

			$data['avg_orig'] = $data['avg'] ? CMathHelper::safeAvg($data['avg']) : null;

			/*
				first_idx - last existing point
				ci - current index
				cj - count of missed in one go
				dx - offset to first value (count to last existing point)
			*/
			for ($ci = 0, $cj = 0; $ci <= $this->sizeX; $ci++) {
				if (!array_key_exists($ci, $data['count']) || ($data['count'][$ci] == 0)) {
					$data['count'][$ci] = 0;
					$data['shift_min'][$ci] = 0;
					$data['shift_max'][$ci] = 0;
					$data['shift_avg'][$ci] = 0;
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
						$data['count'][$ci - ($dx - $cj)] = 1;
					}

					foreach (['clock', 'min', 'max', 'avg'] as $var_name) {
						if ($first_idx == $ci && $var_name == 'clock') {
							$data['clock'][$ci - ($dx - $cj)] = $data['clock'][$first_idx] -
								($this->to_time - $this->from_time) / $this->sizeX * ($dx - $cj);

							continue;
						}

						$data[$var_name][$ci - ($dx - $cj)] = CMathHelper::safeSum([
							$data[$var_name][$first_idx],
							CMathHelper::safeMul([$cj, 1 / $dx, $data[$var_name][$ci]]),
							CMathHelper::safeMul([$cj, 1 / $dx, -$data[$var_name][$first_idx]])
						]);
					}
				}
			}

			if ($cj > 0 && $ci > $cj) {
				$dx = $cj + 1;
				$first_idx = $ci - $dx;

				for(; $cj > 0; $cj--) {
					foreach (['clock', 'min', 'max', 'avg'] as $var) {
						$data[$var][$first_idx + ($dx - $cj)] = ($var == 'clock')
							? $data[$var][$first_idx] + ($this->to_time - $this->from_time) / $this->sizeX * ($dx - $cj)
							: $data[$var][$first_idx];
					}
				}
			}

			$this->data[$item['itemid']] = $data;
		}

		// calculate shift for stacked graphs
		if ($this->type == GRAPH_TYPE_STACKED) {
			for ($i = 1; $i < $this->num; $i++) {
				$item1 = $this->items[$i];

				if (!array_key_exists($item1['itemid'], $this->data)) {
					continue;
				}

				$curr_data = &$this->data[$item1['itemid']];

				for ($j = $i - 1; $j >= 0; $j--) {
					$item2 = $this->items[$j];

					if ($item2['yaxisside'] != $item1['yaxisside']) {
						continue;
					}

					if (!array_key_exists($item2['itemid'], $this->data)) {
						continue;
					}

					$prev_data = &$this->data[$item2['itemid']];

					for ($ci = 0; $ci <= $this->sizeX; $ci++) {
						foreach (['min', 'max', 'avg'] as $var_name) {
							$shift_var_name = 'shift_'.$var_name;
							$curr_shift = &$curr_data[$shift_var_name];
							$prev_shift = &$prev_data[$shift_var_name];
							$prev_var = &$prev_data[$var_name];

							$prev_var_ci = $prev_var ? $prev_var[$ci] : 0;
							$prev_shift_ci = $prev_shift ? $prev_shift[$ci] : 0;
							$curr_shift[$ci] = $prev_var_ci + $prev_shift_ci;
						}
					}

					break;
				}
			}
		}
	}

	protected function selectTriggers() {
		$this->triggers = [];

		if (!$this->show_triggers) {
			return;
		}

		$number_parser = new CNumberParser(['with_size_suffix' => true, 'with_time_suffix' => true]);

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
				$fnc_cnt = DBfetch(DBselect(
					'SELECT COUNT(*) AS cnt'.
					' FROM functions f'.
					' WHERE f.triggerid='.zbx_dbstr($trigger['triggerid'])
				));

				if ($fnc_cnt['cnt'] != 1) {
					continue;
				}

				$trigger['expression'] = CMacrosResolverHelper::resolveTriggerExpressions([$trigger],
					['resolve_usermacros' => true, 'resolve_functionids' => false]
				)[0]['expression'];

				if (!preg_match('/^\{\d+\}\s*(?<operator>[><]=?|=)\s*(?<constant>.*)$/', $trigger['expression'],
						$matches)) {
					continue;
				}

				if ($number_parser->parse($matches['constant']) != CParser::PARSE_SUCCESS) {
					continue;
				}

				$this->triggers[] = [
					'yaxisside' => $item['yaxisside'],
					'val' => $number_parser->calcValue(),
					'color' => CSeverityHelper::getColor((int) $trigger['priority']),
					'description' => _('Trigger').NAME_DELIMITER.CMacrosResolverHelper::resolveTriggerName($trigger),
					'constant' => '['.$matches['operator'].' '.$matches['constant'].']'
				];

				$cnt++;
			}
		}
	}

	// calculates percentages for left & right Y axis
	protected function calcPercentile() {
		if ($this->type != GRAPH_TYPE_NORMAL) {
			return;
		}

		$values = [
			GRAPH_YAXIS_SIDE_LEFT => [],
			GRAPH_YAXIS_SIDE_RIGHT => []
		];

		$maxX = $this->sizeX;

		// for each metric
		for ($i = 0; $i < $this->num; $i++) {
			if (!array_key_exists($this->items[$i]['itemid'], $this->data)) {
				continue;
			}

			$data = &$this->data[$this->items[$i]['itemid']];

			// for each X
			for ($j = 0; $j < $maxX; $j++) { // new point
				if ($data['count'][$j] == 0) {
					continue;
				}

				switch ($this->items[$i]['calc_fnc']) {
					case CALC_FNC_MAX:
						$value = $data['max'][$j];
						break;
					case CALC_FNC_MIN:
						$value = $data['min'][$j];
						break;
					case CALC_FNC_ALL:
					case CALC_FNC_AVG:
					default:
						$value = $data['avg'][$j];
				}

				$values[$this->items[$i]['yaxisside']][] = $value;
			}
		}

		foreach ($this->percentile as $side => $percentile) {
			if ($percentile['percent'] > 0 && $values[$side]) {
				sort($values[$side]);

				// Using "Nearest Rank" method.
				$percent = (int) ceil($percentile['percent'] / 100 * count($values[$side]));
				$this->percentile[$side]['value'] = $values[$side][$percent - 1];
			}
		}
	}

	// calculation of minimum Y of a side (left/right)
	protected function calculateMinY($side) {
		if ($this->ymin_type == GRAPH_YAXIS_TYPE_FIXED) {
			return $this->yaxismin;
		}

		if ($this->ymin_type == GRAPH_YAXIS_TYPE_ITEM_VALUE && $this->ymin_itemid != 0) {
			$items = API::Item()->get([
				'output' => ['itemid', 'value_type'],
				'itemids' => [$this->ymin_itemid],
				'webitems' => true
			]);

			if ($items) {
				$history = Manager::History()->getLastValues($items);

				if ($history) {
					return $history[$items[0]['itemid']][0]['value'];
				}
			}
			else {
				$this->ymin_type = GRAPH_YAXIS_TYPE_CALCULATED;
			}
		}

		$minY = null;
		for ($i = 0; $i < $this->num; $i++) {
			if ($this->items[$i]['yaxisside'] != $side) {
				continue;
			}

			if ($this->items[$i]['calc_type'] != GRAPH_ITEM_SIMPLE) {
				continue;
			}

			if (!array_key_exists($this->items[$i]['itemid'], $this->data)) {
				continue;
			}

			$data = &$this->data[$this->items[$i]['itemid']];

			$calc_fnc = $this->items[$i]['calc_fnc'];

			switch ($calc_fnc) {
				case CALC_FNC_ALL:
				case CALC_FNC_MIN:
					$values = $data['min'];
					$shift_values = $data['shift_min'];
					break;
				case CALC_FNC_MAX:
					$values = $data['max'];
					$shift_values = $data['shift_max'];
					break;
				case CALC_FNC_AVG:
				default:
					$values = $data['avg'];
					$shift_values = $data['shift_avg'];
			}

			if (!$values) {
				continue;
			}

			if ($this->type == GRAPH_TYPE_STACKED) {
				foreach ($values as $ci => &$value) {
					if ($data['count'][$ci] == 0) {
						continue;
					}
					$value += $shift_values[$ci];
				}
				unset($value);
			}

			$minY = ($minY === null) ? min($values) : min($minY, min($values));
		}

		return $minY;
	}

	// calculation of maximum Y of a side (left/right)
	protected function calculateMaxY($side) {
		if ($this->ymax_type == GRAPH_YAXIS_TYPE_FIXED) {
			return $this->yaxismax;
		}

		if ($this->ymax_type == GRAPH_YAXIS_TYPE_ITEM_VALUE && $this->ymax_itemid != 0) {
			$items = API::Item()->get([
				'output' => ['itemid', 'value_type'],
				'itemids' => [$this->ymax_itemid],
				'webitems' => true
			]);

			if ($items) {
				$history = Manager::History()->getLastValues($items);

				if ($history) {
					return $history[$items[0]['itemid']][0]['value'];
				}
			}
			else {
				$this->ymax_type = GRAPH_YAXIS_TYPE_CALCULATED;
			}
		}

		$maxY = null;
		for ($i = 0; $i < $this->num; $i++) {
			if ($this->items[$i]['yaxisside'] != $side) {
				continue;
			}

			if ($this->items[$i]['calc_type'] != GRAPH_ITEM_SIMPLE) {
				continue;
			}

			if (!array_key_exists($this->items[$i]['itemid'], $this->data)) {
				continue;
			}

			$data = &$this->data[$this->items[$i]['itemid']];

			$calc_fnc = $this->items[$i]['calc_fnc'];

			switch ($calc_fnc) {
				case CALC_FNC_ALL:
				case CALC_FNC_MAX:
					$values = $data['max'];
					$shift_values = $data['shift_max'];
					break;
				case CALC_FNC_MIN:
					$values = $data['min'];
					$shift_values = $data['shift_min'];
					break;
				case CALC_FNC_AVG:
				default:
					$values = $data['avg'];
					$shift_values = $data['shift_avg'];
			}

			if (!$values) {
				continue;
			}

			if ($this->type == GRAPH_TYPE_STACKED) {
				foreach ($values as $ci => &$value) {
					if ($data['count'][$ci] == 0) {
						continue;
					}
					$value += $shift_values[$ci];
				}
				unset($value);
			}

			$maxY = ($maxY === null) ? max($values) : max($maxY, max($values));
		}

		return $maxY;
	}

	protected function calcZero() {
		foreach ($this->getVerticalScalesInUse() as $side) {
			// Expression optimized to avoid overflow.
			$this->unit2px[$side] = $this->m_maxY[$side] / $this->sizeY - $this->m_minY[$side] / $this->sizeY;

			if ($this->unit2px[$side] == 0) {
				$this->unit2px[$side] = 1;
			}

			if ($this->m_minY[$side] > 0) {
				$this->zero[$side] = $this->sizeY + $this->shiftY;
				if ($this->m_minY[$side] > $this->m_maxY[$side]) {
					$this->oxy[$side] = $this->m_maxY[$side];
				}
				else {
					$this->oxy[$side] = $this->m_minY[$side];
				}
			}
			elseif ($this->m_maxY[$side] < 0) {
				$this->zero[$side] = $this->shiftY;
				if ($this->m_minY[$side] > $this->m_maxY[$side]) {
					$this->oxy[$side] = $this->m_minY[$side];
				}
				else {
					$this->oxy[$side] = $this->m_maxY[$side];
				}
			}
			else {
				$this->zero[$side] = $this->sizeY + $this->shiftY - abs($this->m_minY[$side] / $this->unit2px[$side]);
				$this->oxy[$side] = 0;
			}
		}
	}

	/**
	* Draw X and Y axis.
	*/
	private function drawXYAxis() {
		$gbColor = $this->getColor($this->graphtheme['gridbordercolor'], 0);

		if ($this->yaxis[GRAPH_YAXIS_SIDE_LEFT]) {
			zbx_imageline(
				$this->im,
				$this->shiftXleft,
				$this->shiftY - 5,
				$this->shiftXleft,
				$this->sizeY + $this->shiftY + 4,
				$gbColor
			);

			$points = [
				$this->shiftXleft - 3, $this->shiftY - 5,
				$this->shiftXleft + 3, $this->shiftY - 5,
				$this->shiftXleft, $this->shiftY - 10
			];
			if (PHP_VERSION_ID >= 80100) {
				imagefilledpolygon($this->im, $points, $this->getColor('White'));
			}
			else {
				imagefilledpolygon($this->im, $points, 3, $this->getColor('White'));
			}

			/* draw left axis triangle */
			zbx_imageline($this->im, $this->shiftXleft - 3, $this->shiftY - 5, $this->shiftXleft + 3, $this->shiftY - 5,
					$gbColor);
			zbx_imagealine($this->im, $this->shiftXleft - 3, $this->shiftY - 5, $this->shiftXleft, $this->shiftY - 10,
					$gbColor);
			zbx_imagealine($this->im, $this->shiftXleft + 3, $this->shiftY - 5, $this->shiftXleft, $this->shiftY - 10,
					$gbColor);
		}
		else {
			dashedLine(
				$this->im,
				$this->shiftXleft,
				$this->shiftY,
				$this->shiftXleft,
				$this->sizeY + $this->shiftY,
				$this->getColor($this->graphtheme['gridcolor'], 0)
			);
		}

		if ($this->yaxis[GRAPH_YAXIS_SIDE_RIGHT]) {
			zbx_imageline(
				$this->im,
				$this->sizeX + $this->shiftXleft,
				$this->shiftY - 5,
				$this->sizeX + $this->shiftXleft,
				$this->sizeY + $this->shiftY + 4,
				$gbColor
			);

			$points = [
				$this->sizeX + $this->shiftXleft - 3, $this->shiftY - 5,
				$this->sizeX + $this->shiftXleft + 3, $this->shiftY - 5,
				$this->sizeX + $this->shiftXleft, $this->shiftY - 10
			];
			if (PHP_VERSION_ID >= 80100) {
				imagefilledpolygon($this->im, $points, $this->getColor('White'));
			}
			else {
				imagefilledpolygon($this->im, $points, 3, $this->getColor('White'));
			}

			/* draw right axis triangle */
			zbx_imageline($this->im, $this->sizeX + $this->shiftXleft - 3, $this->shiftY - 5,
				$this->sizeX + $this->shiftXleft + 3, $this->shiftY - 5, $gbColor);
			zbx_imagealine($this->im, $this->sizeX + $this->shiftXleft + 3, $this->shiftY - 5,
				$this->sizeX + $this->shiftXleft, $this->shiftY - 10, $gbColor);
			zbx_imagealine($this->im, $this->sizeX + $this->shiftXleft - 3, $this->shiftY - 5,
				$this->sizeX + $this->shiftXleft, $this->shiftY - 10, $gbColor);
		}
		else {
			dashedLine(
				$this->im,
				$this->sizeX + $this->shiftXleft,
				$this->shiftY,
				$this->sizeX + $this->shiftXleft,
				$this->sizeY + $this->shiftY,
				$this->getColor($this->graphtheme['gridcolor'], 0)
			);
		}

		zbx_imageline(
			$this->im,
			$this->shiftXleft - 3,
			$this->sizeY + $this->shiftY + 1,
			$this->sizeX + $this->shiftXleft + 5,
			$this->sizeY + $this->shiftY + 1,
			$gbColor
		);

		$points = [
			$this->sizeX + $this->shiftXleft + 5, $this->sizeY + $this->shiftY - 2,
			$this->sizeX + $this->shiftXleft + 5, $this->sizeY + $this->shiftY + 4,
			$this->sizeX + $this->shiftXleft + 10, $this->sizeY + $this->shiftY + 1
		];
		if (PHP_VERSION_ID >= 80100) {
			imagefilledpolygon($this->im, $points, $this->getColor('White'));
		}
		else {
			imagefilledpolygon($this->im, $points, 3, $this->getColor('White'));
		}

		/* draw X axis triangle */
		zbx_imageline($this->im, $this->sizeX + $this->shiftXleft + 5, $this->sizeY + $this->shiftY - 2,
			$this->sizeX + $this->shiftXleft + 5, $this->sizeY + $this->shiftY + 4, $gbColor);
		zbx_imagealine($this->im, $this->sizeX + $this->shiftXleft + 5, $this->sizeY + $this->shiftY + 4,
			$this->sizeX + $this->shiftXleft + 10, $this->sizeY + $this->shiftY + 1, $gbColor);
		zbx_imagealine($this->im, $this->sizeX + $this->shiftXleft + 10, $this->sizeY + $this->shiftY + 1,
			$this->sizeX + $this->shiftXleft + 5, $this->sizeY + $this->shiftY - 2, $gbColor);
	}

	private function drawTimeGrid() {
		$time_format = (date('Y', $this->stime) != date('Y', $this->to_time))
			? DATE_FORMAT
			: DATE_TIME_FORMAT_SHORT;

		// Draw start date (and time) label.
		$this->drawStartEndTimePeriod($this->stime, $time_format, 0);
		$this->drawDateTimeIntervals();

		// Draw end date (and time) label.
		$this->drawStartEndTimePeriod($this->to_time, $time_format, $this->sizeX);
	}

	/**
	 * Draw start or end date (and time) label.
	 *
	 * @param int $value        Unix time.
	 * @param string $format    Date time format.
	 * @param int $position     Position on X axis.
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
	 * @param string $value     Readable timestamp.
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
	 * Get best matching X-axis interval specification for the preferred sub-interval.
	 *
	 * @param int    $pref_sub_interval  Preferred sub-interval in seconds.
	 * @param float  $min_sub_interval   Preferred minimal sub-interval in seconds (float). Discarded if no matches.
	 * @param string $magnitude          The highest non-permanent date component (Y, m, d, H, i, s).
	 *
	 * @return array
	 */
	private function getOptimalDateTimeIntervalSpec(int $pref_sub_interval, float $min_sub_interval,
			string $magnitude): array {
		// Possible X-axis main and sub-intervals.
		$intervals = [
			'PT1M' => ['PT1S', 'PT5S', 'PT10S', 'PT30S'],
			'PT1H' => ['PT1M', 'PT2M', 'PT5M', 'PT15M', 'PT30M'],
			'P1D' => ['PT1H', 'PT3H', 'PT6H', 'PT12H'],
			'P1W' => ['P1D'],
			'P1M' => ['P3D', 'P1W', 'P2W'],
			'P1Y' => ['P1M', 'P3M', 'P4M', 'P6M'],
			'P10Y' => ['P1Y', 'P5Y']
		];

		// Starting date and time aligners.
		$aligners = [
			'PT1M' => ['trim' => 'Y-m-d H:i:00', 'convert' => null],
			'PT1H' => ['trim' => 'Y-m-d H:00:00', 'convert' => null],
			'P1D' => ['trim' => 'Y-m-d 00:00:00', 'convert' => null],
			'P1W' => ['trim' => 'Y-m-d 00:00:00', 'convert' => 'last Sunday 00:00:00'],
			'P1M' => ['trim' => 'Y-m-01 00:00:00', 'convert' => null],
			'P1Y' => ['trim' => 'Y-01-01 00:00:00', 'convert' => null],
			'P10Y' => ['trim' => '1970-01-01 00:00:00', 'convert' => null]
		];

		// Date and time label formats.
		$formats = [
			'PT1M' => ['main' => TIME_FORMAT, 'sub' => _('H:i:s')],
			'PT1H' => ['main' => TIME_FORMAT, 'sub' => TIME_FORMAT],
			'P1D' => ['main' => $magnitude === 'Y' ? DATE_FORMAT : _('m-d'), 'sub' => TIME_FORMAT],
			'P1W' => ['main' => $magnitude === 'Y' ? DATE_FORMAT : _('m-d'), 'sub' => _('m-d')],
			'P1M' => ['main' => $magnitude === 'Y' ? DATE_FORMAT : _('m-d'), 'sub' => _('m-d')],
			'P1Y' => ['main' => _x('Y', DATE_FORMAT_CONTEXT), 'sub' => _('M')],
			'P10Y' => ['main' => _x('Y', DATE_FORMAT_CONTEXT), 'sub' => _x('Y', DATE_FORMAT_CONTEXT)]
		];

		$best_main_interval = null;
		$best_sub_interval = null;
		$best_sub_interval_ts = 0;
		$best_sub_interval_prop = INF;

		foreach ($intervals as $main_interval => $sub_intervals) {
			foreach ($sub_intervals as $sub_interval) {
				$interval_ts = (new DateTime('@0'))
					->add(new DateInterval($sub_interval))
					->getTimestamp();

				$interval_prop = max($pref_sub_interval, $interval_ts) / min($pref_sub_interval, $interval_ts);

				// Search for the best interval preferably but not necessarily matching the $min_sub_interval criteria.
				$is_better = ($best_sub_interval_ts < $min_sub_interval)
					? $interval_ts > $best_sub_interval_ts
					: $interval_prop < $best_sub_interval_prop;

				if ($is_better) {
					$best_main_interval = $main_interval;
					$best_sub_interval = $sub_interval;
					$best_sub_interval_ts = $interval_ts;
					$best_sub_interval_prop = $interval_prop;
				}
			}
		}

		return [
			'intervals' => [
				'main' => new DateInterval($best_main_interval),
				'sub' => new DateInterval($best_sub_interval)
			],
			'aligner' => $aligners[$best_main_interval],
			'format' => $formats[$best_main_interval]
		];
	}

	/**
	 * Get date and time intervals in the given range for the X-axis.
	 *
	 * @param int          $start     Range start in seconds.
	 * @param int          $end       Range end in seconds.
	 * @param DateInterval $interval  Date and time interval.
	 *
	 * @return array
	 */
	private function getDateTimeIntervals(int $start, int $end, DateInterval $interval): array {
		$intervals = [];

		$interval_ts = (new DateTime('@0'))
			->add($interval)
			->getTimestamp();

		// Manage time transitions natively.
		if ($interval_ts >= SEC_PER_DAY) {
			$dt = (new DateTime())->setTimestamp($start);

			while ($dt->getTimestamp() <= $end) {
				$intervals[] = $dt->getTimestamp();
				$dt->add($interval);
			}
		}
		else {
			$transitions = (new DateTime())->getTimezone()->getTransitions($start, $end);
			if (!$transitions) {
				$transitions = [];
			}

			$time = $start;
			$transition = 1;

			while ($time <= $end) {
				$correct_before = 0;
				$correct_after = 0;

				while ($transition < count($transitions) && $time >= $transitions[$transition]['ts']) {
					$offset_diff = $transitions[$transition]['offset'] - $transitions[$transition - 1]['offset'];

					if ($interval_ts > abs($offset_diff)) {
						if ($transitions[$transition]['isdst']) {
							if ($time - $transitions[$transition]['ts'] >= $offset_diff) {
								$correct_before -= $offset_diff;
							}
							else {
								$correct_after -= $offset_diff;
							}
						}
						else {
							$correct_before -= $offset_diff;
						}
					}

					$transition++;
				}

				$time += $correct_before;
				$intervals[] = $time;
				$time += $correct_after + $interval_ts;
			}
		}

		return $intervals;
	}

	/**
	 * Draw date and time intervals under the X axis.
	 */
	private function drawDateTimeIntervals() {
		// Calculate standard label width in time units.
		$label_size = imageTextSize(7, 90, 'WWW')['width'] * $this->period / $this->sizeX * 2;

		$preferred_sub_interval = (int) ($this->period * $this->cell_width / $this->sizeX) ?: 1;

		foreach (['Y', 'm', 'd', 'H', 'i', 's'] as $magnitude) {
			if (date($magnitude, $this->stime) !== date($magnitude, $this->stime + $this->period)) {
				break;
			}
		}

		$optimal = $this->getOptimalDateTimeIntervalSpec($preferred_sub_interval, $label_size, $magnitude);

		// Align starting date and time with the interval.
		$start = strtotime(date($optimal['aligner']['trim'], $this->stime));
		if ($optimal['aligner']['convert'] !== null) {
			$start = strtotime($optimal['aligner']['convert'], $start);
		}

		$end = $this->stime + $this->period;

		// Draw main intervals.
		$mains = [];

		foreach ($this->getDateTimeIntervals($start, $end, $optimal['intervals']['main']) as $time) {
			$pos = $time - $this->stime;

			if ($pos >= $label_size && $pos <= $this->period - $label_size) {
				$this->drawMainPeriod(date($optimal['format']['main'], $time), $pos * $this->sizeX / $this->period);
			}

			$mains[] = $time;
		}

		$mains[] = $end;

		// Draw sub-intervals.
		$sub_interval_ts = (new DateTime('@0'))
			->add($optimal['intervals']['sub'])
			->getTimestamp();

		// Sub-intervals' margin from the main interval boundaries.
		$main_margin = max($label_size, (int) ($sub_interval_ts * .75));

		for ($i = 0, $i_max = count($mains) - 2; $i <= $i_max; $i++) {
			$pos_min = $mains[$i] - $this->stime + $main_margin;
			$pos_max = $mains[$i + 1] - $this->stime - $main_margin;

			foreach ($this->getDateTimeIntervals($mains[$i], $mains[$i + 1], $optimal['intervals']['sub']) as $time) {
				$pos = $time - $this->stime;

				if ($pos >= max($pos_min, $label_size) && $pos <= min($pos_max, $this->period - $label_size)) {
					$this->drawSubPeriod(date($optimal['format']['sub'], $time), $pos * $this->sizeX / $this->period);
				}
			}
		}
	}

	private function drawVerticalScale() {
		foreach ($this->getVerticalScalesInUse() as $side_index => $side) {
			$units = null;
			$units_long = '';

			foreach ($this->items as $item) {
				if ($item['yaxisside'] == $side) {
					if ($units === null) {
						$units = $item['units'];
					}
					elseif ($item['units'] !== $units) {
						$units = '';
					}

					if ($item['units_long'] !== '') {
						$units_long = $item['units_long'];
					}
				}
			}

			if ($units_long !== '') {
				$dims = imageTextSize(9, 90, $units_long);

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
					$units_long
				);
			}

			$scale_values = calculateGraphScaleValues($this->m_minY[$side], $this->m_maxY[$side],
				$this->ymin_type == GRAPH_YAXIS_TYPE_CALCULATED, $this->ymax_type == GRAPH_YAXIS_TYPE_CALCULATED,
				$this->intervals[$side], $units, $this->is_binary[$side], $this->power[$side], 10
			);

			$line_color = $this->getColor($this->graphtheme['gridcolor'], 0);

			foreach ($scale_values as ['relative_pos' => $relative_pos, 'value' => $value]) {
				$pos_X = ($side == GRAPH_YAXIS_SIDE_LEFT)
					? $this->shiftXleft - imageTextSize(8, 0, $value)['width'] - 9
					: $this->sizeX + $this->shiftXleft + 12;

				$pos_Y = $this->shiftY + $this->sizeY * (1 - $relative_pos);

				if ($side_index == 0 && $relative_pos > 0) {
					dashedLine($this->im, $this->shiftXleft, $pos_Y, $this->shiftXleft + $this->sizeX, $pos_Y,
						$line_color
					);
				}

				imageText(
					$this->im,
					8,
					0,
					$pos_X,
					$pos_Y + 4,
					$this->getColor($this->graphtheme['textcolor'], 0),
					$value
				);
			}

			if ($this->zero[$side] != $this->sizeY + $this->shiftY && $this->zero[$side] != $this->shiftY) {
				zbx_imageline(
					$this->im,
					$this->shiftXleft,
					$this->zero[$side],
					$this->shiftXleft + $this->sizeX,
					$this->zero[$side],
					$this->getColor($side == GRAPH_YAXIS_SIDE_LEFT
						? GRAPH_ZERO_LINE_COLOR_LEFT
						: GRAPH_ZERO_LINE_COLOR_RIGHT
					)
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

		if (!$this->show_work_period) {
			return;
		}

		if ($this->period > SEC_PER_MONTH * 3) {
			return;
		}

		$config = [CSettingsHelper::WORK_PERIOD => CSettingsHelper::get(CSettingsHelper::WORK_PERIOD)];
		$config = CMacrosResolverHelper::resolveTimeUnitMacros([$config], [CSettingsHelper::WORK_PERIOD])[0];

		$periods = parse_period($config[CSettingsHelper::WORK_PERIOD]);
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

				if ($maxY - $minY == INF) {
					$y = $this->sizeY + $this->shiftY - CMathHelper::safeMul([$this->sizeY,
						$percentile['value'] / 10 - $minY / 10, 1 / ($maxY / 10 - $minY / 10)]
					);
				}
				else {
					$y = $this->sizeY + $this->shiftY - CMathHelper::safeMul([$this->sizeY,
						$percentile['value'] - $minY, 1 / ($maxY - $minY)]
					);
				}

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
		if (!$this->show_triggers) {
			return;
		}

		$oppColor = $this->getColor(GRAPH_TRIGGER_LINE_OPPOSITE_COLOR);

		foreach ($this->triggers as $trigger) {
			$minY = $this->m_minY[$trigger['yaxisside']];
			$maxY = $this->m_maxY[$trigger['yaxisside']];

			if ($minY >= $trigger['val'] || $trigger['val'] >= $maxY) {
				continue;
			}

			if ($maxY - $minY == INF) {
				$y = $this->sizeY + $this->shiftY - CMathHelper::safeMul([$this->sizeY,
					$trigger['val'] / 10 - $minY / 10, 1 / ($maxY / 10 - $minY / 10)
				]);
			}
			else {
				$y = $this->sizeY + $this->shiftY - CMathHelper::safeMul([$this->sizeY,
					$trigger['val'] - $minY, 1 / ($maxY - $minY)
				]);
			}

			$triggerColor = $this->getColor($trigger['color']);
			$lineStyle = [$triggerColor, $triggerColor, $triggerColor, $triggerColor, $triggerColor, $oppColor, $oppColor, $oppColor];

			dashedLine( $this->im, $this->shiftXleft, $y, $this->sizeX + $this->shiftXleft, $y, $lineStyle);
			dashedLine( $this->im, $this->shiftXleft, $y + 1, $this->sizeX + $this->shiftXleft, $y + 1, $lineStyle);
		}
	}

	private function getLastValue(array $data, int $calc_fnc) {
		for ($i = $this->sizeX; $i >= 0; $i--) {
			if ($data['count'][$i] != 0) {
				switch ($calc_fnc) {
					case CALC_FNC_MIN:
						return $data['min'][$i];
					case CALC_FNC_MAX:
						return $data['max'][$i];
					case CALC_FNC_ALL:
					case CALC_FNC_AVG:
					default:
						return $data['avg'][$i];
				}
			}
		}

		return 0;
	}

	protected function drawLegend() {
		// if graph is small, we are not drawing legend
		if (!$this->drawItemsLegend) {
			return true;
		}

		$leftXShift = 15;
		$units = [GRAPH_YAXIS_SIDE_LEFT => 0, GRAPH_YAXIS_SIDE_RIGHT => 0];

		// draw item legend
		$legend = new CImageTextTable($this->im, $leftXShift - 5, $this->sizeY + $this->shiftY + self::LEGEND_OFFSET_Y);
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

			// draw color square
			$colorSquare = imagecreatetruecolor(11, 11);
			imagefill($colorSquare, 0, 0, $this->getColor($this->graphtheme['backgroundcolor'], 0));
			imagefilledrectangle($colorSquare, 0, 0, 10, 10, $color);
			imagerectangle($colorSquare, 0, 0, 10, 10, $this->getColor('Black'));

			// caption
			$itemCaption = $this->itemsHost
				? $this->items[$i]['name']
				: $this->items[$i]['hostname'].NAME_DELIMITER.$this->items[$i]['name'];

			// draw legend of an item with data
			$data = array_key_exists($this->items[$i]['itemid'], $this->data)
				? $this->data[$this->items[$i]['itemid']]
				: null;

			if ($data && $data['min']) {
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
					'text' => convertUnits([
						'value' => $this->getLastValue($data, $this->items[$i]['calc_fnc']),
						'units' => $this->items[$i]['units'],
						'convert' => ITEM_CONVERT_NO_UNITS
					]),
					'align' => 2
				]);
				$legend->addCell($rowNum, [
					'text' => convertUnits([
						'value' => min($data['min']),
						'units' => $this->items[$i]['units'],
						'convert' => ITEM_CONVERT_NO_UNITS
					]),
					'align' => 2
				]);
				$legend->addCell($rowNum, [
					'text' => convertUnits([
						'value' => $data['avg_orig'],
						'units' => $this->items[$i]['units'],
						'convert' => ITEM_CONVERT_NO_UNITS
					]),
					'align' => 2
				]);
				$legend->addCell($rowNum, [
					'text' => convertUnits([
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
			$this->sizeY + $this->shiftY + 14 * $rowNum + self::LEGEND_OFFSET_Y
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
						? convertUnits([
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

					$points = [
						$leftXShift + 5, $this->sizeY + $this->shiftY + 14 * $rowNum + self::LEGEND_OFFSET_Y,
						$leftXShift - 5, $this->sizeY + $this->shiftY + 14 * $rowNum + self::LEGEND_OFFSET_Y,
						$leftXShift, $this->sizeY + $this->shiftY + 14 * $rowNum + self::LEGEND_OFFSET_Y - 10
					];
					if (PHP_VERSION_ID >= 80100) {
						imagefilledpolygon($this->im, $points, $this->getColor($color));
					}
					else {
						imagefilledpolygon($this->im, $points, 3, $this->getColor($color));
					}

					$points = [
						$leftXShift + 5, $this->sizeY + $this->shiftY + 14 * $rowNum + self::LEGEND_OFFSET_Y,
						$leftXShift - 5, $this->sizeY + $this->shiftY + 14 * $rowNum + self::LEGEND_OFFSET_Y,
						$leftXShift, $this->sizeY + $this->shiftY + 14 * $rowNum + self::LEGEND_OFFSET_Y - 10
					];
					if (PHP_VERSION_ID >= 80100) {
						imagepolygon($this->im, $points, $this->getColor('Black No Alpha'));
					}
					else {
						imagepolygon($this->im, $points, 3, $this->getColor('Black No Alpha'));
					}

					$rowNum++;
				}
			}
		}

		$legend->draw();

		$legend = new CImageTextTable(
			$this->im,
			$leftXShift + 10,
			$this->sizeY + $this->shiftY + 14 * $rowNum + self::LEGEND_OFFSET_Y + 5
		);
		$legend->color = $this->getColor($this->graphtheme['textcolor'], 0);
		$legend->rowheight = 14;
		$legend->fontsize = 9;

		// draw triggers
		foreach ($this->triggers as $trigger) {
			imagefilledellipse(
				$this->im,
				$leftXShift,
				$this->sizeY + $this->shiftY + 14 * $rowNum + self::LEGEND_OFFSET_Y,
				10,
				10,
				$this->getColor($trigger['color'])
			);

			imageellipse(
				$this->im,
				$leftXShift,
				$this->sizeY + $this->shiftY + 14 * $rowNum + self::LEGEND_OFFSET_Y,
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

	protected function drawElement(&$data, $from, $to, $drawtype, $max_color, $avg_color, $min_color, $minmax_color, $calc_fnc, $yaxisside) {
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

		$x1 = $from + $this->shiftXleft;
		$x2 = $to + $this->shiftXleft;

		$y1min = (int) round($zero - ($min_from - $oxy) / $unit2px);
		$y2min = (int) round($zero - ($min_to - $oxy) / $unit2px);

		$y1max = (int) round($zero - ($max_from - $oxy) / $unit2px);
		$y2max = (int) round($zero - ($max_to - $oxy) / $unit2px);

		$y1avg = (int) round($zero - ($avg_from - $oxy) / $unit2px);
		$y2avg = (int) round($zero - ($avg_to - $oxy) / $unit2px);

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
				$shift_from = $shift_avg_from;
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
			case GRAPH_ITEM_DRAWTYPE_LINE:
			case GRAPH_ITEM_DRAWTYPE_BOLD_LINE:
				$style = $drawtype == GRAPH_ITEM_DRAWTYPE_BOLD_LINE ? LINE_TYPE_BOLD : LINE_TYPE_NORMAL;

				if ($calc_fnc == CALC_FNC_ALL) {
					if (PHP_VERSION_ID >= 80100) {
						imagefilledpolygon($this->im, $a, $avg_color);
					}
					else {
						imagefilledpolygon($this->im, $a, 4, $minmax_color);
					}

					if (!$y1x || !$y2x) {
						zbx_imagealine($this->im, $x1, $y1max, $x2, $y2max, $max_color, $style);
					}
					if (!$y1n || !$y2n) {
						zbx_imagealine($this->im, $x1, $y1min, $x2, $y2min, $min_color, $style);
					}
				}

				zbx_imagealine($this->im, $x1, $y1, $x2, $y2, $avg_color, $style);
				break;

			case GRAPH_ITEM_DRAWTYPE_DOT:
				imagefilledrectangle($this->im, $x1 - 1, $y1 - 1, $x1, $y1, $avg_color);
				break;

			case GRAPH_ITEM_DRAWTYPE_BOLD_DOT:
				imagefilledrectangle($this->im, $x2 - 1, $y2 - 1, $x2 + 1, $y2 + 1, $avg_color);
				break;

			case GRAPH_ITEM_DRAWTYPE_DASHED_LINE:
				imagesetstyle($this->im, [$avg_color, $avg_color, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT]);
				zbx_imageline($this->im, $x1, $y1, $x2, $y2, IMG_COLOR_STYLED);
				break;

			case GRAPH_ITEM_DRAWTYPE_GRADIENT_LINE:
			case GRAPH_ITEM_DRAWTYPE_FILLED_REGION:
				/*
				 * Graphs should be at least 50px in height in order to visually see the gradient. Though 51px would not
				 * make any difference either. If graph height is too small to see gradient, use standard solid color
				 * filling function instead.
				 */
				if ($drawtype == GRAPH_ITEM_DRAWTYPE_FILLED_REGION
						|| ($drawtype == GRAPH_ITEM_DRAWTYPE_GRADIENT_LINE && $this->sizeY <= 50)) {
					$a[0] = $x1;
					$a[1] = $y1;
					$a[2] = $x1;
					$a[3] = $y1_shift;
					$a[4] = $x2;
					$a[5] = $y2_shift;
					$a[6] = $x2;
					$a[7] = $y2;

					if (PHP_VERSION_ID >= 80100) {
						imagefilledpolygon($this->im, $a, $avg_color);
					}
					else {
						imagefilledpolygon($this->im, $a, 4, $avg_color);
					}
				}
				else {
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
							$alpha = ($gy + $j) < ($this->shiftY + $startAlpha)
								? 0
								: 127 - (int) abs(127 - ($alphaRatio * ($gy + $j - $this->shiftY - $startAlpha)));

							$color = imagecolorexactalpha($this->im, $red, $green, $blue, $alpha);
							imagesetpixel($this->im, $x2 + $i, (int) $gy + $j, $color);
						}
					}
				}
				break;
		}
	}

	private function calcVerticalScale() {
		$calc_min = $this->ymin_type == GRAPH_YAXIS_TYPE_CALCULATED;
		$calc_max = $this->ymax_type == GRAPH_YAXIS_TYPE_CALCULATED;

		$rows_min = (int) max(1, floor($this->sizeY / $this->cell_height_min / 1.5));
		$rows_max = (int) max(1, floor($this->sizeY / $this->cell_height_min));

		foreach ($this->getVerticalScalesInUse() as $side) {
			$min = $this->calculateMinY($side);
			$max = $this->calculateMaxY($side);

			if ($min === null) {
				$min = 0;
			}

			if ($max === null) {
				$max = 1;
			}

			if ($this->type == GRAPH_TYPE_STACKED && $this->ymin_type == GRAPH_YAXIS_TYPE_CALCULATED) {
				$min = min(0, $min);
			}

			$is_binary = false;
			$calc_power = false;

			foreach ($this->items as $item) {
				if ($item['yaxisside'] == $side) {
					$is_binary = $is_binary || in_array($item['units'], ['B', 'Bps']);
					$calc_power = $calc_power || $item['units'] === '' || $item['units'][0] !== '!';
				}
			}

			$result = calculateGraphScaleExtremes($min, $max, $is_binary, $calc_power, $calc_min, $calc_max, $rows_min,
				$rows_max
			);

			if ($result === null) {
				show_error_message(_('Y axis MAX value must be greater than Y axis MIN value.'));
				exit;
			}

			[
				'min' => $this->m_minY[$side],
				'max' => $this->m_maxY[$side],
				'interval' => $this->intervals[$side],
				'power' => $this->power[$side]
			] = $result;

			$this->is_binary[$side] = $is_binary;

			if ($calc_min && $calc_max) {
				$rows_min = $rows_max = $result['rows'];
			}
		}
	}

	private function calcDimensions() {
		$this->shiftXleft = $this->yaxis[GRAPH_YAXIS_SIDE_LEFT] ? 85 : 30;
		$this->shiftXright = $this->yaxis[GRAPH_YAXIS_SIDE_RIGHT] ? 85 : 30;

		// Calculate graph summary padding for both axes.
		$x_offsets = $this->shiftXleft + $this->shiftXright + 1;
		$y_offsets = $this->shiftY + self::LEGEND_OFFSET_Y;

		if (!$this->with_vertical_padding) {
			$y_offsets -= ($this->show_triggers && count($this->triggers) > 0)
				? static::DEFAULT_TOP_BOTTOM_PADDING / 2
				: static::DEFAULT_TOP_BOTTOM_PADDING;
		}

		// Actual outer dimensions, regardless $this->outer setting.
		$this->fullSizeX = $this->sizeX;
		$this->fullSizeY = $this->sizeY;

		if ($this->drawLegend) {
			// Reserve N+1 item rows, last row is used as padding for legend.
			$h_legend_items = 14 * ($this->num + 1);
			$h_legend_triggers = 14 * count($this->triggers);
			$h_legend_percentile = 0;

			foreach ($this->percentile as $side => $percentile) {
				if ($percentile['percent'] > 0 && $this->yaxis[$side]) {
					$h_legend_percentile += 14;
				}
			}
		}

		// Normalize dimensions according to which dimensions were initially provided.
		if ($this->outer) {
			// Adjust inner graph dimensions.
			$this->sizeX = $this->fullSizeX - $x_offsets;
			$this->sizeY = $this->fullSizeY - $y_offsets;

			if ($this->drawLegend) {
				if ($this->sizeY - $h_legend_items >= self::GRAPH_HEIGHT_MIN) {
					$this->sizeY -= $h_legend_items;
					$this->drawItemsLegend = true;

					if ($this->sizeY - ($h_legend_triggers + $h_legend_percentile) >= self::GRAPH_HEIGHT_MIN) {
						$this->sizeY -= $h_legend_triggers + $h_legend_percentile;
						$this->drawExLegend = true;
					}
				}
			}
		}
		else {
			// Adjust target image dimensions.
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
			$min_dimentions['width']++;

			$min_dimentions['height'] += $this->shiftY + self::LEGEND_OFFSET_Y;
		}

		return $min_dimentions;
	}

	/**
	 * Expands graph item objects data: macros in item name, time units, dependent item
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
				'itemids' => $master_itemids,
				'filter' => [
					'flags' => [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_PROTOTYPE, ZBX_FLAG_DISCOVERY_CREATED]
				]
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

			$graph_item = CMacrosResolverHelper::resolveTimeUnitMacros([$graph_item], ['delay'])[0];

			$update_interval_parser->parse($graph_item['delay']);
			$graph_item['delay'] = getItemDelay($update_interval_parser->getDelay(),
				$update_interval_parser->getIntervals(ITEM_DELAY_FLEXIBLE)
			);

			$graph_item['has_scheduling_intervals']
				= (bool) $update_interval_parser->getIntervals(ITEM_DELAY_SCHEDULING);

			if (strpos($graph_item['units'], ',') === false) {
				$graph_item['units_long'] = '';
			}
			else {
				list($graph_item['units'], $graph_item['units_long']) = explode(',', $graph_item['units'], 2);
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
		$this->calcDimensions();

		$this->im = imagecreatetruecolor(1, 1);

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

		$this->expandItems();
		$this->selectTriggers();
		$this->calcDimensions();

		$this->selectData();
		if (hasErrorMessages()) {
			show_messages();
		}

		$this->calcVerticalScale();
		$this->calcPercentile();
		$this->calcZero();

		$this->im = imagecreatetruecolor($this->fullSizeX, $this->fullSizeY);

		$this->initColors();
		$this->drawRectangle();
		$this->drawHeader();
		$this->drawWorkPeriod();
		$this->drawTimeGrid();
		$this->drawVerticalScale();
		$this->drawXYAxis();

		// Correct item 'delay' field value when graph data requested for trends.
		foreach ($this->items as &$item) {
			if ($item['source'] === 'trends' && (!$item['has_scheduling_intervals'] || $item['delay'] != 0)) {
				$item['delay'] = max($item['delay'], ZBX_MAX_TREND_DIFF);
			}
		}
		unset($item);

		// for each metric
		for ($item = 0; $item < $this->num; $item++) {
			if (!array_key_exists($this->items[$item]['itemid'], $this->data)) {
				continue;
			}

			$data = &$this->data[$this->items[$item]['itemid']];

			$drawtype = $this->items[$item]['drawtype'];
			$max_color = $this->getColor('ValueMax', GRAPH_STACKED_ALFA);
			$avg_color = $this->getColor($this->items[$item]['color'], GRAPH_STACKED_ALFA);
			$min_color = $this->getColor('ValueMin', GRAPH_STACKED_ALFA);
			$minmax_color = $this->getColor('ValueMinMax', GRAPH_STACKED_ALFA);

			$calc_fnc = $this->items[$item]['calc_fnc'];

			// for each X
			$prevDraw = true;
			for ($i = 1, $j = 0; $i <= $this->sizeX; $i++) { // new point
				if ($data['count'][$i] == 0 && $i != $this->sizeX) {
					continue;
				}

				$delay = $this->items[$item]['delay'];

				if ($this->items[$item]['type'] == ITEM_TYPE_TRAPPER
						|| ($this->items[$item]['type'] == ITEM_TYPE_ZABBIX_ACTIVE
							&& preg_match('/^(event)?log(rt)?\[/', $this->items[$item]['key_']))
						|| ($this->items[$item]['has_scheduling_intervals'] && $delay == 0)) {
					$draw = true;
				}
				else {
					if (!$data['clock']) {
						$diff = 0;
					}
					else {
						$diff = abs($data['clock'][$i] - $data['clock'][$j]);
					}

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

		if ($this->drawLegend) {
			$this->drawTriggers();
			$this->drawPercentile();
			$this->drawLegend();
		}

		if ($debug_mode) {
			$data_from = [];
			foreach ($this->items as $item) {
				$data_from[$item['source']] = true;
			}
			ksort($data_from);

			$str = sprintf('%0.2f', microtime(true) - $start_time);
			imageText($this->im, 6, 90, $this->fullSizeX - 2, $this->fullSizeY - 5, $this->getColor('Gray'),
				_s('Data from %1$s. Generated in %2$s sec.', implode(', ', array_keys($data_from)), $str)
			);
		}

		unset($this->items, $this->data);

		imageOut($this->im);
	}
}
