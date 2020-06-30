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


class CControllerGuiUpdate extends CController {

	protected function checkInput() {
		$themes = array_keys(APP::getThemes());
		$fields = [
			'default_theme'           => 'required|in '.implode(',', $themes).'|db config.default_theme',
			'search_limit'            => 'required|db config.search_limit|ge 1|le 999999',
			'max_in_table'            => 'required|db config.max_in_table|ge 1|le 99999',
			'server_check_interval'   => 'required|db config.server_check_interval|in 0,'.SERVER_CHECK_INTERVAL,
			'work_period'             => 'required|db config.work_period|time_periods',
			'show_technical_errors'   => 'db config.show_technical_errors|in 0,1',
			'history_period'          => 'required|db config.history_period',
			'period_default'          => 'required|db config.period_default',
			'max_period'              => 'required|db config.max_period'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			switch ($this->GetValidationError()) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
						->setArgument('action', 'gui.edit')
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
		else {
			$fields = [
				'history_period' => [
					'min' => SEC_PER_DAY,
					'max' => 7 * SEC_PER_DAY,
					'allow_zero' => false,
					'message' => _('Invalid max history display period: %1$s.')
				],
				'period_default' => [
					'min' => SEC_PER_MIN,
					'max' => 10 * SEC_PER_YEAR,
					'allow_zero' => false,
					'message' => _('Invalid time filter default period: %1$s.')
				],
				'max_period' => [
					'min' => SEC_PER_YEAR,
					'max' => 10 * SEC_PER_YEAR,
					'allow_zero' => false,
					'message' => _('Invalid max period: %1$s.'),
					'with_year' => true
				]
			];

			foreach ($fields as $field => $args) {
				$options = array_key_exists('with_year', $args) ? ['with_year' => true] : [];
				if ($this->hasInput($field)
						&& !validateTimeUnit($this->getInput($field), $args['min'], $args['max'], $args['allow_zero'],
							$error, $options
						)) {
					$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
						->setArgument('action', 'gui.edit')
						->getUrl()
					);
					$response->setFormData($this->getInputAll());
					$response->setMessageError(_('Cannot update configurationnn'));
					$this->setResponse($response);
					error(sprintf($args['message'], $error));

					$ret = false;
					break;
				}
			}
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
	}

	protected function doAction() {
		$result = API::Settings()->update([
			'default_theme'           => $this->getInput('default_theme'),
			'search_limit'            => $this->getInput('search_limit'),
			'max_in_table'            => $this->getInput('max_in_table'),
			'server_check_interval'   => $this->getInput('server_check_interval'),
			'work_period'             => $this->getInput('work_period'),
			'show_technical_errors'   => $this->getInput('show_technical_errors'),
			'history_period'          => $this->getInput('history_period'),
			'period_default'          => $this->getInput('period_default'),
			'max_period'              => $this->getInput('max_period')
		]);

		$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
			->setArgument('action', 'gui.edit')
			->getUrl()
		);

		if ($result) {
			$response->setMessageOk(_('Configuration updated'));
		}
		else {
			$response->setFormData($this->getInputAll());
			$response->setMessageError(_('Cannot update configuration'));
		}

		$this->setResponse($response);
	}
}
