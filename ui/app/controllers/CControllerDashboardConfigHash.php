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


class CControllerDashboardConfigHash extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableCsrfValidation();
	}

	/**
	 * @throws JsonException
	 */
	protected function checkInput(): bool {
		$fields = [
			'dashboardid' =>	'required|db dashboard.dashboardid|not_empty',
			'templateid' =>		'db dashboard.dashboardid|not_empty'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				], JSON_THROW_ON_ERROR)])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		/*
		 * Permission check errors (e.g. expired sessions) must be ignored by the frontend and must not cause dashboard
		 * reload.
		 */
		return true;
	}

	/**
	 * @throws APIException|JsonException
	 */
	protected function doAction(): void {
		$configuration_hash = null;

		if (($this->hasInput('templateid') && $this->checkAccess(CRoleHelper::UI_MONITORING_HOSTS))
				|| (!$this->hasInput('templateid') && $this->checkAccess(CRoleHelper::UI_MONITORING_DASHBOARD))) {
			if ($this->hasInput('templateid')) {
				$db_dashboards = API::TemplateDashboard()->get([
					'output' => ['name', 'display_period', 'auto_start'],
					'selectPages' => ['dashboard_pageid', 'name', 'display_period', 'widgets'],
					'dashboardids' => $this->getInput('dashboardid')
				]);
			}
			else {
				$db_dashboards = API::Dashboard()->get([
					'output' => ['name', 'display_period', 'auto_start'],
					'selectPages' => ['dashboard_pageid', 'name', 'display_period', 'widgets'],
					'dashboardids' => $this->getInput('dashboardid')
				]);
			}

			if ($db_dashboards) {
				$configuration_hash = CDashboardHelper::getConfigurationHash($db_dashboards[0],
					APP::ModuleManager()->getWidgetsDefaults()
				);
			}
		}

		$output = [
			'configuration_hash' => $configuration_hash
		];

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output, JSON_THROW_ON_ERROR)]));
	}
}
