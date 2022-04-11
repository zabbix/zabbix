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


class CControllerDashboardUpdate extends CController {

	private $dashboard_pages;

	protected function init() {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput() {
		$fields = [
			'dashboardid' =>	'db dashboard.dashboardid',
			'name' =>			'required|db dashboard.name|not_empty',
			'userid' =>			'required|db dashboard.userid',
			'display_period' =>	'required|db dashboard.display_period|in '.implode(',', DASHBOARD_DISPLAY_PERIODS),
			'auto_start' =>		'required|db dashboard.auto_start|in 0,1',
			'pages' =>			'array',
			'sharing' =>		'array'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$sharing_errors = $this->validateSharing();
			[
				'dashboard_pages' => $this->dashboard_pages,
				'errors' => $dashboard_pages_errors
			] = CDashboardHelper::validateDashboardPages($this->getInput('pages', []), null);

			$errors = array_merge($sharing_errors, $dashboard_pages_errors);

			foreach ($errors as $error) {
				error($error);
			}

			$ret = !$errors;
		}

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_MONITORING_DASHBOARD)
			&& $this->checkAccess(CRoleHelper::ACTIONS_EDIT_DASHBOARDS);
	}

	protected function doAction() {
		$save_dashboard = [
			'name' => $this->getInput('name'),
			'userid' => $this->getInput('userid', 0),
			'display_period' => $this->getInput('display_period'),
			'auto_start' => $this->getInput('auto_start'),
			'pages' => []
		];

		if ($this->hasInput('dashboardid')) {
			$save_dashboard['dashboardid'] = $this->getInput('dashboardid');
		}

		foreach ($this->dashboard_pages as $dashboard_page) {
			$save_dashboard_page = [
				'name' => $dashboard_page['name'],
				'display_period' => $dashboard_page['display_period'],
				'widgets' => []
			];

			// Set dashboard_pageid if it exists and not cloning the dashboard.
			if (array_key_exists('dashboardid', $save_dashboard)
					&& array_key_exists('dashboard_pageid', $dashboard_page)) {
				$save_dashboard_page['dashboard_pageid'] = $dashboard_page['dashboard_pageid'];
			}

			foreach ($dashboard_page['widgets'] as $widget) {
				$save_widget = [
					'x' => $widget['pos']['x'],
					'y' => $widget['pos']['y'],
					'width' => $widget['pos']['width'],
					'height' => $widget['pos']['height'],
					'type' => $widget['type'],
					'name' => $widget['name'],
					'view_mode' => $widget['view_mode'],
					'fields' => $widget['form']->fieldsToApi()
				];

				// Set widgetid if it exists and not cloning the dashboard.
				if (array_key_exists('dashboardid', $save_dashboard) && array_key_exists('widgetid', $widget)) {
					$save_widget['widgetid'] = $widget['widgetid'];
				}

				$save_dashboard_page['widgets'][] = $save_widget;
			}

			$save_dashboard['pages'][] = $save_dashboard_page;
		}

		if ($this->hasInput('sharing')) {
			$sharing = $this->getInput('sharing');

			$save_dashboard['private'] = $sharing['private'];

			if (array_key_exists('users', $sharing)) {
				$save_dashboard['users'] = $sharing['users'];
			}

			if (array_key_exists('userGroups', $sharing)) {
				$save_dashboard['userGroups'] = $sharing['userGroups'];
			}
		}

		if (array_key_exists('dashboardid', $save_dashboard)) {
			$result = API::Dashboard()->update($save_dashboard);

			$success_title = _('Dashboard updated');
			$error_title = _('Failed to update dashboard');
		}
		else {
			$result = API::Dashboard()->create($save_dashboard);

			$success_title = _('Dashboard created');
			$error_title = _('Failed to create dashboard');
		}

		$output = [];

		if ($result) {
			$output['success']['title'] = $success_title;

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}

			$output['dashboardid'] = $result['dashboardids'][0];
		}
		else {
			$output['error'] = [
				'title' => $error_title,
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}

	/**
	 * Validate sharing input parameters.
	 *
	 * @var array  $sharing
	 * @var int    $sharing['private']
	 * @var array  $sharing['users']
	 * @var array  $sharing['users'][]['userid']
	 * @var array  $sharing['users'][]['permission']
	 * @var array  $sharing['userGroups']
	 * @var array  $sharing['userGroups'][]['usrgrpid']
	 * @var array  $sharing['userGroups'][]['permission']
	 *
	 * @return array  Validation errors.
	 */
	protected function validateSharing(): array {
		$errors = [];

		if ($this->hasInput('sharing')) {
			$sharing = $this->getInput('sharing');

			if (!is_array($sharing) || !array_key_exists('private', $sharing)) {
				$errors[] = _s('Invalid parameter "%1$s": %2$s.', 'sharing',
					_s('the parameter "%1$s" is missing', 'private')
				);
			}

			if (array_key_exists('users', $sharing) && is_array($sharing['users'])) {
				foreach ($sharing['users'] as $index => $user) {
					foreach (['userid', 'permission'] as $field) {
						if (!array_key_exists($field, $user)) {
							$errors[] = _s('Invalid parameter "%1$s": %2$s.', 'sharing[users]['.$index.']',
								_s('the parameter "%1$s" is missing', $field)
							);
						}
					}
				}
			}

			if (array_key_exists('userGroups', $sharing) && is_array($sharing['userGroups'])) {
				foreach ($sharing['userGroups'] as $index => $user_group) {
					foreach (['usrgrpid', 'permission'] as $field) {
						if (!array_key_exists($field, $user_group)) {
							$errors[] = _s('Invalid parameter "%1$s": %2$s.', 'sharing[userGroups]['.$index.']',
								_s('the parameter "%1$s" is missing', $field)
							);
						}
					}
				}
			}
		}

		return $errors;
	}
}
