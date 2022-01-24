<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


class CControllerWidgetTopHostsView extends CControllerWidget {

	public function __construct() {
		parent::__construct();

		$this->setType(WIDGET_TOP_HOSTS);
		$this->setValidationRules([
			'name' => 'string',
			'fields' => 'json'
		]);
	}

	protected function doAction() {
		$fields = $this->getForm()->getFieldsData();

		$groupids = $fields['groupids'] ? getSubGroups($fields['groupids']) : null;
		$hostids = $fields['hostids'] ?: null;

		if (array_key_exists('tags', $fields)) {
			$hosts = API::Host()->get([
				'output' => ['name'],
				'groupids' => $groupids,
				'hostids' => $hostids,
				'evaltype' => $fields['evaltype'],
				'tags' => $fields['tags'],
				'monitored_hosts' => true,
				'preservekeys' => true
			]);

			$hostids = array_keys($hosts);
		}
		else {
			$hosts = null;
		}

		$time_now = time();

		$master_items = self::getItems($fields['columns'][$fields['column']]['item'], $groupids, $hostids);
		$master_item_values = self::getItemValues($master_items, $fields['columns'][$fields['column']], $time_now);

		if ($fields['order'] == CWidgetFormTopHosts::ORDER_TOPN) {
			arsort($master_item_values, SORT_NUMERIC);
		}
		else {
			asort($master_item_values, SORT_NUMERIC);
		}

		$master_item_values = array_slice($master_item_values, 0, $fields['count'], true);
		$master_items = array_intersect_key($master_items, $master_item_values);

		$master_hostids = [];

		foreach (array_keys($master_item_values) as $itemid) {
			$master_hostids[] = $master_items[$itemid]['hostid'];
		}

		$item_values = [];

		foreach ($fields['columns'] as $column_index => $column) {
			if ($column['data'] != CWidgetFieldColumnsList::DATA_ITEM_VALUE) {
				continue;
			}

			if ($column_index == $fields['column']) {
				$column_items = $master_items;
				$column_item_values = $master_item_values;
			}
			else {
				$column_items = self::getItems($column['item'], $groupids, $master_hostids);
				$column_item_values = self::getItemValues($column_items, $column, $time_now);
			}

			$item_values[$column_index] = [];

			foreach ($column_item_values as $itemid => $column_item_value) {
				$item_values[$column_index][$column_items[$itemid]['hostid']] = [
					'value' => $column_item_value,
					'item' => $column_items[$itemid]
				];
			}
		}

		$text_columns = [];

		foreach ($fields['columns'] as $column_index => $column) {
			if ($column['data'] == CWidgetFieldColumnsList::DATA_TEXT) {
				$text_columns[$column_index] = $column['text'];
			}
		}

		$text_columns = CMacrosResolverHelper::resolveWidgetTopHostsTextColumns($text_columns, $master_hostids);

		$rows = [];

		foreach ($master_hostids as $hostid) {
			$row = [];

			foreach ($fields['columns'] as $column_index => $column) {
				switch ($column['data']) {
					case CWidgetFieldColumnsList::DATA_HOST_NAME:
						if ($hosts === null) {
							$hosts = API::Host()->get([
								'output' => ['name'],
								'groupids' => $groupids,
								'hostids' => $master_hostids,
								'monitored_hosts' => true,
								'preservekeys' => true
							]);
						}

						$row[] = [
							'value' => $hosts[$hostid]['name'],
							'hostid' => $hostid
						];

						break;

					case CWidgetFieldColumnsList::DATA_TEXT:
						$row[] = [
							'value' => $text_columns[$column_index][$hostid],
							'hostid' => $hostid
						];

						break;

					case CWidgetFieldColumnsList::DATA_ITEM_VALUE:
						$row[] = array_key_exists($hostid, $item_values[$column_index])
							? [
								'value' => $item_values[$column_index][$hostid]['value'],
								'item' => $item_values[$column_index][$hostid]['item']
							]
							: null;

						break;
				}
			}

			$rows[] = $row;
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->getDefaultName()),
			'configuration' => $fields['columns'],
			'rows' => $rows,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}

