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


class CControllerProxyGroupUpdate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'proxy_groupid' =>	'required|db proxy_group.proxy_groupid',
			'name' =>			'required|not_empty|db proxy_group.name',
			'failover_delay' =>	'required|not_empty|db proxy_group.failover_delay',
			'min_online' =>		'required|not_empty|db proxy_group.min_online',
			'description' =>	'db proxy_group.description'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Cannot update proxy group'),
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_ADMINISTRATION_PROXY_GROUPS)) {
			return false;
		}

		return (bool) API::ProxyGroup()->get([
			'output' => [],
			'proxy_groupids' => $this->getInput('proxy_groupid')
		]);
	}

	protected function doAction(): void {
		$proxy_group = [];

		$this->getInputs($proxy_group, ['proxy_groupid', 'name', 'failover_delay', 'min_online', 'description']);

		$result = API::ProxyGroup()->update($proxy_group);

		$output = [];

		if ($result) {
			$output['success']['title'] = _('Proxy group updated');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot update proxy group'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
