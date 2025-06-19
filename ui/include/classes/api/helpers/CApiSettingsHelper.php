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


class CApiSettingsHelper {

	/**
	 * Get name => value list for given settings parameter names.
	 *
	 * @param array $parameter_names
	 * @param bool  $attach_defaults  Whether to mix in defaults for parameters not yet present in the DB.
	 *
	 * @return array
	 */
	public static function getParameters(array $parameter_names, bool $attach_defaults = true): array {
		$options = [
			'output' => ['name', 'value_str', 'value_int', 'value_usrgrpid', 'value_hostgroupid',
				'value_userdirectoryid', 'value_mfaid'
			],
			'filter' => [
				'name' => $parameter_names
			]
		];

		$resource = DBselect(DB::makeSql('settings', $options));
		$db_settings = [];

		while ($db_setting = DBfetch($resource)) {
			$db_settings[$db_setting['name']] = $db_setting[CSettingsSchema::PARAMETERS[$db_setting['name']]['column']];
		}

		if ($attach_defaults) {
			foreach (array_diff($parameter_names, array_keys($db_settings)) as $undeclared_parameter) {
				$db_settings[$undeclared_parameter] = CSettingsSchema::getDefault($undeclared_parameter);
			}
		}

		return $db_settings;
	}

	/**
	 * Persist settings parameters using correct type comparisons to avoid updates on unchanged values.
	 *
	 * @param array $settings
	 * @param array $db_settings  (optional) Current record values or empty array (default) to force update.
	 */
	public static function updateParameters(array $settings, array $db_settings = []): void {
		$upd_settings = [];

		foreach ($settings as $name => $value) {
			$column = CSettingsSchema::PARAMETERS[$name]['column'];

			if (array_key_exists($name, $db_settings)
					&& !DB::getUpdatedValues('settings', [$column => $value], [$column => $db_settings[$name]])) {
				continue;
			}

			$upd_settings[] = [
				'values' => [$column => $value],
				'where' => ['name' => $name]
			];
		}

		if ($upd_settings) {
			DB::update('settings', $upd_settings);
		}
	}

	/**
	 * Prevent updates on parameters not yet present in the settings table.
	 *
	 * @param array $settings
	 * @param array $db_settings
	 *
	 * @throws APIException
	 */
	public static function checkUndeclaredParameters(array $settings, array $db_settings) {
		$undeclared_db_settings = array_diff_key($settings, $db_settings);

		if ($undeclared_db_settings) {
			throw new APIException(ZBX_API_ERROR_PARAMETERS,
				_s('Cannot update "%1$s" parameter because currently it is read-only. Consider upgrading Zabbix to remove this limitation.',
					key($undeclared_db_settings)
				)
			);
		}

	}
}
