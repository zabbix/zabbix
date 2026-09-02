<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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

$this->includeJsFile('lldrule.prototype.list.js.php');

$html_page = (new CHtmlPage())
	->setTitle(_('Discovery prototypes'))
	->setDocUrl(CDocHelper::getUrl($data['context'] === 'host'
		? CDocHelper::DATA_COLLECTION_HOST_DISCOVERY_LIST
		: CDocHelper::DATA_COLLECTION_TEMPLATES_DISCOVERY_LIST
	))
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(
					(new CSimpleButton(_('Create discovery prototype')))
						->addClass('js-create-item')
						->setEnabled(!$data['is_parent_discovered'])
				)
		))->setAttribute('aria-label', _('Content controls'))
	)
	->setNavigation(getHostNavigation('lld_prototypes', $data['hostid'], $data['parent_discoveryid']));

$url = (new CUrl('zabbix.php'))
	->setArgument('action', 'lldrule.prototype.list')
	->setArgument('context', $data['context'])
	->setArgument('parent_discoveryid', $data['parent_discoveryid'])
	->getUrl();

// create form
$discoveryForm = (new CForm('post', $url))->setName('discovery_prototype');

if ($data['hostid'] != 0) {
	$discoveryForm->addItem((new CVar('hostid', $data['hostid']))->removeId());
}

// create table
$discoveryTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_items'))->onClick("checkAll('".$discoveryForm->getName()."', 'all_items', 'g_hostdruleid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $url),
		_('Items'),
		_('Triggers'),
		_('Graphs'),
		_('Hosts'),
		_('Discovery rules'),
		make_sorting_header(_('Key'), 'key_', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Interval'), 'delay', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Type'), 'type', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Create enabled'), 'status', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Discover'), 'discover', $data['sort'], $data['sortorder'], $url)
	])
	->setPageNavigation($data['paging']);

$update_interval_parser = new CUpdateIntervalParser(['usermacros' => true, 'lldmacros' => true]);

foreach ($data['discoveries'] as $discovery) {
	// description
	$description = [];
	$description[] = makeItemTemplatePrefix($discovery['itemid'], $data['parent_templates'],
		ZBX_FLAG_DISCOVERY_RULE_PROTOTYPE, $data['allowed_ui_conf_templates']
	);

	if ($discovery['flags'] & ZBX_FLAG_DISCOVERY_CREATED) {
		$description[] = (new CLink($data['source_link_data']['name'],
			(new CUrl('zabbix.php'))
				->setArgument('action', 'popup')
				->setArgument('popup', 'lldrule.prototype.edit')
				->setArgument('parent_discoveryid', $data['source_link_data']['parent_itemid'])
				->setArgument('itemid', $discovery['discoveryData']['parent_itemid'])
				->setArgument('context', 'host')
		))
			->addClass(ZBX_STYLE_LINK_ALT)
			->addClass(ZBX_STYLE_ORANGE);

		$description[] = NAME_DELIMITER;
	}

	if ($discovery['type'] == ITEM_TYPE_DEPENDENT) {
		if ($discovery['master_item']['type'] == ITEM_TYPE_HTTPTEST) {
			$description[] = $discovery['master_item']['name'];
		}
		else {
			if ($discovery['master_item']['flags'] & ZBX_FLAG_DISCOVERY_PROTOTYPE) {
				$item_url = (new CUrl('zabbix.php'))
					->setArgument('action', 'popup')
					->setArgument('popup', 'item.prototype.edit')
					->setArgument('context', $data['context'])
					->setArgument('parent_discoveryid', $data['parent_discoveryid'])
					->setArgument('itemid', $discovery['master_item']['itemid'])
					->getUrl();
			}
			else {
				$item_url = (new CUrl('zabbix.php'))
					->setArgument('action', 'popup')
					->setArgument('popup', 'item.edit')
					->setArgument('context', $data['context'])
					->setArgument('itemid', $discovery['master_item']['itemid'])
					->getUrl();
			}

			$description[] = (new CLink($discovery['master_item']['name'], $item_url))
				->addClass(ZBX_STYLE_LINK_ALT)
				->addClass(ZBX_STYLE_TEAL);
		}

		$description[] = NAME_DELIMITER;
	}

	$description[] = new CLink(
		$discovery['name'],
		(new CUrl('zabbix.php'))
			->setArgument('action', 'popup')
			->setArgument('popup', 'lldrule.prototype.edit')
			->setArgument('context', $data['context'])
			->setArgument('parent_discoveryid', $data['parent_discoveryid'])
			->setArgument('itemid', $discovery['itemid'])
	);

	$status_disabled = $discovery['status'] == ITEM_STATUS_DISABLED;
	$status_toggle = $data['is_parent_discovered']
		? (new CSpan($status_disabled ? _('No') : _('Yes')))
		: (new CLink($status_disabled ? _('No') : _('Yes')))
			->addClass($status_disabled ? 'js-enable-item' : 'js-disable-item')
			->addClass(ZBX_STYLE_LINK_ACTION)
			->setAttribute('data-itemid', $discovery['itemid']);

	$no_discover = $discovery['discover'] == ZBX_PROTOTYPE_NO_DISCOVER;
	$discover_toggle = $data['is_parent_discovered']
		? (new CSpan($no_discover ? _('No') : _('Yes')))
		: (new CLink($no_discover ? _('No') : _('Yes')))
			->addClass($no_discover ? 'js-discover-enable-item' : 'js-discover-disable-item')
			->addClass(ZBX_STYLE_LINK_ACTION)
			->setAttribute('data-itemid', $discovery['itemid']);

	// Hide zeros for specific items.
	if (in_array($discovery['type'], [ITEM_TYPE_TRAPPER, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_DEPENDENT, ITEM_TYPE_NESTED])
		|| ($discovery['type'] == ITEM_TYPE_ZABBIX_ACTIVE && strncmp($discovery['key_'], 'mqtt.get', 8) == 0)) {
		$discovery['delay'] = '';
	}
	elseif ($update_interval_parser->parse($discovery['delay']) == CParser::PARSE_SUCCESS) {
		$discovery['delay'] = $update_interval_parser->getDelay();
	}

	$checkbox = new CCheckBox('g_hostdruleid['.$discovery['itemid'].']', $discovery['itemid']);

	if (in_array($discovery['type'], checkNowAllowedTypes())
			&& $discovery['status'] == ITEM_STATUS_ACTIVE
			&& $discovery['hosts'][0]['status'] == HOST_STATUS_MONITORED) {
		$checkbox->setAttribute('data-actions', 'execute');
	}

	$host_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'popup')
		->setArgument('popup', $data['context'] === 'host' ? 'host.edit' : 'template.edit')
		->setArgument($data['context'] === 'host' ? 'hostid' : 'templateid', $discovery['hosts'][0]['hostid'])
		->getUrl();


	$discoveryTable->addRow([
		$checkbox,
		$description,
		[
			new CLink(_('Item prototypes'),
				(new CUrl('zabbix.php'))
					->setArgument('action', 'item.prototype.list')
					->setArgument('parent_discoveryid', $discovery['itemid'])
					->setArgument('context', $data['context'])
			),
			CViewHelper::showNum($discovery['items'])
		],
		[
			new CLink(_('Trigger prototypes'),
				(new CUrl('zabbix.php'))
					->setArgument('action', 'trigger.prototype.list')
					->setArgument('parent_discoveryid', $discovery['itemid'])
					->setArgument('context', $data['context'])
			),
			CViewHelper::showNum($discovery['triggers'])
		],
		[
			new CLink(_('Graph prototypes'),
				(new CUrl('zabbix.php'))
					->setArgument('action', 'graph.prototype.list')
					->setArgument('parent_discoveryid', $discovery['itemid'])
					->setArgument('context', $data['context'])
			),
			CViewHelper::showNum($discovery['graphs'])
		],
		[
			new CLink(_('Host prototypes'),
				(new CUrl('zabbix.php'))
					->setArgument('action', 'host.prototype.list')
					->setArgument('parent_discoveryid', $discovery['itemid'])
					->setArgument('context', $data['context'])
			),
			CViewHelper::showNum($discovery['hostPrototypes'])
		],
		[
			new CLink(_('Discovery prototypes'),
				(new CUrl('zabbix.php'))
					->setArgument('action', 'lldrule.prototype.list')
					->setArgument('parent_discoveryid', $discovery['itemid'])
					->setArgument('context', $data['context'])
			),
			CViewHelper::showNum($discovery['discoveryRulePrototypes'])
		],
		(new CDiv($discovery['key_']))->addClass(ZBX_STYLE_WORDWRAP),
		$discovery['delay'],
		item_type2str($discovery['type']),
		$status_toggle->addClass(itemIndicatorStyle($discovery['status'])),
		$discover_toggle->addClass($no_discover ? ZBX_STYLE_RED : ZBX_STYLE_GREEN)
	]);
}

