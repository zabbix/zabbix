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


$hostInventoryWidget = new CWidget(null, 'inventory-list');

$rForm = new CForm('get');
$rForm->addItem(array(_('Group'), SPACE, $this->data['pageFilter']->getGroupsCB(true)));
$hostInventoryWidget->addPageHeader(_('HOST INVENTORY'), SPACE);
$hostInventoryWidget->addHeader(_('Hosts'), $rForm);

// inventory filter
$inventoryFilters = $data['filter'];
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

$filterTable = new CTable('', 'filter');
$filterTable->addRow($inventoryFilterTable, 'host-inventories');

$filter = new CSubmit('filter_set', _('Filter'));
$filter->useJQueryStyle('main');
$reset = new CSubmit('filter_rst', _('Reset'));
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

require_once dirname(__FILE__).'/js/inventory.host.list.js.php';

return $hostInventoryWidget;
