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


require_once dirname(__FILE__).'/js/configuration.item.list.js.php';

$itemsWidget = new CWidget(null, 'item-list');

// create new item button
$createForm = new CForm('get');
$createForm->cleanItems();

if (empty($this->data['hostid'])) {
	$createButton = new CSubmit('form', _('Create item (select host first)'));
	$createButton->setEnabled(false);
	$createForm->addItem($createButton);
}
else {
	$createForm->addVar('hostid', $this->data['hostid']);
	$createForm->addItem(new CSubmit('form', _('Create item')));
}
$itemsWidget->addPageHeader(_('CONFIGURATION OF ITEMS'), $createForm);

// header
$itemsWidget->addHeader(_('Items'));
$itemsWidget->addHeaderRowNumber();

if (!empty($this->data['hostid'])) {
	$itemsWidget->addItem(get_header_host_table('items', $this->data['hostid']));
}
$itemsWidget->addFlicker($this->data['flicker'], CProfile::get('web.items.filter.state', 0));

// create form
$itemForm = new CForm();
$itemForm->setName('items');
if (!empty($this->data['hostid'])) {
	$itemForm->addVar('hostid', $this->data['hostid']);
}

// create table
$itemTable = new CTableInfo(_('No items found.'));
$itemTable->setHeader(array(
	new CCheckBox('all_items', null, "checkAll('".$itemForm->getName()."', 'all_items', 'group_itemid');"),
	_('Wizard'),
	empty($this->data['filter_hostid']) ? _('Host') : null,
	make_sorting_header(_('Name'), 'name'),
	_('Triggers'),
	make_sorting_header(_('Key'), 'key_'),
	make_sorting_header(_('Interval'), 'delay'),
	make_sorting_header(_('History'), 'history'),
	make_sorting_header(_('Trends'), 'trends'),
	make_sorting_header(_('Type'), 'type'),
	_('Applications'),
	make_sorting_header(_('Status'), 'status'),
	$data['showInfoColumn'] ? _('Info') : null
));

