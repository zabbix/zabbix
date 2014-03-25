<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

require_once dirname(__FILE__).'/js/common.filter.trigger.js.php';

$config = select_config();

$filterForm = new CFormTable(null, null, 'get');
$filterForm->setAttribute('name', 'zbx_filter');
$filterForm->setAttribute('id', 'zbx_filter');
$filterForm->addVar('fullscreen', $_REQUEST['fullscreen']);
$filterForm->addVar('groupid', $_REQUEST['groupid']);
$filterForm->addVar('hostid', $_REQUEST['hostid']);

// trigger status
$statusComboBox = new CComboBox('show_triggers', $this->data['showTriggers']);
$statusComboBox->addItem(TRIGGERS_OPTION_ALL, _('Any'));
$statusComboBox->additem(TRIGGERS_OPTION_ONLYTRUE, _('Problem'));
$filterForm->addRow(_('Triggers status'), $statusComboBox);

// ack status
if ($config['event_ack_enable']) {
	$ackStatusComboBox = new CComboBox('ack_status', $this->data['ackStatus']);
	$ackStatusComboBox->addItem(ZBX_ACK_STS_ANY, _('Any'));
	$ackStatusComboBox->additem(ZBX_ACK_STS_WITH_UNACK, _('With unacknowledged events'));
	$ackStatusComboBox->additem(ZBX_ACK_STS_WITH_LAST_UNACK, _('With last event unacknowledged'));
	$filterForm->addRow(_('Acknowledge status'), $ackStatusComboBox);
}

// events
$eventsComboBox = new CComboBox('show_events', $this->data['showEvents']);
$eventsComboBox->addItem(EVENTS_OPTION_NOEVENT, _('Hide all'));
$eventsComboBox->addItem(EVENTS_OPTION_ALL, _('Show all').' ('.$config['event_expire'].' '.(($config['event_expire'] > 1) ? _('Days') : _('Day')).')');
if ($config['event_ack_enable']) {
	$eventsComboBox->addItem(EVENTS_OPTION_NOT_ACK, _('Show unacknowledged').' ('.$config['event_expire'].' '.(($config['event_expire'] > 1) ? _('Days') : _('Day')).')');
}
$filterForm->addRow(_('Events'), $eventsComboBox);

// min severity
$severityComboBox = new CComboBox('show_severity', $this->data['showSeverity']);
$severityComboBox->addItems(array(
	TRIGGER_SEVERITY_NOT_CLASSIFIED => getSeverityCaption(TRIGGER_SEVERITY_NOT_CLASSIFIED),
	TRIGGER_SEVERITY_INFORMATION => getSeverityCaption(TRIGGER_SEVERITY_INFORMATION),
	TRIGGER_SEVERITY_WARNING => getSeverityCaption(TRIGGER_SEVERITY_WARNING),
	TRIGGER_SEVERITY_AVERAGE => getSeverityCaption(TRIGGER_SEVERITY_AVERAGE),
	TRIGGER_SEVERITY_HIGH => getSeverityCaption(TRIGGER_SEVERITY_HIGH),
	TRIGGER_SEVERITY_DISASTER => getSeverityCaption(TRIGGER_SEVERITY_DISASTER)
));
$filterForm->addRow(_('Minimum trigger severity'), $severityComboBox);

// age less than
$statusChangeDays = new CNumericBox('status_change_days', $this->data['statusChangeDays'], 3, false, false, false);
if (!$this->data['statusChange']) {
	$statusChangeDays->setAttribute('disabled', 'disabled');
}
$statusChangeDays->addStyle('vertical-align: middle;');

$statusChangeCheckBox = new CCheckBox('status_change', $this->data['statusChange'], 'javascript: this.checked ? $("status_change_days").enable() : $("status_change_days").disable()', 1);
$statusChangeCheckBox->addStyle('vertical-align: middle;');

$daysSpan = new CSpan(_('days'));
$daysSpan->addStyle('vertical-align: middle;');
$filterForm->addRow(_('Age less than'), array($statusChangeCheckBox, $statusChangeDays, SPACE, $daysSpan));

// name
$filterForm->addRow(_('Filter by name'), new CTextBox('txt_select', $this->data['txtSelect'], 40));

// application
$filterForm->addRow(_('Filter by application'), array(
	new CTextBox('application', $this->data['application'], 40),
	new CButton('application_name', _('Select'),
		'return PopUp("popup.php?srctbl=applications&srcfld1=name&real_hosts=1&dstfld1=application&with_applications=1'.
		'&dstfrm='.$filterForm->getName().'");',
		'filter-button'
	)
));

// inventory filter
$inventoryFilters = $this->data['inventory'];
if (!$inventoryFilters) {
	$inventoryFilters = array(
		array('field' => '', 'value' => '')
	);
}
$inventoryFields = array();
foreach (getHostInventories() as $inventory) {
	$inventoryFields[$inventory['db_field']] = $inventory['title'];
}

$inventoryFilterTable = new CTable();
$inventoryFilterTable->setAttribute('id', 'inventory-filter');
$i = 0;
foreach ($inventoryFilters as $field) {
	$inventoryFilterTable->addRow(array(
		new CComboBox('inventory['.$i.'][field]', $field['field'], null, $inventoryFields),
		new CTextBox('inventory['.$i.'][value]', $field['value'], 20),
		new CButton('inventory['.$i.'][remove]', _('Remove'), null, 'link_menu element-table-remove')
	), 'form_row');

	$i++;
}
$inventoryFilterTable->addRow(
	new CCol(new CButton('inventory_add', _('Add'), null, 'link_menu element-table-add'), null, 3)
);
$filterForm->addRow(_('Filter by host inventory'), $inventoryFilterTable);

// maintenance filter
$filterForm->addRow(_('Show hosts in maintenance'), new CCheckBox('show_maintenance', $this->data['showMaintenance'], null, 1));

return $filterForm;
