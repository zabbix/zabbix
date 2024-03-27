<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


namespace Widgets\ItemNavigator\Actions;

use API,
	CArrayHelper,
	CControllerDashboardWidgetView,
	CControllerResponseData,
	CProfile;

use Widgets\ItemNavigator\Includes\WidgetForm;

class WidgetView extends CControllerDashboardWidgetView {

	protected function init(): void {
		parent::init();

		$this->addValidationRules([
			'with_config' =>	'in 1',
			'widgetid' =>		'db widget.widgetid',
			'fields' =>			'array'
		]);
	}

	protected function doAction(): void {
		$data = [
			'name' => $this->getInput('name', $this->widget->getDefaultName()),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'vars' => $this->getItems()
		];

		if ($this->hasInput('with_config')) {
			$data['vars']['config'] = $this->getConfig($this->hasInput('widgetid')
				? $this->getInput('widgetid')
				: null
			);
		}

		$this->setResponse(new CControllerResponseData($data));
	}

	private function getItems(): array {
		$no_data = [
			'hosts' => [],
			'items' => [],
			'is_limit_exceeded' => false
		];

		$override_hostid = $this->fields_values['override_hostid'] ? $this->fields_values['override_hostid'][0] : '';

		if ($override_hostid === '' && $this->isTemplateDashboard()) {
			return $no_data;
		}

		$group_by_host_groups = false;
		$group_by_host_name = false;
		$host_tags_to_keep = [];
		$item_tags_to_keep = [];

		foreach ($this->fields_values['group_by'] as $group_by_attribute) {
			switch ($group_by_attribute['attribute']) {
				case WidgetForm::GROUP_BY_HOST_GROUP:
					$group_by_host_groups = true;
					break;
				case WidgetForm::GROUP_BY_HOST_NAME:
					$group_by_host_name = true;
					break;
				case WidgetForm::GROUP_BY_HOST_TAG:
					$host_tags_to_keep[] = $group_by_attribute['tag_name'];
					break;
				case WidgetForm::GROUP_BY_ITEM_TAG:
					$item_tags_to_keep[] = $group_by_attribute['tag_name'];
					break;
			}
		}

		$options = [
			'output' => ['itemid', 'hostid'],
			'webitems' => true,
			'evaltype' => $this->fields_values['item_tags_evaltype'],
			'tags' => $this->fields_values['item_tags'] ?: null,
			'filter' => [
				'state' => $this->fields_values['state'] == WidgetForm::STATE_ALL
					? null
					: $this->fields_values['state']
			],
			'searchWildcardsEnabled' => true,
			'searchByAny' => true,
			'selectTags' => $item_tags_to_keep ? ['tag', 'value'] : null,
			'sortfield' => 'name',
			'sortorder' => ZBX_SORT_UP
		];

		$resolve_macros = $override_hostid !== '' && !$this->isTemplateDashboard();
		$name_pattern = in_array('*', $this->fields_values['items'], true) ? null : $this->fields_values['items'];

		if ($resolve_macros) {
			$options['output'][] = 'name_resolved';

			if ($this->getInput('templateid', '') === '') {
				$options['search']['name_resolved'] = $name_pattern;
			}
			else {
				$options['search']['name'] = $name_pattern;
			}
		}
		else {
			$options['output'][] = 'name';
			$options['search']['name'] = $name_pattern;
		}

		$limit = $this->fields_values['show_lines'];
		$selected_items_cnt = 0;
		$items = [];
		$hosts = [];

		if ($override_hostid === '' && !$this->isTemplateDashboard()) {
			$hosts = API::Host()->get([
				'output' => $group_by_host_name ? ['hostid', 'name'] : [],
				'groupids' => $this->fields_values['groupids'] ? getSubGroups($this->fields_values['groupids']) : null,
				'hostids' => $this->fields_values['hostids'] ?: null,
				'evaltype' => $this->fields_values['host_tags_evaltype'],
				'tags' => $this->fields_values['host_tags'] ?: null,
				'selectHostGroups' => $group_by_host_groups ? ['groupid', 'name'] : null,
				'selectTags' => $host_tags_to_keep ? ['tag', 'value'] : null,
				'preservekeys' => true,
				'sortfield' => 'name',
			]);

			if (!$hosts) {
				return $no_data;
			}

			foreach ($hosts as $hostid => $host) {
				if ($selected_items_cnt == $limit + 1) {
					unset($hosts[$hostid]);
					break;
				}
				else {
					$options['limit'] = $limit + 1 - $selected_items_cnt;
				}

				$options['hostids'] = [$hostid];

				$items = array_merge($items, API::Item()->get($options));

				$selected_items_cnt += count($items);
			}
		}
		elseif ($override_hostid !== '') {
			$hosts = API::Host()->get([
				'output' => $group_by_host_name ? ['hostid', 'name'] : [],
				'hostids' => [$override_hostid],
				'selectHostGroups' => $group_by_host_groups ? ['groupid', 'name'] : null,
				'selectTags' => $host_tags_to_keep ? ['tag', 'value'] : null
			]);

			if (!$hosts) {
				return $no_data;
			}

			$options['hostids'] = [$override_hostid];
			$options['limit'] = $limit + 1;

			$items = API::Item()->get($options);
		}

		if (!$items) {
			return $no_data;
		}

		$is_limit_exceeded = false;

		if (count($items) > $limit) {
			$is_limit_exceeded = true;

			array_pop($items);
		}

		if ($resolve_macros) {
			$items = CArrayHelper::renameObjectsKeys($items, ['name_resolved' => 'name']);
		}

		if ($host_tags_to_keep) {
			self::filterTags($hosts, $host_tags_to_keep);
		}

		if ($item_tags_to_keep) {
			self::filterTags($items, $item_tags_to_keep);
		}

		if ($this->fields_values['problems'] !== WidgetForm::PROBLEMS_NONE) {
			$triggers = API::Trigger()->get([
				'output' => [],
				'selectItems' => ['itemid'],
				'itemids' => array_column($items, 'itemid'),
				'skipDependent' => true,
				'monitored' => true,
				'preservekeys' => true
			]);

			$problems = API::Problem()->get([
				'output' => ['eventid', 'objectid', 'severity'],
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'objectids' => array_keys($triggers),
				'suppressed' => $this->fields_values['problems'] == WidgetForm::PROBLEMS_UNSUPPRESSED ? false : null,
				'symptom' => false
			]);

			$item_problems = [];

			foreach ($problems as $problem) {
				foreach ($triggers[$problem['objectid']]['items'] as $trigger_item) {
					$item_problems[$trigger_item['itemid']][$problem['severity']][$problem['eventid']] = true;
				}
			}

			foreach ($items as &$item) {
				$item['problem_count'] = array_fill(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT, 0);

				// Count the number of problems (as value) per severity (as key).
				if ($item_problems && array_key_exists($item['itemid'], $item_problems)) {
					foreach ($item_problems[$item['itemid']] as $severity => $problems) {
						$item['problem_count'][$severity] = count($problems);
					}
				}
			}
			unset($item);
		}

		return [
			'hosts' => $hosts,
			'items' => $items,
			'is_limit_exceeded' => $is_limit_exceeded
		];
	}

	private function getConfig(string $widgetid = null): array {
		$open_groups = [];

		if ($widgetid !== null) {
			$open_groupids = CProfile::findByIdxPattern('web.dashboard.widget.open.%', $widgetid);

			foreach ($open_groupids as $open_groupid) {
				$open_group = CProfile::get($open_groupid, [], $widgetid);

				if ($open_group) {
					$open_groups[] = $open_group;
				}
			}
		}

		return [
			'group_by' => $this->fields_values['group_by'],
			'open_groups' => $open_groups,
			'show_problems' => $this->fields_values['problems'] != WidgetForm::PROBLEMS_NONE
		];
	}

	/**
	 * Filters tags of a given entity (host or item) based on the specified tags to keep.
	 *
	 * @param array $entities     The entities (hosts or items) to filter.
	 * @param array $tags_to_keep The tags to retain in the entities.
	 *
	 * @return void
	 */
	private static function filterTags(array &$entities, array $tags_to_keep): void {
		foreach ($entities as &$entity) {
			$entity['tags'] = array_values(array_filter($entity['tags'], function($tag) use ($tags_to_keep) {
				return in_array($tag['tag'], $tags_to_keep, true);
			}));
		}
		unset($entity);
	}
}
