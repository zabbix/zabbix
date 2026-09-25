<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CControllerScheduledReportDelete extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'reportids' => 'required|array_db report.reportid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_REPORTS_SCHEDULED_REPORTS)
				|| !$this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SCHEDULED_REPORTS)) {
			return false;
		}

		$report_count = API::Report()->get([
			'countOutput' => true,
			'reportids' => $this->getInput('reportids')
		]);

		return ($report_count == count($this->getInput('reportids')));
	}

	protected function doAction(): void {
		$reportids = $this->getInput('reportids');

		$result = API::Report()->delete($reportids);

		if ($result) {
			$output['success']['title'] = _n('Scheduled report deleted', 'Scheduled reports deleted', count($reportids));

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _n('Cannot delete scheduled report', 'Cannot delete scheduled reports', count($reportids)),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];

			$reports = API::Report()->get([
				'output' => [],
				'reportids' => $reportids,
				'preservekeys' => true
			]);

			$output['keepids'] = array_keys($reports);
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
