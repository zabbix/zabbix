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


class CColorCell extends CDiv {

	public function __construct($name, $value, $action = null) {
		parent::__construct(SPACE.SPACE.SPACE, 'pointer');
		$this->setName($name);
		$this->setAttribute('id', zbx_formatDomId($name));
		$this->setAttribute('title', '#'.$value);
		$this->setAttribute('style', 'display: inline; width: 10px; height: 10px; text-decoration: none; border: 1px solid black; background-color: #'.$value);
		if ($action !== null) {
			$this->setAttribute('onclick', $action);
		}
	}
}
