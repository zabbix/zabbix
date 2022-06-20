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
			'authtype' => 'string',
			'context' => 'required|in host,template',
			'itemids' => 'array_id',
			'triggerids' => 'array_id',
			'graphids' => 'array_id'
		];

		return $this->validateInput($fields);
	}

	protected function checkPermissions() {
		$entity = API::Item();
		$action = $this->getAction();

		return (bool) $entity->get([
			'output' => [],
				$action => $this->getInput($action),
				'editable' => true,
		]);
	}

	protected function doAction() {
		$data = [
			'action' => $this->getAction(),
			'form_refresh' => getRequest('form_refresh')
		];

		if ($this->getInput('itemids')) {
			$data['itemids'] = $this->getInput('itemids');
		}
		else if ($this->getInput('triggerids')) {
			$data['triggerids'] =  $this->getInput('triggerids');
		}
		else if ($this->getInput('graphids')) {
			$data['graphids'] = $this->getInput('graphids');
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
