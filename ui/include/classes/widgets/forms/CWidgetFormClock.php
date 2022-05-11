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
 * Clock widget form.
 */
class CWidgetFormClock extends CWidgetForm {

	public function __construct($data, $templateid) {
		parent::__construct($data, $templateid, WIDGET_CLOCK);

		// Time type field.
		$field_time_type = (new CWidgetFieldSelect('time_type', _('Time type'), [
			TIME_TYPE_LOCAL => _('Local time'),
			TIME_TYPE_SERVER => _('Server time'),
			TIME_TYPE_HOST => _('Host time')
		]))
			->setDefault(TIME_TYPE_LOCAL);

		if (array_key_exists('time_type', $this->data)) {
			$field_time_type->setValue($this->data['time_type']);
		}

		$this->fields[$field_time_type->getName()] = $field_time_type;

		// Item field.
		if ($field_time_type->getValue() === TIME_TYPE_HOST) {
			// Item multiselector with single value.
			$field_item = (new CWidgetFieldMsItem('itemid', _('Item'), $templateid))
				->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
				->setMultiple(false);

			if (array_key_exists('itemid', $this->data)) {
				$field_item->setValue($this->data['itemid']);
			}

			$this->fields[$field_item->getName()] = $field_item;
		}
	}
}
