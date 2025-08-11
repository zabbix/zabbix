<?php declare(strict_types = 0);
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


class CControllerValidateWithApi extends CController {

	private string $api;
	private string $method;

	protected function init() {
		$this->disableCsrfValidation();
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput() {
		$fields = [
			'method' =>			'required',
			'params' =>			'array',
			'exclude_id' =>		''
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			list($this->api, $this->method) = explode('.', $this->getInput('method')) + [1 => ''];
		}

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

	protected function checkPermissions(): bool {
		if (in_array($this->method, ['get'])) {
			return true;
		}

		return false;
	}

	protected function doAction() {
		$response = [];

		try {

			if ($this->method == 'get') {
				$object_exists = CFormValidator::existsAPIObject($this->api, $this->getInput('params'),
					$this->getInput('exclude_id'));

				if ($object_exists) {
					$response += [
						'result' => false,
						'error_msg' => _('This object already exists.')
					];
				}
				else {
					$response += ['result' => true];
				}
			}
		}
		catch (Exception $e) {
			$response += [
				'error' => [
					'code' => $e->getCode(),
					'message' => $e->getMessage()
				]
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($response)]));
	}
}
