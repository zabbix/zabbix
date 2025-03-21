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


class CControllerDashboardView extends CController {

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput() {
		$fields = [
			'dashboardid' =>		'db dashboard.dashboardid',
			'hostid' =>				'db hosts.hostid',
			'new' =>				'in 1',
			'cancel' =>				'in 1',
			'clone' =>				'in 1',
			'from' =>				'range_time',
			'to' =>					'range_time',
			'slideshow' =>			'in 1'
		];

		$ret = $this->validateInput($fields) && $this->validateTimeSelectorPeriod();

		if ($ret && $this->hasInput('clone')) {
			$validator = new CNewValidator($this->getInputAll(), [
				'dashboardid' => 'required'
			]);

			foreach ($validator->getAllErrors() as $error) {
				info($error);
			}

			if ($validator->isErrorFatal() || $validator->isError()) {
				$ret = false;
			}
		}

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (!$this->checkAccess(CRoleHelper::UI_MONITORING_DASHBOARD)) {
			return false;
		}

		if ($this->hasInput('new') || $this->hasInput('clone')) {
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
		$widget_defaults = APP::ModuleManager()->getWidgetsDefaults();

		[$dashboard, $stats, $error] = $this->getDashboard($widget_defaults);

		if ($error !== null) {
			$response = new CControllerResponseData(['error' => $error]);
			$response->setTitle(_('Dashboard'));
			$this->setResponse($response);

			return;
		}

		if ($dashboard === null) {
			$this->setResponse(new CControllerResponseRedirect(
				(new CUrl('zabbix.php'))
					->setArgument('action', 'dashboard.list')
					->setArgument('page', $this->hasInput('cancel')
						? CPagerHelper::loadPage('dashboard.list', null)
						: null
					)
			));

			return;
		}

		if ($this->hasInput('slideshow')) {
			$dashboard['auto_start'] = '1';
		}

		$dashboard['can_edit_dashboards'] = $this->checkAccess(CRoleHelper::ACTIONS_EDIT_DASHBOARDS);

		$hostid = $this->getInput('hostid', CProfile::get('web.dashboard.hostid', 0));

		$hosts = $hostid !== 0
			? CArrayHelper::renameObjectsKeys(API::Host()->get([
				'output' => ['hostid', 'name'],
				'hostids' => [$hostid]
			]), ['hostid' => 'id'])
			: [];

		$dashboard_host = $hosts ? $hosts[0] : null;

		$time_selector_options = [
			'profileIdx' => 'web.dashboard.filter',
			'profileIdx2' => $dashboard['dashboardid'] ?? 0,
			'from' => $this->hasInput('from') ? $this->getInput('from') : null,
			'to' => $this->hasInput('to') ? $this->getInput('to') : null
		];

		updateTimeSelectorPeriod($time_selector_options);

		$dashboard_time_period = getTimeSelectorPeriod($time_selector_options);

		$data = [
			// The dashboard property shall only contain data used by the JavaScript framework.
			'dashboard' => $dashboard,
			'widget_defaults' => $widget_defaults,
			'widget_last_type' => CDashboardHelper::getWidgetLastType(),
			'configuration_hash' => $stats['configuration_hash'],
			'can_view_reports' => $this->checkAccess(CRoleHelper::UI_REPORTS_SCHEDULED_REPORTS),
			'can_create_reports' => $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SCHEDULED_REPORTS),
			'has_related_reports' => $stats['has_related_reports'],
			'broadcast_requirements' => $stats['broadcast_requirements'],
			'dashboard_host' => $dashboard_host,
			'dashboard_time_period' => $dashboard_time_period,
			'clone' => $this->hasInput('clone'),
			'active_tab' => CProfile::get('web.dashboard.filter.active', 1)
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Dashboard'));
		$this->setResponse($response);
	}

	/**
	 * Get dashboard data from API.
	 */
	private function getDashboard(array $widget_defaults): array {
		// The dashboard property shall only contain data used by the JavaScript framework.
		$dashboard = null;

		$stats = [
			'has_related_reports' => false,
			'broadcast_requirements' => [],
			'configuration_hash' => null
		];

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
				]
			];

			$stats['configuration_hash'] = CDashboardHelper::getConfigurationHash($dashboard, $widget_defaults);
		}
		elseif ($this->hasInput('clone')) {
			$db_dashboards = API::Dashboard()->get([
				'output' => ['dashboardid', 'name', 'private', 'display_period', 'auto_start'],
				'selectPages' => ['dashboard_pageid', 'name', 'display_period', 'widgets'],
				'selectUsers' => ['userid', 'permission'],
				'selectUserGroups' => ['usrgrpid', 'permission'],
				'dashboardids' => $this->getInput('dashboardid')
			]);

			if ($db_dashboards) {
				$dashboard = $db_dashboards[0];

				$stats['configuration_hash'] = CDashboardHelper::getConfigurationHash($dashboard, $widget_defaults);

				$pages_raw = CDashboardHelper::unsetInaccessibleFields($dashboard['pages']);
				$pages_prepared = CDashboardHelper::preparePages($pages_raw, null, true);

				$dashboard = [
					'dashboardid' => $db_dashboards[0]['dashboardid'],
					'name' => $db_dashboards[0]['name'],
					'display_period' => $db_dashboards[0]['display_period'],
					'auto_start' => $db_dashboards[0]['auto_start'],
					'editable' => true,
					'pages' => $pages_prepared,
					'owner' => [
						'id' => CWebUser::$data['userid'],
						'name' => CDashboardHelper::getOwnerName(CWebUser::$data['userid'])
					],
					'sharing' => [
						'private' => $db_dashboards[0]['private'],
						'users' => $db_dashboards[0]['users'],
						'userGroups' => $db_dashboards[0]['userGroups']
					]
				];

				$stats['broadcast_requirements'] = CDashboardHelper::getBroadcastRequirements($pages_prepared);
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
				$db_dashboards = API::Dashboard()->get([
					'output' => ['dashboardid'],
					'sortfield' => 'name',
					'limit' => 1
				]);

				if ($db_dashboards) {
					$dashboardid = $db_dashboards[0]['dashboardid'];
				}
			}

			if ($dashboardid !== null) {
				$db_dashboards = API::Dashboard()->get([
					'output' => ['dashboardid', 'name', 'userid', 'display_period', 'auto_start'],
					'selectPages' => ['dashboard_pageid', 'name', 'display_period', 'widgets'],
					'dashboardids' => $dashboardid,
					'preservekeys' => true
				]);

				if ($db_dashboards) {
					CDashboardHelper::updateEditableFlag($db_dashboards);

					$dashboard = array_shift($db_dashboards);

					$stats['configuration_hash'] = CDashboardHelper::getConfigurationHash($dashboard, $widget_defaults);

					$pages_raw = $dashboard['pages'];
					$pages_prepared = CDashboardHelper::preparePages($pages_raw, null, true);

					$dashboard['pages'] = $pages_prepared;

					$dashboard['owner'] = [
						'id' => $dashboard['userid'],
						'name' => CDashboardHelper::getOwnerName($dashboard['userid'])
					];

					$stats['has_related_reports'] = (bool) API::Report()->get([
						'output' => [],
						'filter' => ['dashboardid' => $dashboard['dashboardid']],
						'limit' => 1
					]);

					$stats['broadcast_requirements'] = CDashboardHelper::getBroadcastRequirements($pages_prepared);

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

		return [$dashboard, $stats, $error];
	}
}
