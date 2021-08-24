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


class CControllerServiceCreate extends CController {

	protected function checkInput(): bool {
		$fields = [
			'name' =>				'required|db services.name|not_empty',
			'parent_serviceids' =>	'array_db services.serviceid',
			'algorithm' =>			'required|db services.algorithm|in '.implode(',', [SERVICE_ALGORITHM_NONE, SERVICE_ALGORITHM_MAX, SERVICE_ALGORITHM_MIN]),
			'problem_tags' =>		'array',
			'sortorder' =>			'required|db services.sortorder|ge 0|le 999',
			'showsla' =>			'in 1',
			'goodsla' =>			'string',
			'times' =>				'array',
			'tags' =>				'array',
			'child_serviceids' =>	'array_db services.serviceid'
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
		return $this->checkAccess(CRoleHelper::UI_MONITORING_SERVICES)
			&& $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SERVICES);
	}

	protected function doAction(): void {
		$service = [
			'showsla' => $this->hasInput('showsla') ? SERVICE_SHOW_SLA_ON : SERVICE_SHOW_SLA_OFF,
			'tags' => [],
			'problem_tags' => [],
			'parents' => [],
			'children' => [],
			'times' => $this->getInput('times', [])
		];

		$this->getInputs($service, ['name', 'algorithm', 'sortorder', 'goodsla']);

		foreach ($this->getInput('tags', []) as $tag) {
			if ($tag['tag'] === '' && $tag['value'] === '') {
				continue;
			}

			$service['tags'][] = $tag;
		}

		if ($service['algorithm'] != SERVICE_ALGORITHM_NONE) {
			foreach ($this->getInput('problem_tags', []) as $problem_tag) {
				if ($problem_tag['tag'] === '' && $problem_tag['value'] === '') {
					continue;
				}

				$service['problem_tags'][] = $problem_tag;
			}
		}

		foreach ($this->getInput('parent_serviceids', []) as $serviceid) {
			$service['parents'][] = ['serviceid' => $serviceid];
		}

		foreach ($this->getInput('child_serviceids', []) as $serviceid) {
			$service['children'][] = ['serviceid' => $serviceid];
		}

		$result = API::Service()->create($service);

		if ($result) {
			$output = ['title' => _('Service created')];

			if ($messages = CMessageHelper::getMessages()) {
				$output['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output = [
				'errors' => makeMessageBox(ZBX_STYLE_MSG_BAD, filter_messages(), CMessageHelper::getTitle())->toString()
			];
		}

		$this->setResponse((new CControllerResponseData(['main_block' => json_encode($output)]))->disableView());
	}
}
