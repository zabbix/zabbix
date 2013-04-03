<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


class CFlashClock extends CFlash {

	public $timetype;
	public $src;

	public function __construct($width = 200, $height = 200, $url = null) {
		$this->timetype = null;

		if (!is_numeric($width) || $width < 24) {
			$width = 200;
		}
		if (!is_numeric($height) || $height < 24) {
			$height = 200;
		}

		$this->src = 'images/flash/zbxclock.swf?analog=1&smooth=1';
		if (!is_null($url)) {
			$this->src .= '&url='.urlencode($url);
		}
		$this->timeError = null;
		$this->timeType = null;
		$this->timeZone = null;
		$this->timeOffset = null;

		parent::__construct($this->src, $width, $height);
	}

	public function setTimeType($value) {
		$this->timeType = $value;
	}

	public function setTimeZone($value) {
		$this->timeZone = $value;
	}

	public function setTimeOffset($value) {
		$this->timeOffset = $value;
	}

	public function setTimeError($value) {
		$this->timeError = $value;
	}

	public function bodyToString() {
		$src = $this->src;
		if (!empty($this->timeError)) {
			$src .= '&timeerror='.$this->timeError;
		}
		if (!empty($this->timeType)) {
			$src .= '&timetype='.urlencode($this->timeType);
		}
		if (!is_null($this->timeZone)) {
			$src .= '&timezone='.urlencode($this->timeZone);
		}
		if (!is_null($this->timeOffset)) {
			$src .= '&timeoffset='.$this->timeOffset;
		}
		$this->setSrc($src);

		return parent::bodyToString();
	}
}
