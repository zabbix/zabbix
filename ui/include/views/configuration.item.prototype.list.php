<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


/**
 * @var CView $this
 */

require_once dirname(__FILE__).'/js/configuration.item.prototype.list.js.php';

$widget = (new CWidget())
	->setTitle(_('Item prototypes'))
	->setDocUrl(CDocHelper::getUrl($data['context'] === 'host'
		? CDocHelper::CONFIGURATION_HOST_ITEM_PROTOTYPE_LIST
		: CDocHelper::CONFIGURATION_TEMPLATES_ITEM_PROTOTYPE_LIST
	))
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(
					new CRedirectButton(_('Create item prototype'),
						(new CUrl('disc_prototypes.php'))
							->setArgument('form', 'create')
							->setArgument('parent_discoveryid', $data['parent_discoveryid'])
							->setArgument('context', $data['context'])
					)
				)
		))->setAttribute('aria-label', _('Content controls'))
	)
	->setNavigation(getHostNavigation('items', $data['hostid'], $data['parent_discoveryid']));

$url = (new CUrl('disc_prototypes.php'))
	->setArgument('parent_discoveryid', $data['parent_discoveryid'])
	->setArgument('context', $data['context'])
	->getUrl();

// create form
$itemForm = (new CForm('post', $url))
	->setName('items')
	->addVar('parent_discoveryid', $data['parent_discoveryid'], 'form_parent_discoveryid')
	->addVar('context', $data['context'], 'form_context');

// create table
$itemTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_items'))->onClick("checkAll('".$itemForm->getName()."', 'all_items', 'group_itemid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		'',
		make_sorting_header(_('Name'),'name', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Key'), 'key_', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Interval'), 'delay', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('History'), 'history', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Trends'), 'trends', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Type'), 'type', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Create enabled'), 'status', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Discover'), 'discover', $data['sort'], $data['sortorder'], $url),
		_('Tags')
	]);

$update_interval_parser = new CUpdateIntervalParser(['usermacros' => true, 'lldmacros' => true]);

foreach ($data['items'] as $item) {
	$description = [];
	$description[] = makeItemTemplatePrefix($item['itemid'], $data['parent_templates'], ZBX_FLAG_DISCOVERY_PROTOTYPE,
		$data['allowed_ui_conf_templates']
	);

	if ($item['type'] == ITEM_TYPE_DEPENDENT) {
		if ($item['master_item']['type'] == ITEM_TYPE_HTTPTEST) {
			$description[] = CHtml::encode($item['master_item']['name']);
		}
		else {
			$link = ($item['master_item']['source'] === 'itemprototypes')
				? (new CUrl('disc_prototypes.php'))
					->setArgument('parent_discoveryid', $data['parent_discoveryid'])
					->setArgument('context', $data['context'])
				: (new CUrl('items.php'))
					->setArgument('filter_set', '1')
					->setArgument('filter_hostids', [$item['hostid']])
					->setArgument('context', $data['context']);

			$description[] = (new CLink(CHtml::encode($item['master_item']['name']),
				$link
					->setArgument('form', 'update')
					->setArgument('itemid', $item['master_item']['itemid'])
					->setArgument('context', $data['context'])
					->getUrl()
			))
				->addClass(ZBX_STYLE_LINK_ALT)
				->addClass(ZBX_STYLE_TEAL);
		}

		$description[] = NAME_DELIMITER;
	}

	$description[] = new CLink(
		$item['name'],
		(new CUrl('disc_prototypes.php'))
			->setArgument('form', 'update')
			->setArgument('parent_discoveryid', $data['parent_discoveryid'])
			->setArgument('itemid', $item['itemid'])
			->setArgument('context', $data['context'])
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
			->setArgument('context', $data['context'])
			->getUrl()
	))
		->addClass(ZBX_STYLE_LINK_ACTION)
		->addClass(itemIndicatorStyle($item['status']))
		->addSID();

	if (in_array($item['value_type'], [ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT])) {
		$item['trends'] = '';
	}

	// Hide zeros for trapper, SNMP trap and dependent items.
	if ($item['type'] == ITEM_TYPE_TRAPPER || $item['type'] == ITEM_TYPE_SNMPTRAP
			|| $item['type'] == ITEM_TYPE_DEPENDENT
			|| ($item['type'] == ITEM_TYPE_ZABBIX_ACTIVE && strncmp($item['key_'], 'mqtt.get', 8) === 0)) {
		$item['delay'] = '';
	}
	elseif ($update_interval_parser->parse($item['delay']) == CParser::PARSE_SUCCESS) {
		$item['delay'] = $update_interval_parser->getDelay();
	}

	$item_menu = CMenuPopupHelper::getItemPrototypeConfiguration([
		'itemid' => $item['itemid'],
		'context' => $data['context'],
		'backurl' => (new CUrl('disc_prototypes.php'))
			->setArgument('parent_discoveryid', $data['parent_discoveryid'])
			->setArgument('context', $data['context'])
			->getUrl()
	]);

	$wizard = (new CButton(null))
		->addClass(ZBX_STYLE_ICON_WIZARD_ACTION)
		->setMenuPopup($item_menu);

	$nodiscover = ($item['discover'] == ZBX_PROTOTYPE_NO_DISCOVER);
	$discover = (new CLink($nodiscover ? _('No') : _('Yes'),
			(new CUrl('disc_prototypes.php'))
				->setArgument('group_itemid[]', $item['itemid'])
				->setArgument('parent_discoveryid', $data['parent_discoveryid'])
				->setArgument('action', $nodiscover
					? 'itemprototype.massdiscover.enable'
					: 'itemprototype.massdiscover.disable'
				)
				->setArgument('context', $data['context'])
				->getUrl()
		))
			->addSID()
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass($nodiscover ? ZBX_STYLE_RED : ZBX_STYLE_GREEN);

	$itemTable->addRow([
		new CCheckBox('group_itemid['.$item['itemid'].']', $item['itemid']),
		$wizard,
		$description,
		(new CDiv(CHtml::encode($item['key_'])))->addClass(ZBX_STYLE_WORDWRAP),
		$item['delay'],
		$item['history'],
		$item['trends'],
		item_type2str($item['type']),
		$status,
		$discover,
		$data['tags'][$item['itemid']]
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
			'popup.massupdate.itemprototype' => [
				'content' => (new CButton('', _('Mass update')))
					->onClick(
						"openMassupdatePopup('popup.massupdate.itemprototype', {}, {
							dialogue_class: 'modal-popup-preprocessing',
							trigger_element: this
						});"
					)
					->addClass(ZBX_STYLE_BTN_ALT)
					->removeAttribute('id')
			],
			'itemprototype.massdelete' => ['name' => _('Delete'),
				'confirm' => _('Delete selected item prototypes?')
			]
		],
		$data['parent_discoveryid']
	)
]);

// append form to widget
$widget->addItem($itemForm);

$widget->show();
