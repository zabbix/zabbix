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


class CControllerServiceStatusRuleValidate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'row_index' =>		'required|int32',
			'type' =>			'required|in '.implode(',', array_keys(CServiceHelper::getStatusRuleTypeOptions())),
			'limit_value' =>	'required|int32|ge 1',
			'limit_status' =>	'required|in '.implode(',', array_keys(CServiceHelper::getStatusNames())),
			'new_status' =>		'required|in '.implode(',', array_keys(CServiceHelper::getProblemStatusNames()))
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			switch ($this->getInput('type')) {
				case ZBX_SERVICE_STATUS_RULE_TYPE_N_GE:
				case ZBX_SERVICE_STATUS_RULE_TYPE_N_L:
					$limit_value_label = 'N';
					$limit_value_max = 1000000;
					break;

				case ZBX_SERVICE_STATUS_RULE_TYPE_W_GE:
				case ZBX_SERVICE_STATUS_RULE_TYPE_W_L:
					$limit_value_label = 'W';
					$limit_value_max = 1000000;
					break;

				case ZBX_SERVICE_STATUS_RULE_TYPE_NP_GE:
				case ZBX_SERVICE_STATUS_RULE_TYPE_NP_L:
				case ZBX_SERVICE_STATUS_RULE_TYPE_WP_GE:
				case ZBX_SERVICE_STATUS_RULE_TYPE_WP_L:
					$limit_value_label = 'N';
					$limit_value_max = 100;
					break;
			}

			$validator = new CNewValidator([
				$limit_value_label => $this->getInput('limit_value')
			], [
				$limit_value_label => 'le '.$limit_value_max
			]);

			foreach ($validator->getAllErrors() as $error) {
				info($error);
			}

			$ret = !$validator->isErrorFatal() && !$validator->isError();
		}

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_SERVICES_SERVICES);
	}

	protected function doAction(): void {
		$data = [
			'body' => [
				'row_index' => $this->getInput('row_index'),
				'name' => CServiceHelper::formatStatusRuleType((int) $this->getInput('type'),
					(int) $this->getInput('new_status'), (int) $this->getInput('limit_value'),
					(int) $this->getInput('limit_status')
				),
				'type' => $this->getInput('type'),
				'limit_value' => $this->getInput('limit_value'),
				'limit_status' => $this->getInput('limit_status'),
				'new_status' => $this->getInput('new_status')
			]
		];

		if ($this->getDebugMode() == GROUP_DEBUG_MODE_ENABLED) {
			CProfiler::getInstance()->stop();
			$data['debug'] = CProfiler::getInstance()->make()->toString();
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}
}
