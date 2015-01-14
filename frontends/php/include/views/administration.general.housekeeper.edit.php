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


require_once dirname(__FILE__).'/js/administration.general.housekeeper.edit.js.php';

$houseKeeperTab = new CFormList('scriptsTab');

// events and alerts
$eventAlertTab = new CTable(null, 'formElementTable');
$eventsMode = new CCheckBox('hk_events_mode', $data['hk_events_mode'] == 1, null, 1);
$eventAlertTab->addRow(array(
	new CLabel(_('Enable internal housekeeping'), 'hk_events_mode'),
	$eventsMode
));

$houseKeeperEventsTrigger = new CNumericBox('hk_events_trigger', $data['hk_events_trigger'], 5);
$houseKeeperEventsInternal = new CNumericBox('hk_events_internal', $data['hk_events_internal'], 5);
$houseKeeperEventsDiscovery = new CNumericBox('hk_events_discovery', $data['hk_events_discovery'], 5);
$houseKeeperEventsAutoreg = new CNumericBox('hk_events_autoreg', $data['hk_events_autoreg'], 5);
$houseKeeperEventsTrigger->setEnabled($data['hk_events_mode'] == 1);
$houseKeeperEventsInternal->setEnabled($data['hk_events_mode'] == 1);
$houseKeeperEventsDiscovery->setEnabled($data['hk_events_mode'] == 1);
$houseKeeperEventsAutoreg->setEnabled($data['hk_events_mode'] == 1);
$eventAlertTab->addRow(array(
	new CLabel(_('Trigger data storage period (in days)'), 'hk_events_trigger'),
	$houseKeeperEventsTrigger
));
$eventAlertTab->addRow(array(
	new CLabel(_('Internal data storage period (in days)'), 'hk_events_internal'),
	$houseKeeperEventsInternal
));
$eventAlertTab->addRow(array(
	new CLabel(_('Network discovery data storage period (in days)'), 'hk_events_discovery'),
	$houseKeeperEventsDiscovery
));
$eventAlertTab->addRow(array(
	new CLabel(_('Auto-registration data storage period (in days)'), 'hk_events_autoreg'),
	$houseKeeperEventsAutoreg
));
$eventAlertTab->addClass('border_dotted objectgroup element-row element-row-first');
$houseKeeperTab->addRow(_('Events and alerts'), new CDiv($eventAlertTab));

// IT services
$itServicesTab = new CTable(null, 'formElementTable');

$itServicesTab->addRow(array(
	new CLabel(_('Enable internal housekeeping'), 'hk_services_mode'),
	new CCheckBox('hk_services_mode', $data['hk_services_mode'] == 1, null, 1)
));

$houseKeeperServicesMode = new CNumericBox('hk_services', $data['hk_services'], 5);
$houseKeeperServicesMode->setEnabled($data['hk_services_mode'] == 1);
$itServicesTab->addRow(array(
	new CLabel(_('Data storage period (in days)'), 'hk_services'),
	$houseKeeperServicesMode
));
$itServicesTab->addClass('border_dotted objectgroup element-row');
$houseKeeperTab->addRow(_('IT services'), new CDiv($itServicesTab));

// audit
$auditTab = new CTable(null, 'formElementTable');

$auditTab->addRow(array(
	new CLabel(_('Enable internal housekeeping'), 'hk_audit_mode'),
	new CCheckBox('hk_audit_mode', $data['hk_audit_mode'] == 1, null, 1)
));

$houseKeeperAuditMode = new CNumericBox('hk_audit', $data['hk_audit'], 5);
$houseKeeperAuditMode->setEnabled($data['hk_audit_mode'] == 1);
$auditTab->addRow(array(
	new CLabel(_('Data storage period (in days)'), 'hk_audit'),
	$houseKeeperAuditMode
));
$auditTab->addClass('border_dotted objectgroup element-row');
$houseKeeperTab->addRow(_('Audit'), new CDiv($auditTab));

// user session
$userSessionTab = new CTable(null, 'formElementTable');

$userSessionTab->addRow(array(
	new CLabel(_('Enable internal housekeeping'), 'hk_sessions_mode'),
	new CCheckBox('hk_sessions_mode', $data['hk_sessions_mode'] == 1, null, 1)
));

$houseKeeperSessionsMode = new CNumericBox('hk_sessions', $data['hk_sessions'], 5);
$houseKeeperSessionsMode->setEnabled($data['hk_sessions_mode'] == 1);
$userSessionTab->addRow(array(
	new CLabel(_('Data storage period (in days)'), 'hk_sessions'),
	$houseKeeperSessionsMode
));
$userSessionTab->addClass('border_dotted objectgroup element-row');
$houseKeeperTab->addRow(_('User sessions'), new CDiv($userSessionTab));

// history
$histortTab = new CTable(null, 'formElementTable');

$histortTab->addRow(array(
	new CLabel(_('Enable internal housekeeping'), 'hk_history_mode'),
	new CCheckBox('hk_history_mode', $data['hk_history_mode'] == 1, null, 1)
));
$houseKeeperHistoryGlobal = new CCheckBox('hk_history_global', $data['hk_history_global'] == 1, null, 1);
$houseKeeperHistoryModeGlobal = new CNumericBox('hk_history', $data['hk_history'], 5);
$houseKeeperHistoryModeGlobal->setEnabled($data['hk_history_global'] == 1);
$histortTab->addRow(array(new CLabel(_('Override item history period'),
	'hk_history_global'), $houseKeeperHistoryGlobal));
$histortTab->addRow(array(
	new CLabel(_('Data storage period (in days)'), 'hk_history'),
	$houseKeeperHistoryModeGlobal
));
$histortTab->addClass('border_dotted objectgroup element-row');
$houseKeeperTab->addRow(_('History'), new CDiv($histortTab));

// trend
$trendTab = new CTable(null, 'formElementTable');

$trendTab->addRow(array(
	new CLabel(_('Enable internal housekeeping'), 'hk_trends_mode'),
	new CCheckBox('hk_trends_mode', $data['hk_trends_mode'] == 1, null, 1)
));
$houseKeeperTrendGlobal = new CCheckBox('hk_trends_global', $data['hk_trends_global'] == 1, null, 1);
$houseKeeperTrendModeGlobal = new CNumericBox('hk_trends', $data['hk_trends'], 5);
$houseKeeperTrendModeGlobal->setEnabled($data['hk_trends_global'] == 1);
$trendTab->addRow(array(new CLabel(_('Override item trend period'),
	'hk_trends_global'), $houseKeeperTrendGlobal));
$trendTab->addRow(array(
	new CLabel(_('Data storage period (in days)'), 'hk_trends'),
	$houseKeeperTrendModeGlobal
));
$trendTab->addClass('border_dotted objectgroup element-row');
$houseKeeperTab->addRow(_('Trends'), new CDiv($trendTab));

$houseKeeperView = new CTabView();
$houseKeeperView->addTab('houseKeeper', _('Housekeeping'), $houseKeeperTab);

$houseKeeperForm = new CForm();
$houseKeeperForm->setName('houseKeeperForm');
$houseKeeperForm->addItem($houseKeeperView);
$houseKeeperForm->addItem(makeFormFooter(
	new CSubmit('update', _('Update')),
	array(new CButton('resetDefaults', _('Reset defaults')))
));

return $houseKeeperForm;
