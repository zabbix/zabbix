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


class CControllerMaintenanceCreate extends CControllerMaintenanceUpdateGeneral {

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	public static function getValidationRules(): array {
		$api_uniq = [
			['maintenance.get', ['name' => '{name}']]
		];

		return ['object', 'api_uniq' => $api_uniq, 'fields' => [
			'name' => ['db maintenances.name', 'required', 'not_empty'],
			'maintenance_type' => ['db maintenances.maintenance_type', 'required',
				'in' => [MAINTENANCE_TYPE_NORMAL, MAINTENANCE_TYPE_NODATA]],
			'active_since' => ['string', 'required', 'not_empty',
				'use' => [CAbsoluteTimeParser::class, [], ['min' => 0, 'max' => ZBX_MAX_DATE]],
				'messages' => ['use' => _('Invalid date.')]],
			'active_till' => ['string', 'required', 'not_empty',
				'use' => [CAbsoluteTimeParser::class, [], ['min' => 0, 'max' => ZBX_MAX_DATE]],
				'messages' => ['use' => _('Invalid date.')]],
			'timeperiods' => ['objects', 'required', 'not_empty',
				'messages' => ['not_empty' => _('At least one period must be added.')],
				'fields' => [
					'timeperiod_type' => ['db timeperiods.timeperiod_type', 'required',
						'in' => [TIMEPERIOD_TYPE_ONETIME, TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY,
							TIMEPERIOD_TYPE_MONTHLY]],
					'every' => [
						['db timeperiods.every', 'min' => 1, 'max' => 999,
							'when' => ['timeperiod_type', 'in' => [TIMEPERIOD_TYPE_DAILY]]],
						['db timeperiods.every', 'min' => 1, 'max' => 99,
							'when' => ['timeperiod_type', 'in' => [TIMEPERIOD_TYPE_WEEKLY]]],
						['db timeperiods.every',
							'in' => [MONTH_WEEK_FIRST, MONTH_WEEK_SECOND, MONTH_WEEK_THIRD, MONTH_WEEK_FOURTH,
								MONTH_WEEK_LAST],
							'when' => ['timeperiod_type', 'in' => [TIMEPERIOD_TYPE_MONTHLY]]]
					],
					'month' => ['db timeperiods.month', 'min' => 1,
						'when' => ['timeperiod_type', 'in' => [TIMEPERIOD_TYPE_MONTHLY]],
						'messages' => ['min' => _('At least one month must be selected.')]],
					'dayofweek' => [
						['db timeperiods.dayofweek', 'min' => 0, 'max' => 0b1111111,
							'when' => ['timeperiod_type', 'in' => [TIMEPERIOD_TYPE_MONTHLY]],
							'messages' => ['min' => _('At least one weekday must be selected.')]
						],
						['db timeperiods.dayofweek', 'min' => 0b0000001, 'max' => 0b1111111,
							'when' => ['timeperiod_type', 'in' => [TIMEPERIOD_TYPE_WEEKLY]],
							'messages' => ['min' => _('At least one weekday must be selected.')]
						]
					],
					'day' => ['db timeperiods.day', 'required', 'min' => 1, 'max' => MONTH_MAX_DAY,
						'when' => [['dayofweek', 'in' => [0]], ['timeperiod_type', 'in' => [TIMEPERIOD_TYPE_MONTHLY]]]],
					'start_time' => ['db timeperiods.start_time', 'min' => 0, 'max' => SEC_PER_DAY - SEC_PER_MIN,
						'when' => ['timeperiod_type',
							'in' => [TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY, TIMEPERIOD_TYPE_MONTHLY]]],
					'period' => ['db timeperiods.period', 'required',
						'min' => 5 * SEC_PER_MIN, 'max' => CMaintenanceHelper::MAX_TIMEPERIOD],
					'start_date' => ['db timeperiods.start_date', 'required',
						'when' => ['timeperiod_type', 'in' => [TIMEPERIOD_TYPE_ONETIME]]]
				]],
			'hostids' => ['array', 'field' => ['db maintenances_hosts.hostid']],
			'groupids' => [
				['array', 'field' => ['db maintenances_groups.groupid']],
				['array', 'required', 'not_empty', 'field' => ['db maintenances_groups.groupid'],
					'when' => ['hostids', 'empty'],
					'messages' => ['not_empty' => _('At least one host group or host must be selected.')]]
			],
			'tags_evaltype' => [
				['db maintenances.tags_evaltype',
					'in' => [MAINTENANCE_TAG_EVAL_TYPE_AND_OR, MAINTENANCE_TAG_EVAL_TYPE_OR]],
				['db maintenances.tags_evaltype', 'required',
					'in' => [MAINTENANCE_TAG_EVAL_TYPE_AND_OR, MAINTENANCE_TAG_EVAL_TYPE_OR],
					'when' => ['maintenance_type', 'in' => [MAINTENANCE_TYPE_NORMAL]]]
			],
			'tags' => ['objects', 'uniq' => [['tag', 'operator', 'value']],
				'messages' => ['uniq' => _('Tag name, operator and value combination is not unique.')],
				'fields' => [
					'operator' => ['db maintenance_tag.operator',
						'in' => [MAINTENANCE_TAG_OPERATOR_LIKE, MAINTENANCE_TAG_OPERATOR_EQUAL]],
					'value' => ['db maintenance_tag.value'],
					'tag' => ['db maintenance_tag.tag', 'required', 'not_empty', 'when' => ['value', 'not_empty']]
				],
				'when' => ['maintenance_type', 'in' => [MAINTENANCE_TYPE_NORMAL]]
			],
			'description' => ['db maintenances.description']
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());
		$ret = $ret && $this->validateTimePeriods();

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot create maintenance period'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_MAINTENANCE)
			&& $this->checkAccess(CRoleHelper::ACTIONS_EDIT_MAINTENANCE);
	}

	protected function doAction(): void {
		$maintenance = [
			'name' => $this->getInput('name'),
			'maintenance_type' => $this->getInput('maintenance_type'),
			'description' => $this->getInput('description', ''),
			'active_since' => $this->parseActiveTime($this->getInput('active_since')),
			'active_till' => $this->parseActiveTime($this->getInput('active_till')),
			'groups' => zbx_toObject($this->getInput('groupids', []), 'groupid'),
			'hosts' => zbx_toObject($this->getInput('hostids', []), 'hostid'),
			'timeperiods' => $this->processTimePeriods($this->getInput('timeperiods', []))
		];

		$this->processMaintenance($maintenance);
		$result = API::Maintenance()->create($maintenance);

		$output = [];

		if ($result) {
			$output['success']['title'] = _('Maintenance period created');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot create maintenance period'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
