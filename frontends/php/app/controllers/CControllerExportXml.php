<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class CControllerExportXml extends CController {

	protected function checkInput() {
		$fields = [
			'action' => 'required|string',
			'backurl' => 'required|string'
		];

		switch (getRequest('action')) {
			case 'export.valuemaps.xml':
				$fields['valuemapids'] = 'required|array_db valuemaps.valuemapid';
				break;

			case 'export.hosts.xml':
				$fields['hosts'] = 'required|array_db hosts.hostid';
				break;

			case 'export.screens.xml':
				$fields['screens'] = 'required|array_db screens.screenid';
				break;

			case 'export.sysmaps.xml':
				$fields['maps'] = 'required|array_db sysmaps.sysmapid';
				break;

			case 'export.templates.xml':
				$fields['templates'] = 'required|array_db hosts.hostid';
				break;

			default:
				$this->setResponse(new CControllerResponseFatal());

				return false;
		}

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		switch ($this->getInput('action')) {
			case 'export.valuemaps.xml':
				return (CWebUser::$data['type'] >= USER_TYPE_SUPER_ADMIN);

			case 'export.hosts.xml':
			case 'export.templates.xml':
				return (CWebUser::$data['type'] >= USER_TYPE_ZABBIX_ADMIN);

			case 'export.screens.xml':
			case 'export.sysmaps.xml':
				return (CWebUser::$data['type'] >= USER_TYPE_ZABBIX_USER);

			default:
				return false;
		}
	}

	protected function doAction() {
		$action = $this->getInput('action');

		switch ($action) {
			case 'export.valuemaps.xml':
				$export = new CConfigurationExport(['valueMaps' => $this->getInput('valuemapids', [])]);
				break;

			case 'export.hosts.xml':
				$export = new CConfigurationExport(['hosts' => $this->getInput('hosts', [])]);
				break;

			case 'export.screens.xml':
				$export = new CConfigurationExport(['screens' => $this->getInput('screens', [])]);
				break;

			case 'export.sysmaps.xml':
				$export = new CConfigurationExport(['maps' => $this->getInput('maps', [])]);
				break;

			case 'export.templates.xml':
				$export = new CConfigurationExport(['templates' => $this->getInput('templates', [])]);
				break;

			default:
				$this->setResponse(new CControllerResponseFatal());

				return;
		}

		$export->setBuilder(new CConfigurationExportBuilder());
		$export->setWriter(CExportWriterFactory::getWriter(CExportWriterFactory::XML));

		$export_data = $export->export();

		if ($export_data === false) {
			// Access denied.

			$response = new CControllerResponseRedirect(
				$this->getInput('backurl', 'zabbix.php?action=dashboard.view'));

			$response->setMessageError(_('No permissions to referred object or it does not exist!'));
		}
		else {
			$response = new CControllerResponseData([
				'main_block' => $export_data,
				'page' => ['file' => 'zbx_export_' . substr($action, 7)]
			]);
		}

		$this->setResponse($response);
	}
}
