<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
							->getUrl()
					)
					: (new CButton('form', _('Create discovery rule (select host first)')))->setEnabled(false)
			)
		))->setAttribute('aria-label', _('Content controls'))
	);

if ($data['hostid'] != 0) {
	$widget->addItem(get_header_host_table('discoveries', $data['hostid']));
}

// Add filter tab.
$filter = (new CFilter(new CUrl('host_discovery.php')))
	->setProfile($data['profileIdx'])
	->setActiveTab($data['active_tab']);

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
					'dstfrm' => $filter->getName(),
					'dstfld1' => 'filter_groupids_',
					'editable' => true
				]
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	)
	->addRow((new CLabel(_('Hosts'), 'filter_hostids__ms')),
		(new CMultiSelect([
			'name' => 'filter_hostids[]',
			'object_name' => 'host_templates',
			'data' => $data['filter']['hosts'],
			'popup' => [
				'filter_preselect_fields' => [
					'hostgroups' => 'filter_groupids_'
				],
				'parameters' => [
					'srctbl' => 'host_templates',
					'srcfld1' => 'hostid',
					'dstfrm' => $filter->getName(),
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
$cmb_type = new CComboBox('filter_type', $data['filter']['type'], null, [-1 => _('all')]);
zbx_subarray_push($filter_type_visibility, -1, 'filter_delay_row');
zbx_subarray_push($filter_type_visibility, -1, 'filter_delay');

$lld_types = item_type2str();
unset($lld_types[ITEM_TYPE_AGGREGATE], $lld_types[ITEM_TYPE_HTTPTEST], $lld_types[ITEM_TYPE_CALCULATED],
	$lld_types[ITEM_TYPE_SNMPTRAP]
);

$cmb_type->addItems($lld_types);

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
	->addRow(_('Type'), $cmb_type)
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

$filter_column3 = (new CFormList())
	->addRow(_('State'),
		new CComboBox('filter_state', $data['filter']['state'], null, [
			-1 => _('all'),
			ITEM_STATE_NORMAL => itemState(ITEM_STATE_NORMAL),
			ITEM_STATE_NOTSUPPORTED => itemState(ITEM_STATE_NOTSUPPORTED)
		])
	)
	->addRow(_('Status'),
		new CComboBox('filter_status', $data['filter']['status'], null, [
			-1 => _('all'),
			ITEM_STATUS_ACTIVE => item_status2str(ITEM_STATUS_ACTIVE),
			ITEM_STATUS_DISABLED => item_status2str(ITEM_STATUS_DISABLED)
		])
	);

$filter->addFilterTab(_('Filter'), [$filter_column1, $filter_column2, $filter_column3]);

$widget->addItem($filter);

// create form
$discoveryForm = (new CForm())->setName('discovery');

if ($data['hostid'] != 0) {
	$discoveryForm->addVar('hostid', $data['hostid']);
}

$url = (new CUrl('host_discovery.php'))->getUrl();

// create table
$discoveryTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_items'))->onClick("checkAll('".$discoveryForm->getName()."', 'all_items', 'g_hostdruleid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		_('Host'),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $url),
		_('Items'),
		_('Triggers'),
		_('Graphs'),
		_('Hosts'),
		make_sorting_header(_('Key'), 'key_', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Interval'), 'delay', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Type'), 'type', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Status'), 'status', $data['sort'], $data['sortorder'], $url),
		_('Info')
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
			$description[] = CHtml::encode($discovery['master_item']['name_expanded']);
		}
		else {
			$description[] = (new CLink(CHtml::encode($discovery['master_item']['name_expanded']),
				(new CUrl('items.php'))
					->setArgument('form', 'update')
					->setArgument('itemid', $discovery['master_item']['itemid'])
					->getUrl()
			))
				->addClass(ZBX_STYLE_LINK_ALT)
				->addClass(ZBX_STYLE_TEAL);
		}

		$description[] = NAME_DELIMITER;
	}

	$description[] = new CLink($discovery['name_expanded'], '?form=update&itemid='.$discovery['itemid']);

	// status
	$status = (new CLink(
		itemIndicator($discovery['status'], $discovery['state']),
		'?hostid='.$discovery['hostid'].
			'&g_hostdruleid[]='.$discovery['itemid'].
			'&action='.($discovery['status'] == ITEM_STATUS_DISABLED
				? 'discoveryrule.massenable'
				: 'discoveryrule.massdisable'
			))
		)
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass(itemIndicatorStyle($discovery['status'], $discovery['state']))
			->addSID();

	// info
	$info_icons = [];

	if ($discovery['status'] == ITEM_STATUS_ACTIVE && $discovery['error'] !== '') {
		$info_icons[] = makeErrorIcon($discovery['error']);
	}

	// Hide zeros for trapper, SNMP trap and dependent items.
	if ($discovery['type'] == ITEM_TYPE_TRAPPER || $discovery['type'] == ITEM_TYPE_SNMPTRAP
			|| $discovery['type'] == ITEM_TYPE_DEPENDENT || ($discovery['type'] == ITEM_TYPE_ZABBIX_ACTIVE
				&& strncmp($discovery['key_'], 'mqtt.get', 8) === 0)) {
		$discovery['delay'] = '';
	}
	elseif ($update_interval_parser->parse($discovery['delay']) == CParser::PARSE_SUCCESS) {
		$discovery['delay'] = $update_interval_parser->getDelay();
	}

	$discoveryTable->addRow([
		new CCheckBox('g_hostdruleid['.$discovery['itemid'].']', $discovery['itemid']),
		$discovery['hosts'][0]['name'],
		$description,
		[
			new CLink(
				_('Item prototypes'),
				'disc_prototypes.php?parent_discoveryid='.$discovery['itemid']
			),
			CViewHelper::showNum($discovery['items'])
		],
		[
			new CLink(
				_('Trigger prototypes'),
				'trigger_prototypes.php?parent_discoveryid='.$discovery['itemid']
			),
			CViewHelper::showNum($discovery['triggers'])
		],
		[
			new CLink(
				_('Graph prototypes'),
				(new CUrl('graphs.php'))->setArgument('parent_discoveryid', $discovery['itemid'])
			),
			CViewHelper::showNum($discovery['graphs'])
		],
		($discovery['hosts'][0]['flags'] == ZBX_FLAG_DISCOVERY_NORMAL)
			? [
				new CLink(_('Host prototypes'),
					(new CUrl('host_prototypes.php'))->setArgument('parent_discoveryid', $discovery['itemid'])
				),
				CViewHelper::showNum($discovery['hostPrototypes'])
			]
			: '',
		(new CDiv(CHtml::encode($discovery['key_'])))->addClass(ZBX_STYLE_WORDWRAP),
		$discovery['delay'],
		item_type2str($discovery['type']),
		$status,
		makeInformationList($info_icons)
	]);
}

// append table to form
$discoveryForm->addItem([
	$discoveryTable,
	$data['paging'],
	new CActionButtonList('action', 'g_hostdruleid',
		[
			'discoveryrule.massenable' => ['name' => _('Enable'),
				'confirm' =>_('Enable selected discovery rules?')
			],
			'discoveryrule.massdisable' => ['name' => _('Disable'),
				'confirm' =>_('Disable selected discovery rules?')
			],
			'discoveryrule.masscheck_now' => ['name' => _('Execute now'), 'disabled' => $data['is_template']],
			'discoveryrule.massdelete' => ['name' => _('Delete'),
				'confirm' =>_('Delete selected discovery rules?')
			]
		],
		$data['checkbox_hash']
	)
]);

// append form to widget
$widget->addItem($discoveryForm);

$widget->show();
