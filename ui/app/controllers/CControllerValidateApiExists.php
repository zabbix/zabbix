<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CControllerValidateApiExists extends CController {

	protected function init() {
		$this->disableCsrfValidation();
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
	}

	public static function getValidationRules(): array {
		$api_services = ['host', 'template', 'item', 'itemprototype', 'discoveryrule', 'discoveryruleprototype',
			'hostgroup', 'templategroup', 'iconmap', 'image', 'mediatype', 'proxy', 'proxygroup', 'regexp', 'sla',
			'token', 'user', 'role', 'usergroup', 'hostprototype', 'maintenance', 'connector', 'correlation',
			'graph', 'graphprototype', 'report', 'script'
		];

		return ['object', 'fields' => [
			'validations' => ['objects', 'required', 'not_empty', 'fields' => [
				'api' => ['string', 'required', 'in' => $api_services],
				'method' => ['string', 'required', 'in' => ['get']],
				'field' => ['string', 'required'],
				'options' => ['object', 'required', 'fields' => [
					'filter' => [
						['object', 'fields' => [
							'name' => ['string'],
							'host' => ['string']
						], 'when' => ['../api', 'in' => ['host', 'template', 'hostprototype']]],
						['object', 'fields' => [
							'key_' => ['string', 'required', 'not_empty'],
							'hostid' => ['id', 'required']
						], 'when' => ['../api', 'in' => ['item', 'itemprototype', 'discoveryrule',
							'discoveryruleprototype'
						]]],
						['object', 'fields' => [
							'name' => ['string', 'required', 'not_empty'],
							'hostid' => ['id', 'required']
						], 'when' => ['../api', 'in' => ['graph', 'graphprototype']]],
						['object', 'fields' => [
							'name' => ['string', 'required', 'not_empty'],
							'userid' => ['id', 'required']
						], 'when' => ['../api', 'in' => ['token']]],
						['object', 'fields' => [
							'name' => ['string', 'required', 'not_empty'],
							'menu_path' => ['string']
						], 'when' => ['../api', 'in' => ['script']]],
						['object', 'fields' => [
							'name' => ['string', 'required', 'not_empty']
						], 'when' => ['../api', 'in' => ['hostgroup', 'templategroup', 'iconmap', 'image', 'mediatype',
							'proxy', 'proxygroup', 'regexp', 'sla', 'role', 'maintenance', 'connector', 'correlation',
							'usergroup', 'report'
						]]],
						['object', 'fields' => [
							'username' => ['string', 'required', 'not_empty']
						], 'when' => ['../api', 'in' => ['user']]]
					],
					'discoveryids' => ['id', 'required', 'when' => ['../api', 'in' => ['hostprototype']]]
				]],
				'exclude_id' => ['id'],
				'error_msg' => ['string']
			]]
		]];
	}

	protected function checkInput() {
		$ret = $this->validateInput($this->getValidationRules());

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => $this->getValidationError()
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return true;
	}

	protected function doAction() {
		$response = [];
		$errors = [];

		try {
			foreach ($this->getInput('validations') as $validation) {
				$object_exists = CFormValidator::existsAPIObject($validation['api'], $validation['options'],
					array_key_exists('exclude_id', $validation) ? $validation['exclude_id'] : null
				);

				if ($object_exists) {
					$errors[] = [
						'field' => $validation['field'],
						'message' => array_key_exists('error_msg', $validation)
							? $validation['error_msg']
							: _('This object already exists.')
					];
				}
			}

			if ($errors) {
				$response += [
					'result' => false,
					'errors' => $errors
				];
			}
			else {
				$response += ['result' => true];
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