foreach ($this->data['items'] as $item) {
	// description
	$description = array();
	if (!empty($item['template_host'])) {
		$description[] = new CLink(
			CHtml::encode($item['template_host']['name']),
			'?hostid='.$item['template_host']['hostid'].'&filter_set=1',
			'unknown'
		);
		$description[] = NAME_DELIMITER;
	}

	if (!empty($item['discoveryRule'])) {
		$description[] = new CLink(
			CHtml::encode($item['discoveryRule']['name']),
			'disc_prototypes.php?parent_discoveryid='.$item['discoveryRule']['itemid'],
			'parent-discovery'
		);
		$description[] = NAME_DELIMITER.$item['name_expanded'];
	}
	else {
		$description[] = new CLink(
			CHtml::encode($item['name_expanded']),
			'?form=update&hostid='.$item['hostid'].'&itemid='.$item['itemid']
		);
	}

	// status
	$status = new CCol(new CLink(
		itemIndicator($item['status'], $item['state']),
		'?group_itemid='.$item['itemid'].'&hostid='.$item['hostid'].'&go='.($item['status'] ? 'activate' : 'disable'),
		itemIndicatorStyle($item['status'], $item['state'])
	));

	// info
	if ($data['showInfoColumn']) {
		$infoIcons = array();

		if ($item['status'] == ITEM_STATUS_ACTIVE && !zbx_empty($item['error'])) {
			$info = new CDiv(SPACE, 'status_icon iconerror');
			$info->setHint($item['error'], '', 'on');

			$infoIcons[] = $info;
		}

		// discovered item lifetime indicator
		if ($item['flags'] == ZBX_FLAG_DISCOVERY_CREATED && $item['itemDiscovery']['ts_delete']) {
			$deleteError = new CDiv(SPACE, 'status_icon iconwarning');
			$deleteError->setHint(_s(
				'The item is not discovered anymore and will be deleted in %1$s (on %2$s at %3$s).',
				zbx_date2age($item['itemDiscovery']['ts_delete']),
				zbx_date2str(DATE_FORMAT, $item['itemDiscovery']['ts_delete']),
				zbx_date2str(TIME_FORMAT, $item['itemDiscovery']['ts_delete'])
			));

			$infoIcons[] = $deleteError;
		}

		if (!$infoIcons) {
			$infoIcons[] = '';
		}
	}
	else {
		$infoIcons = null;
	}

	// triggers info
	$triggerHintTable = new CTableInfo();
	$triggerHintTable->setHeader(array(
		_('Severity'),
		_('Name'),
		_('Expression'),
		_('Status')
	));

	foreach ($item['triggers'] as $num => &$trigger) {
		$trigger = $this->data['itemTriggers'][$trigger['triggerid']];
		$triggerDescription = array();
		if ($trigger['templateid'] > 0) {
			if (!isset($this->data['triggerRealHosts'][$trigger['triggerid']])) {
				$triggerDescription[] = new CSpan('HOST', 'unknown');
				$triggerDescription[] = ':';
			}
			else {
				$realHost = reset($this->data['triggerRealHosts'][$trigger['triggerid']]);
				$triggerDescription[] = new CLink(
					CHtml::encode($realHost['name']),
					'triggers.php?hostid='.$realHost['hostid'],
					'unknown'
				);
				$triggerDescription[] = ':';
			}
		}

		$trigger['hosts'] = zbx_toHash($trigger['hosts'], 'hostid');

		if ($trigger['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
			$triggerDescription[] = new CSpan(CHtml::encode($trigger['description']));
		}
		else {
			$triggerDescription[] = new CLink(
				CHtml::encode($trigger['description']),
				'triggers.php?form=update&hostid='.key($trigger['hosts']).'&triggerid='.$trigger['triggerid']
			);
		}

		if ($trigger['state'] == TRIGGER_STATE_UNKNOWN) {
			$trigger['error'] = '';
		}

		$trigger['items'] = zbx_toHash($trigger['items'], 'itemid');
		$trigger['functions'] = zbx_toHash($trigger['functions'], 'functionid');

		$triggerHintTable->addRow(array(
			getSeverityCell($trigger['priority']),
			$triggerDescription,
			triggerExpression($trigger, true),
			new CSpan(
				triggerIndicator($trigger['status'], $trigger['state']),
				triggerIndicatorStyle($trigger['status'], $trigger['state'])
			),
		));

		$item['triggers'][$num] = $trigger;
	}
	unset($trigger);

	if (!empty($item['triggers'])) {
		$triggerInfo = new CSpan(_('Triggers'), 'link_menu');
		$triggerInfo->setHint($triggerHintTable);
		$triggerInfo = array($triggerInfo);
		$triggerInfo[] = ' ('.count($item['triggers']).')';

		$triggerHintTable = array();
	}
	else {
		$triggerInfo = SPACE;
	}

	// if item type is 'Log' we must show log menu
	if (in_array($item['value_type'], array(ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_TEXT))) {
		$triggers = array();

		foreach ($item['triggers'] as $trigger) {
			foreach ($trigger['functions'] as $function) {
				if (!str_in_array($function['function'], array('regexp', 'iregexp'))) {
					continue 2;
				}
			}

			$triggers[] = array(
				'id' => $trigger['triggerid'],
				'name' => $trigger['description']
			);
		}

		$menuIcon = new CIcon(_('Menu'), 'iconmenu_b');
		$menuIcon->setMenuPopup(CMenuPopupHelper::getTriggerLog($item['itemid'], $item['name'], $triggers));
	}
	else {
		$menuIcon = SPACE;
	}

	$checkBox = new CCheckBox('group_itemid['.$item['itemid'].']', null, null, $item['itemid']);
	$checkBox->setEnabled(empty($item['discoveryRule']));

	$itemTable->addRow(array(
		$checkBox,
		$menuIcon,
		empty($this->data['filter_hostid']) ? $item['host'] : null,
		$description,
		$triggerInfo,
		CHtml::encode($item['key_']),
		$item['type'] == ITEM_TYPE_TRAPPER || $item['type'] == ITEM_TYPE_SNMPTRAP ? '' : $item['delay'],
		$item['history'],
		in_array($item['value_type'], array(ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT)) ? '' : $item['trends'],
		item_type2str($item['type']),
		new CCol(CHtml::encode($item['applications_list']), 'wraptext'),
		$status,
		$infoIcons
	));
}

// create go buttons
$goComboBox = new CComboBox('go');
$goOption = new CComboItem('activate', _('Enable selected'));
$goOption->setAttribute('confirm', _('Enable selected items?'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('disable', _('Disable selected'));
$goOption->setAttribute('confirm', _('Disable selected items?'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('massupdate', _('Mass update'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('copy_to', _('Copy selected to ...'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('clean_history', _('Clear history for selected'));
$goOption->setAttribute('confirm', _('Delete history of selected items?'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('delete', _('Delete selected'));
$goOption->setAttribute('confirm', _('Delete selected items?'));
$goComboBox->addItem($goOption);

$goButton = new CSubmit('goButton', _('Go').' (0)');
$goButton->setAttribute('id', 'goButton');

zbx_add_post_js('chkbxRange.pageGoName = "group_itemid";');
zbx_add_post_js('chkbxRange.prefix = "'.$this->data['hostid'].'";');
zbx_add_post_js('cookie.prefix = "'.$this->data['hostid'].'";');

// append table to form
$itemForm->addItem(array($this->data['paging'], $itemTable, $this->data['paging'], get_table_header(array($goComboBox, $goButton))));

// append form to widget
$itemsWidget->addItem($itemForm);

return $itemsWidget;
