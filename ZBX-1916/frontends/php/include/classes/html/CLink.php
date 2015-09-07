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

	private	$use_sid = true;
	private $url = null;

	public function __construct($item = null, $url = null) {
		parent::__construct('a', true);

		if ($item !== null) {
			$this->addItem($item);
		}
		$this->url = $url;
	}

	public function removeSID() {
		$this->use_sid = false;
		return $this;
	}

	private function getUrl() {
		$url = $this->url;

		if ($this->use_sid) {
			if (array_key_exists('zbx_sessionid', $_COOKIE)) {
				$url .= (strpos($url, '&') !== false || strpos($url, '?') !== false) ? '&' : '?';
				$url .= 'sid='.substr($_COOKIE['zbx_sessionid'], 16, 16);
			}
		}
		return $url;
	}

	public function setTarget($value = null) {
		$this->attributes['target'] = $value;
		return $this;
	}

	public function toString($destroy = true) {
		$this->setAttribute('href', $this->getUrl());

		return parent::toString($destroy);
	}
}
