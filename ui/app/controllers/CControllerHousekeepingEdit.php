<?php
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

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'hk_events_mode'			=> 'db config.hk_events_mode',
			'hk_events_trigger'			=> 'db config.hk_events_trigger',
			'hk_events_internal'		=> 'db config.hk_events_internal',
			'hk_events_discovery'		=> 'db config.hk_events_discovery',
			'hk_events_autoreg'			=> 'db config.hk_events_autoreg',
			'hk_services_mode'			=> 'db config.hk_services_mode',
			'hk_services'				=> 'db config.hk_services',
			'hk_audit_mode'				=> 'db config.hk_audit_mode',
			'hk_audit'					=> 'db config.hk_audit',
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

	protected function checkPermissions() {
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
	}

	protected function doAction() {
		global $DB;

		$config = select_config();

		$data = [
			'hk_events_mode'			=> $this->getInput('hk_events_mode',			$config['hk_events_mode']),
			'hk_events_trigger'			=> $this->getInput('hk_events_trigger',			$config['hk_events_trigger']),
			'hk_events_internal'		=> $this->getInput('hk_events_internal',		$config['hk_events_internal']),
			'hk_events_discovery'		=> $this->getInput('hk_events_discovery',		$config['hk_events_discovery']),
			'hk_events_autoreg'			=> $this->getInput('hk_events_autoreg',			$config['hk_events_autoreg']),
			'hk_services_mode'			=> $this->getInput('hk_services_mode',			$config['hk_services_mode']),
			'hk_services'				=> $this->getInput('hk_services',				$config['hk_services']),
			'hk_audit_mode'				=> $this->getInput('hk_audit_mode',				$config['hk_audit_mode']),
			'hk_audit'					=> $this->getInput('hk_audit',					$config['hk_audit']),
			'hk_sessions_mode'			=> $this->getInput('hk_sessions_mode',			$config['hk_sessions_mode']),
			'hk_sessions'				=> $this->getInput('hk_sessions',				$config['hk_sessions']),
			'hk_history_mode'			=> $this->getInput('hk_history_mode',			$config['hk_history_mode']),
			'hk_history_global'			=> $this->getInput('hk_history_global',			$config['hk_history_global']),
			'hk_history'				=> $this->getInput('hk_history',				$config['hk_history']),
			'hk_trends_mode'			=> $this->getInput('hk_trends_mode',			$config['hk_trends_mode']),
			'hk_trends_global'			=> $this->getInput('hk_trends_global',			$config['hk_trends_global']),
			'hk_trends'					=> $this->getInput('hk_trends',					$config['hk_trends']),
			'compression_status'		=> $this->getInput('compression_status',		$config['compression_status']),
			'compress_older'			=> $this->getInput('compress_older',			$config['compress_older']),
			'db_extension'				=> $config['db_extension'],
			'compression_availability'	=> $config['compression_availability']
		];

		if ($DB['TYPE'] == ZBX_DB_POSTGRESQL && $config['db_extension'] === ZBX_DB_EXTENSION_TIMESCALEDB
				&& $config['compression_availability'] == 1) {
			$hk_warnings = [
				'hk_needs_override_history' => PostgresqlDbBackend::isCompressed([
					'history', 'history_log', 'history_str', 'history_text', 'history_uint'
				]),
				'hk_needs_override_trends' => PostgresqlDbBackend::isCompressed(['trends', 'trends_uint'])
			];

			$data += array_filter($hk_warnings);
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of housekeeping'));
		$this->setResponse($response);
	}
}
