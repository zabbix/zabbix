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

	public const OUTPUT_FIELDS = ['serverid', 'lastaccess'];

	public function get(array $options = []): array {
		self::validateGet($options);

		$server_data = [];

		if (in_array('serverid', $options['output'])) {
			$server_data['serverid'] = $options['serverid'];
		}

		if (in_array('lastaccess', $options['output'])) {
			$server_data['lastaccess'] = self::getServerLastAccess();
		}

		return $server_data;
	}

	private static function validateGet(array &$options): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'serverid' => ['type' => API_UUID_V7, 'flags' => API_REQUIRED],
			'output' =>	  ['type' => API_OUTPUT, 'flags' => API_NORMALIZE, 'in' => implode(',', self::OUTPUT_FIELDS), 'default' => API_OUTPUT_EXTEND]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if ($options['serverid'] !== CSettingsHelper::get(CSettingsHelper::SERVER_ID)) {
			self::exception(ZBX_API_ERROR_NO_ENTITY, _s('Invalid parameter "%1$s": %2$s.', '/serverid',
				_('object does not exist')
			));
		}
	}

	private static function getServerLastAccess(): int {
		$active_node = DB::select('ha_node', [
			'output' => ['lastaccess'],
			'sortfield' => ['lastaccess'],
			'sortorder' => [ZBX_SORT_DOWN],
			'limit' => 1
		]);

		if ($active_node) {
			return $active_node[0]['lastaccess'];
		}

		return 0;
	}
}
