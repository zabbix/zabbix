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


class CControllerTopTriggersList extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'filter_groupids' =>	'array_db hosts_groups.groupid',
			'filter_hostids' =>		'array_db hosts.hostid',
			'filter_problem' =>		'string',
			'filter_severities' =>	'array',
			'filter_evaltype' =>	'in '.TAG_EVAL_TYPE_AND_OR.','.TAG_EVAL_TYPE_OR,
			'filter_tags' =>		'array',
			'from' =>				'range_time',
			'to' =>					'range_time',
			'filter_set' =>			'in 1',
			'filter_rst' =>			'in 1'
		];

		$ret = $this->validateInput($fields) && $this->validateTimeSelectorPeriod();

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_REPORTS_TOP_TRIGGERS);
	}

	protected function doAction(): void {
		if ($this->hasInput('filter_set')) {
			CProfile::updateArray('web.toptriggers.filter.groupids', $this->getInput('filter_groupids', []),
				PROFILE_TYPE_ID
			);
			CProfile::updateArray('web.toptriggers.filter.hostids', $this->getInput('filter_hostids', []),
				PROFILE_TYPE_ID
			);
			CProfile::update('web.toptriggers.filter.problem', $this->getInput('filter_problem', ''), PROFILE_TYPE_STR);
			CProfile::updateArray('web.toptriggers.filter.severities', $this->getInput('filter_severities', []),
				PROFILE_TYPE_INT
			);
			CProfile::update('web.toptriggers.filter.evaltype',
				$this->getInput('filter_evaltype', TAG_EVAL_TYPE_AND_OR), PROFILE_TYPE_INT
			);

			$filter_tags = ['tags' => [], 'operators' => [], 'values' => []];

			foreach ($this->getInput('filter_tags', []) as $filter_tag) {
				if ($filter_tag['tag'] === '' && $filter_tag['value'] === '') {
					continue;
				}

				$filter_tags['tags'][] = $filter_tag['tag'];
				$filter_tags['operators'][] = $filter_tag['operator'];
				$filter_tags['values'][] = $filter_tag['value'];
			}

			CProfile::updateArray('web.toptriggers.filter.tags.tag', $filter_tags['tags'], PROFILE_TYPE_STR);
			CProfile::updateArray('web.toptriggers.filter.tags.operator', $filter_tags['operators'], PROFILE_TYPE_INT);
			CProfile::updateArray('web.toptriggers.filter.tags.value', $filter_tags['values'], PROFILE_TYPE_STR);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::deleteIdx('web.toptriggers.filter.groupids');
			CProfile::deleteIdx('web.toptriggers.filter.hostids');
			CProfile::deleteIdx('web.toptriggers.filter.problem');
			CProfile::deleteIdx('web.toptriggers.filter.severities');
			CProfile::deleteIdx('web.toptriggers.filter.evaltype');
			CProfile::deleteIdx('web.toptriggers.filter.tags.tag');
			CProfile::deleteIdx('web.toptriggers.filter.tags.operator');
			CProfile::deleteIdx('web.toptriggers.filter.tags.value');
		}

		$timeselector_options = [
			'profileIdx' => 'web.toptriggers.filter',
			'profileIdx2' => 0,
			'from' => null,
			'to' => null
		];

		$this->getInputs($timeselector_options, ['from', 'to']);
		updateTimeSelectorPeriod($timeselector_options);

		$filter = [
			'groups' => [],
			'hosts' => [],
			'problem' => CProfile::get('web.toptriggers.filter.problem', ''),
			'severities' => CProfile::getArray('web.toptriggers.filter.severities', []),
			'evaltype' => CProfile::get('web.toptriggers.filter.evaltype', TAG_EVAL_TYPE_AND_OR),
			'tags' => [],
			'timeline' => getTimeSelectorPeriod($timeselector_options),
			'active_tab' => CProfile::get('web.toptriggers.filter.active', 1)
		];

		foreach (CProfile::getArray('web.toptriggers.filter.tags.tag', []) as $i => $tag) {
			$filter['tags'][] = [
				'tag' => $tag,
				'operator' => CProfile::get('web.toptriggers.filter.tags.operator', null, $i),
				'value' => CProfile::get('web.toptriggers.filter.tags.value', null, $i)
			];
		}

		$groupids = CProfile::getArray('web.toptriggers.filter.groupids', []);

		if ($groupids) {
			$groupids = getSubGroups($groupids, $filter['groups']);
		}

		$hostids = CProfile::getArray('web.toptriggers.filter.hostids', []);

		$filter['hosts'] = $hostids
			? CArrayHelper::renameObjectsKeys(API::Host()->get([
				'output' => ['hostid', 'name'],
				'hostids' => $hostids
			]), ['hostid' => 'id'])
			: [];

		$db_problems = API::Event()->get([
			'countOutput' => true,
			'groupBy' => ['objectid'],
			'groupids' => $groupids ?: null,
			'hostids' => $hostids ?: null,
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'value' => TRIGGER_VALUE_TRUE,
			'time_from' => $filter['timeline']['from_ts'],
			'time_till' => $filter['timeline']['to_ts'],
			'search' => [
				'name' => $filter['problem'] !== '' ? $filter['problem'] : null
			],
			'trigger_severities' => $filter['severities'] ?: null,
			'evaltype' => $filter['evaltype'],
			'tags' => $filter['tags'] ?: null,
			'sortfield' => ['rowscount'],
			'sortorder' => ZBX_SORT_DOWN,
			'limit' => 100
		]);

		$db_triggers = [];

		if ($db_problems) {
			$db_problems = array_column($db_problems, null, 'objectid');

			$db_triggers = API::Trigger()->get([
				'output' => ['description', 'priority'],
				'selectHosts' => ['hostid', 'name', 'status'],
				'expandDescription' => true,
				'triggerids' => array_keys($db_problems),
				'preservekeys' => true
			]);

			foreach ($db_triggers as $triggerid => &$trigger) {
				$trigger['problem_count'] = $db_problems[$triggerid]['rowscount'];
			}
			unset($trigger);

			CArrayHelper::sort($db_triggers, [
				['field' => 'problem_count', 'order' => ZBX_SORT_DOWN],
				['field' => 'priority', 'order' => ZBX_SORT_DOWN],
				'description'
			]);
		}

		$data = [
			'filter' => $filter,
			'triggers' => $db_triggers
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Top 100 triggers'));
		$this->setResponse($response);
	}
}
