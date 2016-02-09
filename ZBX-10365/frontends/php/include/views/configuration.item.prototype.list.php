<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
?>
<?php
$itemsWidget = new CWidget();

// create new item button
$createForm = new CForm('get');
$createForm->cleanItems();
$createForm->addVar('parent_discoveryid', $this->data['parent_discoveryid']);
$createForm->addItem(new CSubmit('form', _('Create item prototype')));
$itemsWidget->addPageHeader(_('CONFIGURATION OF ITEM PROTOTYPES'), $createForm);

// header
$itemsWidget->addHeader(array(_('Item prototypes of').SPACE, new CSpan($this->data['discovery_rule']['name'], 'gold')));
$itemsWidget->addHeaderRowNumber();
$itemsWidget->addItem(get_header_host_table('items', $this->data['hostid'], $this->data['parent_discoveryid']));

// create form
$itemForm = new CForm('get');
$itemForm->setName('items');
$itemForm->addVar('hostid', $this->data['hostid']);
$itemForm->addVar('parent_discoveryid', $this->data['parent_discoveryid']);

// create table
$itemTable = new CTableInfo(_('No items prototypes defined.'));

$sortLink = new CUrl();
$sortLink->setArgument('parent_discoveryid', $this->data['parent_discoveryid']);
$sortLink = $sortLink->getUrl();

$itemTable->setHeader(array(
	new CCheckBox('all_items', null, "checkAll('".$itemForm->getName()."', 'all_items', 'group_itemid');"),
	make_sorting_header(_('Name'),'name', $sortLink),
	make_sorting_header(_('Key'), 'key_', $sortLink),
	make_sorting_header(_('Interval'), 'delay', $sortLink),
	make_sorting_header(_('History'), 'history', $sortLink),
	make_sorting_header(_('Trends'), 'trends', $sortLink),
	make_sorting_header(_('Type'), 'type', $sortLink),
	make_sorting_header(_('Status'), 'status', $sortLink),
	_('Applications')
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

	$itemTable->addRow(array(
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
$goOption->setAttribute('confirm', _('Enable selected items?'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('disable', _('Disable selected'));
$goOption->setAttribute('confirm', _('Disable selected items?'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('delete', _('Delete selected'));
$goOption->setAttribute('confirm', _('Delete selected items?'));
$goComboBox->addItem($goOption);

$goButton = new CSubmit('goButton', _('Go').' (0)');
$goButton->setAttribute('id', 'goButton');
zbx_add_post_js('chkbxRange.pageGoName = "group_itemid";');

// append table to form
$itemForm->addItem(array($this->data['paging'], $itemTable, $this->data['paging'], get_table_header(array($goComboBox, $goButton))));

// append form to widget
$itemsWidget->addItem($itemForm);
return $itemsWidget;
?>
