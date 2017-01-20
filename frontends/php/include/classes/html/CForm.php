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


class CForm extends CTag {

	public function __construct($method = 'post', $action = null, $enctype = null) {
		parent::__construct('form', true);
		$this->setMethod($method);
		$this->setAction($action);
		$this->setEnctype($enctype);
		$this->setAttribute('accept-charset', 'utf-8');

		if (isset($_COOKIE['zbx_sessionid'])) {
			$this->addVar('sid', substr($_COOKIE['zbx_sessionid'], 16, 16));
		}
		$this->addVar('form_refresh', getRequest('form_refresh', 0) + 1);
	}

	public function setMethod($value = 'post') {
		$this->attributes['method'] = $value;
		return $this;
	}

	public function setAction($value) {
		global $page;

		if (is_null($value)) {
			$value = isset($page['file']) ? $page['file'] : 'zabbix.php';
		}
		$this->attributes['action'] = $value;
		return $this;
	}

	public function setEnctype($value = null) {
		if (is_null($value)) {
			$this->removeAttribute('enctype');
		}
		else {
			$this->setAttribute('enctype', $value);
		}
		return $this;
	}

	public function addVar($name, $value, $id = null) {
		if (!is_null($value)) {
			$this->addItem(new CVar($name, $value, $id));
		}
		return $this;
	}
}
