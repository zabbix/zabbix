<?php
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


class CControllerGuiEdit extends CController {

	/**
	 * @var array
	 */
	protected $timezones;

	protected function init() {
		$this->disableCsrfValidation();

		$this->timezones = [
			ZBX_DEFAULT_TIMEZONE => CTimezoneHelper::getTitle(CTimezoneHelper::getSystemTimezone(), _('System'))
		] + CTimezoneHelper::getList();
	}

	protected function checkInput() {
		$fields = [
			'default_lang' =>				'db config.default_lang',
			'default_timezone' =>			'db config.default_timezone|in '.implode(',', array_keys($this->timezones)),
			'default_theme' =>				'db config.default_theme',
			'search_limit' =>				'db config.search_limit',
			'max_overview_table_size' =>	'db config.max_overview_table_size',
			'max_in_table' =>				'db config.max_in_table',
			'server_check_interval' =>		'db config.server_check_interval',
			'work_period' =>				'db config.work_period',
			'show_technical_errors' =>		'db config.show_technical_errors',
			'history_period' =>				'db config.history_period',
			'period_default' =>				'db config.period_default',
			'max_period' =>					'db config.max_period'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction() {
		$data = [
			'default_lang' => $this->getInput('default_lang', CSettingsHelper::get(CSettingsHelper::DEFAULT_LANG)),
			'default_timezone' => $this->getInput('default_timezone', CSettingsHelper::get(
				CSettingsHelper::DEFAULT_TIMEZONE
			)),
			'timezones' => $this->timezones,
			'default_theme' => $this->getInput('default_theme', CSettingsHelper::get(CSettingsHelper::DEFAULT_THEME)),
			'search_limit' => $this->getInput('search_limit', CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT)),
			'max_overview_table_size' => $this->getInput('max_overview_table_size', CSettingsHelper::get(
				CSettingsHelper::MAX_OVERVIEW_TABLE_SIZE
			)),
			'max_in_table' => $this->getInput('max_in_table', CSettingsHelper::get(CSettingsHelper::MAX_IN_TABLE)),
			'server_check_interval' => $this->getInput('server_check_interval', CSettingsHelper::get(
				CSettingsHelper::SERVER_CHECK_INTERVAL
			)),
			'work_period' => $this->getInput('work_period', CSettingsHelper::get(CSettingsHelper::WORK_PERIOD)),
			'show_technical_errors' => $this->getInput('show_technical_errors', CSettingsHelper::get(
				CSettingsHelper::SHOW_TECHNICAL_ERRORS
			)),
			'history_period' => $this->getInput('history_period', CSettingsHelper::get(
				CSettingsHelper::HISTORY_PERIOD
			)),
			'period_default' => $this->getInput('period_default', CSettingsHelper::get(
				CSettingsHelper::PERIOD_DEFAULT
			)),
			'max_period' => $this->getInput('max_period', CSettingsHelper::get(CSettingsHelper::MAX_PERIOD))
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of GUI'));
		$this->setResponse($response);
	}
}