	/**
	 * @param string     $name
	 * @param array|null $groupids
	 * @param array|null $hostids
	 *
	 * @return array
	 */
	private static function getItems(string $name, ?array $groupids, ?array $hostids): array {
		$items = API::Item()->get([
			'output' => ['itemid', 'hostid', 'key_', 'history', 'trends', 'value_type', 'units'],
			'selectValueMap' => ['mappings'],
			'groupids' => $groupids,
			'hostids' => $hostids,
			'monitored' => true,
			'webitems' => true,
			'filter' => [
				'status' => ITEM_STATUS_ACTIVE,
				'name' => $name
			],
			'sortfield' => 'key_',
			'preservekeys' => true
		]);

		if ($items) {
			$single_key = reset($items)['key_'];

			$items = array_filter($items,
				static function ($item) use ($single_key): bool  {
					return $item['key_'] === $single_key;
				}
			);
		}

		return $items;
	}

	/**
	 * @param array $items
	 * @param array $column_fields
	 * @param int   $time_now
	 *
	 * @return array
	 */
	private static function getItemValues(array $items, array $column_fields, int $time_now): array {
		$timeshift = $column_fields['timeshift'] !== '' ? timeUnitToSeconds($column_fields['timeshift']) : 0;

		if ($timeshift == 0 && $column_fields['aggregate_function'] == AGGREGATE_NONE) {
			$data = Manager::History()->getLastValues($items);

			return array_column(array_column($data, 0), 'value', 'itemid');
		}

		$time_to = $time_now + $timeshift;

		$time_from = $column_fields['aggregate_function'] != AGGREGATE_NONE
			? $time_to - timeUnitToSeconds($column_fields['aggregate_interval'])
			: $time_to - timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD));

		self::addDataSource($items, $time_from, $time_now, $column_fields['data']);

		$function = $column_fields['aggregate_function'] != AGGREGATE_NONE
			? $column_fields['aggregate_function']
			: AGGREGATE_LAST;

		$interval = $time_to;

		$data = Manager::History()->getAggregationByInterval($items, $time_from, $time_to, $function, $interval);

		return array_column(array_column(array_column($data, 'data'), 0), 'value', 'itemid');
	}

	/**
	 * @param array $items
	 * @param int   $time_from
	 * @param int   $time_now
	 * @param int   $data_source
	 *
	 * @return void
	 */
	private static function addDataSource(array &$items, int $time_from, int $time_now, int $data_source): void {
		if ($data_source == CWidgetFieldColumnsList::HISTORY_DATA_HISTORY
				|| $data_source == CWidgetFieldColumnsList::HISTORY_DATA_TRENDS) {
			foreach ($items as &$item) {
				$item['source'] = $data_source == CWidgetFieldColumnsList::HISTORY_DATA_HISTORY ? 'history' : 'trends';
			}
			unset($item);

			return;
		}

		static $hk_history_global, $global_history_time, $hk_trends_global, $global_trends_time;

		if ($hk_history_global === null) {
			$hk_history_global = CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_GLOBAL);

			if ($hk_history_global) {
				$global_history_time = timeUnitToSeconds(CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY));
			}
		}

		if ($hk_trends_global === null) {
			$hk_trends_global = CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS_GLOBAL);

			if ($hk_history_global) {
				$global_trends_time = timeUnitToSeconds(CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS));
			}
		}

		if ($hk_history_global) {
			foreach ($items as &$item) {
				$item['history'] = $global_history_time;
			}
			unset($item);
		}

		if ($hk_trends_global) {
			foreach ($items as &$item) {
				$item['trends'] = $global_trends_time;
			}
			unset($item);
		}

		if (!$hk_history_global || !$hk_trends_global) {
			$items = CMacrosResolverHelper::resolveTimeUnitMacros($items,
				array_merge($hk_history_global ? [] : ['history'], $hk_trends_global ? [] : ['trends'])
			);

			$processed_items = [];

			foreach ($items as $item) {
				if (!$global_trends_time) {
					$item['history'] = timeUnitToSeconds($item['history']);

					if ($item['history'] === null) {
						error(_s('Incorrect value for field "%1$s": %2$s.', 'history',
							_('invalid history storage period')
						));

						continue;
					}
				}

				if (!$hk_trends_global) {
					$item['trends'] = timeUnitToSeconds($item['trends']);

					if ($item['trends'] === null) {
						error(_s('Incorrect value for field "%1$s": %2$s.', 'trends',
							_('invalid trend storage period')
						));

						continue;
					}
				}

				$processed_items[] = $item;
			}

			$items = $processed_items;
		}

		foreach ($items as &$item) {
			$item['source'] = $item['trends'] == 0 || $time_now - $item['history'] <= $time_from ? 'history' : 'trends';
		}
		unset($item);
	}
}
