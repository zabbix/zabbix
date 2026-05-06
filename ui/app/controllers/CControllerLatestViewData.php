<?php declare(strict_types = 0);
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


class CControllerLatestViewData extends CControllerDataTable {

	protected array $allowed_data_fields = ['itemid', 'data_actions', 'host', 'maintenance', 'maintenanceid',
		'maintenance_type', 'maintenance_status', 'itemid', 'description_expanded', 'name', 'key_expanded', 'interval',
		'history', 'trends', 'type', 'state', 'last_check', 'last_value', 'change', 'is_graph', 'keep_history',
		'keep_trends', 'item_icons', 'tags'];

	protected function init(): void {
		parent::init();

		$this->addValidationRules(['sort_field' => 'string|in host,name']);
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_MONITORING_LATEST_DATA);
	}

	protected function getData(): array {
		$data_fields = $this->getDataFields();
		$filter = $this->getInput('filter', []);
		$page = $this->getInput('page', 1);

		$sort_field = $this->getInput('sort_field', CControllerLatest::DEFAULT_SORT);
		$sort_order = $this->getInput('sort_order', CControllerLatest::DEFAULT_SORTORDER);

		if ($filter['tags']) {
			$filter['tags'] = array_filter($filter['tags'], static fn(array $tag) => $tag && $tag['tag'] != '');
		}

		$mandatory_filter_set = CControllerLatest::isMandatoryFilterFieldSet($filter);
		$subfilter_set = CControllerLatest::isSubfilterSet($filter);

		if (!$mandatory_filter_set && !$subfilter_set) {
			return [
				'fields' => [],
				'rows' => [],
				'no_data_icon' => ZBX_ICON_FILTER_LARGE,
				'no_data_message' => _('Filter is not set'),
				'no_data_description' => _('Use the filter to display results')
			];
		}

		$filter = CControllerLatest::sanitizeFilter($filter);

		$data = $this->prepareData($filter, $sort_field, $sort_order);

		$data['items'] = CMacrosResolverHelper::resolveItemKeys($data['items']);
		$data['items'] = CMacrosResolverHelper::resolveItemDescriptions($data['items']);
		$data['items'] = CMacrosResolverHelper::resolveTimeUnitMacros($data['items'], ['delay', 'history', 'trends']);

		$history = Manager::History()->getLastValues($data['items'], 2,
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD))
		);

		$hosts_on_page = array_intersect_key($data['hosts'], array_column($data['items'], 'hostid', 'hostid'));

		$maintenanceids = [];

		foreach ($hosts_on_page as $host) {
			if ($host['status'] == HOST_STATUS_MONITORED &&	$host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
				$maintenanceids[$host['maintenanceid']] = true;
			}
		}

		$db_maintenances = [];

		if ($maintenanceids) {
			$db_maintenances = API::Maintenance()->get([
				'output' => ['name', 'description', 'status'],
				'maintenanceids' => array_keys($maintenanceids),
				'preservekeys' => true
			]);
		}

		$subfilters_fields = CControllerLatest::getSubfilterFields($filter);
		$subfilters = CControllerLatest::getSubfilters($subfilters_fields, $data);
		$data['items'] = CControllerLatest::applySubfilters($data['items']);

		$this->paging = $this->paginate($data['items'], $page, $sort_order);

		if ($filter['state'] != -1) {
			$subfilters['state'] = [];
		}

		order_result($data['items'], $sort_field, $sort_order);

		$simple_interval_parser = new CSimpleIntervalParser();
		$update_interval_parser = new CUpdateIntervalParser(['usermacros' => true]);

		$config = [
			'hk_trends' => CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS),
			'hk_trends_global' => CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS_GLOBAL),
			'hk_history' => CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY),
			'hk_history_global' => CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_GLOBAL)
		];

		foreach ($data['items'] as &$item) {
			$host = $hosts_on_page[$item['hostid']];

			$data_actions = [];
			$is_graph = $item['value_type'] == ITEM_VALUE_TYPE_FLOAT || $item['value_type'] == ITEM_VALUE_TYPE_UINT64;
			if ($is_graph) {
				$data_actions['graph'] = true;
			}

			if (in_array($item['type'], checkNowAllowedTypes()) && $item['status'] == ITEM_STATUS_ACTIVE
					&& $host['status'] == HOST_STATUS_MONITORED
					&& array_key_exists($item['itemid'], $data['items_rw'])) {
				$data_actions['execute'] = true;
			}

			$item['is_graph'] = $is_graph;
			$item['data_actions'] = $data_actions;
			$item['host'] = $host;
			$item['maintenance'] = $db_maintenances[$host['maintenanceid']] ?? null;

			$item_icons = [];
			if ($item['status'] == ITEM_STATUS_ACTIVE && $item['error'] !== '') {
				$item_icons[] = makeErrorIcon($item['error']);
			}

			// Row history data preparation.
			$last_history = array_key_exists($item['itemid'], $history)
				? (count($history[$item['itemid']]) > 0 ? $history[$item['itemid']][0] : null)
				: null;

			if ($last_history) {
				$prev_history = count($history[$item['itemid']]) > 1 ? $history[$item['itemid']][1] : null;

				$last_check = (new CSpan(zbx_date2age($last_history['clock'])))
					->addClass(ZBX_STYLE_CURSOR_POINTER)
					->setHint(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $last_history['clock']), '', true, '', 0)
					->toString();

				if ($item['value_type'] == ITEM_VALUE_TYPE_BINARY) {
					$last_value = italic(_('binary value'))
						->addClass(ZBX_STYLE_GREY)
						->toString();
				}
				else {
					$last_value = (new CSpan(formatHistoryValue($last_history['value'], $item, false)))
						->addClass(ZBX_STYLE_CURSOR_POINTER)
						->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)
						->setHint(
							(new CTrim($last_history['value'], ZBX_HINTBOX_HTML_LIMIT))
								->addClass(ZBX_STYLE_HINTBOX_RAW_DATA)
								->addClass(ZBX_STYLE_HINTBOX_WRAP),
							'', true, '', 0
						)
						->toString();
				}

				$change = '';

				if ($prev_history && in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])) {
					$history_diff = $last_history['value'] - $prev_history['value'];

					if ($history_diff != 0) {
						if ($history_diff > 0) {
							$change = '+';
						}

						// The change must be calculated as uptime for the 'unixtime'.
						$change .= convertUnits([
							'value' => $history_diff,
							'units' => $item['units'] === 'unixtime' ? 'uptime' : $item['units']
						]);
					}
				}
			}
			else {
				$last_check = '';
				$last_value = '';
				$change = '';
			}

			if (in_array($item['type'], [ITEM_TYPE_SNMPTRAP, ITEM_TYPE_TRAPPER, ITEM_TYPE_DEPENDENT])
					|| ($item['type'] == ITEM_TYPE_ZABBIX_ACTIVE && strncmp($item['key_expanded'], 'mqtt.get', 8) == 0)) {
				$item_delay = '';
			}
			elseif ($update_interval_parser->parse($item['delay']) == CParser::PARSE_SUCCESS) {
				$item_delay = $update_interval_parser->getDelay();

				if ($item_delay[0] === '{') {
					$item_delay = (new CSpan($item_delay))
						->addClass(ZBX_STYLE_RED)
						->toString();
				}
			}
			else {
				$item_delay = (new CSpan($item['delay']))
					->addClass(ZBX_STYLE_RED)
					->toString();
			}

			$item['interval'] = $item_delay;

			if ($config['hk_history_global']) {
				$keep_history = timeUnitToSeconds($config['hk_history']);
				$item_history = $config['hk_history'];
			}
			elseif ($simple_interval_parser->parse($item['history']) == CParser::PARSE_SUCCESS) {
				$keep_history = timeUnitToSeconds($item['history']);
				$item_history = $item['history'];
			}
			else {
				$keep_history = 0;
				$item_history = (new CSpan($item['history']))
					->addClass(ZBX_STYLE_RED)
					->toString();
			}

			$item['keep_history'] = $keep_history;
			$item['history'] = $item_history;

			if ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT || $item['value_type'] == ITEM_VALUE_TYPE_UINT64) {
				if ($config['hk_trends_global']) {
					$keep_trends = timeUnitToSeconds($config['hk_trends']);
					$item_trends = $config['hk_trends'];
				}
				elseif ($simple_interval_parser->parse($item['trends']) == CParser::PARSE_SUCCESS) {
					$keep_trends = timeUnitToSeconds($item['trends']);
					$item_trends = $item['trends'];
				}
				else {
					$keep_trends = 0;
					$item_trends = (new CSpan($item['trends']))->addClass(ZBX_STYLE_RED);
				}
			}
			else {
				$keep_trends = 0;
				$item_trends = '';
			}

			$item['keep_trends'] = $keep_trends;
			$item['trends'] = $item_trends;
			$item['type'] = item_type2str($item['type']);
			$item['last_check'] = $last_check;
			$item['last_value'] = $last_value;
			$item['change'] = $change;

			CArrayHelper::sort($item['tags'], ['tag', 'value']);
			$item['tags'] = CTagHelper::getTagsList($item);

			$item['item_icons'] = (string) makeInformationList($item_icons);

			$item['description_expanded'] = (new CObject())
				->addItem(zbx_str2links($item['description_expanded']))
				->toString();
		}
		unset($item);

		return [
			'filter_counters' => $this->getFilterCounters(),
			'data_fields' => $data_fields,
			'rows' => array_values(array_map(static fn (array $item) => [[], $item], $data['items'])),
			'subfilter_tags' => array_key_exists('tags', $subfilters_fields) ? $subfilters_fields['tags'] : [],
			'subfilter' => (new CPartial('monitoring.latest.subfilter', [
				'subfilters' => $subfilters,
				'subfilters_expanded' => array_flip($filter['subfilters_expanded'] ?? [])
			]))->getOutput()
		];
	}

	private function getFilterCounters(): array {
		$filter_counters = [];

		if (CViewHelper::loadLayoutMode() == ZBX_LAYOUT_KIOSKMODE) {
			return $filter_counters;
		}

		$profile = (new CTabFilterProfile('web.monitoring.latest', CControllerLatest::FILTER_FIELDS_DEFAULT))
			->read();

		$filters = $profile->getTabsWithDefaults();

		foreach ($filters as $index => $tabfilter) {
			$filter_counters[$index] = 0;

			$tabfilter = CControllerLatest::sanitizeFilter($tabfilter);
			$mandatory_filter_set = CControllerLatest::isMandatoryFilterFieldSet($tabfilter);
			$subfilter_set = CControllerLatest::isSubfilterSet($tabfilter);

			if (!$tabfilter['filter_show_counter'] || (!$mandatory_filter_set && !$subfilter_set)) {
				continue;
			}

			$prepared_data = $this->prepareData($tabfilter, CControllerLatest::DEFAULT_SORT,
				CControllerLatest::DEFAULT_SORTORDER);
			$subfilters_fields = CControllerLatest::getSubfilterFields($tabfilter);

			CControllerLatest::getSubfilters($subfilters_fields, $prepared_data);

			$filter_counters[$index] = count(CControllerLatest::applySubfilters($prepared_data['items']));
		}

		return $filter_counters;
	}

	private function prepareData(array $filter, string $sort_field, string $sort_order): array {
		// Select groups for subsequent selection of hosts and items.
		$groupids = $filter['groupids'] ? getSubGroups($filter['groupids']) : null;

		// Select hosts for subsequent selection of items.
		$hosts = API::Host()->get([
			'output' => ['hostid', 'name', 'status', 'maintenanceid', 'maintenance_status', 'maintenance_type'],
			'groupids' => $groupids,
			'hostids' => $filter['hostids'] ?: null,
			'preservekeys' => true
		]);

		$search_limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT);
		$select_items_cnt = 0;
		$select_items = [];

		foreach ($hosts as $hostid => $host) {
			if ($select_items_cnt > $search_limit) {
				unset($hosts[$hostid]);
				continue;
			}

			$select_items += API::Item()->get([
				'output' => ['itemid', 'hostid', 'value_type'],
				'hostids' => [$hostid],
				'webitems' => true,
				'evaltype' => $filter['evaltype'],
				'tags' => $filter['tags'] ?: null,
				'inheritedTags' => true,
				'filter' => [
					'status' => [ITEM_STATUS_ACTIVE],
					'state' => $filter['state'] == -1 ? null : $filter['state']
				],
				'search' => $filter['name'] === '' ? null : ['name_resolved' => $filter['name']],
				'preservekeys' => true
			]);

			$select_items_cnt = count($select_items);
		}

		if ($select_items) {
			$items = CArrayHelper::renameObjectsKeys(API::Item()->get([
				'output' => ['itemid', 'type', 'hostid', 'name_resolved', 'key_', 'delay', 'history', 'trends',
					'status', 'value_type', 'units', 'description', 'state', 'error'
				],
				'selectTags' => ['tag', 'value'],
				'selectInheritedTags' => ['tag', 'value'],
				'selectValueMap' => ['mappings'],
				'itemids' => array_keys($select_items),
				'webitems' => true,
				'preservekeys' => true
			]), ['name_resolved' => 'name']);

			CTagHelper::mergeOwnAndInheritedTags($items);

			// If user role checkbox 'Invoke "Execute now" on read-only hosts' is ON, read-write items are the same.
			$items_rw = $items;

			// If user role checkbox 'Invoke "Execute now" on read-only hosts' is OFF, get only read-write items.
			if (!$this->hasInput('filter_counters') && $this->getUserType() < USER_TYPE_SUPER_ADMIN
					&& !$this->checkAccess(CRoleHelper::ACTIONS_INVOKE_EXECUTE_NOW)) {
				$items_rw = API::Item()->get([
					'output' => [],
					'itemids' => array_keys($items),
					'editable' => true,
					'preservekeys' => true
				]);
			}

			if ($sort_field === 'host') {
				$items = array_map(function ($item) use ($hosts) {
					return $item + ['host_name' => $hosts[$item['hostid']]['name']];
				}, $items);

				CArrayHelper::sort($items, [['field' => 'host_name', 'order' => $sort_order]]);
			}
			else {
				CArrayHelper::sort($items, [['field' => 'name', 'order' => $sort_order]]);
			}
		}
		else {
			$hosts = [];
			$items = [];
			$items_rw = [];
		}

		return [
			'hosts' => $hosts,
			'items' => $items,
			'items_rw' => $items_rw
		];
	}
}
