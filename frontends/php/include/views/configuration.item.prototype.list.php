<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
				(new CUrl('disc_prototypes.php'))
					->setArgument('form', 'create')
					->setArgument('parent_discoveryid', $data['parent_discoveryid'])
					->getUrl()
			))
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem(get_header_host_table('items', $data['hostid'], $data['parent_discoveryid']));

// create form
$itemForm = (new CForm())
	->setName('items')
	->addVar('parent_discoveryid', $data['parent_discoveryid']);

$url = (new CUrl('disc_prototypes.php'))
	->setArgument('parent_discoveryid', $data['parent_discoveryid'])
	->getUrl();

// create table
$itemTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_items'))->onClick("checkAll('".$itemForm->getName()."', 'all_items', 'group_itemid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		_('Wizard'),
		make_sorting_header(_('Name'),'name', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Key'), 'key_', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Interval'), 'delay', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('History'), 'history', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Trends'), 'trends', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Type'), 'type', $data['sort'], $data['sortorder'], $url),
		_('Applications'),
		make_sorting_header(_('Create enabled'), 'status', $data['sort'], $data['sortorder'], $url)
	]);

$update_interval_parser = new CUpdateIntervalParser(['usermacros' => true, 'lldmacros' => true]);

foreach ($data['items'] as $item) {
	$description = [];
	$description[] = makeItemTemplatePrefix($item['itemid'], $data['parent_templates'], ZBX_FLAG_DISCOVERY_PROTOTYPE);

	if ($item['type'] == ITEM_TYPE_DEPENDENT) {
		if ($item['master_item']['type'] == ITEM_TYPE_HTTPTEST) {
			$description[] = CHtml::encode($item['master_item']['name_expanded']);
		}
		else {
			$link = ($item['master_item']['source'] === 'itemprototypes')
				? (new CUrl('disc_prototypes.php'))->setArgument('parent_discoveryid', $data['parent_discoveryid'])
				: (new CUrl('items.php'))
					->setArgument('filter_set', '1')
					->setArgument('filter_hostids', [$item['hostid']]);

			$description[] = (new CLink(CHtml::encode($item['master_item']['name_expanded']),
				$link
					->setArgument('form', 'update')
					->setArgument('itemid', $item['master_item']['itemid'])
					->getUrl()
			))
				->addClass(ZBX_STYLE_LINK_ALT)
				->addClass(ZBX_STYLE_TEAL);
		}

		$description[] = NAME_DELIMITER;
	}

	$description[] = new CLink(
		$item['name_expanded'],
		(new CUrl('disc_prototypes.php'))
			->setArgument('form', 'update')
			->setArgument('parent_discoveryid', $data['parent_discoveryid'])
			->setArgument('itemid', $item['itemid'])
			->getUrl()
	);

	$status = (new CLink(
		($item['status'] == ITEM_STATUS_DISABLED) ? _('No') : _('Yes'),
		(new CUrl('disc_prototypes.php'))
			->setArgument('group_itemid[]', $item['itemid'])
			->setArgument('parent_discoveryid', $data['parent_discoveryid'])
			->setArgument('action', ($item['status'] == ITEM_STATUS_DISABLED)
				? 'itemprototype.massenable'
				: 'itemprototype.massdisable'
			)
			->getUrl()
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

	// Hide zeros for trapper, SNMP trap and dependent items.
	if ($item['type'] == ITEM_TYPE_TRAPPER || $item['type'] == ITEM_TYPE_SNMPTRAP
			|| $item['type'] == ITEM_TYPE_DEPENDENT) {
		$item['delay'] = '';
	}
	elseif ($update_interval_parser->parse($item['delay']) == CParser::PARSE_SUCCESS) {
		$item['delay'] = $update_interval_parser->getDelay();
	}

	$item_menu = CMenuPopupHelper::getItemPrototype($item['itemid'], $data['parent_discoveryid']);

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

// append table to form
$itemForm->addItem([
	$itemTable,
	$data['paging'],
	new CActionButtonList('action', 'group_itemid',
		[
			'itemprototype.massenable' => ['name' => _('Create enabled'),
				'confirm' => _('Create items from selected prototypes as enabled?')
			],
			'itemprototype.massdisable' => ['name' => _('Create disabled'),
				'confirm' => _('Create items from selected prototypes as disabled?')
			],
			'itemprototype.massupdateform' => ['name' => _('Mass update')],
			'itemprototype.massdelete' => ['name' => _('Delete'),
				'confirm' => _('Delete selected item prototypes?')
			]
		],
		$data['parent_discoveryid']
	)
]);

// append form to widget
$widget->addItem($itemForm);

return $widget;
