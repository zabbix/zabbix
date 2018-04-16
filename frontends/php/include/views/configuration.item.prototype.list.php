<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


$widget = (new CWidget())
	->setTitle(_('Item prototypes'))
	->setControls(
		(new CTag('nav', true,
			(new CList())->addItem(new CRedirectButton(_('Create item prototype'),
				(new CUrl())
					->setArgument('form', 'create')
					->setArgument('parent_discoveryid', $data['parent_discoveryid'])
					->getUrl()
			))
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem(get_header_host_table('items', $this->data['hostid'], $this->data['parent_discoveryid']));

// create form
$itemForm = (new CForm())
	->setName('items')
	->addVar('parent_discoveryid', $this->data['parent_discoveryid']);

// create table
$itemTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_items'))->onClick("checkAll('".$itemForm->getName()."', 'all_items', 'group_itemid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		_('Wizard'),
		make_sorting_header(_('Name'),'name', $this->data['sort'], $this->data['sortorder']),
		make_sorting_header(_('Key'), 'key_', $this->data['sort'], $this->data['sortorder']),
		make_sorting_header(_('Interval'), 'delay', $this->data['sort'], $this->data['sortorder']),
		make_sorting_header(_('History'), 'history', $this->data['sort'], $this->data['sortorder']),
		make_sorting_header(_('Trends'), 'trends', $this->data['sort'], $this->data['sortorder']),
		make_sorting_header(_('Type'), 'type', $this->data['sort'], $this->data['sortorder']),
		_('Applications'),
		make_sorting_header(_('Create enabled'), 'status', $this->data['sort'], $this->data['sortorder'])
	]);

$update_interval_parser = new CUpdateIntervalParser(['usermacros' => true, 'lldmacros' => true]);

foreach ($this->data['items'] as $item) {
	$description = [];
	if (!empty($item['templateid'])) {
		$template_host = get_realhost_by_itemid($item['templateid']);
		$templateDiscoveryRuleId = get_realrule_by_itemid_and_hostid($this->data['parent_discoveryid'], $template_host['hostid']);

		$description[] = (new CLink($template_host['name'], '?parent_discoveryid='.$templateDiscoveryRuleId))
			->addClass(ZBX_STYLE_LINK_ALT)
			->addClass(ZBX_STYLE_GREY);
		$description[] = NAME_DELIMITER;
	}
	if ($item['type'] == ITEM_TYPE_DEPENDENT) {
		$description[] = (new CLink(CHtml::encode($item['master_item']['name_expanded']),
			'?form=update&parent_discoveryid='.$data['parent_discoveryid'].'&itemid='.$item['master_item']['itemid']
		))
			->addClass(ZBX_STYLE_LINK_ALT)
			->addClass(ZBX_STYLE_TEAL);
		$description[] = NAME_DELIMITER;
	}

	$description[] = new CLink(
		$item['name_expanded'],
		'?form=update&itemid='.$item['itemid'].'&parent_discoveryid='.$this->data['parent_discoveryid']
	);

	$status = (new CLink(
		($item['status'] == ITEM_STATUS_DISABLED) ? _('No') : _('Yes'),
		'?group_itemid[]='.$item['itemid'].
			'&parent_discoveryid='.$this->data['parent_discoveryid'].
			'&action='.(($item['status'] == ITEM_STATUS_DISABLED)
				? 'itemprototype.massenable'
				: 'itemprototype.massdisable'
			)
	))
		->addClass(ZBX_STYLE_LINK_ACTION)
		->addClass(itemIndicatorStyle($item['status']))
		->addSID();

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

	if (in_array($item['value_type'], [ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT])) {
		$item['trends'] = '';
	}

	// Hide zeroes for trapper, SNMP trap and dependent items.
	if ($item['type'] == ITEM_TYPE_TRAPPER || $item['type'] == ITEM_TYPE_SNMPTRAP
			|| $item['type'] == ITEM_TYPE_DEPENDENT) {
		$item['delay'] = '';
	}
	elseif ($update_interval_parser->parse($item['delay']) == CParser::PARSE_SUCCESS) {
		$item['delay'] = $update_interval_parser->getDelay();
	}

	$item_menu = CMenuPopupHelper::getDependentItemPrototype($item['itemid'], $data['parent_discoveryid'],
		$item['name']
	);

	$wizard = (new CSpan(
		(new CButton(null))->addClass(ZBX_STYLE_ICON_WZRD_ACTION)->setMenuPopup($item_menu)
	))->addClass(ZBX_STYLE_REL_CONTAINER);

	$itemTable->addRow([
		new CCheckBox('group_itemid['.$item['itemid'].']', $item['itemid']),
		$wizard,
		$description,
		$item['key_'],
		$item['delay'],
		$item['history'],
		$item['trends'],
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
			'itemprototype.massenable' => ['name' => _('Create enabled'),
				'confirm' => _('Enable selected item prototypes?')
			],
			'itemprototype.massdisable' => ['name' => _('Create disabled'),
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
$widget->addItem($itemForm);

return $widget;
