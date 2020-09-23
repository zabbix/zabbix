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


require_once dirname(__FILE__).'/../../include/blocks.inc.php';

class CControllerDashboardView extends CControllerDashboardAbstract {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'dashboardid' => 'db dashboard.dashboardid',
			'source_dashboardid' => 'db dashboard.dashboardid',
			'hostid' => 'db hosts.hostid',
			'new' => 'in 1',
			'cancel' => 'in 1',
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
			$this->setResponse(new CControllerResponseData(['error' => $error]));

			return;
		}

		if ($dashboard === null) {
			$this->setResponse(new CControllerResponseRedirect((new CUrl('zabbix.php'))
				->setArgument('action', 'dashboard.list')
				->setArgument('page', $this->hasInput('cancel') ? CPagerHelper::loadPage('dashboard.list', null) : null)
			));

			return;
		}

		$time_selector_options = [
			'profileIdx' => 'web.dashbrd.filter',
			'profileIdx2' => ($dashboard['dashboardid'] !== null) ? $dashboard['dashboardid'] : 0,
			'from' => $this->hasInput('from') ? $this->getInput('from') : null,
			'to' => $this->hasInput('to') ? $this->getInput('to') : null
		];

		updateTimeSelectorPeriod($time_selector_options);

		$data = [
			'dashboard' => $dashboard,
			'widget_defaults' => CWidgetConfig::getDefaults(CWidgetConfig::CONTEXT_DASHBOARD),
			'time_selector' => self::hasTimeSelector($dashboard['widgets'])
				? getTimeSelectorPeriod($time_selector_options)
				: null,
			'active_tab' => CProfile::get('web.dashbrd.filter.active', 1),
		];

		if (self::hasDynamicWidgets($dashboard['widgets'])) {
			$hostid = $this->getInput('hostid', CProfile::get('web.dashbrd.hostid', 0));

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
				'editable' => true,
				'widgets' => [],
				'owner' => [
					'id' => CWebUser::$data['userid'],
					'name' => self::getOwnerName(CWebUser::$data['userid'])
				]
			];
		}
		elseif ($this->hasInput('source_dashboardid')) {
			// Clone dashboard and show as new.
			$dashboards = API::Dashboard()->get([
				'output' => ['name', 'private'],
				'selectWidgets' => ['widgetid', 'type', 'name', 'view_mode', 'x', 'y', 'width', 'height', 'fields'],
				'selectUsers' => ['userid', 'permission'],
				'selectUserGroups' => ['usrgrpid', 'permission'],
				'dashboardids' => $this->getInput('source_dashboardid')
			]);

			if ($dashboards) {
				$dashboard = [
					'dashboardid' => null,
					'name' => $dashboards[0]['name'],
					'editable' => true,
					'widgets' => self::prepareWidgetsForGrid(self::unsetInaccessibleFields($dashboards[0]['widgets'])),
					'owner' => [
						'id' => CWebUser::$data['userid'],
						'name' => self::getOwnerName(CWebUser::$data['userid'])
					],
					'sharing' => [
						'private' => $dashboards[0]['private'],
						'users' => $dashboards[0]['users'],
						'userGroups' => $dashboards[0]['userGroups']
					]
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
				: CProfile::get('web.dashbrd.dashboardid');

			if ($dashboardid === null && CProfile::get('web.dashbrd.list_was_opened') != 1) {
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
					'output' => ['dashboardid', 'name', 'userid'],
					'selectWidgets' => ['widgetid', 'type', 'name', 'view_mode', 'x', 'y', 'width', 'height', 'fields'],
					'dashboardids' => $dashboardid,
					'preservekeys' => true
				]);

				if ($dashboards) {
					self::updateEditableFlag($dashboards);

					$dashboard = array_shift($dashboards);
					$dashboard['widgets'] = self::prepareWidgetsForGrid($dashboard['widgets']);
					$dashboard['owner'] = [
						'id' => $dashboard['userid'],
						'name' => self::getOwnerName($dashboard['userid'])
					];

					CProfile::update('web.dashbrd.dashboardid', $dashboardid, PROFILE_TYPE_ID);
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
	 * Prepare widgets for dashboard grid.
	 *
	 * @static
	 *
	 * @return array
	 */
	private static function prepareWidgetsForGrid($widgets) {
		$grid_widgets = [];

		if ($widgets) {
			CArrayHelper::sort($widgets, ['y', 'x']);

			$known_widget_types = array_keys(CWidgetConfig::getKnownWidgetTypes(CWidgetConfig::CONTEXT_DASHBOARD));

			foreach ($widgets as $widget) {
				if (!in_array($widget['type'], $known_widget_types)) {
					continue;
				}

				$widgetid = $widget['widgetid'];
				$fields_orig = self::convertWidgetFields($widget['fields']);

				// Transforms corrupted data to default values.
				$widget_form = CWidgetConfig::getForm($widget['type'], json_encode($fields_orig), null);
				$widget_form->validate();
				$fields = $widget_form->getFieldsData();

				$rf_rate = ($fields['rf_rate'] == -1)
					? CWidgetConfig::getDefaultRfRate($widget['type'])
					: $fields['rf_rate'];

				$grid_widgets[] = [
					'widgetid' => $widgetid,
					'type' => $widget['type'],
					'header' => $widget['name'],
					'view_mode' => $widget['view_mode'],
					'pos' => [
						'x' => (int) $widget['x'],
						'y' => (int) $widget['y'],
						'width' => (int) $widget['width'],
						'height' => (int) $widget['height']
					],
					'rf_rate' => (int) CProfile::get('web.dashbrd.widget.rf_rate', $rf_rate, $widgetid),
					'fields' => $fields_orig,
					'configuration' => CWidgetConfig::getConfiguration($widget['type'], $fields, $widget['view_mode'])
				];
			}
		}

		return $grid_widgets;
	}

	/**
	 * Converts fields, received from API to key/value format.
	 *
	 * @param array $fields  fields as received from API
	 *
	 * @static
	 *
	 * @return array
	 */
	private static function convertWidgetFields($fields) {
		$ret = [];
		foreach ($fields as $field) {
			if (array_key_exists($field['name'], $ret)) {
				$ret[$field['name']] = (array) $ret[$field['name']];
				$ret[$field['name']][] = $field['value'];
			}
			else {
				$ret[$field['name']] = $field['value'];
			}
		}

		return $ret;
	}

	/**
	 * Checks, if any of widgets has checked dynamic field.
	 *
	 * @param array $grid_widgets
	 *
	 * @static
	 *
	 * @return bool
	 */
	private static function hasDynamicWidgets($grid_widgets) {
		foreach ($grid_widgets as $widget) {
			if (array_key_exists('dynamic', $widget['fields']) && $widget['fields']['dynamic'] == 1) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks, if any of widgets needs time selector.
	 *
	 * @param array $widgets
	 *
	 * @static
	 *
	 * @return bool
	 */
	private static function hasTimeSelector(array $widgets) {
		foreach ($widgets as $widget) {
			if (CWidgetConfig::usesTimeSelector($widget)) {
				return true;
			}
		}
		return false;
	}
}
