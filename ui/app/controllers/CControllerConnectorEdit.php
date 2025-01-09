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


class CControllerConnectorEdit extends CController {

	/**
	 * @var array|null
	 */
	private ?array $connector = null;

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'connectorid' =>	'db connector.connectorid'
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

	/**
	 * @throws APIException
	 */
	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL)) {
			return false;
		}

		if ($this->hasInput('connectorid')) {
			$db_connectors = API::Connector()->get([
				'output' => ['connectorid', 'name', 'protocol', 'data_type', 'url', 'max_records', 'item_value_type',
					'max_senders', 'max_attempts', 'attempt_interval', 'timeout', 'http_proxy', 'authtype', 'username',
					'password', 'token', 'verify_peer', 'verify_host', 'ssl_cert_file', 'ssl_key_file',
					'ssl_key_password', 'description', 'status', 'tags_evaltype'
				],
				'selectTags' => ['tag', 'operator', 'value'],
				'connectorids' => $this->getInput('connectorid')
			]);
			$db_connector = reset($db_connectors);

			if (!$db_connector) {
				return false;
			}

			$this->connector = $db_connector;
		}

		return true;
	}

	/**
	 * @throws Exception
	 */
	protected function doAction(): void {
		$db_defaults = DB::getDefaults('connector');

		if ($this->connector !== null) {
			$data = [
				'connectorid' => $this->connector['connectorid'],
				'form' => [
					'name' => $this->connector['name'],
					'protocol' => $this->connector['protocol'],
					'data_type' => (int) $this->connector['data_type'],
					'url' => $this->connector['url'],
					'item_value_type' => (int) $this->connector['item_value_type'],
					'authtype' => (int) $this->connector['authtype'],
					'username' => $this->connector['username'],
					'password' => $this->connector['password'],
					'token' => $this->connector['token'],
					'http_proxy' => $this->connector['http_proxy'],
					'max_records_mode' => $this->connector['max_records'] == 0 ? 0 : 1,
					'max_records' => (int) $this->connector['max_records'],
					'max_senders' => (int) $this->connector['max_senders'],
					'max_attempts' => (int) $this->connector['max_attempts'],
					'attempt_interval' => $this->connector['attempt_interval'],
					'timeout' => $this->connector['timeout'],
					'verify_peer' => (int) $this->connector['verify_peer'],
					'verify_host' => (int) $this->connector['verify_host'],
					'ssl_cert_file' => $this->connector['ssl_cert_file'],
					'ssl_key_file' => $this->connector['ssl_key_file'],
					'ssl_key_password' => $this->connector['ssl_key_password'],
					'description' => $this->connector['description'],
					'status' => (int) $this->connector['status'],
					'tags_evaltype' => (int) $this->connector['tags_evaltype'],
					'tags' => $this->connector['tags']
				]
			];

			if ($data['form']['tags']) {
				CArrayHelper::sort($data['form']['tags'], ['tag', 'value', 'operator']);
				$data['form']['tags'] = array_values($data['form']['tags']);
			}
			else {
				$data['form']['tags'] = [['tag' => '', 'operator' => CONDITION_OPERATOR_EQUAL, 'value' => '']];
			}
		}
		else {
			$data = [
				'connectorid' => null,
				'form' => [
					'name' => $db_defaults['name'],
					'protocol' => (int) $db_defaults['protocol'],
					'data_type' => (int) $db_defaults['data_type'],
					'url' => $db_defaults['url'],
					'item_value_type' => (int) $db_defaults['item_value_type'],
					'authtype' => (int) $db_defaults['authtype'],
					'username' => $db_defaults['username'],
					'password' => $db_defaults['password'],
					'token' => $db_defaults['token'],
					'max_records_mode' => 0,
					'max_records' => (int) $db_defaults['max_records'],
					'max_senders' => (int) $db_defaults['max_senders'],
					'max_attempts' => (int) $db_defaults['max_attempts'],
					'attempt_interval' => $db_defaults['attempt_interval'],
					'timeout' => $db_defaults['timeout'],
					'http_proxy' => $db_defaults['http_proxy'],
					'verify_peer' => (int) $db_defaults['verify_peer'],
					'verify_host' => (int) $db_defaults['verify_host'],
					'ssl_cert_file' => $db_defaults['ssl_cert_file'],
					'ssl_key_file' => $db_defaults['ssl_key_file'],
					'ssl_key_password' => $db_defaults['ssl_key_password'],
					'description' => $db_defaults['description'],
					'status' => $db_defaults['status'],
					'tags_evaltype' => (int) $db_defaults['tags_evaltype'],
					'tags' => [['tag' => '', 'operator' => CONDITION_OPERATOR_EQUAL, 'value' => '']]
				]
			];
		}

		$data['user'] = ['debug_mode' => $this->getDebugMode()];

		$this->setResponse(new CControllerResponseData($data));
	}
}
