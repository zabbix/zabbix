<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


/**
 * Class containing methods for operations with server information.
 */
class CServerInfo extends CApiService {

	public const ACCESS_RULES = [
		'get' => []
	];

	public const OUTPUT_FIELDS = ['serverid', 'status'];

	public function get(array $options = []): array {
		self::validateGet($options);

		$server_status = [];

		if (array_key_exists('status', array_flip($options['output']))) {
			$server_status['status'] = (int) $this->isServerRunning();
		}

		unset($options['output']);

		return $options + $server_status;
	}

	private static function validateGet(array &$options): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'serverid' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
			'output' =>	  ['type' => API_OUTPUT, 'flags' => API_NORMALIZE, 'in' => implode(',', self::OUTPUT_FIELDS), 'default' => API_OUTPUT_EXTEND]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if ($options['serverid'] !== CApiDpopHelper::getServerId()) {
			self::exception(ZBX_API_ERROR_NO_ENTITY, _s('Invalid parameter "%1$s": %2$s.', '/serverid',
				_('referred object does not exist')
			));
		}
	}

	private function isServerRunning(): bool {
		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		$server = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT,
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::CONNECT_TIMEOUT)),
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::DEVICE_LINK_TIMEOUT)), ZBX_SOCKET_BYTES_LIMIT
		);

		return $server->isRunning();
	}
}
