<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

	/**
	 * Default top padding including header label height and vertical padding.
	 */
	const DEFAULT_HEADER_PADDING_TOP = 36;
	/**
	 * Default font size for header label text.
	 */
	const DEFAULT_HEADER_LABEL_FONT_SIZE = 11;
	/**
	 * Default value for top and bottom padding.
	 */
	const DEFAULT_TOP_BOTTOM_PADDING = 12;

	/**
	 * Header label visibility.
	 */
	public $draw_header = true;
	/**
	 * Use top and bottom padding for graph image.
	 */
	public $with_vertical_padding = true;

	public function __construct($type = GRAPH_TYPE_NORMAL) {
		$this->stime = null;
		$this->fullSizeX = null;
		$this->fullSizeY = null;
		$this->m_minY = null;
		$this->m_maxY = null;
		$this->data = [];
		$this->items = [];
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
		$this->sizeX = 900; // default graph size X
		$this->sizeY = 200; // default graph size Y
		$this->shiftXleft = 100;
		$this->shiftXright = 50;
		$this->shiftXCaption = 0;
		$this->num = 0;
		$this->type = $type; // graph type
		$this->drawLegend = 1;
		$this->graphtheme = getUserGraphTheme();
		$this->shiftY = 0;
	}

	/**
	 * Recalculate $this->shiftY property for graph according header label visibility settings and visibility of graph
	 * top and bottom padding settings.
	 */
	protected function calculateTopPadding() {
		$shift = static::DEFAULT_HEADER_PADDING_TOP;

		if (!$this->draw_header) {
			$shift -= static::DEFAULT_HEADER_LABEL_FONT_SIZE;
		}

		if (!$this->with_vertical_padding) {
			$shift -= static::DEFAULT_TOP_BOTTOM_PADDING;
		}

		$this->shiftY = $shift;
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
			$value = 200;
		}
		$this->sizeY = $value;
	}

	public function getWidth() {
		return $this->sizeX;
	}

	public function getHeight() {
		return $this->sizeY;
	}

	public function getLastValue($num) {
		$data = &$this->data[$this->items[$num]['itemid']];

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

	public function drawHeader() {
		if (!$this->draw_header) {
			return;
		}

		if (!isset($this->header)) {
			$str = $this->items[0]['hostname'].NAME_DELIMITER.$this->items[0]['name'];
		}
		else {
			// TODO: graphs shouldn't resolve names themselves
			$str = CMacrosResolverHelper::resolveGraphName($this->header, $this->items);
		}

		// calculate largest font size that can fit graph header
		// TODO: font size must be dynamic in other parts of the graph as well, like legend, timeline, etc
		for ($fontsize = static::DEFAULT_HEADER_LABEL_FONT_SIZE; $fontsize > 7; $fontsize--) {
			$dims = imageTextSize($fontsize, 0, $str);
			$x = $this->fullSizeX / 2 - ($dims['width'] / 2);

			// Most important information must be displayed.
			if ($x < 2) {
				$x = 2;
			}
			if ($dims['width'] <= $this->fullSizeX) {
				break;
			}
		}
		$y_baseline = 24;

		if (!$this->with_vertical_padding) {
			$y_baseline -= static::DEFAULT_TOP_BOTTOM_PADDING;
		}

		imageText($this->im, $fontsize, 0, $x, $y_baseline, $this->getColor($this->graphtheme['textcolor'], 0), $str);
	}

	public function setHeader($header) {
		$this->header = $header;
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
