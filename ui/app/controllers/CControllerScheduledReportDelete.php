<?php declare(strict_types = 0);
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


class CControllerScheduledReportDelete extends CController {

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
		$reportids = $this->getInput('reportids');

		$result = API::Report()->delete($reportids);

		$response = new CControllerResponseRedirect(
			(new CUrl('zabbix.php'))
				->setArgument('action', 'scheduledreport.list')
				->setArgument('page', CPagerHelper::loadPage('scheduledreport.list', null))
		);

		$deleted = count($reportids);

		if ($result) {
			$response->setFormData(['uncheck' => '1']);
			CMessageHelper::setSuccessTitle(_n('Scheduled report deleted', 'Scheduled reports deleted', $deleted));
		}
		else {
			CMessageHelper::setErrorTitle(
				_n('Cannot delete scheduled report', 'Cannot delete scheduled reports', $deleted)
			);
		}

		$this->setResponse($response);
	}
}
