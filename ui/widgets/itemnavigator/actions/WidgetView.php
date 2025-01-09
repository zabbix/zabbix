<?php declare(strict_types = 0);
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


namespace Widgets\ItemNavigator\Actions;

use API,
	CArrayHelper,
	CControllerDashboardWidgetView,
	CControllerResponseData,
	CProfile,
	CSeverityHelper;

use Widgets\ItemNavigator\Includes\{
	CWidgetFieldItemGrouping,
	WidgetForm
};

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
				case CWidgetFieldItemGrouping::GROUP_BY_HOST_GROUP:
					$group_by_host_groups = true;
					break;
				case CWidgetFieldItemGrouping::GROUP_BY_HOST_NAME:
					$group_by_host_name = true;
					break;
				case CWidgetFieldItemGrouping::GROUP_BY_HOST_TAG:
					$host_tags_to_keep[] = $group_by_attribute['tag_name'];
					break;
				case CWidgetFieldItemGrouping::GROUP_BY_ITEM_TAG:
					$item_tags_to_keep[] = $group_by_attribute['tag_name'];
					break;
			}
		}

		if ($override_hostid === '') {
			$groupids = $this->fields_values['groupids'] ? getSubGroups($this->fields_values['groupids']) : null;

			$db_hosts = API::Host()->get([
				'output' => $group_by_host_name ? ['hostid', 'name'] : [],
				'groupids' => $groupids,
				'hostids' => $this->fields_values['hostids'] ?: null,
				'evaltype' => $this->fields_values['host_tags_evaltype'],
				'tags' => $this->fields_values['host_tags'] ?: null,
				'with_items' => true,
				'selectHostGroups' => $group_by_host_groups ? ['groupid', 'name'] : null,
				'selectTags' => $host_tags_to_keep ? ['tag', 'value'] : null,
				'preservekeys' => true,
				'sortfield' => 'hostid'
			]);
		}
		else {
			$db_hosts = API::Host()->get([
				'output' => $group_by_host_name ? ['hostid', 'name'] : [],
				'hostids' => [$override_hostid],
				'with_items' => true,
				'selectHostGroups' => $group_by_host_groups ? ['groupid', 'name'] : null,
				'selectTags' => $host_tags_to_keep ? ['tag', 'value'] : null,
				'preservekeys' => true
			]);
		}

		if (!$db_hosts) {
			return $no_data;
		}

		$search_field = $this->isTemplateDashboard() ? 'name' : 'name_resolved';

		$options = [
			'output' => ['itemid', 'hostid', 'name_resolved', 'key_'],
			'webitems' => true,
			'evaltype' => $this->fields_values['item_tags_evaltype'],
			'tags' => $this->fields_values['item_tags'] ?: null,
			'filter' => [
				'state' => $this->fields_values['state'] == WidgetForm::STATE_ALL ? null : $this->fields_values['state']
			],
			'searchWildcardsEnabled' => true,
			'searchByAny' => true,
			'search' => [
				$search_field => in_array('*', $this->fields_values['items'], true)
					? null
					: $this->fields_values['items']
			],
			'selectTags' => $item_tags_to_keep ? ['tag', 'value'] : null,
			'preservekeys' => true,
			'sortfield' => 'name',
			'sortorder' => ZBX_SORT_UP
		];

		$limit_extended = $this->fields_values['show_lines'] + 1;
		$selected_items_cnt = 0;
		$items = [];
		$hosts = [];

		foreach ($db_hosts as $hostid => $host) {
			if ($selected_items_cnt == $limit_extended) {
				break;
			}

			if ($host_tags_to_keep) {
				self::filterTags($host, $host_tags_to_keep);
			}

			if ($group_by_host_groups && $override_hostid === '' && !$this->isTemplateDashboard()
					&& $groupids !== null) {
				$host['hostgroups'] = array_values(array_filter($host['hostgroups'], function($group) use ($groupids) {
					return in_array($group['groupid'], $groupids);
				}));
			}

			$hosts[$hostid] = $host;

			$options['limit'] = $limit_extended - $selected_items_cnt;
			$options['hostids'] = [$hostid];

			$items += API::Item()->get($options);

			$selected_items_cnt = count($items);
		}

		if (!$items) {
			return $no_data;
		}

		$items = CArrayHelper::renameObjectsKeys($items, ['name_resolved' => 'name']);

		CArrayHelper::sort($items, ['name']);

		$is_limit_exceeded = false;

		if (count($items) > $this->fields_values['show_lines']) {
			$is_limit_exceeded = true;

			array_pop($items);
		}

		if ($this->fields_values['problems'] != WidgetForm::PROBLEMS_NONE || $item_tags_to_keep) {
			if ($this->fields_values['problems'] != WidgetForm::PROBLEMS_NONE) {
				$triggers = API::Trigger()->get([
					'output' => [],
					'selectItems' => ['itemid'],
					'itemids' => array_keys($items),
					'skipDependent' => true,
					'monitored' => true,
					'preservekeys' => true
				]);

				$problems = API::Problem()->get([
					'output' => ['eventid', 'objectid', 'severity'],
					'source' => EVENT_SOURCE_TRIGGERS,
					'object' => EVENT_OBJECT_TRIGGER,
					'objectids' => array_keys($triggers),
					'suppressed' => $this->fields_values['problems'] == WidgetForm::PROBLEMS_UNSUPPRESSED
						? false
						: null,
					'symptom' => false
				]);

				$item_problems = [];

				foreach ($problems as $problem) {
					foreach ($triggers[$problem['objectid']]['items'] as $trigger_item) {
						$item_problems[$trigger_item['itemid']][$problem['severity']][$problem['eventid']] = true;
					}
				}
			}

			foreach ($items as $key => &$item) {
				if ($this->fields_values['problems'] != WidgetForm::PROBLEMS_NONE) {
					$item['problem_count'] = array_fill(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT, 0);

					// Count the number of problems (as value) per severity (as key).
					if ($item_problems && array_key_exists($item['itemid'], $item_problems)) {
						foreach ($item_problems[$key] as $severity => $problems) {
							$item['problem_count'][$severity] = count($problems);
						}
					}
				}

				if ($item_tags_to_keep) {
					self::filterTags($item, $item_tags_to_keep);
				}
			}
			unset($item);
		}

		return [
			'hosts' => $hosts,
			'items' => array_values($items),
			'is_limit_exceeded' => $is_limit_exceeded
		];
	}

	private function getConfig(string $widgetid = null): array {
		$open_groups = [];

		if ($widgetid !== null) {
			$open_groupids = CProfile::findByIdxPattern('web.dashboard.widget.open.%', $widgetid);

			foreach ($open_groupids as $open_groupid) {
				$open_group = CProfile::get($open_groupid, null, $widgetid);

				if ($open_group !== null) {
					$open_groups[] = $open_group;
				}
			}
		}

		$severities = [];

		if ($this->fields_values['problems'] != WidgetForm::PROBLEMS_NONE) {
			$severities = CSeverityHelper::getSeverities();

			foreach ($severities as &$severity) {
				$severity['status_style'] = CSeverityHelper::getStatusStyle($severity['value']);
			}
			unset($severity);
		}

		return [
			'group_by' => $this->fields_values['group_by'],
			'open_groups' => $open_groups,
			'show_problems' => $this->fields_values['problems'] != WidgetForm::PROBLEMS_NONE,
			'severities' => $severities
		];
	}

	/**
	 * Filters tags of a given entity (host or item) based on the specified tags to keep.
	 *
	 * @param array $entity        The entity (host or item) to filter.
	 * @param array $tags_to_keep  The tags to retain in the entity.
	 */
	private static function filterTags(array &$entity, array $tags_to_keep): void {
		$entity['tags'] = array_values(array_filter($entity['tags'], function($tag) use ($tags_to_keep) {
			return in_array($tag['tag'], $tags_to_keep, true);
		}));
	}
}
