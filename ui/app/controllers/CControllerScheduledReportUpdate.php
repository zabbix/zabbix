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


class CControllerScheduledReportUpdate extends CController {

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	public static function getValidationRules(): array {
		$api_uniq = [
			['report.get', ['name' => '{name}'], 'reportid']
		];

		return ['object', 'api_uniq' => $api_uniq, 'fields' => [
			'reportid' => ['db report.reportid', 'required'],
			'userid' => ['db report.userid', 'required'],
			'name' => ['db report.name', 'required', 'not_empty'],
			'dashboardid' => ['db report.dashboardid', 'required'],
			'period' => ['db report.period', 'required', 'in' => [ZBX_REPORT_PERIOD_DAY, ZBX_REPORT_PERIOD_WEEK, ZBX_REPORT_PERIOD_MONTH, ZBX_REPORT_PERIOD_YEAR]],
			'cycle' => ['db report.cycle', 'required', 'in' => [ZBX_REPORT_CYCLE_DAILY, ZBX_REPORT_CYCLE_WEEKLY, ZBX_REPORT_CYCLE_MONTHLY, ZBX_REPORT_CYCLE_YEARLY]],
			'weekdays' => ['array', 'required', 'not_empty', 'field' => ['integer'],
				'when' => ['cycle', 'in' => [ZBX_REPORT_CYCLE_WEEKLY]],
				'messages' => [
					'type' => _s('Incorrect value for field "%1$s": %2$s.', _('Repeat on'), _('at least one day of the week must be selected')),
					'not_empty' => _s('Incorrect value for field "%1$s": %2$s.', _('Repeat on'), _('at least one day of the week must be selected'))
				]
			],
			'hours' => ['integer', 'required', 'min' => 0, 'max' => 23],
			'minutes' => ['integer', 'required', 'min' => 0, 'max' => 59],
			'active_since' => ['string', 'use' => [CAbsoluteTimeValidator::class, ['min' => 0, 'max' => ZBX_MAX_DATE]],
				'regex' => '/^(\\d\\d\\d\\d-\\d\\d-\\d\\d)?$/',
				'messages' => ['regex' => _('Invalid date.')]
			],
			'active_till' => ['string', 'use' => [CAbsoluteTimeValidator::class, ['min' => 0, 'max' => ZBX_MAX_DATE]],
				'regex' => '/^(\\d\\d\\d\\d-\\d\\d-\\d\\d)?$/',
				'messages' => ['regex' => _('Invalid date.')]
			],
			'subject' => ['string'],
			'message' => ['string'],
			'subscriptions' => ['objects', 'required', 'not_empty', 'fields' => [
				'recipient_type' => ['integer', 'required', 'in' => [ZBX_REPORT_RECIPIENT_TYPE_USER, ZBX_REPORT_RECIPIENT_TYPE_USER_GROUP]],
				'recipientid' => [
					['db users.userid', 'required', 'when' => ['recipient_type', 'in' => [ZBX_REPORT_RECIPIENT_TYPE_USER]]],
					['db usrgrp.usrgrpid', 'required', 'when' => ['recipient_type', 'in' => [ZBX_REPORT_RECIPIENT_TYPE_USER_GROUP]]]
				],
				'creatorid' => ['db users.userid', 'required'],
				'exclude' => ['integer', 'required',
					'in' => [ZBX_REPORT_EXCLUDE_USER_FALSE, ZBX_REPORT_EXCLUDE_USER_TRUE],
					'when' => ['recipient_type', 'in' => [ZBX_REPORT_RECIPIENT_TYPE_USER]]
				]
			]],
			'description' => ['db report.description'],
			'status' => ['db report.status', 'in' => [ZBX_REPORT_STATUS_DISABLED, ZBX_REPORT_STATUS_ENABLED]]
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot update scheduled report'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_REPORTS_SCHEDULED_REPORTS)
				|| !$this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SCHEDULED_REPORTS)) {
			return false;
		}

		return (bool) API::Report()->get([
			'output' => [],
			'reportids' => $this->getInput('reportid')
		]);
	}

	protected function doAction(): void {
		$report = [];

		$this->getInputs($report, ['reportid', 'userid', 'name', 'dashboardid', 'period', 'cycle', 'active_since',
			'active_till', 'subject', 'message', 'description', 'status'
		]);

		$report['weekdays'] = ($report['cycle'] == ZBX_REPORT_CYCLE_WEEKLY)
			? array_sum($this->getInput('weekdays', []))
			: 0;
		$report['start_time'] = ($this->getInput('hours') * SEC_PER_HOUR) + ($this->getInput('minutes') * SEC_PER_MIN);
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

		$result = API::Report()->update($report);

		$output = [];

		if ($result) {
			$output['success']['title'] = _('Scheduled report updated');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot update scheduled report'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
