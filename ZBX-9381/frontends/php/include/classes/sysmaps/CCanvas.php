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


class CCanvas {

	protected $canvas;
	protected $width;
	protected $height;
	protected $colors = array();

	public function __construct($w, $h) {
		$this->width = $w;
		$this->height = $h;

		if (function_exists('imagecreatetruecolor') && @imagecreatetruecolor(1, 1)) {
			$this->canvas = imagecreatetruecolor($this->width, $this->height);
		}
		else {
			$this->canvas = imagecreate($this->width, $this->height);
		}

		$this->allocateColors();
	}

	public function getWidth() {
		return $this->width;
	}

	public function getHeight() {
		return $this->height;
	}

	public function fill($color) {
		imagefilledrectangle($this->canvas, 0, 0, $this->width, $this->height, $this->getColor($color));
	}

	public function setBgImage($image) {
		$bg = imagecreatefromstring($image);
		imagecopy($this->canvas, $bg, 0, 0, 0, 0, imagesx($bg), imagesy($bg));
	}

	public function drawTitle($text, $color) {
		$x = $this->width / 2 - imagefontwidth(4) * zbx_strlen($text) / 2;
		imagetext($this->canvas, 10, 0, $x, 25, $this->getColor($color), $text);
	}

	public function drawBorder($color) {
		imagerectangle($this->canvas, 0, 0, $this->width - 1, $this->height - 1, $this->getColor($color));
	}

	public function getCanvas() {
		$date = zbx_date2str(MAPS_DATE_FORMAT);
		imagestring($this->canvas, 0, $this->width - 120, $this->height - 12, $date, $this->getColor('gray'));
		imagestringup($this->canvas, 0, $this->width - 10, $this->height - 50, ZABBIX_HOMEPAGE, $this->getColor('gray'));

		return $this->canvas;
	}

	public function drawLine($x1, $y1, $x2, $y2, $color, $drawtype) {
		myDrawLine($this->canvas, $x1, $y1, $x2, $y2, $this->getColor($color), $drawtype);
	}

	public function drawText($fontsize, $angle, $x, $y, $color, $string) {
		imageText($this->canvas, $fontsize, $angle, $x, $y, $this->getColor($color), $string);
	}

	public function drawRectangle($x1, $y1, $x2, $y2, $color) {
		imagerectangle($this->canvas, $x1, $y1, $x2, $y2, $this->getColor($color));
	}

	public function drawRoundedRectangle($x1, $y1, $x2, $y2, $radius, $color) {
		$color = $this->getColor($color);
		$arcRadius = $radius * 2;
		imagearc($this->canvas, $x1 + $radius, $y1 + $radius, $arcRadius, $arcRadius, 180, 270, $color);
		imagearc($this->canvas, $x1 + $radius, $y2 - $radius, $arcRadius, $arcRadius, 90, 180, $color);
		imagearc($this->canvas, $x2 - $radius, $y1 + $radius, $arcRadius, $arcRadius, 270, 0, $color);
		imagearc($this->canvas, $x2 - $radius, $y2 - $radius, $arcRadius, $arcRadius, 0, 90, $color);

		zbx_imageline($this->canvas, $x1 + $radius, $y1, $x2 - $radius, $y1, $color);
		zbx_imageline($this->canvas, $x1 + $radius, $y2, $x2 - $radius, $y2, $color);
		zbx_imageline($this->canvas, $x1, $y1 + $radius, $x1, $y2 - $radius, $color);
		zbx_imageline($this->canvas, $x2, $y1 + $radius, $x2, $y2 - $radius, $color);
	}

	protected function getColor($color) {
		if (!isset($this->colors[$color])) {
			throw new Exception('Color "'.$color.'" is not allocated.');
		}
		return $this->colors[$color];
	}

	protected function allocateColors() {
		$this->colors['red'] = imagecolorallocate($this->canvas, 255, 0, 0);
		$this->colors['darkred'] = imagecolorallocate($this->canvas, 150, 0, 0);
		$this->colors['green'] = imagecolorallocate($this->canvas, 0, 255, 0);
		$this->colors['darkgreen'] = imagecolorallocate($this->canvas, 0, 150, 0);
		$this->colors['blue'] = imagecolorallocate($this->canvas, 0, 0, 255);
		$this->colors['darkblue'] = imagecolorallocate($this->canvas, 0, 0, 150);
		$this->colors['yellow'] = imagecolorallocate($this->canvas, 255, 255, 0);
		$this->colors['darkyellow'] = imagecolorallocate($this->canvas, 150, 150, 0);
		$this->colors['cyan'] = imagecolorallocate($this->canvas, 0, 255, 255);
		$this->colors['black'] = imagecolorallocate($this->canvas, 0, 0, 0);
		$this->colors['gray'] = imagecolorallocate($this->canvas, 150, 150, 150);
		$this->colors['gray1'] = imagecolorallocate($this->canvas, 180, 180, 180);
		$this->colors['gray2'] = imagecolorallocate($this->canvas, 210, 210, 210);
		$this->colors['gray3'] = imagecolorallocate($this->canvas, 240, 240, 240);
		$this->colors['white'] = imagecolorallocate($this->canvas, 255, 255, 255);
		$this->colors['orange'] = imagecolorallocate($this->canvas, 238, 96, 0);
	}
}
