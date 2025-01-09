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


class CControllerScheduledReportCreate extends CController {

	protected function checkInput() {
		$fields = [
			'userid' =>			'required|db report.userid',
			'name' =>			'required|db report.name|not_empty',
			'dashboardid' =>	'required|db report.dashboardid',
			'period' =>			'db report.period|in '.implode(',', [ZBX_REPORT_PERIOD_DAY, ZBX_REPORT_PERIOD_WEEK, ZBX_REPORT_PERIOD_MONTH, ZBX_REPORT_PERIOD_YEAR]),
			'cycle' =>			'db report.cycle|in '.implode(',', [ZBX_REPORT_CYCLE_DAILY, ZBX_REPORT_CYCLE_WEEKLY, ZBX_REPORT_CYCLE_MONTHLY, ZBX_REPORT_CYCLE_YEARLY]),
			'weekdays' =>		'array',
			'hours' =>			'int32|ge 0|le 23',
			'minutes' =>		'int32|ge 0|le 59',
			'active_since' =>	'string',
			'active_till' =>	'string',
			'subject' =>		'string',
			'message' =>		'string',
			'subscriptions' =>	'array',
			'description' =>	'db report.description',
			'status' =>			'db report.status|in '.ZBX_REPORT_STATUS_DISABLED.','.ZBX_REPORT_STATUS_ENABLED,
			'form_refresh' =>	'int32'
		];

		$ret = $this->validateInput($fields);
		$error = $this->getValidationError();

		if ($ret && !$this->validateWeekdays()) {
			$error = self::VALIDATION_ERROR;
			$ret = false;
		}

		if (!$ret) {
			switch ($error) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect(
						(new CUrl('zabbix.php'))->setArgument('action', 'scheduledreport.edit')
					);
					$response->setFormData($this->getInputAll());
					CMessageHelper::setErrorTitle(_('Cannot create scheduled report'));
					$this->setResponse($response);
					break;

				case self::VALIDATION_FATAL_ERROR:
					$this->setResponse(new CControllerResponseFatal());
					break;
			}
		}

		return $ret;
	}

	/**
	 * Validate days of the week.
	 *
	 * @return bool
	 */
	private function validateWeekdays(): bool {
		$cycle = $this->getInput('cycle', ZBX_REPORT_CYCLE_DAILY);
		$weekdays = array_sum($this->getInput('weekdays', []));

		if ($cycle == ZBX_REPORT_CYCLE_WEEKLY && $weekdays == 0) {
			error(_s('Incorrect value for field "%1$s": %2$s.', _('Repeat on'),
				_('at least one day of the week must be selected'))
			);

			return false;
		}

		return true;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_REPORTS_SCHEDULED_REPORTS)
			&& $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SCHEDULED_REPORTS);
	}

	protected function doAction() {
		$report = [];

		$this->getInputs($report, ['userid', 'name', 'dashboardid', 'period', 'cycle', 'subject', 'message',
			'description', 'status'
		]);

		if ($report['cycle'] == ZBX_REPORT_CYCLE_WEEKLY) {
			$report['weekdays'] = array_sum($this->getInput('weekdays', []));
		}

		$report['start_time'] = ($this->getInput('hours') * SEC_PER_HOUR) + ($this->getInput('minutes') * SEC_PER_MIN);

		if ($this->getInput('active_since') !== '') {
			$report['active_since'] = $this->getInput('active_since');
		}
		if ($this->getInput('active_till') !== '') {
			$report['active_till'] = $this->getInput('active_till');
		}

		$report['users'] = [];
		$report['user_groups'] = [];

		foreach ($this->getInput('subscriptions', []) as $subscription) {
			if ($subscription['recipient_type'] == ZBX_REPORT_RECIPIENT_TYPE_USER) {
				$report['users'][] = [
					'userid' => $subscription['recipientid'],
					'exclude' => $subscription['exclude'],
					'access_userid' => $subscription['creatorid']
				];
			}
			else {
				$report['user_groups'][] = [
					'usrgrpid' => $subscription['recipientid'],
					'access_userid' => $subscription['creatorid']
				];
			}
		}

		$result = API::Report()->create($report);

		if ($result) {
			$response = new CControllerResponseRedirect(
				(new CUrl('zabbix.php'))
					->setArgument('action', 'scheduledreport.list')
					->setArgument('page', CPagerHelper::loadPage('scheduledreport.list', null))
			);
			$response->setFormData(['uncheck' => '1']);
			CMessageHelper::setSuccessTitle(_('Scheduled report added'));
		}
		else {
			$response = new CControllerResponseRedirect(
				(new CUrl('zabbix.php'))->setArgument('action', 'scheduledreport.edit')
			);
			$response->setFormData($this->getInputAll());
			CMessageHelper::setErrorTitle(_('Cannot add scheduled report'));
		}

		$this->setResponse($response);
	}
}
