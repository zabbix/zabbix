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


class CControllerMaintenanceEdit extends CController {

	/**
	 * @var array
	 */
	private $maintenance;

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'maintenanceid' => 'db maintenances.maintenanceid'
		];

		$ret = $this->validateInput($fields);

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

			$data += [
				'hosts_ms' => CArrayHelper::renameObjectsKeys($db_hosts, ['hostid' => 'id']),
				'groups_ms' => CArrayHelper::renameObjectsKeys($db_groups, ['groupid' => 'id'])
			];

			CArrayHelper::sort($data['hosts_ms'], ['name']);
			CArrayHelper::sort($data['groups_ms'], ['name']);
		}
		else {
			$data += [
				'hosts_ms' => [],
				'groups_ms' => []
			];
		}

		$data['allowed_edit'] = $this->checkAccess(CRoleHelper::ACTIONS_EDIT_MAINTENANCE);
		$data['user'] = ['debug_mode' => $this->getDebugMode()];

		$this->setResponse(new CControllerResponseData($data));
	}
}
