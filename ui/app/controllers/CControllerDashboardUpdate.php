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


class CControllerDashboardUpdate extends CController {

	private ?array $db_dashboard = null;

	private ?array $dashboard_pages = null;

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
			'sharing' =>		'array',
			'clone' => 			'in 1'
		];

		$ret = $this->validateInput($fields);

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

		if ($ret) {
			$sharing_errors = $this->validateSharing();
			[
				'dashboard_pages' => $this->dashboard_pages,
				'errors' => $dashboard_pages_errors
			] = CDashboardHelper::validateDashboardPages($this->getInput('pages', []));

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
		if (!$this->checkAccess(CRoleHelper::UI_MONITORING_DASHBOARD)
				|| !$this->checkAccess(CRoleHelper::ACTIONS_EDIT_DASHBOARDS)) {
			return false;
		}

		if ($this->hasInput('dashboardid')) {
			$db_dashboards = API::Dashboard()->get([
				'output' => ['dashboardid'],
				'selectPages' => ['widgets'],
				'dashboardids' => $this->getInput('dashboardid')
			]);

			if (!$db_dashboards) {
				return false;
			}

			$this->db_dashboard = $db_dashboards[0];
		}

		return true;
	}

	protected function doAction() {
		$output = [];

		try {
			$db_widgets = [];

			if ($this->db_dashboard !== null) {
				foreach ($this->db_dashboard['pages'] as $db_dashboard_page) {
					foreach ($db_dashboard_page['widgets'] as $db_widget) {
						$db_widgets[$db_widget['widgetid']] = $db_widget;
					}
				}
			}

			$save_dashboard = [
				'name' => $this->getInput('name'),
				'userid' => $this->getInput('userid', 0),
				'display_period' => $this->getInput('display_period'),
				'auto_start' => $this->getInput('auto_start'),
				'pages' => []
			];

			if ($this->db_dashboard !== null && !$this->hasInput('clone')) {
				$save_dashboard['dashboardid'] = $this->db_dashboard['dashboardid'];
			}

			foreach ($this->dashboard_pages as $dashboard_page) {
				$save_dashboard_page = [
					'name' => $dashboard_page['name'],
					'display_period' => $dashboard_page['display_period'],
					'widgets' => []
				];

				if (array_key_exists('dashboard_pageid', $dashboard_page) && !$this->hasInput('clone')) {
					$save_dashboard_page['dashboard_pageid'] = $dashboard_page['dashboard_pageid'];
				}

				foreach ($dashboard_page['widgets'] as $widget) {
					$save_widget = [
						'x' => $widget['pos']['x'],
						'y' => $widget['pos']['y'],
						'width' => $widget['pos']['width'],
						'height' => $widget['pos']['height']
					];

					if ($widget['type'] !== ZBX_WIDGET_INACCESSIBLE) {
						$save_widget += [
							'type' => $widget['type'],
							'name' => $widget['name'],
							'view_mode' => $widget['view_mode'],
							'fields' => $widget['form']->fieldsToApi()
						];
					}
					else {
						if (!array_key_exists('widgetid', $widget)
								|| !array_key_exists($widget['widgetid'], $db_widgets)) {
							error(_('No permissions to referred object or it does not exist!'));

							throw new InvalidArgumentException();
						}

						$db_widget = $db_widgets[$widget['widgetid']];

						$save_widget += [
							'type' => $db_widget['type'],
							'name' => $db_widget['name'],
							'view_mode' => $db_widget['view_mode'],
							'fields' => $db_widget['fields']
						];
					}

					if (array_key_exists('widgetid', $widget) && !$this->hasInput('clone')) {
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

			$result = $this->db_dashboard !== null && !$this->hasInput('clone')
				? API::Dashboard()->update($save_dashboard)
				: API::Dashboard()->create($save_dashboard);

			if (!$result) {
				throw new InvalidArgumentException();
			}

			$output['success']['title'] = $this->db_dashboard !== null && !$this->hasInput('clone')
				? _('Dashboard updated')
				: _('Dashboard created');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}

			$output['dashboardid'] = $result['dashboardids'][0];
		}
		catch (InvalidArgumentException $e) {
			$output['error'] = [
				'title' => $this->db_dashboard !== null && !$this->hasInput('clone')
					? _('Failed to update dashboard')
					: _('Failed to create dashboard'),
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
