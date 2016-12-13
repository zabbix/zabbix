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


class CGraphDraw extends CSvg {

	public function __construct($type = GRAPH_TYPE_NORMAL) {
		parent::__construct();

		$this->stime = null;
		$this->fullSizeX = 900;
		$this->fullSizeY = 200;
		$this->m_minY = null;
		$this->m_maxY = null;
		$this->data = [];
		$this->graph_items = null;
		$this->min = null;
		$this->max = null;
		$this->avg = null;
		$this->clock = null;
		$this->count = null;
		$this->header = null;
		$this->from_time = null;
		$this->to_time = null;
		$this->colors = null;
		$this->colorsrgb = null;
		$this->im = null;
		$this->period = SEC_PER_HOUR;
		$this->from = 0;
		$this->sizeX = null; // default graph size X
		$this->sizeY = null; // default graph size Y
		$this->shiftXleft = 100;
		$this->shiftXright = 50;
		$this->shiftXCaption = 0;
		$this->shiftY = 36;
		$this->num = 0;
		$this->type = $type; // graph type
		$this->drawLegend = 1;
		$this->axis_valuetype = []; // overal items type (int/float)
		$this->graphtheme = [
			'theme' => 'blue-theme',
			'textcolor' => '1F2C33',
			'highlightcolor' => 'E33734',
			'backgroundcolor' => 'FFFFFF',
			'graphcolor' => 'FFFFFF',
			'gridcolor' => 'CCD5D9',
			'maingridcolor' => 'ACBBC2',
			'gridbordercolor' => 'ACBBC2',
			'nonworktimecolor' => 'EBEBEB',
			'leftpercentilecolor' => '429E47',
			'righttpercentilecolor' => 'E33734'
		];
		$this->applyGraphTheme();
	}

	public function initColors() {
		// red, green, blue, alpha
		$this->colorsrgb = [
			'Red'				=> [255, 0, 0, 50],
			'Dark Red'			=> [150, 0, 0, 50],
			'Green'				=> [0, 255, 0, 50],
			'Dark Green'		=> [0, 150, 0, 50],
			'Blue'				=> [0, 0, 255, 50],
			'Dark Blue'			=> [0, 0, 150, 50],
			'Yellow'			=> [255, 255, 0, 50],
			'Dark Yellow'		=> [150, 150, 0, 50],
			'Cyan'				=> [0, 255, 255, 50],
			'Dark Cyan'			=> [0, 150, 150, 50],
			'Black'				=> [0, 0, 0, 50],
			'Gray'				=> [150, 150, 150, 50],
			'White'				=> [255, 255, 255],
			'Dark Red No Alpha'	=> [150, 0, 0],
			'Black No Alpha'	=> [0, 0, 0],
			'HistoryMinMax'		=> [90, 150, 185, 50],
			'HistoryMax'		=> [255, 100, 100, 50],
			'HistoryMin'		=> [50, 255, 50, 50],
			'HistoryAvg'		=> [50, 50, 50, 50],
			'ValueMinMax'		=> [255, 255, 150, 50],
			'ValueMax'			=> [255, 180, 180, 50],
			'ValueMin'			=> [100, 255, 100, 50],
			'Not Work Period'	=> [230, 230, 230],
			'UnknownData'		=> [130, 130, 130, 50]
		];

		// i should rename no alpha to alpha at some point to get rid of some confusion
		foreach ($this->colorsrgb as $name => $RGBA) {
			if (isset($RGBA[3]) && function_exists('imagecolorexactalpha')
					&& function_exists('imagecreatetruecolor') && @imagecreatetruecolor(1, 1)) {
				$this->colors[$name] = imagecolorexactalpha($this->im, $RGBA[0], $RGBA[1], $RGBA[2], $RGBA[3]);
			}
			else {
				$this->colors[$name] = imagecolorallocate($this->im, $RGBA[0], $RGBA[1], $RGBA[2]);
			}
		}
	}

	/**
	 * Load the graph theme from the database.
	 */
	public function applyGraphTheme() {
		$themes = DB::find('graph_theme', [
			'theme' => getUserTheme(CWebUser::$data)
		]);
		if ($themes) {
			$this->graphtheme = $themes[0];
		}
	}

	public function showLegend($type = true) {
		$this->drawLegend = $type;
	}

	public function setPeriod($period) {
		$this->period = $period;
	}

	public function setSTime($stime) {
		if ($stime > 19000000000000 && $stime < 21000000000000) {
			$this->stime = zbxDateToTime($stime);
		}
		else {
			$this->stime = $stime;
		}
	}

	public function setFrom($from) {
		$this->from = $from;
	}

	public function setWidth($width = null) {
		// avoid sizex==0, to prevent division by zero later
		if ($width == 0) {
			$width = null;
		}
		if (is_null($width)) {
			$width = 900;
		}
//		$this->sizeX = $value;
		$this->fullSizeX = $width;
		// TODO SVG
//		$this->setAttribute('width', ($this->sizeX+150).'px');
		$this->setAttribute('width', $this->fullSizeX.'px');
	}

	public function setHeight($height = null) {
		if ($height == 0) {
			$height = null;
		}
		if (is_null($height)) {
			$height = 900;
		}
		$this->fullSizeY = $height;
		// TODO SVG
		$this->setAttribute('height', $this->fullSizeY.'px');
	}

	public function getLastValue($num) {
		$data = &$this->data[$this->graph_items[$num]['itemid']][$this->graph_items[$num]['calc_type']];

		if (isset($data)) {
			for ($i = $this->sizeX - 1; $i >= 0; $i--) {
				if (!empty($data['count'][$i])) {
					switch ($this->graph_items[$num]['calc_fnc']) {
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
		}

		return 0;
	}

	public function drawBackground() {
		$this->setAttribute('style', 'background:#'.$this->graphtheme['backgroundcolor']);
	}

	public function drawRectangle() {
		imagefilledrectangle($this->im, 0, 0,
			$this->fullSizeX,
			$this->fullSizeY,
			$this->getColor($this->graphtheme['backgroundcolor'], 0)
		);
	}

	public function period2str($period) {
		return ' ('.zbx_date2age(0, $period).')';
	}

	public function drawHeader() {
		if (!isset($this->header)) {
			$str = $this->graph_items[0]['hostname'].NAME_DELIMITER.$this->graph_items[0]['name'];
		}
		else {
			// TODO: graphs shouldn't resolve names themselves
			$str = CMacrosResolverHelper::resolveGraphName($this->header, $this->graph_items);
		}

		$this->addItem(
			(new CText(
				$this->fullSizeX/2,
				24,
				$str,
				'#'.$this->graphtheme['textcolor']))
			->setAttribute('text-anchor', 'middle')
		);
	}

	public function setHeader($header) {
		$this->header = $header;
	}

	public function drawLogo() {
		$this->addItem(
			(new CText(
				$this->fullSizeX - 10,
				$this->fullSizeY - 50,
				ZABBIX_HOMEPAGE,
				'Gray'))
				->setAngle(-90)
				->setAttribute('opacity', '0.2')
		);
	}

	public function getColor($color, $alfa = 50) {
		if (isset($this->colors[$color])) {
			return $this->colors[$color];
		}

		return get_color($this->im, $color, $alfa);
	}
}
