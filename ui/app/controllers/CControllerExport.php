<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


class CControllerExport extends CController {

	protected function checkInput() {
		$fields = [
			'action' =>			'required|string',
			'backurl' =>		'required|string',
			'valuemapids' =>	'not_empty|array_db valuemaps.valuemapid',
			'hosts' =>			'not_empty|array_db hosts.hostid',
			'mediatypeids' =>	'not_empty|array_db media_type.mediatypeid',
			'screens' =>		'not_empty|array_db screens.screenid',
			'maps' =>			'not_empty|array_db sysmaps.sysmapid',
			'templates' =>		'not_empty|array_db hosts.hostid',
			'format' =>			'in '.implode(',', [CExportWriterFactory::YAML, CExportWriterFactory::XML, CExportWriterFactory::JSON])
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		switch ($this->getInput('action')) {
			case 'export.mediatypes':
			case 'export.valuemaps':
				return (CWebUser::$data['type'] >= USER_TYPE_SUPER_ADMIN);

			case 'export.hosts':
			case 'export.templates':
				return (CWebUser::$data['type'] >= USER_TYPE_ZABBIX_ADMIN);

			case 'export.screens':
			case 'export.sysmaps':
				return (CWebUser::$data['type'] >= USER_TYPE_ZABBIX_USER);

			default:
				return false;
		}
	}

	protected function doAction() {
		$action = $this->getInput('action');
		$format = $this->getInput('format', CExportWriterFactory::YAML);

		switch ($action) {
			case 'export.valuemaps':
				$export = new CConfigurationExport(['valueMaps' => $this->getInput('valuemapids', [])]);
				break;

			case 'export.hosts':
				$export = new CConfigurationExport(['hosts' => $this->getInput('hosts', [])]);
				break;

			case 'export.mediatypes':
				$export = new CConfigurationExport(['mediaTypes' => $this->getInput('mediatypeids', [])]);
				break;

			case 'export.screens':
				$export = new CConfigurationExport(['screens' => $this->getInput('screens', [])]);
				break;

			case 'export.sysmaps':
				$export = new CConfigurationExport(['maps' => $this->getInput('maps', [])]);
				break;

			case 'export.templates':
				$export = new CConfigurationExport(['templates' => $this->getInput('templates', [])]);
				break;

			default:
				$this->setResponse(new CControllerResponseFatal());

				return;
		}

		$export->setBuilder(new CConfigurationExportBuilder());
		$export->setWriter(CExportWriterFactory::getWriter($format));

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
				'mime_type' => CExportWriterFactory::getMimeType($format),
				'page' => ['file' => 'zbx_export_'.substr($action, 7).'.'.$format]
			]);
		}

		$this->setResponse($response);
	}
}
