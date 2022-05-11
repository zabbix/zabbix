<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


/**
 * System information widget form.
 */
class CWidgetFormSystemInfo extends CWidgetForm {

	public function __construct($data, $templateid) {
		parent::__construct($data, $templateid, WIDGET_SYSTEM_INFO);

		$field_info_type = (new CWidgetFieldRadioButtonList('info_type', _('Show'), [
			ZBX_SYSTEM_INFO_SERVER_STATS => _('System stats'),
			ZBX_SYSTEM_INFO_HAC_STATUS => _('High availability nodes')
		]))
			->setDefault(ZBX_SYSTEM_INFO_SERVER_STATS)
			->setModern(true);

		if (array_key_exists('info_type', $this->data)) {
			$field_info_type->setValue($this->data['info_type']);
		}

		$this->fields[$field_info_type->getName()] = $field_info_type;
	}
}
