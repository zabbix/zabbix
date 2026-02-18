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


class CControllerSlaUpdate extends CController {

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	public static function getValidationRules(): array {
		$api_uniq = [
			['sla.get', ['name' => '{name}'], 'slaid']
		];

		return ['object', 'api_uniq' => $api_uniq, 'fields' => [
			'slaid' => ['db sla.slaid', 'required'],
			'name' => ['db sla.name', 'required', 'not_empty'],
			'slo' => ['float', 'required', 'not_empty', 'min' => 0, 'max' => 100, 'decimal_limit' => 4],
			'period' => ['db sla.period', 'in' => [
				ZBX_SLA_PERIOD_DAILY, ZBX_SLA_PERIOD_WEEKLY, ZBX_SLA_PERIOD_MONTHLY, ZBX_SLA_PERIOD_QUARTERLY,
				ZBX_SLA_PERIOD_ANNUALLY
			]],
			'timezone' => ['db sla.timezone',
				'in' => array_merge([ZBX_DEFAULT_TIMEZONE], array_keys(CTimezoneHelper::getList()))
			],
			'schedule_mode' => ['integer', 'in' => [CSlaHelper::SCHEDULE_MODE_24X7, CSlaHelper::SCHEDULE_MODE_CUSTOM]],
			'schedule_periods' => ['objects', 'uniq' => ['day'],
				'when' => ['schedule_mode', 'in' => [CSlaHelper::SCHEDULE_MODE_CUSTOM]],
				'fields' => [
					'day' => ['integer', 'required', 'in' => range(0, 6)],
					'enabled' => ['boolean'],
					'period' => ['string', 'not_empty',
						'use' => [CTimeRangesValidator::class, []],
						'when' => ['enabled', 'in' => [1]]
					]
				],
				'count_values' => [
					'field_rules' => ['enabled', 'in' => [1]],
					'min' => 1,
					'message' => _('At least one entry should be selected.')
				]
			],
			'effective_date' => ['string', 'required', 'not_empty',
				'use' => [CAbsoluteTimeValidator::class, ['date_only' => true, 'min' => 0, 'max' => ZBX_MAX_DATE]]
			],
			'service_tags' => ['objects', 'required', 'not_empty', 'uniq' => ['tag', 'value'],
				'messages' => ['uniq' => _('Tag name and value combination is not unique.')],
				'fields' => [
					'value' => ['db sla_service_tag.value'],
					'operator' => ['db sla_service_tag.operator', 'in' => [
						ZBX_SERVICE_PROBLEM_TAG_OPERATOR_EQUAL, ZBX_SERVICE_PROBLEM_TAG_OPERATOR_LIKE
					]],
					'tag' => [
						['db sla_service_tag.tag', 'required'],
						['db sla_service_tag.tag', 'required', 'not_empty', 'when' => ['value', 'not_empty']]
					]
				]
			],
			'description' => ['db sla.description'],
			'excluded_downtimes' => ['objects', 'uniq' => ['period_from', 'period_to'],
				'messages' => ['uniq' => _('Excluded downtime periods must be unique.')],
				'fields' => [
					'name' => ['db sla_excluded_downtime.name'],
					'period_from' => ['db sla_excluded_downtime.period_from', 'required', 'not_empty',
						'use' => [CTimeUnitValidator::class, ['min' => 1, 'max' => ZBX_MAX_DATE]],
						'when' => ['name', 'not_empty']
					],
					'period_to' => ['db sla_excluded_downtime.period_to', 'required', 'not_empty',
						'use' => [CTimeUnitValidator::class, ['min' => 1, 'max' => ZBX_MAX_DATE]],
						'when' => ['name', 'not_empty']
					]
				]
			],
			'status' => ['db sla.status', 'in' => [ZBX_SLA_STATUS_DISABLED, ZBX_SLA_STATUS_ENABLED]]
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();

			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot update SLA'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response, JSON_THROW_ON_ERROR)])
			);
		}

		return $ret;
	}

	/**
	 * @throws APIException
	 */
	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_SERVICES_SLA) || !$this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SLA)) {
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

		$schedule = $this->getInput('schedule_mode') == CSlaHelper::SCHEDULE_MODE_CUSTOM
			? CSlaHelper::prepareSchedulePeriods($this->getInput('schedule_periods'))
			: [];

		$sla = [
			'effective_date' => $effective_date,
			'status' => $this->hasInput('status') ? ZBX_SLA_STATUS_ENABLED : ZBX_SLA_STATUS_DISABLED,
			'schedule' => $schedule,
			'service_tags' => $this->getInput('service_tags', []),
			'excluded_downtimes' =>	[]
		];

		$fields = ['slaid', 'name', 'slo', 'period', 'timezone', 'description', 'excluded_downtimes'];

		$this->getInputs($sla, $fields);

		$result = API::Sla()->update($sla);

		$output = [];

		if ($result) {
			$output['success']['title'] = _('SLA updated');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot update SLA'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
