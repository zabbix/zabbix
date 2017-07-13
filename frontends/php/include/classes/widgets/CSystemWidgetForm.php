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


/**
 * System widget form
 */
class CSystemWidgetForm extends CWidgetForm {

	public function __construct($data) {
		parent::__construct($data);

		// Host groups
		$field_groups = new CWidgetFieldGroup('groupids', _('Host groups'));

		if (array_key_exists('groupids', $data)) {
			$field_groups->setValue($data['groupids']);
		}
		$this->fields[] = $field_groups;

		// Exclude host groups
		$field_exclude_groups = new CWidgetFieldGroup('exclude_groupids', _('Exclude host groups'));

		if (array_key_exists('exclude_groupids', $data)) {
			$field_exclude_groups->setValue($data['exclude_groupids']);
		}
		$this->fields[] = $field_exclude_groups;

		// Hosts
		$field_hosts = new CWidgetFieldHost('hostids', _('Hosts'));

		if (array_key_exists('hostids', $data)) {
			$field_hosts->setValue($data['hostids']);
		}
		$this->fields[] = $field_hosts;

		// Problem
		$field_problem = new CWidgetFieldTextBox('problem', _('Problem'));

		if (array_key_exists('problem', $data)) {
			$field_problem->setValue($data['problem']);
		}
		$this->fields[] = $field_problem;

		// Severity
		$field_severities = new CWidgetFieldSeverities('severities', _('Severity'));

		if (array_key_exists('severities', $data)) {
			$field_severities->setValue($data['severities']);
		}
		$this->fields[] = $field_severities;

		// Show hosts in maintenance
		$field_maintenance = (new CWidgetFieldCheckBox('maintenance', _('Show hosts in maintenance')))
			->setDefault(1);

		if (array_key_exists('maintenance', $data)) {
			$field_maintenance->setValue($data['maintenance']);
		}
		$this->fields[] = $field_maintenance;

		// Problem display
		$field_ext_ack = (new CWidgetFieldRadioButtonList('ext_ack', _('Problem display'), [
			EXTACK_OPTION_ALL => _('All'),
			EXTACK_OPTION_BOTH => _('Separated'),
			EXTACK_OPTION_UNACK => _('Unacknowledged only')
		]))
			->setDefault(EXTACK_OPTION_ALL)
			->setModern(true);

		if (array_key_exists('ext_ack', $data)) {
			$field_ext_ack->setValue($data['ext_ack']);
		}
		$this->fields[] = $field_ext_ack;
	}
}
