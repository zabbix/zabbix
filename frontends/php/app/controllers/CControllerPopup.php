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


/**
 * Class manages popup forwarding when popup is opened with 'popup' action.
 */
class CControllerPopup extends CController {

	/**
	 * Properties for supported popups.
	 *
	 * @var array
	 */
	protected $supported_popups;

	/**
	 * Instance of popup.
	 *
	 * @var CController
	 */
	protected $popup_instance;

	/**
	 * Properties of called popup.
	 *
	 * @var array
	 */
	protected $popup_data;

	protected function init() {
		$this->disableSIDValidation();

		// List of supported popups and properties for each of them.
		$this->supported_popups = [
			'acknowledge.edit' => [
				'title' => _('Update problem'),
				'action' => 'popup.acknowledge.edit',
				'controller' => 'CControllerPopupAcknowledgeEdit'
			]
		];
	}

	protected function checkInput() {
		$fields = [
			'popup_action' => 'required|in acknowledge.edit'
		];

		if (($ret = $this->validateInput($fields)) === true) {
			$this->popup_data = $this->supported_popups[$this->getInput('popup_action')];
			$this->popup_instance = new $this->popup_data['controller']();

			$ret = $this->popup_instance->checkInput();
		}

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER && $this->popup_instance->checkPermissions());
	}

	protected function doAction() {
		$data = [
			'popup' => [
				'action' => $this->popup_data['action'],
				'options' => $this->popup_instance->getInputAll()
			]
		];

		$response = (new CControllerResponseData($data));
		$response->setTitle($this->popup_data['title']);

		$this->setResponse($response);
	}
}
