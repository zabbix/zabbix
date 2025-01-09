<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


class CPieGraphDraw extends CGraphDraw {

	const DEFAULT_HEADER_PADDING_TOP = 30;

	const GRAPH_WIDTH_MIN = 20;
	const GRAPH_HEIGHT_MIN = 20;

	private $background;
	private $sum;
	private $exploderad;
	private $exploderad3d;
	private $graphheight3d;
	private $shiftlegendright;
	private $dataFrom;
	private $shiftYLegend;

	public function __construct($type = GRAPH_TYPE_PIE) {
		parent::__construct($type);
		$this->background = false;
		$this->sum = false;
		$this->exploderad = 1;
		$this->exploderad3d = 3;
		$this->graphheight3d = 12;
		$this->shiftlegendright = 17 * 7 + 7 + 10; // count of static chars * px/char + for color rectangle + space
	}

	/********************************************************************************************************/
	/* PRE CONFIG: ADD / SET / APPLY
	/********************************************************************************************************/
	public function addItem($itemid, bool $resolve_macros, $calc_fnc = CALC_FNC_AVG, $color = null, $type = null) {
		$items = API::Item()->get([
			'output' => ['itemid', 'hostid', $resolve_macros ? 'name_resolved' : 'name', 'key_', 'units', 'value_type',
				'valuemapid', 'history', 'trends'
			],
			'itemids' => [$itemid],
			'webitems' => true
		]);

		if ($resolve_macros) {
			$items = CArrayHelper::renameObjectsKeys($items, ['name_resolved' => 'name']);
		}

		if (!$items) {
			$items = API::ItemPrototype()->get([
				'output' => ['itemid', 'hostid', 'name', 'key_', 'units', 'value_type', 'valuemapid', 'history',
					'trends'
				],
				'itemids' => [$itemid]
			]);
		}

		$this->items[$this->num] = reset($items);

		$host = get_host_by_hostid($this->items[$this->num]['hostid']);

		$this->items[$this->num]['host'] = $host['host'];
		$this->items[$this->num]['hostname'] = $host['name'];
		$this->items[$this->num]['color'] = is_null($color) ? 'Dark Green' : $color;
		$this->items[$this->num]['calc_fnc'] = is_null($calc_fnc) ? CALC_FNC_AVG : $calc_fnc;
		$this->items[$this->num]['calc_type'] = is_null($type) ? GRAPH_ITEM_SIMPLE : $type;

		$this->num++;
	}

	public function switchPie3D($type = false) {
		if ($type) {
			$this->type = $type;
		}
		else {
			switch ($this->type) {
				case GRAPH_TYPE_EXPLODED:
					$this->type = GRAPH_TYPE_3D_EXPLODED;
					break;
				case GRAPH_TYPE_3D_EXPLODED:
					$this->type = GRAPH_TYPE_EXPLODED;
					break;
				case GRAPH_TYPE_3D:
					$this->type = GRAPH_TYPE_PIE;
					break;
				case GRAPH_TYPE_PIE:
					$this->type = GRAPH_TYPE_3D;
					break;
				default:
					$this->type = GRAPH_TYPE_PIE;
			}
		}
		return $this->type;
	}

	protected function calc3dheight($height) {
		$this->graphheight3d = (int) ($height / 20);
	}

	protected function calcExplodedCenter($anglestart, $angleend, $x, $y, $count) {
		$count *= $this->exploderad;
		$anglemid = (int) (($anglestart + $angleend) / 2);

		$y += round($count * sin(deg2rad($anglemid)));
		$x += round($count * cos(deg2rad($anglemid)));

		return [(int) $x, (int) $y];
	}

	protected function calcExplodedRadius($sizeX, $sizeY, $count) {
		$count *= $this->exploderad * 2;
		$sizeX -= $count;
		$sizeY -= $count;

		return [(int) $sizeX, (int) $sizeY];
	}

