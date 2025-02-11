<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
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


class CControllerExport extends CController {

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput() {
		$fields = [
			'action' =>			'required|string',
			'backurl' =>		'required|string',
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

		if (!CHtmlUrlValidator::validateSameSite($this->getInput('backurl'))) {
			throw new CAccessDeniedException();
		}

		return $ret;
	}

	protected function checkPermissions() {
		switch ($this->getInput('action')) {
			case 'export.mediatypes':
				return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_MEDIA_TYPES);

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
			$response = new CControllerResponseRedirect(
				new CUrl($this->getInput('backurl', 'zabbix.php?action=dashboard.view'))
			);
			CMessageHelper::setErrorTitle(_('Export failed'));
		}

		$this->setResponse($response);
	}
}
