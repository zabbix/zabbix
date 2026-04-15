<?php declare(strict_types = 0);
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


class CControllerHousekeepingEdit extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		return true;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_HOUSEKEEPING);
	}

	private function getDefaultValues(): array {
		return [
			'hk_events_mode' => CSettingsSchema::getDefault(CHousekeepingHelper::HK_EVENTS_MODE),
			'hk_events_trigger' => CSettingsSchema::getDefault(CHousekeepingHelper::HK_EVENTS_TRIGGER),
			'hk_events_service' => CSettingsSchema::getDefault(CHousekeepingHelper::HK_EVENTS_SERVICE),
			'hk_events_internal' => CSettingsSchema::getDefault(CHousekeepingHelper::HK_EVENTS_INTERNAL),
			'hk_events_discovery' => CSettingsSchema::getDefault(CHousekeepingHelper::HK_EVENTS_DISCOVERY),
			'hk_events_autoreg' => CSettingsSchema::getDefault(CHousekeepingHelper::HK_EVENTS_AUTOREG),
			'hk_services_mode' => CSettingsSchema::getDefault(CHousekeepingHelper::HK_SERVICES_MODE),
			'hk_services' => CSettingsSchema::getDefault(CHousekeepingHelper::HK_SERVICES),
			'hk_sessions_mode' => CSettingsSchema::getDefault(CHousekeepingHelper::HK_SESSIONS_MODE),
			'hk_sessions' => CSettingsSchema::getDefault(CHousekeepingHelper::HK_SESSIONS),
			'hk_history_mode' => CSettingsSchema::getDefault(CHousekeepingHelper::HK_HISTORY_MODE),
			'hk_history_global' => CSettingsSchema::getDefault(CHousekeepingHelper::HK_HISTORY_GLOBAL),
			'hk_history' => CSettingsSchema::getDefault(CHousekeepingHelper::HK_HISTORY),
			'hk_trends_mode' => CSettingsSchema::getDefault(CHousekeepingHelper::HK_TRENDS_MODE),
			'hk_trends_global' => CSettingsSchema::getDefault(CHousekeepingHelper::HK_TRENDS_GLOBAL),
			'hk_trends' => CSettingsSchema::getDefault(CHousekeepingHelper::HK_TRENDS),
			'compression_status' => CSettingsSchema::getDefault(CHousekeepingHelper::COMPRESSION_STATUS),
			'compress_older' => CSettingsSchema::getDefault(CHousekeepingHelper::COMPRESS_OLDER)
		];
	}

	protected function doAction(): void {
		$default_values = $this->getDefaultValues();
		$data = [];

		foreach (array_keys($default_values) as $key) {
			$data[$key] = CHousekeepingHelper::get($key);
		}

		$data['extension_err_code'] = ZBX_EXT_ERR_UNDEFINED;
		$data['compression_availability'] = false;
		$data['db_extension'] = CHousekeepingHelper::get(CHousekeepingHelper::DB_EXTENSION);

		if ($data['db_extension'] === ZBX_DB_EXTENSION_TIMESCALEDB) {
			// Temporary state to show checkbox checked and disabled before the real state is detected.
			$data['compression_not_detected'] = true;

			foreach (CSettingsHelper::getDbVersionStatus() as $dbversion) {
				if ($dbversion['database'] === ZBX_DB_EXTENSION_TIMESCALEDB) {
					$data['timescaledb_min_version'] = $dbversion['min_version'];
					$data['timescaledb_max_version'] = $dbversion['max_version'];
					$data['timescaledb_min_supported_version'] = $dbversion['min_supported_version'];
					$data['extension_err_code'] = $dbversion['extension_err_code'];
					$data['compression_availability'] = array_key_exists('compression_availability', $dbversion)
						&& $dbversion['compression_availability'];

					if (array_key_exists('compression_availability', $dbversion)) {
						$data['compression_not_detected'] = false;
					}

					if ($data['compression_availability']) {
						$data += CHousekeepingHelper::getWarnings();
					}

					break;
				}
			}
		}

		$data['js_validation_rules'] = (new CFormValidator(CControllerHousekeepingUpdate::getValidationRules()))
			->getRules();

		$data['default_values'] = $default_values;

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of housekeeping'));
		$this->setResponse($response);
	}
}
