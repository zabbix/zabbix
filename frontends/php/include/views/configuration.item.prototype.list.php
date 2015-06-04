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


$itemsWidget = new CWidget();
$itemsWidget->setTitle([
	_('Item prototypes of').SPACE,
	new CSpan($this->data['discovery_rule']['name'], ZBX_STYLE_GREEN)
]);

// create new item button
$createForm = (new CForm('get'))->cleanItems();
$createForm->addVar('parent_discoveryid', $this->data['parent_discoveryid']);

$controls = new CList();
$controls->addItem(new CSubmit('form', _('Create item prototype')));
$createForm->addItem($controls);

$itemsWidget->setControls($createForm);

// header
$itemsWidget->addItem(get_header_host_table('items', $this->data['hostid'], $this->data['parent_discoveryid']));

// create form
$itemForm = new CForm();
$itemForm->setName('items');
$itemForm->addVar('hostid', $this->data['hostid']);
$itemForm->addVar('parent_discoveryid', $this->data['parent_discoveryid']);

// create table
$itemTable = new CTableInfo();

$itemTable->setHeader([
	(new CColHeader(
		new CCheckBox('all_items', null, "checkAll('".$itemForm->getName()."', 'all_items', 'group_itemid');")))->
		addClass('cell-width'),
	make_sorting_header(_('Name'),'name', $this->data['sort'], $this->data['sortorder']),
	make_sorting_header(_('Key'), 'key_', $this->data['sort'], $this->data['sortorder']),
	make_sorting_header(_('Interval'), 'delay', $this->data['sort'], $this->data['sortorder']),
	make_sorting_header(_('History'), 'history', $this->data['sort'], $this->data['sortorder']),
	make_sorting_header(_('Trends'), 'trends', $this->data['sort'], $this->data['sortorder']),
	make_sorting_header(_('Type'), 'type', $this->data['sort'], $this->data['sortorder']),
	_('Applications'),
	make_sorting_header(_('Status'), 'status', $this->data['sort'], $this->data['sortorder'])
]);

foreach ($this->data['items'] as $item) {
	$description = [];
	if (!empty($item['templateid'])) {
		$template_host = get_realhost_by_itemid($item['templateid']);
		$templateDiscoveryRuleId = get_realrule_by_itemid_and_hostid($this->data['parent_discoveryid'], $template_host['hostid']);

		$description[] = new CLink($template_host['name'], '?parent_discoveryid='.$templateDiscoveryRuleId, ZBX_STYLE_LINK_ALT.' '.ZBX_STYLE_GREY);
		$description[] = NAME_DELIMITER;
	}
	$description[] = new CLink(
		$item['name_expanded'],
		'?form=update&itemid='.$item['itemid'].'&parent_discoveryid='.$this->data['parent_discoveryid']
	);

	$status = new CLink(
		itemIndicator($item['status']),
		'?group_itemid='.$item['itemid'].
			'&parent_discoveryid='.$this->data['parent_discoveryid'].
			'&action='.($item['status'] == ITEM_STATUS_DISABLED
				? 'itemprototype.massenable'
				: 'itemprototype.massdisable'
			),
		ZBX_STYLE_LINK_ACTION.' '.itemIndicatorStyle($item['status'])
	);

	if (!empty($item['applications'])) {
		order_result($item['applications'], 'name');

		$applications = zbx_objectValues($item['applications'], 'name');
		$applications = implode(', ', $applications);
		if (empty($applications)) {
			$applications = '';
		}
	}
	else {
		$applications = '';
	}

	$itemTable->addRow([
		new CCheckBox('group_itemid['.$item['itemid'].']', null, null, $item['itemid']),
		$description,
		$item['key_'],
		($item['delay'] !== '') ? convertUnitsS($item['delay']) : '',
		convertUnitsS(SEC_PER_DAY * $item['history']),
		($item['trends'] !== '') ? convertUnitsS(SEC_PER_DAY * $item['trends']) : '',
		item_type2str($item['type']),
		$applications,
		$status
	]);
}

zbx_add_post_js('cookie.prefix = "'.$this->data['parent_discoveryid'].'";');

// append table to form
$itemForm->addItem([
	$itemTable,
	$this->data['paging'],
	new CActionButtonList('action', 'group_itemid',
		[
			'itemprototype.massenable' => ['name' => _('Enable'),
				'confirm' => _('Enable selected item prototypes?')
			],
			'itemprototype.massdisable' => ['name' => _('Disable'),
				'confirm' => _('Disable selected item prototypes?')
			],
			'itemprototype.massdelete' => ['name' => _('Delete'),
				'confirm' => _('Delete selected item prototypes?')
			]
		],
		$this->data['parent_discoveryid']
	)
]);

// append form to widget
$itemsWidget->addItem($itemForm);

return $itemsWidget;
