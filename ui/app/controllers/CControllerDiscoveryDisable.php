<?php declare(strict_types=1);
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


class CControllerDiscoveryDisable extends CController {

	protected function checkInput() {
		$fields = [
			'druleids' => 'required|array_db drules.druleid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (!$this->checkAccess(CRoleHelper::UI_CONFIGURATION_DISCOVERY)) {
			return false;
		}

		$drules = API::DRule()->get([
			'druleids' => $this->getInput('druleids'),
			'countOutput' => true,
			'editable' => true
		]);

		return ($drules == count($this->getInput('druleids')));
	}

	protected function doAction() {
		$drules = [];

		foreach ($this->getInput('druleids') as $druleid) {
			$drules[] = [
				'druleid' => $druleid,
				'status' => DRULE_STATUS_DISABLED
			];
		}

		$result = API::DRule()->update($drules);
		$updated = count($drules);
		$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
			->setArgument('action', 'discovery.list')
			->setArgument('page', CPagerHelper::loadPage('discovery.list', null))
		);

		if ($result) {
			$response->setFormData(['uncheck' => '1']);
			CMessageHelper::setSuccessTitle(_n('Discovery rule disabled', 'Discovery rules disabled', $updated));
		}
		else {
			CMessageHelper::setErrorTitle(_n('Cannot disable discovery rule', 'Cannot disable discovery rules',
				$updated
			));
		}

		$this->setResponse($response);
	}
}
