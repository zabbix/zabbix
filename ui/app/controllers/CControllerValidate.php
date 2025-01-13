<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


class CControllerValidate extends CController {

	protected function init() {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableCsrfValidation();
	}

	protected function checkInput() {
		$ret = $this->validateInput(['object', 'fields' => [
			'use' => [],
			'value' => ['string'],
			'jsonrpc' => ['string', 'in' => ['2.0']],
			'id' => []
		]]);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'jsonrpc' => '2.0',
					'error' => [
						'code' => -32600,
						'message' => 'Invalid Request'
					],
					'id' => null
				])])
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		return true;
	}

	protected function doAction() {
		$data = $this->getInputAll();
		$response = [
			'jsonrpc' => '2.0',
			'id' => array_key_exists('id', $data) ? $data['id'] : null
		];

		try {
			if (!array_key_exists('use', $data) || !is_array($data['use'])
					|| !array_key_exists(0, $data['use']) || !is_string($data['use'][0])
					|| !array_key_exists('value', $data) || !is_string($data['value'])) {
				throw new Exception('Invalid Request', -32600);
			}

			CFormValidator::validateUse(['use' => $data['use']], $data['value'], $error);

			$response += ['result' => $error ?? ''];
		}
		catch (Exception $e) {
			$response += [
				'error' => [
					'code' => $e->getCode(),
					'message' => $e->getMessage()
				]
			];
		}

		$this->setResponse(
			(new CControllerResponseData(['main_block' => json_encode($response)]))->disableView()
		);
	}
}
