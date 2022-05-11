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
 * Host availability widget form.
 */
class CWidgetFormHostAvail extends CWidgetForm {

	public function __construct($data, $templateid) {
		parent::__construct($data, $templateid, WIDGET_HOST_AVAIL);

		// Host groups.
		$field_groups = new CWidgetFieldMsGroup('groupids', _('Host groups'));

		if (array_key_exists('groupids', $this->data)) {
			$field_groups->setValue($this->data['groupids']);
		}
		$this->fields[$field_groups->getName()] = $field_groups;

		// Interface type.
		$field_interface_type = new CWidgetFieldCheckBoxList('interface_type', _('Interface type'));

		if (array_key_exists('interface_type', $this->data)) {
			$field_interface_type->setValue($this->data['interface_type']);
		}

		$this->fields[$field_interface_type->getName()] = $field_interface_type;

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

		$this->fields[$field_layout->getName()] = $field_layout;

		// Show hosts in maintenance.
		$field_maintenance = (new CWidgetFieldCheckBox('maintenance', _('Show hosts in maintenance')))
			->setDefault(HOST_MAINTENANCE_STATUS_OFF);

		if (array_key_exists('maintenance', $this->data)) {
			$field_maintenance->setValue($this->data['maintenance']);
		}

		$this->fields[$field_maintenance->getName()] = $field_maintenance;
	}
}
