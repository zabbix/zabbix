<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

	private $sysmapid;

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'druleid' =>	'db drules.druleid',
			'sort' =>		'in ip',
			'sortorder' =>	'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'fullscreen' =>	'in 0,1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if ($this->getUserType() < USER_TYPE_ZABBIX_ADMIN) {
			return false;
		}

		if ($this->hasInput('druleid') && $this->getInput('druleid') != 0) {
			$drules = API::DRule()->get([
				'output' => [],
				'druleids' => [$this->getInput('druleid')],
				'filter' => ['status' => DRULE_STATUS_ACTIVE]
			]);
			if (!$drules) {
				return false;
			}
		}

		return true;
	}

	protected function doAction() {
		$sort = $this->getInput('sort', CProfile::get('web.discovery.php.sort', 'ip'));
		$sortorder = $this->getInput('sortorder', CProfile::get('web.discovery.php.sortorder', ZBX_SORT_UP));

		CProfile::update('web.discovery.php.sort', $sort, PROFILE_TYPE_STR);
		CProfile::update('web.discovery.php.sortorder', $sortorder, PROFILE_TYPE_STR);

		/*
		 * Display
		 */
		$data = [
			'fullscreen' => $this->getInput('fullscreen', 0),
			'druleid' => $this->getInput('druleid', 0),
			'sort' => $sort,
			'sortorder' => $sortorder
		];

		$data['pageFilter'] = new CPageFilter([
			'drules' => ['filter' => ['status' => DRULE_STATUS_ACTIVE]],
			'druleid' => $data['druleid']
		]);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Status of discovery'));
		$this->setResponse($response);
	}
}
