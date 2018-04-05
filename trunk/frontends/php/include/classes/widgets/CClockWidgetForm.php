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
 * Clock widget form.
 */
class CClockWidgetForm extends CWidgetForm {

	public function __construct($data) {
		parent::__construct($data, WIDGET_CLOCK);

		// Time type field
		$field_time_type = (new CWidgetFieldComboBox('time_type', _('Time type'), [
			TIME_TYPE_LOCAL => _('Local time'),
			TIME_TYPE_SERVER => _('Server time'),
			TIME_TYPE_HOST => _('Host time')
		]))
			->setDefault(TIME_TYPE_LOCAL)
			->setAction('updateWidgetConfigDialogue()');

		if (array_key_exists('time_type', $this->data)) {
			$field_time_type->setValue($this->data['time_type']);
		}
		$this->fields[] = $field_time_type;

		// Item field
		if ($field_time_type->getValue() === TIME_TYPE_HOST) {
			$field_item = (new CWidgetFieldSelectResource('itemid', _('Item'), WIDGET_FIELD_SELECT_RES_ITEM))
				->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK);

			if (array_key_exists('itemid', $this->data)) {
				$field_item->setValue($this->data['itemid']);
			}
			$this->fields[] = $field_item;
		}
	}
}
