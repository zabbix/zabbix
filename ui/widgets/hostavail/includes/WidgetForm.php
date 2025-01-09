<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


namespace Widgets\HostAvail\Includes;

use Zabbix\Widgets\CWidgetForm;

use Zabbix\Widgets\Fields\{
	CWidgetFieldCheckBox,
	CWidgetFieldCheckBoxList,
	CWidgetFieldMultiSelectGroup,
	CWidgetFieldMultiSelectOverrideHost,
	CWidgetFieldRadioButtonList
};

/**
 * Host availability widget form.
 */
class WidgetForm extends CWidgetForm {

	public function addFields(): self {
		return $this
			->addField($this->isTemplateDashboard()
				? null
				: new CWidgetFieldMultiSelectGroup('groupids', _('Host groups'))
			)
			->addField(
				new CWidgetFieldCheckBoxList('interface_type', _('Interface type'), [
					INTERFACE_TYPE_AGENT_ACTIVE => _('Zabbix agent (active checks)'),
					INTERFACE_TYPE_AGENT => _('Zabbix agent (passive checks)'),
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
				new CWidgetFieldCheckBox('maintenance',
					$this->isTemplateDashboard() ? _('Show data in maintenance') : _('Include hosts in maintenance')
				)
			)
			->addField(
				new CWidgetFieldCheckBox('only_totals', _('Show only totals'))
			)
			->addField(
				new CWidgetFieldMultiSelectOverrideHost()
			);
	}
}
