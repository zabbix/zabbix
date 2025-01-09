<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * @var CView $this
 * @var array $data
 */

$this->addJsFile('multilineinput.js');
$this->addJsFile('items.js');
$this->addJsFile('class.tagfilteritem.js');
$this->includeJsFile('item.list.js.php', $data);

$filter = new CPartial('item.list.filter', [
	'action' => $data['action'],
	'context' => $data['context'],
	'filter_data' => $data['filter_data'],
	'subfilter' => $data['subfilter'],
	'filtered_count' => $data['filtered_count'],
	'types' => $data['types']
]);

$form = (new CForm())
	->setName('item_list')
	->addVar('context', $data['context'], uniqid('item_'))
	->addVar('hostid', $data['hostid'] != 0 ? $data['hostid'] : null);

$list_url = (new CUrl('zabbix.php'))
	->setArgument('action', $data['action'])
	->setArgument('context', $data['context'])
	->getUrl();

$header = [
	(new CColHeader(
		(new CCheckBox('all_items'))->onClick("checkAll('item_list', 'all_items', 'itemids');")
	))->addClass(ZBX_STYLE_CELL_WIDTH),
	'',
	($data['hostid'] != 0)
		? null
		: ($data['context'] === 'host' ? _('Host') : _('Template')),
	make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $list_url),
	_('Triggers'),
	make_sorting_header(_('Key'), 'key_', $data['sort'], $data['sortorder'], $list_url),
	make_sorting_header(_('Interval'), 'delay', $data['sort'], $data['sortorder'], $list_url),
	make_sorting_header(_('History'), 'history', $data['sort'], $data['sortorder'], $list_url),
	make_sorting_header(_('Trends'), 'trends', $data['sort'], $data['sortorder'], $list_url),
	make_sorting_header(_('Type'), 'type', $data['sort'], $data['sortorder'], $list_url),
	make_sorting_header(_('Status'), 'status', $data['sort'], $data['sortorder'], $list_url),
	_('Tags'),
	($data['context'] === 'host') ? _('Info') : null
];

$item_list = (new CTableInfo())
	->setHeader($header)
	->setPageNavigation($data['paging']);

$now_ts = time();
$update_interval_parser = new CUpdateIntervalParser(['usermacros' => true]);

