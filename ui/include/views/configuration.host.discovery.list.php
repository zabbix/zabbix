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

require_once dirname(__FILE__).'/js/configuration.host.discovery.list.js.php';

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
						? new CRedirectButton(_('Create discovery rule'),
							(new CUrl('host_discovery.php'))
								->setArgument('form', 'create')
								->setArgument('hostid', $data['hostid'])
								->setArgument('context', $data['context'])
						)
						: (new CButton('form',
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
	->setResetUrl((new CUrl('host_discovery.php'))->setArgument('context', $data['context']))
	->setProfile($data['profileIdx'])
	->setActiveTab($data['active_tab'])
	->addvar('context', $data['context']);

$hg_ms_params = $data['context'] === 'host' ? ['with_hosts' => true] : ['with_templates' => true];

$filter_column1 = (new CFormList())
	->addRow(
		new CLabel($data['context'] === 'host' ? _('Host groups') : _('Template groups'), 'filter_groupids__ms'),
		(new CMultiSelect([
			'name' => 'filter_groupids[]',
			'object_name' => $data['context'] === 'host' ? 'hostGroup' : 'templateGroup',
			'data' => $data['filter']['groups'],
			'popup' => [
				'parameters' => [
					'srctbl' => $data['context'] === 'host' ? 'host_groups' : 'template_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'filter_groupids_',
					'editable' => true,
					'enrich_parent_groups' => true
				] + $hg_ms_params
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	)
	->addRow(
		new CLabel($data['context'] === 'host' ? _('Hosts') : _('Templates'), 'filter_hostids__ms'),
		(new CMultiSelect([
			'name' => 'filter_hostids[]',
			'object_name' => $data['context'] === 'host' ? 'hosts' : 'templates',
			'data' => $data['filter']['hosts'],
			'popup' => [
				'filter_preselect' => [
					'id' => 'filter_groupids_',
					'submit_as' => $data['context'] === 'host' ? 'groupid' : 'templategroupid'
				],
				'parameters' => [
					'srctbl' => $data['context'] === 'host' ? 'hosts' : 'templates',
					'srcfld1' => 'hostid',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'filter_hostids_',
					'editable' => true
				]
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	)
	->addRow(_('Name'),
		(new CTextBox('filter_name', $data['filter']['name']))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	)
	->addRow(_('Key'),
		(new CTextBox('filter_key', $data['filter']['key']))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	);

// type select
$filter_type_visibility = [];
$type_select = (new CSelect('filter_type'))
	->setId('filter_type')
	->setFocusableElementId('label-type')
	->addOption(new CSelectOption(-1, _('All')))
	->setValue($data['filter']['type']);

zbx_subarray_push($filter_type_visibility, -1, 'filter_delay_row');
zbx_subarray_push($filter_type_visibility, -1, 'filter_delay');

$lld_types = item_type2str();
unset($lld_types[ITEM_TYPE_HTTPTEST], $lld_types[ITEM_TYPE_CALCULATED], $lld_types[ITEM_TYPE_SNMPTRAP]);

$type_select->addOptions(CSelect::createOptionsFromArray($lld_types));

foreach ($lld_types as $type => $name) {
	if ($type != ITEM_TYPE_TRAPPER) {
		zbx_subarray_push($filter_type_visibility, $type, 'filter_delay_row');
		zbx_subarray_push($filter_type_visibility, $type, 'filter_delay');
	}
	if ($type == ITEM_TYPE_SNMP) {
		zbx_subarray_push($filter_type_visibility, $type, 'filter_snmp_oid_row');
	}
}

zbx_add_post_js(
	'var filterTypeSwitcher = new CViewSwitcher("filter_type", "change", '.json_encode($filter_type_visibility).');'
);

$filter_column2 = (new CFormList())
	->addRow(new CLabel(_('Type'), $type_select->getFocusableElementId()), $type_select)
	->addRow(_('Update interval'),
		(new CTextBox('filter_delay', $data['filter']['delay']))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
		'filter_delay_row'
	)
	->addRow(
		new CLabel(_('Delete lost resources'), 'filter_lifetime'),
		new CFormField([
			(new CRadioButtonList('filter_lifetime_type', (int) $data['filter']['lifetime_type']))
				->addValue(_('All'), -1)
				->addValue(_('Never'), ZBX_LLD_DELETE_NEVER)
				->addValue(_('Immediately'), ZBX_LLD_DELETE_IMMEDIATELY)
				->addValue(_('After'), ZBX_LLD_DELETE_AFTER)
				->setModern(),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CTextBox('filter_lifetime', $data['filter']['lifetime']))
				->setAttribute('disabled', $data['filter']['lifetime_type'] != ZBX_LLD_DELETE_AFTER)
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
		])
	)
	->addRow(
		new CLabel(_('Disable lost resources'), 'filter_enabled_lifetime'),
		new CFormField([
			(new CRadioButtonList('filter_enabled_lifetime_type', (int) $data['filter']['enabled_lifetime_type']))
				->addValue(_('All'), -1)
				->addValue(_('Never'), ZBX_LLD_DISABLE_NEVER)
				->addValue(_('Immediately'), ZBX_LLD_DISABLE_IMMEDIATELY)
				->addValue(_('After'), ZBX_LLD_DISABLE_AFTER)
				->setModern(),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CTextBox('filter_enabled_lifetime', $data['filter']['enabled_lifetime']))
				->setAttribute('disabled', $data['filter']['enabled_lifetime_type'] != ZBX_LLD_DISABLE_AFTER)
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
		])
	)
	->addRow(_('SNMP OID'),
		(new CTextBox('filter_snmp_oid', $data['filter']['snmp_oid']))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
		'filter_snmp_oid_row'
	);

if ($data['context'] === 'host') {
	$filter_column2->addRow(_('State'),
		(new CRadioButtonList('filter_state', (int) $data['filter']['state']))
			->addValue(_('All'), -1)
			->addValue(_('Normal'), ITEM_STATE_NORMAL)
			->addValue(_('Not supported'), ITEM_STATE_NOTSUPPORTED)
			->setModern(true)
	);
}

$filter_column2->addRow(_('Status'),
	(new CRadioButtonList('filter_status', (int) $data['filter']['status']))
		->addValue(_('All'), -1)
		->addValue(_('Enabled'), ITEM_STATUS_ACTIVE)
		->addValue(_('Disabled'), ITEM_STATUS_DISABLED)
		->setEnabled($data['context'] !== 'host' || $data['filter']['state'] == -1)
		->setModern(true)
);

$filter->addFilterTab(_('Filter'), [$filter_column1, $filter_column2]);

$html_page->addItem($filter);

$url = (new CUrl('host_discovery.php'))
	->setArgument('context', $data['context'])
	->getUrl();

// create form
$discoveryForm = (new CForm('post', $url))->setName('discovery');

if ($data['hostid'] != 0) {
	$discoveryForm->addVar('hostid', $data['hostid']);
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
		make_sorting_header(_('Key'), 'key_', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Interval'), 'delay', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Type'), 'type', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Status'), 'status', $data['sort'], $data['sortorder'], $url),
		($data['context'] === 'host') ? _('Info') : null
	])
	->setPageNavigation($data['paging']);

$update_interval_parser = new CUpdateIntervalParser(['usermacros' => true]);
$csrf_token = CCsrfTokenHelper::get('host_discovery.php');

foreach ($data['discoveries'] as $discovery) {
	// description
	$description = [];
	$description[] = makeItemTemplatePrefix($discovery['itemid'], $data['parent_templates'], ZBX_FLAG_DISCOVERY_RULE,
		$data['allowed_ui_conf_templates']
	);

	if ($discovery['type'] == ITEM_TYPE_DEPENDENT) {
		if ($discovery['master_item']['type'] == ITEM_TYPE_HTTPTEST) {
			$description[] = $discovery['master_item']['name'];
		}
		else {
			$item_url = (new CUrl('zabbix.php'))
				->setArgument('action', 'popup')
				->setArgument('popup', 'item.edit')
				->setArgument('context', $data['context'])
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
		(new CUrl('host_discovery.php'))
			->setArgument('form', 'update')
			->setArgument('itemid', $discovery['itemid'])
			->setArgument('context', $data['context'])
	);

	// status
	$status = (new CLink(
		itemIndicator($discovery['status'], $discovery['state']),
		(new CUrl('host_discovery.php'))
			->setArgument('hostid', $discovery['hostid'])
			->setArgument('g_hostdruleid[]', $discovery['itemid'])
			->setArgument('action', ($discovery['status'] == ITEM_STATUS_DISABLED)
				? 'discoveryrule.massenable'
				: 'discoveryrule.massdisable'
			)
			->setArgument('context', $data['context'])
			->setArgument('backurl', $url)
			->getUrl()
		))
			->addCsrfToken($csrf_token)
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass(itemIndicatorStyle($discovery['status'], $discovery['state']));

	// Hide zeros for trapper, SNMP trap and dependent items.
	if ($discovery['type'] == ITEM_TYPE_TRAPPER || $discovery['type'] == ITEM_TYPE_SNMPTRAP
			|| $discovery['type'] == ITEM_TYPE_DEPENDENT || ($discovery['type'] == ITEM_TYPE_ZABBIX_ACTIVE
				&& strncmp($discovery['key_'], 'mqtt.get', 8) == 0)) {
		$discovery['delay'] = '';
	}
	elseif ($update_interval_parser->parse($discovery['delay']) == CParser::PARSE_SUCCESS) {
		$discovery['delay'] = $update_interval_parser->getDelay();
	}

	// info
	if ($data['context'] === 'host') {
		$info_icons = [];

		if ($discovery['status'] == ITEM_STATUS_ACTIVE && $discovery['error'] !== '') {
			$info_icons[] = makeErrorIcon($discovery['error']);
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
				(new CUrl('graphs.php'))
					->setArgument('parent_discoveryid', $discovery['itemid'])
					->setArgument('context', $data['context'])
			),
			CViewHelper::showNum($discovery['graphs'])
		],
		($discovery['hosts'][0]['flags'] == ZBX_FLAG_DISCOVERY_NORMAL)
			? [
				new CLink(_('Host prototypes'),
					(new CUrl('host_prototypes.php'))
						->setArgument('parent_discoveryid', $discovery['itemid'])
						->setArgument('context', $data['context'])
				),
				CViewHelper::showNum($discovery['hostPrototypes'])
			]
			: '',
		(new CDiv($discovery['key_']))->addClass(ZBX_STYLE_WORDWRAP),
		$discovery['delay'],
		item_type2str($discovery['type']),
		$status,
		($data['context'] === 'host') ? makeInformationList($info_icons) : null
	]);
}

$button_list = [
	'discoveryrule.massenable' => [
		'name' => _('Enable'),
		'confirm_singular' => _('Enable selected discovery rule?'),
		'confirm_plural' => _('Enable selected discovery rules?'),
		'csrf_token' => $csrf_token
	],
	'discoveryrule.massdisable' => [
		'name' => _('Disable'),
		'confirm_singular' => _('Disable selected discovery rule?'),
		'confirm_plural' => _('Disable selected discovery rules?'),
		'csrf_token' => $csrf_token
	]
];

if ($data['context'] === 'host') {
	$button_list += [
		'discoveryrule.masscheck_now' => [
			'content' => (new CSimpleButton(_('Execute now')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massexecute-item')
				->addClass('js-no-chkbxrange')
				->setAttribute('data-required', 'execute')
		]
	];
}

$button_list += [
	'discoveryrule.massdelete' => [
		'name' => _('Delete'),
		'confirm_singular' => _('Delete selected discovery rule?'),
		'confirm_plural' => _('Delete selected discovery rules?'),
		'csrf_token' => $csrf_token
	]
];

// Append table to form.
$discoveryForm->addItem([
	$discoveryTable,
	new CActionButtonList('action', 'g_hostdruleid', $button_list, $data['checkbox_hash'])
]);

$html_page
	->addItem($discoveryForm)
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'context' => $data['context'],
		'checkbox_hash' => $data['checkbox_hash'],
		'checkbox_object' => 'g_hostdruleid',
		'token' => [CSRF_TOKEN_NAME, CCsrfTokenHelper::get('item')],
		'form_name' => $discoveryForm->getName()
	]).');
'))
	->setOnDocumentReady()
	->show();
