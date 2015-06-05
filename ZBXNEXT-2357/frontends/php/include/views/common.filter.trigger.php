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

require_once dirname(__FILE__).'/js/common.filter.trigger.js.php';

$overview = $this->data['overview'];
$filter = $this->data['filter'];
$config = $this->data['config'];

$filterForm = new CFilter('web.tr_status.filter.state');

$filterForm->addVar('fullscreen', $filter['fullScreen']);
$filterForm->addVar('groupid', $filter['groupId']);
$filterForm->addVar('hostid', $filter['hostId']);

$column = new CFormList();

// trigger status
$column->addRow(_('Triggers status'), new CComboBox('show_triggers', $filter['showTriggers'], null, [
	TRIGGERS_OPTION_ALL => _('Any'),
	TRIGGERS_OPTION_RECENT_PROBLEM => _('Recent problem'),
	TRIGGERS_OPTION_IN_PROBLEM => _('Problem')
]));

// ack status
if ($config['event_ack_enable']) {
	$column->addRow(_('Acknowledge status'), new CComboBox('ack_status', $filter['ackStatus'], null, [
		ZBX_ACK_STS_ANY => _('Any'),
		ZBX_ACK_STS_WITH_UNACK => _('With unacknowledged events'),
		ZBX_ACK_STS_WITH_LAST_UNACK => _('With last event unacknowledged')
	]));
}

// events
if (!$overview) {
	$eventsComboBox = new CComboBox('show_events', $filter['showEvents'], null, [
		EVENTS_OPTION_NOEVENT => _('Hide all'),
		EVENTS_OPTION_ALL => _n('Show all (%1$s day)', 'Show all (%1$s days)', $config['event_expire'])
	]);
	if ($config['event_ack_enable']) {
		$eventsComboBox->addItem(EVENTS_OPTION_NOT_ACK,
			_n('Show unacknowledged (%1$s day)', 'Show unacknowledged (%1$s days)', $config['event_expire'])
		);
	}
	$column->addRow(_('Events'), $eventsComboBox);
}

// min severity
$severityNames = [];
for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
	$severityNames[] = getSeverityName($severity, $config);
}
$column->addRow(_('Minimum trigger severity'),
	new CComboBox('show_severity', $filter['showSeverity'], null, $severityNames)
);

// age less than
$statusChangeDays = new CNumericBox('status_change_days', $filter['statusChangeDays'], 3, false, false, false);
if (!$filter['statusChange']) {
	$statusChangeDays->setAttribute('disabled', 'disabled');
}
$statusChangeDays->addStyle('vertical-align: middle;');

$statusChangeCheckBox = new CCheckBox('status_change', $filter['statusChange'],
	'javascript: this.checked ? $("status_change_days").enable() : $("status_change_days").disable()', 1
);
$statusChangeCheckBox->addStyle('vertical-align: middle;');

$daysSpan = new CSpan(_('days'));
$daysSpan->addStyle('vertical-align: middle;');
$column->addRow(_('Age less than'), [$statusChangeCheckBox, $statusChangeDays, SPACE, $daysSpan]);

// name
$column->addRow(_('Filter by name'), new CTextBox('txt_select', $filter['txtSelect'], 40));

// application
$column->addRow(_('Filter by application'), [
	new CTextBox('application', $filter['application'], 40),

	new CButton('application_name', _('Select'),
		'return PopUp("popup.php?srctbl=applications&srcfld1=name&real_hosts=1&dstfld1=application&with_applications=1'.
		'&dstfrm=zbx_filter");',
		ZBX_STYLE_BTN_GREY
	)
]);

// inventory filter
$inventoryFilters = $filter['inventory'];
if (!$inventoryFilters) {
	$inventoryFilters = [
		['field' => '', 'value' => '']
	];
}
$inventoryFields = [];
foreach (getHostInventories() as $inventory) {
	$inventoryFields[$inventory['db_field']] = $inventory['title'];
}

$inventoryFilterTable = new CTable();
$inventoryFilterTable->setAttribute('id', 'inventory-filter');
$i = 0;
foreach ($inventoryFilters as $field) {
	$inventoryFilterTable->addRow([
		new CComboBox('inventory['.$i.'][field]', $field['field'], null, $inventoryFields),
		new CTextBox('inventory['.$i.'][value]', $field['value'], 20),
		new CButton('inventory['.$i.'][remove]', _('Remove'), null, 'element-table-remove')
	], 'form_row');

	$i++;
}
$inventoryFilterTable->addRow(
	(new CCol(new CButton('inventory_add', _('Add'), null, 'element-table-add')))->setColSpan(2)
);
$column->addRow(_('Filter by host inventory'), $inventoryFilterTable);

// maintenance filter
$column->addRow(_('Show hosts in maintenance'),
	new CCheckBox('show_maintenance', $filter['showMaintenance'], null, 1)
);

// show details
if (!$overview) {
	$column->addRow(_('Show details'), new CCheckBox('show_details', $filter['showDetails'], null, 1));
}

$filterForm->addColumn($column);

return $filterForm;
