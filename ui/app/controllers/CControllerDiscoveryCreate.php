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


class CControllerDiscoveryCreate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'name' =>					'required|db drules.name|not_empty',
			'discovery_by' =>			'int32|in '.implode(',', [ZBX_DISCOVERY_BY_SERVER, ZBX_DISCOVERY_BY_PROXY]),
			'proxyid' =>				'db drules.proxyid',
			'iprange' =>				'required|db drules.iprange|not_empty|flags '.P_CRLF,
			'delay' =>					'required|db drules.delay|not_empty',
			'status' =>					'db drules.status|in '.implode(',', [DRULE_STATUS_ACTIVE, DRULE_STATUS_DISABLED]),
			'concurrency_max_type' =>	'in '.implode(',', [ZBX_DISCOVERY_CHECKS_ONE, ZBX_DISCOVERY_CHECKS_UNLIMITED, ZBX_DISCOVERY_CHECKS_CUSTOM]),
			'concurrency_max' =>		'db drules.concurrency_max|ge '.ZBX_DISCOVERY_CHECKS_UNLIMITED.'|le '.ZBX_DISCOVERY_CHECKS_MAX,
			'uniqueness_criteria' =>	'string',
			'dchecks' =>				'required|array'
		];

		$ret = $this->validateInput($fields);

		if ($ret && $this->getInput('discovery_by', ZBX_DISCOVERY_BY_SERVER) == ZBX_DISCOVERY_BY_PROXY) {
			$fields = [
				'proxyid' =>	'required'
			];

			$validator = new CNewValidator(array_intersect_key($this->getInputAll(), $fields), $fields);

			foreach ($validator->getAllErrors() as $error) {
				info($error);
			}

			$ret = !$validator->isErrorFatal() && !$validator->isError();
		}

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Cannot create discovery rule'),
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				], JSON_THROW_ON_ERROR)])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_DISCOVERY);
	}

	protected function doAction(): void {
		$drule = [];
		$this->getInputs($drule, ['name', 'iprange', 'delay', 'dchecks']);

		if ($this->getInput('discovery_by', ZBX_DISCOVERY_BY_SERVER) == ZBX_DISCOVERY_BY_PROXY) {
			$drule['proxyid'] = $this->getInput('proxyid');
		}

		$uniq = $this->getInput('uniqueness_criteria', 0);

		$drule['status'] = $this->getInput('status', DRULE_STATUS_DISABLED);

		foreach ($drule['dchecks'] as $dcnum => $check) {
			if (substr($check['dcheckid'], 0, 3) === 'new') {
				unset($drule['dchecks'][$dcnum]['dcheckid']);
			}

			$drule['dchecks'][$dcnum]['uniq'] = ($uniq == $dcnum) ? 1 : 0;
		}

		$concurrency_max_type = $this->getInput('concurrency_max_type', ZBX_DISCOVERY_CHECKS_UNLIMITED);

		$drule['concurrency_max'] = $concurrency_max_type == ZBX_DISCOVERY_CHECKS_CUSTOM
			? $this->getInput('concurrency_max', ZBX_DISCOVERY_CHECKS_UNLIMITED)
			: $concurrency_max_type;

		$result = API::DRule()->create($drule);

		$output = [];

		if ($result) {
			$output['success']['title'] = _('Discovery rule created');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot create discovery rule'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
