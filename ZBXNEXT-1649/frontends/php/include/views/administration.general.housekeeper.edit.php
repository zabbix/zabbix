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

// remove events and alerts block
$houseKeeperTab->addRow(_('Enable housekeeping'), new CCheckBox('hk_events_mode',
	$this->data['config']['hk_events_mode'], null, 1));
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
$houseKeeperTab->addRow(_('Keep trigger data for (in days)'), $houseKeeperEventsTrigger);
$houseKeeperTab->addRow(_('Keep internal data for (in days)'), $houseKeeperEventsInternal);
$houseKeeperTab->addRow(_('Keep network discovery data for (in days)'), $houseKeeperEventsDiscovery);
$houseKeeperTab->addRow(_('Keep auto-registration data for (in days)'),
	$houseKeeperEventsAutoreg);

// remove IT service history block
$houseKeeperTab->addRow(_('Enable housekeeping'), new CCheckBox('hk_services_mode',
	$this->data['config']['hk_services_mode'], null, 1));
$houseKeeperServicesMode = new CNumericBox('hk_services', $this->data['config']['hk_services'], 5);
if (!$this->data['config']['hk_services_mode']) {
	$houseKeeperServicesMode->setAttribute('disabled', 'disabled');
}
$houseKeeperTab->addRow(_('Keep data for (in days)'), $houseKeeperServicesMode);

// remove audit history block
$houseKeeperTab->addRow(_('Enable housekeeping'), new CCheckBox('hk_audit_mode',
	$this->data['config']['hk_audit_mode'], null, 1));
$houseKeeperAuditMode = new CNumericBox('hk_audit', $this->data['config']['hk_audit'], 5);
if (!$this->data['config']['hk_audit_mode']) {
	$houseKeeperAuditMode->setAttribute('disabled', 'disabled');
}
$houseKeeperTab->addRow(_('Keep data for (in days)'), $houseKeeperAuditMode);

// remove audit history block
$houseKeeperTab->addRow(_('Enable housekeeping'), new CCheckBox('hk_sessions_mode',
	$this->data['config']['hk_sessions_mode'], null, 1));
$houseKeeperSessionsMode = new CNumericBox('hk_sessions', $this->data['config']['hk_sessions'], 5);
if (!$this->data['config']['hk_sessions_mode']) {
	$houseKeeperSessionsMode->setAttribute('disabled', 'disabled');
}
$houseKeeperTab->addRow(_('Keep data for (in days)'), $houseKeeperSessionsMode);

// remove historical data block
$houseKeeperTab->addRow(_('Enable housekeeping'), new CCheckBox('hk_history_mode',
	$this->data['config']['hk_history_mode'], null, 1));
$houseKeeperHistoryGlobal = new CCheckBox('hk_history_global',
	$this->data['config']['hk_history_global'], null, 1);
if (!$this->data['config']['hk_history_mode']) {
	$houseKeeperHistoryGlobal->setAttribute('disabled', 'disabled');
}
$houseKeeperHistoryModeGlobal = new CNumericBox('hk_history', $this->data['config']['hk_history'], 5);
if (!$this->data['config']['hk_history_mode'] || !$this->data['config']['hk_history_global']) {
	$houseKeeperHistoryModeGlobal->setAttribute('disabled', 'disabled');
}
$houseKeeperTab->addRow(_('Override item history period'), $houseKeeperHistoryGlobal);
$houseKeeperTab->addRow(_('Keep data for (in days)'), $houseKeeperHistoryModeGlobal);

// remove trend data block
$houseKeeperTab->addRow(_('Enable housekeeping'), new CCheckBox('hk_trends_mode',
	$this->data['config']['hk_trends_mode'], null, 1));
$houseKeeperTrendsGlobal= new CCheckBox('hk_trends_global',
	$this->data['config']['hk_trends_global'], null, 1);
if (!$this->data['config']['hk_trends_mode']) {
	$houseKeeperTrendsGlobal->setAttribute('disabled', 'disabled');
}
$houseKeeperTrendsModeGlobal = new CNumericBox('hk_trends', $this->data['config']['hk_trends'], 5);
if (!$this->data['config']['hk_trends_mode'] || !$this->data['config']['hk_trends_global']) {
	$houseKeeperTrendsModeGlobal->setAttribute('disabled', 'disabled');
}
$houseKeeperTab->addRow(_('Override item trends period'), $houseKeeperTrendsGlobal);
$houseKeeperTab->addRow(_('Keep data for (in days)'), $houseKeeperTrendsModeGlobal);

$houseKeeperView = new CTabView();
$houseKeeperView->addTab('houseKeeper', _('Housekeeper'), $houseKeeperTab);

$houseKeeperForm = new CForm();
$houseKeeperForm->setName('houseKeeperForm');
$houseKeeperForm->addVar('form_refresh', $this->data['form_refresh'] + 1);
$houseKeeperForm->addItem($houseKeeperView);
$houseKeeperForm->addItem(makeFormFooter(array(new CSubmit('save', _('Save'))), new CButton('reset', _('Reset'))));

return $houseKeeperForm;
?>
