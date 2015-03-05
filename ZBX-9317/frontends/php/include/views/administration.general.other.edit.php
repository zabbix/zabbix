<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


$otherTab = new CFormList('scriptsTab');

$discoveryGroup = new CComboBox('discovery_groupid', $data['discovery_groupid']);
foreach ($data['discovery_groups'] as $group) {
	$discoveryGroup->addItem($group['groupid'], $group['name']);
}

$alertUserGroup = new CComboBox('alert_usrgrpid', $data['alert_usrgrpid']);
$alertUserGroup->addItem(0, _('None'));
foreach ($data['alert_usrgrps'] as $usrgrp) {
	$alertUserGroup->addItem($usrgrp['usrgrpid'], $usrgrp['name']);
}

$otherTab->addRow(_('Refresh unsupported items (in sec)'),
	new CNumericBox('refresh_unsupported', $data['refresh_unsupported'], 5)
);
$otherTab->addRow(_('Group for discovered hosts'), $discoveryGroup);
$otherTab->addRow(_('User group for database down message'), $alertUserGroup);
$otherTab->addRow(_('Log unmatched SNMP traps'),
	new CCheckBox('snmptrap_logging', $data['snmptrap_logging'] == 1, null, 1)
);

$otherView = new CTabView();
$otherView->addTab('other', _('Other parameters'), $otherTab);

$otherForm = new CForm();
$otherForm->setName('otherForm');
$otherForm->addItem($otherView);
$otherForm->addItem(makeFormFooter(new CSubmit('update', _('Update'))));

return $otherForm;
