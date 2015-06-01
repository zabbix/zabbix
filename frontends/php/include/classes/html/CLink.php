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


class CLink extends CTag {

	protected $sid = null;
	private	$usesid = true;

	public function __construct($item = null, $url = null) {
		parent::__construct('a', 'yes');

		if (!is_null($item)) {
			$this->addItem($item);
		}
		if (!is_null($url)) {
			$this->setUrl($url);
		}
	}

	private function setUrl($value) {
		if ($this->usesid) {
			if (is_null($this->sid)) {
				$this->sid = isset($_COOKIE['zbx_sessionid']) ? substr($_COOKIE['zbx_sessionid'], 16, 16) : null;
			}
			if (!is_null($this->sid)) {
				$value .= (strpos($value, '&') !== false || strpos($value, '?') !== false)
					? '&sid='.$this->sid
					: '?sid='.$this->sid;
			}
			$url = $value;
		}
		else {
			$url = $value;
		}
		$this->setAttribute('href', $url);
	}

	public function removeSID() {
		$this->usesid  = false;

		return $this;
	}

	public function getUrl() {
		return isset($this->attributes['href']) ? $this->attributes['href'] : null;
	}

	public function setTarget($value = null) {
		$this->attributes['target'] = $value;

		return $this;
	}
}
