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

	protected function checkInput(): bool {
		$fields = [
			'maintenanceid' =>	'db maintenances.maintenanceid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::ACTIONS_EDIT_MAINTENANCE)) {
			return false;
		}

		$this->maintenance = null;

		if ($this->hasInput('maintenanceid')) {
			$db_maintenances = API::Maintenance()->get([
				'output' => API_OUTPUT_EXTEND,
				'selectTimeperiods' => API_OUTPUT_EXTEND,
				'selectTags' => API_OUTPUT_EXTEND,
				'editable' => true,
				'maintenanceids' => $this->getInput('maintenanceid')
			]);

			if (!$db_maintenances) {
				return false;
			}

			$db_maintenance = reset($db_maintenances);
			$this->maintenance = $db_maintenance;
		}

		return true;
	}

	protected function doAction(): void {
		if ($this->maintenance !== null) {
			$data = [
				'maintenanceid' => $this->maintenance['maintenanceid'],
				'mname' => $this->maintenance['name'],
				'maintenance_type' => $this->maintenance['maintenance_type'],
				'active_since' => date(ZBX_DATE_TIME, $this->maintenance['active_since']),
				'active_till' => date(ZBX_DATE_TIME, $this->maintenance['active_till']),
				'description' => $this->maintenance['description'],
				'timeperiods' => $this->maintenance['timeperiods'],
				'tags_evaltype' => $this->maintenance['tags_evaltype'],
				'tags' => $this->maintenance['tags']
			];

			CArrayHelper::sort($data['timeperiods'], ['timeperiod_type', 'start_date']);
			CArrayHelper::sort($data['tags'], ['tag', 'value']);

			foreach ($data['timeperiods'] as &$timeperiod) {
				$timeperiod['start_date'] = date(ZBX_DATE_TIME, $timeperiod['start_date']);
			}
			unset($timeperiod);

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
				'maintenanceid' => $this->getInput('maintenanceid', 0),
				'mname' => $this->getInput('mname', ''),
				'maintenance_type' => $this->getInput('maintenance_type', 0),
				'active_since' => $this->getInput('active_since', date(ZBX_DATE_TIME, strtotime('today'))),
				'active_till' => $this->getInput('active_till', date(ZBX_DATE_TIME, strtotime('tomorrow'))),
				'description' => $this->getInput('description', ''),
				'timeperiods' => $this->getInput('timeperiods', []),
				'tags_evaltype' => $this->getInput('tags_evaltype', MAINTENANCE_TAG_EVAL_TYPE_AND_OR),
				'tags' => $this->getInput('tags', [])
			];

			$hostids = $this->getInput('hostids', []);
			$groupids = $this->getInput('groupids', []);

			$db_hosts = $hostids
				? API::Host()->get([
					'output' => ['hostid', 'name'],
					'hostids' => $hostids,
					'editable' => true
				])
				: [];

			$db_groups = $groupids
				? API::HostGroup()->get([
					'output' => ['groupid', 'name'],
					'groupids' => $groupids,
					'editable' => true
				])
				: [];
		}

		$data['allowed_edit'] = $this->checkAccess(CRoleHelper::ACTIONS_EDIT_MAINTENANCE);

		$data['hosts_ms'] = CArrayHelper::renameObjectsKeys($db_hosts, ['hostid' => 'id']);
		CArrayHelper::sort($data['hosts_ms'], ['name']);

		$data['groups_ms'] = CArrayHelper::renameObjectsKeys($db_groups, ['groupid' => 'id']);
		CArrayHelper::sort($data['groups_ms'], ['name']);

		$data['user'] = ['debug_mode' => $this->getDebugMode()];

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}
}