	protected function calc3DAngle($sizeX, $sizeY) {
		$sizeY *= GRAPH_3D_ANGLE / 90;

		return [$sizeX, (int) round($sizeY)];
	}

	protected function selectData() {
		$this->data = [];
		$now = time();

		if (isset($this->stime)) {
			$this->from_time = $this->stime;
			$this->to_time = $this->stime + $this->period;
		}
		else {
			$this->to_time = $now;
			$this->from_time = $this->to_time - $this->period;
		}

		$strvaluelength = 0; // we need to know how long in px will be our legend

		// fetch values for items with the "last" function
		$lastValueItems = [];
		foreach ($this->items as $item) {
			if ($item['calc_fnc'] == CALC_FNC_LST) {
				$lastValueItems[] = $item;
			}
		}
		if ($lastValueItems) {
			$history = Manager::History()->getLastValues($lastValueItems);
		}

		$items = [];

		for ($i = 0; $i < $this->num; $i++) {
			$item = $this->items[$i];

			$from_time = $this->from_time;
			$to_time = $this->to_time;

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

			$this->data[$this->items[$i]['itemid']]['last'] = isset($history[$item['itemid']])
				? $history[$item['itemid']][0]['value'] : null;
			$this->data[$this->items[$i]['itemid']]['shift_min'] = 0;
			$this->data[$this->items[$i]['itemid']]['shift_max'] = 0;
			$this->data[$this->items[$i]['itemid']]['shift_avg'] = 0;

			$item['source'] = ($item['trends'] == 0 || ($item['history'] > time() - ($from_time + $this->period / 2)))
				? 'history'
				: 'trends';
			$items[] = $item;
		}

		$results = Manager::History()->getGraphAggregationByWidth($items, $from_time, $to_time);
		$i = 0;

		foreach ($items as $item) {
			if (array_key_exists($item['itemid'], $results)) {
				$result = $results[$item['itemid']];
				$this->dataFrom = $result['source'];

				foreach ($result['data'] as $row) {
					$this->data[$item['itemid']]['min'] = $row['min'];
					$this->data[$item['itemid']]['max'] = $row['max'];
					$this->data[$item['itemid']]['avg'] = $row['avg'];
					$this->data[$item['itemid']]['clock'] = $row['clock'];
				}
				unset($result);
			}
			else {
				$this->dataFrom = $item['source'];
			}

			switch ($item['calc_fnc']) {
				case CALC_FNC_MIN:
					$fncName = 'min';
					break;
				case CALC_FNC_MAX:
					$fncName = 'max';
					break;
				case CALC_FNC_LST:
					$fncName = 'last';
					break;
				case CALC_FNC_AVG:
				default:
					$fncName = 'avg';
			}

			$item_value = empty($this->data[$item['itemid']][$fncName])
				? 0
				: abs($this->data[$item['itemid']][$fncName]);

			if ($item['calc_type'] == GRAPH_ITEM_SUM) {
				$this->background = $i;
				$graph_sum = $item_value;
			}

			$this->sum += $item_value;

			$convertedUnit = strlen(convertUnits([
				'value' => $item_value,
				'units' => $item['units']
			]));
			$strvaluelength = max($strvaluelength, $convertedUnit);
			$i++;
		}

		if (isset($graph_sum)) {
			$this->sum = $graph_sum;
		}
		$this->shiftlegendright += $strvaluelength * 7;
	}

