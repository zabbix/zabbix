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


class CControllerExport extends CController {

	protected function checkInput() {
		$fields = [
			'action' =>			'required|string',
			'backurl' =>		'required|string',
			'valuemapids' =>	'not_empty|array_db valuemaps.valuemapid',
			'hostids' =>		'not_empty|array_db hosts.hostid',
			'mediatypeids' =>	'not_empty|array_db media_type.mediatypeid',
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
				return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_MEDIA_TYPES);

			case 'export.valuemaps':
				return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);

			case 'export.hosts':
				return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS);

			case 'export.templates':
				return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);

			case 'export.sysmaps':
				return $this->checkAccess(CRoleHelper::UI_MONITORING_MAPS);

			default:
				return false;
		}
	}

	protected function doAction() {
		$action = $this->getInput('action');
		$params = [
			'format' => $this->getInput('format', CExportWriterFactory::YAML),
			'prettyprint' => true,
			'options' => []
		];

		switch ($action) {
			case 'export.valuemaps':
				$params['options']['valueMaps'] = $this->getInput('valuemapids', []);
				break;

			case 'export.hosts':
				$params['options']['hosts'] = $this->getInput('hostids', []);
				break;

			case 'export.mediatypes':
				$params['options']['mediaTypes'] = $this->getInput('mediatypeids', []);
				break;

			case 'export.sysmaps':
				$params['options']['maps'] = $this->getInput('maps', []);
				break;

			case 'export.templates':
				$params['options']['templates'] = $this->getInput('templates', []);
				break;

			default:
				$this->setResponse(new CControllerResponseFatal());

				return;
		}

		$result = API::Configuration()->export($params);

		if ($result) {
			$response = new CControllerResponseData([
				'main_block' => $result,
				'mime_type' => CExportWriterFactory::getMimeType($params['format']),
				'page' => ['file' => 'zbx_export_'.substr($action, 7).'.'.$params['format']]
			]);
		}
		else {
			$response = new CControllerResponseRedirect($this->getInput('backurl', 'zabbix.php?action=dashboard.view'));
			CMessageHelper::setErrorTitle(_('Export failed'));
		}

		$this->setResponse($response);
	}
}
