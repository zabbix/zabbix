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

class CClockWidgetForm extends CWidgetForm
{
	public function __construct($data) {
		parent::__construct($data);

		// Time Type field
		$time_types = [
			TIME_TYPE_LOCAL => _('Local time'),
			TIME_TYPE_SERVER => _('Server time'),
			TIME_TYPE_HOST => _('Host time')
		];
		$field_time_type =
			(new CWidgetFieldComboBox(
				'time_type',
				_('Time type'),
				$time_types,
				TIME_TYPE_LOCAL,
				'updateWidgetConfigDialogue()',
				ZBX_WIDGET_FIELD_TYPE_INT32
			));
		$field_time_type->setRequired(true);
		if (array_key_exists('time_type', $data)) {
			$field_time_type->setValue($data['time_type']);
		}
		$this->fields[] = $field_time_type;

		// Item field
		if ($field_time_type->getValue() === TIME_TYPE_HOST) {
			$field_itemid = (new CWidgetFieldItemId('itemid', _('Item')));
			$field_itemid->setRequired(true);
			if (array_key_exists('itemid', $data)) {
				$field_itemid->setValue($data['itemid']);
			}
			if (array_key_exists('caption', $data)) {
				$field_itemid->setCaption($data['caption']);
			}
			$this->fields[] = $field_itemid;
		}
	}
}
