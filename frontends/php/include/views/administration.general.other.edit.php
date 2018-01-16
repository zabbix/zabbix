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


$widget = (new CWidget())
	->setTitle(_('Other configuration parameters'))
	->setControls((new CTag('nav', true,
		(new CForm())
			->cleanItems()
			->addItem((new CList())
				->addItem(makeAdministrationGeneralMenu('adm.other.php'))
			)
		))
			->setAttribute('aria-label', _('Content controls'))
	);

$otherTab = new CFormList();

$discoveryGroup = new CComboBox('discovery_groupid', $data['discovery_groupid']);
foreach ($data['discovery_groups'] as $group) {
	$discoveryGroup->addItem($group['groupid'], $group['name']);
}

$alertUserGroup = new CComboBox('alert_usrgrpid', $data['alert_usrgrpid']);
$alertUserGroup->addItem(0, _('None'));
foreach ($data['alert_usrgrps'] as $usrgrp) {
	$alertUserGroup->addItem($usrgrp['usrgrpid'], $usrgrp['name']);
}

$otherTab
	->addRow(_('Refresh unsupported items'),
		(new CTextBox('refresh_unsupported', $data['refresh_unsupported']))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
	)
	->addRow(_('Group for discovered hosts'), $discoveryGroup)
	->addRow(_('Default host inventory mode'),
		(new CRadioButtonList('default_inventory_mode', (int) $data['default_inventory_mode']))
			->addValue(_('Disabled'), HOST_INVENTORY_DISABLED)
			->addValue(_('Manual'), HOST_INVENTORY_MANUAL)
			->addValue(_('Automatic'), HOST_INVENTORY_AUTOMATIC)
			->setModern(true)
	)
	->addRow(_('User group for database down message'), $alertUserGroup)
	->addRow(_('Log unmatched SNMP traps'),
		(new CCheckBox('snmptrap_logging'))->setChecked($data['snmptrap_logging'] == 1)
	);

$otherForm = (new CForm())
	->setName('otherForm')
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addItem(
		(new CTabView())
			->addTab('other', _('Other parameters'), $otherTab)
			->setFooter(makeFormFooter(new CSubmit('update', _('Update'))))
	);

$widget->addItem($otherForm);

return $widget;
