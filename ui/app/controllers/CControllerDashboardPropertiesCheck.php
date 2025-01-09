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


class CControllerDashboardPropertiesCheck extends CController {

	protected function init() {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableCsrfValidation();
	}

	protected function checkInput() {
		$fields = [
			'template' =>		'in 1',
			'userid' =>			'db users.userid',
			'name' =>			'required|db dashboard.name|not_empty',
			'display_period' =>	'required|db dashboard.display_period|in '.implode(',', DASHBOARD_DISPLAY_PERIODS),
			'auto_start' =>		'in 1'
		];

		$ret = $this->validateInput($fields);

		if (!$this->hasInput('template') && !$this->hasInput('userid')) {
			error(_s('Field "%1$s" is mandatory.', 'userid'));

			$ret = false;
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
		if ($this->hasInput('template')) {
			return $this->getUserType() >= USER_TYPE_ZABBIX_ADMIN;
		}
		else {
			return $this->checkAccess(CRoleHelper::UI_MONITORING_DASHBOARD)
				&& $this->checkAccess(CRoleHelper::ACTIONS_EDIT_DASHBOARDS);
		}
	}

	protected function doAction() {
		$data = [];

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}
}