$buttons = [
	[
		'content' => (new CSimpleButton(_('Create enabled')))
			->addClass(ZBX_STYLE_BTN_ALT)
			->addClass('js-massenable-item')
			->addClass('js-no-chkbxrange')
			->setEnabled(!$data['is_parent_discovered'])
			->setAttribute('data-disabled', $data['is_parent_discovered'] ? 1 : null)
	],
	[
		'content' => (new CSimpleButton(_('Create disabled')))
			->addClass(ZBX_STYLE_BTN_ALT)
			->addClass('js-massdisable-item')
			->addClass('js-no-chkbxrange')
			->setEnabled(!$data['is_parent_discovered'])
			->setAttribute('data-disabled', $data['is_parent_discovered'] ? 1 : null)
	],
	[
		'content' => (new CSimpleButton(_('Delete')))
			->addClass(ZBX_STYLE_BTN_ALT)
			->addClass('js-massdelete-item')
			->addClass('js-no-chkbxrange')
	]
];

// Append table to form.
$discoveryForm->addItem([
	$discoveryTable,
	new CActionButtonList('action', 'g_hostdruleid', $buttons)
]);

$html_page
	->addItem($discoveryForm)
	->show();

$confirm_messages = [
	'lldrule.prototype.enable' => [_('Enable selected discovery prototype?'), _('Enable selected discovery prototypes?')],
	'lldrule.prototype.disable' => [_('Disable selected discovery prototype?'), _('Disable selected discovery prototypes?')],
	'lldrule.prototype.delete' => [_('Delete selected discovery prototype?'), _('Delete selected discovery prototypes?')]
];

(new CScriptTag('
	view.init('.json_encode([
		'context' => $data['context'],
		'confirm_messages' => $confirm_messages,
		'form_name' => $discoveryForm->getName(),
		'hostid' => $data['hostid'],
		'parent_discoveryid' => $data['parent_discoveryid']
	]).');
'))
	->setOnDocumentReady()
	->show();
