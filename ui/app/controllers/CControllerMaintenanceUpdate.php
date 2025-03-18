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


class CControllerMaintenanceUpdate extends CController {

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	public static function getValidationRules(): array {
		$api_uniq = [
			['maintenance.get', ['name' => '{name}'], 'maintenanceid']
		];

		return ['object', 'api_uniq' => $api_uniq, 'fields' => [
			'maintenanceid' =>	['db maintenances.maintenanceid', 'required'],
			'name' => ['db maintenances.name', 'required', 'not_empty'],
			'maintenance_type' => ['db maintenances.maintenance_type', 'required', 'in' => [MAINTENANCE_TYPE_NORMAL, MAINTENANCE_TYPE_NODATA]],
			'active_since' => ['string', 'required', 'not_empty', 'use' => [CAbsoluteTimeParser::class, [], ['min' => 0, 'max' => ZBX_MAX_DATE]], 'messages' => ['use' => _('Invalid date.')]],
			'active_till' => ['string', 'required', 'not_empty', 'use' => [CAbsoluteTimeParser::class, [], ['min' => 0, 'max' => ZBX_MAX_DATE]], 'messages' => ['use' => _('Invalid date.')]],
			'timeperiods' => ['objects', 'required', 'not_empty', 'messages' => ['not_empty' => _('At least one period must be added.')], 'fields' => [
				'timeperiod_type' => ['db timeperiods.timeperiod_type', 'required', 'in' => [TIMEPERIOD_TYPE_ONETIME, TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY, TIMEPERIOD_TYPE_MONTHLY]],
				'every' => [
					['db timeperiods.every', 'min' => 1, 'max' => 999, 'when' => ['timeperiod_type', 'in' => [TIMEPERIOD_TYPE_DAILY]]],
					['db timeperiods.every', 'min' => 1, 'max' => 99, 'when' => ['timeperiod_type', 'in' => [TIMEPERIOD_TYPE_WEEKLY]]],
					['db timeperiods.every', 'in' => [1, 2, 3, 4, 5], 'when' => ['timeperiod_type', 'in' => [TIMEPERIOD_TYPE_MONTHLY]]]
				],
				'month' => ['db timeperiods.month', 'min' => 1, 'messages' => ['min' => _('At least one month must be selected.')], 'when' => ['timeperiod_type', 'in' => [TIMEPERIOD_TYPE_MONTHLY]]],
				'month_date_type' => ['integer', 'in' => [0, 1], 'when' => ['timeperiod_type', 'in' => [TIMEPERIOD_TYPE_MONTHLY]]],
				'dayofweek' => [
					['db timeperiods.dayofweek', 'min' => 1,
						'when' => [['timeperiod_type', 'in' => [TIMEPERIOD_TYPE_MONTHLY]], ['month_date_type', 'in' => [1]]],
						'messages' => ['min' => _('At least one weekday must be selected.')]
					],
					['db timeperiods.dayofweek', 'min' => 1,
						'when' => [['timeperiod_type', 'in' => [TIMEPERIOD_TYPE_MONTHLY]], ['month_date_type', 'in' => [1]]],
						'messages' => ['min' => _('At least one weekday must be selected.')]
					]
				],
				'day' => [
					['db timeperiods.day'],
				],
				'start_time' => ['db timeperiods.start_time', 'min' => 0, 'max' => SEC_PER_DAY, 'when' => ['timeperiod_type', 'in' => [TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY, TIMEPERIOD_TYPE_MONTHLY]]],
				'period' => ['db timeperiods.period', 'required'],
				'start_date' => ['db timeperiods.start_date', 'required', 'when' => ['timeperiod_type', 'in' => [TIMEPERIOD_TYPE_ONETIME]]]
			]],
			'hostids' => ['array', 'field' => ['db maintenances_hosts.hostid']],
			'groupids' => [
				['array', 'field' => ['db maintenances_groups.groupid']],
				['array', 'required', 'not_empty', 'field' => ['db maintenances_groups.groupid'], 'when' => ['hostids', 'empty']]
			],
			'tags_evaltype' => [
				['db maintenances.tags_evaltype', 'in' => [MAINTENANCE_TAG_EVAL_TYPE_AND_OR, MAINTENANCE_TAG_EVAL_TYPE_OR]],
				['db maintenances.tags_evaltype', 'required', 'in' => [MAINTENANCE_TAG_EVAL_TYPE_AND_OR, MAINTENANCE_TAG_EVAL_TYPE_OR], 'when' => ['maintenance_type', 'in' => [MAINTENANCE_TYPE_NORMAL]]]
			],
			'tags' => ['objects', 'uniq' => [['tag', 'operator', 'value']], 'fields' => [
					'operator' => ['db maintenance_tag.operator', 'in' => [MAINTENANCE_TAG_OPERATOR_LIKE, MAINTENANCE_TAG_OPERATOR_EQUAL]],
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
					'title' => _('Cannot update maintenance period'),
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
		$absolute_time_parser = new CAbsoluteTimeParser();

		$absolute_time_parser->parse($this->getInput('active_since'));
		$active_since_ts = $absolute_time_parser->getDateTime(true)->getTimestamp();

		$absolute_time_parser->parse($this->getInput('active_till'));
		$active_till_ts = $absolute_time_parser->getDateTime(true)->getTimestamp();

		$timeperiod_fields = [
			TIMEPERIOD_TYPE_ONETIME => ['timeperiod_type', 'start_date', 'period'],
			TIMEPERIOD_TYPE_DAILY => ['timeperiod_type', 'every', 'start_time', 'period'],
			TIMEPERIOD_TYPE_WEEKLY => ['timeperiod_type', 'every', 'dayofweek', 'start_time', 'period'],
			TIMEPERIOD_TYPE_MONTHLY => ['timeperiod_type', 'every', 'month', 'dayofweek', 'day', 'start_time', 'period']
		];

		$timeperiods = $this->getInput('timeperiods', []);

		foreach ($timeperiods as &$timeperiod) {
			$timeperiod = array_intersect_key($timeperiod,
				array_flip($timeperiod_fields[$timeperiod['timeperiod_type']])
			);
		}
		unset($timeperiod);

		$maintenance = [
			'maintenanceid' => $this->getInput('maintenanceid'),
			'name' => $this->getInput('name'),
			'maintenance_type' => $this->getInput('maintenance_type'),
			'description' => $this->getInput('description', ''),
			'active_since' => $active_since_ts,
			'active_till' => $active_till_ts,
			'groups' => zbx_toObject($this->getInput('groupids', []), 'groupid'),
			'hosts' => zbx_toObject($this->getInput('hostids', []), 'hostid'),
			'timeperiods' => $timeperiods
		];

		if ($maintenance['maintenance_type'] == MAINTENANCE_TYPE_NORMAL) {
			$maintenance += [
				'tags_evaltype' => $this->getInput('tags_evaltype'),
				'tags' => []
			];

			foreach ($this->getInput('tags', []) as $tag) {
				if (array_key_exists('tag', $tag) && array_key_exists('value', $tag)
						&& ($tag['tag'] !== '' || $tag['value'] !== '')) {
					$maintenance['tags'][] = $tag;
				}
			}
		}

		$result = API::Maintenance()->update($maintenance);

		$output = [];

		if ($result) {
			$output['success']['title'] = _('Maintenance period updated');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot update maintenance period'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}

	/**
	 * Function to compare values from fields "Active since" and "Active till".
	 */
	protected function validateTimePeriods(): bool {
		$absolute_time_parser = new CAbsoluteTimeParser();

		$absolute_time_parser->parse($this->getInput('active_since'));
		$active_since_ts = $absolute_time_parser->getDateTime(true)->getTimestamp();

		$absolute_time_parser->parse($this->getInput('active_till'));
		$active_till_ts = $absolute_time_parser->getDateTime(true)->getTimestamp();

		if ($active_since_ts >= $active_till_ts) {
			$this->addFormError('/active_till', _s('Must be greater than "%1$s".', _('Active since')),
				CFormValidator::ERROR_LEVEL_PRIMARY
			);

			return false;
		}

		return true;
	}
}