foreach ($data['items'] as $item) {
	$name = makeItemTemplatePrefix($item['itemid'], $data['parent_templates'], ZBX_FLAG_DISCOVERY_NORMAL,
		$data['allowed_ui_conf_templates']
	);

	$item_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'popup')
		->setArgument('popup', 'item.edit')
		->setArgument('context', $data['context'])
		->setArgument('itemid', $item['itemid'])
		->getUrl();

	if ($item['discoveryRule']) {
		$name[] = (new CLink($item['discoveryRule']['name'],
			(new CUrl('zabbix.php'))
				->setArgument('action', 'item.prototype.list')
				->setArgument('parent_discoveryid', $item['discoveryRule']['itemid'])
				->setArgument('context', $data['context'])
		))
			->addClass(ZBX_STYLE_LINK_ALT)
			->addClass(ZBX_STYLE_ORANGE);
		$name[] = NAME_DELIMITER;
	}

	if ($item['type'] == ITEM_TYPE_DEPENDENT) {
		if ($item['master_item']['type'] == ITEM_TYPE_HTTPTEST) {
			$name[] = $item['master_item']['name'];
		}
		else {
			$name[] = (new CLink($item['master_item']['name'], $item_url))
				->addClass(ZBX_STYLE_LINK_ALT)
				->addClass(ZBX_STYLE_TEAL)
				->setAttribute('data-itemid', $item['master_item']['itemid'])
				->setAttribute('data-context', $data['context'])
				->setAttribute('data-action', 'item.edit');
		}

		$name[] = NAME_DELIMITER;
	}

	$name[] = (new CLink($item['name'], $item_url))
		->setAttribute('data-itemid', $item['itemid'])
		->setAttribute('data-context', $data['context'])
		->setAttribute('data-action', 'item.edit');

	// Trigger information
	$hint_table = (new CTableInfo())->setHeader([_('Severity'), _('Name'), _('Expression'), _('Status')]);

	foreach ($item['triggers'] as $trigger) {
		$trigger = $data['triggers'][$trigger['triggerid']];

		$trigger_url = (new CUrl('zabbix.php'))
			->setArgument('action', 'popup')
			->setArgument('popup', 'trigger.edit')
			->setArgument('triggerid', $trigger['triggerid'])
			->setArgument('hostid', key($trigger['hosts']))
			->setArgument('context', $data['context'])
			->getUrl();

		$hint_table->addRow([
			CSeverityHelper::makeSeverityCell((int) $trigger['priority']),
			[
				makeTriggerTemplatePrefix($trigger['triggerid'], $data['trigger_parent_templates'],
					ZBX_FLAG_DISCOVERY_NORMAL, $data['allowed_ui_conf_templates']
				),
				(new CLink($trigger['description'], $trigger_url))
					->setAttribute('data-action', 'trigger.edit')
					->setAttribute('data-hostid', key($trigger['hosts']))
					->setAttribute('data-triggerid', $trigger['triggerid'])
					->setAttribute('data-context', $data['context'])
			],
			(new CDiv(
				$trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION
					? [
						_('Problem'), ': ', $trigger['expression'], BR(),
						_('Recovery'), ': ', $trigger['recovery_expression']
					]
					: $trigger['expression']
			))->addClass(ZBX_STYLE_WORDBREAK),
			(new CSpan(triggerIndicator($trigger['status'], $trigger['state'])))
				->addClass(triggerIndicatorStyle($trigger['status'], $trigger['state']))
		]);
	}

	// Interval
	if (in_array($item['type'], [ITEM_TYPE_TRAPPER, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_DEPENDENT])
			|| ($item['type'] == ITEM_TYPE_ZABBIX_ACTIVE && strpos($item['key_'], 'mqtt.get') === 0)) {
		$item['delay'] = '';
	}
	elseif ($update_interval_parser->parse($item['delay']) == CParser::PARSE_SUCCESS) {
		$item['delay'] = $update_interval_parser->getDelay();
	}

	// Trends
	if (in_array($item['value_type'], [ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT,
			ITEM_VALUE_TYPE_BINARY])) {
		$item['trends'] = '';
	}

	$disable_source = $item['status'] == ITEM_STATUS_DISABLED && $item['itemDiscovery']
		? $item['itemDiscovery']['disable_source']
		: '';

	// Info
	$info_cell = null;

	if ($data['context'] === 'host') {
		$info_cell = [];

		if ($item['status'] == ITEM_STATUS_ACTIVE && $item['error'] !== '') {
			$info_cell[] = makeErrorIcon($item['error']);
		}


		if ($item['flags'] == ZBX_FLAG_DISCOVERY_CREATED && $item['itemDiscovery']['status'] == ZBX_LLD_STATUS_LOST) {
			$info_cell[] = getLldLostEntityIndicator(time(), $item['itemDiscovery']['ts_delete'],
				$item['itemDiscovery']['ts_disable'], $disable_source, $item['status'] == ITEM_STATUS_DISABLED,
				_('item')
			);
		}

		$info_cell = makeInformationList($info_cell);
	}

	$can_execute = in_array($item['type'], $data['check_now_types']) && $item['status'] == ITEM_STATUS_ACTIVE
		&& $item['hosts'][0]['status'] == HOST_STATUS_MONITORED;

	$status = (new CLink(itemIndicator($item['status'], $item['state'])))
		->addClass(ZBX_STYLE_LINK_ACTION)
		->addClass(itemIndicatorStyle($item['status'], $item['state']))
		->addClass($item['status'] == ITEM_STATUS_DISABLED ? 'js-enable-item' : 'js-disable-item')
		->setAttribute('data-itemid', $item['itemid']);

	$disabled_by_lld = $disable_source == ZBX_DISABLE_SOURCE_LLD;

	$host_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'popup')
		->setArgument('popup', $data['context'] === 'host' ? 'host.edit' : 'template.edit')
		->setArgument($data['context'] === 'host' ? 'hostid' : 'templateid', $item['hosts'][0]['hostid'])
		->getUrl();

	$host = $data['hostid'] == 0
		? (new CLink($item['hosts'][0]['name'], $host_url))
			->setAttribute($data['context'] === 'host' ? 'data-hostid' : 'data-templateid', $item['hosts'][0]['hostid'])
			->setAttribute('data-action', $data['context'] === 'host' ? 'host.edit' : 'template.edit')
		: null;

	$row = [
		(new CCheckBox('itemids['.$item['itemid'].']', $item['itemid']))
			->setAttribute('data-actions', $can_execute ? 'execute' : null),
		(new CButtonIcon(ZBX_ICON_MORE))
			->setMenuPopup(
				CMenuPopupHelper::getItem([
					'itemid' => $item['itemid'],
					'context' => $data['context'],
					'backurl' => $list_url
				])
			),
		$host,
		(new CCol($name))->addClass(ZBX_STYLE_WORDBREAK),
		$item['triggers']
			? [
				(new CLinkAction(_('Triggers')))->setHint($hint_table),
				CViewHelper::showNum($hint_table->getNumRows())
			]
			: '',
		(new CDiv($item['key_']))->addClass(ZBX_STYLE_WORDWRAP),
		$item['delay'],
		$item['history'],
		$item['trends'],
		item_type2str($item['type']),
		(new CDiv([
			$status,
			$disabled_by_lld ? makeDescriptionIcon(_('Disabled automatically by an LLD rule.')) : null
		]))->addClass(ZBX_STYLE_NOWRAP),
		$data['tags'][$item['itemid']],
		$info_cell
	];

	$item_list->addRow($row);
}

