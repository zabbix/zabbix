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


class CControllerProxyGroupDelete extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'proxy_groupids' =>	'required|array_db proxy_group.proxy_groupid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Cannot delete proxy groups'),
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

		$db_count = API::ProxyGroup()->get([
			'countOutput' => true,
			'proxy_groupids' => $this->getInput('proxy_groupids')
		]);

		return $db_count == count($this->getInput('proxy_groupids'));
	}

	protected function doAction(): void {
		$proxy_groupids = $this->getInput('proxy_groupids');

		$result = API::ProxyGroup()->delete($proxy_groupids);

		$output = [];

		if ($result) {
			$output['success']['title'] = _n('Proxy group deleted', 'Proxy groups deleted', count($proxy_groupids));

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _n('Cannot delete proxy group', 'Cannot delete proxy groups', count($proxy_groupids)),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];

			$db_proxy_groups = API::ProxyGroup()->get([
				'output' => [],
				'proxy_groupids' => $proxy_groupids,
				'preservekeys' => true
			]);

			$output['keepids'] = array_keys($db_proxy_groups);
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
