<?php declare(strict_types=1);
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


class CControllerHostDashboardView extends CController {

	private $host;

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
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

	protected function checkPermissions() {
		if ($this->getUserType() < USER_TYPE_ZABBIX_USER) {
			return false;
		}

		$hosts = API::Host()->get([
			'output' => ['hostid', 'name'],
			'selectParentTemplates' => ['templateid'],
			'hostids' => [$this->getInput('hostid')]
		]);

		$this->host = array_shift($hosts);

		return (bool) $this->host;
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

			if (!array_key_exists($dashboardid, $host_dashboards)) {
				$dashboardid = array_keys($host_dashboards)[0];
			}

			$dashboards = API::TemplateDashboard()->get([
				'output' => ['dashboardid', 'name', 'templateid', 'display_period', 'auto_start'],
				'selectPages' => ['dashboard_pageid', 'name', 'display_period', 'widgets'],
				'dashboardids' => [$dashboardid]
			]);

			$dashboard = array_shift($dashboards);

			if ($dashboard !== null) {
				CProfile::update('web.host.dashboard.dashboardid', $dashboard['dashboardid'], PROFILE_TYPE_ID,
					$this->getInput('hostid')
				);

				$dashboard['pages'] = CDashboardHelper::preparePagesForGrid($dashboard['pages'],
					$dashboard['templateid'], true
				);

				$time_selector_options = [
					'profileIdx' => 'web.dashboard.filter',
					'profileIdx2' => $dashboard['dashboardid'],
					'from' => $this->hasInput('from') ? $this->getInput('from') : null,
					'to' => $this->hasInput('to') ? $this->getInput('to') : null
				];

				updateTimeSelectorPeriod($time_selector_options);

				$data = [
					'host' => $this->host,
					'host_dashboards' => $host_dashboards,
					'dashboard' => $dashboard,
					'widget_defaults' => CWidgetConfig::getDefaults(CWidgetConfig::CONTEXT_TEMPLATE_DASHBOARD),
					'has_time_selector' => CDashboardHelper::hasTimeSelector($dashboard['pages']),
					'time_period' => getTimeSelectorPeriod($time_selector_options),
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

		return array_combine(array_column($dashboards, 'dashboardid'), array_column($dashboards, 'name'));
	}
}
