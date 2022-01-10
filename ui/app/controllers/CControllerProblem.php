<?php declare(strict_types = 1);
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
 * Base controller for the "Monitoring->Problems" page.
 */
abstract class CControllerProblem extends CController {

	// Filter idx prefix.
	const FILTER_IDX = 'web.monitoring.problem';

	// Filter fields default values.
	const FILTER_FIELDS_DEFAULT = [
		'show' => TRIGGERS_OPTION_RECENT_PROBLEM,
		'groupids' => [],
		'hostids' => [],
		'triggerids' => [],
		'name' => '',
		'severities' => [],
		'age_state' => 0,
		'age' => 14,
		'inventory' => [],
		'evaltype' => TAG_EVAL_TYPE_AND_OR,
		'tags' => [],
		'show_tags' => SHOW_TAGS_3,
		'show_suppressed' => 0,
		'unacknowledged' => 0,
		'compact_view' => 0,
		'show_timeline' => 1,
		'details' => 0,
		'highlight_row' => 0,
		'show_opdata' => OPERATIONAL_DATA_SHOW_NONE,
		'tag_name_format' => TAG_NAME_FULL,
		'tag_priority' => '',
		'page' => null,
		'sort' => 'clock',
		'sortorder' => ZBX_SORT_DOWN,
		'from' => '',
		'to' => ''
	];

	/**
	 * Get count of resulting rows for specified filter.
	 *
	 * @param array $filter   Filter fields values.
	 *
	 * @return int
	 */
	protected function getCount(array $filter): int {
		$range_time_parser = new CRangeTimeParser();
		$range_time_parser->parse($filter['from']);
		$filter['from'] = $range_time_parser->getDateTime(true)->getTimestamp();
		$range_time_parser->parse($filter['to']);
		$filter['to'] = $range_time_parser->getDateTime(false)->getTimestamp();

		$data = CScreenProblem::getData($filter);

		return count($data['problems']);
	}

	/**
	 * Get resulting rows for specified filter.
	 *
	 * @param array $filter  Filter fields values.
	 *
	 * @return array
	 */
	protected function getData(array $filter): array {
		// getData is handled by jsrpc.php 'screen.get' action.
		return [];
	}

	/**
	 * Get additional data required for render filter as HTML.
	 *
	 * @param array $filter  Filter fields values.
	 *
	 * @return array
	 */
	protected function getAdditionalData(array $filter): array {
		$data = [
			'groups' => [],
			'hosts' => [],
			'triggers' => []
		];

		// Host groups multiselect.
		if ($filter['groupids']) {
			$host_groups = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $filter['groupids'],
				'preservekeys' => true
			]);
			$data['groups'] = CArrayHelper::renameObjectsKeys($host_groups, ['groupid' => 'id']);
		}

		// Triggers multiselect.
		if ($filter['triggerids']) {
			$triggers = CArrayHelper::renameObjectsKeys(API::Trigger()->get([
				'output' => ['triggerid', 'description'],
				'selectHosts' => ['name'],
				'expandDescription' => true,
				'triggerids' => $filter['triggerids'],
				'monitored' => true
			]), ['triggerid' => 'id', 'description' => 'name']);

			CArrayHelper::sort($triggers, [
				['field' => 'name', 'order' => ZBX_SORT_UP]
			]);

			foreach ($triggers as &$trigger) {
				$trigger['prefix'] = $trigger['hosts'][0]['name'].NAME_DELIMITER;
				unset($trigger['hosts']);
			}

			$data['triggers'] = $triggers;
		}

		// Hosts multiselect.
		if ($filter['hostids']) {
			$data['hosts'] = CArrayHelper::renameObjectsKeys(API::Host()->get([
				'output' => ['hostid', 'name'],
				'hostids' => $filter['hostids']
			]), ['hostid' => 'id']);
		}

		return $data;
	}

	/**
	 * Clean passed filter fields in input from default values required for HTML presentation. Convert field
	 *
	 * @param array $input  Filter fields values.
	 *
	 * @return array
	 */
	protected function cleanInput(array $input): array {
		if (array_key_exists('filter_reset', $input) && $input['filter_reset']) {
			return array_intersect_key(['filter_name' => ''], $input);
		}

		if (array_key_exists('tags', $input) && $input['tags']) {
			$input['tags'] = array_filter($input['tags'], function($tag) {
				return !($tag['tag'] === '' && $tag['value'] === '');
			});
			$input['tags'] = array_values($input['tags']);
		}

		if (array_key_exists('inventory', $input) && $input['inventory']) {
			$input['inventory'] = array_filter($input['inventory'], function($inventory) {
				return $inventory['value'] !== '';
			});
			$input['inventory'] = array_values($input['inventory']);
		}

		return $input;
	}
}
