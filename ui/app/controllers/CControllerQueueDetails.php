<?php declare(strict_types = 0);
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


class CControllerQueueDetails extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		return true;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_QUEUE);
	}

	protected function doAction() {
		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		$zabbix_server = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT,
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::CONNECT_TIMEOUT)),
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::SOCKET_TIMEOUT)), ZBX_SOCKET_BYTES_LIMIT
		);

		$queue_data = $zabbix_server->getQueue(CZabbixServer::QUEUE_DETAILS, CSessionHelper::getId(),
			CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT)
		);

		if ($zabbix_server->getError()) {
			$items = [];
			$hosts = [];
			$queue_data = [];
			$proxies = [];

			error($zabbix_server->getError());
			show_error_message(_('Cannot display item queue.'));
		}
		else {
			$queue_data = array_column($queue_data, null, 'itemid');
			$items = API::Item()->get([
				'output' => ['hostid', 'name'],
				'selectHosts' => ['name'],
				'itemids' => array_keys($queue_data),
				'webitems' => true,
				'preservekeys' => true
			]);

			if (count($queue_data) != count($items)) {
				$items += API::DiscoveryRule()->get([
					'output' => ['hostid', 'name'],
					'selectHosts' => ['name'],
					'itemids' => array_diff(array_keys($queue_data), array_keys($items)),
					'preservekeys' => true
				]);
			}

			$hosts = API::Host()->get([
				'output' => ['proxy_hostid'],
				'hostids' => array_column($items, 'hostid', 'hostid'),
				'preservekeys' => true
			]);

			$proxy_hostids = [];
			foreach ($hosts as $host) {
				if ($host['proxy_hostid']) {
					$proxy_hostids[$host['proxy_hostid']] = true;
				}
			}

			$proxies = [];

			if ($proxy_hostids) {
				$proxies = API::Proxy()->get([
					'proxyids' => array_keys($proxy_hostids),
					'output' => ['proxyid', 'host'],
					'preservekeys' => true
				]);
			}
		}

		$response = new CControllerResponseData([
			'items' => $items,
			'hosts' => $hosts,
			'proxies' => $proxies,
			'queue_data' => $queue_data,
			'total_count' => $zabbix_server->getTotalCount()
		]);

		$title = _('Queue');
		if (CWebUser::getRefresh()) {
			$title .= ' ['._s('refreshed every %1$s sec.', CWebUser::getRefresh()).']';
		}
		$response->setTitle($title);

		$this->setResponse($response);
	}
}
