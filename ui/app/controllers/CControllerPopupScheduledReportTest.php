<?php declare(strict_types = 1);
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


class CControllerPopupScheduledReportTest extends CController {

	protected function checkInput() {
		$fields = [
			'name' =>			'required|db report.name|not_empty',
			'dashboardid' =>	'required|db report.dashboardid',
			'period' =>			'required|db report.period|in '.implode(',', [ZBX_REPORT_PERIOD_DAY, ZBX_REPORT_PERIOD_WEEK, ZBX_REPORT_PERIOD_MONTH, ZBX_REPORT_PERIOD_YEAR]),
			'now' =>			'required|int32',
			'subject' =>		'string',
			'message' =>		'string'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$output = [];
			if (($messages = getMessages(false, null, false)) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_REPORTS_SCHEDULED_REPORTS)
			&& $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SCHEDULED_REPORTS);
	}

	protected function doAction() {
		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		$params = [
			'name' => $this->getInput('name'),
			'userid' => CWebUser::$data['userid'],
			'dashboardid' => $this->getInput('dashboardid'),
			'period' => $this->getInput('period'),
			'now' => $this->getInput('now'),
			'params' => [
				'subject' => $this->getInput('subject', ''),
				'body' => $this->getInput('message', '')
			]
		];

		$server = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT,
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::CONNECT_TIMEOUT)),
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::SCHEDULED_REPORT_TEST_TIMEOUT)),
			ZBX_SOCKET_BYTES_LIMIT
		);

		$result = $server->testReport($params, CSessionHelper::getId());

		$data = [
			'title' => _('Test report generating'),
			'messages' => null,
			'results' => [],
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		if ($result) {
			$msg_title = null;

			if (is_array($result) && array_key_exists('recipients', $result)) {
				$data['recipients'] = $result['recipients'];
			}

			info(_('Report generating test successful.'));
		}
		else {
			$msg_title = _('Report generating test failed.');
			error($server->getError());
		}

		if (($messages = getMessages((bool) $result, $msg_title)) !== null) {
			$data['messages'] = $messages;
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
