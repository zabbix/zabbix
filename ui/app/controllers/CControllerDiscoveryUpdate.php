<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


class CControllerDiscoveryUpdate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'druleid' =>				'required|db drules.druleid',
			'name' =>					'required|db drules.name|not_empty',
			'proxyid' =>				'db drules.proxyid',
			'iprange' =>				'required|db drules.iprange|not_empty|flags '.P_CRLF,
			'delay' =>					'required|db drules.delay|not_empty',
			'status' =>					'db drules.status|in '.DRULE_STATUS_ACTIVE,
			'concurrency_max_type' =>	'in '.implode(',', [ZBX_DISCOVERY_CHECKS_ONE, ZBX_DISCOVERY_CHECKS_UNLIMITED, ZBX_DISCOVERY_CHECKS_CUSTOM]),
			'concurrency_max' =>		'db drules.concurrency_max|ge '.ZBX_DISCOVERY_CHECKS_UNLIMITED.'|le '.ZBX_DISCOVERY_CHECKS_MAX,
			'uniqueness_criteria' =>	'string',
			'dchecks' =>				'required|array'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Cannot update discovery rule'),
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
		$this->getInputs($drule, ['druleid', 'name', 'proxyid', 'iprange', 'delay', 'dchecks']);
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

		$result = API::DRule()->update($drule);

		$output = [];

		if ($result) {
			$output['success']['title'] = _('Discovery rule updated');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot update discovery rule'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
