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


class CControllerQueueOverview extends CController {

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

		$queue_data = $zabbix_server->getQueue(CZabbixServer::QUEUE_OVERVIEW, CSessionHelper::getId(),
			CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT)
		);

		if ($zabbix_server->getError()) {
			$queue_data = [];
			$item_types = [];

			error($zabbix_server->getError());
			show_error_message(_('Cannot display item queue.'));
		}
		else {
			$queue_data = array_column($queue_data, null, 'itemtype');
			$item_types = [
				ITEM_TYPE_ZABBIX,
				ITEM_TYPE_ZABBIX_ACTIVE,
				ITEM_TYPE_SIMPLE,
				ITEM_TYPE_SNMP,
				ITEM_TYPE_INTERNAL,
				ITEM_TYPE_EXTERNAL,
				ITEM_TYPE_DB_MONITOR,
				ITEM_TYPE_HTTPAGENT,
				ITEM_TYPE_IPMI,
				ITEM_TYPE_SSH,
				ITEM_TYPE_TELNET,
				ITEM_TYPE_JMX,
				ITEM_TYPE_CALCULATED,
				ITEM_TYPE_SCRIPT,
				ITEM_TYPE_BROWSER
			];
		}

		$response = new CControllerResponseData([
			'item_types' => $item_types,
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
