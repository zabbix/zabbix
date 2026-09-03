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

$this->includeJsFile('lldrule.list.js.php');

$html_page = (new CHtmlPage())
	->setTitle(_('Discovery rules'))
	->setDocUrl(CDocHelper::getUrl($data['context'] === 'host'
		? CDocHelper::DATA_COLLECTION_HOST_DISCOVERY_LIST
		: CDocHelper::DATA_COLLECTION_TEMPLATES_DISCOVERY_LIST
	))
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(
					$data['hostid'] != 0
						? (new CSimpleButton(_('Create discovery rule')))->addClass('js-create-item')
						: (new CSimpleButton(
							$data['context'] === 'host'
								? _('Create discovery rule (select host first)')
								: _('Create discovery rule (select template first)')
						))->setEnabled(false)
				)
		))->setAttribute('aria-label', _('Content controls'))
	);

if ($data['hostid'] != 0) {
	$html_page->setNavigation(getHostNavigation('discoveries', $data['hostid']));
}

// Add filter tab.
$filter = (new CFilter())
	->setResetUrl(
		(new CUrl('zabbix.php'))
			->setArgument('action', $data['action'])
			->setArgument('context', $data['context'])
	)
	->setProfile($data['profileIdx'])
	->setActiveTab($data['active_tab'])
	->addFormItem((new CVar('action', $data['action']))->removeId())
	->addFormItem((new CVar('context', $data['context']))->removeId());

$hg_ms_params = $data['context'] === 'host' ? ['with_hosts' => true] : ['with_templates' => true];

