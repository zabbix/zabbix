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


/**
 * Web widget form.
 */
class CWidgetFormWeb extends CWidgetForm {

	public function __construct($data) {
		parent::__construct($data, WIDGET_WEB_OVERVIEW);

		// host groups
		$field_groups = new CWidgetFieldGroup('groupids', _('Host groups'));

		if (array_key_exists('groupids', $this->data)) {
			$field_groups->setValue($this->data['groupids']);
		}
		$this->fields[] = $field_groups;

		// Exclude host groups.
		$field_exclude_groups = new CWidgetFieldGroup('exclude_groupids', _('Exclude host groups'));

		if (array_key_exists('exclude_groupids', $this->data)) {
			$field_exclude_groups->setValue($this->data['exclude_groupids']);
		}
		$this->fields[] = $field_exclude_groups;

		// hosts
		$field_hosts = new CWidgetFieldHost('hostids', _('Hosts'));

		if (array_key_exists('hostids', $this->data)) {
			$field_hosts->setValue($this->data['hostids']);
		}
		$this->fields[] = $field_hosts;

		// Show hosts in maintenance.
		$field_maintenance = (new CWidgetFieldCheckBox('maintenance', _('Show hosts in maintenance')))
			->setDefault(1);

		if (array_key_exists('maintenance', $this->data)) {
			$field_maintenance->setValue($this->data['maintenance']);
		}
		$this->fields[] = $field_maintenance;
	}
}