	protected function drawLegend() {
		$shiftY = $this->shiftY + $this->shiftYLegend;
		$fontSize = 8;

		// check if host name will be displayed
		$displayHostName = (count(array_unique(zbx_objectValues($this->items, 'hostname'))) > 1);

		// calculate function name X shift
		$functionNameXShift = 0;

		foreach ($this->items as $item) {
			$name = $displayHostName ? $item['hostname'].NAME_DELIMITER.$item['name'] : $item['name'];
			$dims = imageTextSize($fontSize, 0, $name);

			if ($dims['width'] > $functionNameXShift) {
				$functionNameXShift = $dims['width'];
			}
		}

		// display items
		$i = 0;
		$top_padding = $this->with_vertical_padding ? 10 : -(static::DEFAULT_TOP_BOTTOM_PADDING / 2);

		foreach ($this->items as $item) {
			$color = $this->getColor($item['color'], 0);

			// function name
			switch ($item['calc_fnc']) {
				case CALC_FNC_MIN:
					$fncName = 'min';
					$fncRealName = _('min');
					break;
				case CALC_FNC_MAX:
					$fncName = 'max';
					$fncRealName = _('max');
					break;
				case CALC_FNC_LST:
					$fncName = 'last';
					$fncRealName = _('last');
					break;
				case CALC_FNC_AVG:
				default:
					$fncName = 'avg';
					$fncRealName = _('avg');
			}

			if (isset($this->data[$item['itemid']])
					&& isset($this->data[$item['itemid']][$fncName])) {
				$dataValue = $this->data[$item['itemid']][$fncName];
				$proc = ($this->sum == 0) ? 0 : ($dataValue * 100) / $this->sum;

				$strValue = sprintf(_('Value').': %s ('.(round($proc) != round($proc, 2) ? '%0.2f' : '%0.0f').'%%)',
					convertUnits([
						'value' => $dataValue,
						'units' => $this->items[$i]['units']
					]),
					$proc
				);

				$str = '['.$fncRealName.']';
			}
			else {
				$strValue = _('Value: no data');

				$str = '['._('no data').']';
			}

			// item name
			imageText(
				$this->im,
				$fontSize,
				0,
				$this->shiftXleft + 15,
				$this->sizeY + $shiftY + 14 * $i + 5,
				$this->getColor($this->graphtheme['textcolor'], 0),
				$displayHostName ? $item['hostname'].NAME_DELIMITER.$item['name'] : $item['name']
			);

			// function name
			imageText(
				$this->im,
				$fontSize,
				0,
				$this->shiftXleft + $functionNameXShift + 30,
				$this->sizeY + $shiftY + 14 * $i + 5,
				$this->getColor($this->graphtheme['textcolor'], 0),
				$str
			);

			// left square fill
			imagefilledrectangle(
				$this->im,
				$this->shiftXleft,
				$this->sizeY + $shiftY + 14 * $i - 5,
				$this->shiftXleft + 10,
				$this->sizeY + $shiftY + 5 + 14 * $i,
				$color
			);

			// left square frame
			imagerectangle(
				$this->im,
				$this->shiftXleft,
				$this->sizeY + $shiftY + 14 * $i - 5,
				$this->shiftXleft + 10,
				$this->sizeY + $shiftY + 5 + 14 * $i,
				$this->getColor('Black No Alpha')
			);

			$shiftX = $this->fullSizeX - $this->shiftlegendright - $this->shiftXright + 25;

			// right square fill
			imagefilledrectangle(
				$this->im,
				$shiftX - 10,
				$this->shiftY + $top_padding + 14 * $i,
				$shiftX,
				$this->shiftY + $top_padding + 10 + 14 * $i,
				$color
			);

			// right square frame
			imagerectangle(
				$this->im,
				$shiftX - 10,
				$this->shiftY + $top_padding + 14 * $i,
				$shiftX,
				$this->shiftY + $top_padding + 10 + 14 * $i,
				$this->GetColor('Black No Alpha')
			);

			// item value
			imagetext(
				$this->im,
				$fontSize,
				0,
				$shiftX + 5,
				$this->shiftY + $top_padding + 14 * $i + 10,
				$this->getColor($this->graphtheme['textcolor'], 0),
				$strValue
			);

			$i++;
		}

		if ($this->sizeY < 120) {
			return;
		}
	}

