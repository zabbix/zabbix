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
 * Action log widget form.
 */
class CWidgetFormActionLog extends CWidgetForm {

	public function __construct($data, $templateid) {
		parent::__construct($data, $templateid, WIDGET_ACTION_LOG);

		$field_sort = (new CWidgetFieldSelect('sort_triggers', _('Sort entries by'), [
			SCREEN_SORT_TRIGGERS_TIME_DESC => _('Time').' ('._('descending').')',
			SCREEN_SORT_TRIGGERS_TIME_ASC => _('Time').' ('._('ascending').')',
			SCREEN_SORT_TRIGGERS_TYPE_DESC => _('Type').' ('._('descending').')',
			SCREEN_SORT_TRIGGERS_TYPE_ASC => _('Type').' ('._('ascending').')',
			SCREEN_SORT_TRIGGERS_STATUS_DESC => _('Status').' ('._('descending').')',
			SCREEN_SORT_TRIGGERS_STATUS_ASC => _('Status').' ('._('ascending').')',
			SCREEN_SORT_TRIGGERS_RECIPIENT_DESC => _('Recipient').' ('._('descending').')',
			SCREEN_SORT_TRIGGERS_RECIPIENT_ASC => _('Recipient').' ('._('ascending').')'
		]))
			->setDefault(SCREEN_SORT_TRIGGERS_TIME_DESC);

		if (array_key_exists('sort_triggers', $this->data)) {
			$field_sort->setValue($this->data['sort_triggers']);
		}

		$this->fields[$field_sort->getName()] = $field_sort;

		$field_lines = (new CWidgetFieldIntegerBox('show_lines', _('Show lines'), ZBX_MIN_WIDGET_LINES,
			ZBX_MAX_WIDGET_LINES
		))
			->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
			->setDefault(25);

		if (array_key_exists('show_lines', $this->data)) {
			$field_lines->setValue($this->data['show_lines']);
		}

		$this->fields[$field_lines->getName()] = $field_lines;
	}
}
