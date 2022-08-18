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


class CControllerDashboardView extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'dashboardid' =>		'db dashboard.dashboardid',
			'source_dashboardid' =>	'db dashboard.dashboardid',
			'hostid' =>				'db hosts.hostid',
			'new' =>				'in 1',
			'cancel' =>				'in 1',
			'from' =>				'range_time',
			'to' =>					'range_time',
			'slideshow' =>			'in 1'
		];

		$ret = $this->validateInput($fields) && $this->validateTimeSelectorPeriod();

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (!$this->checkAccess(CRoleHelper::UI_MONITORING_DASHBOARD)) {
			return false;
		}

		if ($this->hasInput('new') || $this->hasInput('source_dashboardid')) {
			return $this->checkAccess(CRoleHelper::ACTIONS_EDIT_DASHBOARDS);
		}

		if ($this->hasInput('hostid')) {
			$hosts = API::Host()->get([
				'output' => [],
				'hostids' => [$this->getInput('hostid')]
			]);

			if (!$hosts) {
				return false;
			}
		}

		return true;
	}

	protected function doAction() {
		[$dashboard, $error] = $this->getDashboard();

		if ($error !== null) {
			$response = new CControllerResponseData(['error' => $error]);
			$response->setTitle(_('Dashboard'));
			$this->setResponse($response);

			return;
		}

		if ($dashboard === null) {
			$this->setResponse(new CControllerResponseRedirect((new CUrl('zabbix.php'))
				->setArgument('action', 'dashboard.list')
				->setArgument('page', $this->hasInput('cancel') ? CPagerHelper::loadPage('dashboard.list', null) : null)
			));

			return;
		}

		if ($this->hasInput('slideshow')) {
			$dashboard['auto_start'] = '1';
		}

		$dashboard['can_edit_dashboards'] = $this->checkAccess(CRoleHelper::ACTIONS_EDIT_DASHBOARDS);
		$dashboard['can_view_reports'] = $this->checkAccess(CRoleHelper::UI_REPORTS_SCHEDULED_REPORTS);
		$dashboard['can_create_reports'] = $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SCHEDULED_REPORTS);

		$time_selector_options = [
			'profileIdx' => 'web.dashboard.filter',
			'profileIdx2' => ($dashboard['dashboardid'] !== null) ? $dashboard['dashboardid'] : 0,
			'from' => $this->hasInput('from') ? $this->getInput('from') : null,
			'to' => $this->hasInput('to') ? $this->getInput('to') : null
		];

		updateTimeSelectorPeriod($time_selector_options);

		$data = [
			'dashboard' => $dashboard,
			'widget_defaults' => CWidgetConfig::getDefaults(CWidgetConfig::CONTEXT_DASHBOARD),
			'has_time_selector' => CDashboardHelper::hasTimeSelector($dashboard['pages']),
			'time_period' => getTimeSelectorPeriod($time_selector_options),
			'active_tab' => CProfile::get('web.dashboard.filter.active', 1)
		];

		if (self::hasDynamicWidgets($dashboard['pages'])) {
			$hostid = $this->getInput('hostid', CProfile::get('web.dashboard.hostid', 0));

			$hosts = ($hostid != 0)
				? CArrayHelper::renameObjectsKeys(API::Host()->get([
					'output' => ['hostid', 'name'],
					'hostids' => [$hostid]
				]), ['hostid' => 'id'])
				: [];

			$data['dynamic'] = [
				'has_dynamic_widgets' => true,
				'host' => $hosts ? $hosts[0] : null
			];
		}
		else {
			$data['dynamic'] = [
				'has_dynamic_widgets' => false,
				'host' => null
			];
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Dashboard'));
		$this->setResponse($response);
	}

	/**
	 * Get dashboard data from API.
	 *
	 * @return array
	 */
	private function getDashboard() {
		$dashboard = null;
		$error = null;

		if ($this->hasInput('new')) {
			$dashboard = [
				'dashboardid' => null,
				'name' => _('New dashboard'),
				'display_period' => DB::getDefault('dashboard', 'display_period'),
				'auto_start' => DB::getDefault('dashboard', 'auto_start'),
				'editable' => true,
				'pages' => [
					[
						'dashboard_pageid' => null,
						'name' => '',
						'display_period' => 0,
						'widgets' => []
					]
				],
				'owner' => [
					'id' => CWebUser::$data['userid'],
					'name' => CDashboardHelper::getOwnerName(CWebUser::$data['userid'])
				],
				'has_related_reports' => false
			];
		}
		elseif ($this->hasInput('source_dashboardid')) {
			// Clone dashboard and show as new.
			$dashboards = API::Dashboard()->get([
				'output' => ['name', 'private', 'display_period', 'auto_start'],
				'selectPages' => ['dashboard_pageid', 'name', 'display_period', 'widgets'],
				'selectUsers' => ['userid', 'permission'],
				'selectUserGroups' => ['usrgrpid', 'permission'],
				'dashboardids' => [$this->getInput('source_dashboardid')]
			]);

			if ($dashboards) {
				$dashboard = [
					'dashboardid' => null,
					'name' => $dashboards[0]['name'],
					'display_period' => $dashboards[0]['display_period'],
					'auto_start' => $dashboards[0]['auto_start'],
					'editable' => true,
					'pages' => CDashboardHelper::preparePagesForGrid(
						CDashboardHelper::unsetInaccessibleFields($dashboards[0]['pages']), null, true
					),
					'owner' => [
						'id' => CWebUser::$data['userid'],
						'name' => CDashboardHelper::getOwnerName(CWebUser::$data['userid'])
					],
					'sharing' => [
						'private' => $dashboards[0]['private'],
						'users' => $dashboards[0]['users'],
						'userGroups' => $dashboards[0]['userGroups']
					],
					'has_related_reports' => false
				];
			}
			else {
				$error = _('No permissions to referred object or it does not exist!');
			}
		}
		else {
			// Getting existing dashboard.
			$dashboardid = $this->hasInput('dashboardid')
				? $this->getInput('dashboardid')
				: CProfile::get('web.dashboard.dashboardid');

			if ($dashboardid === null && CProfile::get('web.dashboard.list_was_opened') != 1) {
				// Get first available dashboard that user has read permissions.
				$dashboards = API::Dashboard()->get([
					'output' => ['dashboardid'],
					'sortfield' => 'name',
					'limit' => 1
				]);

				if ($dashboards) {
					$dashboardid = $dashboards[0]['dashboardid'];
				}
			}

			if ($dashboardid !== null) {
				$dashboards = API::Dashboard()->get([
					'output' => ['dashboardid', 'name', 'userid', 'display_period', 'auto_start'],
					'selectPages' => ['dashboard_pageid', 'name', 'display_period', 'widgets'],
					'dashboardids' => [$dashboardid],
					'preservekeys' => true
				]);

				if ($dashboards) {
					CDashboardHelper::updateEditableFlag($dashboards);

					$dashboard = array_shift($dashboards);
					$dashboard['pages'] = CDashboardHelper::preparePagesForGrid($dashboard['pages'], null, true);
					$dashboard['owner'] = [
						'id' => $dashboard['userid'],
						'name' => CDashboardHelper::getOwnerName($dashboard['userid'])
					];
					$dashboard['has_related_reports'] = (bool) API::Report()->get([
						'output' => [],
						'filter' => ['dashboardid' => $dashboard['dashboardid']],
						'limit' => 1
					]);

					CProfile::update('web.dashboard.dashboardid', $dashboardid, PROFILE_TYPE_ID);
				}
				elseif ($this->hasInput('dashboardid')) {
					$error = _('No permissions to referred object or it does not exist!');
				}
				else {
					// In case if previous dashboard is deleted, show dashboard list.
				}
			}
		}

		return [$dashboard, $error];
	}

	/**
	 * Checks, if any of widgets has checked dynamic field.
	 *
	 * @param array $grid_pages
	 *
	 * @static
	 *
	 * @return bool
	 */
	private static function hasDynamicWidgets($grid_pages) {
		foreach ($grid_pages as $page) {
			foreach ($page['widgets'] as $widget) {
				if (array_key_exists('dynamic', $widget['fields']) && $widget['fields']['dynamic'] == 1) {
					return true;
				}
			}
		}

		return false;
	}
}
