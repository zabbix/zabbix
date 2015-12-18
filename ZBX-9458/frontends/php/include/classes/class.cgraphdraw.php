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
?>
<?php
require_once dirname(__FILE__).'/../items.inc.php';
require_once dirname(__FILE__).'/../hosts.inc.php';

class CGraphDraw {

	public function __construct($type = GRAPH_TYPE_NORMAL) {
		$this->stime = null;
		$this->fullSizeX = null;
		$this->fullSizeY = null;
		$this->m_minY = null;
		$this->m_maxY = null;
		$this->data = array();
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
		$this->border = 1;
		$this->num = 0;
		$this->type = $type; // graph type
		$this->drawLegend = 1;
		$this->axis_valuetype = array(); // overal items type (int/float)
		$this->graphtheme = array(
			'description' => 'default',
			'frontendtheme' => 'default.css',
			'textcolor' => '202020',
			'highlightcolor' => 'aa4444',
			'backgroundcolor' => 'f0f0f0',
			'graphcolor' => 'ffffff',
			'graphbordercolor' => '333333',
			'gridcolor' => 'cccccc',
			'maingridcolor' => 'aaaaaa',
			'gridbordercolor' => '000000',
			'nonworktimecolor' => 'eaeaea',
			'leftpercentilecolor' => '00AA00',
			'righttpercentilecolor' => 'AA0000',
			'legendview' => '1',
			'gridview' => '1'
		);
		$this->applyGraphTheme();
	}

	public function initColors() {
		// red, green, blue, alpha
		$this->colorsrgb = array(
			'Red'				=> array(255, 0, 0, 50),
			'Dark Red'			=> array(150, 0, 0, 50),
			'Green'				=> array(0, 255, 0, 50),
			'Dark Green'		=> array(0, 150, 0, 50),
			'Blue'				=> array(0, 0, 255, 50),
			'Dark Blue'			=> array(0, 0, 150, 50),
			'Yellow'			=> array(255, 255, 0, 50),
			'Dark Yellow'		=> array(150, 150, 0, 50),
			'Cyan'				=> array(0, 255, 255, 50),
			'Dark Cyan'			=> array(0, 150, 150, 50),
			'Black'				=> array(0, 0, 0, 50),
			'Gray'				=> array(150, 150, 150, 50),
			'White'				=> array(255, 255, 255),
			'Dark Red No Alpha'	=> array(150, 0, 0),
			'Black No Alpha'	=> array(0, 0, 0),
			'HistoryMinMax'		=> array(90, 150, 185, 50),
			'HistoryMax'		=> array(255, 100, 100, 50),
			'HistoryMin'		=> array(50, 255, 50, 50),
			'HistoryAvg'		=> array(50, 50, 50, 50),
			'ValueMinMax'		=> array(255, 255, 150, 50),
			'ValueMax'			=> array(255, 180, 180, 50),
			'ValueMin'			=> array(100, 255, 100, 50),
			'Not Work Period'	=> array(230, 230, 230),
			'UnknownData'		=> array(130, 130, 130, 50)
		);

		// i should rename no alpha to alpha at some point to get rid of some confusion
		foreach ($this->colorsrgb as $name => $RGBA) {
			if (isset($RGBA[3]) && function_exists('imagecolorexactalpha')
					&& function_exists('imagecreatetruecolor') && @imagecreatetruecolor(1,1)) {
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
		$theme = DB::find('graph_theme', array(
			'theme' => getUserTheme(CWebUser::$data)
		));
		$theme = reset($theme);
		if ($theme) {
			$this->graphtheme = $theme;
		}
	}

	public function showLegend($type = true) {
		$this->drawLegend = $type;
		return $this->drawLegend;
	}

	public function setPeriod($period) {
		$this->period=$period;
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

	public function setBorder($border) {
		$this->border = $border;
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

		if ($this->border == 1) {
			imagerectangle($this->im, 0, 0,
				$this->fullSizeX - 1,
				$this->fullSizeY - 1,
				$this->getColor($this->graphtheme['graphbordercolor'], 0)
			);
		}
	}

	public function drawSmallRectangle() {
		dashedRectangle($this->im,
			$this->shiftXleft + $this->shiftXCaption - 1,
			$this->shiftY - 1,
			$this->sizeX + $this->shiftXleft + $this->shiftXCaption - 1,
			$this->sizeY + $this->shiftY + 1,
			$this->getColor('Black No Alpha')
		);
	}

	public function period2str($period) {
		return ' ('.zbx_date2age(0, $period).')';
	}

	public function drawHeader() {
		if (!isset($this->header)) {
			$str = $this->items[0]['hostname'].': '.$this->items[0]['name'];
		}
		else {
			$str = $this->header;
		}

		$str .= $this->period2str($this->period);

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
		imagestringup($this->im, 0,
			$this->fullSizeX - 10,
			$this->fullSizeY - 50,
			'http://www.zabbix.com',
			$this->getColor('Gray')
		);
	}

	public function getColor($color, $alfa = 50) {
		if (isset($this->colors[$color])) {
			return $this->colors[$color];
		}

		$RGB = array(
			hexdec('0x'.substr($color, 0, 2)),
			hexdec('0x'.substr($color, 2, 2)),
			hexdec('0x'.substr($color, 4, 2))
		);

		if (isset($alfa) && function_exists('imagecolorexactalpha') && function_exists('imagecreatetruecolor')
				&& @imagecreatetruecolor(1, 1)) {
			return imagecolorexactalpha($this->im, $RGB[0], $RGB[1], $RGB[2], $alfa);
		}
		return imagecolorallocate($this->im, $RGB[0], $RGB[1], $RGB[2]);
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

		$RGB = array($red, $green, $blue);

		if (isset($alfa) && function_exists('imagecolorexactalpha') && function_exists('imagecreatetruecolor')
				&& @imagecreatetruecolor(1, 1)) {
			return imagecolorexactalpha($this->im, $RGB[0], $RGB[1], $RGB[2], $alfa);
		}

		return imagecolorallocate($this->im, $RGB[0], $RGB[1], $RGB[2]);
	}
}
?>
