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


class CPieGraphDraw extends CGraphDraw {

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
	public function addGraphItem($itemid, $calc_fnc = CALC_FNC_AVG, $color = null, $type = null) {
		$graph_items = CMacrosResolverHelper::resolveItemNames([get_item_by_itemid($itemid)]);

		$this->graph_items[$this->num] = reset($graph_items);

		$host = get_host_by_hostid($this->graph_items[$this->num]['hostid']);

		$this->graph_items[$this->num]['host'] = $host['host'];
		$this->graph_items[$this->num]['hostname'] = $host['name'];
		$this->graph_items[$this->num]['color'] = is_null($color) ? 'Dark Green' : $color;
		$this->graph_items[$this->num]['calc_fnc'] = is_null($calc_fnc) ? CALC_FNC_AVG : $calc_fnc;
		$this->graph_items[$this->num]['calc_type'] = is_null($type) ? GRAPH_ITEM_SIMPLE : $type;

		$this->num++;
	}

	public function set3DAngle($angle = 70) {
		if (is_numeric($angle) && $angle < 85 && $angle > 10) {
			$this->angle3d = (int) $angle;
		}
		else {
			$this->angle3d = 70;
		}
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

	public function switchPieExploded($type) {
		if ($type) {
			$this->type = $type;
		}
		else {
			switch ($this->type) {
				case GRAPH_TYPE_EXPLODED:
					$this->type = GRAPH_TYPE_PIE;
					break;
				case GRAPH_TYPE_3D_EXPLODED:
					$this->type = GRAPH_TYPE_3D;
					break;
				case GRAPH_TYPE_3D:
					$this->type = GRAPH_TYPE_3D_EXPLODED;
					break;
				case GRAPH_TYPE_PIE:
					$this->type = GRAPH_TYPE_EXPLODED;
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

		$y+= round($count * sin(deg2rad($anglemid)));
		$x+= round($count * cos(deg2rad($anglemid)));

		return [$x, $y];
	}

	protected function calcExplodedRadius($sizeX, $sizeY, $count) {
		$count *= $this->exploderad * 2;
		$sizeX -= $count;
		$sizeY -= $count;
		return [$sizeX, $sizeY];
	}

	protected function calc3DAngle($sizeX, $sizeY) {
		$sizeY *= GRAPH_3D_ANGLE / 90;
		return [$sizeX, round($sizeY)];
	}

	protected function selectData() {
		$this->data = [];
		$now = time(null);

		if (isset($this->stime)) {
			$this->from_time = $this->stime;
			$this->to_time = $this->stime + $this->period;
		}
		else {
			$this->to_time = $now - SEC_PER_HOUR * $this->from;
			$this->from_time = $this->to_time - $this->period;
		}

		$strvaluelength = 0; // we need to know how long in px will be our legend

		// fetch values for items with the "last" function
		$lastValueItems = [];
		foreach ($this->graph_items as $item) {
			if ($item['calc_fnc'] == CALC_FNC_LST) {
				$lastValueItems[] = $item;
			}
		}
		if ($lastValueItems) {
			$history = Manager::History()->getLast($lastValueItems);
		}

		$config = select_config();

		for ($i = 0; $i < $this->num; $i++) {
			$item = get_item_by_itemid($this->graph_items[$i]['itemid']);
			$type = $this->graph_items[$i]['calc_type'];
			$from_time = $this->from_time;
			$to_time = $this->to_time;

			// override item history setting with housekeeping settings
			if ($config['hk_history_global']) {
				$item['history'] = $config['hk_history'];
			}

			$trendsEnabled = $config['hk_trends_global'] ? ($config['hk_trends'] > 0) : ($item['trends'] > 0);

			if (!$trendsEnabled || (($item['history'] * SEC_PER_DAY) > (time() - ($from_time + $this->period / 2)))) {
				$this->dataFrom = 'history';

				$sql_select = 'AVG(value) AS avg,MIN(value) AS min,MAX(value) AS max';
				$sql_from = ($item['value_type'] == ITEM_VALUE_TYPE_UINT64) ? 'history_uint' : 'history';
			}
			else {
				$this->dataFrom = 'trends';

				$sql_select = 'AVG(value_avg) AS avg,MIN(value_min) AS min,MAX(value_max) AS max';
				$sql_from = ($item['value_type'] == ITEM_VALUE_TYPE_UINT64) ? 'trends_uint' : 'trends';
			}

			$this->data[$this->graph_items[$i]['itemid']][$type]['last'] = isset($history[$item['itemid']])
				? $history[$item['itemid']][0]['value'] : null;
			$this->data[$this->graph_items[$i]['itemid']][$type]['shift_min'] = 0;
			$this->data[$this->graph_items[$i]['itemid']][$type]['shift_max'] = 0;
			$this->data[$this->graph_items[$i]['itemid']][$type]['shift_avg'] = 0;

			$result = DBselect(
				'SELECT itemid,'.$sql_select.',MAX(clock) AS clock'.
				' FROM '.$sql_from.
				' WHERE itemid='.zbx_dbstr($this->graph_items[$i]['itemid']).
					' AND clock>='.zbx_dbstr($from_time).
					' AND clock<='.zbx_dbstr($to_time).
				' GROUP BY itemid'
			);
			while ($row = DBfetch($result)) {
				$this->data[$this->graph_items[$i]['itemid']][$type]['min'] = $row['min'];
				$this->data[$this->graph_items[$i]['itemid']][$type]['max'] = $row['max'];
				$this->data[$this->graph_items[$i]['itemid']][$type]['avg'] = $row['avg'];
				$this->data[$this->graph_items[$i]['itemid']][$type]['clock'] = $row['clock'];
			}
			unset($row);

			switch ($this->graph_items[$i]['calc_fnc']) {
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

			$item_value = empty($this->data[$this->graph_items[$i]['itemid']][$type][$fncName])
				? 0
				: abs($this->data[$this->graph_items[$i]['itemid']][$type][$fncName]);

			if ($type == GRAPH_ITEM_SUM) {
				$this->background = $i;
				$graph_sum = $item_value;
			}

			$this->sum += $item_value;

			$convertedUnit = strlen(convert_units([
				'value' => $item_value,
				'units' => $this->graph_items[$i]['units']
			]));
			$strvaluelength = max($strvaluelength, $convertedUnit);
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
		$displayHostName = (count(array_unique(zbx_objectValues($this->graph_items, 'hostname'))) > 1);

		// calculate function name X shift
		$functionNameXShift = 0;

		foreach ($this->graph_items as $item) {
			$name = $displayHostName ? $item['hostname'].': '.$item['name_expanded'] : $item['name_expanded'];
			$dims = imageTextSize($fontSize, 0, $name);

			if ($dims['width'] > $functionNameXShift) {
				$functionNameXShift = $dims['width'];
			}
		}

		// display items
		$i = 0;

		foreach ($this->graph_items as $item) {
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

			if (isset($this->data[$item['itemid']][$item['calc_type']])
					&& isset($this->data[$item['itemid']][$item['calc_type']][$fncName])) {
				$dataValue = $this->data[$item['itemid']][$item['calc_type']][$fncName];
				$proc = ($this->sum == 0) ? 0 : ($dataValue * 100) / $this->sum;

				$strValue = sprintf(_('Value').': %s ('.(round($proc) != round($proc, 2) ? '%0.2f' : '%0.0f').'%%)',
					convert_units([
						'value' => $dataValue,
						'units' => $this->graph_items[$i]['units']
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
			$text = $displayHostName ? $item['hostname'].': '.$item['name_expanded'] : $item['name_expanded'];
			$this->addItem(
				new CText(
					$this->shiftXleft + 15,
					$this->sizeY + $shiftY + 14 * $i + 5,
					$text,
					'#'.$this->graphtheme['textcolor'])
			);

			// function name
			$this->addItem(
				new CText(
					$this->shiftXleft + $functionNameXShift + 30,
					$this->sizeY + $shiftY + 14 * $i + 5,
					$str,
					'#'.$this->graphtheme['textcolor'])
			);

			// left square
			$this->addItem(
				(new CRect($this->shiftXleft, $this->sizeY + $shiftY + 14 * $i - 5, 10, 10))
				->setStrokeWidth(1)
				->setFillColor('#'.$item['color'])
				->setStrokeColor('black')
			);

			$shiftX = $this->fullSizeX - $this->shiftlegendright - $this->shiftXright + 25;

			// right square
			$this->addItem(
				(new CRect($shiftX - 10, $this->shiftY + 10 + 14 * $i, 10, 10))
				->setStrokeWidth(1)
				->setFillColor('#'.$item['color'])
				->setStrokeColor('black')
			);

			// item value
			$this->addItem(
				new CText(
					$shiftX + 5,
					$this->shiftY + 10 + 14 * $i + 10,
					$strValue,
					'#'.$this->graphtheme['textcolor'])
			);

			$i++;
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
		else {
			$sizeX = (int) $sizeX * 0.95;
			$sizeY = (int) $sizeY * 0.95;
		}

		$xc = $x = (int) $this->sizeX / 2 + $this->shiftXleft;
		$yc = $y = (int) $this->sizeY / 2 + $this->shiftY;

		$anglestart = 0;
		$angleend = 0;
		foreach ($values as $item => $value) {
			$angleend += (int) (360 * $value / $sum) + 1;
			$angleend = ($angleend > 360) ? 360 : $angleend;
			if (($angleend - $anglestart) < 1) {
				continue;
			}

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
				$this->getColor((!$isEmptyData ? $this->graph_items[$item]['color'] : 'FFFFFF'), 0),
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
			$anglestart = $angleend;
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

		$xc = $x = (int) $this->sizeX / 2 + $this->shiftXleft;
		$yc = $y = (int) $this->sizeY / 2 + $this->shiftY;

		// bottom angle line
		$anglestart = 0;
		$angleend = 0;
		foreach ($values as $item => $value) {
			$angleend += (int) (360 * $value / $sum) + 1;
			$angleend = ($angleend > 360) ? 360 : $angleend;
			if (($angleend - $anglestart) < 1) {
				continue;
			}

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
// SVG			$this->getShadow((!$isEmptyData ? $this->items[$item]['color'] : 'FFFFFF'), 0), // SVG
				$this->getColor('Black'),
				IMG_ARC_PIE
			);
			$this->addItem(
				(new CTag('path', true))
					->setAttribute('d', 'M80 80 A 45 45, 0, 0, 0, 125 125 L 125 80 Z')
					->setAttribute('fill', 'blue')
					->setAttribute('stroke', 'black')
					->setAttribute('stroke-width', 1)
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
			$anglestart = $angleend;
		}

		// 3d effect
		for ($i = $this->graphheight3d; $i > 0; $i--) {
			$anglestart = 0;
			$angleend = 0;
			foreach ($values as $item => $value) {
				$angleend += (int) (360 * $value / $sum) + 1;
				$angleend = ($angleend > 360) ? 360 : $angleend;

				if (($angleend - $anglestart) < 1) {
					continue;
				}
				elseif ($this->sum == 0) {
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
// SVG				$this->getShadow((!$isEmptyData ? $this->items[$item]['color'] : 'FFFFFF'), 0), // SVG
					$this->getColor('Black'),
					IMG_ARC_PIE
				);
				$anglestart = $angleend;
			}
		}

		$anglestart = 0;
		$angleend = 0;
		foreach ($values as $item => $value) {
			$angleend += (int) (360 * $value / $sum) + 1;
			$angleend = ($angleend > 360) ? 360 : $angleend;
			if (($angleend - $anglestart) < 1) {
				continue;
			}

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
				$this->getColor((!$isEmptyData ? $this->graph_items[$item]['color'] : 'FFFFFF'), 0),
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
			$anglestart = $angleend;
		}
	}

	public function draw() {
		$start_time = microtime(true);
// SVG		set_image_header();

		$this->selectData();

		$this->shiftY = 30;
		$this->shiftYLegend = 20;
		$this->shiftXleft = 10;
		$this->shiftXright = 0;
		$this->sizeX = $this->fullSizeX;
		$this->sizeY = $this->fullSizeY;

		if ($this->fullSizeX < 300 || $this->fullSizeY < 200) {
			$this->showLegend(0);
		}

		if ($this->drawLegend == 1) {
			$this->sizeX -= $this->shiftXleft + $this->shiftXright + $this->shiftlegendright;
			$this->sizeY -= $this->shiftY + $this->shiftYLegend + 12 * $this->num + 8;
		}
		else {
			$this->sizeX -= $this->shiftXleft * 2;
			$this->sizeY -= $this->shiftY * 2;
		}

		$this->sizeX = min($this->sizeX, $this->sizeY);
		$this->sizeY = min($this->sizeX, $this->sizeY);

		$this->calc3dheight($this->sizeY);

		$this->exploderad = (int) $this->sizeX / 100;
		$this->exploderad3d = (int) $this->sizeX / 60;

		$this->im = imagecreate(100, 100);

		$this->drawBackground();
		$this->drawHeader();

		// for each metric
		$values = [];
		for ($i = 0; $i < $this->num; $i++) {
			$type = $this->graph_items[$i]['calc_type'];

			$data = &$this->data[$this->graph_items[$i]['itemid']][$type];

			if (!isset($data)) {
				continue;
			}

			switch ($this->graph_items[$i]['calc_fnc']) {
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

			$values[$i] = empty($this->data[$this->graph_items[$i]['itemid']][$type][$fncName])
				? 0
				: abs($this->data[$this->graph_items[$i]['itemid']][$type][$fncName]);
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

		$this->drawLogo();
		if ($this->drawLegend == 1) {
			$this->drawLegend();
		}

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
}
