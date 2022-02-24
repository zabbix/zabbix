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


class CControllerTemplateDashboardUpdate extends CController {

	private $dashboard_pages;

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
			$this->setResponse(new CControllerResponseData([
				'main_block' => json_encode(['errors' => getMessages()->toString()])
			]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		if ($this->getUserType() < USER_TYPE_ZABBIX_ADMIN) {
			return false;
		}

		if ($this->hasInput('dashboardid')) {
			return (bool) API::TemplateDashboard()->get([
				'output' => [],
				'dashboardids' => [$this->getInput('dashboardid')],
				'templateids' => [$this->getInput('templateid')],
				'editable' => true
			]);
		}
		else {
			return isWritableHostTemplates((array) $this->getInput('templateid'));
		}
	}

	protected function doAction() {
		$data = [];

		$save_dashboard = [
			'name' => $this->getInput('name'),
			'display_period' => $this->getInput('display_period'),
			'auto_start' => $this->getInput('auto_start'),
			'pages' => []
		];

		if ($this->hasInput('dashboardid')) {
			$save_dashboard['dashboardid'] = $this->getInput('dashboardid');
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
					'height' => $widget['pos']['height'],
					'type' => $widget['type'],
					'name' => $widget['name'],
					'view_mode' => $widget['view_mode'],
					'fields' => $widget['form']->fieldsToApi()
				];

				if (array_key_exists('widgetid', $widget)) {
					$save_widget['widgetid'] = $widget['widgetid'];
				}

				$save_dashboard_page['widgets'][] = $save_widget;
			}

			$save_dashboard['pages'][] = $save_dashboard_page;
		}

		if ($this->hasInput('dashboardid')) {
			$result = API::TemplateDashboard()->update($save_dashboard);
			$message = _('Dashboard updated');
			$error_msg =  _('Failed to update dashboard');
		}
		else {
			$result = API::TemplateDashboard()->create($save_dashboard);
			$message = _('Dashboard created');
			$error_msg = _('Failed to create dashboard');
		}

		if ($result) {
			$data['system-message-ok'] = $message;
		}
		else {
			if (!hasErrorMessages()) {
				error($error_msg);
			}
		}

		if (($messages = getMessages()) !== null) {
			$data['errors'] = $messages->toString();
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}
}
