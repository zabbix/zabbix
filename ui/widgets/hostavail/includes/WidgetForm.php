<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


namespace Widgets\HostAvail\Includes;

use Zabbix\Widgets\CWidgetForm;

use Zabbix\Widgets\Fields\{
	CWidgetFieldCheckBox,
	CWidgetFieldCheckBoxList,
	CWidgetFieldMultiSelectGroup,
	CWidgetFieldRadioButtonList
};

/**
 * Host availability widget form.
 */
class WidgetForm extends CWidgetForm {

	public function addFields(): self {
		return $this
			->addField(
				new CWidgetFieldMultiSelectGroup('groupids', _('Host groups'))
			)
			->addField(
				new CWidgetFieldCheckBoxList('interface_type', _('Interface type'), [
					INTERFACE_TYPE_AGENT => _('Zabbix agent'),
					INTERFACE_TYPE_SNMP => _('SNMP'),
					INTERFACE_TYPE_JMX => _('JMX'),
					INTERFACE_TYPE_IPMI => _('IPMI')
				])
			)
			->addField(
				(new CWidgetFieldRadioButtonList('layout', _('Layout'), [
					STYLE_HORIZONTAL => _('Horizontal'),
					STYLE_VERTICAL => _('Vertical')
				]))->setDefault(STYLE_HORIZONTAL)
			)
			->addField(
				new CWidgetFieldCheckBox('maintenance', _('Show hosts in maintenance'))
			);
	}
}
