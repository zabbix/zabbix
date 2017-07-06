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
 * Action log widget form
 */
class CActionLogWidgetForm extends CWidgetForm {

	public function __construct($data)
	{
		parent::__construct($data);

		$sort_types = [
			SCREEN_SORT_TRIGGERS_TIME_DESC => _('Time (descending)'),
			SCREEN_SORT_TRIGGERS_TIME_ASC => _('Time (ascending)'),
			SCREEN_SORT_TRIGGERS_TYPE_DESC => _('Type (descending)'),
			SCREEN_SORT_TRIGGERS_TYPE_ASC => _('Type (ascending)'),
			SCREEN_SORT_TRIGGERS_STATUS_DESC => _('Status (descending)'),
			SCREEN_SORT_TRIGGERS_STATUS_ASC => _('Status (ascending)'),
			SCREEN_SORT_TRIGGERS_RECIPIENT_DESC => _('Recipient (descending)'),
			SCREEN_SORT_TRIGGERS_RECIPIENT_ASC => _('Recipient (ascending)')
		];

		$field_sort = (new CWidgetFieldComboBox('sort_triggers', _('Sort entries by'), $sort_types,
			ZBX_WIDGET_FIELD_TYPE_INT32
		))
			->setDefault(SCREEN_SORT_TRIGGERS_TIME_DESC);

		if (array_key_exists('sort_triggers', $data)) {
			$field_sort->setValue($data['sort_triggers']);
		}
		$this->fields[] = $field_sort;

		$field_lines = (new CWidgetFieldNumericBox('show_lines', _('Show lines'), 1, 100))->setDefault(25);
		if (array_key_exists('show_lines', $data)) {
			$field_lines->setValue($data['show_lines']);
		}
		$this->fields[] = $field_lines;
	}
}
