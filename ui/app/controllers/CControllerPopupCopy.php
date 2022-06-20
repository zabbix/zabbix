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

		if ($this->getInput('itemids')) {
			return (bool) $entity->get([
				'output' => [],
				'itemids' => $this->getInput('itemids'),
				'editable' => true,
				'limit' => 1
			]);
		}
		else if ($this->getInput('triggerids')) {
			return (bool) $entity->get([
				'output' => [],
				'triggerids' => $this->getInput('triggerids'),
				'editable' => true,
				'limit' => 1
			]);
		}
		else if ($this->getInput('graphids')) {
			return (bool) $entity->get([
				'output' => [],
				'graphids' => $this->getInput('graphids'),
				'editable' => true,
				'limit' => 1
			]);
		}
	}

	protected function doAction() {
		$this->setResponse($this->form());
	}

	/**
	 * Handle item mass update form initialization.
	 *
	 * @return CControllerResponse
	 */
	protected function form(): CControllerResponse {
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

		return new CControllerResponseData($data);
	}
}
