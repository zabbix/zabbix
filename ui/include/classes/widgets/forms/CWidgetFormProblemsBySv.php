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
 * Problems by severity widget form.
 */
class CWidgetFormProblemsBySv extends CWidgetForm {

	public function __construct($data, $templateid) {
		parent::__construct($data, $templateid, WIDGET_PROBLEMS_BY_SV);

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

		// Show type.
		$field_show_type = (new CWidgetFieldRadioButtonList('show_type', _('Show'), [
			WIDGET_PROBLEMS_BY_SV_SHOW_GROUPS => _('Host groups'),
			WIDGET_PROBLEMS_BY_SV_SHOW_TOTALS => _('Totals')
		]))
			->setDefault(WIDGET_PROBLEMS_BY_SV_SHOW_GROUPS)
			->setModern(true)
			->setAction('var disabled = jQuery(this).filter("[value=\''.WIDGET_PROBLEMS_BY_SV_SHOW_GROUPS.'\']")'.
				'.is(":checked");'.
				'jQuery("#hide_empty_groups").prop("disabled", !disabled);'.
				'jQuery("#layout input").prop("disabled", disabled)'
			);

		if (array_key_exists('show_type', $this->data)) {
			$field_show_type->setValue($this->data['show_type']);
		}

		$this->fields[$field_show_type->getName()] = $field_show_type;

		// Layout.
		$field_layout = (new CWidgetFieldRadioButtonList('layout', _('Layout'), [
			STYLE_HORIZONTAL => _('Horizontal'),
			STYLE_VERTICAL => _('Vertical')
		]))
			->setDefault(STYLE_HORIZONTAL)
			->setModern(true);

		if (array_key_exists('layout', $this->data)) {
			$field_layout->setValue($this->data['layout']);
		}

		if ($field_show_type->getValue() == WIDGET_PROBLEMS_BY_SV_SHOW_GROUPS) {
			$field_layout->setFlags(CWidgetField::FLAG_DISABLED);
		}

		$this->fields[$field_layout->getName()] = $field_layout;

		// Show operational data.
		$field_show_opdata = (new CWidgetFieldRadioButtonList('show_opdata', _('Show operational data'), [
			OPERATIONAL_DATA_SHOW_NONE => _('None'),
			OPERATIONAL_DATA_SHOW_SEPARATELY => _('Separately'),
			OPERATIONAL_DATA_SHOW_WITH_PROBLEM => _('With problem name')
		]))
			->setDefault(OPERATIONAL_DATA_SHOW_NONE)
			->setModern(true);

		if (array_key_exists('show_opdata', $this->data)) {
			$field_show_opdata->setValue($this->data['show_opdata']);
		}

		$this->fields[$field_show_opdata->getName()] = $field_show_opdata;

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

		if ($field_show_type->getValue() == WIDGET_PROBLEMS_BY_SV_SHOW_TOTALS) {
			$field_hide_empty_groups->setFlags(CWidgetField::FLAG_DISABLED);
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

		// Show timeline.
		$field_show_timeline = (new CWidgetFieldCheckBox('show_timeline', _('Show timeline')))
			->setDefault(1);

		if (array_key_exists('show_timeline', $this->data)) {
			$field_show_timeline->setValue($this->data['show_timeline']);
		}

		$this->fields[$field_show_timeline->getName()] = $field_show_timeline;
	}
}
