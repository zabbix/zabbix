<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
$itemsWidget = new CWidget();

$discoverRule = $this->data['discovery_rule'];

// create new item button
$createForm = new CForm('get');
$createForm->cleanItems();
$createForm->addVar('parent_hostid', $this->data['parent_hostid']);
$createForm->addVar('parent_discoveryid', $this->data['parent_discoveryid']);
$createForm->addItem(new CSubmit('form', _('Create host prototype')));
$itemsWidget->addPageHeader(_('CONFIGURATION OF HOST PROTOTYPES'), $createForm);

// header
$itemsWidget->addHeader(array(_('Host prototypes of').SPACE, new CSpan($this->data['discovery_rule']['name'], 'gold')));
$itemsWidget->addHeaderRowNumber();
$itemsWidget->addItem(get_header_host_table('hosts', $this->data['parent_hostid'], $this->data['parent_discoveryid']));

// create form
$itemForm = new CForm('get');
$itemForm->setName('hosts');
$itemForm->addVar('parent_hostid', $this->data['parent_hostid']);
$itemForm->addVar('parent_discoveryid', $this->data['parent_discoveryid']);

// create table
$hostTable = new CTableInfo(_('No host prototypes defined.'));

$sortLink = new CUrl();
$sortLink->setArgument('parent_discoveryid', $this->data['parent_discoveryid']);
$sortLink = $sortLink->getUrl();

$hostTable->setHeader(array(
	new CCheckBox('all_hosts', null, "checkAll('".$itemForm->getName()."', 'all_hosts', 'group_hostid');"),
	make_sorting_header(_('Name'),'name', $sortLink),
	_('Templates'),
	make_sorting_header(_('Status'),'status', $sortLink)
));

foreach ($this->data['hostPrototypes'] as $host) {
	$status = new CLink(item_status2str($host['status']),
		'?group_hostid='.$host['hostid'].'&parent_discoveryid='.$discoverRule['itemid'].'&parent_hostid='.$this->data['parent_hostid'].
		'&go='.($host['status'] ? 'activate' : 'disable'), item_status2style($host['status'])
	);

	$hostTable->addRow(array(
		new CCheckBox('group_hostid['.$host['hostid'].']', null, null, $host['hostid']),
		new CLink($host['name'], '?form=update&parent_discoveryid='.$discoverRule['itemid'].'&parent_hostid='.$this->data['parent_hostid'].'&hostid='.$host['hostid']),
		'',
		$status
	));
}

// create go buttons
$goComboBox = new CComboBox('go');
$goOption = new CComboItem('activate', _('Activate selected'));
$goOption->setAttribute('confirm', _('Enable selected host prototypes?'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('disable', _('Disable selected'));
$goOption->setAttribute('confirm', _('Disable selected host prototypes?'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('delete', _('Delete selected'));
$goOption->setAttribute('confirm', _('Delete selected host prototypes?'));
$goComboBox->addItem($goOption);

$goButton = new CSubmit('goButton', _('Go').' (0)');
$goButton->setAttribute('id', 'goButton');
zbx_add_post_js('chkbxRange.pageGoName = "group_hostid";');

// append table to form
$itemForm->addItem(array($this->data['paging'], $hostTable, $this->data['paging'], get_table_header(array($goComboBox, $goButton))));

// append form to widget
$itemsWidget->addItem($itemForm);
return $itemsWidget;
?>
