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
				]))->addClass(ZBX_STYLE_SUBFILTER);
			}
		}
	}

	return $output;
}

/**
 * Make subfilter for tags.
 *
 * @param array $data       Array contains available subfilter tags.
 * @param array $subfilter  Array of already selected subfilter tags.
 *
 * @return array
 */
function prepareTagsSubfilterOutput(array $data, array &$subfilter): array {
	$output = [new CTag('h3', true, _('Tags'))];
	CArrayHelper::sort($data, ['tag', 'value']);

	$i = 0;
	foreach ($data as $tag_hash => $tag) {
		$element_name = ($tag['value'] === '') ? $tag['tag'] : $tag['tag'].': '.$tag['value'];
		$element_name = CHtml::encode($element_name);

		$tag['tag'] = json_encode($tag['tag']);
		$tag['value'] = json_encode($tag['value']);

		// is activated
		if (array_key_exists($tag_hash, $subfilter)) {
			$subfilter[$tag_hash]['num'] = $i;

			$output[] = (new CSpan([
				(new CLinkAction($element_name))
					->onClick(CHtml::encode(
						'javascript: create_var("zbx_filter", "subfilter_set", "1", false);'.
						'create_var("zbx_filter", "subfilter_tags['.$i.'][tag]", null, false);'.
						'create_var("zbx_filter", "subfilter_tags['.$i.'][value]", null, true);'
					)),
				' ',
				new CSup($tag['count'])
			]))
				->addClass(ZBX_STYLE_SUBFILTER)
				->addClass(ZBX_STYLE_SUBFILTER_ENABLED);
		}
		// isn't activated
		else {
			// Subfilter has 0 items.
			if ($tag['count'] == 0) {
				$output[] = (new CSpan([
					(new CSpan($element_name))->addClass(ZBX_STYLE_GREY),
					' ',
					new CSup($tag['count'])
				]))->addClass(ZBX_STYLE_SUBFILTER);
			}
			else {
				$link = (new CLinkAction($element_name))
					->onClick(CHtml::encode(
						'javascript: create_var("zbx_filter", "subfilter_set", "1", false);'.
						'create_var("zbx_filter", "subfilter_tags['.$i.'][tag]", '.$tag['tag'].', false);'.
						'create_var("zbx_filter", "subfilter_tags['.$i.'][value]", '.$tag['value'].', true);'
					));

				$output[] = (new CSpan([
					$link,
					' ',
					new CSup(($subfilter ? '+' : '').$tag['count'])
				]))->addClass(ZBX_STYLE_SUBFILTER);
			}
		}

		$i++;
	}

	return $output;
}

