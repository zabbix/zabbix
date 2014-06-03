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

$overview = $this->data['overview'];
$filter = $this->data['filter'];

$config = select_config();

$filterForm = new CFormTable(null, null, 'get');
$filterForm->setTableClass('formtable old-filter');
$filterForm->setAttribute('name', 'zbx_filter');
$filterForm->setAttribute('id', 'zbx_filter');
$filterForm->addVar('fullscreen', $filter['fullScreen']);
$filterForm->addVar('groupid', $filter['groupId']);
$filterForm->addVar('hostid', $filter['hostId']);

// trigger status
$filterForm->addRow(_('Triggers status'), new CComboBox('show_triggers', $filter['showTriggers'], null, array(
	TRIGGERS_OPTION_ALL => _('Any'),
	TRIGGERS_OPTION_RECENT_PROBLEM => _('Recent problem'),
	TRIGGERS_OPTION_IN_PROBLEM => _('Problem')
)));

// ack status
if ($config['event_ack_enable']) {
	$filterForm->addRow(_('Acknowledge status'), new CComboBox('ack_status', $filter['ackStatus'], null, array(
		ZBX_ACK_STS_ANY => _('Any'),
		ZBX_ACK_STS_WITH_UNACK => _('With unacknowledged events'),
		ZBX_ACK_STS_WITH_LAST_UNACK => _('With last event unacknowledged')
	)));
}

// events
if (!$overview) {
	$eventsComboBox = new CComboBox('show_events', $filter['showEvents'], null, array(
		EVENTS_OPTION_NOEVENT => _('Hide all'),
		EVENTS_OPTION_ALL => _n('Show all (%1$s day)', 'Show all (%1$s days)', $config['event_expire'])
	));
	if ($config['event_ack_enable']) {
		$eventsComboBox->addItem(EVENTS_OPTION_NOT_ACK,
			_n('Show unacknowledged (%1$s day)', 'Show unacknowledged (%1$s days)', $config['event_expire'])
		);
	}
	$filterForm->addRow(_('Events'), $eventsComboBox);
}

// min severity
$filterForm->addRow(_('Minimum trigger severity'),
	new CComboBox('show_severity', $filter['showSeverity'], null, array(
		TRIGGER_SEVERITY_NOT_CLASSIFIED => getSeverityCaption(TRIGGER_SEVERITY_NOT_CLASSIFIED),
		TRIGGER_SEVERITY_INFORMATION => getSeverityCaption(TRIGGER_SEVERITY_INFORMATION),
		TRIGGER_SEVERITY_WARNING => getSeverityCaption(TRIGGER_SEVERITY_WARNING),
		TRIGGER_SEVERITY_AVERAGE => getSeverityCaption(TRIGGER_SEVERITY_AVERAGE),
		TRIGGER_SEVERITY_HIGH => getSeverityCaption(TRIGGER_SEVERITY_HIGH),
		TRIGGER_SEVERITY_DISASTER => getSeverityCaption(TRIGGER_SEVERITY_DISASTER)
	)
));

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
$filterForm->addRow(_('Age less than'), array($statusChangeCheckBox, $statusChangeDays, SPACE, $daysSpan));

// name
$filterForm->addRow(_('Filter by name'), new CTextBox('txt_select', $filter['txtSelect'], 40));

// application
$filterForm->addRow(_('Filter by application'), array(
	new CTextBox('application', $filter['application'], 40),
	new CButton('application_name', _('Select'),
		'return PopUp("popup.php?srctbl=applications&srcfld1=name&real_hosts=1&dstfld1=application&with_applications=1'.
		'&dstfrm='.$filterForm->getName().'");',
		'filter-button'
	)
));

// inventory filter
$inventoryFilters = $filter['inventory'];
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
$filterForm->addRow(_('Show hosts in maintenance'),
	new CCheckBox('show_maintenance', $filter['showMaintenance'], null, 1)
);

// show details
if (!$overview) {
	$filterForm->addRow(_('Show details'), new CCheckBox('show_details', $filter['showDetails'], null, 1));
}

// buttons
$filterForm->addItemToBottomRow(new CSubmit('filter_set', _('Filter'), 'chkbxRange.clearSelectedOnFilterChange();'));
$filterForm->addItemToBottomRow(new CSubmit('filter_rst', _('Reset'), 'chkbxRange.clearSelectedOnFilterChange();'));

return $filterForm;
