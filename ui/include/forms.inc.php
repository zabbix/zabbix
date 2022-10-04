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


function prepareSubfilterOutput($label, $data, $subfilter, $subfilterName) {
	CArrayHelper::sort($data, ['value', 'name']);

	$output = [new CTag('h3', true, $label)];

	foreach ($data as $id => $element) {
		$element['name'] = CHtml::encode($element['name']);

		// is activated
		if (str_in_array($id, $subfilter)) {
			$output[] = (new CSpan([
				(new CLinkAction($element['name']))
					->onClick(CHtml::encode(
						'javascript: create_var("zbx_filter", "subfilter_set", "1", false);'.
						'create_var("zbx_filter", '.json_encode($subfilterName.'['.$id.']').', null, true);'
					)),
				' ',
				new CSup($element['count'])
			]))
				->addClass(ZBX_STYLE_NOWRAP)
				->addClass(ZBX_STYLE_SUBFILTER)
				->addClass(ZBX_STYLE_SUBFILTER_ENABLED);
		}
		// isn't activated
		else {
			// subfilter has 0 items
			if ($element['count'] == 0) {
				$output[] = (new CSpan([
					(new CSpan($element['name']))->addClass(ZBX_STYLE_GREY),
					' ',
					new CSup($element['count'])
				]))->addClass(ZBX_STYLE_SUBFILTER);
			}
			else {
				$link = (new CLinkAction($element['name']))
					->onClick(CHtml::encode(
						'javascript: create_var("zbx_filter", "subfilter_set", "1", false);'.
						'create_var("zbx_filter", '.
							json_encode($subfilterName.'['.$id.']').', '.
							json_encode($id).', '.
							'true'.
						');'
					));

				$output[] = (new CSpan([
					$link,
					' ',
					new CSup(($subfilter ? '+' : '').$element['count'])
				]))
					->addClass(ZBX_STYLE_NOWRAP)
					->addClass(ZBX_STYLE_SUBFILTER);
			}
		}
	}

	return $output;
}

