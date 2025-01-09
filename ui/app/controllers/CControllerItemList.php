<?php declare(strict_types=0);
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


class CControllerItemList extends CControllerItem {

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'filter_set'				=> 'in 1',
			'filter_rst'				=> 'in 1',
			'context'					=> 'required|in host,template',
			'filter_groupids'			=> 'array_db hstgrp.groupid',
			'filter_hostids'			=> 'array_db hosts.hostid',
			'filter_name'				=> 'string',
			'filter_key'				=> 'string',
			'filter_valuemapids'		=> 'array_db valuemap.valuemapid',
			'filter_type'				=> 'in '.implode(',', [-1, ITEM_TYPE_ZABBIX, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_SIMPLE, ITEM_TYPE_SNMP, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_INTERNAL, ITEM_TYPE_TRAPPER, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_HTTPAGENT, ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_JMX, ITEM_TYPE_CALCULATED, ITEM_TYPE_DEPENDENT, ITEM_TYPE_SCRIPT, ITEM_TYPE_BROWSER]),
			'filter_value_type'			=> 'in '.implode(',', [-1, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT, ITEM_VALUE_TYPE_BINARY]),
			'filter_snmp_oid'			=> 'string',
			'filter_history'			=> 'string',
			'filter_trends'				=> 'string',
			'filter_delay'				=> 'string',
			'filter_evaltype'			=> 'in '.implode(',', [TAG_EVAL_TYPE_AND_OR, TAG_EVAL_TYPE_OR]),
			'filter_tags'				=> 'array',
			'filter_state'				=> 'in '.implode(',', [-1, ITEM_STATE_NORMAL, ITEM_STATE_NOTSUPPORTED]),
			'filter_status'				=> 'in '.implode(',', [-1, ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED]),
			'filter_with_triggers'		=> 'in -1,0,1',
			'filter_inherited'			=> 'in -1,0,1',
			'filter_discovered'			=> 'in '.implode(',', [-1, ZBX_FLAG_DISCOVERY_CREATED, ZBX_FLAG_DISCOVERY_NORMAL]),
			'subfilter_types'			=> 'array',
			'subfilter_value_types'		=> 'array',
			'subfilter_status'			=> 'array',
			'subfilter_state'			=> 'array',
			'subfilter_inherited'		=> 'array',
			'subfilter_with_triggers'	=> 'array',
			'subfilter_discovered'		=> 'array',
			'subfilter_hosts'			=> 'array',
			'subfilter_interval'		=> 'array',
			'subfilter_history'			=> 'array',
			'subfilter_trends'			=> 'array',
			'subfilter_tags'			=> 'array',
			'sort'						=> 'in name,key_,delay,history,trends,type,status',
			'sortorder'					=> 'in '.implode(',', [ZBX_SORT_DOWN.','.ZBX_SORT_UP]),
			'page'						=> 'ge 1'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$fields = array_flip(['tag', 'operator', 'value']);
			$operators = [
				TAG_OPERATOR_EXISTS, TAG_OPERATOR_EQUAL, TAG_OPERATOR_LIKE, TAG_OPERATOR_NOT_EXISTS,
				TAG_OPERATOR_NOT_EQUAL, TAG_OPERATOR_NOT_LIKE
			];
			foreach ($this->getInput('filter_tags', []) as $tag) {
				if (!is_array($tag) || array_diff_key($tag, $fields) || !in_array($tag['operator'], $operators)) {
					$ret = false;
					break;
				}
			}
		}

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	public function doAction() {
		if ($this->hasInput('filter_set')) {
			$this->updateProfiles();
		}
		elseif ($this->hasInput('filter_rst')) {
			$this->deleteProfiles();
		}

		$page = $this->getInput('page', 1);
		$filter = $this->getFilter();
		$data = [
			'action' => $this->getAction(),
			'hostid' => 0,
			'context' => $this->getInput('context'),
			'filter_data' => $filter,
			'items' => [],
			'types' => item_type2str(),
			'triggers' => [],
			'trigger_parent_templates' => [],
			'filtered_count' => 0,
			'tags' => [],
			'parent_templates' => [],
			'check_now_types' => checkNowAllowedTypes(),
			'allowed_ui_conf_templates' => CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES),
			'sort' => $filter['sort'],
			'sortorder' => $filter['sortorder'],
			'uncheck' => $this->hasInput('uncheck')
		];
		unset($data['types'][ITEM_TYPE_HTTPTEST]);

		$items = $this->getItems($data['context'], $filter);
		$data['filtered_count'] = count($items);
		[$items, $subfilter_fields] = $this->getItemsAndSubfilter($items, $this->getSubfilter($items, $filter));
		$data['subfilter'] = static::sortSubfilter($subfilter_fields);
		$items = $this->sortItems($items, ['sort' => $filter['sort'], 'sortorder' => $filter['sortorder']]);

		$selected_filters = array_merge($filter, $this->getselectedSubfilters($subfilter_fields));
		$view_url = new CUrl('zabbix.php');
		$view_url_params = ['action' => $data['action'], 'context' => $data['context']] + $selected_filters;

		array_map([$view_url, 'setArgument'], array_keys($view_url_params), $view_url_params);

		$data['paging'] = CPagerHelper::paginate($page, $items, $filter['sortorder'], $view_url);

		$triggers = $this->getItemsTriggers($items);

		if (count($filter['filter_hostids']) == 1) {
			$data['hostid'] = reset($filter['filter_hostids']);
		}

		if ($triggers) {
			$data['triggers'] = $triggers;
			$data['trigger_parent_templates'] = getTriggerParentTemplates($triggers, ZBX_FLAG_DISCOVERY_NORMAL);
			$data['triggers'] = CMacrosResolverHelper::resolveTriggerExpressions($data['triggers'], [
				'html' => true,
				'sources' => ['expression', 'recovery_expression'],
				'context' => $data['context']
			]);
		}

		if ($items) {
			$data['items'] = $items;
			$data['parent_templates'] = getItemParentTemplates($items, ZBX_FLAG_DISCOVERY_NORMAL);
			$data['tags'] = makeTags($items, true, 'itemid', ZBX_TAG_COUNT_DEFAULT, $filter['filter_tags']);
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of items'));
		$this->setResponse($response);
	}

	/**
	 * @param array $subfilters  Array of subfilter data.
	 *
	 * @return array
	 */
	private function getselectedSubfilters($subfilters): array {
		$result = [];

		foreach ($subfilters as $key => $subfilter) {
			if ($subfilter['selected']) {
				$result[$key] = array_keys($subfilter['selected']);
			}
		}

		return $result;
	}

	/**
	 * Sorts array [values] in ascending order based on entries in [sort] array.
	 * If [sort] does not have an entry for sorting, the [labels] array value is used.
	 * If both [sort] and [labels] do not contain a value, the original [values] array is used for sorting.
	 *
	 * @param array $subfilters            Array of subfilter data.
	 * @param array $subfilters[][values]  Associative array, subfilter value as array key and count as value.
	 * @param array $subfilters[][labels]  Associative array, subfilter value as array key and label as value.
	 * @param array $subfilters[][sort]    Associative array, subfilter value as array key and sorting order as value.
	 *
	 * @return array
	 */
	public static function sortSubfilter(array $subfilters): array {
		foreach ($subfilters as &$subfilter) {
			$values = [];

			foreach ($subfilter['values'] as $value => $count) {
				$label = $value;
				$sort = $value;

				if (array_key_exists('labels', $subfilter) && array_key_exists($value, $subfilter['labels'])) {
					$label = $subfilter['labels'][$value];
				}

				if (array_key_exists('sort', $subfilter) && array_key_exists($value, $subfilter['sort'])) {
					$sort = $subfilter['sort'][$value];
				}

				$values[] = [
					'value' => $value,
					'count' => $count,
					'label' => $label,
					'sort' => $sort
				];
			}

			CArrayHelper::sort($values, [
				['field' => 'sort', 'order' => ZBX_SORT_UP],
				['field' => 'count', 'order' => ZBX_SORT_UP]
			]);
			$subfilter['values'] = array_column($values, 'count', 'value');
		}
		unset($subfilter);

		return $subfilters;
	}

	/**
	 * Get subfilter with additional labels data.
	 *
	 * @param array $items       Array of items data without applied subfilter.
	 * @param array $subfilters  Array of subfilter data.
	 *
	 * @return array
	 */
	public static function addSubfilterLabels(array $items, array $subfilters): array {
		// Hosts
		$hosts = array_reduce(array_column($items, 'hosts'), 'array_merge', []);
		$subfilters['subfilter_hosts']['labels'] = array_column($hosts, 'host', 'hostid');

		// Types
		$subfilters['subfilter_types']['labels'] = item_type2str();
		unset($subfilters['subfilter_types']['labels'][ITEM_TYPE_HTTPTEST]);

		// Type of information
		$subfilters['subfilter_value_types']['labels'] = [
			ITEM_VALUE_TYPE_UINT64 => _('Numeric (unsigned)'),
			ITEM_VALUE_TYPE_FLOAT => _('Numeric (float)'),
			ITEM_VALUE_TYPE_STR => _('Character'),
			ITEM_VALUE_TYPE_LOG => _('Log'),
			ITEM_VALUE_TYPE_TEXT => _('Text'),
			ITEM_VALUE_TYPE_BINARY => _('Binary')
		];

		// Status
		$subfilters['subfilter_status']['labels'] = [
			ITEM_STATUS_DISABLED => _('Disabled'),
			ITEM_STATUS_ACTIVE => _('Enabled')
		];

		// State
		$subfilters['subfilter_state']['labels'] = [
			ITEM_STATE_NORMAL => _('Normal'),
			ITEM_STATE_NOTSUPPORTED => _('Not supported')
		];

		// Template
		$subfilters['subfilter_inherited']['labels'] = [
			1 => _('Inherited items'),
			0 => _('Not inherited items')
		];

		// With triggers
		$subfilters['subfilter_with_triggers']['labels'] = [
			1 => _('With triggers'),
			0 => _('Without triggers')
		];

		// Discovery
		$subfilters['subfilter_discovered']['labels'] = [
			1 => _('Discovered'),
			0 => _('Regular')
		];

		// History
		foreach ($items as $item) {
			$value = $item['history'];
			$units_value = $value;

			if (strpos($value, '{') === false) {
				$value = timeUnitToSeconds($value);
				$units_value = convertSecondsToTimeUnits($value);
			}

			$subfilters['subfilter_history']['labels'][$value] = $units_value;
			$subfilters['subfilter_history']['sort'][$value] = $value;
		}

		// Trends
		foreach ($items as $item) {
			$value = $item['trends'];
			$units_value = $value;

			if ($value === '' || !in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])) {
				continue;
			}

			if (strpos($value, '{') === false) {
				$value = timeUnitToSeconds($value);
				$units_value = convertSecondsToTimeUnits($value);
			}

			$subfilters['subfilter_trends']['labels'][$value] = $units_value;
			$subfilters['subfilter_trends']['sort'][$value] = $value;
		}

		// Interval
		$parser = new CUpdateIntervalParser(['usermacros' => true]);
		foreach ($items as $item) {
			$value = $item['delay'];
			$units_value = $value;

			if ($item['type'] == ITEM_TYPE_ZABBIX_ACTIVE && strpos($item['key_'], 'mqtt.get') === 0) {
				continue;
			}

			if (in_array($item['type'], [ITEM_TYPE_TRAPPER, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_DEPENDENT])) {
				continue;
			}

			if ($parser->parse($value) == CParser::PARSE_SUCCESS) {
				$value = $parser->getDelay();
			}

			if (strpos($value, '{') === false) {
				$value = timeUnitToSeconds($value);
				$units_value = convertSecondsToTimeUnits($value);
			}

			$subfilters['subfilter_interval']['labels'][$value] = $units_value;
			$subfilters['subfilter_interval']['sort'][$value] = $value;
		}

		return $subfilters;
	}

	/**
	 * Get items subfilter fields.
	 *
	 * @return array
	 */
	public static function getSubfilterSchema(): array {
		$schema = [
			[
				'key' => 'subfilter_tags',
				'label' => _('Tags')
			],
			[
				'key' => 'subfilter_hosts',
				'label' => _('Hosts')
			],
			[
				'key' => 'subfilter_types',
				'label' => _('Types')
			],
			[
				'key' => 'subfilter_value_types',
				'label' => _('Type of information')
			],
			[
				'key' => 'subfilter_status',
				'label' => _('Status')
			],
			[
				'key' => 'subfilter_state',
				'label' => _('State')
			],
			[
				'key' => 'subfilter_inherited',
				'label' => _('Template')
			],
			[
				'key' => 'subfilter_with_triggers',
				'label' => _('With triggers')
			],
			[
				'key' => 'subfilter_discovered',
				'label' => _('Discovery')
			],
			[
				'key' => 'subfilter_history',
				'label' => _('History')
			],
			[
				'key' => 'subfilter_trends',
				'label' => _('Trends')
			],
			[
				'key' => 'subfilter_interval',
				'label' => _('Interval')
			]
		];

		return array_column($schema, null, 'key');
	}

	/**
	 * Get subfilter schema according filter defined values.
	 *
	 * @param array $items
	 * @param array $filter
	 *
	 * @return array
	 */
	protected function getSubfilter(array $items, array $filter): array {
		$context = $this->getInput('context');
		$schema = static::getSubfilterSchema();
		$schema = static::addSubfilterLabels($items, $schema);

		if (count($filter['filter_hostids']) < 2) {
			unset($schema['subfilter_hosts']);
		}

		if ($filter['filter_type'] != -1) {
			unset($schema['subfilter_types']);
		}

		if ($filter['filter_value_type'] != -1) {
			unset($schema['subfilter_value_types']);
		}

		if ($filter['filter_status'] != -1) {
			unset($schema['subfilter_status']);
		}

		if ($filter['filter_state'] != -1 && $context === 'host') {
			unset($schema['subfilter_state']);
		}

		if ($filter['filter_inherited'] != -1) {
			unset($schema['subfilter_inherited']);
		}

		if ($filter['filter_with_triggers'] != -1) {
			unset($schema['subfilter_with_triggers']);
		}

		if ($filter['filter_discovered'] != -1 && $context === 'host') {
			unset($schema['subfilter_discovered']);
		}

		if ($filter['filter_history'] !== '') {
			unset($schema['subfilter_history']);
		}

		if ($filter['filter_trends'] !== '') {
			unset($schema['subfilter_trends']);
		}

		if ($filter['filter_delay'] !== '' && $filter['filter_type'] == ITEM_TYPE_TRAPPER) {
			unset($schema['subfilter_interval']);
		}

		$selected = array_fill_keys(array_column($schema, 'key'), []);

		if (!$this->hasInput('filter_set') && !$this->hasInput('filter_rst')) {
			$this->getInputs($selected, array_keys($selected));
		}

		foreach ($selected as $key => $value) {
			$schema[$key]['selected'] = array_fill_keys($value, true);
		}

		return $schema;
	}

	/**
	 * Sort results by field.
	 *
	 * @param array $items  Array of items to sort.
	 * @param array $sort   Array with [sort] field name and [sortorder] order.
	 *
	 * @return array
	 */
	protected function sortItems(array $items, array $sort): array {
		switch ($sort['sort']) {
			case 'delay':
				orderItemsByDelay($items, $sort['sortorder'], ['usermacros' => true]);
				break;

			case 'history':
				orderItemsByHistory($items, $sort['sortorder']);
				break;

			case 'trends':
				orderItemsByTrends($items, $sort['sortorder']);
				break;

			case 'status':
				orderItemsByStatus($items, $sort['sortorder']);
				break;

			default:
				order_result($items, $sort['sort'], $sort['sortorder']);
		}

		return $items;
	}

	/**
	 * Get triggers data for filtered items.
	 *
	 * @param array $items[]
	 * @param array $items[][triggers][triggerid]
	 *
	 * @return array
	 */
	protected function getItemsTriggers(array $items): array {
		$triggerids = array_reduce(array_column($items, 'triggers'), 'array_merge', []);
		$triggerids = array_column($triggerids, 'triggerid', 'triggerid');

		if (!$triggerids) {
			return [];
		}

		return API::Trigger()->get([
			'output' => ['triggerid', 'description', 'expression', 'recovery_mode', 'recovery_expression', 'priority',
				'status', 'state', 'error', 'templateid', 'flags'
			],
			'selectHosts' => ['hostid', 'name', 'host'],
			'triggerids' => array_values($triggerids),
			'preservekeys' => true
		]);
	}

	/**
	 * Get items for selected filter via API.
	 *
	 * @param string $context
	 * @param array  $input
	 *
	 * @return array
	 */
	protected function getItems(string $context, array $input): array {
		$options = [
			'search' => [],
			'output' => [
				'itemid', 'type', 'hostid', 'name', 'key_', 'delay', 'history', 'trends', 'status', 'value_type', 'error',
				'templateid', 'flags', 'state', 'master_itemid'
			],
			'templated' => $context === 'template',
			'editable' => true,
			'selectHosts' => API_OUTPUT_EXTEND,
			'selectTriggers' => ['triggerid'],
			'selectDiscoveryRule' => API_OUTPUT_EXTEND,
			'selectItemDiscovery' => ['status', 'ts_delete', 'ts_disable', 'disable_source'],
			'selectTags' => ['tag', 'value'],
			'sortfield' => $input['sort'],
			'evaltype' => $input['filter_evaltype'],
			'tags' => $input['filter_tags'],
			'limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1
		];

		if ($input['filter_groupids']) {
			$options['groupids'] = getSubGroups($input['filter_groupids'], $ignore, $context);
		}

		if ($input['filter_hostids']) {
			$options['hostids'] = $input['filter_hostids'];

			if ($input['filter_valuemapids']) {
				$hostids = CTemplateHelper::getParentTemplatesRecursive($input['filter_hostids'], $context);
				$valuemap_names = array_unique(array_column(API::ValueMap()->get([
					'output' => ['name'],
					'valuemapids' => $input['filter_valuemapids']
				]), 'name'));
				$options['filter']['valuemapid'] = array_column(API::ValueMap()->get([
					'output' => ['valuemapid'],
					'hostids' => $hostids,
					'filter' => ['name' => $valuemap_names]
				]), 'valuemapid');
			}
		}

		if ($input['filter_name'] !== '') {
			$options['search']['name'] = $input['filter_name'];
		}

		if ($input['filter_key'] !== '') {
			$options['search']['key_'] = $input['filter_key'];
		}

		if ($input['filter_type'] != -1) {
			$options['filter']['type'] = $input['filter_type'];
		}

		if ($input['filter_value_type'] != -1) {
			$options['filter']['value_type'] = $input['filter_value_type'];
		}

		if ($input['filter_type'] == ITEM_TYPE_SNMP && $input['filter_snmp_oid'] !== '') {
			$options['filter']['snmp_oid'] = $input['filter_snmp_oid'];
		}

		if ($input['filter_delay'] !== '') {
			$options['filter']['delay'] = $input['filter_delay'];

			/*
			* Trapper and SNMP trap items contain zeros in "delay" field and, if no specific type is set, look in
			* item types other than trapper and SNMP trap that allow zeros.
			* For example, when a flexible interval is used. Since trapper and SNMP trap items contain zeros, but
			* those zeros should not be displayed, they cannot be filtered by entering either zero or any other
			* number in filter field.
			*/
			if ($input['filter_type'] == -1 && $input['filter_delay'] == 0) {
				$options['filter']['type'] = [ITEM_TYPE_ZABBIX, ITEM_TYPE_SIMPLE,  ITEM_TYPE_INTERNAL,
					ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI,
					ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_CALCULATED, ITEM_TYPE_JMX
				];
			}
			elseif ($input['filter_type'] == ITEM_TYPE_TRAPPER || $input['filter_type'] == ITEM_TYPE_SNMPTRAP
					|| $input['filter_type'] == ITEM_TYPE_DEPENDENT
					|| ($input['filter_type'] == ITEM_TYPE_ZABBIX_ACTIVE
						&& strpos($input['filter_type'], 'mqtt.get') === 0)) {
				$options['filter']['delay'] = -1;
			}
		}

		if ($input['filter_history'] != '') {
			$options['filter']['history'] = $input['filter_history'];
		}

		if ($input['filter_trends'] !== '') {
			$options['filter']['trends'] = $input['filter_trends'];

			if ($input['filter_value_type'] == -1) {
				$options['filter']['value_type'] = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64];
			}
		}

		if ($input['filter_state'] != -1) {
			$options['filter']['status'] = ITEM_STATUS_ACTIVE;
			$options['filter']['state'] = $input['filter_state'];
		}
		elseif ($input['filter_status'] != -1) {
			$options['filter']['status'] = $input['filter_status'];
		}

		if ($input['filter_inherited'] != -1) {
			$options['inherited'] = $input['filter_inherited'];
		}

		if ($input['filter_discovered'] != -1) {
			$options['filter']['flags'] = $input['filter_discovered'];
		}

		if ($input['filter_with_triggers'] != -1) {
			$options['with_triggers'] = $input['filter_with_triggers'];
		}

		$items = API::Item()->get($options);

		return expandItemNamesWithMasterItems($items, 'items');
	}

	/**
	 * Get items data matched subfilter and subfilters total match count with subfilter available values.
	 *
	 * @param array $items   Array of items returned by API.
	 * @param array $schema  Array of arrays with subfilter schema.
	 *
	 * @return array of two elements, filtered items array and subfilter schema array.
	 */
	protected function getItemsAndSubfilter(array $items, array $schema): array {
		$items_values = $this->getSubfilterColumnsData($items, array_keys($schema));
		$subfilters_input = [];

		foreach ($schema as &$subfilter) {
			$values = array_column($items_values, $subfilter['key']);
			$values = array_reduce($values, 'array_merge', []);

			if ($subfilter['selected']) {
				$subfilters_input[$subfilter['key']] = array_keys($subfilter['selected']);
			}

			$subfilter['values'] = array_fill_keys($values, 0);
		}
		unset($subfilter);

		foreach ($items_values as $item_index => $item_values) {
			$discard = [];

			foreach ($subfilters_input as $column => $subfilter_input) {
				if (!array_intersect($item_values[$column], $subfilter_input)) {
					$discard[$column] = true;
				}
			}

			foreach ($item_values as $column => $values) {
				if ($discard && array_diff_key($discard, [$column => true])) {
					continue;
				}

				foreach ($values as $value) {
					$schema[$column]['values'][$value]++;
				}
			}

			if ($discard) {
				unset($items[$item_index]);
			}
		}

		return [array_values($items), $schema];
	}

	/**
	 * Return array of item array having only columns used by subfilter, keys of items array are preserved.
	 *
	 * @param array $items   Array of items data.
	 * @param array $fields  Array of subfilters schema fields names.
	 *
	 * @return array
	 */
	protected function getSubfilterColumnsData(array $items, array $fields): array {
		$item_subfilter = [];
		$schema = array_fill_keys($fields, true);
		$update_interval_parser = new CUpdateIntervalParser(['usermacros' => true]);

		foreach ($items as $i => $item) {
			$item_fields = [
				'subfilter_hosts' => array_column($item['hosts'], 'hostid'),
				'subfilter_types' => [$item['type']],
				'subfilter_value_types' => [$item['value_type']],
				'subfilter_status' => [$item['status']],
				'subfilter_inherited' => [$item['templateid'] > 0 ? 1 : 0],
				'subfilter_with_triggers' => [count($item['triggers']) > 0 ? 1 : 0],
				'subfilter_history' => [$item['history']],
				'subfilter_state' => $item['status'] == ITEM_STATUS_ACTIVE ? [$item['state']] : [],
				'subfilter_discovered' => [$item['flags'] == ZBX_FLAG_DISCOVERY_CREATED ? 1 : 0],
				'subfilter_trends' => [],
				'subfilter_interval' => [],
				'subfilter_tags' => []
			];

			if ($item['history'][0] !== '{') {
				$item_fields['subfilter_history'] = [timeUnitToSeconds($item['history'])];
			}

			if ($item['trends'] !== '' && $item['trends'][0] !== '{'
					&& in_array($item['value_type'],  [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])) {
				$item_fields['subfilter_trends'][] = ($item['trends'] !== '' && $item['trends'][0] !== '{')
					? timeUnitToSeconds($item['trends'])
					: $item['trends'];
			}

			if (!($item['type'] == ITEM_TYPE_ZABBIX_ACTIVE && strpos($item['key_'], 'mqtt.get') === 0)
					&& !in_array($item['type'], [ITEM_TYPE_TRAPPER, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_DEPENDENT])) {
				$delay = $item['delay'];

				if ($update_interval_parser->parse($delay) == CParser::PARSE_SUCCESS) {
					$delay = $update_interval_parser->getDelay();
					$delay = ($delay[0] !== '{') ? timeUnitToSeconds($delay) : $delay;
				}

				$item_fields['subfilter_interval'][] = $delay;
			}

			foreach ($item['tags'] as $tag) {
				$item_fields['subfilter_tags'][] = implode(': ', [$tag['tag'], $tag['value']]);
			}

			$item_subfilter[$i] = array_intersect_key($item_fields, $schema);
		}


		return $item_subfilter;
	}

	/**
	 * Get filter data stored in profile and data required for multiselect initialization in filter form.
	 *
	 * @return array
	 */
	protected function getFilter(): array {
		$context = $this->getInput('context');
		$filter = $this->getProfiles() + [
			'ms_hostgroups' => [],
			'ms_hosts' => [],
			'ms_valuemaps' => []
		];

		if ($filter['filter_hostids']) {
			if ($context === 'host') {
				$filter['ms_hosts'] = CArrayHelper::renameObjectsKeys(API::Host()->get([
					'output' => ['hostid', 'name'],
					'hostids' => $filter['filter_hostids']
				]), ['hostid' => 'id']);
			}
			else {
				$filter['ms_hosts'] = CArrayHelper::renameObjectsKeys(API::Template()->get([
					'output' => ['hostid', 'name'],
					'templateids' => $filter['filter_hostids']
				]), ['templateid' => 'id']);
			}

			if ($filter['ms_hosts'] && $filter['filter_valuemapids']) {
				$filter['ms_valuemaps'] = CArrayHelper::renameObjectsKeys(API::ValueMap()->get([
					'output' => ['valuemapid', 'name'],
					'valuemapids' => $filter['filter_valuemapids']
				]), ['valuemapid' => 'id']);
			}
		}

		if ($filter['filter_groupids']) {
			$service = $context === 'host' ? API::HostGroup() : API::TemplateGroup();
			$filter['ms_hostgroups'] = CArrayHelper::renameObjectsKeys($service->get([
				'output' => ['groupid', 'name'],
				'groupids' => $filter['filter_groupids']
			]), ['groupid' => 'id']);
		}

		if ($this->getInput('sort', $filter['sort']) !== $filter['sort']
				|| $this->getInput('sortorder', $filter['sortorder']) !== $filter['sortorder']) {
			$this->getInputs($filter, ['sort', 'sortorder']);
			$this->updateProfileSort();
		}

		return $filter;
	}

	protected function getProfiles(): array {
		$prefix = $this->getInput('context') === 'host' ? 'web.hosts.items.list.' : 'web.templates.items.list.';
		$filter = [
			'filter_evaltype'		=> CProfile::get($prefix.'filter.evaltype', TAG_EVAL_TYPE_AND_OR),
			'filter_groupids'		=> CProfile::getArray($prefix.'filter_groupids', []),
			'filter_hostids'		=> CProfile::getArray($prefix.'filter_hostids', []),
			'filter_name'			=> CProfile::get($prefix.'filter_name', ''),
			'filter_type'			=> CProfile::get($prefix.'filter_type', -1),
			'filter_key'			=> CProfile::get($prefix.'filter_key', ''),
			'filter_snmp_oid'		=> CProfile::get($prefix.'filter_snmp_oid', ''),
			'filter_value_type'		=> CProfile::get($prefix.'filter_value_type', -1),
			'filter_delay'			=> CProfile::get($prefix.'filter_delay', ''),
			'filter_history'		=> CProfile::get($prefix.'filter_history', ''),
			'filter_trends'			=> CProfile::get($prefix.'filter_trends', ''),
			'filter_status'			=> CProfile::get($prefix.'filter_status', -1),
			'filter_state'			=> CProfile::get($prefix.'filter_state', -1),
			'filter_inherited'		=> CProfile::get($prefix.'filter_inherited', -1),
			'filter_discovered'		=> CProfile::get($prefix.'filter_discovered', -1),
			'filter_with_triggers'	=> CProfile::get($prefix.'filter_with_triggers', -1),
			'filter_valuemapids'	=> CProfile::getArray($prefix.'filter_valuemapids', []),
			'filter_tags'			=> []
		];

		$tags = CProfile::getArray($prefix.'filter.tags.tag', []);
		$values = CProfile::getArray($prefix.'filter.tags.value', []);
		$operators = CProfile::getArray($prefix.'filter.tags.operator', []);
		foreach ($tags as $i => $tag) {
			$filter['filter_tags'][] = [
				'tag'		=> $tag,
				'value'		=> $values[$i],
				'operator'	=> $operators[$i]
			];
		}

		$filter += [
			'filter_profile'	=> $prefix.'filter',
			'filter_tab'		=> CProfile::get($prefix.'filter.active', 1),
			'sort'				=> CProfile::get($prefix.'sort', 'name'),
			'sortorder' 		=> CProfile::get($prefix.'sortorder', ZBX_SORT_UP)
		];

		return $filter;
	}

	protected function updateProfiles() {
		$filter_tags = ['tags' => [], 'values' => [], 'operators' => []];
		foreach ($this->getInput('filter_tags', []) as $tag) {
			if ($tag['tag'] === '' && $tag['value'] === '') {
				continue;
			}

			$filter_tags['tags'][] = $tag['tag'];
			$filter_tags['values'][] = $tag['value'];
			$filter_tags['operators'][] = $tag['operator'];
		}

		$prefix = $this->getInput('context') === 'host' ? 'web.hosts.items.list.' : 'web.templates.items.list.';
		CProfile::updateArray($prefix.'filter_groupids', $this->getInput('filter_groupids', []), PROFILE_TYPE_ID);
		CProfile::updateArray($prefix.'filter_hostids', $this->getInput('filter_hostids', []), PROFILE_TYPE_ID);
		CProfile::updateArray($prefix.'filter_valuemapids', $this->getInput('filter_valuemapids', []), PROFILE_TYPE_ID);
		CProfile::update($prefix.'filter_name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
		CProfile::update($prefix.'filter_type', $this->getInput('filter_type', -1), PROFILE_TYPE_INT);
		CProfile::update($prefix.'filter_key', $this->getInput('filter_key', ''), PROFILE_TYPE_STR);
		CProfile::update($prefix.'filter_snmp_oid', $this->getInput('filter_snmp_oid', ''), PROFILE_TYPE_STR);
		CProfile::update($prefix.'filter_value_type', $this->getInput('filter_value_type', -1), PROFILE_TYPE_INT);
		CProfile::update($prefix.'filter_delay', $this->getInput('filter_delay', ''), PROFILE_TYPE_STR);
		CProfile::update($prefix.'filter_history', $this->getInput('filter_history', ''), PROFILE_TYPE_STR);
		CProfile::update($prefix.'filter_trends', $this->getInput('filter_trends', ''), PROFILE_TYPE_STR);
		CProfile::update($prefix.'filter_status', $this->getInput('filter_status', -1), PROFILE_TYPE_INT);
		CProfile::update($prefix.'filter_state', $this->getInput('filter_state', -1), PROFILE_TYPE_INT);
		CProfile::update($prefix.'filter_inherited', $this->getInput('filter_inherited', -1), PROFILE_TYPE_INT);
		CProfile::update($prefix.'filter_with_triggers', $this->getInput('filter_with_triggers', -1), PROFILE_TYPE_INT);
		CProfile::update($prefix.'filter_discovered', $this->getInput('filter_discovered', -1), PROFILE_TYPE_INT);
		CProfile::update($prefix.'filter.evaltype', $this->getInput('filter_evaltype', TAG_EVAL_TYPE_AND_OR), PROFILE_TYPE_INT);
		CProfile::updateArray($prefix.'filter.tags.tag', $filter_tags['tags'], PROFILE_TYPE_STR);
		CProfile::updateArray($prefix.'filter.tags.value', $filter_tags['values'], PROFILE_TYPE_STR);
		CProfile::updateArray($prefix.'filter.tags.operator', $filter_tags['operators'], PROFILE_TYPE_INT);
		$this->updateProfileSort();
	}

	protected function updateProfileSort() {
		$prefix = $this->getInput('context') === 'host' ? 'web.hosts.items.list.' : 'web.templates.items.list.';

		if ($this->hasInput('sort')) {
			CProfile::update($prefix.'sort', $this->getInput('sort'), PROFILE_TYPE_STR);
		}

		if ($this->hasInput('sortorder')) {
			CProfile::update($prefix.'sortorder', $this->getInput('sortorder'), PROFILE_TYPE_STR);
		}
	}

	protected function deleteProfiles() {
		$prefix = $this->getInput('context') === 'host' ? 'web.hosts.items.list.' : 'web.templates.items.list.';

		if (count(CProfile::getArray($prefix.'filter_hostids', [])) != 1) {
			CProfile::deleteIdx($prefix.'filter_hostids');
		}

		CProfile::deleteIdx($prefix.'filter_groupids');
		CProfile::deleteIdx($prefix.'filter_name');
		CProfile::deleteIdx($prefix.'filter_type');
		CProfile::deleteIdx($prefix.'filter_key');
		CProfile::deleteIdx($prefix.'filter_snmp_oid');
		CProfile::deleteIdx($prefix.'filter_value_type');
		CProfile::deleteIdx($prefix.'filter_delay');
		CProfile::deleteIdx($prefix.'filter_history');
		CProfile::deleteIdx($prefix.'filter_trends');
		CProfile::deleteIdx($prefix.'filter_status');
		CProfile::deleteIdx($prefix.'filter_state');
		CProfile::deleteIdx($prefix.'filter_inherited');
		CProfile::deleteIdx($prefix.'filter_with_triggers');
		CProfile::deleteIdx($prefix.'filter_discovered');
		CProfile::deleteIdx($prefix.'filter.tags.tag');
		CProfile::deleteIdx($prefix.'filter.tags.value');
		CProfile::deleteIdx($prefix.'filter.tags.operator');
		CProfile::deleteIdx($prefix.'filter.evaltype');
		CProfile::deleteIdx($prefix.'filter_valuemapids');
	}
}
