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


class CForm extends CTag {

	public function __construct($method = 'post', $action = null, $enctype = null) {
		parent::__construct('form', 'yes');
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
		return $this->attributes['method'] = $value;
	}

	public function setAction($value) {
		global $page;

		if (is_null($value)) {
			$value = isset($page['file']) ? $page['file'] : '#';
		}
		return $this->attributes['action'] = $value;
	}

	public function setEnctype($value = null) {
		if (is_null($value)) {
			return $this->removeAttribute('enctype');
		}
		elseif (!is_string($value)) {
			return $this->error('Incorrect value for SetEnctype "'.$value.'".');
		}
		return $this->setAttribute('enctype', $value);
	}

	public function addVar($name, $value, $id = null) {
		if (!is_null($value)) {
			$this->addItem(new CVar($name, $value, $id));
		}
	}
}
