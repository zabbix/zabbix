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


class CControllerQueueOverviewProxy extends CController {

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
			$proxies = API::proxy()->get([
				'output' => ['host'],
				'preservekeys' => true
			]);
			$proxies[0] = ['host' => _('Server')];

			CArrayHelper::sort($proxies, ['host']);
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
