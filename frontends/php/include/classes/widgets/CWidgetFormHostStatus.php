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
 * Host status widget form.
 */
class CWidgetFormHostStatus extends CWidgetForm {

	public function __construct($data) {
		parent::__construct($data, WIDGET_HOST_STATUS);

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

		// problem
		$field_problem = new CWidgetFieldTextBox('problem', _('Problem'));

		if (array_key_exists('problem', $this->data)) {
			$field_problem->setValue($this->data['problem']);
		}
		$this->fields[] = $field_problem;

		// severity
		$field_severities = new CWidgetFieldSeverities('severities', _('Severity'));

		if (array_key_exists('severities', $this->data)) {
			$field_severities->setValue($this->data['severities']);
		}
		$this->fields[] = $field_severities;

		// Show hosts in maintenance.
		$field_maintenance = (new CWidgetFieldCheckBox('maintenance', _('Show hosts in maintenance')))->setDefault(1);

		if (array_key_exists('maintenance', $this->data)) {
			$field_maintenance->setValue($this->data['maintenance']);
		}
		$this->fields[] = $field_maintenance;

		// Hide groups without problems.
		$field_hide_empty_groups = new CWidgetFieldCheckBox('hide_empty_groups', _('Hide groups without problems'));

		if (array_key_exists('hide_empty_groups', $this->data)) {
			$field_hide_empty_groups->setValue($this->data['hide_empty_groups']);
		}
		$this->fields[] = $field_hide_empty_groups;

		// problem display
		$field_ext_ack = (new CWidgetFieldRadioButtonList('ext_ack', _('Problem display'), [
			EXTACK_OPTION_ALL => _('All'),
			EXTACK_OPTION_BOTH => _('Separated'),
			EXTACK_OPTION_UNACK => _('Unacknowledged only')
		]))
			->setDefault(EXTACK_OPTION_ALL)
			->setFlags(CWidgetField::FLAG_ACKNOWLEDGES)
			->setModern(true);

		if (array_key_exists('ext_ack', $this->data)) {
			$field_ext_ack->setValue($this->data['ext_ack']);
		}
		$this->fields[] = $field_ext_ack;
	}
}
