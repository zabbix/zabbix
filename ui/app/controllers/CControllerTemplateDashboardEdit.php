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


class CControllerTemplateDashboardEdit extends CController {

	private $dashboard;

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'templateid' => 'db dashboard.templateid',
			'dashboardid' => 'db dashboard.dashboardid'
		];

		$ret = $this->validateInput($fields);

		$ret = $ret && ($this->hasInput('templateid') || $this->hasInput('dashboardid'));

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if ($this->getUserType() < USER_TYPE_ZABBIX_ADMIN) {
			return false;
		}

		if ($this->hasInput('dashboardid')) {
			$dashboards = API::TemplateDashboard()->get([
				'output' => ['dashboardid', 'name', 'templateid', 'display_period', 'auto_start'],
				'selectPages' => ['dashboard_pageid', 'name', 'display_period', 'widgets'],
				'dashboardids' => [$this->getInput('dashboardid')],
				'editable' => true
			]);

			$this->dashboard = $dashboards[0];

			return (bool) $this->dashboard;
		}
		else {
			return isWritableHostTemplates((array) $this->getInput('templateid'));
		}
	}

	protected function doAction() {
		if ($this->hasInput('dashboardid')) {
			$dashboard = $this->dashboard;
			$dashboard['pages'] = CDashboardHelper::preparePagesForGrid($dashboard['pages'], $dashboard['templateid'],
				false
			);
		}
		else {
			$dashboard = [
				'dashboardid' => null,
				'templateid' => $this->getInput('templateid'),
				'name' => _('New dashboard'),
				'display_period' => DB::getDefault('dashboard', 'display_period'),
				'auto_start' => DB::getDefault('dashboard', 'auto_start'),
				'pages' => [
					[
						'dashboard_pageid' => null,
						'name' => '',
						'display_period' => 0,
						'widgets' => []
					]
				]
			];
		}

		$data = [
			'dashboard' => $dashboard,
			'widget_defaults' => CWidgetConfig::getDefaults(CWidgetConfig::CONTEXT_TEMPLATE_DASHBOARD),
			'time_period' => getTimeSelectorPeriod([]),
			'page' => CPagerHelper::loadPage('template.dashboard.list', null)
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of dashboards'));
		$this->setResponse($response);
	}
}