$filter_column1 = (new CFormGrid())
	->addItem([
		new CLabel($data['context'] === 'host' ? _('Host groups') : _('Template groups'), 'filter_groupids__ms'),
		new CFormField(
			(new CMultiSelect([
				'name' => 'filter_groupids[]',
				'object_name' => $data['context'] === 'host' ? 'hostGroup' : 'templateGroup',
				'data' => $data['filter']['ms_groups'],
				'popup' => [
					'parameters' => [
						'srctbl' => $data['context'] === 'host' ? 'host_groups' : 'template_groups',
						'srcfld1' => 'groupid',
						'dstfrm' => CFilter::FORM_NAME,
						'dstfld1' => 'filter_groupids_',
						'editable' => true,
						'enrich_parent_groups' => true
					] + $hg_ms_params
				]
			]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
		)
	])
	->addItem([
		new CLabel($data['context'] === 'host' ? _('Hosts') : _('Templates'), 'filter_hostids__ms'),
		new CFormField(
			(new CMultiSelect([
				'name' => 'filter_hostids[]',
				'object_name' => $data['context'] === 'host' ? 'hosts' : 'templates',
				'data' => $data['filter']['ms_hosts'],
				'popup' => [
					'filter_preselect' => [
						'id' => 'filter_groupids_',
						'submit_as' => $data['context'] === 'host' ? 'groupid' : 'templategroupid'
					],
					'parameters' => [
						'srctbl' => $data['context'] === 'host' ? 'hosts' : 'templates',
						'srcfld1' => 'hostid',
						'dstfrm' => CFilter::FORM_NAME,
						'dstfld1' => 'filter_hostids_',
						'editable' => true
					]
				]
			]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
		)
	])
	->addItem([new CLabel(_('Name'), 'filter_name'),
		new CFormField(
			(new CTextBox('filter_name', $data['filter']['filter_name']))
				->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
		)
	])
	->addItem([new CLabel(_('Key'), 'filter_key'),
		new CFormField(
			(new CTextBox('filter_key', $data['filter']['filter_key']))
				->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
		)
	]);

// type select
$type_select = (new CSelect('filter_type'))
	->setId('filter_type')
	->setFocusableElementId('label-filter-type')
	->addOption(new CSelectOption(-1, _('All')))
	->setValue($data['filter']['filter_type']);

$lld_types = item_type2str();
unset($lld_types[ITEM_TYPE_HTTPTEST], $lld_types[ITEM_TYPE_CALCULATED], $lld_types[ITEM_TYPE_SNMPTRAP]);

$type_select->addOptions(CSelect::createOptionsFromArray($lld_types));

$filter_column2 = (new CFormGrid())
	->addItem([
		new CLabel(_('Type'), $type_select->getFocusableElementId()),
		new CFormField($type_select)
	])
	->addItem([
		new CLabel(_('Update interval'), 'filter_delay'),
		new CFormField(
			(new CTextBox('filter_delay', $data['filter']['filter_delay']))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
		)
	])
	->addItem([
		new CLabel(_('Delete lost resources'), 'filter_lifetime'),
		new CFormField([
			(new CRadioButtonList('filter_lifetime_type', (int) $data['filter']['filter_lifetime_type']))
				->addValue(_('All'), -1)
				->addValue(_('Never'), ZBX_LLD_DELETE_NEVER)
				->addValue(_('Immediately'), ZBX_LLD_DELETE_IMMEDIATELY)
				->addValue(_('After'), ZBX_LLD_DELETE_AFTER)
				->setModern(),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CTextBox('filter_lifetime', $data['filter']['filter_lifetime']))
				->setAttribute('disabled', $data['filter']['filter_lifetime_type'] != ZBX_LLD_DELETE_AFTER)
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
		])
	])
	->addItem([
		new CLabel(_('Disable lost resources'), 'filter_enabled_lifetime'),
		new CFormField([
			(new CRadioButtonList('filter_enabled_lifetime_type', $data['filter']['filter_enabled_lifetime_type']))
				->addValue(_('All'), -1)
				->addValue(_('Never'), ZBX_LLD_DISABLE_NEVER)
				->addValue(_('Immediately'), ZBX_LLD_DISABLE_IMMEDIATELY)
				->addValue(_('After'), ZBX_LLD_DISABLE_AFTER)
				->setModern(),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CTextBox('filter_enabled_lifetime', $data['filter']['filter_enabled_lifetime']))
				->setAttribute('disabled', $data['filter']['filter_enabled_lifetime_type'] != ZBX_LLD_DISABLE_AFTER)
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
		])
	])
	->addItem([
		new CLabel(_('SNMP OID'), 'filter_snmp_oid'),
		new CFormField(
			(new CTextBox('filter_snmp_oid', $data['filter']['filter_snmp_oid']))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
		)
	]);

if ($data['context'] === 'host') {
	$filter_column2->addItem([
		new CLabel(_('State'), 'filter_state'),
		new CFormField(
			(new CRadioButtonList('filter_state', (int) $data['filter']['filter_state']))
				->addValue(_('All'), -1)
				->addValue(_('Normal'), ITEM_STATE_NORMAL)
				->addValue(_('Not supported'), ITEM_STATE_NOTSUPPORTED)
				->setModern(true)
		)
	]);
}

$filter_column2->addItem([
	new CLabel(_('Status'), 'filter_status'),
	new CFormField(
			(new CRadioButtonList('filter_status', (int) $data['filter']['filter_status']))
			->addValue(_('All'), -1)
			->addValue(_('Enabled'), ITEM_STATUS_ACTIVE)
			->addValue(_('Disabled'), ITEM_STATUS_DISABLED)
			->setEnabled($data['context'] !== 'host' || $data['filter']['filter_state'] == -1)
			->setModern(true)
	)
]);

$filter->addFilterTab(_('Filter'), [$filter_column1, $filter_column2]);

$html_page->addItem($filter);

$url = (new CUrl('zabbix.php'))
	->setArgument('action', $data['action'])
	->setArgument('context', $data['context'])
	->getUrl();

// create form
$discoveryForm = (new CForm())
	->setName('lldrule_list')
	->addItem((new CVar('context', $data['context']))->removeId())
	->setName('discovery');

if ($data['hostid'] != 0) {
	$discoveryForm->addItem((new CVar('hostid', $data['hostid']))->removeId());
}

// create table
$discoveryTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_items'))->onClick("checkAll('".$discoveryForm->getName()."', 'all_items', 'g_hostdruleid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		($data['context'] === 'host') ? _('Host') : _('Template'),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $url),
		_('Items'),
		_('Triggers'),
		_('Graphs'),
		_('Hosts'),
		_('Discovery rules'),
		make_sorting_header(_('Key'), 'key_', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Interval'), 'delay', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Type'), 'type', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Status'), 'status', $data['sort'], $data['sortorder'], $url),
		($data['context'] === 'host') ? _('Info') : null
	])
	->setPageNavigation($data['paging']);

$update_interval_parser = new CUpdateIntervalParser(['usermacros' => true]);
$current_time = time();

