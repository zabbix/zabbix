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
 * Latest problems widget form
 */
class CProblemsWidgetForm extends CWidgetForm
{
	public function __construct($data)
	{
		parent::__construct($data);

		$shows = [
			TRIGGERS_OPTION_RECENT_PROBLEM => _('Recent problems'),
			TRIGGERS_OPTION_IN_PROBLEM => _('Problems'),
			TRIGGERS_OPTION_ALL => _('History')
		];

		$field_show = (new CWidgetFieldRadioButtonList('show', _('Show'), $shows, TRIGGERS_OPTION_IN_PROBLEM))
			->setModern(true);

		if (array_key_exists('show', $data)) {
			$field_show->setValue($data['show']);
		}
		$this->fields[] = $field_show;

		$field_groups = new CWidgetFieldGroup('groupids', _('Host groups'), []);

		if (array_key_exists('groupids', $data)) {
			$field_groups->setValue($data['groupids']);
		}
		$this->fields[] = $field_groups;

		$field_hosts = new CWidgetFieldHost('hostids', _('Hosts'), []);

		if (array_key_exists('hostids', $data)) {
			$field_hosts->setValue($data['hostids']);
		}
		$this->fields[] = $field_hosts;

		$sort_types = [
			SCREEN_SORT_TRIGGERS_TIME_DESC => _('Time').' ('._('descending').')',
			SCREEN_SORT_TRIGGERS_TIME_ASC => _('Time').' ('._('ascending').')',
			SCREEN_SORT_TRIGGERS_SEVERITY_DESC => _('Severity').' ('._('descending').')',
			SCREEN_SORT_TRIGGERS_SEVERITY_ASC => _('Severity').' ('._('ascending').')',
			SCREEN_SORT_TRIGGERS_NAME_DESC => _('Problem').' ('._('descending').')',
			SCREEN_SORT_TRIGGERS_NAME_ASC => _('Problem').' ('._('ascending').')',
			SCREEN_SORT_TRIGGERS_HOST_NAME_DESC => _('Host').' ('._('descending').')',
			SCREEN_SORT_TRIGGERS_HOST_NAME_ASC => _('Host').' ('._('ascending').')'
		];

		$field_sort = new CWidgetFieldComboBox('sort_triggers', _('Sort entries by'), $sort_types,
			SCREEN_SORT_TRIGGERS_TIME_DESC, null, ZBX_WIDGET_FIELD_TYPE_INT32
		);

		if (array_key_exists('sort_triggers', $data)) {
			$field_sort->setValue($data['sort_triggers']);
		}
		$this->fields[] = $field_sort;

		$field_lines = (new CWidgetFieldNumericBox('show_lines', _('Show lines'), ZBX_DEFAULT_WIDGET_LINES,
			ZBX_MIN_WIDGET_LINES, ZBX_MAX_WIDGET_LINES
		))
			->setRequired(true);

		if (array_key_exists('show_lines', $data)) {
			$field_lines->setValue($data['show_lines']);
		}
		$this->fields[] = $field_lines;
	}
}
