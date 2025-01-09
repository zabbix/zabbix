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


class CControllerHostDashboardView extends CController {

	private $host;

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'hostid' => 'required|db hosts.hostid',
			'dashboardid' => 'db dashboard.dashboardid',
			'from' => 'range_time',
			'to' => 'range_time'
		];

		$ret = $this->validateInput($fields) && $this->validateTimeSelectorPeriod();

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_MONITORING_HOSTS)) {
			return false;
		}

		$db_hosts = API::Host()->get([
			'output' => ['hostid', 'name'],
			'selectParentTemplates' => ['templateid'],
			'hostids' => [$this->getInput('hostid')]
		]);

		if (!$db_hosts) {
			return false;
		}

		$this->host = $db_hosts[0];

		return true;
	}

	protected function doAction() {
		$host_dashboards = $this->getSortedHostDashboards();

		if (!$host_dashboards) {
			$data = ['no_data' => true];
		}
		else {
			$dashboardid = $this->hasInput('dashboardid')
				? $this->getInput('dashboardid')
				: CProfile::get('web.host.dashboard.dashboardid', null, $this->getInput('hostid'));

			if (!in_array($dashboardid, array_column($host_dashboards, 'dashboardid'))) {
				$dashboardid = $host_dashboards[0]['dashboardid'];
			}

			$db_dashboards = API::TemplateDashboard()->get([
				'output' => ['dashboardid', 'name', 'templateid', 'display_period', 'auto_start'],
				'selectPages' => ['dashboard_pageid', 'name', 'display_period', 'widgets'],
				'dashboardids' => [$dashboardid]
			]);

			if ($db_dashboards) {
				$dashboard = $db_dashboards[0];

				CProfile::update('web.host.dashboard.dashboardid', $dashboard['dashboardid'], PROFILE_TYPE_ID,
					$this->getInput('hostid')
				);

				$widget_defaults = APP::ModuleManager()->getWidgetsDefaults();

				$configuration_hash = CDashboardHelper::getConfigurationHash($dashboard, $widget_defaults);

				$pages_raw = $dashboard['pages'];
				$pages_prepared = CDashboardHelper::preparePages($pages_raw, $dashboard['templateid'], true);

				$dashboard['pages'] = $pages_prepared;

				$broadcast_requirements = CDashboardHelper::getBroadcastRequirements($pages_prepared);

				$time_selector_options = [
					'profileIdx' => 'web.dashboard.filter',
					'profileIdx2' => $dashboard['dashboardid'],
					'from' => $this->hasInput('from') ? $this->getInput('from') : null,
					'to' => $this->hasInput('to') ? $this->getInput('to') : null
				];

				updateTimeSelectorPeriod($time_selector_options);

				$dashboard_time_period = getTimeSelectorPeriod($time_selector_options);

				$data = [
					'host_dashboards' => $host_dashboards,
					'dashboard' => $dashboard,
					'widget_defaults' => $widget_defaults,
					'configuration_hash' => $configuration_hash,
					'broadcast_requirements' => $broadcast_requirements,
					'dashboard_host' => $this->host,
					'dashboard_time_period' => $dashboard_time_period,
					'active_tab' => CProfile::get('web.dashboard.filter.active', 1)
				];
			}
			else {
				$data = ['error' => _('No permissions to referred object or it does not exist!')];

				CProfile::delete('web.host.dashboard.dashboardid', $this->getInput('hostid'));
			}
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Dashboards'));
		$this->setResponse($response);
	}

	private function getSortedHostDashboards(): array {
		$dashboards = getHostDashboards($this->host['hostid'], ['dashboardid', 'name']);

		CArrayHelper::sort($dashboards, [['field' => 'name', 'order' => ZBX_SORT_UP]]);

		return array_values($dashboards);
	}
}