foreach ($data['discoveries'] as $discovery) {
	// description
	$description = [];
	$description[] = makeItemTemplatePrefix($discovery['itemid'], $data['parent_templates'], ZBX_FLAG_DISCOVERY_RULE,
		$data['allowed_ui_conf_templates']
	);

	if ($discovery['flags'] & ZBX_FLAG_DISCOVERY_CREATED) {
		if ($discovery['discoveryRule']) {
			if ($discovery['is_discovery_rule_editable']) {
				$description[] = (new CLink($discovery['discoveryRule']['name'],
					(new CUrl('zabbix.php'))
						->setArgument('action', 'popup')
						->setArgument('popup', 'lldrule.edit')
						->setArgument('context', 'host')
						->setArgument('itemid', $discovery['discoveryRule']['itemid'])
						->getUrl()
				))
					->addClass(ZBX_STYLE_LINK_ALT)
					->addClass(ZBX_STYLE_ORANGE);
			}
			else {
				$description[] = (new CSpan($discovery['discoveryRule']['name']))->addClass(ZBX_STYLE_ORANGE);
			}
		}
		else {
			$description[] = (new CSpan(_('Inaccessible discovery rule')))->addClass(ZBX_STYLE_ORANGE);
		}

		$description[] = NAME_DELIMITER;
	}

	if ($discovery['type'] == ITEM_TYPE_DEPENDENT) {
		if ($discovery['master_item']['type'] == ITEM_TYPE_HTTPTEST) {
			$description[] = $discovery['master_item']['name'];
		}
		else {
			$item_url = (new CUrl('zabbix.php'))
				->setArgument('action', 'popup')
				->setArgument('popup', 'item.edit')
				->setArgument('context', 'host')
				->setArgument('itemid', $discovery['master_item']['itemid'])
				->getUrl();

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
			->setArgument('popup', 'lldrule.edit')
			->setArgument('context', $data['context'])
			->setArgument('itemid', $discovery['itemid'])
	);

	$status = (new CLink(itemIndicator($discovery['status'], $discovery['state'])))
		->addClass(ZBX_STYLE_LINK_ACTION)
		->addClass(itemIndicatorStyle($discovery['status'], $discovery['state']))
		->addClass($discovery['status'] == ITEM_STATUS_DISABLED ? 'js-enable-item' : 'js-disable-item')
		->setAttribute('data-itemid', $discovery['itemid']);

	// Hide zeros for specific items.
	if (in_array($discovery['type'], [ITEM_TYPE_TRAPPER, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_DEPENDENT, ITEM_TYPE_NESTED])
			|| ($discovery['type'] == ITEM_TYPE_ZABBIX_ACTIVE && strncmp($discovery['key_'], 'mqtt.get', 8) == 0)) {
		$discovery['delay'] = '';
	}
	elseif ($update_interval_parser->parse($discovery['delay']) == CParser::PARSE_SUCCESS) {
		$discovery['delay'] = $update_interval_parser->getDelay();
	}

	$disable_source = $discovery['status'] == ITEM_STATUS_DISABLED && $discovery['discoveryData']
		? $discovery['discoveryData']['disable_source']
		: '';

	// info
	if ($data['context'] === 'host') {
		$info_icons = [];

		if ($discovery['status'] == ITEM_STATUS_ACTIVE && $discovery['error'] !== '') {
			$info_icons[] = makeErrorIcon($discovery['error']);
		}

		if ($discovery['discoveryData'] && $discovery['discoveryData']['status'] == ZBX_LLD_STATUS_LOST) {
			$info_icons[] = getLldLostEntityIndicator($current_time, $discovery['discoveryData']['ts_delete'],
				$discovery['discoveryData']['ts_disable'], $disable_source,
				$discovery['status'] == ITEM_STATUS_DISABLED, _('discovery rule')
			);
		}
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

	$host = new CLink($discovery['hosts'][0]['name'], $host_url);
	$disabled_by_lld = $disable_source == ZBX_DISABLE_SOURCE_LLD;

	$discoveryTable->addRow([
		$checkbox,
		$host,
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
		[
			$status,
			$disabled_by_lld ? makeDescriptionIcon(_('Disabled automatically by an LLD rule.')) : null
		],
		($data['context'] === 'host') ? makeInformationList($info_icons) : null
	]);
}

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
	[
		'content' => (new CSimpleButton(_('Delete')))
			->addClass(ZBX_STYLE_BTN_ALT)
			->addClass('js-massdelete-item')
			->addClass('js-no-chkbxrange')
	]
];

if ($data['context'] === 'template') {
	unset($buttons['execute']);
}

// Append table to form.
$discoveryForm->addItem([
	$discoveryTable,
	new CActionButtonList('action', 'g_hostdruleid', $buttons,
		'g_hostdruleid_'.(array_key_exists('hostid', $data) ? $data['hostid'] : 0)
	)
]);

$html_page
	->addItem($discoveryForm)
	->show();


$confirm_messages = [
	'lldrule.enable' => [_('Enable selected discovery rule?'), _('Enable selected discovery rules?')],
	'lldrule.disable' => [_('Disable selected discovery rule?'), _('Disable selected discovery rules?')],
	'lldrule.delete' => [_('Delete selected discovery rule?'), _('Delete selected discovery rules?')]
];

(new CScriptTag('
	view.init('.json_encode([
		'context' => $data['context'],
		'tokens' => [
			'lldrule' => [CSRF_TOKEN_NAME => CCsrfTokenHelper::get('lldrule')],
			'item' => [CSRF_TOKEN_NAME => CCsrfTokenHelper::get('item')]
		],
		'form_name' => $discoveryForm->getName(),
		'confirm_messages' => $confirm_messages,
		'hostid' => $data['hostid']
	]).');
'))
	->setOnDocumentReady()
	->show();
