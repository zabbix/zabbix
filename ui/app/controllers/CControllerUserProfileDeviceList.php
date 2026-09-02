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


class CControllerUserProfileDeviceList extends CController {

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'sort' =>					'in name,uuid,activated_at,lastaccess',
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
				|| !CWebUser::checkAccess(CRoleHelper::DEVICES_ACCESS)) {
			return false;
		}

		return true;
	}

	private function getProfilePrefix(): string {
		return 'web.userprofile.device.list.';
	}

	protected function getProfileFilters(): array {
		$prefix = $this->getProfilePrefix();

		$filter = [
			'sort'			=> CProfile::get($prefix.'sort', 'lastaccess'),
			'sortorder'		=> CProfile::get($prefix.'sortorder', ZBX_SORT_UP)
		];

		return $filter;
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

	private function getFilter(): array {
		$filter = $this->getProfileFilters();

		if ($this->getInput('sort', $filter['sort']) !== $filter['sort']
				|| $this->getInput('sortorder', $filter['sortorder']) !== $filter['sortorder']) {
			$this->getInputs($filter, ['sort', 'sortorder']);
			$this->updateProfileSort();
		}

		return $filter;
	}

	private function getDevices(array $filter): array {
		$options = [
			'userids' => CWebUser::$data['userid'],
			'filter' => ['status' => ZBX_DEVICE_STATUS_ACTIVATED],
			'limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1
		];

		$devices = API::Device()->get($options);

		CArrayHelper::sort($devices, [
			['field' => $filter['sort'], 'order' => $filter['sortorder']]
		]);

		return $devices;
	}

	protected function doAction(): void {
		$page = $this->getInput('page', 1);
		$filter = $this->getFilter();
		$view_url = (new CUrl('zabbix.php'))->setArgument('action', 'userprofile.device.list');
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
				CRoleHelper::DEVICES_ACTIONS_MANAGE_OWN => $this->checkAccess(CRoleHelper::DEVICES_ACTIONS_MANAGE_OWN)
			]
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Devices'));
		$this->setResponse($response);
	}
}
