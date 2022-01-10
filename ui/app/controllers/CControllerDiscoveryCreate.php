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


class CControllerDiscoveryCreate extends CController {

	protected function checkInput() {
		$fields = [
			'name'                => 'required|db drules.name|not_empty',
			'proxy_hostid'        => 'db drules.proxy_hostid',
			'iprange'             => 'required|db drules.iprange|not_empty|flags '.P_CRLF,
			'delay'               => 'required|db drules.delay|not_empty',
			'status'              => 'db drules.status|in '.implode(',', [DRULE_STATUS_ACTIVE, DRULE_STATUS_DISABLED]),
			'uniqueness_criteria' => 'string',
			'host_source'         => 'string',
			'name_source'         => 'string',
			'dchecks'             => 'required|array',
			'form_refresh'        => 'int32'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			switch ($this->getValidationError()) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
						->setArgument('action', 'discovery.edit')
					);
					$response->setFormData($this->getInputAll());
					CMessageHelper::setErrorTitle(_('Cannot create discovery rule'));
					$this->setResponse($response);
					break;

				case self::VALIDATION_FATAL_ERROR:
					$this->setResponse(new CControllerResponseFatal());
					break;
			}
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_DISCOVERY);
	}

	protected function doAction() {
		$drule = [];
		$this->getInputs($drule, ['name', 'proxy_hostid', 'iprange', 'delay', 'status', 'dchecks']);
		$uniq = $this->getInput('uniqueness_criteria', 0);

		foreach ($drule['dchecks'] as $dcnum => $check) {
			if (substr($check['dcheckid'], 0, 3) === 'new') {
				unset($drule['dchecks'][$dcnum]['dcheckid']);
			}

			$drule['dchecks'][$dcnum]['uniq'] = ($uniq == $dcnum) ? 1 : 0;
		}

		$result = API::DRule()->create($drule);

		if ($result) {
			$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
				->setArgument('action', 'discovery.list')
				->setArgument('page', CPagerHelper::loadPage('discovery.list', null))
			);
			$response->setFormData(['uncheck' => '1']);
			CMessageHelper::setSuccessTitle(_('Discovery rule created'));
		}
		else {
			$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
				->setArgument('action', 'discovery.edit')
			);
			$response->setFormData($this->getInputAll());
			CMessageHelper::setErrorTitle(_('Cannot create discovery rule'));
		}

		$this->setResponse($response);
	}
}
