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


$hostInventoryWidget = new CWidget();

$rForm = new CForm('get');
$rForm->addItem(array(_('Group'), SPACE, $this->data['pageFilter']->getGroupsCB(true)));
$hostInventoryWidget->addPageHeader(_('HOST INVENTORY'), SPACE);
$hostInventoryWidget->addHeader(_('Hosts'), $rForm);

$filterTable = new CTable('', 'filter');
// getting inventory fields to make a drop down
$inventoryFields = getHostInventories(true); // 'true' means list should be ordered by title
$inventoryFieldsComboBox = new CComboBox('filter_field', $this->data['filterField']);
foreach ($inventoryFields as $inventoryField) {
	$inventoryFieldsComboBox->addItem(
		$inventoryField['db_field'],
		$inventoryField['title']
	);
}
$exactComboBox = new CComboBox('filter_exact', $this->data['filterExact']);
$exactComboBox->addItem('0', _('like'));
$exactComboBox->addItem('1', _('exactly'));
$filterTable->addRow(array(
	array(
		array(bold(_('Field')), SPACE, $inventoryFieldsComboBox),
		array(
			$exactComboBox,
			new CTextBox('filter_field_value', $this->data['filterFieldValue'], 20)
		),
	),
), 'host-inventories');

$filter = new CButton('filter', _('Filter'),
	"javascript: create_var('zbx_filter', 'filter_set', '1', true); chkbxRange.clearSelectedOnFilterChange();"
);
$filter->useJQueryStyle('main');

$reset = new CButton('reset', _('Reset'), "javascript: clearAllForm('zbx_filter');");
$reset->useJQueryStyle();

$divButtons = new CDiv(array($filter, SPACE, $reset));
$divButtons->setAttribute('style', 'padding: 4px 0px;');

$footerCol = new CCol($divButtons, 'controls');

$filterTable->addRow($footerCol);

$filterForm = new CForm('get');
$filterForm->setAttribute('name', 'zbx_filter');
$filterForm->setAttribute('id', 'zbx_filter');
$filterForm->addItem($filterTable);
$hostInventoryWidget->addFlicker($filterForm, CProfile::get('web.hostinventories.filter.state', 0));
$hostInventoryWidget->addHeaderRowNumber();

$table = new CTableInfo(_('No hosts found.'));
$table->setHeader(array(
	is_show_all_nodes() ? make_sorting_header(_('Node'), 'hostid') : null,
	make_sorting_header(_('Host'), 'name'),
	_('Group'),
	make_sorting_header(_('Name'), 'pr_name'),
	make_sorting_header(_('Type'), 'pr_type'),
	make_sorting_header(_('OS'), 'pr_os'),
	make_sorting_header(_('Serial number A'), 'pr_serialno_a'),
	make_sorting_header(_('Tag'), 'pr_tag'),
	make_sorting_header(_('MAC address A'), 'pr_macaddress_a'))
);

foreach ($this->data['hosts'] as $host) {
	$hostGroups = array();
	foreach ($host['groups'] as $group) {
		$hostGroups[] = $group['name'];
	}
	natsort($hostGroups);
	$hostGroups = implode(', ', $hostGroups);

	$row = array(
		get_node_name_by_elid($host['hostid']),
		new CLink(
			$host['name'],
			'?hostid='.$host['hostid'].url_param('groupid'),
			($host['status'] == HOST_STATUS_NOT_MONITORED) ? 'not-monitored' : ''
		),
		$hostGroups,
		zbx_str2links($host['inventory']['name']),
		zbx_str2links($host['inventory']['type']),
		zbx_str2links($host['inventory']['os']),
		zbx_str2links($host['inventory']['serialno_a']),
		zbx_str2links($host['inventory']['tag']),
		zbx_str2links($host['inventory']['macaddress_a'])
	);

	$table->addRow($row);
}

$table = array($this->data['paging'], $table, $this->data['paging']);
$hostInventoryWidget->addItem($table);

return $hostInventoryWidget;
