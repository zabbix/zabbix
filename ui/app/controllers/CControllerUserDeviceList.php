<?php
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


class CControllerUserDeviceList extends CController {

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'filter_set' =>				'in 1',
			'filter_rst' =>				'in 1',
			'filter_userids' =>			'array_db users.userid',
			'filter_roleids' =>			'array_db role.roleid',
			'filter_usrgrpids' =>		'array_db users_groups.usrgrpid',
			'filter_status' =>			'in '.implode(',', [-1, ZBX_DEVICE_STATUS_NEW, ZBX_DEVICE_STATUS_ACTIVATED, ZBX_DEVICE_STATUS_ORPHANED]),
			'sort' =>					'in name,uuid,activated_at,lastaccess,user_fullname,user_role,status',
			'sortorder' =>				'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'page' =>					'ge 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (CWebUser::isGuest() || !CSettingsHelper::isMobileDevicesEnabled()
				|| !CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_LINKED_DEVICES)){
			return false;
		}

		return true;
	}

	private function getProfilePrefix(): string {
		return 'web.user.device.list.';
	}

	protected function getProfileFilters(): array {
		$prefix = $this->getProfilePrefix();

		$filter = [
			'filter_userids'				=> CProfile::getArray($prefix.'filter_userids', []),
			'filter_roleids'				=> CProfile::getArray($prefix.'filter_roleids', []),
			'filter_usrgrpids'				=> CProfile::getArray($prefix.'filter_usrgrpids', []),
			'filter_status'					=> CProfile::get($prefix.'filter_status', -1),
			'sort'							=> CProfile::get($prefix.'sort', 'lastaccess'),
			'sortorder' 					=> CProfile::get($prefix.'sortorder', ZBX_SORT_UP)
		];

		return $filter;
	}

	private function updateProfileFilters(): void {
		$prefix = $this->getProfilePrefix();
		CProfile::updateArray($prefix.'filter_userids', $this->getInput('filter_userids', []), PROFILE_TYPE_ID);
		CProfile::updateArray($prefix.'filter_roleids', $this->getInput('filter_roleids', []), PROFILE_TYPE_ID);
		CProfile::updateArray($prefix.'filter_usrgrpids', $this->getInput('filter_usrgrpids', []), PROFILE_TYPE_ID);
		CProfile::update($prefix.'filter_status', $this->getInput('filter_status', -1), PROFILE_TYPE_INT);

		$this->updateProfileSort();
	}

	private function updateProfileSort(): void {
		$prefix = $this->getProfilePrefix();

		if ($this->hasInput('sort')) {
			CProfile::update($prefix.'sort', $this->getInput('sort'), PROFILE_TYPE_STR);
		}

		if ($this->hasInput('sortorder')) {
			CProfile::update($prefix.'sortorder', $this->getInput('sortorder'), PROFILE_TYPE_STR);
		}
	}

	private function deleteProfileFilters(): void {
		$prefix = $this->getProfilePrefix();

		CProfile::deleteIdx($prefix.'filter_userids');
		CProfile::deleteIdx($prefix.'filter_roleids');
		CProfile::deleteIdx($prefix.'filter_usrgrpids');
		CProfile::deleteIdx($prefix.'filter_status');
	}

	private function getFilter(): array {
		$filter = $this->getProfileFilters() + ['ms_users' => [], 'ms_roles' => [], 'ms_usrgrps' => []];

		if ($filter['filter_userids']) {
			$users = API::User()->get([
				'output' => ['userid', 'username', 'name', 'surname'],
				'userids' => $filter['filter_userids']
			]);

			foreach ($users as $user) {
				$filter['ms_users'][] = ['id' => $user['userid'], 'name' => getUserFullname($user)];
			}
		}

		if ($filter['filter_roleids']) {
			$filter['ms_roles'] = CArrayHelper::renameObjectsKeys(API::Role()->get([
				'output' => ['roleid', 'name'],
				'roleids' => $filter['filter_roleids']
			]), ['roleid' => 'id']);
		}

		if ($filter['filter_usrgrpids']) {
			$filter['ms_usrgrps'] = CArrayHelper::renameObjectsKeys(API::UserGroup()->get([
				'output' => ['usrgrpid', 'name'],
				'usrgrpids' => $filter['filter_usrgrpids']
			]), ['usrgrpid' => 'id']);
		}

		if ($this->getInput('sort', $filter['sort']) !== $filter['sort']
				|| $this->getInput('sortorder', $filter['sortorder']) !== $filter['sortorder']) {
			$this->getInputs($filter, ['sort', 'sortorder']);
			$this->updateProfileSort();
		}

		return $filter;
	}

	private function getDevices(array $filter): array {
		$options = [
			'limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1
		];

		if ($filter['filter_userids']) {
			$options['userids'] = $filter['filter_userids'];
		}

		if ($filter['filter_roleids']) {
			$options['roleids'] = $filter['filter_roleids'];
		}

		if ($filter['filter_usrgrpids']) {
			$options['usrgrpids'] = $filter['filter_usrgrpids'];
		}

		if ($filter['filter_status'] != -1) {
			$options['filter'] = ['status' => $filter['filter_status']];
		}

		$devices = API::Device()->get($options);

		if (count($devices) > 0) {
			$users = API::User()->get([
				'output' => ['userid', 'username', 'name', 'surname'],
				'selectRole' => ['name'],
				'userids' => array_unique(array_column($devices, 'userid')),
				'preservekeys' => true
			]);

			foreach ($devices as &$device) {
				if (array_key_exists($device['userid'], $users)) {
					$device['user_fullname'] = getUserFullname($users[$device['userid']]);
					$device['user_role'] = $users[$device['userid']]['role']['name'];
				}
				else {
					$device['user_fullname'] = _('Inaccessible user');
					$device['user_role'] = '';
				}
			}

			CArrayHelper::sort($devices, [
				['field' => $filter['sort'], 'order' => $filter['sortorder']]
			]);
		}

		return $devices;
	}

	protected function doAction(): void {
		if ($this->hasInput('filter_set')) {
			$this->updateProfileFilters();
		}
		elseif ($this->hasInput('filter_rst')) {
			$this->deleteProfileFilters();
		}

		$page = $this->getInput('page', 1);
		$filter = $this->getFilter();
		$view_url = (new CUrl('zabbix.php'))->setArgument('action', 'user.device.list');
		$devices = $this->getDevices($filter);
		$paging = CPagerHelper::paginate($page, $devices, $filter['sortorder'], $view_url);

		$data = [
			'active_tab' => CProfile::get($this->getProfilePrefix().'filter.active', 1),
			'devices' => $devices,
			'filter' => $filter,
			'paging' => $paging,
			'profileIdx' => $this->getProfilePrefix().'filter',
			'sort' => $filter['sort'],
			'sortorder' => $filter['sortorder'],
			'url' => $view_url->getUrl(),
			'has_access' => [
				CRoleHelper::DEVICES_ACTIONS_MANAGE_USER =>
					$this->checkAccess(CRoleHelper::DEVICES_ACTIONS_MANAGE_USER),
				CRoleHelper::DEVICES_ACTIONS_MANAGE_OWN =>
					$this->checkAccess(CRoleHelper::DEVICES_ACTIONS_MANAGE_OWN)
			],
			'device_statuses' => [
				ZBX_DEVICE_STATUS_NEW => _('New'),
				ZBX_DEVICE_STATUS_ACTIVATED => _('Active'),
				ZBX_DEVICE_STATUS_ORPHANED => _('Orphaned')
			]
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Devices'));
		$this->setResponse($response);
	}
}
