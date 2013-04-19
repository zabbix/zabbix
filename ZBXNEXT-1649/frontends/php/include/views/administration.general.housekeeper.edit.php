<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

$eventAlertTab->addRow(
	array(
		new CDiv(_('Enable housekeeping'), 'hkElementLabel'),
		new CCheckBox('hk_events_mode', $this->data['config']['hk_events_mode'], null, 1)
	)
);

$houseKeeperEventsTrigger = new CNumericBox('hk_events_trigger', $this->data['config']['hk_events_trigger'], 5);
$houseKeeperEventsInternal = new CNumericBox('hk_events_internal', $this->data['config']['hk_events_internal'], 5);
$houseKeeperEventsDiscovery = new CNumericBox('hk_events_discovery', $this->data['config']['hk_events_discovery'], 5);
$houseKeeperEventsAutoreg = new CNumericBox('hk_events_autoreg', $this->data['config']['hk_events_autoreg'], 5);
if (!$this->data['config']['hk_events_mode']) {
	$houseKeeperEventsTrigger->setAttribute('disabled', 'disabled');
	$houseKeeperEventsInternal->setAttribute('disabled', 'disabled');
	$houseKeeperEventsDiscovery->setAttribute('disabled', 'disabled');
	$houseKeeperEventsAutoreg->setAttribute('disabled', 'disabled');
}
$eventAlertTab->addRow(array(_('Keep trigger data for (in days)'), $houseKeeperEventsTrigger));
$eventAlertTab->addRow(array(_('Keep internal data for (in days)'), $houseKeeperEventsInternal));
$eventAlertTab->addRow(array(_('Keep network discovery data for (in days)'), $houseKeeperEventsDiscovery));
$eventAlertTab->addRow(array(_('Keep auto-registration data for (in days)'),
	$houseKeeperEventsAutoreg));
$eventAlertTab->addClass('border_dotted objectgroup interface-group');
$houseKeeperTab->addRow(_('Events and alerts'), new CDiv($eventAlertTab));


// IT services
$itServicesTab = new CTable(null, 'formElementTable');

$itServicesTab->addRow(
	array(
		new CDiv(_('Enable housekeeping'), 'hkElementLabel'),
		new CCheckBox('hk_services_mode', $this->data['config']['hk_services_mode'], null, 1)
	)
);

$houseKeeperServicesMode = new CNumericBox('hk_services', $this->data['config']['hk_services'], 5);
if (!$this->data['config']['hk_services_mode']) {
	$houseKeeperServicesMode->setAttribute('disabled', 'disabled');
}
$itServicesTab->addRow(array(_('Keep data for (in days)'), $houseKeeperServicesMode));
$itServicesTab->addClass('border_dotted objectgroup interface-group');
$houseKeeperTab->addRow(_('IT services'), new CDiv($itServicesTab));

// audit
$auditTab = new CTable(null, 'formElementTable');

$auditTab->addRow(
	array(
		new CDiv(_('Enable housekeeping'), 'hkElementLabel'),
		new CCheckBox('hk_audit_mode', $this->data['config']['hk_audit_mode'], null, 1)
	)
);

$houseKeeperAuditMode = new CNumericBox('hk_audit', $this->data['config']['hk_audit'], 5);
if (!$this->data['config']['hk_audit_mode']) {
	$houseKeeperAuditMode->setAttribute('disabled', 'disabled');
}
$auditTab->addRow(array(_('Keep data for (in days)'), $houseKeeperAuditMode));
$auditTab->addClass('border_dotted objectgroup interface-group');
$houseKeeperTab->addRow(_('Audit'), new CDiv($auditTab));

// user session
$userSessionTab = new CTable(null, 'formElementTable');

$userSessionTab->addRow(
	array(
		new CDiv(_('Enable housekeeping'), 'hkElementLabel'),
		new CCheckBox('hk_sessions_mode', $this->data['config']['hk_sessions_mode'], null, 1)
	)
);

$houseKeeperSessionsMode = new CNumericBox('hk_sessions', $this->data['config']['hk_sessions'], 5);
if (!$this->data['config']['hk_sessions_mode']) {
	$houseKeeperSessionsMode->setAttribute('disabled', 'disabled');
}
$userSessionTab->addRow(array(_('Keep data for (in days)'), $houseKeeperSessionsMode));
$userSessionTab->addClass('border_dotted objectgroup interface-group');
$houseKeeperTab->addRow(_('User sessions'), new CDiv($userSessionTab));

// history
$histortTab = new CTable(null, 'formElementTable');

$histortTab->addRow(array(new CDiv(_('Enable housekeeping'), 'hkElementLabel'), new CCheckBox('hk_history_mode',
	$this->data['config']['hk_history_mode'], null, 1)));
$houseKeeperHistoryGlobal = new CCheckBox('hk_history_global',
	$this->data['config']['hk_history_global'], null, 1);
if (!$this->data['config']['hk_history_mode']) {
	$houseKeeperHistoryGlobal->setAttribute('disabled', 'disabled');
}
$houseKeeperHistoryModeGlobal = new CNumericBox('hk_history', $this->data['config']['hk_history'], 5);
if (!$this->data['config']['hk_history_mode'] || !$this->data['config']['hk_history_global']) {
	$houseKeeperHistoryModeGlobal->setAttribute('disabled', 'disabled');
}
$histortTab->addRow(array(_('Override item history period'), $houseKeeperHistoryGlobal));
$histortTab->addRow(array(_('Keep data for (in days)'), $houseKeeperHistoryModeGlobal));
$histortTab->addClass('border_dotted objectgroup interface-group');
$houseKeeperTab->addRow(_('History'), new CDiv($histortTab));

// trend
$trendTab = new CTable(null, 'formElementTable');

$trendTab->addRow(array(new CDiv(_('Enable housekeeping'), 'hkElementLabel'), new CCheckBox('hk_trends_mode',
	$this->data['config']['hk_trends_mode'], null, 1)));
$houseKeeperTrendGlobal = new CCheckBox('hk_trends_global',
	$this->data['config']['hk_trends_global'], null, 1);
if (!$this->data['config']['hk_trends_mode']) {
	$houseKeeperTrendGlobal->setAttribute('disabled', 'disabled');
}
$houseKeeperTrendModeGlobal = new CNumericBox('hk_trends', $this->data['config']['hk_trends'], 5);
if (!$this->data['config']['hk_trends_mode'] || !$this->data['config']['hk_trends_global']) {
	$houseKeeperTrendModeGlobal->setAttribute('disabled', 'disabled');
}
$trendTab->addRow(array(_('Override item trends period'), $houseKeeperTrendGlobal));
$trendTab->addRow(array(_('Keep data for (in days)'), $houseKeeperTrendModeGlobal));
$trendTab->addClass('border_dotted objectgroup interface-group');
$houseKeeperTab->addRow(_('Trends'), new CDiv($trendTab));

$houseKeeperView = new CTabView();
$houseKeeperView->addTab('houseKeeper', _('Housekeeper'), $houseKeeperTab);

$houseKeeperForm = new CForm();
$houseKeeperForm->setName('houseKeeperForm');
$houseKeeperForm->addVar('form_refresh', $this->data['form_refresh'] + 1);
$houseKeeperForm->addItem($houseKeeperView);
$houseKeeperForm->addItem(makeFormFooter(array(new CSubmit('save', _('Save'))), new CButton('reset', _('Reset'))));

return $houseKeeperForm;
?>
