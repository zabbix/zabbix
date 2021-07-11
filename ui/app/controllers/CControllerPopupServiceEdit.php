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


require_once dirname(__FILE__).'/../../include/forms.inc.php';

class CControllerPopupServiceEdit extends CController {

	private $service;

	protected function checkInput(): bool {
		$fields = [
			'serviceid' =>			'db services.serviceid',
			'parent_serviceids' =>	'array_db services.serviceid',
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

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_MONITORING_SERVICES)
				|| !$this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SERVICES)) {
			return false;
		}

		if ($this->hasInput('serviceid')) {
			$this->service = API::Service()->get([
				'output' => ['serviceid', 'name', 'algorithm', 'showsla', 'goodsla', 'sortorder'],
				'selectParents' => ['serviceid', 'name'],
				'selectChildren' => ['serviceid', 'name', 'algorithm'],
				'selectTags' => ['tag', 'value'],
				'selectProblemTags' => ['tag', 'operator', 'value'],
				'selectTimes' => ['type', 'ts_from', 'ts_to', 'note'],
				'serviceids' => $this->getInput('serviceid')
			]);

			if (!$this->service) {
				return false;
			}

			$this->service = $this->service[0];
		}

		return true;
	}

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

			CArrayHelper::sort($this->service['times'], ['type', 'ts_from', 'ts_to']);
			$this->service['times'] = array_values($this->service['times']);
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

			foreach ($children_serviceids as $serviceid) {
				$children_problem_tags_html[$serviceid] = array_key_exists($serviceid, $children)
					? CServiceHelper::makeProblemTags($children[$serviceid]['problem_tags'])->toString()
					: '';
			}
		}

		$defaults = DB::getDefaults('services');

		$data = [
			'title' => _('Service'),
			'serviceid' => $this->service !== null ? $this->service['serviceid'] : null,
			'form_action' => $this->service !== null ? 'popup.service.update' : 'popup.service.create',
			'form' => [
				'name' => $this->service !== null ? $this->service['name'] : $defaults['name'],
				'parents' => $parents,
				'children' => $this->service !== null ? $this->service['children'] : [],
				'children_problem_tags_html' => $children_problem_tags_html,
				'algorithm' => $this->service !== null ? $this->service['algorithm'] : SERVICE_ALGORITHM_MAX,
				'sortorder' => $this->service !== null ? $this->service['sortorder'] : $defaults['sortorder'],
				'showsla' => $this->service !== null ? $this->service['showsla'] : $defaults['showsla'],
				'goodsla' => $this->service !== null ? $this->service['goodsla'] : $defaults['goodsla'],
				'times' => $this->service !== null ? $this->service['times'] : [],
				'tags' => ($this->service !== null && $this->service['tags'])
					? $this->service['tags']
					: [['tag' => '', 'value' => '']],
				'problem_tags' => ($this->service !== null && $this->service['problem_tags'])
					? $this->service['problem_tags']
					: [['tag' => '', 'value' => '', 'operator' => TAG_OPERATOR_EQUAL]]
			],
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}