	protected function drawElementPie($values) {
		$sum = $this->sum;

		if ($this->background !== false) {
			$least = 0;
			foreach ($values as $item => $value) {
				if ($item != $this->background) {
					$least += $value;
				}
			}
			$values[$this->background] -= $least;
		}

		if ($sum <= 0) {
			$values = [0 => 1];
			$sum = 1;
			$isEmptyData = true;
		}
		else {
			$isEmptyData = false;
		}

		$sizeX = $this->sizeX;
		$sizeY = $this->sizeY;

		if ($this->type == GRAPH_TYPE_EXPLODED) {
			list($sizeX, $sizeY) = $this->calcExplodedRadius($sizeX, $sizeY, count($values));
		}

		$xc = $x = (int) ($this->sizeX / 2) + $this->shiftXleft;
		$yc = $y = (int) ($this->sizeY / 2) + $this->shiftY;

		$calculated_angles = self::calculatePieAngles($values, $sum);

		foreach ($calculated_angles as $item => $value) {
			[$anglestart, $angleend] = $value;

			if ($this->type == GRAPH_TYPE_EXPLODED) {
				list($x, $y) = $this->calcExplodedCenter($anglestart, $angleend, $xc, $yc, count($values));
			}

			imagefilledarc(
				$this->im,
				$x,
				$y,
				$sizeX,
				$sizeY,
				$anglestart,
				$angleend,
				$this->getColor((!$isEmptyData ? $this->items[$item]['color'] : 'FFFFFF'), 0),
				IMG_ARC_PIE
			);
			imagefilledarc(
				$this->im,
				$x,
				$y,
				$sizeX,
				$sizeY,
				$anglestart,
				$angleend,
				$this->getColor('Black'),
				IMG_ARC_PIE | IMG_ARC_EDGED | IMG_ARC_NOFILL
			);
		}
	}

	protected function drawElementPie3D($values) {
		$sum = $this->sum;

		if ($this->background !== false) {
			$least = 0;
			foreach ($values as $item => $value) {
				if ($item != $this->background) {
					$least += $value;
				}
			}
			$values[$this->background] -= $least;
		}

		if ($sum <= 0) {
			$values = [0 => 1];
			$sum = 1;
			$isEmptyData = true;
		}
		else {
			$isEmptyData = false;
		}

		$sizeX = $this->sizeX;
		$sizeY = $this->sizeY;

		$this->exploderad = $this->exploderad3d;

		if ($this->type == GRAPH_TYPE_3D_EXPLODED) {
			list($sizeX, $sizeY) = $this->calcExplodedRadius($sizeX, $sizeY, count($values));
		}

		list($sizeX, $sizeY) = $this->calc3DAngle($sizeX, $sizeY);

		$xc = $x = (int) ($this->sizeX / 2) + $this->shiftXleft;
		$yc = $y = (int) ($this->sizeY / 2) + $this->shiftY;

		$calculated_angles = self::calculatePieAngles($values, $sum);

		foreach ($calculated_angles as $item => $value) {
			[$anglestart, $angleend] = $value;

			if ($this->type == GRAPH_TYPE_3D_EXPLODED) {
				list($x, $y) = $this->calcExplodedCenter($anglestart, $angleend, $xc, $yc, count($values));
			}

			imagefilledarc(
				$this->im,
				$x,
				$y + $this->graphheight3d + 1,
				$sizeX,
				$sizeY,
				$anglestart,
				$angleend,
				$this->getShadow((!$isEmptyData ? $this->items[$item]['color'] : 'FFFFFF'), 0),
				IMG_ARC_PIE
			);
			imagefilledarc(
				$this->im,
				$x,
				$y + $this->graphheight3d + 1,
				$sizeX,
				$sizeY,
				$anglestart,
				$angleend,
				$this->getColor('Black'),
				IMG_ARC_PIE | IMG_ARC_EDGED | IMG_ARC_NOFILL
			);
		}

		// 3d effect
		for ($i = $this->graphheight3d; $i > 0; $i--) {
			foreach ($calculated_angles as $item => $value) {
				[$anglestart, $angleend] = $value;

				if ($this->sum == 0) {
					continue;
				}

				if ($this->type == GRAPH_TYPE_3D_EXPLODED) {
					list($x, $y) = $this->calcExplodedCenter($anglestart, $angleend, $xc, $yc, count($values));
				}

				imagefilledarc(
					$this->im,
					$x,
					$y + $i,
					$sizeX,
					$sizeY,
					$anglestart,
					$angleend,
					$this->getShadow((!$isEmptyData ? $this->items[$item]['color'] : 'FFFFFF'), 0),
					IMG_ARC_PIE
				);
			}
		}

		foreach ($calculated_angles as $item => $value) {
			[$anglestart, $angleend] = $value;

			if ($this->type == GRAPH_TYPE_3D_EXPLODED) {
				list($x, $y) = $this->calcExplodedCenter($anglestart, $angleend, $xc, $yc, count($values));
			}

			imagefilledarc(
				$this->im,
				$x,
				$y,
				$sizeX,
				$sizeY,
				$anglestart,
				$angleend,
				$this->getColor((!$isEmptyData ? $this->items[$item]['color'] : 'FFFFFF'), 0),
				IMG_ARC_PIE
			);
			imagefilledarc(
				$this->im,
				$x,
				$y,
				$sizeX,
				$sizeY,
				$anglestart,
				$angleend,
				$this->getColor('Black'),
				IMG_ARC_PIE | IMG_ARC_EDGED | IMG_ARC_NOFILL
			);
		}
	}

