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


class CControllerMaintenanceCreate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'name' =>				'required|string|not_empty',
			'maintenance_type' =>	'required|in '.implode(',', [MAINTENANCE_TYPE_NORMAL, MAINTENANCE_TYPE_NODATA]),
			'active_since' =>		'required|abs_time',
			'active_till' =>		'required|abs_time',
			'timeperiods' =>		'required|array',
			'groupids' =>			'array_id',
			'hostids' => 			'array_id',
			'tags_evaltype' =>		'in '.implode(',', [MAINTENANCE_TAG_EVAL_TYPE_AND_OR, MAINTENANCE_TAG_EVAL_TYPE_OR]),
			'tags' =>				'array',
			'description' =>		'required|string'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			if ($this->getInput('maintenance_type') == MAINTENANCE_TYPE_NORMAL) {
				$fields = [
					'tags_evaltype' => 'required'
				];

				$validator = new CNewValidator(array_intersect_key($this->getInputAll(), $fields), $fields);

				foreach ($validator->getAllErrors() as $error) {
					error($error);
				}

				if ($validator->isErrorFatal() || $validator->isError()) {
					$ret = false;
				}
			}

			if (!$this->hasInput('groupids') && !$this->hasInput('hostids')) {
				error(_('At least one host group or host must be selected.'));

				$ret = false;
			}
		}

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Cannot create maintenance period'),
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
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
			'name' => $this->getInput('name'),
			'maintenance_type' => $this->getInput('maintenance_type'),
			'description' => $this->getInput('description'),
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
