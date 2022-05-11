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


class CControllerHousekeepingEdit extends CController {

	protected function init(): void {
		$this->disableSIDValidation();
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
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
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
			'compression_status' => $this->getInput('compression_status', CHousekeepingHelper::get(
				CHousekeepingHelper::COMPRESSION_STATUS
			)),
			'compress_older' => $this->getInput('compress_older', CHousekeepingHelper::get(
				CHousekeepingHelper::COMPRESS_OLDER
			)),
			'db_extension' => CHousekeepingHelper::get(CHousekeepingHelper::DB_EXTENSION)
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of housekeeping'));
		$this->setResponse($response);
	}
}