function makeItemSubfilter(array &$filter_data, array $items, string $context) {
	// subfilters
	$table_subfilter = (new CTableInfo())
		->addRow([
			new CTag('h4', true, [
				_('Subfilter'), SPACE, (new CSpan(_('affects only filtered data')))->addClass(ZBX_STYLE_GREY)
			])
		], ZBX_STYLE_HOVER_NOBG);

	// array contains subfilters and number of items in each
	$item_params = [
		'hosts' => [],
		'types' => [],
		'value_types' => [],
		'status' => [],
		'state' => [],
		'templated_items' => [],
		'with_triggers' => [],
		'discovery' => [],
		'history' => [],
		'trends' => [],
		'interval' => [],
		'tags' => []
	];

	$update_interval_parser = new CUpdateIntervalParser(['usermacros' => true]);
	$simple_interval_parser = new CSimpleIntervalParser();

	// Generate array with values for subfilters of selected items.
	foreach ($items as $item) {
		// tags
		foreach ($item['tags'] as $tag) {
			$tag_hash = json_encode([$tag['tag'], $tag['value']]);
			if (!array_key_exists($tag_hash, $item_params['tags'])) {
				$item_params['tags'][$tag_hash] = $tag + ['count' => 0];
			}

			$show_item = true;
			foreach ($item['subfilters'] as $name => $value) {
				if ($name === 'subfilter_tags') {
					continue;
				}
				if (!$value) {
					$show_item = false;
					break;
				}
			}

			if ($show_item) {
				$item_params['tags'][$tag_hash]['count']++;
			}
		}

		// hosts
		if ($filter_data['hosts']) {
			$host = reset($item['hosts']);

			if (!isset($item_params['hosts'][$host['hostid']])) {
				$item_params['hosts'][$host['hostid']] = ['name' => $host['name'], 'count' => 0];
			}
			$show_item = true;
			foreach ($item['subfilters'] as $name => $value) {
				if ($name == 'subfilter_hosts') {
					continue;
				}
				if (!$value) {
					$show_item = false;
					break;
				}
			}
			if ($show_item) {
				$host = reset($item['hosts']);
				$item_params['hosts'][$host['hostid']]['count']++;
			}
		}

		// types
		if ($filter_data['filter_type'] == -1) {
			if (!isset($item_params['types'][$item['type']])) {
				$item_params['types'][$item['type']] = ['name' => item_type2str($item['type']), 'count' => 0];
			}
			$show_item = true;
			foreach ($item['subfilters'] as $name => $value) {
				if ($name == 'subfilter_types') {
					continue;
				}
				if (!$value) {
					$show_item = false;
					break;
				}
			}
			if ($show_item) {
				$item_params['types'][$item['type']]['count']++;
			}
		}

		// value types
		if ($filter_data['filter_value_type'] == -1) {
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
				if (!$value) {
					$show_item = false;
					break;
				}
			}
			if ($show_item) {
				$item_params['value_types'][$item['value_type']]['count']++;
			}
		}

		// status
		if ($filter_data['filter_status'] == -1) {
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
				if (!$value) {
					$show_item = false;
					break;
				}
			}
			if ($show_item) {
				$item_params['status'][$item['status']]['count']++;
			}
		}

		// state
		if ($context === 'host' && $filter_data['filter_state'] == -1) {
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
				if (!$value) {
					$show_item = false;
					break;
				}
			}
			if ($show_item) {
				$item_params['state'][$item['state']]['count']++;
			}
		}

		// template
		if ($filter_data['filter_inherited'] == -1) {
			if ($item['templateid'] == 0 && !isset($item_params['templated_items'][0])) {
				$item_params['templated_items'][0] = ['name' => _('Not inherited items'), 'count' => 0];
			}
			elseif ($item['templateid'] > 0 && !isset($item_params['templated_items'][1])) {
				$item_params['templated_items'][1] = ['name' => _('Inherited items'), 'count' => 0];
			}
			$show_item = true;
			foreach ($item['subfilters'] as $name => $value) {
				if ($name == 'subfilter_inherited') {
					continue;
				}
				if (!$value) {
					$show_item = false;
					break;
				}
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
		if ($filter_data['filter_with_triggers'] == -1) {
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
				if (!$value) {
					$show_item = false;
					break;
				}
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
		if ($context === 'host' && $filter_data['filter_discovered'] == -1) {
			if ($item['flags'] == ZBX_FLAG_DISCOVERY_NORMAL && !isset($item_params['discovery'][0])) {
				$item_params['discovery'][0] = ['name' => _('Regular'), 'count' => 0];
			}
			elseif ($item['flags'] == ZBX_FLAG_DISCOVERY_CREATED && !isset($item_params['discovery'][1])) {
				$item_params['discovery'][1] = ['name' => _('Discovered'), 'count' => 0];
			}
			$show_item = true;
			foreach ($item['subfilters'] as $name => $value) {
				if ($name == 'subfilter_discovered') {
					continue;
				}
				if (!$value) {
					$show_item = false;
					break;
				}
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
		if ($filter_data['filter_trends'] === ''
				&& !in_array($item['value_type'], [ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT])) {
			$trends = $item['trends'];
			$value = $trends;

			if ($simple_interval_parser->parse($trends) == CParser::PARSE_SUCCESS) {
				$value = timeUnitToSeconds($trends);
				$trends = convertSecondsToTimeUnits($value);
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
				if (!$value) {
					$show_item = false;
					break;
				}
			}

			if ($show_item) {
				$item_params['trends'][$trends]['count']++;
			}
		}

		// history
		if ($filter_data['filter_history'] === '') {
			$history = $item['history'];
			$value = $history;

			if ($simple_interval_parser->parse($history) == CParser::PARSE_SUCCESS) {
				$value = timeUnitToSeconds($history);
				$history = convertSecondsToTimeUnits($value);
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
				if (!$value) {
					$show_item = false;
					break;
				}
			}

			if ($show_item) {
				$item_params['history'][$history]['count']++;
			}
		}

		// interval
		if ($filter_data['filter_delay'] === '' && $filter_data['filter_type'] != ITEM_TYPE_TRAPPER
				&& $item['type'] != ITEM_TYPE_TRAPPER && $item['type'] != ITEM_TYPE_SNMPTRAP
				&& $item['type'] != ITEM_TYPE_DEPENDENT
				&& ($item['type'] != ITEM_TYPE_ZABBIX_ACTIVE || strncmp($item['key_'], 'mqtt.get', 8) !== 0)) {
			// Use temporary variable for delay, because the original will be used for sorting later.
			$delay = $item['delay'];
			$value = $delay;

			if ($update_interval_parser->parse($delay) == CParser::PARSE_SUCCESS) {
				$delay = $update_interval_parser->getDelay();

				// "value" is delay represented in seconds and it is used for sorting the subfilter.
				if ($delay[0] !== '{') {
					$value = timeUnitToSeconds($delay);
					$delay = convertSecondsToTimeUnits($value);
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
				if (!$value) {
					$show_item = false;
					break;
				}
			}

			if ($show_item) {
				$item_params['interval'][$delay]['count']++;
			}
		}
	}

	// output
	if (count($item_params['tags']) > 1) {
		$tags_output = prepareTagsSubfilterOutput($item_params['tags'], $filter_data['subfilter_tags']);
		$table_subfilter->addRow([$tags_output]);
	}

	if (!$filter_data['hosts'] && $filter_data['subfilter_hosts'] != -1 && count($item_params['hosts']) > 1) {
		$hosts_output = prepareSubfilterOutput(_('Hosts'), $item_params['hosts'], $filter_data['subfilter_hosts'],
			'subfilter_hosts'
		);
		$table_subfilter->addRow([$hosts_output]);
	}

	if ($filter_data['filter_type'] == -1 && count($item_params['types']) > 1) {
		$type_output = prepareSubfilterOutput(_('Types'), $item_params['types'], $filter_data['subfilter_types'],
			'subfilter_types'
		);
		$table_subfilter->addRow([$type_output]);
	}

	if ($filter_data['filter_value_type'] == -1 && count($item_params['value_types']) > 1) {
		$value_types_output = prepareSubfilterOutput(_('Type of information'), $item_params['value_types'],
			$filter_data['subfilter_value_types'], 'subfilter_value_types'
		);
		$table_subfilter->addRow([$value_types_output]);
	}

	if ($filter_data['filter_status'] == -1 && count($item_params['status']) > 1) {
		$status_output = prepareSubfilterOutput(_('Status'), $item_params['status'], $filter_data['subfilter_status'],
			'subfilter_status'
		);
		$table_subfilter->addRow([$status_output]);
	}

	if ($context === 'host' && $filter_data['filter_state'] == -1 && count($item_params['state']) > 1) {
		$state_output = prepareSubfilterOutput(_('State'), $item_params['state'], $filter_data['subfilter_state'],
			'subfilter_state'
		);
		$table_subfilter->addRow([$state_output]);
	}

	if ($filter_data['filter_inherited'] == -1 && count($item_params['templated_items']) > 1) {
		$templated_items_output = prepareSubfilterOutput(_('Template'), $item_params['templated_items'],
			$filter_data['subfilter_inherited'], 'subfilter_inherited'
		);
		$table_subfilter->addRow([$templated_items_output]);
	}

	if ($filter_data['filter_with_triggers'] == -1 && count($item_params['with_triggers']) > 1) {
		$with_triggers_output = prepareSubfilterOutput(_('With triggers'), $item_params['with_triggers'],
			$filter_data['subfilter_with_triggers'], 'subfilter_with_triggers'
		);
		$table_subfilter->addRow([$with_triggers_output]);
	}

	if ($context === 'host' && $filter_data['filter_discovered'] == -1 && count($item_params['discovery']) > 1) {
		$discovery_output = prepareSubfilterOutput(_('Discovery'), $item_params['discovery'],
			$filter_data['subfilter_discovered'], 'subfilter_discovered'
		);
		$table_subfilter->addRow([$discovery_output]);
	}

	if (!$filter_data['filter_history'] && count($item_params['history']) > 1) {
		$history_output = prepareSubfilterOutput(_('History'), $item_params['history'],
			$filter_data['subfilter_history'], 'subfilter_history'
		);
		$table_subfilter->addRow([$history_output]);
	}

	if (!$filter_data['filter_trends'] && (count($item_params['trends']) > 1)) {
		$trends_output = prepareSubfilterOutput(_('Trends'), $item_params['trends'], $filter_data['subfilter_trends'],
			'subfilter_trends'
		);
		$table_subfilter->addRow([$trends_output]);
	}

	if (!$filter_data['filter_delay'] && $filter_data['filter_type'] != ITEM_TYPE_TRAPPER
			&& count($item_params['interval']) > 1) {
		$interval_output = prepareSubfilterOutput(_('Interval'), $item_params['interval'],
			$filter_data['subfilter_interval'], 'subfilter_interval'
		);
		$table_subfilter->addRow([$interval_output]);
	}

	return $table_subfilter;
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
			$sortorder = $item['query_fields']['sortorder'][$index];

			if ($key !== '' || $value !== '') {
				$query_fields[$sortorder] = [$key => $value];
			}
		}

		ksort($query_fields);
		$item['query_fields'] = $query_fields;
	}

	if ($item['headers']) {
		$tmp_headers = [];

		foreach ($item['headers']['name'] as $index => $key) {
			$value = $item['headers']['value'][$index];
			$sortorder = $item['headers']['sortorder'][$index];

			if ($key !== '' || $value !== '') {
				$tmp_headers[$sortorder] = [$key => $value];
			}
		}

		ksort($tmp_headers);
		$headers = [];

		foreach ($tmp_headers as $key_value_pair) {
			$headers[key($key_value_pair)] = reset($key_value_pair);
		}

		$item['headers'] = $headers;
	}

	return $item;
}

/**
 * Prepare ITEM_TYPE_SCRIPT type item data for create or update API calls.
 * - Converts 'parameters' from array of keys and array of values to arrays of names and values.
 *   IN:
 *   Array (
 *       [name] => Array (
 *           [0] => a
 *           [1] => c
 *       )
 *       [value] => Array (
 *           [0] => b
 *           [1] => d
 *       )
 *   )
 *
 *   OUT:
 *   Array (
 *       [0] => Array (
 *           [name] => a
 *           [value] => b
 *       )
 *       [1] => Array (
 *           [name] => c
 *           [value] => d
 *       )
 *   )
 *
 * @param array $item                          Array of form fields data for ITEM_TYPE_SCRIPT item.
 * @param array $item['parameters']            Item parameters array.
 * @param array $item['parameters']['name']    Item parameter names array.
 * @param array $item['parameters']['values']  Item parameter values array.
 *
 * @return array
 */
function prepareScriptItemFormData(array $item): array {
	$values = [];

	if (is_array($item['parameters']) && array_key_exists('name', $item['parameters'])
			&& array_key_exists('value', $item['parameters'])) {
		foreach ($item['parameters']['name'] as $index => $key) {
			if (array_key_exists($index, $item['parameters']['value'])
					&& ($key !== '' || $item['parameters']['value'][$index] !== '')) {
				$values[] = [
					'name' => $key,
					'value' => $item['parameters']['value'][$index]
				];
			}
		}
	}

	$item['parameters'] = $values;

	return $item;
}

/**
 * Get data for item edit page.
 *
 * @param array  $item                          Item, item prototype, LLD rule or LLD item to take the data from.
 * @param array  $options
 * @param bool   $options['form']               (mandatory)
 * @param bool   $options['is_discovery_rule']  (optional)
 *
 * @return array
 */
function getItemFormData(array $item = [], array $options = []) {
	$data = [
		'form' => $options['form'],
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
		'delay_flex' => array_values(getRequest('delay_flex', [])),
		'ipmi_sensor' => getRequest('ipmi_sensor', ''),
		'authtype' => getRequest('authtype', 0),
		'username' => getRequest('username', ''),
		'password' => getRequest('password', ''),
		'publickey' => getRequest('publickey', ''),
		'privatekey' => getRequest('privatekey', ''),
		'logtimefmt' => getRequest('logtimefmt', ''),
		'possibleHostInventories' => null,
		'alreadyPopulated' => null,
		'initial_item_type' => null,
		'templates' => [],
		'jmx_endpoint' => getRequest('jmx_endpoint', ZBX_DEFAULT_JMX_ENDPOINT),
		'timeout' => getRequest('timeout', DB::getDefault('items', 'timeout')),
		'url' => getRequest('url'),
		'query_fields' => getRequest('query_fields', []),
		'parameters' => getRequest('parameters', []),
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
		'preprocessing_script_maxlength' => DB::getFieldLength('item_preproc', 'params'),
		'context' => getRequest('context'),
		'show_inherited_tags' => getRequest('show_inherited_tags', 0),
		'tags' => getRequest('tags', [])
	];

	// Unset empty and inherited tags.
	foreach ($data['tags'] as $key => $tag) {
		if ($tag['tag'] === '' && $tag['value'] === '') {
			unset($data['tags'][$key]);
		}
		elseif (array_key_exists('type', $tag) && !($tag['type'] & ZBX_PROPERTY_OWN)) {
			unset($data['tags'][$key]);
		}
		else {
			unset($data['tags'][$key]['type']);
		}
	}

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
						$sortorder = $data[$property]['sortorder'][$index];
						$values[$sortorder] = [$key => $data[$property]['value'][$index]];
					}
				}
			}
			ksort($values);
			$data[$property] = $values;
		}

		$data['parameters'] = [];
	}
	elseif ($data['type'] == ITEM_TYPE_SCRIPT) {
		$values = [];

		if (is_array($data['parameters']) && array_key_exists('name', $data['parameters'])
				&& array_key_exists('value', $data['parameters'])) {
			foreach ($data['parameters']['name'] as $index => $key) {
				if (array_key_exists($index, $data['parameters']['value'])) {
					$values[] = [
						'name' => $key,
						'value' => $data['parameters']['value'][$index]
					];
				}
			}
		}
		$data['parameters'] = $values;

		$data['headers'] = [];
		$data['query_fields'] = [];
	}
	else {
		$data['headers'] = [];
		$data['query_fields'] = [];
		$data['parameters'] = [];
	}

	// Dependent item initialization by master_itemid.
	if (array_key_exists('master_item', $item)) {
		$data['master_itemid'] = $item['master_item']['itemid'];
		$data['master_itemname'] = $item['master_item']['name'];
		// Do not initialize item data if only master_item array was passed.
		unset($item['master_item']);
	}

	// hostid
	if ($data['parent_discoveryid'] != 0) {
		$discoveryRule = API::DiscoveryRule()->get([
			'output' => ['hostid'],
			'selectHosts' => ['flags'],
			'itemids' => $data['parent_discoveryid'],
			'editable' => true
		]);
		$discoveryRule = reset($discoveryRule);
		$data['hostid'] = $discoveryRule['hostid'];
		$data['host'] = $discoveryRule['hosts'][0];
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
		unset($data['types'][ITEM_TYPE_CALCULATED], $data['types'][ITEM_TYPE_SNMPTRAP]);
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

		$data['templates'] = makeItemTemplatesHtml($item['itemid'], getItemParentTemplates([$item], $flag), $flag,
			CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)
		);
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
		// ITEM_TYPE_HTTPAGENT
		$data['timeout'] = $data['item']['timeout'];
		$data['url'] = $data['item']['url'];
		$data['query_fields'] = $data['item']['query_fields'];
		$data['parameters'] = $data['item']['parameters'];
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

		if (!$data['is_discovery_rule']) {
			$data['tags'] = $data['item']['tags'];
		}

		if ($data['type'] == ITEM_TYPE_HTTPAGENT) {
			// Convert hash to array where every item is hash for single key value pair as it is used by view.
			$headers = [];

			foreach ($data['headers'] as $key => $value) {
				$headers[] = [$key => $value];
			}

			$data['headers'] = $headers;
		}
		elseif ($data['type'] == ITEM_TYPE_SCRIPT && $data['parameters']) {
			CArrayHelper::sort($data['parameters'], ['name']);
		}

		$data['preprocessing'] = $data['item']['preprocessing'];

		if (!$data['is_discovery_rule']) {
			$data['output_format'] = $data['item']['output_format'];
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
							|| $data['type'] == ITEM_TYPE_DEPENDENT || ($data['type'] == ITEM_TYPE_ZABBIX_ACTIVE
								&& strncmp($data['key'], 'mqtt.get', 8) === 0))) {
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
		}
	}

	if (!$data['delay_flex']) {
		$data['delay_flex'][] = ['delay' => '', 'period' => '', 'type' => ITEM_DELAY_FLEXIBLE];
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

	if (!$data['is_discovery_rule'] && $data['form'] === 'clone') {
		if ($data['valuemapid'] != 0) {
			$entity = ($data['parent_discoveryid'] == 0) ? API::Item() : API::ItemPrototype();
			$cloned_item = $entity->get([
				'output' => ['templateid'],
				'selectValueMap' => ['name'],
				'itemids' => $data['itemid']
			]);
			$cloned_item = reset($cloned_item);

			if ($cloned_item['templateid'] != 0) {
				$host_valuemaps = API::ValueMap()->get([
					'output' => ['valuemapid'],
					'hostids' => $data['hostid'],
					'filter' => ['name' => $cloned_item['valuemap']['name']]
				]);

				$data['valuemapid'] = $host_valuemaps ? $host_valuemaps[0]['valuemapid'] : 0;
			}
		}

		$data['itemid'] = 0;
		$data['form'] = 'create';
	}

	if ($data['is_discovery_rule']) {
		unset($data['valuemapid']);
	}
	else if ($data['valuemapid'] != 0) {
		$data['valuemap'] = CArrayHelper::renameObjectsKeys(API::ValueMap()->get([
			'output' => ['valuemapid', 'name'],
			'valuemapids' => $data['valuemapid']
		]), ['valuemapid' => 'id']);
	}
	else {
		$data['valuemap'] = [];
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

	if (!$data['is_discovery_rule']) {
		// Select inherited tags.
		if ($data['show_inherited_tags'] && array_key_exists('item', $data)) {
			if ($data['item']['discoveryRule']) {
				$items = [$data['item']['discoveryRule']];
				$parent_templates = getItemParentTemplates($items, ZBX_FLAG_DISCOVERY_RULE)['templates'];
			}
			else {
				$items = [[
					'templateid' => $data['item']['templateid'],
					'itemid' => $data['itemid']
				]];
				$parent_templates = getItemParentTemplates($items, ZBX_FLAG_DISCOVERY_NORMAL)['templates'];
			}
			unset($parent_templates[0]);

			$db_templates = $parent_templates
				? API::Template()->get([
					'output' => ['templateid'],
					'selectTags' => ['tag', 'value'],
					'templateids' => array_keys($parent_templates),
					'preservekeys' => true
				])
				: [];

			$inherited_tags = [];

			// Make list of template tags.
			foreach ($parent_templates as $templateid => $template) {
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
				'output' => ['hostid', 'name'],
				'selectTags' => ['tag', 'value'],
				'hostids' => $data['hostid'],
				'templated_hosts' => true
			]);

			// Overwrite and attach host level tags.
			if ($db_hosts) {
				foreach ($db_hosts[0]['tags'] as $tag) {
					$inherited_tags[$tag['tag']][$tag['value']] = $tag;
					$inherited_tags[$tag['tag']][$tag['value']]['type'] = ZBX_PROPERTY_INHERITED;
				}
			}

			// Overwrite and attach item's own tags.
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

		if (!$data['tags']) {
			$data['tags'] = [['tag' => '', 'value' => '']];
		}
		else {
			CArrayHelper::sort($data['tags'], ['tag', 'value']);
		}
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
	$have_validate_not_supported = in_array(ZBX_PREPROC_VALIDATE_NOT_SUPPORTED, array_column($preprocessing, 'type'));

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
				$enabled = (!$have_validate_not_supported || $type != ZBX_PREPROC_VALIDATE_NOT_SUPPORTED
						|| $type == $step['type']);
				$opt_group->addOption((new CSelectOption($type, $label))->setDisabled(!$enabled));
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
				$step_param_2_value = (array_key_exists('params', $step) && array_key_exists(2, $step['params']))
					? $step['params'][2]
					: '';

				if ($step_param_1_value === ZBX_PREPROC_PROMETHEUS_FUNCTION) {
					$step_param_1_value = $step_param_2_value;
					$step_param_2_value = '';
				}

				$params = [
					$step_param_0->setAttribute('placeholder',
						_('<metric name>{<label name>="<label value>", ...} == <value>')
					),
					(new CSelect('preprocessing['.$i.'][params][1]'))
						->addOptions(CSelect::createOptionsFromArray([
							ZBX_PREPROC_PROMETHEUS_VALUE => _('value'),
							ZBX_PREPROC_PROMETHEUS_LABEL => _('label'),
							ZBX_PREPROC_PROMETHEUS_SUM => 'sum',
							ZBX_PREPROC_PROMETHEUS_MIN => 'min',
							ZBX_PREPROC_PROMETHEUS_MAX => 'max',
							ZBX_PREPROC_PROMETHEUS_AVG => 'avg',
							ZBX_PREPROC_PROMETHEUS_COUNT => 'count'
						]))
						->addClass('js-preproc-param-prometheus-pattern-function')
						->setValue($step_param_1_value)
						->setReadonly($readonly),
					(new CTextBox('preprocessing['.$i.'][params][2]', $step_param_2_value))
						->setTitle($step_param_2_value)
						->setAttribute('placeholder', _('<label name>'))
						->setEnabled($step_param_1_value === ZBX_PREPROC_PROMETHEUS_LABEL)
						->setReadonly($readonly)
				];
				break;

			case ZBX_PREPROC_PROMETHEUS_TO_JSON:
				$params = $step_param_0->setAttribute('placeholder',
					_('<metric name>{<label name>="<label value>", ...} == <value>')
				);
				break;

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

			case ZBX_PREPROC_VALIDATE_NOT_SUPPORTED:
				$on_fail
					->setEnabled(false)
					->setChecked(true);
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
					(new CDiv(new CVar('preprocessing['.$i.'][sortorder]', $step['sortorder'])))
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
		'hostid' => 0
	];

	$prefix = (getRequest('context') === 'host') ? 'web.hosts.' : 'web.templates.';
	$filter_hostids = getRequest('filter_hostids', CProfile::getArray($prefix.'triggers.filter_hostids', []));

	if (count($filter_hostids) == 1) {
		$data['hostid'] = reset($filter_hostids);
	}

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
		'hostid' => getRequest('hostid', 0),
		'context' => getRequest('context')
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
 * @param string      $data['event_name']                       Trigger event name.
 * @param string      $data['opdata']                           Trigger operational data.
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
 * @param string      $data['tags']                             Trigger tags.
 * @param string      $data['correlation_mode']                 Trigger correlation mode.
 * @param string      $data['correlation_tag']                  Trigger correlation tag.
 * @param string      $data['manual_close']                     Trigger manual close.
 * @param string      $data['context']                          Additional parameter in URL to identify main section.
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
			getTriggerParentTemplates([$trigger], $flag), $flag,
			CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)
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
			$data['event_name'] = $trigger['event_name'];
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
		$analyze = analyzeExpression($data['expression'], TRIGGER_EXPRESSION, $error);

		if ($analyze !== false) {
			list($data['expression_formula'], $data['expression_tree']) = $analyze;

			if ($data['expression_action'] !== '' && $data['expression_tree'] !== null) {
				$new_expr = remakeExpression($data['expression'], $_REQUEST['expr_target_single'],
					$data['expression_action'], $data['expr_temp'], $error
				);

				if ($new_expr !== false) {
					$data['expression'] = $new_expr;
					$analyze = analyzeExpression($data['expression'], TRIGGER_EXPRESSION, $error);

					if ($analyze !== false) {
						list($data['expression_formula'], $data['expression_tree']) = $analyze;
					}
					else {
						error(_s('Cannot build expression tree: %1$s.', $error));
						show_messages(false, '', _('Expression syntax error.'));
					}

					$data['expr_temp'] = '';
				}
				else {
					error(_s('Cannot build expression tree: %1$s.', $error));
					show_messages(false, '', _('Expression syntax error.'));
				}
			}

			$data['expression_field_name'] = 'expr_temp';
			$data['expression_field_value'] = $data['expr_temp'];
			$data['expression_field_readonly'] = true;
		}
		else {
			error(_s('Cannot build expression tree: %1$s.', $error));
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
		$analyze = analyzeExpression($data['recovery_expression'], TRIGGER_RECOVERY_EXPRESSION, $error);

		if ($analyze !== false) {
			list($data['recovery_expression_formula'], $data['recovery_expression_tree']) = $analyze;

			if ($data['recovery_expression_action'] !== '' && $data['recovery_expression_tree'] !== null) {
				$new_expr = remakeExpression($data['recovery_expression'], $_REQUEST['recovery_expr_target_single'],
					$data['recovery_expression_action'], $data['recovery_expr_temp'], $error
				);

				if ($new_expr !== false) {
					$data['recovery_expression'] = $new_expr;
					$analyze = analyzeExpression($data['recovery_expression'], TRIGGER_RECOVERY_EXPRESSION, $error);

					if ($analyze !== false) {
						list($data['recovery_expression_formula'], $data['recovery_expression_tree']) = $analyze;
					}
					else {
						error(_s('Cannot build expression tree: %1$s.', $error));
						show_messages(false, '', _('Recovery expression syntax error.'));
					}

					$data['recovery_expr_temp'] = '';
				}
				else {
					error(_s('Cannot build expression tree: %1$s.', $error));
					show_messages(false, '', _('Recovery expression syntax error.'));
				}
			}

			$data['recovery_expression_field_name'] = 'recovery_expr_temp';
			$data['recovery_expression_field_value'] = $data['recovery_expr_temp'];
			$data['recovery_expression_field_readonly'] = true;
		}
		else {
			error(_s('Cannot build expression tree: %1$s.', $error));
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
				->setAdaptiveWidth(ZBX_TEXTAREA_TAG_WIDTH)
				->setAttribute('placeholder', _('tag'))
		))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
		(new CCol(
			(new CTextAreaFlexible($options['field_name'].'['.$index.'][value]', $value, $options))
				->setAdaptiveWidth(ZBX_TEXTAREA_TAG_VALUE_WIDTH)
				->setAttribute('placeholder', _('value'))
		))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
		(new CCol(
			(new CButton($options['field_name'].'['.$index.'][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
				->setEnabled(!$options['readonly'])
		))
			->addClass(ZBX_STYLE_NOWRAP)
			->addClass(ZBX_STYLE_TOP)
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
	$table = (new CTable())
		->addStyle('width: 100%; max-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_CONTAINER);

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
