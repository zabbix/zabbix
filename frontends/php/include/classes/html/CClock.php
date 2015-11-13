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


class CClock extends CDiv {

	private $width;
	private $height;
	private $time_zone;
	private $title;

	public $timetype;
	public $src;

	public function __construct() {
		parent::__construct();

		$this->addClass(ZBX_STYLE_CLOCK);

		$this->width = 150;
		$this->height = 150;
		$this->time_zone = null;
		$this->title = null;

		$this->timetype = null;

		$this->timeError = null;
		$this->timeType = null;
		$this->timeZone = null;
		$this->timeOffset = null;
	}

	public function setWidth($value) {
		$this->width = $value;

		return $this;
	}

	public function setHeight($value) {
		$this->height = $value;

		return $this;
	}

	public function setTimeZone($value) {
		$this->time_zone = $value;

		return $this;
	}

	public function setTitle($value) {
		$this->title = $value;

		return $this;
	}

	public function setTimeOffset($value) {
		$this->timeOffset = $value;
		return $this;
	}

	public function setTimeError($value) {
		$this->timeError = $value;
		return $this;
	}

	private function makeClockLine($width, $height, $x, $y, $deg) {
		return (new CTag('rect', true))
			->setAttribute('width', $width)
			->setAttribute('height', $height)
			->setAttribute('x', $x)
			->setAttribute('y', $y)
			->setAttribute('transform', 'rotate('.$deg.' 50 50)')
			->addClass(ZBX_STYLE_CLOCK_LINES);
	}

	private function makeClockFace() {
		return [
			(new CTag('circle', true))
				->setAttribute('cx', '50')
				->setAttribute('cy', '50')
				->setAttribute('r', '50')
				->addClass(ZBX_STYLE_CLOCK_FACE),
			$this->makeClockLine('1.5', '5', '49.25', '5', '330'),
			$this->makeClockLine('1.5', '5', '49.25', '5', '300'),
			$this->makeClockLine('2.5', '7', '48.75', '5', '270'),
			$this->makeClockLine('1.5', '5', '49.25', '5', '240'),
			$this->makeClockLine('1.5', '5', '49.25', '5', '210'),
			$this->makeClockLine('2.5', '7', '48.75', '5', '180'),
			$this->makeClockLine('1.5', '5', '49.25', '5', '150'),
			$this->makeClockLine('1.5', '5', '49.25', '5', '120'),
			$this->makeClockLine('2.5', '7', '48.75', '5', '90'),
			$this->makeClockLine('1.5', '5', '49.25', '5', '60'),
			$this->makeClockLine('1.5', '5', '49.25', '5', '30'),
			$this->makeClockLine('2.5', '7', '48.75', '5', '0')
		];
	}

	private function makeClockHands() {
		return [
			(new CTag('rect', true))
				->setAttribute('width', '3.25')
				->setAttribute('height', '24')
				->setAttribute('x', '48.375')
				->setAttribute('y', '26')
				->setAttribute('rx', '1.5')
				->setAttribute('ry', '1.5')
//				->setAttribute('transform', 'rotate(60 50 50)')
				->addClass(ZBX_STYLE_CLOCK_HAND),
			(new CTag('rect', true))
				->setAttribute('width', '3.25')
				->setAttribute('height', '35')
				->setAttribute('x', '48.375')
				->setAttribute('y', '15')
				->setAttribute('rx', '1.5')
				->setAttribute('ry', '1.5')
//				->setAttribute('transform', 'rotate(-300 50 50)')
				->addClass(ZBX_STYLE_CLOCK_HAND),
			(new CTag('rect', true))
				->setAttribute('width', '1.5')
				->setAttribute('height', '55')
				->setAttribute('x', '49.25')
				->setAttribute('y', '5')
//				->setAttribute('transform', 'rotate(190 50 50)')
				->addClass(ZBX_STYLE_CLOCK_HAND_SEC),
			(new CTag('circle', true))
				->setAttribute('cx', '50')
				->setAttribute('cy', '50')
				->setAttribute('r', '3.5')
				->addClass(ZBX_STYLE_CLOCK_HAND_SEC)
		];
	}

	private function build() {
		$time_zone = null;
		$title = null;

		if ($this->time_zone !== null) {
			$time_zone = (new CDiv($this->time_zone))
				->addClass(ZBX_STYLE_TIME_ZONE)
				->addClass(ZBX_STYLE_GREY);
		}

		if ($this->title !== null) {
			$title = (new CDiv($this->title))
				->addClass(ZBX_STYLE_LOCAL_CLOCK)
				->addClass(ZBX_STYLE_GREY);
		}

		$this->addItem([
			$time_zone,
			(new CTag('svg', true))
				->addItem($this->makeClockFace())
				->addItem($this->makeClockHands())
				->setAttribute('xmlns', 'http://www.w3.org/2000/svg')
				->setAttribute('viewBox', '0 0 100 100')
				->setAttribute('width', (string) $this->width)
				->setAttribute('height', (string) $this->height),
			$title
		]);
	}

	public function toString($destroy = true) {
		$this->build();

		return parent::toString($destroy);
	}
}
