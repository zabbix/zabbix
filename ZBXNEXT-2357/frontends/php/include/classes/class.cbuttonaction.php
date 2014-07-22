<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


class CButtonAction extends CTag {

	public function __construct($action, $label, $id = null, $class = null) {
		parent::__construct('button', 'yes', $label, $class);
		$this->attr('name', 'action');
		$this->attr('type', 'submit');
		$this->attr('value', $action);
	}

	public function useJQueryStyle($class = '') {
		$this->attr('class', 'jqueryinput '.$this->getAttribute('class').' '.$class);
	}
}
