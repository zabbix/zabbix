<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
	private $time_zone_string;
	private $time;
	private $time_zone_offset;
	private $error;
	private $script_file;

	public function __construct() {
		parent::__construct();

		$this->setId(uniqid());
		$this->addClass(ZBX_STYLE_CLOCK);

		$this->width = null;
		$this->height = null;
		$this->time_zone_string = null;
		$this->time = null;
		$this->time_zone_offset = null;
		$this->error = null;
		$this->script_file = 'js/class.cclock.js';
	}

	public function setWidth($value) {
		$this->width = $value;

		return $this;
	}

	public function setHeight($value) {
		$this->height = $value;

		return $this;
	}

	public function setTimeZoneString($value) {
		$this->time_zone_string = $value;

		return $this;
	}

	public function setTime($value) {
		$this->time = $value;

		return $this;
	}

	public function setTimeZoneOffset($value) {
		$this->time_zone_offset = $value;

		return $this;
	}

	public function setError($value) {
		$this->error = $value;

		return $this;
	}

	public function getTimeDiv() {
		return (new CDiv($this->error))
			->addClass(ZBX_STYLE_TIME_ZONE.'-'.$this->getId())
			->addClass($this->error !== null ? ZBX_STYLE_RED : ZBX_STYLE_GREY);
	}

	public function getScriptFile() {
		return $this->script_file;
	}

	public function getScriptRun() {
		return ($this->error === null)
			? 'jQuery(function($) {'.
				'$("#'.$this->getId().'").zbx_clock('.
					CJs::encodeJson([
						'time' => $this->time,
						'time_zone_string' => $this->time_zone_string,
						'time_zone_offset' => $this->time_zone_offset,
						'clock_id' => $this->getId()
					]).
				');'.
				// Hack for Safari to manually accept parent container height in pixels when clock widget is loaded.
				'if (SF) {'.
					'$("#'.$this->getId().'").height($("#'.$this->getId().'").parent().height());'.
				'}'.
			'});'
			: '';
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
				->addClass('clock-hand-h')
				->addClass(ZBX_STYLE_CLOCK_HAND),
			(new CTag('rect', true))
				->setAttribute('width', '3.25')
				->setAttribute('height', '35')
				->setAttribute('x', '48.375')
				->setAttribute('y', '15')
				->setAttribute('rx', '1.5')
				->setAttribute('ry', '1.5')
				->addClass('clock-hand-m')
				->addClass(ZBX_STYLE_CLOCK_HAND),
			(new CTag('rect', true))
				->setAttribute('width', '1.5')
				->setAttribute('height', '55')
				->setAttribute('x', '49.25')
				->setAttribute('y', '5')
				->addClass('clock-hand-s')
				->addClass(ZBX_STYLE_CLOCK_HAND_SEC),
			(new CTag('circle', true))
				->setAttribute('cx', '50')
				->setAttribute('cy', '50')
				->setAttribute('r', '3.5')
				->addClass(ZBX_STYLE_CLOCK_HAND_SEC)
		];
	}

	private function build() {
		$clock = (new CTag('svg', true))
			->addItem($this->makeClockFace())
			->addItem($this->makeClockHands())
			->setAttribute('xmlns', 'http://www.w3.org/2000/svg')
			->setAttribute('viewBox', '0 0 100 100')
			->addClass(ZBX_STYLE_CLOCK_SVG);

		if ($this->width !== null && $this->height !== null) {
			$clock
				->setAttribute('width', (string) $this->width.'px')
				->setAttribute('height', (string) $this->height.'px');
		}

		if ($this->error !== null) {
			$clock->addClass(ZBX_STYLE_DISABLED);
		}

		$this->addItem($clock);
	}

	public function toString($destroy = true) {
		$this->build();

		return parent::toString($destroy);
	}
}
