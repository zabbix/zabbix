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


class CControllerMaintenanceEdit extends CController {

	/**
	 * @var array
	 */
	private $maintenance;

	protected function init() {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$rules = ['object', 'fields' => [
			'maintenanceid' => ['db maintenances.maintenanceid'],
			'context' 		=> ['string'],
			'eventids' 		=> ['array']
		]];

		$ret = $this->validateInput($rules, true);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_CONFIGURATION_MAINTENANCE)
				|| (!$this->checkAccess(CRoleHelper::ACTIONS_EDIT_MAINTENANCE)) && !$this->hasInput('maintenanceid')) {
			return false;
		}

		if ($this->hasInput('maintenanceid')) {
			$this->maintenance = API::Maintenance()->get([
				'output' => ['maintenanceid', 'name', 'maintenance_type', 'description', 'active_since', 'active_till',
					'tags_evaltype'
				],
				'selectEventNames' => ['operator', 'value'],
				'selectTags' => ['tag', 'value', 'operator'],
				'selectTimeperiods' => ['timeperiod_type', 'every', 'month', 'dayofweek', 'day', 'start_time', 'period',
					'start_date'
				],
				'editable' => true,
				'maintenanceids' => $this->getInput('maintenanceid')
			]);

			if (!$this->maintenance) {
				return false;
			}

			$this->maintenance = $this->maintenance[0];
		}

		return true;
	}

	protected function doAction(): void {
		if ($this->maintenance !== null) {
			CArrayHelper::sort($this->maintenance['event_names'], ['value', 'operator']);
			$this->maintenance['event_names'] = array_values($this->maintenance['event_names']);

			CArrayHelper::sort($this->maintenance['tags'], ['tag', 'value', 'operator']);
			$this->maintenance['tags'] = array_values($this->maintenance['tags']);

			CArrayHelper::sort($this->maintenance['timeperiods'], ['timeperiod_type', 'start_date']);
			$this->maintenance['timeperiods'] = array_values($this->maintenance['timeperiods']);

			foreach ($this->maintenance['timeperiods'] as &$timeperiod) {
				$timeperiod += [
					'formatted_type' => CMaintenanceHelper::getTimePeriodTypeNames()[$timeperiod['timeperiod_type']],
					'formatted_schedule' => CMaintenanceHelper::getTimePeriodSchedule($timeperiod),
					'formatted_period' => zbx_date2age(0, $timeperiod['period'])
				];
			}
			unset($timeperiod);

			$data = [
				'maintenanceid' => $this->maintenance['maintenanceid'],
				'name' => $this->maintenance['name'],
				'maintenance_type' => $this->maintenance['maintenance_type'],
				'active_since' => date(ZBX_DATE_TIME, $this->maintenance['active_since']),
				'active_till' => date(ZBX_DATE_TIME, $this->maintenance['active_till']),
				'timeperiods' => $this->maintenance['timeperiods'],
				'event_names' => $this->maintenance['event_names']
					?: [['operator' => MAINTENANCE_EVENT_NAME_OPERATOR_LIKE, 'value' => '']],
				'tags_evaltype' => $this->maintenance['tags_evaltype'],
				'tags' => $this->maintenance['tags']
					?: [['tag' => '', 'operator' => MAINTENANCE_TAG_OPERATOR_LIKE, 'value' => '']],
				'description' => $this->maintenance['description']
			];
		}
		else {
			$defaults = DB::getDefaults('maintenances');

			$data = [
				'maintenanceid' => null,
				'name' => $defaults['name'],
				'maintenance_type' => $defaults['maintenance_type'],
				'active_since' => date(ZBX_DATE_TIME, strtotime('today')),
				'active_till' => date(ZBX_DATE_TIME, strtotime('tomorrow')),
				'timeperiods' => [],
				'event_names' => [['operator' => MAINTENANCE_EVENT_NAME_OPERATOR_LIKE, 'value' => '']],
				'tags_evaltype' => $defaults['tags_evaltype'],
				'tags' => [['tag' => '', 'operator' => MAINTENANCE_TAG_OPERATOR_LIKE, 'value' => '']],
				'description' => $defaults['description']
			];
		}

		if ($this->maintenance !== null) {
			$db_hosts = API::Host()->get([
				'output' => ['hostid', 'name'],
				'maintenanceids' => $data['maintenanceid'],
				'editable' => true
			]);

			$db_groups = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'maintenanceids' => $data['maintenanceid'],
				'editable' => true
			]);

			$db_triggers = API::Trigger()->get([
				'output' => ['triggerid', 'description'],
				'maintenanceids' => $data['maintenanceid'],
				'editable' => true
			]);

			$data += [
				'hosts_ms' => CArrayHelper::renameObjectsKeys($db_hosts, ['hostid' => 'id']),
				'groups_ms' => CArrayHelper::renameObjectsKeys($db_groups, ['groupid' => 'id']),
				'triggers_ms' => CArrayHelper::renameObjectsKeys($db_triggers,
					['triggerid' => 'id', 'description' => 'name'])
			];

			CArrayHelper::sort($data['hosts_ms'], ['name']);
			CArrayHelper::sort($data['groups_ms'], ['name']);
			CArrayHelper::sort($data['triggers_ms'], ['name']);
		}
		else {
			$data += [
				'hosts_ms' => [],
				'groups_ms' => [],
				'triggers_ms' => []
			];

			if ($this->hasInput('context') && $this->hasInput('eventids')) {
				$db_events = API::Event()->get([
					'output' => ['name', 'objectid'],
					'eventids' => $this->getInput('eventids'),
					'selectTags' => ['tag', 'value']
				]);

				if ($db_events) {
					$this->applyProblemContextPreset($db_events, $data);
				}
			}
		}

		$data['allowed_edit'] = $this->checkAccess(CRoleHelper::ACTIONS_EDIT_MAINTENANCE);
		$data['user'] = ['debug_mode' => $this->getDebugMode()];

		$create_rules = (new CFormValidator(CControllerMaintenanceCreate::getValidationRules()))->getRules();
		$rules = $this->maintenance
			? (new CFormValidator(CControllerMaintenanceUpdate::getValidationRules()))->getRules()
			: $create_rules;

		$data += [
			'js_validation_rules' => $rules,
			'js_clone_validation_rules' => $create_rules
		];

		$this->setResponse(new CControllerResponseData($data));
	}

	/**
	 * Function to prepare data for ad-hoc prefilling.
	 *
	 * @param array $events
	 * @param array $data
	 */
	protected function applyProblemContextPreset(array $events, array &$data): void {
		$data['name'] = CMaintenanceHelper::getNextIndexedName(_s('Ad-hoc: %1$s', $events[0]['name']));
		$data['active_since'] = date(ZBX_DATE_TIME, strtotime('now'));
		$data['active_till'] = date(ZBX_DATE_TIME, strtotime('now + '.secondsToPeriod(
			timeUnitToSeconds(CWebUser::$data['default_maintenance_period'])
		)));
		$timeperiod = [
			'timeperiod_type' => 0,
			'every' => 1,
			'month' => 0,
			'dayofweek' => 0,
			'day' => 0,
			'start_time' => 0,
			'period' => timeUnitToSeconds(CWebUser::$data['default_maintenance_period']),
			'start_date' => strtotime('now'),
			'formatted_type' => 'One time only',
			'formatted_period' => zbx_date2age(0, timeUnitToSeconds(CWebUser::$data['default_maintenance_period']))
		];
		$data['timeperiods'][] = $timeperiod
			+ ['formatted_schedule' => CMaintenanceHelper::getTimePeriodSchedule($timeperiod)];

		foreach ($events as $event) {
			$db_triggers = API::Trigger()->get([
				'output' => ['triggerid', 'description'],
				'triggerids' => $event['objectid'],
				'selectHosts' => ['hostid', 'name'],
				'selectHostGroups' => ['groupid', 'name'],
				'selectItems' => ['hostid']
			]);

			if ($db_triggers) {
				$hostids = array_values(array_unique(
					array_column($db_triggers[0]['items'], 'hostid')
				));

				$db_hosts = $hostids
					? API::Host()->get([
						'output' => ['hostid', 'name'],
						'hostids' => $hostids,
						'selectHostGroups' => ['groupid', 'name']
					])
					: [];

				$host_groups = [];
				foreach ($db_hosts as $host) {
					$host_groups = array_merge($host_groups, $host['hostgroups']);
				}

				switch ($this->getInput('context')) {
					case 'host':
						foreach (CArrayHelper::renameObjectsKeys($db_hosts, ['hostid' => 'id']) as $host) {
							$data['hosts_ms'][$host['id']] = $host;
						}
						break;

					case 'trigger':
						foreach (CArrayHelper::renameObjectsKeys($db_triggers, ['triggerid' => 'id',
								'description' => 'name']) as $trigger) {
							$data['triggers_ms'][$trigger['id']] = $trigger;
						}
						break;

					case 'event_name':
						foreach (CArrayHelper::renameObjectsKeys($host_groups, ['groupid' => 'id']) as $group) {
							$data['groups_ms'][$group['id']] = $group;
						}

						CArrayHelper::sort($data['groups_ms'], ['name']);

						$data['event_names'] = [[
							'operator' => MAINTENANCE_EVENT_NAME_OPERATOR_LIKE,
							'value' => $event['name']
						]];
						break;

					case 'event_tags':
						foreach (CArrayHelper::renameObjectsKeys($host_groups, ['groupid' => 'id']) as $group) {
							$data['groups_ms'][$group['id']] = $group;
						}

						CArrayHelper::sort($data['groups_ms'], ['name']);

						if ($event['tags']) {
							CArrayHelper::sort($event['tags'], ['tag', 'value']);
							$data['tags'] = array_values($event['tags']);

							foreach ($data['tags'] as &$tag) {
								$tag['operator'] = MAINTENANCE_TAG_OPERATOR_LIKE;
							}
							unset($tag);
						}
						break;
				}
			}
		}
	}
}
