<?php declare(strict_types = 0);
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


require_once __DIR__ .'/../../include/forms.inc.php';

class CControllerPopupServiceEdit extends CController {

	/**
	 * @var array
	 */
	private $service;

	protected function checkInput(): bool {
		$fields = [
			'serviceid' =>			'id',
			'parent_serviceids' =>	'array_id'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode(['errors' => getMessages()->toString()])
				]))->disableView()
			);
		}

		return $ret;
	}

	/**
	 * @throws APIException
	 */
	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_SERVICES_SERVICES)) {
			return false;
		}

		if ($this->hasInput('serviceid')) {
			$this->service = API::Service()->get([
				'output' => ['serviceid', 'name', 'algorithm', 'sortorder', 'weight', 'propagation_rule',
					'propagation_value', 'description', 'created_at'
				],
				'selectParents' => ['serviceid', 'name'],
				'selectChildren' => ['serviceid', 'name', 'algorithm'],
				'selectTags' => ['tag', 'value'],
				'selectProblemTags' => ['tag', 'operator', 'value'],
				'selectStatusRules' => ['type', 'limit_value', 'limit_status', 'new_status'],
				'serviceids' => $this->getInput('serviceid')
			]);

			if (!$this->service) {
				return false;
			}

			$this->service = $this->service[0];
		}

		return true;
	}

	/**
	 * @throws APIException
	 */
	protected function doAction(): void {
		if ($this->service !== null) {
			CArrayHelper::sort($this->service['parents'], ['name']);
			$this->service['parents'] = array_values($this->service['parents']);

			CArrayHelper::sort($this->service['children'], ['name']);
			$this->service['children'] = array_values($this->service['children']);

			CArrayHelper::sort($this->service['tags'], ['tag', 'value']);
			$this->service['tags'] = array_values($this->service['tags']);

			CArrayHelper::sort($this->service['problem_tags'], ['tag', 'value', 'operator']);
			$this->service['problem_tags'] = array_values($this->service['problem_tags']);

			CArrayHelper::sort($this->service['status_rules'], ['new_status', 'type', 'limit_value', 'limit_status']);
			$this->service['status_rules'] = array_values($this->service['status_rules']);
		}

		if ($this->service !== null) {
			$parents = $this->service['parents'];
		}
		elseif ($this->hasInput('parent_serviceids')) {
			$parents = API::Service()->get([
				'output' => ['serviceid', 'name'],
				'serviceids' => $this->getInput('parent_serviceids')
			]);
		}
		else {
			$parents = [];
		}

		$children_problem_tags_html = [];

		if ($this->service !== null) {
			$children_serviceids = array_column($this->service['children'], 'serviceid');

			$children = API::Service()->get([
				'output' => [],
				'selectProblemTags' => ['tag', 'value'],
				'serviceids' => $children_serviceids,
				'preservekeys' => true
			]);

			$problem_tags = [];

			foreach ($children as $serviceid => $service) {
				$problem_tags[] = [
					'serviceid' => $serviceid,
					'tags' => $service['problem_tags']
				];
			}

			foreach (makeTags($problem_tags, true, 'serviceid') as $serviceid => $tags) {
				$children_problem_tags_html[$serviceid] = implode('', $tags);
			}
		}

		$defaults = DB::getDefaults('services');

		if ($this->service !== null) {
			foreach ($this->service['status_rules'] as $index => &$status_rule) {
				$status_rule += [
					'row_index' => $index,
					'name' => CServiceHelper::formatStatusRuleType((int) $status_rule['type'],
						(int) $status_rule['new_status'], (int) $status_rule['limit_value'],
						(int) $status_rule['limit_status']
					)
				];
			}
			unset($status_rule);

			$data = [
				'serviceid' => $this->service['serviceid'],
				'form' => [
					'name' => $this->service['name'],
					'parents' => $parents,
					'children' => $this->service['children'],
					'children_problem_tags_html' => $children_problem_tags_html,
					'sortorder' => $this->service['sortorder'],
					'algorithm' => $this->service['algorithm'],
					'description' => $this->service['description'],
					'created_at' => $this->service['created_at'],
					'tags' => $this->service['tags'] ?: [['tag' => '', 'value' => '']],
					'problem_tags' => $this->service['problem_tags']
						?: [['tag' => '', 'operator' => ZBX_SERVICE_PROBLEM_TAG_OPERATOR_EQUAL, 'value' => '']],
					'advanced_configuration' => $this->service['status_rules']
						|| $this->service['propagation_rule'] != $defaults['propagation_rule']
						|| $this->service['propagation_value'] != $defaults['propagation_value']
						|| $this->service['weight'] != $defaults['weight'],
					'status_rules' => $this->service['status_rules'],
					'propagation_rule' => $this->service['propagation_rule'],
					'propagation_value_number' => (
							$this->service['propagation_rule'] == ZBX_SERVICE_STATUS_PROPAGATION_INCREASE
							|| $this->service['propagation_rule'] == ZBX_SERVICE_STATUS_PROPAGATION_DECREASE)
						? $this->service['propagation_value']
						: 1,
					'propagation_value_status' =>
							$this->service['propagation_rule'] == ZBX_SERVICE_STATUS_PROPAGATION_FIXED
						? $this->service['propagation_value']
						: ZBX_SEVERITY_OK,
					'weight' => $this->service['weight']
				]
			];
		}
		else {
			$data = [
				'serviceid' => null,
				'form' => [
					'name' => $defaults['name'],
					'parents' => $parents,
					'children' => [],
					'children_problem_tags_html' => $children_problem_tags_html,
					'sortorder' => $defaults['sortorder'],
					'algorithm' => ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ONE,
					'description' => $defaults['description'],
					'tags' => [['tag' => '', 'value' => '']],
					'problem_tags' => [
						['tag' => '', 'operator' => ZBX_SERVICE_PROBLEM_TAG_OPERATOR_EQUAL, 'value' => '']
					],
					'advanced_configuration' => false,
					'status_rules' => [],
					'propagation_rule' => $defaults['propagation_rule'],
					'propagation_value_number' => 1,
					'propagation_value_status' => ZBX_SEVERITY_OK,
					'weight' => $defaults['weight']
				]
			];
		}

		$data['user'] = ['debug_mode' => $this->getDebugMode()];

		$this->setResponse(new CControllerResponseData($data));
	}
}
