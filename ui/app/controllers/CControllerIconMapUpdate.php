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


class CControllerIconMapUpdate extends CController {

	protected function checkInput() {
		$fields = [
			'iconmapid' => 'fatal|required|db icon_map.iconmapid',
			'iconmap'   => 'required|array'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if ($this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL)) {
			return (bool) API::IconMap()->get([
				'output' => [],
				'iconmapids' => $this->getInput('iconmapid')
			]);
		}

		return false;
	}

	protected function doAction() {
		$iconmap = $this->getInput('iconmap') + ['mappings' => []];
		$iconmap['iconmapid'] = $this->getInput('iconmapid');

		$result = (bool) API::IconMap()->update($iconmap);

		if ($result) {
			$response = new CControllerResponseRedirect(
				(new CUrl('zabbix.php'))->setArgument('action', 'iconmap.list')
			);
			CMessageHelper::setSuccessTitle(_('Icon map updated'));
		}
		else {
			$response = new CControllerResponseRedirect(
				(new CUrl('zabbix.php'))
					->setArgument('action', 'iconmap.edit')
					->setArgument('iconmapid', $iconmap['iconmapid'])
			);
			$form_data = $this->getInputAll();
			if (!array_key_exists('mappings', $form_data['iconmap'])) {
				$form_data['iconmap']['mappings'] = [];
			}
			$response->setFormData($form_data);
			CMessageHelper::setErrorTitle(_('Cannot update icon map'));
		}

		$this->setResponse($response);
	}
}
