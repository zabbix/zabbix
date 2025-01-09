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


class CControllerProxyGroupEdit extends CController {

	/**
	 * @var array|null
	 */
	private ?array $proxy_group = null;

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'proxy_groupid' =>	'db proxy_group.proxy_groupid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_ADMINISTRATION_PROXY_GROUPS)) {
			return false;
		}

		if ($this->hasInput('proxy_groupid')) {
			$db_proxy_groups = API::ProxyGroup()->get([
				'output' => ['proxy_groupid', 'name', 'failover_delay', 'min_online', 'description'],
				'selectProxies' => ['proxyid', 'name'],
				'proxy_groupids' => $this->getInput('proxy_groupid')
			]);

			if (!$db_proxy_groups) {
				return false;
			}

			$this->proxy_group = $db_proxy_groups[0];
		}

		return true;
	}

	protected function doAction(): void {
		if ($this->proxy_group !== null) {
			$data = [
				'proxy_groupid' => $this->proxy_group['proxy_groupid'],
				'proxies' => $this->proxy_group['proxies'],
				'proxy_count_total' => count($this->proxy_group['proxies']),
				'form' => [
					'name' => $this->proxy_group['name'],
					'failover_delay' => $this->proxy_group['failover_delay'],
					'min_online' => $this->proxy_group['min_online'],
					'description' => $this->proxy_group['description']
				]
			];

			if ($data['proxies']) {
				CArrayHelper::sort($data['proxies'], ['name']);
				$data['proxies'] = array_slice($data['proxies'], 0, 5);
			}
		}
		else {
			$data = [
				'proxy_groupid' => null,
				'proxies' => [],
				'proxy_count_total' => 0,
				'form' => [
					'name' => DB::getDefault('proxy_group', 'name'),
					'failover_delay' => DB::getDefault('proxy_group', 'failover_delay'),
					'min_online' => DB::getDefault('proxy_group', 'min_online'),
					'description' => DB::getDefault('proxy_group', 'description')
				]
			];
		}

		$data['user'] = [
			'debug_mode' => $this->getDebugMode(),
			'can_edit_proxies' => $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_PROXIES)
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}
