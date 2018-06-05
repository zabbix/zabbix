<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

class CControllerDashbrdWidgetUpdate extends CController {

	private $widgets;

	public function __construct() {
		parent::__construct();

		$this->widgets = [];
	}

	protected function checkInput() {
		$fields = [
			'fullscreen' =>	'in 0,1',
			'dashboardid' => 'db dashboard.dashboardid',
			'userid' => 'db dashboard.userid',
			'name' => 'db dashboard.name|not_empty',
			'widgets' => 'array',
			'sharing' => 'array'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			/*
			 * @var array  $sharing
			 * @var int    $sharing['private']
			 * @var array  $sharing['users']
			 * @var array  $sharing['users'][]['userid']
			 * @var array  $sharing['users'][]['permission']
			 * @var array  $sharing['userGroups']
			 * @var array  $sharing['userGroups'][]['usrgrpid']
			 * @var array  $sharing['userGroups'][]['permission']
			 */
			$sharing = $this->getInput('sharing', []);

			if ($sharing) {
				if (!array_key_exists('private', $sharing)) {
					error(_s('Invalid parameter "%1$s": %2$s.', 'sharing',
						_s('the parameter "%1$s" is missing', 'private')
					));
					$ret = false;
				}

				if (array_key_exists('users', $sharing) && $sharing['users']) {
					foreach ($sharing['users'] as $index => $user) {
						if (!array_key_exists('userid', $user)) {
							error(_s('Invalid parameter "%1$s": %2$s.', 'sharing[users]['.$index.']',
								_s('the parameter "%1$s" is missing', 'userid')
							));
							$ret = false;
						}

						if (!array_key_exists('permission', $user)) {
							error(_s('Invalid parameter "%1$s": %2$s.', 'sharing[users]['.$index.']',
								_s('the parameter "%1$s" is missing', 'permission')
							));
							$ret = false;
						}
					}
				}

				if (array_key_exists('userGroups', $sharing) && $sharing['userGroups']) {
					foreach ($sharing['userGroups'] as $index => $usergrp) {
						if (!array_key_exists('usrgrpid', $usergrp)) {
							error(_s('Invalid parameter "%1$s": %2$s.', 'sharing[userGroups]['.$index.']',
								_s('the parameter "%1$s" is missing', 'usrgrpid')
							));
							$ret = false;
						}

						if (!array_key_exists('permission', $usergrp)) {
							error(_s('Invalid parameter "%1$s": %2$s.', 'sharing[userGroups]['.$index.']',
								_s('the parameter "%1$s" is missing', 'permission')
							));
							$ret = false;
						}
					}
				}
			}

			/*
			 * @var array  $widgets
			 * @var string $widget[]['widgetid']        (optional)
			 * @var array  $widget[]['pos']             (optional)
			 * @var int    $widget[]['pos']['x']
			 * @var int    $widget[]['pos']['y']
			 * @var int    $widget[]['pos']['width']
			 * @var int    $widget[]['pos']['height']
			 * @var string $widget[]['type']
			 * @var string $widget[]['name']
			 * @var string $widget[]['fields']          (optional) JSON object
			 */
			foreach ($this->getInput('widgets', []) as $index => $widget) {
				if (!array_key_exists('pos', $widget)) {
					error(_s('Invalid parameter "%1$s": %2$s.', 'widgets['.$index.']',
						_s('the parameter "%1$s" is missing', 'pos')
					));
					$ret = false;
				}
				else {
					foreach (['x', 'y', 'width', 'height'] as $field) {
						if (!array_key_exists($field, $widget['pos'])) {
							error(_s('Invalid parameter "%1$s": %2$s.', 'widgets['.$index.'][pos]',
								_s('the parameter "%1$s" is missing', $field)
							));
							$ret = false;
						}
					}
				}

				if (!array_key_exists('type', $widget)) {
					error(_s('Invalid parameter "%1$s": %2$s.', 'widgets['.$index.']',
						_s('the parameter "%1$s" is missing', 'type')
					));
					$ret = false;
					break;
				}

				if (!array_key_exists('name', $widget)) {
					error(_s('Invalid parameter "%1$s": %2$s.', 'widgets['.$index.']',
						_s('the parameter "%1$s" is missing', 'name')
					));
					$ret = false;
				}

				$widget['fields'] = array_key_exists('fields', $widget) ? $widget['fields'] : '{}';
				$widget['form'] = CWidgetConfig::getForm($widget['type'], $widget['fields']);
				unset($widget['fields']);

				if ($errors = $widget['form']->validate()) {
					$widget_name = (array_key_exists('name', $widget) && $widget['name'] === '')
						? CWidgetConfig::getKnownWidgetTypes()[$widget['type']]
						: $widget['name'];

					error_group(['header' => _s('Cannot save widget "%1$s".', $widget_name), 'msgs' => $errors]);

					$ret = false;
				}

				$this->widgets[] = $widget;
			}
		}

		if (!$ret) {
			$output = [];
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}
			$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson($output)]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
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

		$sharing = $this->getInput('sharing', []);

		if ($sharing) {
			if (array_key_exists('private', $sharing)) {
				$dashboard['private'] = $sharing['private'];
			}

			if (array_key_exists('users', $sharing)) {
				$dashboard['users'] = $sharing['users'];
			}

			if (array_key_exists('userGroups', $sharing)) {
				$dashboard['userGroups'] = $sharing['userGroups'];
			}
		}

		foreach ($this->widgets as $widget) {
			$upd_widget = [];
			if (array_key_exists('widgetid', $widget) // widgetid exist during clone action also
					&& array_key_exists('dashboardid', $dashboard)) {
				$upd_widget['widgetid'] = $widget['widgetid'];
			}

			$upd_widget += [
				'x' => $widget['pos']['x'],
				'y' => $widget['pos']['y'],
				'width' => $widget['pos']['width'],
				'height' => $widget['pos']['height'],
				'type' => $widget['type'],
				'name' => $widget['name'],
				'fields' => $widget['form']->fieldsToApi(),
			];

			$dashboard['widgets'][] = $upd_widget;
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
				->setArgument('fullscreen', $this->getInput('fullscreen', '0') ? '1' : null)
				->getUrl();
			CSession::setValue('messageOk', $message);
		}
		else {
			if (!hasErrorMesssages()) {
				error($error_msg);
			}
		}

		if (($messages = getMessages()) !== null) {
			$data['errors'] = $messages->toString();
		}

		$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson($data)]));
	}
}
