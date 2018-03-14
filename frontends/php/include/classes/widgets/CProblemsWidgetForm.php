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
 * Problems widget form.
 */
class CProblemsWidgetForm extends CWidgetForm {

	public function __construct($data) {
		parent::__construct($data, WIDGET_PROBLEMS);

		$this->data = self::convertDottedKeys($this->data);

		// show
		$field_show = (new CWidgetFieldRadioButtonList('show', _('Show'), [
			TRIGGERS_OPTION_RECENT_PROBLEM => _('Recent problems'),
			TRIGGERS_OPTION_IN_PROBLEM => _('Problems'),
			TRIGGERS_OPTION_ALL => _('History')
		]))
			->setDefault(TRIGGERS_OPTION_RECENT_PROBLEM)
			->setModern(true);

		if (array_key_exists('show', $this->data)) {
			$field_show->setValue($this->data['show']);
		}
		$this->fields[] = $field_show;

		// host groups
		$field_groups = new CWidgetFieldGroup('groupids', _('Host groups'));

		if (array_key_exists('groupids', $this->data)) {
			$field_groups->setValue($this->data['groupids']);
		}
		$this->fields[] = $field_groups;

		// exclude host groups
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

		// Tag evalype (AND/OR).
		$field_evaltype = (new CWidgetFieldRadioButtonList('evaltype', _('Tags'), [
			TAG_EVAL_TYPE_AND => _('AND'),
			TAG_EVAL_TYPE_OR => _('OR')
		]))
			->setDefault(TAG_EVAL_TYPE_AND)
			->setModern(true);

		if (array_key_exists('evaltype', $this->data)) {
			$field_evaltype->setValue($this->data['evaltype']);
		}

		$this->fields[] = $field_evaltype;

		// Tags array: tag, operator and value. No label, because it belongs to previous group.
		$field_tags = new CWidgetFieldTags('tags', '');

		if (array_key_exists('tags', $this->data)) {
			$field_tags->setValue($this->data['tags']);
		}
		$this->fields[] = $field_tags;

		// show hosts in maintenance
		$field_maintenance = (new CWidgetFieldCheckBox('maintenance', _('Show hosts in maintenance')))->setDefault(1);

		if (array_key_exists('maintenance', $this->data)) {
			$field_maintenance->setValue($this->data['maintenance']);
		}
		$this->fields[] = $field_maintenance;

		// show unacknowledged only
		$field_unacknowledged = (new CWidgetFieldCheckBox('unacknowledged', _('Show unacknowledged only')))
			->setFlags(CWidgetField::FLAG_ACKNOWLEDGES);

		if (array_key_exists('unacknowledged', $this->data)) {
			$field_unacknowledged->setValue($this->data['unacknowledged']);
		}
		$this->fields[] = $field_unacknowledged;

		// show tags
		$field_show_tags = (new CWidgetFieldRadioButtonList('show_tags', _('Show tags'), [
			PROBLEMS_SHOW_TAGS_NONE => _('None'),
			PROBLEMS_SHOW_TAGS_1 => PROBLEMS_SHOW_TAGS_1,
			PROBLEMS_SHOW_TAGS_2 => PROBLEMS_SHOW_TAGS_2,
			PROBLEMS_SHOW_TAGS_3 => PROBLEMS_SHOW_TAGS_3
		]))
			->setDefault(PROBLEMS_SHOW_TAGS_NONE)
			->setModern(true);

		if (array_key_exists('show_tags', $this->data)) {
			$field_show_tags->setValue($this->data['show_tags']);
		}
		$this->fields[] = $field_show_tags;

		// sort entries by
		$field_sort = (new CWidgetFieldComboBox('sort_triggers', _('Sort entries by'), [
			SCREEN_SORT_TRIGGERS_TIME_DESC => _('Time').' ('._('descending').')',
			SCREEN_SORT_TRIGGERS_TIME_ASC => _('Time').' ('._('ascending').')',
			SCREEN_SORT_TRIGGERS_SEVERITY_DESC => _('Severity').' ('._('descending').')',
			SCREEN_SORT_TRIGGERS_SEVERITY_ASC => _('Severity').' ('._('ascending').')',
			SCREEN_SORT_TRIGGERS_NAME_DESC => _('Problem').' ('._('descending').')',
			SCREEN_SORT_TRIGGERS_NAME_ASC => _('Problem').' ('._('ascending').')',
			SCREEN_SORT_TRIGGERS_HOST_NAME_DESC => _('Host').' ('._('descending').')',
			SCREEN_SORT_TRIGGERS_HOST_NAME_ASC => _('Host').' ('._('ascending').')'
		]))->setDefault(SCREEN_SORT_TRIGGERS_TIME_DESC);

		if (array_key_exists('sort_triggers', $this->data)) {
			$field_sort->setValue($this->data['sort_triggers']);
		}
		$this->fields[] = $field_sort;

		// show lines
		$field_lines = (new CWidgetFieldNumericBox('show_lines', _('Show lines'), ZBX_MIN_WIDGET_LINES,
			ZBX_MAX_WIDGET_LINES
		))
			->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
			->setDefault(ZBX_DEFAULT_WIDGET_LINES);

		if (array_key_exists('show_lines', $this->data)) {
			$field_lines->setValue($this->data['show_lines']);
		}
		$this->fields[] = $field_lines;
	}
}
