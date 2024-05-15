<?php
/*
** Copyright (C) 2001-2024 Zabbix SIA
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


/**
 * Class manages popup forwarding when popup is opened with 'popup' action.
 */
class CControllerPopup extends CController {

	/**
	 * List of supported popups.
	 *
	 * @var array
	 */
	protected $supported_popups;

	/**
	 * Controller instance of the popup.
	 *
	 * @var CController
	 */
	protected $popup_controller;

	protected function init() {
		$this->disableCsrfValidation();

		$this->supported_popups = [
			'acknowledge.edit' => _('Update problem')
		];
	}

	protected function checkInput() {
		$fields = [
			'popup_action' => 'required|in '.implode(',', array_keys($this->supported_popups))
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			/** @var CRouter $router */
			$router = clone APP::Component()->get('router');
			$router->setAction('popup.'.$this->getInput('popup_action'));
			$popup_controller_class = $router->getController();
			$this->popup_controller = new $popup_controller_class;

			$ret = $this->popup_controller->checkInput();
		}

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->popup_controller->checkPermissions();
	}

	protected function doAction() {
		$data = [
			'popup' => [
				'action' => 'popup.'.$this->getInput('popup_action'),
				'options' => $this->popup_controller->getInputAll()
			]
		];

		$response = (new CControllerResponseData($data));
		$response->setTitle($this->supported_popups[$this->getInput('popup_action')]);

		$this->setResponse($response);
	}
}
