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

require_once dirname(__FILE__).'/js/configuration.host.discovery.list.js.php';

$widget = (new CWidget())
	->setTitle(_('Discovery rules'))
	->setControls(
		(new CTag('nav', true,
			(new CList())->addItem(
				($data['hostid'] != 0)
					? new CRedirectButton(_('Create discovery rule'),
						(new CUrl('host_discovery.php'))
							->setArgument('form', 'create')
							->setArgument('hostid', $data['hostid'])
							->setArgument('context', $data['context'])
							->getUrl()
					)
					: (new CButton('form',
						($data['context'] === 'host')
							? _('Create discovery rule (select host first)')
							: _('Create discovery rule (select template first)')
					))->setEnabled(false)
			)
		))->setAttribute('aria-label', _('Content controls'))
	);

if ($data['hostid'] != 0) {
	$widget->setNavigation(getHostNavigation('discoveries', $data['hostid']));
}

// Add filter tab.
$filter = (new CFilter())
	->setResetUrl((new CUrl('host_discovery.php'))->setArgument('context', $data['context']))
	->setProfile($data['profileIdx'])
	->setActiveTab($data['active_tab'])
	->addvar('context', $data['context']);

$hg_ms_params = ($data['context'] === 'host') ? ['real_hosts' => 1] : ['templated_hosts' => 1];

$filter_column1 = (new CFormList())
	->addRow((new CLabel(_('Host groups'), 'filter_groupids__ms')),
		(new CMultiSelect([
			'name' => 'filter_groupids[]',
			'object_name' => 'hostGroup',
			'data' => $data['filter']['groups'],
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'filter_groupids_',
					'editable' => true,
					'enrich_parent_groups' => true
				] + $hg_ms_params
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	)
	->addRow((new CLabel(($data['context'] === 'host') ? _('Hosts') : _('Templates'), 'filter_hostids__ms')),
		(new CMultiSelect([
			'name' => 'filter_hostids[]',
			'object_name' => ($data['context'] === 'host') ? 'hosts' : 'templates',
			'data' => $data['filter']['hosts'],
			'popup' => [
				'filter_preselect_fields' => [
					'hostgroups' => 'filter_groupids_'
				],
				'parameters' => [
					'srctbl' => ($data['context'] === 'host') ? 'hosts' : 'templates',
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
	->addOption(new CSelectOption(-1, _('all')))
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
	->addRow(_('Keep lost resources period'),
		(new CTextBox('filter_lifetime', $data['filter']['lifetime']))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
	)
	->addRow(_('SNMP OID'),
		(new CTextBox('filter_snmp_oid', $data['filter']['snmp_oid']))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
		'filter_snmp_oid_row'
	);

$filter_column3 = (new CFormList());

if ($data['context'] === 'host') {
	$filter_column3->addRow(_('State'),
		(new CRadioButtonList('filter_state', (int) $data['filter']['state']))
			->addValue(_('all'), -1)
			->addValue(_('Normal'), ITEM_STATE_NORMAL)
			->addValue(_('Not supported'), ITEM_STATE_NOTSUPPORTED)
			->setModern(true)
	);
}

$filter_column3->addRow(_('Status'),
	(new CRadioButtonList('filter_status', (int) $data['filter']['status']))
		->addValue(_('all'), -1)
		->addValue(_('Enabled'), ITEM_STATUS_ACTIVE)
		->addValue(_('Disabled'), ITEM_STATUS_DISABLED)
		->setModern(true)
);

$filter->addFilterTab(_('Filter'), [$filter_column1, $filter_column2, $filter_column3]);

$widget->addItem($filter);

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
	]);

$update_interval_parser = new CUpdateIntervalParser(['usermacros' => true]);

foreach ($data['discoveries'] as $discovery) {
	// description
	$description = [];
	$description[] = makeItemTemplatePrefix($discovery['itemid'], $data['parent_templates'], ZBX_FLAG_DISCOVERY_RULE,
		$data['allowed_ui_conf_templates']
	);

	if ($discovery['type'] == ITEM_TYPE_DEPENDENT) {
		if ($discovery['master_item']['type'] == ITEM_TYPE_HTTPTEST) {
			$description[] = CHtml::encode($discovery['master_item']['name']);
		}
		else {
			$description[] = (new CLink(CHtml::encode($discovery['master_item']['name']),
				(new CUrl('items.php'))
					->setArgument('form', 'update')
					->setArgument('itemid', $discovery['master_item']['itemid'])
					->setArgument('context', $data['context'])
					->getUrl()
			))
				->addClass(ZBX_STYLE_LINK_ALT)
				->addClass(ZBX_STYLE_TEAL);
		}

		$description[] = NAME_DELIMITER;
	}

	$description[] = new CLink(
		CHtml::encode($discovery['name']),
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
			->getUrl()
		))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass(itemIndicatorStyle($discovery['status'], $discovery['state']))
			->addSID();

	// Hide zeros for trapper, SNMP trap and dependent items.
	if ($discovery['type'] == ITEM_TYPE_TRAPPER || $discovery['type'] == ITEM_TYPE_SNMPTRAP
			|| $discovery['type'] == ITEM_TYPE_DEPENDENT || ($discovery['type'] == ITEM_TYPE_ZABBIX_ACTIVE
				&& strncmp($discovery['key_'], 'mqtt.get', 8) === 0)) {
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

	$discoveryTable->addRow([
		new CCheckBox('g_hostdruleid['.$discovery['itemid'].']', $discovery['itemid']),
		$discovery['hosts'][0]['name'],
		$description,
		[
			new CLink(_('Item prototypes'),
				(new CUrl('disc_prototypes.php'))
					->setArgument('parent_discoveryid', $discovery['itemid'])
					->setArgument('context', $data['context'])
			),
			CViewHelper::showNum($discovery['items'])
		],
		[
			new CLink(_('Trigger prototypes'),
				(new CUrl('trigger_prototypes.php'))
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
		(new CDiv(CHtml::encode($discovery['key_'])))->addClass(ZBX_STYLE_WORDWRAP),
		$discovery['delay'],
		item_type2str($discovery['type']),
		$status,
		($data['context'] === 'host') ? makeInformationList($info_icons) : null
	]);
}

$button_list = [
	'discoveryrule.massenable' => ['name' => _('Enable'), 'confirm' =>_('Enable selected discovery rules?')],
	'discoveryrule.massdisable' => ['name' => _('Disable'), 'confirm' =>_('Disable selected discovery rules?')]
];

if ($data['context'] === 'host') {
	$button_list += ['discoveryrule.masscheck_now' => ['name' => _('Execute now'), 'disabled' => $data['is_template']]];
}

$button_list += [
	'discoveryrule.massdelete' => ['name' => _('Delete'), 'confirm' =>_('Delete selected discovery rules?')]
];

// Append table to form.
$discoveryForm->addItem([$discoveryTable, $data['paging'], new CActionButtonList('action', 'g_hostdruleid',
	$button_list, $data['checkbox_hash']
)]);

// Append form to widget.
$widget->addItem($discoveryForm);

$widget->show();

(new CScriptTag('view.init();'))
	->setOnDocumentReady()
	->show();
