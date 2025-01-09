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


class CControllerTemplateDashboardUpdate extends CController {

	private ?array $db_dashboard = null;

	private ?array $dashboard_pages = null;

	protected function init() {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput() {
		$fields = [
			'templateid' =>		'required|db dashboard.templateid',
			'dashboardid' =>	'db dashboard.dashboardid',
			'name' =>			'required|db dashboard.name|not_empty',
			'display_period' =>	'required|db dashboard.display_period|in '.implode(',', DASHBOARD_DISPLAY_PERIODS),
			'auto_start' =>		'required|db dashboard.auto_start|in 0,1',
			'pages' =>			'array'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			[
				'dashboard_pages' => $this->dashboard_pages,
				'errors' => $errors
			] = CDashboardHelper::validateDashboardPages($this->getInput('pages', []), $this->getInput('templateid'));

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
		if ($this->getUserType() < USER_TYPE_ZABBIX_ADMIN) {
			return false;
		}

		if ($this->hasInput('dashboardid')) {
			$db_dashboards = API::TemplateDashboard()->get([
				'output' => ['dashboardid'],
				'selectPages' => ['widgets'],
				'dashboardids' => $this->getInput('dashboardid'),
				'templateids' => $this->getInput('templateid'),
				'editable' => true
			]);

			if (!$db_dashboards) {
				return false;
			}

			$this->db_dashboard = $db_dashboards[0];

			return true;
		}

		return isWritableHostTemplates([$this->getInput('templateid')]);
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
				'display_period' => $this->getInput('display_period'),
				'auto_start' => $this->getInput('auto_start'),
				'pages' => []
			];

			if ($this->db_dashboard !== null) {
				$save_dashboard['dashboardid'] = $this->db_dashboard['dashboardid'];
			}
			else {
				$save_dashboard['templateid'] = $this->getInput('templateid');
			}

			foreach ($this->dashboard_pages as $dashboard_page) {
				$save_dashboard_page = [
					'name' => $dashboard_page['name'],
					'display_period' => $dashboard_page['display_period'],
					'widgets' => []
				];

				if (array_key_exists('dashboard_pageid', $dashboard_page)) {
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

					if (array_key_exists('widgetid', $widget)) {
						$save_widget['widgetid'] = $widget['widgetid'];
					}

					$save_dashboard_page['widgets'][] = $save_widget;
				}

				$save_dashboard['pages'][] = $save_dashboard_page;
			}

			$result = $this->db_dashboard !== null
				? API::TemplateDashboard()->update($save_dashboard)
				: API::TemplateDashboard()->create($save_dashboard);

			if (!$result) {
				throw new InvalidArgumentException();
			}

			$output['success']['title'] = $this->db_dashboard !== null
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
}
