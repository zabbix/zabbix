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


class CControllerMaintenanceEdit extends CController {

	/**
	 * @var array
	 */
	private $maintenance;

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
		if (!$this->checkAccess(CRoleHelper::UI_CONFIGURATION_MAINTENANCE)) {
			return false;
		}

		if ($this->hasInput('maintenanceid')) {
			$this->maintenance = API::Maintenance()->get([
				'output' => API_OUTPUT_EXTEND,
				'selectTimeperiods' => API_OUTPUT_EXTEND,
				'selectTags' => API_OUTPUT_EXTEND,
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

	/**
	 * @throws Exception
	 */
	protected function doAction(): void {
		$empty_tags = [['tag' => '', 'operator' => MAINTENANCE_TAG_OPERATOR_LIKE, 'value' => '']];
		$defaults = DB::getDefaults('maintenances');

		if ($this->maintenance !== null) {
			CArrayHelper::sort($this->maintenance['tags'], ['tag', 'value', 'operator']);
			$this->maintenance['tags'] = array_values($this->maintenance['tags']);

			CArrayHelper::sort($this->maintenance['timeperiods'], ['timeperiod_type', 'start_date']);
			$this->maintenance['timeperiods'] = array_values($this->maintenance['timeperiods']);

			foreach ($this->maintenance['timeperiods'] as $row_index => &$timeperiod) {
				$timeperiod['start_date'] = date(ZBX_DATE_TIME, $timeperiod['start_date']);
				$timeperiod += [
					'row_index' => $row_index,
					'period_type' => timeperiod_type2str($timeperiod['timeperiod_type']),
					'schedule' => $timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_ONETIME
						? $timeperiod['start_date']
						: schedule2str($timeperiod),
					'period_table_entry' => zbx_date2age(0, $timeperiod['period'])
				];
			}
			unset($timeperiod);

			$data = [
				'maintenanceid' => $this->maintenance['maintenanceid'],
				'mname' => $this->maintenance['name'],
				'maintenance_type' => $this->maintenance['maintenance_type'],
				'active_since' => date(ZBX_DATE_TIME, $this->maintenance['active_since']),
				'active_till' => date(ZBX_DATE_TIME, $this->maintenance['active_till']),
				'timeperiods' => $this->maintenance['timeperiods'],
				'tags_evaltype' => $this->maintenance['tags_evaltype'],
				'tags' => $this->maintenance['tags'] ?: $empty_tags,
				'description' => $this->maintenance['description']
			];

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
		}
		else {
			$data = [
				'maintenanceid' => null,
				'mname' => $defaults['name'],
				'maintenance_type' => $defaults['maintenance_type'],
				'active_since' => date(ZBX_DATE_TIME, strtotime('today')),
				'active_till' => date(ZBX_DATE_TIME, strtotime('tomorrow')),
				'timeperiods' => [],
				'tags_evaltype' => $defaults['tags_evaltype'],
				'tags' => $empty_tags,
				'description' => $defaults['description']
			];

			$db_hosts = [];
			$db_groups = [];
		}

		$data['hosts_ms'] = CArrayHelper::renameObjectsKeys($db_hosts, ['hostid' => 'id']);
		CArrayHelper::sort($data['hosts_ms'], ['name']);

		$data['groups_ms'] = CArrayHelper::renameObjectsKeys($db_groups, ['groupid' => 'id']);
		CArrayHelper::sort($data['groups_ms'], ['name']);

		$data['allowed_edit'] = $this->checkAccess(CRoleHelper::ACTIONS_EDIT_MAINTENANCE);
		$data['user'] = ['debug_mode' => $this->getDebugMode()];

		$this->setResponse(new CControllerResponseData($data));
	}
}
