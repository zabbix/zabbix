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
 * Problem hosts widget form.
 */
class CWidgetFormProblemHosts extends CWidgetForm {

	public function __construct($data, $templateid) {
		parent::__construct($data, $templateid, WIDGET_PROBLEM_HOSTS);

		$this->data = self::convertDottedKeys($this->data);

		// Host groups.
		$field_groups = new CWidgetFieldMsGroup('groupids', _('Host groups'));

		if (array_key_exists('groupids', $this->data)) {
			$field_groups->setValue($this->data['groupids']);
		}

		$this->fields[$field_groups->getName()] = $field_groups;

		// Exclude host groups.
		$field_exclude_groups = new CWidgetFieldMsGroup('exclude_groupids', _('Exclude host groups'));

		if (array_key_exists('exclude_groupids', $this->data)) {
			$field_exclude_groups->setValue($this->data['exclude_groupids']);
		}

		$this->fields[$field_exclude_groups->getName()] = $field_exclude_groups;

		// Hosts field.
		$field_hosts = new CWidgetFieldMsHost('hostids', _('Hosts'));
		$field_hosts->filter_preselect_host_group_field = 'groupids_';

		if (array_key_exists('hostids', $this->data)) {
			$field_hosts->setValue($this->data['hostids']);
		}

		$this->fields[$field_hosts->getName()] = $field_hosts;

		// Problem field.
		$field_problem = new CWidgetFieldTextBox('problem', _('Problem'));

		if (array_key_exists('problem', $this->data)) {
			$field_problem->setValue($this->data['problem']);
		}

		$this->fields[$field_problem->getName()] = $field_problem;

		// Severity field.
		$field_severities = new CWidgetFieldSeverities('severities', _('Severity'));

		if (array_key_exists('severities', $this->data)) {
			$field_severities->setValue($this->data['severities']);
		}

		$this->fields[$field_severities->getName()] = $field_severities;

		// Tag evaltype (And/Or).
		$field_evaltype = (new CWidgetFieldRadioButtonList('evaltype', _('Tags'), [
			TAG_EVAL_TYPE_AND_OR => _('And/Or'),
			TAG_EVAL_TYPE_OR => _('Or')
		]))
			->setDefault(TAG_EVAL_TYPE_AND_OR)
			->setModern(true);

		if (array_key_exists('evaltype', $this->data)) {
			$field_evaltype->setValue($this->data['evaltype']);
		}

		$this->fields[$field_evaltype->getName()] = $field_evaltype;

		// Tags array: tag, operator and value. No label, because it belongs to previous group.
		$field_tags = new CWidgetFieldTags('tags', '');

		if (array_key_exists('tags', $this->data)) {
			$field_tags->setValue($this->data['tags']);
		}

		$this->fields[$field_tags->getName()] = $field_tags;

		// Show suppressed problems.
		$field_show_suppressed = (new CWidgetFieldCheckBox('show_suppressed', _('Show suppressed problems')))
			->setDefault(ZBX_PROBLEM_SUPPRESSED_FALSE);

		if (array_key_exists('show_suppressed', $this->data)) {
			$field_show_suppressed->setValue($this->data['show_suppressed']);
		}

		$this->fields[$field_show_suppressed->getName()] = $field_show_suppressed;

		// Hide groups without problems.
		$field_hide_empty_groups = new CWidgetFieldCheckBox('hide_empty_groups', _('Hide groups without problems'));

		if (array_key_exists('hide_empty_groups', $this->data)) {
			$field_hide_empty_groups->setValue($this->data['hide_empty_groups']);
		}

		$this->fields[$field_hide_empty_groups->getName()] = $field_hide_empty_groups;

		// Problem display.
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

		$this->fields[$field_ext_ack->getName()] = $field_ext_ack;
	}
}
