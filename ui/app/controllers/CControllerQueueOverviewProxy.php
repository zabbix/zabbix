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


class CControllerQueueOverviewProxy extends CController {

	protected function init() {
		$this->disableCsrfValidation();
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

		$queue_data = $zabbix_server->getQueue(CZabbixServer::QUEUE_OVERVIEW_BY_PROXY, CSessionHelper::getId(),
			CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT)
		);

		if ($zabbix_server->getError()) {
			$queue_data = [];
			$proxies = [];

			error($zabbix_server->getError());
			show_error_message(_('Cannot display item queue.'));
		}
		else {
			$queue_data = array_column($queue_data, null, 'proxyid');
			$proxies = API::Proxy()->get([
				'output' => ['name'],
				'preservekeys' => true
			]);
			$proxies[0] = ['name' => _('Server')];

			CArrayHelper::sort($proxies, ['name']);
		}

		$response = new CControllerResponseData([
			'proxies' => $proxies,
			'queue_data' => $queue_data
		]);

		$title = _('Queue');
		if (CWebUser::getRefresh()) {
			$title .= ' ['._s('refreshed every %1$s sec.', CWebUser::getRefresh()).']';
		}
		$response->setTitle($title);

		$this->setResponse($response);
	}
}
