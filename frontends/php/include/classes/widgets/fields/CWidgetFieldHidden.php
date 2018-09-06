<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


class CWidgetFieldHidden extends CWidgetField {

	/**
	 * Hidden widget field. Will not be displayed for user. Can contain string, int or id type value.
	 *
	 * @param string $name       field name in form
	 * @param int    $save_type  ZBX_WIDGET_FIELD_TYPE_ constant. Defines how field will be saved in database.
	 */
	public function __construct($name, $save_type) {
		parent::__construct($name, null);

		$this->setSaveType($save_type);
	}
}
