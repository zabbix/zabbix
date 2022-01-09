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


class CControllerServiceUpdate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'serviceid' =>					'required|id',
			'name' =>						'required|db services.name|not_empty',
			'parent_serviceids' =>			'array_db services.serviceid',
			'problem_tags' =>				'array',
			'sortorder' =>					'required|db services.sortorder|ge 0|le 999',
			'algorithm' =>					'required|db services.algorithm|in '.implode(',', [ZBX_SERVICE_STATUS_CALC_SET_OK, ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ALL, ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ONE]),
			'description' =>				'db services.description',
			'advanced_configuration' =>		'in 1',
			'status_rules' =>				'array',
			'propagation_rule' =>			'in '.implode(',', array_keys(CServiceHelper::getStatusPropagationNames())),
			'propagation_value_number' =>	'int32',
			'propagation_value_status' =>	'int32',
			'weight' =>						'string',
			'tags' =>						'array',
			'child_serviceids' =>			'array_db services.serviceid'
		];

		$ret = $this->validateInput($fields);

		if ($ret && $this->hasInput('advanced_configuration')) {
			$fields = [
				'propagation_rule' => 'required'
			];

			if ($this->getInput('weight', '') !== '') {
				$fields['weight'] = 'int32|ge 0|le 1000000';
			}

			$validator = new CNewValidator(array_intersect_key($this->getInputAll(), $fields), $fields);

			foreach ($validator->getAllErrors() as $error) {
				info($error);
			}

			$ret = !$validator->isErrorFatal() && !$validator->isError();
		}

		if ($ret && $this->hasInput('advanced_configuration')) {
			$fields = [];

			switch ($this->getInput('propagation_rule')) {
				case ZBX_SERVICE_STATUS_PROPAGATION_INCREASE:
				case ZBX_SERVICE_STATUS_PROPAGATION_DECREASE:
					$fields['propagation_value_number'] = 'required|ge 1|le '.(TRIGGER_SEVERITY_COUNT - 1);
					break;

				case ZBX_SERVICE_STATUS_PROPAGATION_FIXED:
					$fields['propagation_value_status'] =
						'required|in '.implode(',', array_keys(CServiceHelper::getStatusNames()));
					break;
			}

			if ($fields) {
				$validator = new CNewValidator(array_intersect_key($this->getInputAll(), $fields), $fields);

				foreach ($validator->getAllErrors() as $error) {
					info($error);
				}

				$ret = !$validator->isErrorFatal() && !$validator->isError();
			}
		}

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

		return (bool) API::Service()->get([
			'output' => [],
			'serviceids' => $this->getInput('serviceid')
		]);
	}

	/**
	 * @throws APIException
	 */
	protected function doAction(): void {
		$service = [
			'tags' => [],
			'problem_tags' => [],
			'parents' => [],
			'children' => [],
			'status_rules' => []
		];

		$fields = ['serviceid', 'name', 'algorithm', 'sortorder', 'description'];

		$this->getInputs($service, $fields);

		foreach ($this->getInput('tags', []) as $tag) {
			if ($tag['tag'] === '' && $tag['value'] === '') {
				continue;
			}

			$service['tags'][] = $tag;
		}

		foreach ($this->getInput('problem_tags', []) as $problem_tag) {
			if ($problem_tag['tag'] === '' && $problem_tag['value'] === '') {
				continue;
			}

			$service['problem_tags'][] = $problem_tag;
		}

		foreach ($this->getInput('parent_serviceids', []) as $serviceid) {
			$service['parents'][] = ['serviceid' => $serviceid];
		}

		foreach ($this->getInput('child_serviceids', []) as $serviceid) {
			$service['children'][] = ['serviceid' => $serviceid];
		}

		if ($this->hasInput('advanced_configuration')) {
			$this->getInputs($service, ['status_rules', 'propagation_rule']);

			switch ($this->getInput('propagation_rule', DB::getDefault('services', 'propagation_rule'))) {
				case ZBX_SERVICE_STATUS_PROPAGATION_INCREASE:
				case ZBX_SERVICE_STATUS_PROPAGATION_DECREASE:
					$service['propagation_value'] = $this->getInput('propagation_value_number', 0);
					break;

				case ZBX_SERVICE_STATUS_PROPAGATION_FIXED:
					$service['propagation_value'] = $this->getInput('propagation_value_status', 0);
					break;

				default:
					$service['propagation_value'] = 0;
					break;
			}

			$service['weight'] = $this->getInput('weight', '') !== '' ? $this->getInput('weight') : 0;
		}
		else {
			$service['propagation_rule'] = DB::getDefault('services', 'propagation_rule');
			$service['propagation_value'] = DB::getDefault('services', 'propagation_value');
			$service['weight'] = DB::getDefault('services', 'weight');
		}

		$result = API::Service()->update($service);

		if ($result) {
			$output = ['title' => _('Service updated')];

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