	public function draw() {
		$debug_mode = CWebUser::getDebugMode();
		if ($debug_mode) {
			$start_time = microtime(true);
		}
		set_image_header();
		$this->calculateTopPadding();

		$this->selectData();
		if (hasErrorMessages()) {
			show_messages();
		}

		$this->shiftYLegend = 20;
		$this->shiftXleft = 10;
		$this->shiftXright = 0;
		$this->fullSizeX = $this->sizeX;
		$this->fullSizeY = $this->sizeY;

		if ($this->sizeX < 300 || $this->sizeY < 200) {
			$this->showLegend(0);
		}

		if ($this->drawLegend == 1) {
			$this->sizeX -= $this->shiftXleft + $this->shiftXright + $this->shiftlegendright;
			$this->sizeY -= $this->shiftY + $this->shiftYLegend + 14 * $this->num + 8;
		}
		elseif ($this->with_vertical_padding) {
			$this->sizeX -= $this->shiftXleft * 2;
			$this->sizeY -= $this->shiftY * 2;
		}

		if (!$this->with_vertical_padding) {
			if ($this->drawLegend == 1) {
				// Increase size of graph by sum of: 8px legend font size and 5px legend item bottom shift.
				$this->sizeY += 13;
			}
			else {
				// Remove y shift if only graph is rendered (no labels, header, vertical paddings).
				$this->shiftY = 0;
			}
		}

		$this->sizeX = min($this->sizeX, $this->sizeY);
		$this->sizeY = min($this->sizeX, $this->sizeY);

		if ($this->sizeX + $this->shiftXleft > $this->fullSizeX) {
			$this->sizeX = $this->fullSizeX - $this->shiftXleft - $this->shiftXleft;
			$this->sizeY = min($this->sizeX, $this->sizeY);
		}

		$this->calc3dheight($this->sizeY);

		$this->exploderad = (int) $this->sizeX / 100;
		$this->exploderad3d = (int) $this->sizeX / 60;

		$this->im = imagecreatetruecolor($this->fullSizeX, $this->fullSizeY);

		$this->initColors();
		$this->drawRectangle();
		$this->drawHeader();

		// for each metric
		$values = [];
		for ($i = 0; $i < $this->num; $i++) {
			$data = &$this->data[$this->items[$i]['itemid']];

			if (!isset($data)) {
				continue;
			}

			switch ($this->items[$i]['calc_fnc']) {
				case CALC_FNC_MIN:
					$fncName = 'min';
					break;
				case CALC_FNC_MAX:
					$fncName = 'max';
					break;
				case CALC_FNC_LST:
					$fncName = 'last';
					break;
				case CALC_FNC_AVG:
				default:
					$fncName = 'avg';
			}

			$values[$i] = empty($this->data[$this->items[$i]['itemid']][$fncName])
				? 0
				: abs($this->data[$this->items[$i]['itemid']][$fncName]);
		}

		switch ($this->type) {
			case GRAPH_TYPE_EXPLODED:
				$this->drawElementPie($values);
				break;
			case GRAPH_TYPE_3D:
				$this->drawElementPie3D($values);
				break;
			case GRAPH_TYPE_3D_EXPLODED:
				$this->drawElementPie3D($values);
				break;
			default:
				$this->drawElementPie($values);
		}

		if ($this->drawLegend == 1) {
			$this->drawLegend();
		}

		if ($debug_mode) {
			$str = sprintf('%0.2f', microtime(true) - $start_time);
			imageText(
				$this->im,
				6,
				90,
				$this->fullSizeX - 2,
				$this->fullSizeY - 5,
				$this->getColor('Gray'),
				_s('Data from %1$s. Generated in %2$s sec.', $this->dataFrom, $str)
			);
		}

		unset($this->items, $this->data);

		imageOut($this->im);
	}

