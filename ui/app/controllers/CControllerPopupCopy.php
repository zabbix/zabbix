<?php declare(strict_types = 0);
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


class CControllerPopupCopy extends CController {

	protected function checkInput() {
		$fields = [
			'context' => 'required|in host,template',
			'itemids' => 'array_id',
			'triggerids' => 'array_id',
			'graphids' => 'array_id',
			'allowed_ui_conf_hosts' => $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS),
			'allowed_ui_conf_templates' => $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (!$this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS) ||
				!$this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES) ||
				!$this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOST_GROUPS)) {
			return false;
		}

		$action = $this->getAction();

		if ($action == 'popup.copy.items') {
			$entity = API::Item()->get([
				'output' => [],
				'itemids' => $this->getInput('itemids'),
				'editable' => true,
			]);
			$element_count = count($this->getInput('itemids'));
		}

		elseif ($action == 'popup.copy.triggers') {
			$entity = API::Trigger()->get([
				'output' => [],
				'triggerids' => $this->getInput('triggerids'),
				'editable' => true,
			]);
			$element_count = count($this->getInput('triggerids'));
		}

		elseif ($action == 'popup.copy.graphs') {
			$entity = API::Graph()->get([
				'output' => [],
				'graphids' => $this->getInput('graphids'),
				'editable' => true,
			]);
			$element_count = count($this->getInput('graphids'));
		}

		return $element_count === count($entity);
	}

	protected function doAction() {
		$data = [
			'action' => $this->getAction(),
			'form_refresh' => getRequest('form_refresh')
		];

		if ($data['action'] === 'popup.copy.items') {
			$data['itemids'] = $this->getInput('itemids');
		}
		elseif ($data['action'] === 'popup.copy.triggers') {
			$data['triggerids'] =  $this->getInput('triggerids');
		}
		elseif ($data['action'] === 'popup.copy.graphs') {
			$data['graphids'] = $this->getInput('graphids');
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
