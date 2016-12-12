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


abstract class CGraphDraw {

	public function __construct($type = GRAPH_TYPE_NORMAL) {
		$this->stime = null;
		$this->fullSizeX = null;
		$this->fullSizeY = null;
		$this->m_minY = null;
		$this->m_maxY = null;
		$this->data = [];
		$this->items = null;
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
		$this->sizeX = 900; // default graph size X
		$this->sizeY = 200; // default graph size Y
		$this->shiftXleft = 100;
		$this->shiftXright = 50;
		$this->shiftXCaption = 0;
		$this->shiftY = 36;
		$this->num = 0;
		$this->type = $type; // graph type
		$this->drawLegend = 1;
		$this->axis_valuetype = []; // overall items type (int/float)
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
			'rightpercentilecolor' => 'E33734'
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

	public function setWidth($value = null) {
		// avoid sizex==0, to prevent division by zero later
		if ($value == 0) {
			$value = null;
		}
		if (is_null($value)) {
			$value = 900;
		}
		$this->sizeX = $value;
	}

	public function setHeight($value = null) {
		if ($value == 0) {
			$value = null;
		}
		if (is_null($value)) {
			$value = 900;
		}
		$this->sizeY = $value;
	}

	public function getLastValue($num) {
		$data = &$this->data[$this->items[$num]['itemid']][$this->items[$num]['calc_type']];

		if (isset($data)) {
			for ($i = $this->sizeX - 1; $i >= 0; $i--) {
				if (!empty($data['count'][$i])) {
					switch ($this->items[$num]['calc_fnc']) {
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
			$str = $this->items[0]['hostname'].NAME_DELIMITER.$this->items[0]['name'];
		}
		else {
			// TODO: graphs shouldn't resolve names themselves
			$str = CMacrosResolverHelper::resolveGraphName($this->header, $this->items);
		}

		if ($this->period) {
			$str .= $this->period2str($this->period);
		}

		// calculate largest font size that can fit graph header
		// TODO: font size must be dynamic in other parts of the graph as well, like legend, timeline, etc
		for ($fontsize = 11; $fontsize > 7; $fontsize--) {
			$dims = imageTextSize($fontsize, 0, $str);
			$x = $this->fullSizeX / 2 - ($dims['width'] / 2);

			// most important information must be displayed, period can be out of the graph
			if ($x < 2) {
				$x = 2;
			}
			if ($dims['width'] <= $this->fullSizeX) {
				break;
			}
		}

		imageText($this->im, $fontsize, 0, $x, 24, $this->getColor($this->graphtheme['textcolor'], 0), $str);
	}

	public function setHeader($header) {
		$this->header = $header;
	}

	public function drawLogo() {
		imagestringup($this->im, 1,
			$this->fullSizeX - 10,
			$this->fullSizeY - 50,
			ZABBIX_HOMEPAGE,
			$this->getColor('Gray')
		);
	}

	public function getColor($color, $alfa = 50) {
		if (isset($this->colors[$color])) {
			return $this->colors[$color];
		}

		return get_color($this->im, $color, $alfa);
	}

	public function getShadow($color, $alfa = 0) {
		if (isset($this->colorsrgb[$color])) {
			$red = $this->colorsrgb[$color][0];
			$green = $this->colorsrgb[$color][1];
			$blue = $this->colorsrgb[$color][2];
		}
		else {
			list($red, $green, $blue) = hex2rgb($color);
		}

		if ($this->sum > 0) {
			$red = (int)($red * 0.6);
			$green = (int)($green * 0.6);
			$blue = (int)($blue * 0.6);
		}

		$RGB = [$red, $green, $blue];

		if (isset($alfa) && function_exists('imagecolorexactalpha') && function_exists('imagecreatetruecolor')
				&& @imagecreatetruecolor(1, 1)) {
			return imagecolorexactalpha($this->im, $RGB[0], $RGB[1], $RGB[2], $alfa);
		}

		return imagecolorallocate($this->im, $RGB[0], $RGB[1], $RGB[2]);
	}
}