	private function getShadow($color, $alpha = 0) {
		if (isset($this->colorsrgb[$color])) {
			$red = $this->colorsrgb[$color][0];
			$green = $this->colorsrgb[$color][1];
			$blue = $this->colorsrgb[$color][2];
		}
		else {
			list($red, $green, $blue) = hex2rgb($color);
		}

		if ($this->sum > 0) {
			$red = (int) ($red * 0.6);
			$green = (int) ($green * 0.6);
			$blue = (int) ($blue * 0.6);
		}

		$RGB = [$red, $green, $blue];

		if ($alpha != 0) {
			return imagecolorexactalpha($this->im, $RGB[0], $RGB[1], $RGB[2], $alpha);
		}

		return imagecolorallocate($this->im, $RGB[0], $RGB[1], $RGB[2]);
	}

	protected static function calculatePieAngles(array $values, $sum): array {
		if ($sum == 0) {
			return [];
		}

		$angle_end = 0;
		$angle_start = 0;

		$visible_values = array_filter($values, function ($value) use (&$angle_end, &$angle_start, $sum) {
			if ($value == 0 || $angle_end >= 360) {
				return false;
			}

			$angle_end += (int) (360 * $value / $sum);

			if (($angle_end - $angle_start) < 1) {
				return false;
			}

			$angle_start = $angle_end;

			return true;
		});
		unset($angle_start);

		// Reserve extra 1px for each slice.
		$space_to_split = 360 - count($visible_values);
		$sum = array_sum($visible_values);

		// Because angles must be integers angles are rounded and all values summed may take less than 360 degrees.
		// Calculate how many angles are missed because of rounding.
		$rounding_diff = $space_to_split;
		foreach ($visible_values as $value) {
			$rounding_diff -= (int) ($space_to_split * $value / $sum);
		}

		// Rounding difference is evenly added to N largest pie slices.
		// Find what is considered a largest slice.
		$rounding_boundary = 0;
		if ($rounding_diff != 0) {
			$values_sorted = $visible_values;
			arsort($values_sorted);
			$rounding_boundary = array_slice($values_sorted, $rounding_diff - 1, 1)[0];
		}

		$angle_start = 0;
		$angle_end = 0;
		$calculated_angles = [];

		foreach ($visible_values as $item => $value) {
			$angle_end += (int) ($space_to_split * $value / $sum) + 1;
			$angle_end = ($angle_end > 360) ? 360 : $angle_end;

			if ($value >= $rounding_boundary && $rounding_diff > 0) {
				// Compensate rounding difference.
				$angle_end += 1;
				$rounding_diff--;
			}

			$calculated_angles[$item] = [$angle_start, $angle_end];

			$angle_start = $angle_end;
		}

		return $calculated_angles;
	}
}