function getItemFilterForm(&$items) {
	$filter_groupids			= $_REQUEST['filter_groupids'];
	$filter_hostids				= $_REQUEST['filter_hostids'];
	$filter_application			= $_REQUEST['filter_application'];
	$filter_name				= $_REQUEST['filter_name'];
	$filter_type				= $_REQUEST['filter_type'];
	$filter_key					= $_REQUEST['filter_key'];
	$filter_snmp_oid			= $_REQUEST['filter_snmp_oid'];
	$filter_value_type			= $_REQUEST['filter_value_type'];
	$filter_delay				= $_REQUEST['filter_delay'];
	$filter_history				= $_REQUEST['filter_history'];
	$filter_trends				= $_REQUEST['filter_trends'];
	$filter_status				= $_REQUEST['filter_status'];
	$filter_state				= $_REQUEST['filter_state'];
	$filter_templated_items		= $_REQUEST['filter_templated_items'];
	$filter_with_triggers		= $_REQUEST['filter_with_triggers'];
	$filter_discovery           = $_REQUEST['filter_discovery'];
	$subfilter_hosts			= $_REQUEST['subfilter_hosts'];
	$subfilter_apps				= $_REQUEST['subfilter_apps'];
	$subfilter_types			= $_REQUEST['subfilter_types'];
	$subfilter_value_types		= $_REQUEST['subfilter_value_types'];
	$subfilter_status			= $_REQUEST['subfilter_status'];
	$subfilter_state			= $_REQUEST['subfilter_state'];
	$subfilter_templated_items	= $_REQUEST['subfilter_templated_items'];
	$subfilter_with_triggers	= $_REQUEST['subfilter_with_triggers'];
	$subfilter_discovery        = $_REQUEST['subfilter_discovery'];
	$subfilter_history			= $_REQUEST['subfilter_history'];
	$subfilter_trends			= $_REQUEST['subfilter_trends'];
	$subfilter_interval			= $_REQUEST['subfilter_interval'];

	$filter = (new CFilter(new CUrl('items.php')))
		->setProfile('web.items.filter')
		->setActiveTab(CProfile::get('web.items.filter.active', 1))
		->addVar('subfilter_hosts', $subfilter_hosts)
		->addVar('subfilter_apps', $subfilter_apps)
		->addVar('subfilter_types', $subfilter_types)
		->addVar('subfilter_value_types', $subfilter_value_types)
		->addVar('subfilter_status', $subfilter_status)
		->addVar('subfilter_state', $subfilter_state)
		->addVar('subfilter_templated_items', $subfilter_templated_items)
		->addVar('subfilter_with_triggers', $subfilter_with_triggers)
		->addVar('subfilter_discovery', $subfilter_discovery)
		->addVar('subfilter_history', $subfilter_history)
		->addVar('subfilter_trends', $subfilter_trends)
		->addVar('subfilter_interval', $subfilter_interval);

	$filterColumn1 = new CFormList();
	$filterColumn2 = new CFormList();
	$filterColumn3 = new CFormList();
	$filterColumn4 = new CFormList();

	// type select
	$fTypeVisibility = [];
	$type_select = (new CSelect('filter_type'))
		->setId('filter_type')
		->setValue($filter_type)
		->setFocusableElementId('label-filter-type')
		->addOption(new CSelectOption(-1, _('all')));

	zbx_subarray_push($fTypeVisibility, -1, 'filter_delay_row');

	$item_types = item_type2str();
	unset($item_types[ITEM_TYPE_HTTPTEST]); // httptest items are only for internal zabbix logic

	$type_select->addOptions(CSelect::createOptionsFromArray($item_types));

	foreach ($item_types as $type => $name) {
		if ($type != ITEM_TYPE_TRAPPER && $type != ITEM_TYPE_SNMPTRAP) {
			zbx_subarray_push($fTypeVisibility, $type, 'filter_delay_row');
		}
		if ($type == ITEM_TYPE_SNMP) {
			zbx_subarray_push($fTypeVisibility, $type, 'filter_snmp_oid_row');
		}
	}

	zbx_add_post_js("var filterTypeSwitcher = new CViewSwitcher('filter_type', 'change', ".zbx_jsvalue($fTypeVisibility, true).');');

	// row 1
	$group_filter = !empty($filter_groupids)
		? CArrayHelper::renameObjectsKeys(API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'groupids' => $filter_groupids,
			'editable' => true
		]), ['groupid' => 'id'])
		: [];

	$filterColumn1->addRow((new CLabel(_('Host groups'), 'filter_groupid_ms')),
		(new CMultiSelect([
			'name' => 'filter_groupids[]',
			'object_name' => 'hostGroup',
			'data' => $group_filter,
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => $filter->getName(),
					'dstfld1' => 'filter_groupids_',
					'editable' => true
				]
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
	);

	$filterColumn2->addRow(new CLabel(_('Type'), $type_select->getFocusableElementId()), $type_select);
	$filterColumn3->addRow(new CLabel(_('Type of information'), 'label-filter-value-type'),
		(new CSelect('filter_value_type'))
			->setFocusableElementId('label-filter-value-type')
			->setValue($filter_value_type)
			->addOptions(CSelect::createOptionsFromArray([
				-1 => _('all'),
				ITEM_VALUE_TYPE_UINT64 => _('Numeric (unsigned)'),
				ITEM_VALUE_TYPE_FLOAT => _('Numeric (float)'),
				ITEM_VALUE_TYPE_STR => _('Character'),
				ITEM_VALUE_TYPE_LOG => _('Log'),
				ITEM_VALUE_TYPE_TEXT => _('Text')
			]))
	);
	$filterColumn4->addRow(new CLabel(_('State'), 'label-filter-state'),
		(new CSelect('filter_state'))
			->setId('filter_state')
			->setFocusableElementId('label-filter-state')
			->setValue($filter_state)
			->addOptions(CSelect::createOptionsFromArray([
				-1 => _('all'),
				ITEM_STATE_NORMAL => itemState(ITEM_STATE_NORMAL),
				ITEM_STATE_NOTSUPPORTED => itemState(ITEM_STATE_NOTSUPPORTED)
			]))
	);

	// row 2
	$host_filter = !empty($filter_hostids)
		? CArrayHelper::renameObjectsKeys(API::Host()->get([
			'output' => ['hostid', 'name'],
			'hostids' => $filter_hostids,
			'templated_hosts' => true,
			'editable' => true
		]), ['hostid' => 'id'])
		: [];

	$filterColumn1->addRow((new CLabel(_('Hosts'), 'filter_hostid_ms')),
		(new CMultiSelect([
			'name' => 'filter_hostids[]',
			'object_name' => 'host_templates',
			'data' => $host_filter,
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
		]))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
	);

	$filterColumn2->addRow(_('Update interval'),
		(new CTextBox('filter_delay', $filter_delay))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
		'filter_delay_row'
	);
	$filterColumn4->addRow(new CLabel(_('Status'), 'label-filter-status'),
		(new CSelect('filter_status'))
			->setId('filter_status')
			->setFocusableElementId('label-filter-status')
			->setValue($filter_status)
			->addOptions(CSelect::createOptionsFromArray([
				-1 => _('all'),
				ITEM_STATUS_ACTIVE => item_status2str(ITEM_STATUS_ACTIVE),
				ITEM_STATUS_DISABLED => item_status2str(ITEM_STATUS_DISABLED)
			]))
	);

	// row 3
	$filterColumn1->addRow(_('Application'),
		[
			(new CTextBox('filter_application', $filter_application))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CButton(null, _('Select')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->onClick('return PopUp("popup.generic",jQuery.extend('.
					json_encode([
						'srctbl' => 'applications',
						'srcfld1' => 'name',
						'dstfrm' => $filter->getName(),
						'dstfld1' => 'filter_application',
						'with_applications' => '1'
					]).
					', getFirstMultiselectValue("filter_hostids_")), null, this);'
				)
		]
	);

	$filterColumn3->addRow(_('History'),
		(new CTextBox('filter_history', $filter_history))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
	);
	$filterColumn4->addRow(new CLabel(_('Triggers'), 'label-filter-with-triggers'),
		(new CSelect('filter_with_triggers'))
			->setFocusableElementId('label-filter-with-triggers')
			->setValue($filter_with_triggers)
			->addOptions(CSelect::createOptionsFromArray([
				-1 => _('all'),
				1 => _('With triggers'),
				0 => _('Without triggers')
			]))
	);

	// row 4
	$filterColumn1->addRow(_('Name'),
		(new CTextBox('filter_name', $filter_name))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
	);
	$filterColumn2->addRow(_('SNMP OID'),
		(new CTextBox('filter_snmp_oid', $filter_snmp_oid, '', 255))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
		'filter_snmp_oid_row'
	);
	$filterColumn3->addRow(_('Trends'),
		(new CTextBox('filter_trends', $filter_trends))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
	);
	$filterColumn4->addRow(new CLabel(_('Template'), 'label-filter-templated-items'),
		(new CSelect('filter_templated_items'))
			->setFocusableElementId('label-filter-templated-items')
			->setValue($filter_templated_items)
			->addOptions(CSelect::createOptionsFromArray([
				-1 => _('all'),
				1 => _('Inherited items'),
				0 => _('Not inherited items')
			]))
	);

	// row 5
	$filterColumn1->addRow(_('Key'),
		(new CTextBox('filter_key', $filter_key))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
	);
	$filterColumn4->addRow(new CLabel(_('Discovery'), 'label-filter-discovery'),
		(new CSelect('filter_discovery'))
			->setFocusableElementId('label-filter-discovery')
			->setValue($filter_discovery)
			->addOptions(CSelect::createOptionsFromArray([
				-1 => _('all'),
				ZBX_FLAG_DISCOVERY_CREATED => _('Discovered items'),
				ZBX_FLAG_DISCOVERY_NORMAL => _('Regular items')
			]))
	);

	// subfilters
	$table_subfilter = (new CTableInfo())
		->addRow([
			new CTag('h4', true, [
				_('Subfilter'), SPACE, (new CSpan(_('affects only filtered data')))->addClass(ZBX_STYLE_GREY)
			])
		]);

	// array contains subfilters and number of items in each
	$item_params = [
		'hosts' => [],
		'applications' => [],
		'types' => [],
		'value_types' => [],
		'status' => [],
		'state' => [],
		'templated_items' => [],
		'with_triggers' => [],
		'discovery' => [],
		'history' => [],
		'trends' => [],
		'interval' => []
	];

	$update_interval_parser = new CUpdateIntervalParser(['usermacros' => true]);
	$simple_interval_parser = new CSimpleIntervalParser();

	// generate array with values for subfilters of selected items
	foreach ($items as $item) {
		// hosts
		if ($filter_hostids) {
			$host = reset($item['hosts']);

			if (!isset($item_params['hosts'][$host['hostid']])) {
				$item_params['hosts'][$host['hostid']] = ['name' => $host['name'], 'count' => 0];
			}
			$show_item = true;
			foreach ($item['subfilters'] as $name => $value) {
				if ($name == 'subfilter_hosts') {
					continue;
				}
				$show_item &= $value;
			}
			if ($show_item) {
				$host = reset($item['hosts']);
				$item_params['hosts'][$host['hostid']]['count']++;
			}
		}

		// applications
		if (!empty($item['applications'])) {
			foreach ($item['applications'] as $application) {
				if (!isset($item_params['applications'][$application['name']])) {
					$item_params['applications'][$application['name']] = ['name' => $application['name'], 'count' => 0];
				}
			}
		}
		$show_item = true;
		foreach ($item['subfilters'] as $name => $value) {
			if ($name == 'subfilter_apps') {
				continue;
			}
			$show_item &= $value;
		}
		$sel_app = false;
		if ($show_item) {
			// if any of item applications are selected
			foreach ($item['applications'] as $app) {
				if (str_in_array($app['name'], $subfilter_apps)) {
					$sel_app = true;
					break;
				}
			}
			foreach ($item['applications'] as $app) {
				if (str_in_array($app['name'], $subfilter_apps) || !$sel_app) {
					$item_params['applications'][$app['name']]['count']++;
				}
			}
		}

		// types
		if ($filter_type == -1) {
			if (!isset($item_params['types'][$item['type']])) {
				$item_params['types'][$item['type']] = ['name' => item_type2str($item['type']), 'count' => 0];
			}
			$show_item = true;
			foreach ($item['subfilters'] as $name => $value) {
				if ($name == 'subfilter_types') {
					continue;
				}
				$show_item &= $value;
			}
			if ($show_item) {
				$item_params['types'][$item['type']]['count']++;
			}
		}

		// value types
		if ($filter_value_type == -1) {
			if (!isset($item_params['value_types'][$item['value_type']])) {
				$item_params['value_types'][$item['value_type']] = [
					'name' => itemValueTypeString($item['value_type']),
					'count' => 0
				];
			}

			$show_item = true;
			foreach ($item['subfilters'] as $name => $value) {
				if ($name == 'subfilter_value_types') {
					continue;
				}
				$show_item &= $value;
			}
			if ($show_item) {
				$item_params['value_types'][$item['value_type']]['count']++;
			}
		}

		// status
		if ($filter_status == -1) {
			if (!isset($item_params['status'][$item['status']])) {
				$item_params['status'][$item['status']] = [
					'name' => item_status2str($item['status']),
					'count' => 0
				];
			}
			$show_item = true;
			foreach ($item['subfilters'] as $name => $value) {
				if ($name == 'subfilter_status') {
					continue;
				}
				$show_item &= $value;
			}
			if ($show_item) {
				$item_params['status'][$item['status']]['count']++;
			}
		}

		// state
		if ($filter_state == -1) {
			if (!isset($item_params['state'][$item['state']])) {
				$item_params['state'][$item['state']] = [
					'name' => itemState($item['state']),
					'count' => 0
				];
			}
			$show_item = true;
			foreach ($item['subfilters'] as $name => $value) {
				if ($name == 'subfilter_state') {
					continue;
				}
				$show_item &= $value;
			}
			if ($show_item) {
				$item_params['state'][$item['state']]['count']++;
			}
		}

		// template
		if ($filter_templated_items == -1) {
			if ($item['templateid'] == 0 && !isset($item_params['templated_items'][0])) {
				$item_params['templated_items'][0] = ['name' => _('Not inherited items'), 'count' => 0];
			}
			elseif ($item['templateid'] > 0 && !isset($item_params['templated_items'][1])) {
				$item_params['templated_items'][1] = ['name' => _('Inherited items'), 'count' => 0];
			}
			$show_item = true;
			foreach ($item['subfilters'] as $name => $value) {
				if ($name == 'subfilter_templated_items') {
					continue;
				}
				$show_item &= $value;
			}
			if ($show_item) {
				if ($item['templateid'] == 0) {
					$item_params['templated_items'][0]['count']++;
				}
				else {
					$item_params['templated_items'][1]['count']++;
				}
			}
		}

		// with triggers
		if ($filter_with_triggers == -1) {
			if (count($item['triggers']) == 0 && !isset($item_params['with_triggers'][0])) {
				$item_params['with_triggers'][0] = ['name' => _('Without triggers'), 'count' => 0];
			}
			elseif (count($item['triggers']) > 0 && !isset($item_params['with_triggers'][1])) {
				$item_params['with_triggers'][1] = ['name' => _('With triggers'), 'count' => 0];
			}
			$show_item = true;
			foreach ($item['subfilters'] as $name => $value) {
				if ($name == 'subfilter_with_triggers') {
					continue;
				}
				$show_item &= $value;
			}
			if ($show_item) {
				if (count($item['triggers']) == 0) {
					$item_params['with_triggers'][0]['count']++;
				}
				else {
					$item_params['with_triggers'][1]['count']++;
				}
			}
		}

		// discovery
		if ($filter_discovery == -1) {
			if ($item['flags'] == ZBX_FLAG_DISCOVERY_NORMAL && !isset($item_params['discovery'][0])) {
				$item_params['discovery'][0] = ['name' => _('Regular'), 'count' => 0];
			}
			elseif ($item['flags'] == ZBX_FLAG_DISCOVERY_CREATED && !isset($item_params['discovery'][1])) {
				$item_params['discovery'][1] = ['name' => _('Discovered'), 'count' => 0];
			}
			$show_item = true;
			foreach ($item['subfilters'] as $name => $value) {
				if ($name == 'subfilter_discovery') {
					continue;
				}
				$show_item &= $value;
			}
			if ($show_item) {
				if ($item['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
					$item_params['discovery'][0]['count']++;
				}
				else {
					$item_params['discovery'][1]['count']++;
				}
			}
		}

		// trends
		if ($filter_trends === ''
				&& !in_array($item['value_type'], [ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT])) {
			$trends = $item['trends'];
			$value = $trends;

			if ($simple_interval_parser->parse($trends) == CParser::PARSE_SUCCESS) {
				$value = timeUnitToSeconds($trends);
				$trends = convertUnitsS($value);
			}

			if (!array_key_exists($trends, $item_params['trends'])) {
				$item_params['trends'][$trends] = [
					'name' => $trends,
					'count' => 0,
					'value' => $value
				];
			}

			$show_item = true;

			foreach ($item['subfilters'] as $name => $value) {
				if ($name === 'subfilter_trends') {
					continue;
				}
				$show_item &= $value;
			}

			if ($show_item) {
				$item_params['trends'][$trends]['count']++;
			}
		}

		// history
		if ($filter_history === '') {
			$history = $item['history'];
			$value = $history;

			if ($simple_interval_parser->parse($history) == CParser::PARSE_SUCCESS) {
				$value = timeUnitToSeconds($history);
				$history = convertUnitsS($value);
			}

			if (!array_key_exists($history, $item_params['history'])) {
				$item_params['history'][$history] = [
					'name' => $history,
					'count' => 0,
					'value' => $value
				];
			}

			$show_item = true;

			foreach ($item['subfilters'] as $name => $value) {
				if ($name === 'subfilter_history') {
					continue;
				}
				$show_item &= $value;
			}

			if ($show_item) {
				$item_params['history'][$history]['count']++;
			}
		}

		// interval
		if ($filter_delay === '' && $filter_type != ITEM_TYPE_TRAPPER && $item['type'] != ITEM_TYPE_TRAPPER
				&& $item['type'] != ITEM_TYPE_SNMPTRAP && $item['type'] != ITEM_TYPE_DEPENDENT) {
			// Use temporary variable for delay, because the original will be used for sorting later.
			$delay = $item['delay'];
			$value = $delay;

			if ($update_interval_parser->parse($delay) == CParser::PARSE_SUCCESS) {
				$delay = $update_interval_parser->getDelay();

				// "value" is delay represented in seconds and it is used for sorting the subfilter.
				if ($delay[0] !== '{') {
					$value = timeUnitToSeconds($delay);
					$delay = convertUnitsS($value);
				}
				else {
					$value = $delay;
				}
			}

			if (!array_key_exists($delay, $item_params['interval'])) {
				$item_params['interval'][$delay] = [
					'name' => $delay,
					'count' => 0,
					'value' => $value
				];
			}

			$show_item = true;

			foreach ($item['subfilters'] as $name => $value) {
				if ($name === 'subfilter_interval') {
					continue;
				}
				$show_item &= $value;
			}

			if ($show_item) {
				$item_params['interval'][$delay]['count']++;
			}
		}
	}

	// output
	if ($filter_hostids && count($item_params['hosts']) > 1) {
		$hosts_output = prepareSubfilterOutput(_('Hosts'), $item_params['hosts'], $subfilter_hosts, 'subfilter_hosts');
		$table_subfilter->addRow([$hosts_output]);
	}

	if (!empty($item_params['applications']) && count($item_params['applications']) > 1) {
		$application_output = prepareSubfilterOutput(_('Applications'), $item_params['applications'], $subfilter_apps, 'subfilter_apps');
		$table_subfilter->addRow([$application_output]);
	}

	if ($filter_type == -1 && count($item_params['types']) > 1) {
		$type_output = prepareSubfilterOutput(_('Types'), $item_params['types'], $subfilter_types, 'subfilter_types');
		$table_subfilter->addRow([$type_output]);
	}

	if ($filter_value_type == -1 && count($item_params['value_types']) > 1) {
		$value_types_output = prepareSubfilterOutput(_('Type of information'), $item_params['value_types'], $subfilter_value_types, 'subfilter_value_types');
		$table_subfilter->addRow([$value_types_output]);
	}

	if ($filter_status == -1 && count($item_params['status']) > 1) {
		$status_output = prepareSubfilterOutput(_('Status'), $item_params['status'], $subfilter_status, 'subfilter_status');
		$table_subfilter->addRow([$status_output]);
	}

	if ($filter_state == -1 && count($item_params['state']) > 1) {
		$state_output = prepareSubfilterOutput(_('State'), $item_params['state'], $subfilter_state, 'subfilter_state');
		$table_subfilter->addRow([$state_output]);
	}

	if ($filter_templated_items == -1 && count($item_params['templated_items']) > 1) {
		$templated_items_output = prepareSubfilterOutput(_('Template'), $item_params['templated_items'], $subfilter_templated_items, 'subfilter_templated_items');
		$table_subfilter->addRow([$templated_items_output]);
	}

	if ($filter_with_triggers == -1 && count($item_params['with_triggers']) > 1) {
		$with_triggers_output = prepareSubfilterOutput(_('With triggers'), $item_params['with_triggers'], $subfilter_with_triggers, 'subfilter_with_triggers');
		$table_subfilter->addRow([$with_triggers_output]);
	}

	if ($filter_discovery == -1 && count($item_params['discovery']) > 1) {
		$discovery_output = prepareSubfilterOutput(_('Discovery'), $item_params['discovery'], $subfilter_discovery, 'subfilter_discovery');
		$table_subfilter->addRow([$discovery_output]);
	}

	if (zbx_empty($filter_history) && count($item_params['history']) > 1) {
		$history_output = prepareSubfilterOutput(_('History'), $item_params['history'], $subfilter_history, 'subfilter_history');
		$table_subfilter->addRow([$history_output]);
	}

	if (zbx_empty($filter_trends) && (count($item_params['trends']) > 1)) {
		$trends_output = prepareSubfilterOutput(_('Trends'), $item_params['trends'], $subfilter_trends, 'subfilter_trends');
		$table_subfilter->addRow([$trends_output]);
	}

	if (zbx_empty($filter_delay) && $filter_type != ITEM_TYPE_TRAPPER && count($item_params['interval']) > 1) {
		$interval_output = prepareSubfilterOutput(_('Interval'), $item_params['interval'], $subfilter_interval, 'subfilter_interval');
		$table_subfilter->addRow([$interval_output]);
	}

	$filter->addFilterTab(_('Filter'), [$filterColumn1, $filterColumn2, $filterColumn3, $filterColumn4],
		$table_subfilter
	);

	return $filter;
}

/**
 * Prepare ITEM_TYPE_HTTPAGENT type item data for create or update API calls.
 * - Converts 'query_fields' from array of keys and array of values to array of hash maps for every field.
 * - Converts 'headers' from array of keys and array of values to hash map.
 * - For request method HEAD set retrieve mode to retrieve only headers.
 *
 * @param array $item                       Array of form fields data for ITEM_TYPE_HTTPAGENT item.
 * @param int   $item['request_method']     Request method type.
 * @param array $item['query_fields']       Array of 'name' and 'value' arrays for URL query fields.
 * @param array $item['headers']            Array of 'name' and 'value' arrays for headers.
 *
 * @return array
 */
function prepareItemHttpAgentFormData(array $item) {
	if ($item['request_method'] == HTTPCHECK_REQUEST_HEAD) {
		$item['retrieve_mode'] = HTTPTEST_STEP_RETRIEVE_MODE_HEADERS;
	}

	if ($item['query_fields']) {
		$query_fields = [];

		foreach ($item['query_fields']['name'] as $index => $key) {
			$value = $item['query_fields']['value'][$index];

			if ($key !== '' || $value !== '') {
				$query_fields[] = [$key => $value];
			}
		}
		$item['query_fields'] = $query_fields;
	}

	if ($item['headers']) {
		$headers = [];

		foreach ($item['headers']['name'] as $index => $key) {
			$value = $item['headers']['value'][$index];

			if ($key !== '' || $value !== '') {
				$headers[$key] = $value;
			}
		}

		$item['headers'] = $headers;
	}

	return $item;
}

/**
 * Get data for item edit page.
 *
 * @param array $item                          Item, item prototype, LLD rule or LLD item to take the data from.
 * @param array $options
 * @param bool  $options['is_discovery_rule']
 *
 * @return array
 */
function getItemFormData(array $item = [], array $options = []) {
	$data = [
		'form' => getRequest('form'),
		'form_refresh' => getRequest('form_refresh'),
		'is_discovery_rule' => !empty($options['is_discovery_rule']),
		'parent_discoveryid' => getRequest('parent_discoveryid', 0),
		'itemid' => getRequest('itemid'),
		'limited' => false,
		'interfaceid' => getRequest('interfaceid', 0),
		'name' => getRequest('name', ''),
		'description' => getRequest('description', ''),
		'key' => getRequest('key', ''),
		'master_itemid' => getRequest('master_itemid', 0),
		'hostname' => getRequest('hostname'),
		'delay' => getRequest('delay', ZBX_ITEM_DELAY_DEFAULT),
		'history' => getRequest('history', DB::getDefault('items', 'history')),
		'status' => getRequest('status', isset($_REQUEST['form_refresh']) ? 1 : 0),
		'type' => getRequest('type', 0),
		'snmp_oid' => getRequest('snmp_oid', ''),
		'value_type' => getRequest('value_type', ITEM_VALUE_TYPE_UINT64),
		'trapper_hosts' => getRequest('trapper_hosts', ''),
		'units' => getRequest('units', ''),
		'valuemapid' => getRequest('valuemapid', 0),
		'params' => getRequest('params', ''),
		'trends' => getRequest('trends', DB::getDefault('items', 'trends')),
		'new_application' => getRequest('new_application', ''),
		'applications' => getRequest('applications', []),
		'delay_flex' => array_values(getRequest('delay_flex', [])),
		'ipmi_sensor' => getRequest('ipmi_sensor', ''),
		'authtype' => getRequest('authtype', 0),
		'username' => getRequest('username', ''),
		'password' => getRequest('password', ''),
		'publickey' => getRequest('publickey', ''),
		'privatekey' => getRequest('privatekey', ''),
		'logtimefmt' => getRequest('logtimefmt', ''),
		'valuemaps' => null,
		'possibleHostInventories' => null,
		'alreadyPopulated' => null,
		'initial_item_type' => null,
		'templates' => [],
		'jmx_endpoint' => getRequest('jmx_endpoint', ZBX_DEFAULT_JMX_ENDPOINT),
		'timeout' => getRequest('timeout', DB::getDefault('items', 'timeout')),
		'url' => getRequest('url'),
		'query_fields' => getRequest('query_fields', []),
		'posts' => getRequest('posts'),
		'status_codes' => getRequest('status_codes', DB::getDefault('items', 'status_codes')),
		'follow_redirects' => hasRequest('form_refresh')
			? (int) getRequest('follow_redirects')
			: getRequest('follow_redirects', DB::getDefault('items', 'follow_redirects')),
		'post_type' => getRequest('post_type', DB::getDefault('items', 'post_type')),
		'http_proxy' => getRequest('http_proxy'),
		'headers' => getRequest('headers', []),
		'retrieve_mode' => getRequest('retrieve_mode', DB::getDefault('items', 'retrieve_mode')),
		'request_method' => getRequest('request_method', DB::getDefault('items', 'request_method')),
		'output_format' => getRequest('output_format', DB::getDefault('items', 'output_format')),
		'allow_traps' => getRequest('allow_traps', DB::getDefault('items', 'allow_traps')),
		'ssl_cert_file' => getRequest('ssl_cert_file'),
		'ssl_key_file' => getRequest('ssl_key_file'),
		'ssl_key_password' => getRequest('ssl_key_password'),
		'verify_peer' => getRequest('verify_peer', DB::getDefault('items', 'verify_peer')),
		'verify_host' => getRequest('verify_host', DB::getDefault('items', 'verify_host')),
		'http_authtype' => getRequest('http_authtype', HTTPTEST_AUTH_NONE),
		'http_username' => getRequest('http_username', ''),
		'http_password' => getRequest('http_password', ''),
		'preprocessing' => getRequest('preprocessing', []),
		'preprocessing_script_maxlength' => DB::getFieldLength('item_preproc', 'params')
	];

	if ($data['parent_discoveryid'] != 0) {
		$data['discover'] = hasRequest('form_refresh')
			? getRequest('discover', DB::getDefault('items', 'discover'))
			: (($item && array_key_exists('discover', $item))
				? $item['discover']
				: DB::getDefault('items', 'discover')
			);
	}

	if ($data['type'] == ITEM_TYPE_HTTPAGENT) {
		foreach (['query_fields', 'headers'] as $property) {
			$values = [];

			if (is_array($data[$property]) && array_key_exists('name', $data[$property])
					&& array_key_exists('value', $data[$property])) {
				foreach ($data[$property]['name'] as $index => $key) {
					if (array_key_exists($index, $data[$property]['value'])) {
						$values[] = [$key => $data[$property]['value'][$index]];
					}
				}
			}
			$data[$property] = $values;
		}
	}
	else {
		$data['headers'] = [];
		$data['query_fields'] = [];
	}

	// Dependent item initialization by master_itemid.
	if (array_key_exists('master_item', $item)) {
		$expanded = CMacrosResolverHelper::resolveItemNames([$item['master_item']]);
		$master_item = reset($expanded);
		$data['master_itemid'] = $master_item['itemid'];
		$data['master_itemname'] = $master_item['name_expanded'];
		// Do not initialize item data if only master_item array was passed.
		unset($item['master_item']);
	}

	// hostid
	if ($data['parent_discoveryid'] != 0) {
		$discoveryRule = API::DiscoveryRule()->get([
			'output' => ['hostid'],
			'itemids' => $data['parent_discoveryid'],
			'editable' => true
		]);
		$discoveryRule = reset($discoveryRule);
		$data['hostid'] = $discoveryRule['hostid'];

		$data['new_application_prototype'] = getRequest('new_application_prototype', '');
		$data['application_prototypes'] = getRequest('application_prototypes', []);
	}
	else {
		$data['hostid'] = getRequest('hostid', 0);
	}

	foreach ($data['preprocessing'] as &$step) {
		$step += [
			'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
			'error_handler_params' => ''
		];
	}
	unset($step);

	// types, http items only for internal processes
	$data['types'] = item_type2str();
	unset($data['types'][ITEM_TYPE_HTTPTEST]);
	if ($data['is_discovery_rule']) {
		unset($data['types'][ITEM_TYPE_AGGREGATE],
			$data['types'][ITEM_TYPE_CALCULATED],
			$data['types'][ITEM_TYPE_SNMPTRAP]
		);
	}

	// item
	if (array_key_exists('itemid', $item)) {
		$data['item'] = $item;
		$data['hostid'] = !empty($data['hostid']) ? $data['hostid'] : $data['item']['hostid'];
		$data['limited'] = ($data['item']['templateid'] != 0);
		$data['interfaceid'] = $item['interfaceid'];

		// discovery rule
		if ($data['is_discovery_rule']) {
			$flag = ZBX_FLAG_DISCOVERY_RULE;
		}
		// item prototype
		elseif ($data['parent_discoveryid'] != 0) {
			$flag = ZBX_FLAG_DISCOVERY_PROTOTYPE;
		}
		// plain item
		else {
			$flag = ZBX_FLAG_DISCOVERY_NORMAL;
		}

		$data['templates'] = makeItemTemplatesHtml($item['itemid'], getItemParentTemplates([$item], $flag), $flag);
	}

	// caption
	if ($data['is_discovery_rule']) {
		$data['caption'] = _('Discovery rule');
	}
	else {
		$data['caption'] = ($data['parent_discoveryid'] != 0) ? _('Item prototype') : _('Item');
	}

	// hostname
	if (empty($data['is_discovery_rule']) && empty($data['hostname'])) {
		if (!empty($data['hostid'])) {
			$hostInfo = API::Host()->get([
				'hostids' => $data['hostid'],
				'output' => ['name'],
				'templated_hosts' => true
			]);
			$hostInfo = reset($hostInfo);
			$data['hostname'] = $hostInfo['name'];
		}
		else {
			$data['hostname'] = _('not selected');
		}
	}

	// fill data from item
	if (!hasRequest('form_refresh') && ($item || $data['limited'])) {
		$data['name'] = $data['item']['name'];
		$data['description'] = $data['item']['description'];
		$data['key'] = $data['item']['key_'];
		$data['interfaceid'] = $data['item']['interfaceid'];
		$data['type'] = $data['item']['type'];
		$data['snmp_oid'] = $data['item']['snmp_oid'];
		$data['value_type'] = $data['item']['value_type'];
		$data['trapper_hosts'] = $data['item']['trapper_hosts'];
		$data['units'] = $data['item']['units'];
		$data['valuemapid'] = $data['item']['valuemapid'];
		$data['hostid'] = $data['item']['hostid'];
		$data['params'] = $data['item']['params'];
		$data['ipmi_sensor'] = $data['item']['ipmi_sensor'];
		$data['authtype'] = $data['item']['authtype'];
		$data['username'] = $data['item']['username'];
		$data['password'] = $data['item']['password'];
		$data['publickey'] = $data['item']['publickey'];
		$data['privatekey'] = $data['item']['privatekey'];
		$data['logtimefmt'] = $data['item']['logtimefmt'];
		$data['jmx_endpoint'] = $data['item']['jmx_endpoint'];
		$data['new_application'] = getRequest('new_application', '');
		// ITEM_TYPE_HTTPAGENT
		$data['timeout'] = $data['item']['timeout'];
		$data['url'] = $data['item']['url'];
		$data['query_fields'] = $data['item']['query_fields'];
		$data['posts'] = $data['item']['posts'];
		$data['status_codes'] = $data['item']['status_codes'];
		$data['follow_redirects'] = $data['item']['follow_redirects'];
		$data['post_type'] = $data['item']['post_type'];
		$data['http_proxy'] = $data['item']['http_proxy'];
		$data['headers'] = $data['item']['headers'];
		$data['retrieve_mode'] = $data['item']['retrieve_mode'];
		$data['request_method'] = $data['item']['request_method'];
		$data['allow_traps'] = $data['item']['allow_traps'];
		$data['ssl_cert_file'] = $data['item']['ssl_cert_file'];
		$data['ssl_key_file'] = $data['item']['ssl_key_file'];
		$data['ssl_key_password'] = $data['item']['ssl_key_password'];
		$data['verify_peer'] = $data['item']['verify_peer'];
		$data['verify_host'] = $data['item']['verify_host'];
		$data['http_authtype'] = $data['item']['authtype'];
		$data['http_username'] = $data['item']['username'];
		$data['http_password'] = $data['item']['password'];

		if ($data['type'] == ITEM_TYPE_HTTPAGENT) {
			// Convert hash to array where every item is hash for single key value pair as it is used by view.
			$headers = [];

			foreach ($data['headers'] as $key => $value) {
				$headers[] = [$key => $value];
			}

			$data['headers'] = $headers;
		}

		$data['preprocessing'] = $data['item']['preprocessing'];

		if (!$data['is_discovery_rule']) {
			$data['output_format'] = $data['item']['output_format'];
		}

		if ($data['parent_discoveryid'] != 0) {
			$data['new_application_prototype'] = getRequest('new_application_prototype', '');
		}

		if (!$data['limited'] || !isset($_REQUEST['form_refresh'])) {
			$data['delay'] = $data['item']['delay'];

			$update_interval_parser = new CUpdateIntervalParser([
				'usermacros' => true,
				'lldmacros' => ($data['parent_discoveryid'] != 0)
			]);

			if ($update_interval_parser->parse($data['delay']) == CParser::PARSE_SUCCESS) {
				$data['delay'] = $update_interval_parser->getDelay();

				if ($data['delay'][0] !== '{') {
					$delay = timeUnitToSeconds($data['delay']);

					if ($delay == 0 && ($data['type'] == ITEM_TYPE_TRAPPER || $data['type'] == ITEM_TYPE_SNMPTRAP
							|| $data['type'] == ITEM_TYPE_DEPENDENT)) {
						$data['delay'] = ZBX_ITEM_DELAY_DEFAULT;
					}
				}

				foreach ($update_interval_parser->getIntervals() as $interval) {
					if ($interval['type'] == ITEM_DELAY_FLEXIBLE) {
						$data['delay_flex'][] = [
							'delay' => $interval['update_interval'],
							'period' => $interval['time_period'],
							'type' => ITEM_DELAY_FLEXIBLE
						];
					}
					else {
						$data['delay_flex'][] = [
							'schedule' => $interval['interval'],
							'type' => ITEM_DELAY_SCHEDULING
						];
					}
				}
			}
			else {
				$data['delay'] = ZBX_ITEM_DELAY_DEFAULT;
			}

			$data['history'] = $data['item']['history'];
			$data['status'] = $data['item']['status'];
			$data['trends'] = $data['item']['trends'];

			$data['applications'] = array_unique(zbx_array_merge($data['applications'], get_applications_by_itemid($data['itemid'])));

			if ($data['parent_discoveryid'] != 0) {
				/*
				 * Get a list of application prototypes assigned to item prototype. Don't select distinct names,
				 * since database can be accidentally created case insensitive.
				 */
				$application_prototypes = DBfetchArray(DBselect(
					'SELECT ap.name'.
					' FROM application_prototype ap,item_application_prototype iap'.
					' WHERE ap.application_prototypeid=iap.application_prototypeid'.
						' AND ap.itemid='.zbx_dbstr($data['parent_discoveryid']).
						' AND iap.itemid='.zbx_dbstr($data['itemid'])
				));

				// Merge form submitted data with data existing in DB to find diff and correctly display ListBox.
				$data['application_prototypes'] = array_unique(
					zbx_array_merge($data['application_prototypes'], zbx_objectValues($application_prototypes, 'name'))
				);
			}
		}
	}

	if (!$data['delay_flex']) {
		$data['delay_flex'][] = ['delay' => '', 'period' => '', 'type' => ITEM_DELAY_FLEXIBLE];
	}

	// applications
	if (count($data['applications']) == 0) {
		array_push($data['applications'], 0);
	}
	$data['db_applications'] = DBfetchArray(DBselect(
		'SELECT DISTINCT a.applicationid,a.name'.
		' FROM applications a'.
		' WHERE a.hostid='.zbx_dbstr($data['hostid']).
			(($data['parent_discoveryid'] != 0) ? ' AND a.flags='.ZBX_FLAG_DISCOVERY_NORMAL : '')
	));
	order_result($data['db_applications'], 'name');

	if ($data['parent_discoveryid'] != 0) {
		// Make the application prototype list no appearing empty, but filling it with "-None-" as first element.
		if (count($data['application_prototypes']) == 0) {
			$data['application_prototypes'][] = 0;
		}

		// Get a list of application prototypes by discovery rule.
		$data['db_application_prototypes'] = DBfetchArray(DBselect(
			'SELECT ap.application_prototypeid,ap.name'.
			' FROM application_prototype ap'.
			' WHERE ap.itemid='.zbx_dbstr($data['parent_discoveryid'])
		));
		order_result($data['db_application_prototypes'], 'name');
	}

	// interfaces
	$data['interfaces'] = API::HostInterface()->get([
		'hostids' => $data['hostid'],
		'output' => API_OUTPUT_EXTEND
	]);
	// Sort interfaces to be listed starting with one selected as 'main'.
	CArrayHelper::sort($data['interfaces'], [
		['field' => 'main', 'order' => ZBX_SORT_DOWN],
		['field' => 'interfaceid','order' => ZBX_SORT_UP]
	]);

	if ($data['limited'] || (array_key_exists('item', $data) && $data['parent_discoveryid'] == 0
			&& $data['item']['flags'] == ZBX_FLAG_DISCOVERY_CREATED)) {
		if ($data['valuemapid'] != 0) {
			$valuemaps = API::ValueMap()->get([
				'output' => ['name'],
				'valuemapids' => [$data['valuemapid']]
			]);

			if ($valuemaps) {
				$data['valuemaps'] = $valuemaps[0]['name'];
			}
		}
	}
	else {
		$data['valuemaps'] = API::ValueMap()->get([
			'output' => ['valuemapid', 'name']
		]);

		CArrayHelper::sort($data['valuemaps'], ['name']);
	}

	// possible host inventories
	if ($data['parent_discoveryid'] == 0) {
		$data['possibleHostInventories'] = getHostInventories();

		// get already populated fields by other items
		$data['alreadyPopulated'] = API::item()->get([
			'output' => ['inventory_link'],
			'filter' => ['hostid' => $data['hostid']],
			'nopermissions' => true
		]);
		$data['alreadyPopulated'] = zbx_toHash($data['alreadyPopulated'], 'inventory_link');
	}

	// unset ssh auth fields
	if ($data['type'] != ITEM_TYPE_SSH) {
		$data['authtype'] = ITEM_AUTHTYPE_PASSWORD;
		$data['publickey'] = '';
		$data['privatekey'] = '';
	}

	if ($data['type'] != ITEM_TYPE_DEPENDENT) {
		$data['master_itemid'] = 0;
	}

	return $data;
}

/**
 * Get list of item pre-processing data and return a prepared HTML object.
 *
 * @param CForm  $form                                     Form object to where add pre-processing list.
 * @param array  $preprocessing                            Array of item pre-processing steps.
 * @param string $preprocessing[]['type']                  Pre-processing step type.
 * @param array  $preprocessing[]['params']                Additional parameters used by pre-processing.
 * @param string $preprocessing[]['error_handler']         Action type used in case of pre-processing step failure.
 * @param string $preprocessing[]['error_handler_params']  Error handler parameters.
 * @param bool   $readonly                                 True if fields should be read only.
 * @param array  $types                                    Supported pre-processing types.
 *
 * @return CList
 */
function getItemPreprocessing(CForm $form, array $preprocessing, $readonly, array $types) {
	$script_maxlength = DB::getFieldLength('item_preproc', 'params');
	$preprocessing_list = (new CList())
		->setId('preprocessing')
		->addClass('preprocessing-list')
		->addClass('list-numbered')
		->addItem(
			(new CListItem([
				(new CDiv(_('Name')))->addClass('step-name'),
				(new CDiv(_('Parameters')))->addClass('step-parameters'),
				(new CDiv(_('Custom on fail')))->addClass('step-on-fail'),
				(new CDiv(_('Actions')))->addClass('step-action')
			]))
				->addClass('preprocessing-list-head')
				->addStyle(!$preprocessing ? 'display: none;' : null)
		);

	$sortable = (count($preprocessing) > 1 && !$readonly);

	$i = 0;

	foreach ($preprocessing as $step) {
		// Create a select with preprocessing types.
		$preproc_types_select = (new CSelect('preprocessing['.$i.'][type]'))
			->setId('preprocessing_'.$i.'_type')
			->setValue($step['type'])
			->setReadonly($readonly)
			->setWidthAuto();

		foreach (get_preprocessing_types(null, true, $types) as $group) {
			$opt_group = new CSelectOptionGroup($group['label']);

			foreach ($group['types'] as $type => $label) {
				$opt_group->addOption(new CSelectOption($type, $label));
			}

			$preproc_types_select->addOptionGroup($opt_group);
		}

		// Depending on preprocessing type, display corresponding params field and placeholders.
		$params = '';

		// Create a primary param text box, so it can be hidden if necessary.
		$step_param_0_value = array_key_exists('params', $step) ? $step['params'][0] : '';
		$step_param_0 = (new CTextBox('preprocessing['.$i.'][params][0]', $step_param_0_value))
			->setTitle($step_param_0_value)
			->setReadonly($readonly);

		// Create a secondary param text box, so it can be hidden if necessary.
		$step_param_1_value = (array_key_exists('params', $step) && array_key_exists(1, $step['params']))
			? $step['params'][1]
			: '';
		$step_param_1 = (new CTextBox('preprocessing['.$i.'][params][1]', $step_param_1_value))
			->setTitle($step_param_1_value)
			->setReadonly($readonly);

		// Add corresponding placeholders and show or hide text boxes.
		switch ($step['type']) {
			case ZBX_PREPROC_MULTIPLIER:
				$params = $step_param_0
					->setAttribute('placeholder', _('number'))
					->setWidth(ZBX_TEXTAREA_NUMERIC_BIG_WIDTH);
				break;

			case ZBX_PREPROC_RTRIM:
			case ZBX_PREPROC_LTRIM:
			case ZBX_PREPROC_TRIM:
				$params = $step_param_0
					->setAttribute('placeholder', _('list of characters'))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH);
				break;

			case ZBX_PREPROC_XPATH:
			case ZBX_PREPROC_ERROR_FIELD_XML:
				$params = $step_param_0->setAttribute('placeholder', _('XPath'));
				break;

			case ZBX_PREPROC_JSONPATH:
			case ZBX_PREPROC_ERROR_FIELD_JSON:
				$params = $step_param_0->setAttribute('placeholder', _('$.path.to.node'));
				break;

			case ZBX_PREPROC_REGSUB:
			case ZBX_PREPROC_ERROR_FIELD_REGEX:
				$params = [
					$step_param_0->setAttribute('placeholder', _('pattern')),
					$step_param_1->setAttribute('placeholder', _('output'))
				];
				break;

			case ZBX_PREPROC_VALIDATE_RANGE:
				$params = [
					$step_param_0->setAttribute('placeholder', _('min')),
					$step_param_1->setAttribute('placeholder', _('max'))
				];
				break;

			case ZBX_PREPROC_VALIDATE_REGEX:
			case ZBX_PREPROC_VALIDATE_NOT_REGEX:
				$params = $step_param_0->setAttribute('placeholder', _('pattern'));
				break;

			case ZBX_PREPROC_THROTTLE_TIMED_VALUE:
				$params = $step_param_0
					->setAttribute('placeholder', _('seconds'))
					->setWidth(ZBX_TEXTAREA_NUMERIC_BIG_WIDTH);
				break;

			case ZBX_PREPROC_SCRIPT:
				$params = new CMultilineInput($step_param_0->getName(), $step_param_0_value, [
					'title' => _('JavaScript'),
					'placeholder' => _('script'),
					'placeholder_textarea' => 'return value',
					'label_before' => 'function (value) {',
					'label_after' => '}',
					'grow' => 'auto',
					'rows' => 0,
					'maxlength' => $script_maxlength,
					'readonly' => $readonly
				]);
				break;

			case ZBX_PREPROC_PROMETHEUS_PATTERN:
				$params = [
					$step_param_0->setAttribute('placeholder',
						_('<metric name>{<label name>="<label value>", ...} == <value>')
					),
					$step_param_1->setAttribute('placeholder', _('<label name>'))
				];
				break;

			case ZBX_PREPROC_PROMETHEUS_TO_JSON:
				$params = $step_param_0->setAttribute('placeholder',
					_('<metric name>{<label name>="<label value>", ...} == <value>')
				);
				break;

			// ZBX-16642
			case ZBX_PREPROC_CSV_TO_JSON:
				$step_param_2_value = (array_key_exists('params', $step) && array_key_exists(2, $step['params']))
					? $step['params'][2]
					: ZBX_PREPROC_CSV_NO_HEADER;

				$params = [
					$step_param_0
						->setAttribute('placeholder', ',')
						->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
						->setAttribute('maxlength', 1),
					$step_param_1
						->setAttribute('placeholder', '"')
						->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
						->setAttribute('maxlength', 1),
					(new CCheckBox('preprocessing['.$i.'][params][2]', ZBX_PREPROC_CSV_HEADER))
						->setLabel(_('With header row'))
						->setChecked($step_param_2_value == ZBX_PREPROC_CSV_HEADER)
						->setReadonly($readonly)
				];
				break;

			case ZBX_PREPROC_STR_REPLACE:
				$params = [
					$step_param_0->setAttribute('placeholder', _('search string')),
					$step_param_1->setAttribute('placeholder', _('replacement'))
				];
				break;
		}

		// Create checkbox "Custom on fail" and enable or disable depending on preprocessing type.
		$on_fail = new CCheckBox('preprocessing['.$i.'][on_fail]');

		switch ($step['type']) {
			case ZBX_PREPROC_RTRIM:
			case ZBX_PREPROC_LTRIM:
			case ZBX_PREPROC_TRIM:
			case ZBX_PREPROC_THROTTLE_VALUE:
			case ZBX_PREPROC_THROTTLE_TIMED_VALUE:
			case ZBX_PREPROC_SCRIPT:
			case ZBX_PREPROC_STR_REPLACE:
				$on_fail->setEnabled(false);
				break;

			default:
				$on_fail->setEnabled(!$readonly);

				if ($step['error_handler'] != ZBX_PREPROC_FAIL_DEFAULT) {
					$on_fail->setChecked(true);
				}
				break;
		}

		$error_handler = (new CRadioButtonList('preprocessing['.$i.'][error_handler]',
			($step['error_handler'] == ZBX_PREPROC_FAIL_DEFAULT)
				? ZBX_PREPROC_FAIL_DISCARD_VALUE
				: (int) $step['error_handler']
		))
			->addValue(_('Discard value'), ZBX_PREPROC_FAIL_DISCARD_VALUE)
			->addValue(_('Set value to'), ZBX_PREPROC_FAIL_SET_VALUE)
			->addValue(_('Set error to'), ZBX_PREPROC_FAIL_SET_ERROR)
			->setModern(true);

		$error_handler_params = (new CTextBox('preprocessing['.$i.'][error_handler_params]',
			$step['error_handler_params'])
		)->setTitle($step['error_handler_params']);

		if ($step['error_handler'] == ZBX_PREPROC_FAIL_DEFAULT) {
			$error_handler->setEnabled(false);
		}

		if ($step['error_handler'] == ZBX_PREPROC_FAIL_DEFAULT
				|| $step['error_handler'] == ZBX_PREPROC_FAIL_DISCARD_VALUE) {
			$error_handler_params
				->setEnabled(false)
				->addStyle('display: none;');
		}

		$on_fail_options = (new CDiv([
			new CLabel(_('Custom on fail')),
			$error_handler->setReadonly($readonly),
			$error_handler_params->setReadonly($readonly)
		]))->addClass('on-fail-options');

		if ($step['error_handler'] == ZBX_PREPROC_FAIL_DEFAULT) {
			$on_fail_options->addStyle('display: none;');
		}

		$preprocessing_list->addItem(
			(new CListItem([
				(new CDiv([
					(new CDiv())
						->addClass(ZBX_STYLE_DRAG_ICON)
						->addClass(!$sortable ? ZBX_STYLE_DISABLED : null),
					(new CDiv($preproc_types_select))
						->addClass('list-numbered-item')
						->addClass('step-name'),
					(new CDiv($params))->addClass('step-parameters'),
					(new CDiv($on_fail))->addClass('step-on-fail'),
					(new CDiv([
						(new CButton('preprocessing['.$i.'][test]', _('Test')))
							->addClass(ZBX_STYLE_BTN_LINK)
							->addClass('preprocessing-step-test')
							->removeId(),
						(new CButton('preprocessing['.$i.'][remove]', _('Remove')))
							->addClass(ZBX_STYLE_BTN_LINK)
							->addClass('element-table-remove')
							->setEnabled(!$readonly)
							->removeId()
					]))->addClass('step-action')
				]))->addClass('preprocessing-step'),
				$on_fail_options
			]))
				->addClass('preprocessing-list-item')
				->addClass('sortable')
				->setAttribute('data-step', $i)
		);

		$i++;
	}

	$preprocessing_list->addItem(
		(new CListItem([
			(new CDiv(
				(new CButton('param_add', _('Add')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-add')
					->setEnabled(!$readonly)
			))->addClass('step-action'),
			(new CDiv(
				(new CButton('preproc_test_all', _('Test all steps')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addStyle(($i > 0) ? null : 'display: none')
			))->addClass('step-action')
		]))->addClass('preprocessing-list-foot')
	);

	return $preprocessing_list;
}

/**
 * Prepares data to copy items/triggers/graphs.
 *
 * @param string      $elements_field
 * @param null|string $title
 *
 * @return array
 */
function getCopyElementsFormData($elements_field, $title = null) {
	$data = [
		'title' => $title,
		'elements_field' => $elements_field,
		'elements' => getRequest($elements_field, []),
		'copy_type' => getRequest('copy_type', COPY_TYPE_TO_HOST_GROUP),
		'copy_targetids' => getRequest('copy_targetids', []),
		'hostid' => getRequest('hostid', 0)
	];

	if (!$data['elements'] || !is_array($data['elements'])) {
		show_error_message(_('Incorrect list of items.'));

		return $data;
	}

	if ($data['copy_targetids']) {
		switch ($data['copy_type']) {
			case COPY_TYPE_TO_HOST_GROUP:
				$data['copy_targetids'] = CArrayHelper::renameObjectsKeys(API::HostGroup()->get([
					'output' => ['groupid', 'name'],
					'groupids' => $data['copy_targetids'],
					'editable' => true
				]), ['groupid' => 'id']);
				break;

			case COPY_TYPE_TO_HOST:
				$data['copy_targetids'] = CArrayHelper::renameObjectsKeys(API::Host()->get([
					'output' => ['hostid', 'name'],
					'hostids' => $data['copy_targetids'],
					'editable' => true
				]), ['hostid' => 'id']);
				break;

			case COPY_TYPE_TO_TEMPLATE:
				$data['copy_targetids'] = CArrayHelper::renameObjectsKeys(API::Template()->get([
					'output' => ['templateid', 'name'],
					'templateids' => $data['copy_targetids'],
					'editable' => true
				]), ['templateid' => 'id']);
		}
	}

	return $data;
}

function getTriggerMassupdateFormData() {
	$data = [
		'visible' => getRequest('visible', []),
		'dependencies' => getRequest('dependencies', []),
		'tags' => getRequest('tags', []),
		'mass_update_tags' => getRequest('mass_update_tags', ZBX_ACTION_ADD),
		'manual_close' => getRequest('manual_close', ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED),
		'massupdate' => getRequest('massupdate', 1),
		'parent_discoveryid' => getRequest('parent_discoveryid'),
		'g_triggerid' => getRequest('g_triggerid', []),
		'priority' => getRequest('priority', 0),
		'config' => select_config(),
		'hostid' => getRequest('hostid', 0)
	];

	if ($data['dependencies']) {
		$dependencyTriggers = API::Trigger()->get([
			'output' => ['triggerid', 'description', 'flags'],
			'selectHosts' => ['hostid', 'name'],
			'triggerids' => $data['dependencies'],
			'preservekeys' => true
		]);

		if ($data['parent_discoveryid']) {
			$dependencyTriggerPrototypes = API::TriggerPrototype()->get([
				'output' => ['triggerid', 'description', 'flags'],
				'selectHosts' => ['hostid', 'name'],
				'triggerids' => $data['dependencies'],
				'preservekeys' => true
			]);
			$data['dependencies'] = $dependencyTriggers + $dependencyTriggerPrototypes;
		}
		else {
			$data['dependencies'] = $dependencyTriggers;
		}
	}

	foreach ($data['dependencies'] as &$dependency) {
		order_result($dependency['hosts'], 'name', ZBX_SORT_UP);
	}
	unset($dependency);

	order_result($data['dependencies'], 'description', ZBX_SORT_UP);

	if (!$data['tags']) {
		$data['tags'][] = ['tag' => '', 'value' => ''];
	}

	return $data;
}

/**
 * Generate data for the trigger configuration form.
 *
 * @param array       $data                                     Trigger data array.
 * @param string      $data['form']                             Form action.
 * @param string      $data['form_refresh']                     Form refresh.
 * @param null|string $data['parent_discoveryid']               Parent discovery ID.
 * @param array       $data['dependencies']                     Trigger dependencies.
 * @param array       $data['db_dependencies']                  DB trigger dependencies.
 * @param string      $data['triggerid']                        Trigger ID.
 * @param string      $data['expression']                       Trigger expression.
 * @param string      $data['recovery_expression']              Trigger recovery expression.
 * @param string      $data['expr_temp']                        Trigger temporary expression.
 * @param string      $data['recovery_expr_temp']               Trigger temporary recovery expression.
 * @param string      $data['recovery_mode']                    Trigger recovery mode.
 * @param string      $data['description']                      Trigger description.
 * @param int         $data['type']                             Trigger problem event generation mode.
 * @param string      $data['priority']                         Trigger severity.
 * @param int         $data['status']                           Trigger status.
 * @param string      $data['comments']                         Trigger description.
 * @param string      $data['url']                              Trigger URL.
 * @param string      $data['expression_constructor']           Trigger expression constructor mode.
 * @param string      $data['recovery_expression_constructor']  Trigger recovery expression constructor mode.
 * @param bool        $data['limited']                          Templated trigger.
 * @param array       $data['templates']                        Trigger templates.
 * @param string      $data['hostid']                           Host ID.
 * @param string      $data['expression_action']                Trigger expression action.
 * @param string      $data['recovery_expression_action']       Trigger recovery expression action.
 *
 * @return array
 */
function getTriggerFormData(array $data) {
	if ($data['triggerid'] !== null) {
		// Get trigger.
		$options = [
			'output' => API_OUTPUT_EXTEND,
			'selectHosts' => ['hostid'],
			'triggerids' => $data['triggerid']
		];

		if (!hasRequest('form_refresh')) {
			$options['selectTags'] = ['tag', 'value'];
		}

		if ($data['show_inherited_tags']) {
			$options['selectItems'] = ['itemid', 'templateid', 'flags'];
		}

		if ($data['parent_discoveryid'] === null) {
			$options['selectDiscoveryRule'] = ['itemid', 'name', 'templateid'];
			$options['selectTriggerDiscovery'] = ['parent_triggerid'];
			$triggers = API::Trigger()->get($options);
			$flag = ZBX_FLAG_DISCOVERY_NORMAL;
		}
		else {
			$triggers = API::TriggerPrototype()->get($options);
			$flag = ZBX_FLAG_DISCOVERY_PROTOTYPE;
		}

		$triggers = CMacrosResolverHelper::resolveTriggerExpressions($triggers,
			['sources' => ['expression', 'recovery_expression']]
		);

		$trigger = reset($triggers);

		if (!hasRequest('form_refresh')) {
			$data['tags'] = $trigger['tags'];
		}

		// Get templates.
		$data['templates'] = makeTriggerTemplatesHtml($trigger['triggerid'],
			getTriggerParentTemplates([$trigger], $flag), $flag
		);

		if ($data['show_inherited_tags']) {
			if ($data['parent_discoveryid'] === null) {
				if ($trigger['discoveryRule']) {
					$item_parent_templates = getItemParentTemplates([$trigger['discoveryRule']],
						ZBX_FLAG_DISCOVERY_RULE
					)['templates'];
				}
				else {
					$item_parent_templates = getItemParentTemplates($trigger['items'],
						ZBX_FLAG_DISCOVERY_NORMAL
					)['templates'];
				}
			}
			else {
				$items = [];
				$item_prototypes = [];

				foreach ($trigger['items'] as $item) {
					if ($item['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
						$items[] = $item;
					}
					else {
						$item_prototypes[] = $item;
					}
				}

				$item_parent_templates = getItemParentTemplates($items, ZBX_FLAG_DISCOVERY_NORMAL)['templates']
					+ getItemParentTemplates($item_prototypes, ZBX_FLAG_DISCOVERY_PROTOTYPE)['templates'];
			}
			unset($item_parent_templates[0]);

			$db_templates = $item_parent_templates
				? API::Template()->get([
					'output' => ['templateid'],
					'selectTags' => ['tag', 'value'],
					'templateids' => array_keys($item_parent_templates),
					'preservekeys' => true
				])
				: [];

			$inherited_tags = [];

			foreach ($item_parent_templates as $templateid => $template) {
				if (array_key_exists($templateid, $db_templates)) {
					foreach ($db_templates[$templateid]['tags'] as $tag) {
						if (array_key_exists($tag['tag'], $inherited_tags)
								&& array_key_exists($tag['value'], $inherited_tags[$tag['tag']])) {
							$inherited_tags[$tag['tag']][$tag['value']]['parent_templates'] += [
								$templateid => $template
							];
						}
						else {
							$inherited_tags[$tag['tag']][$tag['value']] = $tag + [
								'parent_templates' => [$templateid => $template],
								'type' => ZBX_PROPERTY_INHERITED
							];
						}
					}
				}
			}

			$db_hosts = API::Host()->get([
				'output' => [],
				'selectTags' => ['tag', 'value'],
				'hostids' => $data['hostid'],
				'templated_hosts' => true
			]);

			if ($db_hosts) {
				foreach ($db_hosts[0]['tags'] as $tag) {
					$inherited_tags[$tag['tag']][$tag['value']] = $tag;
					$inherited_tags[$tag['tag']][$tag['value']]['type'] = ZBX_PROPERTY_INHERITED;
				}
			}

			foreach ($data['tags'] as $tag) {
				if (array_key_exists($tag['tag'], $inherited_tags)
						&& array_key_exists($tag['value'], $inherited_tags[$tag['tag']])) {
					$inherited_tags[$tag['tag']][$tag['value']]['type'] = ZBX_PROPERTY_BOTH;
				}
				else {
					$inherited_tags[$tag['tag']][$tag['value']] = $tag + ['type' => ZBX_PROPERTY_OWN];
				}
			}

			$data['tags'] = [];

			foreach ($inherited_tags as $tag) {
				foreach ($tag as $value) {
					$data['tags'][] = $value;
				}
			}
		}

		$data['limited'] = ($trigger['templateid'] != 0);

		// Select first host from triggers if no matching value is given.
		$hosts = $trigger['hosts'];
		if (count($hosts) > 0 && !in_array(['hostid' => $data['hostid']], $hosts)) {
			$host = reset($hosts);
			$data['hostid'] = $host['hostid'];
		}
	}

	// tags
	if (!$data['tags']) {
		$data['tags'][] = ['tag' => '', 'value' => ''];
	}
	else {
		CArrayHelper::sort($data['tags'], ['tag', 'value']);
	}

	if ((!empty($data['triggerid']) && !isset($_REQUEST['form_refresh'])) || $data['limited']) {
		$data['expression'] = $trigger['expression'];
		$data['recovery_expression'] = $trigger['recovery_expression'];

		if (!$data['limited'] || !isset($_REQUEST['form_refresh'])) {
			$data['description'] = $trigger['description'];
			$data['opdata'] = $trigger['opdata'];
			$data['type'] = $trigger['type'];
			$data['recovery_mode'] = $trigger['recovery_mode'];
			$data['correlation_mode'] = $trigger['correlation_mode'];
			$data['correlation_tag'] = $trigger['correlation_tag'];
			$data['manual_close'] = $trigger['manual_close'];
			$data['priority'] = $trigger['priority'];
			$data['status'] = $trigger['status'];
			$data['comments'] = $trigger['comments'];
			$data['url'] = $trigger['url'];

			if ($data['parent_discoveryid'] !== null) {
				$data['discover'] = $trigger['discover'];
			}

			$db_triggers = DBselect(
				'SELECT t.triggerid,t.description'.
				' FROM triggers t,trigger_depends d'.
				' WHERE t.triggerid=d.triggerid_up'.
					' AND d.triggerid_down='.zbx_dbstr($data['triggerid'])
			);
			while ($db_trigger = DBfetch($db_triggers)) {
				if (uint_in_array($db_trigger['triggerid'], $data['dependencies'])) {
					continue;
				}
				array_push($data['dependencies'], $db_trigger['triggerid']);
			}
		}
	}

	$readonly = false;
	if ($data['triggerid'] !== null) {
		$data['flags'] = $trigger['flags'];

		if ($data['parent_discoveryid'] === null) {
			$data['discoveryRule'] = $trigger['discoveryRule'];
			$data['triggerDiscovery'] = $trigger['triggerDiscovery'];
		}

		if ($trigger['flags'] == ZBX_FLAG_DISCOVERY_CREATED || $data['limited']) {
			$readonly = true;
		}
	}

	// Trigger expression constructor.
	if ($data['expression_constructor'] == IM_TREE) {
		$analyze = analyzeExpression($data['expression'], TRIGGER_EXPRESSION);

		if ($analyze !== false) {
			list($data['expression_formula'], $data['expression_tree']) = $analyze;

			if ($data['expression_action'] !== '' && $data['expression_tree'] !== null) {
				$new_expr = remakeExpression($data['expression'], $_REQUEST['expr_target_single'],
					$data['expression_action'], $data['expr_temp']
				);

				if ($new_expr !== false) {
					$data['expression'] = $new_expr;
					$analyze = analyzeExpression($data['expression'], TRIGGER_EXPRESSION);

					if ($analyze !== false) {
						list($data['expression_formula'], $data['expression_tree']) = $analyze;
					}
					else {
						show_messages(false, '', _('Expression syntax error.'));
					}

					$data['expr_temp'] = '';
				}
				else {
					show_messages(false, '', _('Expression syntax error.'));
				}
			}

			$data['expression_field_name'] = 'expr_temp';
			$data['expression_field_value'] = $data['expr_temp'];
			$data['expression_field_readonly'] = true;
		}
		else {
			show_messages(false, '', _('Expression syntax error.'));
			$data['expression_field_name'] = 'expression';
			$data['expression_field_value'] = $data['expression'];
			$data['expression_field_readonly'] = $readonly;
			$data['expression_constructor'] = IM_ESTABLISHED;
		}
	}
	elseif ($data['expression_constructor'] != IM_TREE) {
		$data['expression_field_name'] = 'expression';
		$data['expression_field_value'] = $data['expression'];
		$data['expression_field_readonly'] = $readonly;
	}

	// Trigger recovery expression constructor.
	if ($data['recovery_expression_constructor'] == IM_TREE) {
		$analyze = analyzeExpression($data['recovery_expression'], TRIGGER_RECOVERY_EXPRESSION);

		if ($analyze !== false) {
			list($data['recovery_expression_formula'], $data['recovery_expression_tree']) = $analyze;

			if ($data['recovery_expression_action'] !== '' && $data['recovery_expression_tree'] !== null) {
				$new_expr = remakeExpression($data['recovery_expression'], $_REQUEST['recovery_expr_target_single'],
					$data['recovery_expression_action'], $data['recovery_expr_temp']
				);

				if ($new_expr !== false) {
					$data['recovery_expression'] = $new_expr;
					$analyze = analyzeExpression($data['recovery_expression'], TRIGGER_RECOVERY_EXPRESSION);

					if ($analyze !== false) {
						list($data['recovery_expression_formula'], $data['recovery_expression_tree']) = $analyze;
					}
					else {
						show_messages(false, '', _('Recovery expression syntax error.'));
					}

					$data['recovery_expr_temp'] = '';
				}
				else {
					show_messages(false, '', _('Recovery expression syntax error.'));
				}
			}

			$data['recovery_expression_field_name'] = 'recovery_expr_temp';
			$data['recovery_expression_field_value'] = $data['recovery_expr_temp'];
			$data['recovery_expression_field_readonly'] = true;
		}
		else {
			show_messages(false, '', _('Recovery expression syntax error.'));
			$data['recovery_expression_field_name'] = 'recovery_expression';
			$data['recovery_expression_field_value'] = $data['recovery_expression'];
			$data['recovery_expression_field_readonly'] = $readonly;
			$data['recovery_expression_constructor'] = IM_ESTABLISHED;
		}
	}
	elseif ($data['recovery_expression_constructor'] != IM_TREE) {
		$data['recovery_expression_field_name'] = 'recovery_expression';
		$data['recovery_expression_field_value'] = $data['recovery_expression'];
		$data['recovery_expression_field_readonly'] = $readonly;
	}

	if ($data['dependencies']) {
		$dependencyTriggers = API::Trigger()->get([
			'output' => ['triggerid', 'description', 'flags'],
			'selectHosts' => ['hostid', 'name'],
			'triggerids' => $data['dependencies'],
			'preservekeys' => true
		]);

		if ($data['parent_discoveryid']) {
			$dependencyTriggerPrototypes = API::TriggerPrototype()->get([
				'output' => ['triggerid', 'description', 'flags'],
				'selectHosts' => ['hostid', 'name'],
				'triggerids' => $data['dependencies'],
				'preservekeys' => true
			]);

			$data['db_dependencies'] = $dependencyTriggers + $dependencyTriggerPrototypes;
		}
		else {
			$data['db_dependencies'] = $dependencyTriggers;
		}
	}

	foreach ($data['db_dependencies'] as &$dependency) {
		order_result($dependency['hosts'], 'name', ZBX_SORT_UP);
	}
	unset($dependency);

	order_result($data['db_dependencies'], 'description');

	return $data;
}

/**
 * Renders tag table row.
 *
 * @param int|string $index
 * @param string     $tag      (optional)
 * @param string     $value    (optional)
 * @param array      $options  (optional)
 *
 * @return CRow
 */
function renderTagTableRow($index, $tag = '', $value = '', array $options = []) {
	$options = array_merge([
		'readonly' => false,
		'field_name' => 'tags'
	], $options);

	return (new CRow([
		(new CCol(
			(new CTextAreaFlexible($options['field_name'].'['.$index.'][tag]', $tag, $options))
				->setWidth(ZBX_TEXTAREA_TAG_WIDTH)
				->setAttribute('placeholder', _('tag'))
		))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
		(new CCol(
			(new CTextAreaFlexible($options['field_name'].'['.$index.'][value]', $value, $options))
				->setWidth(ZBX_TEXTAREA_TAG_VALUE_WIDTH)
				->setAttribute('placeholder', _('value'))
		))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
		(new CButton($options['field_name'].'['.$index.'][remove]', _('Remove')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-remove')
			->setEnabled(!$options['readonly'])
	]))->addClass('form_row');
}

/**
 * Renders tag table.
 *
 * @param array  $tags
 * @param array  $tags[]['tag']
 * @param array  $tags[]['value']
 * @param bool   $readonly         (optional)
 *
 * @return CTable
 */
function renderTagTable(array $tags, $readonly = false, array $options = []) {
	$table = (new CTable())->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_CONTAINER);

	$row_options = ['readonly' => $readonly];

	if (array_key_exists('field_name', $options)) {
		$row_options['field_name'] = $options['field_name'];
	}

	foreach ($tags as $index => $tag) {
		$table->addRow(renderTagTableRow($index, $tag['tag'], $tag['value'], $row_options));
	}

	return $table->setFooter(new CCol(
		(new CButton('tag_add', _('Add')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-add')
			->setEnabled(!$readonly)
	));
}
