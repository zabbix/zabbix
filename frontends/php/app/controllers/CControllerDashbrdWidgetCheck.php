<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


class CControllerDashbrdWidgetCheck extends CController {

	public function __construct() {
		parent::__construct();
	}

	protected function checkInput() {
		$fields = [
			'type' => 'string|required',
			'name' => 'string',
			'fields' => 'json',
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$form = CWidgetConfig::getForm($this->getInput('type'), $this->getInput('fields', '{}'));

			if ($errors = $form->validate(true)) {
				foreach ($errors as $msg) {
					error($msg);
				}

				$ret = false;
			}
		}

		if (!$ret) {
			$output = [];
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson($output)]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson([])]));
	}
}
