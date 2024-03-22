<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


class CControllerProxyList extends CController {

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'filter_set' =>				'in 1',
			'filter_rst' =>				'in 1',
			'filter_name' =>			'string',
			'filter_operating_mode' =>	'in '.implode(',', [-1, PROXY_OPERATING_MODE_ACTIVE, PROXY_OPERATING_MODE_PASSIVE]),
			'filter_version' =>			'in '.implode(',', [-1, ZBX_PROXY_VERSION_ANY_OUTDATED, ZBX_PROXY_VERSION_CURRENT]),
			'sort' =>					'in '.implode(',', ['name', 'operating_mode', 'tls_accept', 'version', 'lastaccess']),
			'sortorder' =>				'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'page' =>					'ge 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_PROXIES);
	}

	protected function doAction() {
		$sortField = $this->getInput('sort', CProfile::get('web.proxies.php.sort', 'name'));
		$sortOrder = $this->getInput('sortorder', CProfile::get('web.proxies.php.sortorder', ZBX_SORT_UP));

		CProfile::update('web.proxies.php.sort', $sortField, PROFILE_TYPE_STR);
		CProfile::update('web.proxies.php.sortorder', $sortOrder, PROFILE_TYPE_STR);

		if ($this->hasInput('filter_set')) {
			CProfile::update('web.proxies.filter_name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
			CProfile::update('web.proxies.filter_operating_mode',
				$this->getInput('filter_operating_mode', -1), PROFILE_TYPE_INT
			);
			CProfile::update('web.proxies.filter_version', $this->getInput('filter_version', -1), PROFILE_TYPE_INT);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.proxies.filter_name');
			CProfile::delete('web.proxies.filter_operating_mode');
			CProfile::delete('web.proxies.filter_version');
		}

		$filter = [
			'name' => CProfile::get('web.proxies.filter_name', ''),
			'operating_mode' => CProfile::get('web.proxies.filter_operating_mode', -1),
			'version' => CProfile::get('web.proxies.filter_version', -1)
		];

		$data = [
			'sort' => $sortField,
			'sortorder' => $sortOrder,
			'filter' => $filter,
			'active_tab' => CProfile::get('web.proxies.filter.active', 1),
			'user' => [
				'can_edit_hosts' => $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS),
				'can_edit_proxy_groups' => $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_PROXY_GROUPS)
			]
		];

		if ($filter['version'] == ZBX_PROXY_VERSION_ANY_OUTDATED) {
			$filter['version'] = [ZBX_PROXY_VERSION_OUTDATED, ZBX_PROXY_VERSION_UNSUPPORTED];
		}

		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
		$data['proxies'] = API::Proxy()->get([
			'output' => ['proxyid', $sortField],
			'search' => [
				'name' => ($filter['name'] === '') ? null : $filter['name']
			],
			'filter' => [
				'operating_mode' => ($filter['operating_mode'] == -1) ? null : $filter['operating_mode'],
				'compatibility' => ($filter['version'] == -1) ? null : $filter['version']
			],
			'limit' => $limit,
			'editable' => true,
			'preservekeys' => true
		]);

		$data['proxies'] = API::Proxy()->get([
			'output' => ['proxyid', 'name', 'proxy_groupid', 'operating_mode', 'lastaccess', 'tls_connect',
				'tls_accept', 'version', 'compatibility', 'state'
			],
			'selectAssignedHosts' => ['hostid', 'name', 'status'],
			'selectHosts' => ['hostid', 'name', 'status'],
			'selectProxyGroup' => ['name'],
			'proxyids' => array_keys($data['proxies']),
			'editable' => true,
			'preservekeys' => true
		]);
		order_result($data['proxies'], $sortField, $sortOrder);

		$page_num = $this->getInput('page', 1);
		CPagerHelper::savePage('proxy.list', $page_num);
		$data['paging'] = CPagerHelper::paginate($page_num, $data['proxies'], $sortOrder,
			(new CUrl('zabbix.php'))->setArgument('action', $this->getAction())
		);

		foreach ($data['proxies'] as &$proxy) {
			// Convert proxy version to readable format.
			$proxy['version'] = $proxy['version'] != 0
				? (intdiv($proxy['version'], 10000) % 100).'.'.(intdiv($proxy['version'], 100) % 100).'.'.
					($proxy['version'] % 100)
				: '';

			$proxy['hosts'] = array_merge($proxy['hosts'], $proxy['assignedHosts']);
			unset($proxy['assignedHosts']);
			$proxy['host_count_total'] = count($proxy['hosts']);

			if ($proxy['hosts']) {
				CArrayHelper::sort($proxy['hosts'], ['name']);

				$proxy['hosts'] = array_slice($proxy['hosts'], 0, CSettingsHelper::get(CSettingsHelper::MAX_IN_TABLE));
			}
		}
		unset($proxy);

		if ($data['proxies']) {
			global $ZBX_SERVER, $ZBX_SERVER_PORT;

			$server = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT,
				timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::CONNECT_TIMEOUT)),
				timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::SOCKET_TIMEOUT)), ZBX_SOCKET_BYTES_LIMIT
			);
			$server_status = $server->getStatus(CSessionHelper::getId());

			if ($server_status !== false) {
				$defaults = [
					'host_count' => 0,
					'item_count' => 0
				];
				if (array_key_exists('required performance', $server_status)) {
					$defaults['vps_total'] = 0;
				}
				foreach ($data['proxies'] as &$proxy) {
					$proxy += $defaults;
				}
				unset($proxy);

				// hosts
				foreach ($server_status['host stats'] as $stats) {
					if ($stats['attributes']['status'] == HOST_STATUS_MONITORED) {
						if (array_key_exists($stats['attributes']['proxyid'], $data['proxies'])) {
							$data['proxies'][$stats['attributes']['proxyid']]['host_count'] += $stats['count'];
						}
					}
				}

				// items
				foreach ($server_status['item stats'] as $stats) {
					if ($stats['attributes']['status'] == ITEM_STATUS_ACTIVE) {
						if (array_key_exists($stats['attributes']['proxyid'], $data['proxies'])) {
							$data['proxies'][$stats['attributes']['proxyid']]['item_count'] += $stats['count'];
						}
					}
				}

				// performance
				if (array_key_exists('required performance', $server_status)) {
					foreach ($server_status['required performance'] as $stats) {
						if (array_key_exists($stats['attributes']['proxyid'], $data['proxies'])) {
							$data['proxies'][$stats['attributes']['proxyid']]['vps_total'] += round($stats['count'],
								2
							);
						}
					}
				}
			}
		}
		$data['config'] = ['max_in_table' => CSettingsHelper::get(CSettingsHelper::MAX_IN_TABLE)];

		$server_status = CSettingsHelper::getPrivate(CSettingsHelper::SERVER_STATUS);
		$data['server_version'] = $server_status !== ''
			? preg_split('/[a-z]/i', json_decode($server_status, true)['version'], 2)[0]
			: '';

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of proxies'));
		$this->setResponse($response);
	}
}
