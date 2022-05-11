<?php
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


class CControllerDiscoveryView extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'sort' =>				'in ip',
			'filter_set' =>			'in 1',
			'filter_rst' =>			'in 1',
			'filter_druleids' =>	'array_id',
			'sortorder' =>			'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_MONITORING_DISCOVERY);
	}

	protected function doAction() {
		$sortField = $this->getInput('sort', CProfile::get('web.discovery.php.sort', 'ip'));
		$sortOrder = $this->getInput('sortorder', CProfile::get('web.discovery.php.sortorder', ZBX_SORT_UP));

		CProfile::update('web.discovery.php.sort', $sortField, PROFILE_TYPE_STR);
		CProfile::update('web.discovery.php.sortorder', $sortOrder, PROFILE_TYPE_STR);

		// filter
		if ($this->hasInput('filter_set')) {
			CProfile::updateArray('web.discovery.filter.druleids', $this->getInput('filter_druleids', []),
				PROFILE_TYPE_ID
			);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::deleteIdx('web.discovery.filter.druleids');
		}

		$filter_druleids = CProfile::getArray('web.discovery.filter.druleids', []);

		/*
		 * Display
		 */
		$data = [
			'sort' => $sortField,
			'sortorder' => $sortOrder,
			'filter' => [
				'druleids' => $filter_druleids,
				'drules' => $filter_druleids
					? CArrayHelper::renameObjectsKeys(API::DRule()->get([
						'output' => ['druleid', 'name'],
						'druleids' => $filter_druleids,
						'filter' => ['status' => DRULE_STATUS_ACTIVE]
					]), ['druleid' => 'id'])
					: []
			],
			'profileIdx' => 'web.discovery.filter',
			'active_tab' => CProfile::get('web.discovery.filter.active', 1)
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Status of discovery'));
		$this->setResponse($response);
	}
}
