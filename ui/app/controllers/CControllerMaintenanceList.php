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


class CControllerMaintenanceList extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'filter_set'	=> 'in 1',
			'filter_rst'	=> 'in 1',
			'filter_name'	=> 'string',
			'filter_status'	=> 'in '.implode(',', [-1, MAINTENANCE_STATUS_ACTIVE, MAINTENANCE_STATUS_APPROACH, MAINTENANCE_STATUS_EXPIRED]),
			'filter_groups'	=> 'array_db hosts_groups.groupid',
			'sort'			=> 'in '.implode(',', ['name', 'maintenance_type', 'active_since', 'active_till']),
			'sortorder'		=> 'in '.implode(',', [ZBX_SORT_UP, ZBX_SORT_DOWN]),
			'page'			=> 'ge 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_MAINTENANCE);
	}

	protected function doAction(): void {
		if ($this->hasInput('filter_set')) {
			$this->updateProfiles();
		}
		elseif ($this->hasInput('filter_rst')) {
			$this->deleteProfiles();
		}

		$sort_field = $this->getInput('sort', CProfile::get('web.maintenance.list.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.maintenance.list.sortorder', ZBX_SORT_UP));

		CProfile::update('web.maintenance.list.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.maintenance.list.sortorder', $sort_order, PROFILE_TYPE_STR);

		$filter = [
			'name' => CProfile::get('web.maintenance.filter_name', ''),
			'status' => CProfile::get('web.maintenance.filter_status', -1),
			'groups' => CProfile::getArray('web.maintenance.filter_groups', [])
		];

		$filter['groups'] = $filter['groups']
			? CArrayHelper::renameObjectsKeys(
				API::HostGroup()->get([
					'output' => ['groupid', 'name'],
					'groupids' => $filter['groups'],
					'editable' => true,
					'preservekeys' => true
				]),
				['groupid' => 'id']
			)
			: [];

		$filter_groupids = $filter['groups'] ? array_keys($filter['groups']) : null;

		if ($filter_groupids) {
			$filter_groupids = getSubGroups($filter_groupids);
		}

		$data = [
			'sort' => $sort_field,
			'sortorder' => $sort_order,
			'filter' => $filter,
			'filter_profile' => 'web.maintenance.filter',
			'filter_active_tab' => CProfile::get('web.maintenance.filter.active', 1),
			'allowed_edit' => $this->checkAccess(CRoleHelper::ACTIONS_EDIT_MAINTENANCE)
		];

		$options = [
			'output' => ['maintenanceid', 'name', 'maintenance_type', 'active_since', 'active_till', 'description'],
			'search' => [
				'name' => $filter['name'] !== '' ? $filter['name'] : null
			],
			'groupids' => $filter_groupids,
			'editable' => true,
			'sortfield' => $sort_field,
			'sortorder' => $sort_order,
			'limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1,
			'preservekeys' => true
		];

		$data['maintenances'] = API::Maintenance()->get($options);

		foreach ($data['maintenances'] as &$maintenance) {
			if ($maintenance['active_till'] < time()) {
				$maintenance['status'] = MAINTENANCE_STATUS_EXPIRED;
			}
			elseif ($maintenance['active_since'] > time()) {
				$maintenance['status'] = MAINTENANCE_STATUS_APPROACH;
			}
			else {
				$maintenance['status'] = MAINTENANCE_STATUS_ACTIVE;
			}
		}
		unset($maintenance);

		if ($filter['status'] != -1) {
			$data['maintenances'] = array_filter($data['maintenances'],
				static function (array $maintenance) use ($filter): bool {
					return $maintenance['status'] == $filter['status'];
				}
			);
		}

		CArrayHelper::sort($data['maintenances'], [['field' => $sort_field, 'order' => $sort_order]]);

		$page_num = $this->getInput('page', 1);
		CPagerHelper::savePage('maintenance.list', $page_num);
		$data['paging'] = CPagerHelper::paginate($page_num, $data['maintenances'], $sort_order,
			(new CUrl('zabbix.php'))->setArgument('action', 'maintenance.list')
		);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of maintenance periods'));
		$this->setResponse($response);
	}

	private function updateProfiles(): void {
		CProfile::update('web.maintenance.filter_name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
		CProfile::update('web.maintenance.filter_status', $this->getInput('filter_status', -1), PROFILE_TYPE_INT);
		CProfile::updateArray('web.maintenance.filter_groups', $this->getInput('filter_groups', []), PROFILE_TYPE_ID);
	}

	private function deleteProfiles(): void {
		CProfile::delete('web.maintenance.filter_name');
		CProfile::delete('web.maintenance.filter_status');
		CProfile::deleteIdx('web.maintenance.filter_groups');
	}
}
