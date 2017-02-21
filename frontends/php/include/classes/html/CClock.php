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


class CClock extends CDiv {

	private $width;
	private $height;
	private $time_zone_string;
	private $footer;
	private $time;
	private $time_zone_offset;
	private $error;

	public function __construct() {
		parent::__construct();

		$this->addClass(ZBX_STYLE_CLOCK);

		$this->width = 150;
		$this->height = 150;
		$this->time_zone_string = null;
		$this->footer = null;
		$this->time = null;
		$this->time_zone_offset = null;
		$this->error = null;
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

	public function setFooter($value) {
		$this->footer = $value;

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
			->setAttribute('width', (string) $this->width)
			->setAttribute('height', (string) $this->height);

		if ($this->error !== null) {
			$clock->addClass(ZBX_STYLE_DISABLED);
		}

		$this->addItem([
			(new CDiv($this->error))
				->addClass(ZBX_STYLE_TIME_ZONE)
				->addClass($this->error !== null ? ZBX_STYLE_RED : ZBX_STYLE_GREY),
			$clock,
			(new CDiv($this->footer))
				->addClass(ZBX_STYLE_LOCAL_CLOCK)
				->addClass(ZBX_STYLE_GREY)
		]);

		$this->setId(uniqid());

		$options = [
			'time' => $this->time,
			'time_zone_string' => $this->time_zone_string,
			'time_zone_offset' => $this->time_zone_offset
		];

		if (!defined('ZBX_CLOCK') && $this->error === null) {
			define('ZBX_CLOCK', 1);

			insert_js("
jQuery(function($) {
	/**
	 * Create clock element.
	 *
	 * @param int    options['time']				time in seconds
	 * @param int    options['time_zone_string']	time zone string like 'GMT+02:00'
	 * @param int    options['time_zone_offset']	time zone offset in seconds
	 *
	 * @return object
	 */
	$.fn.zbx_clock = function(options) {
		var obj = $(this);

		if (obj.length == 0) {
			return false;
		}

		clock_hands_start();

		return this;

		function clock_hands_start() {
			var time_offset = 0,
				now = new Date();

			if (options.time !== null) {
				time_offset = now.getTime() - options.time * 1000;
			}

			if (options.time_zone_offset !== null) {
				time_offset += (- now.getTimezoneOffset() * 60 - options.time_zone_offset) * 1000;
			}

			clock_hands_rotate(time_offset);

			setInterval(function() {
				clock_hands_rotate(time_offset);
			}, 1000);
		}

		function clock_hands_rotate(time_offset) {
			var now = new Date();

			if (time_offset != 0) {
				now.setTime(now.getTime() - time_offset);
			}

			var header = now.toTimeString().replace(/.*(\d{2}:\d{2}:\d{2}).*/, \"$1\");

			if (options.time_zone_string !== null) {
				header = header + ' ' + options.time_zone_string;
			}

			$('.time-zone', obj).text(header);

			var h = now.getHours() % 12,
				m = now.getMinutes(),
				s = now.getSeconds();

			clock_hand_rotate($('.clock-hand-h', obj), 30 * (h + m / 60 + s / 3600));
			clock_hand_rotate($('.clock-hand-m', obj), 6 * (m + s / 60));
			clock_hand_rotate($('.clock-hand-s', obj), 6 * s);
		}

		function clock_hand_rotate(clock_hand, degree) {
			$(clock_hand).attr('transform', 'rotate(' + degree + ' 50 50)');
		}
	}
});
			");
		}

		if ($this->error === null) {
			zbx_add_post_js('jQuery("#'.$this->getId().'").zbx_clock('.CJs::encodeJson($options).');');
		}
	}

	public function toString($destroy = true) {
		$this->build();

		return parent::toString($destroy);
	}
}
