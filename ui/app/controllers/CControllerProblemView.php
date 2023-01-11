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
 * Controller for the "Problems" page and Problems CSV export.
 */
class CControllerProblemView extends CControllerProblem {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'action' =>					'string',
			'sort' =>					'in clock,host,severity,name',
			'sortorder' =>				'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'uncheck' =>				'in 1',
			'page' =>					'ge 1',
			'filter_set' =>				'in 1',
			'filter_rst' =>				'in 1',
			'filter_show' =>			'in '.TRIGGERS_OPTION_RECENT_PROBLEM.','.TRIGGERS_OPTION_IN_PROBLEM.','.TRIGGERS_OPTION_ALL,
			'filter_groupids' =>		'array_id',
			'filter_hostids' =>			'array_id',
			'filter_application' =>		'string',
			'filter_triggerids' =>		'array_id',
			'filter_name' =>			'string',
			'filter_severities' =>		'array',
			'filter_age_state' =>		'in 1',
			'filter_age' =>				'int32',
			'filter_inventory' =>		'array',
			'filter_evaltype' =>		'in '.TAG_EVAL_TYPE_AND_OR.','.TAG_EVAL_TYPE_OR,
			'filter_tags' =>			'array',
			'filter_show_tags' =>		'in '.PROBLEMS_SHOW_TAGS_NONE.','.PROBLEMS_SHOW_TAGS_1.','.PROBLEMS_SHOW_TAGS_2.','.PROBLEMS_SHOW_TAGS_3,
			'filter_show_suppressed' =>	'in 1',
			'filter_unacknowledged' =>	'in 1',
			'filter_compact_view' =>	'in 1',
			'filter_show_timeline' =>	'in 1',
			'filter_details' =>			'in 1',
			'filter_highlight_row' =>	'in 1',
			'filter_show_opdata' =>		'in '.OPERATIONAL_DATA_SHOW_NONE.','.OPERATIONAL_DATA_SHOW_SEPARATELY.','.OPERATIONAL_DATA_SHOW_WITH_PROBLEM,
			'filter_tag_name_format' =>	'in '.PROBLEMS_TAG_NAME_FULL.','.PROBLEMS_TAG_NAME_SHORTENED.','.PROBLEMS_TAG_NAME_NONE,
			'filter_tag_priority' =>	'string',
			'from' =>					'range_time',
			'to' =>						'range_time'
		];

		$ret = $this->validateInput($fields) && $this->validateTimeSelectorPeriod() && $this->validateInventory()
			&& $this->validateTags();

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$sortField = $this->getInput('sort', CProfile::get('web.problem.sort', 'clock'));
		$sortOrder = $this->getInput('sortorder', CProfile::get('web.problem.sortorder', ZBX_SORT_DOWN));
		$active_tab = CProfile::get('web.problem.filter.active', 1);

		CProfile::update('web.problem.sort', $sortField, PROFILE_TYPE_STR);
		CProfile::update('web.problem.sortorder', $sortOrder, PROFILE_TYPE_STR);

		// filter
		if ($this->hasInput('filter_set')) {
			$show_db_type = CProfile::get('web.problem.filter.show', TRIGGERS_OPTION_RECENT_PROBLEM);
			$show_type = $this->getInput('filter_show', TRIGGERS_OPTION_RECENT_PROBLEM);

			if ($show_db_type != $show_type) {
				CProfile::update('web.problem.filter.show', $show_type, PROFILE_TYPE_INT);

				if ($show_type == TRIGGERS_OPTION_ALL && $active_tab == 1) {
					$active_tab = 2;
					CProfile::update('web.problem.filter.active', $active_tab, PROFILE_TYPE_INT);
				}
			}

			CProfile::updateArray('web.problem.filter.groupids', $this->getInput('filter_groupids', []),
				PROFILE_TYPE_ID
			);
			CProfile::updateArray('web.problem.filter.hostids', $this->getInput('filter_hostids', []), PROFILE_TYPE_ID);
			CProfile::update('web.problem.filter.application', $this->getInput('filter_application', ''),
				PROFILE_TYPE_STR
			);
			CProfile::updateArray('web.problem.filter.triggerids', $this->getInput('filter_triggerids', []),
				PROFILE_TYPE_ID
			);
			CProfile::update('web.problem.filter.name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
			CProfile::updateArray('web.problem.filter.severities', $this->getInput('filter_severities', []),
				PROFILE_TYPE_INT
			);
			CProfile::update('web.problem.filter.age_state', $this->getInput('filter_age_state', 0), PROFILE_TYPE_INT);
			CProfile::update('web.problem.filter.age', $this->getInput('filter_age', 14), PROFILE_TYPE_INT);

			$filter_inventory = ['fields' => [], 'values' => []];
			foreach ($this->getInput('filter_inventory', []) as $field) {
				if ($field['value'] === '') {
					continue;
				}

				$filter_inventory['fields'][] = $field['field'];
				$filter_inventory['values'][] = $field['value'];
			}
			CProfile::updateArray('web.problem.filter.inventory.field', $filter_inventory['fields'], PROFILE_TYPE_STR);
			CProfile::updateArray('web.problem.filter.inventory.value', $filter_inventory['values'], PROFILE_TYPE_STR);

			CProfile::update('web.problem.filter.evaltype', $this->getInput('filter_evaltype', TAG_EVAL_TYPE_AND_OR),
				PROFILE_TYPE_INT
			);

			$filter_tags = ['tags' => [], 'values' => [], 'operators' => []];
			foreach ($this->getInput('filter_tags', []) as $filter_tag) {
				if ($filter_tag['tag'] === '' && $filter_tag['value'] === '') {
					continue;
				}

				$filter_tags['tags'][] = $filter_tag['tag'];
				$filter_tags['values'][] = $filter_tag['value'];
				$filter_tags['operators'][] = $filter_tag['operator'];
			}
			CProfile::updateArray('web.problem.filter.tags.tag', $filter_tags['tags'], PROFILE_TYPE_STR);
			CProfile::updateArray('web.problem.filter.tags.value', $filter_tags['values'], PROFILE_TYPE_STR);
			CProfile::updateArray('web.problem.filter.tags.operator', $filter_tags['operators'], PROFILE_TYPE_INT);

			CProfile::update('web.problem.filter.show_tags', $this->getInput('filter_show_tags', PROBLEMS_SHOW_TAGS_3),
				PROFILE_TYPE_INT
			);
			CProfile::update('web.problem.filter.tag_name_format', $this->getInput('filter_tag_name_format',
				PROBLEMS_TAG_NAME_FULL), PROFILE_TYPE_INT
			);
			CProfile::update('web.problem.filter.tag_priority', $this->getInput('filter_tag_priority', ''),
				PROFILE_TYPE_STR
			);
			CProfile::update('web.problem.filter.show_suppressed',
				$this->getInput('filter_show_suppressed', ZBX_PROBLEM_SUPPRESSED_FALSE), PROFILE_TYPE_INT
			);
			CProfile::update('web.problem.filter.unacknowledged', $this->getInput('filter_unacknowledged', 0),
				PROFILE_TYPE_INT
			);
			CProfile::update('web.problem.filter.compact_view', $this->getInput('filter_compact_view', 0),
				PROFILE_TYPE_INT
			);
			CProfile::update('web.problem.filter.show_timeline', $this->getInput('filter_show_timeline', 0),
				PROFILE_TYPE_INT
			);
			CProfile::update('web.problem.filter.details', $this->getInput('filter_details', 0), PROFILE_TYPE_INT);
			CProfile::update('web.problem.filter.highlight_row', $this->getInput('filter_highlight_row', 0),
				PROFILE_TYPE_INT
			);
			CProfile::update('web.problem.filter.show_opdata', $this->getInput('filter_show_opdata', 0),
				PROFILE_TYPE_INT
			);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.problem.filter.show');
			CProfile::deleteIdx('web.problem.filter.groupids');
			CProfile::deleteIdx('web.problem.filter.hostids');
			CProfile::delete('web.problem.filter.application');
			CProfile::deleteIdx('web.problem.filter.triggerids');
			CProfile::delete('web.problem.filter.name');
			CProfile::deleteIdx('web.problem.filter.severities');
			CProfile::delete('web.problem.filter.age_state');
			CProfile::delete('web.problem.filter.age');
			CProfile::deleteIdx('web.problem.filter.inventory.field');
			CProfile::deleteIdx('web.problem.filter.inventory.value');
			CProfile::delete('web.problem.filter.evaltype');
			CProfile::deleteIdx('web.problem.filter.tags.tag');
			CProfile::deleteIdx('web.problem.filter.tags.value');
			CProfile::deleteIdx('web.problem.filter.tags.operator');
			CProfile::delete('web.problem.filter.show_tags');
			CProfile::delete('web.problem.filter.tag_name_format');
			CProfile::delete('web.problem.filter.tag_priority');
			CProfile::delete('web.problem.filter.show_suppressed');
			CProfile::delete('web.problem.filter.unacknowledged');
			CProfile::delete('web.problem.filter.compact_view');
			CProfile::delete('web.problem.filter.show_timeline');
			CProfile::delete('web.problem.filter.details');
			CProfile::delete('web.problem.filter.highlight_row');
			CProfile::delete('web.problem.filter.show_opdata');
		}

		$filter_groupids = CProfile::getArray('web.problem.filter.groupids', []);
		$filter_hostids = CProfile::getArray('web.problem.filter.hostids', []);
		$filter_triggerids = CProfile::getArray('web.problem.filter.triggerids', []);

		$groups = [];

		if ($filter_groupids) {
			$filter_groupids = getSubGroups($filter_groupids, $groups);
		}

		$filter_triggers = $filter_triggerids
			? CArrayHelper::renameObjectsKeys(API::Trigger()->get([
				'output' => ['triggerid', 'description'],
				'selectHosts' => ['name'],
				'expandDescription' => true,
				'triggerids' => $filter_triggerids,
				'monitored' => true
			]), ['triggerid' => 'id', 'description' => 'name'])
			: [];

		CArrayHelper::sort($filter_triggers, [
			['field' => 'name', 'order' => ZBX_SORT_UP]
		]);

		foreach ($filter_triggers as &$filter_trigger) {
			$filter_trigger['prefix'] = $filter_trigger['hosts'][0]['name'].NAME_DELIMITER;
			unset($filter_trigger['hosts']);
		}
		unset($filter_trigger);

		$inventories = [];
		foreach (getHostInventories() as $inventory) {
			$inventories[$inventory['db_field']] = $inventory['title'];
		}

		$filter_inventory = [];
		foreach (CProfile::getArray('web.problem.filter.inventory.field', []) as $i => $field) {
			$filter_inventory[] = [
				'field' => $field,
				'value' => CProfile::get('web.problem.filter.inventory.value', null, $i)
			];
		}

		$filter_tags = [];
		foreach (CProfile::getArray('web.problem.filter.tags.tag', []) as $i => $tag) {
			$filter_tags[] = [
				'tag' => $tag,
				'value' => CProfile::get('web.problem.filter.tags.value', null, $i),
				'operator' => CProfile::get('web.problem.filter.tags.operator', null, $i)
			];
		}

		/*
		 * Display
		 */
		$data = [
			'action' => $this->getInput('action'),
			'sort' => $sortField,
			'sortorder' => $sortOrder,
			'uncheck' => $this->hasInput('uncheck'),
			'page' => $this->getInput('page', 1),
			'filter' => [
				'show' => CProfile::get('web.problem.filter.show', TRIGGERS_OPTION_RECENT_PROBLEM),
				'groupids' => $filter_groupids,
				'groups' => $groups,
				'hostids' => $filter_hostids,
				'hosts' => $filter_hostids
					? CArrayHelper::renameObjectsKeys(API::Host()->get([
						'output' => ['hostid', 'name'],
						'hostids' => $filter_hostids
					]), ['hostid' => 'id'])
					: [],
				'application' => CProfile::get('web.problem.filter.application', ''),
				'triggerids' => $filter_triggerids,
				'triggers' => $filter_triggers,
				'name' => CProfile::get('web.problem.filter.name', ''),
				'severities' => CProfile::getArray('web.problem.filter.severities', []),
				'age_state' => CProfile::get('web.problem.filter.age_state', 0),
				'age' => CProfile::get('web.problem.filter.age', 14),
				'inventories' => $inventories,
				'inventory' => $filter_inventory,
				'evaltype' => CProfile::get('web.problem.filter.evaltype', TAG_EVAL_TYPE_AND_OR),
				'tags' => $filter_tags,
				'show_tags' => CProfile::get('web.problem.filter.show_tags', PROBLEMS_SHOW_TAGS_3),
				'tag_name_format' => CProfile::get('web.problem.filter.tag_name_format', PROBLEMS_TAG_NAME_FULL),
				'tag_priority' => CProfile::get('web.problem.filter.tag_priority', ''),
				'show_suppressed' => CProfile::get('web.problem.filter.show_suppressed', 0),
				'unacknowledged' => CProfile::get('web.problem.filter.unacknowledged', 0),
				'compact_view' => CProfile::get('web.problem.filter.compact_view', 0),
				'show_timeline' => CProfile::get('web.problem.filter.show_timeline', 1),
				'details' => CProfile::get('web.problem.filter.details', 0),
				'highlight_row' => CProfile::get('web.problem.filter.highlight_row', 0),
				'show_opdata' => CProfile::get('web.problem.filter.show_opdata', 0)
			],
			'active_tab' => $active_tab
		];

		if ($data['filter']['show'] == TRIGGERS_OPTION_ALL) {
			$timeselector_options = [
				'profileIdx' => 'web.problem.filter',
				'profileIdx2' => 0,
				'from' => $this->hasInput('from') ? $this->getInput('from') : null,
				'to' => $this->hasInput('to') ? $this->getInput('to') : null
			];
			updateTimeSelectorPeriod($timeselector_options);

			$data['timeline'] = getTimeSelectorPeriod($timeselector_options);

			$data += $timeselector_options;
		}
		else {
			$data['profileIdx'] = 'web.problem.filter';
		}

		// Prepare URL for asynchronous refresh requests.
		$refresh_curl = (new CUrl('zabbix.php'))->setArgument('action', 'problem.view.refresh');

		$refresh_url_arguments = [
			'show' => 'filter_show',
			'groupids' => 'filter_groupids',
			'hostids' => 'filter_hostids',
			'application' => 'filter_application',
			'triggerids' => 'filter_triggerids',
			'name' => 'filter_name',
			'severities' => 'filter_severities',
			'inventory' => 'filter_inventory',
			'evaltype' => 'filter_evaltype',
			'tags' => 'filter_tags',
			'show_tags' => 'filter_show_tags',
			'show_suppressed' => 'filter_show_suppressed',
			'unacknowledged' => 'filter_unacknowledged',
			'compact_view' => 'filter_compact_view',
			'show_timeline' => 'filter_show_timeline',
			'details' => 'filter_details',
			'highlight_row' => 'filter_highlight_row',
			'show_opdata' => 'filter_show_opdata',
			'tag_name_format' => 'filter_tag_name_format',
			'tag_priority' => 'filter_tag_priority'
		];
		foreach (array_filter(array_intersect_key($data['filter'], $refresh_url_arguments)) as $key => $value) {
			$refresh_curl->setArgument($refresh_url_arguments[$key], $value);
		}

		if ($data['filter']['show_tags'] == PROBLEMS_SHOW_TAGS_NONE) {
			$refresh_curl->setArgument('filter_show_tags', $data['filter']['show_tags']);
		}

		if ($data['filter']['age_state'] == 1) {
			$refresh_curl
				->setArgument('filter_age_state', $data['filter']['age_state'])
				->setArgument('filter_age', $data['filter']['age']);
		}

		$refresh_curl
			->setArgument('sort', $sortField)
			->setArgument('sortorder', $sortOrder)
			->setArgument('from', $this->hasInput('from') ? $this->getInput('from') : null)
			->setArgument('to', $this->hasInput('to') ? $this->getInput('to') : null)
			->setArgument('page', $this->hasInput('page') ? $this->getInput('page') : null);

		$data += [
			'refresh_url' => $refresh_curl->getUrl(),
			'refresh_interval' => CWebUser::getRefresh() * 1000
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Problems'));
		if ($data['action'] === 'problem.view.csv') {
			$response->setFileName('zbx_problems_export.csv');
		}

		$this->setResponse($response);
	}
}
