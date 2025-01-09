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


class CControllerHousekeepingEdit extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'hk_events_mode'			=> 'db config.hk_events_mode',
			'hk_events_trigger'			=> 'db config.hk_events_trigger',
			'hk_events_service'			=> 'db config.hk_events_service',
			'hk_events_internal'		=> 'db config.hk_events_internal',
			'hk_events_discovery'		=> 'db config.hk_events_discovery',
			'hk_events_autoreg'			=> 'db config.hk_events_autoreg',
			'hk_services_mode'			=> 'db config.hk_services_mode',
			'hk_services'				=> 'db config.hk_services',
			'hk_sessions_mode'			=> 'db config.hk_sessions_mode',
			'hk_sessions'				=> 'db config.hk_sessions',
			'hk_history_mode'			=> 'db config.hk_history_mode',
			'hk_history_global'			=> 'db config.hk_history_global',
			'hk_history'				=> 'db config.hk_history',
			'hk_trends_mode'			=> 'db config.hk_trends_mode',
			'hk_trends_global'			=> 'db config.hk_trends_global',
			'hk_trends'					=> 'db config.hk_trends',
			'compression_status'		=> 'db config.compression_status',
			'compress_older'			=> 'db config.compress_older'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_HOUSEKEEPING);
	}

	protected function doAction(): void {
		$data = [
			'hk_events_mode' => $this->getInput('hk_events_mode', CHousekeepingHelper::get(
				CHousekeepingHelper::HK_EVENTS_MODE
			)),
			'hk_events_trigger' => $this->getInput('hk_events_trigger', CHousekeepingHelper::get(
				CHousekeepingHelper::HK_EVENTS_TRIGGER
			)),
			'hk_events_service' => $this->getInput('hk_events_service', CHousekeepingHelper::get(
				CHousekeepingHelper::HK_EVENTS_SERVICE
			)),
			'hk_events_internal' => $this->getInput('hk_events_internal', CHousekeepingHelper::get(
				CHousekeepingHelper::HK_EVENTS_INTERNAL
			)),
			'hk_events_discovery' => $this->getInput('hk_events_discovery', CHousekeepingHelper::get(
				CHousekeepingHelper::HK_EVENTS_DISCOVERY
			)),
			'hk_events_autoreg' => $this->getInput('hk_events_autoreg', CHousekeepingHelper::get(
				CHousekeepingHelper::HK_EVENTS_AUTOREG
			)),
			'hk_services_mode' => $this->getInput('hk_services_mode', CHousekeepingHelper::get(
				CHousekeepingHelper::HK_SERVICES_MODE
			)),
			'hk_services' => $this->getInput('hk_services', CHousekeepingHelper::get(CHousekeepingHelper::HK_SERVICES)),
			'hk_sessions_mode' => $this->getInput('hk_sessions_mode', CHousekeepingHelper::get(
				CHousekeepingHelper::HK_SESSIONS_MODE
			)),
			'hk_sessions' => $this->getInput('hk_sessions', CHousekeepingHelper::get(CHousekeepingHelper::HK_SESSIONS)),
			'hk_history_mode' => $this->getInput('hk_history_mode', CHousekeepingHelper::get(
				CHousekeepingHelper::HK_HISTORY_MODE
			)),
			'hk_history_global' => $this->getInput('hk_history_global', CHousekeepingHelper::get(
				CHousekeepingHelper::HK_HISTORY_GLOBAL
			)),
			'hk_history' => $this->getInput('hk_history', CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY)),
			'hk_trends_mode' => $this->getInput('hk_trends_mode', CHousekeepingHelper::get(
				CHousekeepingHelper::HK_TRENDS_MODE
			)),
			'hk_trends_global' => $this->getInput('hk_trends_global', CHousekeepingHelper::get(
				CHousekeepingHelper::HK_TRENDS_GLOBAL
			)),
			'hk_trends' => $this->getInput('hk_trends', CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS)),
			'extension_err_code' => ZBX_EXT_ERR_UNDEFINED,
			'compression_availability' => false,
			'compression_status' => $this->getInput('compression_status', CHousekeepingHelper::get(
				CHousekeepingHelper::COMPRESSION_STATUS
			)),
			'compress_older' => $this->getInput('compress_older', CHousekeepingHelper::get(
				CHousekeepingHelper::COMPRESS_OLDER
			)),
			'db_extension' => CHousekeepingHelper::get(CHousekeepingHelper::DB_EXTENSION)
		];

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

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of housekeeping'));
		$this->setResponse($response);
	}
}
