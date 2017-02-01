<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

	public function __construct($w, $h) {
		$this->width = $w;
		$this->height = $h;

		if (function_exists('imagecreatetruecolor') && @imagecreatetruecolor(1, 1)) {
			$this->canvas = imagecreatetruecolor($this->width, $this->height);
		}
		else {
			$this->canvas = imagecreate($this->width, $this->height);
		}
	}

	public function getWidth() {
		return $this->width;
	}

	public function getHeight() {
		return $this->height;
	}

	public function fill($color) {
		imagefilledrectangle($this->canvas, 0, 0, $this->width, $this->height, get_color($this->canvas, $color));
	}

	public function setBgImage($image) {
		$bg = imagecreatefromstring($image);
		imagecopy($this->canvas, $bg, 0, 0, 0, 0, imagesx($bg), imagesy($bg));
	}

	public function drawTitle($text, $color) {
		$x = $this->width / 2 - imagefontwidth(4) * mb_strlen($text) / 2;
		imagetext($this->canvas, 10, 0, $x, 25, get_color($this->canvas, $color), $text);
	}

	public function getCanvas() {
		$grey = get_color($this->canvas, '969696', 50);

		$date = zbx_date2str(DATE_TIME_FORMAT_SECONDS);
		imagestring($this->canvas, 1, $this->width - 120, $this->height - 12, $date, $grey);
		imagestringup($this->canvas, 1, $this->width - 10, $this->height - 50, ZABBIX_HOMEPAGE, $grey);

		return $this->canvas;
	}

	public function drawLine($x1, $y1, $x2, $y2, $color, $drawtype) {
		myDrawLine($this->canvas, $x1, $y1, $x2, $y2, get_color($this->canvas, $color), $drawtype);
	}

	public function drawText($fontsize, $angle, $x, $y, $color, $string) {
		imageText($this->canvas, $fontsize, $angle, $x, $y, get_color($this->canvas, $color), $string);
	}

	public function drawRectangle($x1, $y1, $x2, $y2, $color) {
		imagerectangle($this->canvas, $x1, $y1, $x2, $y2, get_color($this->canvas, $color));
	}
}
