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

class CWidgetFieldRadioButtonList extends CWidgetField
{
	private $values = [];
	private $modern = false;
	
	public function __construct($name, $label, $default = '', $action = null, $dataFieldType = ZBX_WIDGET_FIELD_TYPE_INT32) {
		parent::__construct($name, $label, $default, $action);
		$this->setSaveType($dataFieldType);
	}

	public function addValue($name, $value, $id = null, $on_change = null) {
		$this->javascript = '';
		$this->values[] = [
			'name' => $name,
			'value' => $value,
			'id' => ($id === null ? null : zbx_formatDomId($id)),
			'on_change' => $on_change
		];

		return $this;
	}

	public function setModern($modern) {
		$this->modern = $modern;

		return $this;
	}

	public function getModern() {
		return $this->modern;
	}

	public function getValues() {
		return $this->values;
	}
}
