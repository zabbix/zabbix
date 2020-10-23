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


class CControllerDashboardUpdate extends CController {

	private $widgets;

	protected function checkInput() {
		$fields = [
			'dashboardid' => 'db dashboard.dashboardid',
			'userid' => 'required|db dashboard.userid',
			'name' => 'required|db dashboard.name|not_empty',
			'widgets' => 'array',
			'sharing' => 'array'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$sharing_errors = $this->validateSharing();
			[
				'widgets' => $this->widgets,
				'errors' => $widgets_errors
			] = CDashboardHelper::validateWidgets($this->getInput('widgets', []), null);

			$errors = array_merge($sharing_errors, $widgets_errors);

			foreach ($errors as $error) {
				error($error);
			}

			$ret = !$errors;
		}

		if (!$ret) {
			$this->setResponse(new CControllerResponseData([
				'main_block' => json_encode(['errors' => getMessages()->toString()])
			]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_MONITORING_DASHBOARD)
				&& $this->checkAccess(CRoleHelper::ACTIONS_EDIT_DASHBOARDS);
	}

	protected function doAction() {
		$data = [];

		$dashboard = [
			'name' => $this->getInput('name'),
			'userid' => $this->getInput('userid', 0),
			'widgets' => []
		];

		if ($this->hasInput('dashboardid')) {
			$dashboard['dashboardid'] = $this->getInput('dashboardid');
		}

		if ($this->hasInput('sharing')) {
			$sharing = $this->getInput('sharing');

			$dashboard['private'] = $sharing['private'];

			if (array_key_exists('users', $sharing)) {
				$dashboard['users'] = $sharing['users'];
			}

			if (array_key_exists('userGroups', $sharing)) {
				$dashboard['userGroups'] = $sharing['userGroups'];
			}
		}

		foreach ($this->widgets as $widget) {
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

			if (array_key_exists('widgetid', $widget) // widgetid exist during clone action also
					&& array_key_exists('dashboardid', $dashboard)) {
				$save_widget['widgetid'] = $widget['widgetid'];
			}

			$dashboard['widgets'][] = $save_widget;
		}

		if (array_key_exists('dashboardid', $dashboard)) {
			$result = API::Dashboard()->update($dashboard);
			$message = _('Dashboard updated');
			$error_msg =  _('Failed to update dashboard');
		}
		else {
			$result = API::Dashboard()->create($dashboard);
			$message = _('Dashboard created');
			$error_msg = _('Failed to create dashboard');
		}

		if ($result) {
			$data['redirect'] = (new CUrl('zabbix.php'))
				->setArgument('action', 'dashboard.view')
				->setArgument('dashboardid', $result['dashboardids'][0])
				->getUrl();

			$data['system-message-ok'] = $message;
		}
		else {
			if (!hasErrorMesssages()) {
				error($error_msg);
			}
		}

		if (($messages = getMessages()) !== null) {
			$data['errors'] = $messages->toString();
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
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
