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


class CControllerHostMassDelete extends CController {

	protected function checkInput(): bool {
		$fields = [
			'ids'       => 'required|array_db hosts.hostid',
			'back_url'   => 'string'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS);
	}

	protected function doAction(): void {
		header('Cache-Control: max-age=1');

		$redirect_url = $this->getInput('back_url', '');
		$hostids = $this->getInput('ids');
		$result = API::Host()->delete($hostids);

		if ($result) {
			uncheckTableRows('hosts');
		}
		else {
			$hostids = API::Host()->get([
				'output' => [],
				'hostids' => $hostids,
				'editable' => true
			]);

			uncheckTableRows('hosts', array_column($hostids, 'hostid'));
		}

		if ($result) {
			CMessageHelper::setSuccessTitle(_('Host deleted'));
		}
		else {
			CMessageHelper::setErrorTitle(_('Cannot delete host'));
		}

		if ($redirect_url === '') {
			$redirect_url = (new CUrl('zabbix.php'))
				->setArgument('action', 'host.list')
				->setArgument('page', CPagerHelper::loadPage('host.list', null));
		}

		$response = new CControllerResponseRedirect($redirect_url);

		$this->setResponse($response);
	}
}
