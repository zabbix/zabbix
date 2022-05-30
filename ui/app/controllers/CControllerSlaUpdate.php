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


class CControllerSlaUpdate extends CControllerSlaCreateUpdate {

	/**
	 * @var array
	 */
	private $schedule = [];

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'slaid' =>				'required|id',
			'name' =>				'required|string|not_empty',
			'slo' =>				'required|string|not_empty',
			'period' =>				'required|in '.implode(',', [ZBX_SLA_PERIOD_DAILY, ZBX_SLA_PERIOD_WEEKLY, ZBX_SLA_PERIOD_MONTHLY, ZBX_SLA_PERIOD_QUARTERLY, ZBX_SLA_PERIOD_ANNUALLY]),
			'timezone' =>			'required|in '.implode(',', array_merge([ZBX_DEFAULT_TIMEZONE], array_keys(CTimezoneHelper::getList()))),
			'schedule_mode' =>		'required|in '.implode(',', [CSlaHelper::SCHEDULE_MODE_24X7, CSlaHelper::SCHEDULE_MODE_CUSTOM]),
			'schedule_enabled' =>	'array',
			'schedule_periods' =>	'array',
			'effective_date' =>		'required|abs_date',
			'service_tags' =>		'required|array',
			'description' =>		'required|string',
			'status' =>				'in '.ZBX_SLA_STATUS_ENABLED,
			'excluded_downtimes' =>	'array'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			if ($this->getInput('schedule_mode') == CSlaHelper::SCHEDULE_MODE_CUSTOM) {
				try {
					$this->schedule = self::validateCustomSchedule($this->getInput('schedule_enabled', []),
						$this->getInput('schedule_periods', [])
					);
				}
				catch (InvalidArgumentException $e) {
					info($e->getMessage());
					$ret = false;
				}
			}
		}

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode(['errors' => getMessages()->toString()])
				]))->disableView()
			);
		}

		return $ret;
	}

	/**
	 * @throws APIException
	 */
	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_SERVICES_SLA)
				|| !$this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SLA)) {
			return false;
		}

		return (bool) API::Sla()->get([
			'output' => [],
			'slaids' => $this->getInput('slaid')
		]);
	}

	/**
	 * @throws Exception
	 */
	protected function doAction(): void {
		$parser = new CAbsoluteTimeParser();
		$parser->parse($this->getInput('effective_date'));
		$effective_date = $parser
			->getDateTime(true, new DateTimeZone('UTC'))
			->getTimestamp();

		$sla = [
			'effective_date' => $effective_date,
			'status' => $this->hasInput('status') ? ZBX_SLA_STATUS_ENABLED : ZBX_SLA_STATUS_DISABLED,
			'schedule' => $this->schedule,
			'service_tags' => [],
			'excluded_downtimes' =>	[]
		];

		$fields = ['slaid', 'name', 'slo', 'period', 'timezone', 'description', 'excluded_downtimes'];

		$this->getInputs($sla, $fields);

		foreach ($this->getInput('service_tags', []) as $service_tag) {
			if ($service_tag['tag'] === '' && $service_tag['value'] === '') {
				continue;
			}

			$sla['service_tags'][] = $service_tag;
		}

		$result = API::Sla()->update($sla);

		if ($result) {
			$output = ['title' => _('SLA updated')];

			if ($messages = CMessageHelper::getMessages()) {
				$output['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output = [
				'errors' => makeMessageBox(ZBX_STYLE_MSG_BAD, filter_messages(), CMessageHelper::getTitle())->toString()
			];
		}

		$this->setResponse((new CControllerResponseData(['main_block' => json_encode($output)]))->disableView());
	}
}
