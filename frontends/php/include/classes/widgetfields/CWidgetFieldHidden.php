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

class CWidgetFieldHidden extends CWidgetField
{
	public function __construct($name, $value = '', $save_type = ZBX_WIDGET_FIELD_TYPE_STR) {
		parent::__construct($name);
		$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_STR);
		$this->setValue($value);

		switch ($save_type) {
			case ZBX_WIDGET_FIELD_TYPE_STR:
				$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_STR);
				break;
			case ZBX_WIDGET_FIELD_TYPE_INT32:
				$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_INT32);
				break;
			case ZBX_WIDGET_FIELD_TYPE_MAP:
				$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_MAP);
				break;
			default:
				break;
		}
	}
}
