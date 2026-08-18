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


class CControllerCepRuleList extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_CEPRULES);
	}

	protected function checkInput(): bool {
		$fields = [
			'sort' =>			'in name,status,sortorder',
			'sortorder' =>		'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'filter_set' =>		'in 1',
			'filter_rst' =>		'in 1',
			'filter_name' =>	'string',
			'filter_status' =>	'in -1,'.CCepRuleHelper::STATUS_ENABLED.','.CCepRuleHelper::STATUS_DISABLED,
			'filter_type' =>	'in '.CCepRuleHelper::FILTER_SHOW_ALL.','.CCepRuleHelper::FILTER_SHOW_CEP.','.CCepRuleHelper::FILTER_SHOW_LEGACY,
			'page' =>			'ge 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function doAction(): void {
		$sort_field = $this->getInput('sort', CProfile::get('web.ceprule.list.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.ceprule.list.sortorder', ZBX_SORT_UP));
		CProfile::update('web.ceprule.list.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.ceprule.list.sortorder', $sort_order, PROFILE_TYPE_STR);

		if ($this->hasInput('filter_set')) {
			CProfile::update('web.ceprule.filter_name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
			CProfile::update('web.ceprule.filter_status', $this->getInput('filter_status',
				CCepRuleHelper::FILTER_SHOW_ALL), PROFILE_TYPE_INT
			);
			CProfile::update('web.ceprule.filter_type', $this->getInput('filter_type', -1), PROFILE_TYPE_INT);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.ceprule.filter_name');
			CProfile::delete('web.ceprule.filter_status');
			CProfile::delete('web.ceprule.filter_type');
		}

		$filter = [
			'name' => CProfile::get('web.ceprule.filter_name', ''),
			'status' => CProfile::get('web.ceprule.filter_status', -1),
			'type' => CProfile::get('web.ceprule.filter_type', CCepRuleHelper::FILTER_SHOW_ALL)
		];

		$data = [
			'sort' => $sort_field,
			'sortorder' => $sort_order,
			'filter' => $filter,
			'profileIdx' => 'web.ceprule.filter',
			'active_tab' => CProfile::get('web.ceprule.filter.active', 1)
		];

		$data['ceprules'] = self::fetchCepRules($filter);
		$data['group_names'] = self::fetchGroupNames($data['ceprules']);

		if ($sort_field === 'sortorder') {
			CArrayHelper::sort($data['ceprules'], [
				['field' => 'sortorder', 'order' => $sort_order],
				['field' => 'name', 'order' => $sort_order]
			]);
		}
		else {
			CArrayHelper::sort($data['ceprules'], [['field' => $sort_field, 'order' => $sort_order]]);
		}

		$page_num = $this->getInput('page', 1);
		CPagerHelper::savePage('ceprule.list', $page_num);
		$data['paging'] = CPagerHelper::paginate($page_num, $data['ceprules'], $sort_order,
			(new CUrl('zabbix.php'))->setArgument('action', $this->getAction())
		);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of event processing rules'));
		$this->setResponse($response);
	}

	protected static function fetchCepRules(array $filter): array {
		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;

		$result_cep = [];
		$result_legacy = [];

		if ($filter['type'] == CCepRuleHelper::FILTER_SHOW_ALL
				|| $filter['type'] == CCepRuleHelper::FILTER_SHOW_LEGACY) {
			$result_legacy = API::Correlation()->get([
				'output' => ['correlationid', 'name', 'description', 'status'],
				'selectFilter' => ['conditions'],
				'selectOperations' => ['type'],
				'search' => ['name' => $filter['name'] === '' ? null : $filter['name']],
				'filter' => ['status' => $filter['status'] == -1 ? null : $filter['status']],
				'editable' => true,
				'limit' => $limit
			]);

			if ($result_legacy === false) {
				return [];
			}
		}

		if ($filter['type'] == CCepRuleHelper::FILTER_SHOW_ALL || $filter['type'] == CCepRuleHelper::FILTER_SHOW_CEP) {
			$result_cep = API::CepRule()->get([
				'output' => ['cep_ruleid', 'name', 'window_type', 'stop', 'sortorder', 'status', 'error'],
				'selectFilter' => ['conditions'],
				'selectOperations' => ['execute_when', 'type', 'event_name', 'tag', 'new_tag', 'tag_value', 'severity'],
				'search' => ['name' => $filter['name'] === '' ? null : $filter['name']],
				'filter' => ['status' => $filter['status'] == -1 ? null : $filter['status']],
				'limit' => $limit
			]);

			if ($result_cep === false) {
				return [];
			}
		}

		return array_map(function(array $record) {
			if (!array_key_exists('error', $record)) {
				$record['error'] = '';
			}

			if (array_key_exists('filter', $record)) {
				$record['filter'] += [
					'conditions' => []
				];

				$record['filter']['conditions'] = array_map(function(array $condition) {
					if ($condition['type'] == CCepRuleHelper::CONDITION_TAG_VALUE) {
						$condition['type'] = CCepRuleHelper::CONDITION_TAG;
					}

					if ($condition['type'] == CCepRuleHelper::CONDITION_TAG) {
						$condition['tag_operator'] = $condition['operator'];
					}

					return $condition;
				}, $record['filter']['conditions']);
			}

			$record['cepruleid'] = array_key_exists('cep_ruleid', $record) ? $record['cep_ruleid'] : null;

			return $record;
		}, array_merge($result_cep, $result_legacy));
	}

	protected static function fetchGroupNames(array $ceprules): array {
		$groupids = [];

		foreach ($ceprules as $ceprule) {
			$is_legacy = array_key_exists('correlationid', $ceprule);

			if ($is_legacy) {
				$groupids = array_merge($groupids,
					array_column($ceprule['filter']['conditions'], 'groupid', 'groupid')
				);
			}
		}

		if ($groupids) {
			$groups = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => array_unique($groupids)
			]);

			return array_column($groups, 'name', 'groupid');
		}

		return [];
	}
}
