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

// create table
$hostTable = new CTableInfo(_('No host prototypes defined.'));

$sortLink = new CUrl();
$sortLink->setArgument('parent_discoveryid', $this->data['parent_discoveryid']);
$sortLink = $sortLink->getUrl();

$hostTable->setHeader(array(
	new CCheckBox('all_hosts', null, "checkAll('".$itemForm->getName()."', 'all_hosts', 'group_host');"),
	make_sorting_header(_('Name'),'name', $sortLink),
	_('Templates')
));

foreach ($this->data['items'] as $item) {
	$description = array();
	if (!empty($item['templateid'])) {
		$template_host = get_realhost_by_itemid($item['templateid']);
		$templateDiscoveryRuleId = get_realrule_by_itemid_and_hostid($this->data['parent_discoveryid'], $template_host['hostid']);

		$description[] = new CLink($template_host['name'], '?parent_discoveryid='.$templateDiscoveryRuleId, 'unknown');
		$description[] = ': ';
	}
	$description[] = new CLink(itemName($item), '?form=update&itemid='.$item['itemid'].'&parent_discoveryid='.$this->data['parent_discoveryid']);

	$status = new CLink(item_status2str($item['status']), '?group_itemid='.$item['itemid'].'&parent_discoveryid='.$this->data['parent_discoveryid'].
		'&go='.($item['status'] ? 'activate' : 'disable'), item_status2style($item['status'])
	);

	if (!empty($item['applications'])) {
		order_result($item['applications'], 'name');

		$applications = zbx_objectValues($item['applications'], 'name');
		$applications = implode(', ', $applications);
		if (empty($applications)) {
			$applications = '-';
		}
	}
	else {
		$applications = '-';
	}

	$hostTable->addRow(array(
		new CCheckBox('group_itemid['.$item['itemid'].']', null, null, $item['itemid']),
		$description,
		$item['key_'],
		$item['delay'],
		$item['history'],
		in_array($item['value_type'], array(ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT)) ? '' : $item['trends'],
		item_type2str($item['type']),
		$status,
		new CCol($applications, 'wraptext'),
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