$form->addItem($item_list);

$buttons = [
	[
		'content' => (new CSimpleButton(_('Enable')))
			->addClass(ZBX_STYLE_BTN_ALT)
			->addClass('js-massenable-item')
			->addClass('js-no-chkbxrange')
	],
	[
		'content' => (new CSimpleButton(_('Disable')))
			->addClass(ZBX_STYLE_BTN_ALT)
			->addClass('js-massdisable-item')
			->addClass('js-no-chkbxrange')
	],
	'execute' => [
		'content' => (new CSimpleButton(_('Execute now')))
			->addClass(ZBX_STYLE_BTN_ALT)
			->addClass('js-massexecute-item')
			->addClass('js-no-chkbxrange')
			->setAttribute('data-required', 'execute')
	],
	'clearhistory' => [
		'content' => (new CSimpleButton(_('Clear history and trends')))
			->addClass(ZBX_STYLE_BTN_ALT)
			->addClass('js-massclearhistory-item')
			->addClass('js-no-chkbxrange')
	],
	[
		'content' => (new CSimpleButton(_('Copy')))
			->addClass(ZBX_STYLE_BTN_ALT)
			->addClass('js-masscopy-item')
			->addClass('js-no-chkbxrange')
	],
	[
		'content' => (new CSimpleButton(_('Mass update')))
			->addClass(ZBX_STYLE_BTN_ALT)
			->addClass('js-massupdate-item')
			->addClass('js-no-chkbxrange')
	],
	[
		'content' => (new CSimpleButton(_('Delete')))
			->addClass(ZBX_STYLE_BTN_ALT)
			->addClass('js-massdelete-item')
			->addClass('js-no-chkbxrange')
	]
];

if ($data['context'] === 'template') {
	unset($buttons['execute'], $buttons['clearhistory']);
}

$form->addItem(new CActionButtonList('action', 'itemids', $buttons,
	'items_'.(array_key_exists('hostid', $data) ? $data['hostid'] : 0))
);

(new CHtmlPage())
	->setTitle(_('Items'))
	->setDocUrl(CDocHelper::getUrl($data['context'] === 'host'
		? CDocHelper::DATA_COLLECTION_HOST_ITEM_LIST
		: CDocHelper::DATA_COLLECTION_TEMPLATE_ITEM_LIST
	))
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(
					$data['hostid'] != 0
						? (new CSimpleButton(_('Create item')))
								->setAttribute('data-hostid', $data['hostid'])
								->setAttribute('data-context', $data['context'])
								->addClass('js-create-item')
						: (new CSimpleButton(
							$data['context'] === 'host'
								? _('Create item (select host first)')
								: _('Create item (select template first)')
						))->setEnabled(false)
				)
		))->setAttribute('aria-label', _('Content controls'))
	)
	->setNavigation(
		$data['hostid'] != 0
			? getHostNavigation('items', $data['hostid'])
			: null
	)
	->addItem($filter)
	->addItem($form)
	->show();

$confirm_messages = [
	'item.enable' => [_('Enable selected item?'), _('Enable selected items?')],
	'item.disable' => [_('Disable selected item?'), _('Disable selected items?')],
	'item.clear' => $data['context'] === 'host' && !CHousekeepingHelper::get(CHousekeepingHelper::COMPRESSION_STATUS)
		? [_('Clear history and trends of selected item?'), _('Clear history and trends of selected items?')]
		: [],
	'item.delete' => [_('Delete selected item?'), _('Delete selected items?')]
];

(new CScriptTag('
	view.init('.json_encode([
		'context' => $data['context'],
		'confirm_messages' => $confirm_messages,
		'field_switches' => CItemData::filterSwitchingConfiguration(),
		'form_name' => $form->getName(),
		'hostids' => $data['filter_data']['filter_hostids'],
		'token' => [CSRF_TOKEN_NAME => CCsrfTokenHelper::get('item')]
	]).');
'))
	->setOnDocumentReady()
	->show();
