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
 * Problems widget form.
 */
class CWidgetFormProblems extends CWidgetForm {

	public function __construct($data, $templateid) {
		parent::__construct($data, $templateid, WIDGET_PROBLEMS);

		$this->data = self::convertDottedKeys($this->data);

		// Output information option.
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

		$this->fields[$field_show->getName()] = $field_show;

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

		// Show tags.
		$field_show_tags = (new CWidgetFieldRadioButtonList('show_tags', _('Show tags'), [
			SHOW_TAGS_NONE => _('None'),
			SHOW_TAGS_1 => SHOW_TAGS_1,
			SHOW_TAGS_2 => SHOW_TAGS_2,
			SHOW_TAGS_3 => SHOW_TAGS_3
		]))
			->setDefault(SHOW_TAGS_NONE)
			->setModern(true)
			->setAction('var disabled = jQuery(this).filter("[value=\''.SHOW_TAGS_NONE.'\']").is(":checked");'.
				'jQuery("#tag_priority").prop("disabled", disabled);'.
				'jQuery("#tag_name_format input").prop("disabled", disabled)'
			);

		if (array_key_exists('show_tags', $this->data)) {
			$field_show_tags->setValue($this->data['show_tags']);
		}

		$this->fields[$field_show_tags->getName()] = $field_show_tags;

		// Tag name.
		$tag_format_line = (new CWidgetFieldRadioButtonList('tag_name_format', _('Tag name'), [
			TAG_NAME_FULL => _('Full'),
			TAG_NAME_SHORTENED => _('Shortened'),
			TAG_NAME_NONE => _('None')
		]))
			->setDefault(TAG_NAME_FULL)
			->setModern(true);

		if (array_key_exists('tag_name_format', $this->data)) {
			$tag_format_line->setValue($this->data['tag_name_format']);
		}
		$this->fields[$tag_format_line->getName()] = $tag_format_line;

		// Tag display priority.
		$tag_priority = (new CWidgetFieldTextBox('tag_priority', _('Tag display priority')));

		if (array_key_exists('tag_priority', $this->data)) {
			$tag_priority->setValue($this->data['tag_priority']);
		}
		$this->fields[$tag_priority->getName()] = $tag_priority;

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

		// Show unacknowledged only.
		$field_unacknowledged = (new CWidgetFieldCheckBox('unacknowledged', _('Show unacknowledged only')))
			->setFlags(CWidgetField::FLAG_ACKNOWLEDGES);

		if (array_key_exists('unacknowledged', $this->data)) {
			$field_unacknowledged->setValue($this->data['unacknowledged']);
		}

		$this->fields[$field_unacknowledged->getName()] = $field_unacknowledged;

		$sort_with_enabled_show_timeline = [
			SCREEN_SORT_TRIGGERS_TIME_DESC => true,
			SCREEN_SORT_TRIGGERS_TIME_ASC => true
		];

		// Sort entries by.
		$field_sort = (new CWidgetFieldSelect('sort_triggers', _('Sort entries by'), [
			SCREEN_SORT_TRIGGERS_TIME_DESC => _('Time').' ('._('descending').')',
			SCREEN_SORT_TRIGGERS_TIME_ASC => _('Time').' ('._('ascending').')',
			SCREEN_SORT_TRIGGERS_SEVERITY_DESC => _('Severity').' ('._('descending').')',
			SCREEN_SORT_TRIGGERS_SEVERITY_ASC => _('Severity').' ('._('ascending').')',
			SCREEN_SORT_TRIGGERS_NAME_DESC => _('Problem').' ('._('descending').')',
			SCREEN_SORT_TRIGGERS_NAME_ASC => _('Problem').' ('._('ascending').')',
			SCREEN_SORT_TRIGGERS_HOST_NAME_DESC => _('Host').' ('._('descending').')',
			SCREEN_SORT_TRIGGERS_HOST_NAME_ASC => _('Host').' ('._('ascending').')'
		]))
			->setDefault(SCREEN_SORT_TRIGGERS_TIME_DESC);

		if (array_key_exists('sort_triggers', $this->data)) {
			$field_sort->setValue($this->data['sort_triggers']);
		}

		$this->fields[$field_sort->getName()] = $field_sort;

		// Show timeline.
		$field_show_timeline = (new CWidgetFieldCheckBox('show_timeline', _('Show timeline')))->setDefault(1);

		if (array_key_exists('show_timeline', $this->data)) {
			$field_show_timeline->setValue($this->data['show_timeline']);
		}

		if (!array_key_exists($field_sort->getValue(), $sort_with_enabled_show_timeline)) {
			$field_show_timeline->setFlags(CWidgetField::FLAG_DISABLED);
		}

		$this->fields[$field_show_timeline->getName()] = $field_show_timeline;

		// Show lines.
		$field_lines = (new CWidgetFieldIntegerBox('show_lines', _('Show lines'), ZBX_MIN_WIDGET_LINES,
			ZBX_MAX_WIDGET_LINES
		))
			->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
			->setDefault(ZBX_DEFAULT_WIDGET_LINES);

		if (array_key_exists('show_lines', $this->data)) {
			$field_lines->setValue($this->data['show_lines']);
		}

		$this->fields[$field_lines->getName()] = $field_lines;
	}
}
