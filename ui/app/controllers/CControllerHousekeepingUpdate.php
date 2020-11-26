<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


class CControllerHousekeepingUpdate extends CController {

	protected function checkInput() {
		$fields = [
			'hk_trends'				=> 'db config.hk_trends',
			'hk_trends_global'		=> 'db config.hk_trends_global | in 1',
			'hk_trends_mode'		=> 'db config.hk_trends_mode',
			'hk_history'			=> 'db config.hk_history',
			'hk_history_global'		=> 'db config.hk_history_global | in 1',
			'hk_history_mode'		=> 'db config.hk_history_mode',
			'hk_sessions'			=> 'db config.hk_sessions',
			'hk_sessions_mode'		=> 'db config.hk_sessions_mode | in 1',
			'hk_audit'				=> 'db config.hk_audit',
			'hk_audit_mode'			=> 'db config.hk_audit_mode | in 1',
			'hk_services'			=> 'db config.hk_services',
			'hk_services_mode'		=> 'db config.hk_services_mode | in 1',
			'hk_events_autoreg'		=> 'db config.hk_events_autoreg',
			'hk_events_discovery'	=> 'db config.hk_events_discovery',
			'hk_events_internal'	=> 'db config.hk_events_internal',
			'hk_events_trigger'		=> 'db config.hk_events_trigger',
			'hk_events_mode'		=> 'db config.hk_events_mode | in 1',
			'compression_status'	=> 'db config.compression_status | in 1',
			'compress_older'		=> 'db config.compress_older'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			switch ($this->GetValidationError()) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
						->setArgument('action', 'housekeeping.edit')
						->getUrl()
					);
					$response->setFormData($this->getInputAll());
					$response->setMessageError(_('Cannot update configuration'));
					$this->setResponse($response);
					break;
				case self::VALIDATION_FATAL_ERROR:
					$this->setResponse(new CControllerResponseFatal());
					break;
			}
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
	}

	protected function doAction() {
		$config = [
			'hk_events_mode'		=> $this->getInput('hk_events_mode', 0),
			'hk_services_mode'		=> $this->getInput('hk_services_mode', 0),
			'hk_audit_mode'			=> $this->getInput('hk_audit_mode', 0),
			'hk_sessions_mode'		=> $this->getInput('hk_sessions_mode', 0),
			'hk_history_mode'		=> $this->getInput('hk_history_mode', 0),
			'hk_history_global'		=> $this->getInput('hk_history_global', 0),
			'hk_trends_mode'		=> $this->getInput('hk_trends_mode', 0),
			'hk_trends_global'		=> $this->getInput('hk_trends_global', 0),
			'compression_status'	=> $this->getInput('compression_status', 0),
			'compress_older'		=> $this->getInput('compress_older', DB::getDefault('config', 'compress_older'))
		];

		if ($config['hk_events_mode'] == 1) {
			$this->getInputs($config,
				['hk_events_trigger', 'hk_events_internal', 'hk_events_discovery', 'hk_events_autoreg']
			);
		}

		if ($config['hk_services_mode'] == 1) {
			$config['hk_services'] = $this->getInput('hk_services');
		}

		if ($config['hk_audit_mode'] == 1) {
			$config['hk_audit'] = $this->getInput('hk_audit');
		}

		if ($config['hk_sessions_mode'] == 1) {
			$config['hk_sessions'] = $this->getInput('hk_sessions');
		}

		if ($config['hk_history_global'] == 1) {
			$config['hk_history'] = $this->getInput('hk_history');
		}

		if ($config['hk_trends_global'] == 1) {
			$config['hk_trends'] = $this->getInput('hk_trends');
		}

		DBstart();
		$result = update_config($config);
		$result = DBend($result);

		$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
			->setArgument('action', 'housekeeping.edit')
		);

		if ($result) {
			$response->setMessageOk(_('Configuration updated'));
		}
		else {
			$response->setFormData($this->getInputAll() + [
				'hk_events_mode' => '0',
				'hk_services_mode' => '0',
				'hk_audit_mode' => '0',
				'hk_sessions_mode' => '0',
				'hk_history_mode' => '0',
				'hk_history_global' => '0',
				'hk_trends_mode' => '0',
				'hk_trends_global' => '0',
				'compression_status' => '0'
			]);
			$response->setMessageError(_('Cannot update configuration'));
		}

		$this->setResponse($response);
	}
}
