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


class CControllerScheduledReportDisable extends CController {

	protected function checkInput() {
		$fields = [
			'reportids' => 'required|array_db report.reportid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
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

	protected function doAction() {
		$reports = [];

		foreach ($this->getInput('reportids') as $reportid) {
			$reports[] = [
				'reportid' => $reportid,
				'status' => ZBX_REPORT_STATUS_DISABLED
			];
		}

		$result = API::Report()->update($reports);

		$response = new CControllerResponseRedirect(
			(new CUrl('zabbix.php'))
				->setArgument('action', 'scheduledreport.list')
				->setArgument('page', CPagerHelper::loadPage('scheduledreport.list', null))
		);

		$updated = count($reports);

		if ($result) {
			$response->setFormData(['uncheck' => '1']);
			CMessageHelper::setSuccessTitle(_n('Scheduled report disabled', 'Scheduled reports disabled', $updated));
		}
		else {
			CMessageHelper::setErrorTitle(
				_n('Cannot disable scheduled report', 'Cannot disable scheduled reports', $updated)
			);
		}

		$this->setResponse($response);
	}
}
