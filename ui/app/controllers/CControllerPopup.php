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


/**
 * Class manages popup forwarding when popup is opened with 'popup' action.
 */
class CControllerPopup extends CController {

	/**
	 * List of supported popups.
	 */
	protected array $supported_popups;

	/**
	 * Controller instance of the popup.
	 */
	protected CController $popup_controller;

	/**
	 * Current action name.
	 */
	private string $action;

	protected function init() {
		$this->disableCsrfValidation();

		$this->supported_popups = [
			'acknowledge.edit' => _('Update problem'),
			'action.edit' => _('Action edit'),
			'connector.edit' => _('Connector edit'),
			'correlation.edit' => _('Correlation edit'),
			'discovery.edit' => _('Discovery rule edit'),
			'host.edit' => _('Host edit'),
			'hostgroup.edit' => _('Host group edit'),
			'item.edit' => _('Item edit'),
			'item.prototype.edit' => _('Item prototype edit'),
			'maintenance.edit' => _('Maintenance edit'),
			'mediatype.edit' => _('Media type edit'),
			'module.edit' => _('Module edit'),
			'proxy.edit' => _('Proxy edit'),
			'proxygroup.edit' => _('Proxy group edit'),
			'templategroup.edit' => _('Template group edit'),
			'script.edit' => _('Script edit'),
			'service.edit' => _('Service edit'),
			'sla.edit' => _('SLA edit'),
			'template.edit' => _('Template edit'),
			'token.edit' => _('Token edit'),
			'trigger.edit' => _('Trigger edit'),
			'trigger.prototype.edit' => _('Trigger prototype edit')
		];
	}

	protected function checkInput() {
		$fields = [
			'popup' => 'required|in '.implode(',', array_keys($this->supported_popups))
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			/** @var CRouter $router */
			$router = clone APP::Component()->get('router');

			$this->action = $this->getInput('popup');
			$router->setAction($this->action);

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
				'action' => $this->action,
				'action_parameters' => $this->popup_controller->getInputAll()
			]
		];

		$response = (new CControllerResponseData($data));
		$response->setTitle($this->supported_popups[$this->getInput('popup')]);

		$this->setResponse($response);
	}
}
