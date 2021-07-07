<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


class CControllerServiceDelete extends CController {

	protected function checkInput() {
		$fields = [
			'serviceids' => 'required|array_db services.serviceid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (!$this->checkAccess(CRoleHelper::UI_MONITORING_SERVICES)
				|| !$this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SERVICES)) {
			return false;
		}

		$service_count = API::Service()->get([
			'countOutput' => true,
			'serviceids' => $this->getInput('serviceids')
		]);

		return ($service_count == count($this->getInput('serviceids')));
	}

	protected function doAction() {
		$serviceids = $this->getInput('serviceids');

		$result = API::Service()->delete($serviceids);

		$response = new CControllerResponseRedirect(
			(new CUrl('zabbix.php'))
				->setArgument('action', 'service.list')
				->setArgument('page', CPagerHelper::loadPage('service.list', null))
		);

		$deleted = count($serviceids);

		if ($result) {
			$response->setFormData(['uncheck' => '1']);
			CMessageHelper::setSuccessTitle(_n('Service deleted', 'Services deleted', $deleted));
		}
		else {
			CMessageHelper::setErrorTitle(
				_n('Cannot delete service', 'Cannot delete services', $deleted)
			);
		}

		$this->setResponse($response);
	}
}
