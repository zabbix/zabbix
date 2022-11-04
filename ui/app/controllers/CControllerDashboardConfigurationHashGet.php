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


class CControllerDashboardConfigurationHashGet extends CController {

	private ?array $db_dashboard = null;

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	/**
	 * @throws JsonException
	 */
	protected function checkInput(): bool {
		$fields = [
			'dashboardid' => 'required|db dashboard.dashboardid|not_empty'
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
		if (!$this->checkAccess(CRoleHelper::UI_MONITORING_DASHBOARD)) {
			return false;
		}

		$db_dashboards = API::Dashboard()->get([
			'output' => ['name', 'display_period', 'auto_start'],
			'selectPages' => ['dashboard_pageid', 'name', 'display_period', 'widgets'],
			'dashboardids' => $this->getInput('dashboardid')
		]);

		if (!$db_dashboards) {
			return false;
		}

		$this->db_dashboard = $db_dashboards[0];

		return true;
	}

	protected function doAction(): void {
		$this->db_dashboard['pages'] = CDashboardHelper::preparePagesForGrid($this->db_dashboard['pages'], null, true);

		$widget_defaults = APP::ModuleManager()->getWidgetsDefaults();

		$output = [
			'configuration_hash' => CDashboardHelper::getConfigurationHash($this->db_dashboard, $widget_defaults)
		];

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output, JSON_THROW_ON_ERROR)]));
	}
}
