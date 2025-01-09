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


class CControllerPopupActionOperationEdit extends CController {

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'eventsource' =>	'required|db actions.eventsource|in '.implode(',', [
									EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION,
									EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE
								]),
			'recovery' =>		'db operations.recovery',
			'actionid' =>		'db actions.actionid',
			'operationid' =>	'db operations.operationid',
			'operationtype' =>	'db operations.operationtype',
			'data' =>			'array',
			'row_index' =>		'int32'
		];

		$ret = $this->validateInput($fields) && $this->validateInputConstraints();

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function validateInputConstraints(): bool {
		$eventsource = $this->getInput('eventsource');
		$recovery = $this->getInput('recovery');
		$allowed_operations = getAllowedOperations($eventsource);

		if (!array_key_exists($recovery, $allowed_operations)) {
			error(_('Unsupported operation.'));
			return false;
		}

		return true;
	}

	protected function checkPermissions(): bool {
		if ($this->getUserType() >= USER_TYPE_ZABBIX_ADMIN) {
			if (!$this->getInput('actionid', '0')) {
				return true;
			}

			return (bool) API::Action()->get([
				'output' => [],
				'actionids' => $this->getInput('actionid')
			]);
		}

		return false;
	}

	protected function doAction(): void {
		$operation = $this->getInput('data', []) + $this->defaultOperationObject();
		$eventsource = (int) $this->getInput('eventsource');
		$recovery = (int) $this->getInput('recovery');
		$operation_types = $this->popupConfigOperationTypes($eventsource, $recovery);

		$scripts_with_warning = [];

		foreach ($operation_types as $type) {
			$operation_type[$type['value']] = $type['name'];

			if ($type['has_warning']) {
				$scripts_with_warning[] = $type['value'];
			}
		}

		$media_types = API::MediaType()->get(['output' => ['mediatypeid', 'name', 'status']]);
		CArrayHelper::sort($media_types, ['name']);
		$media_types = array_values($media_types);
		$operation['row_index'] = $this->getInput('row_index', -1);

		$operation_data = $this->getData($operation);

		if (array_key_exists('user_group', $operation_data)) {
			$operation['opmessage_grp'] = $operation_data['user_group'];
		}

		if (array_key_exists('users', $operation_data)) {
			$operation['opmessage_usr'] = $operation_data['users'];
		}

		if (array_key_exists('opcommand_hst', $operation)) {
			$current_host = null;

			foreach ($operation['opcommand_hst'] as $opcommand_hst) {
				if ($opcommand_hst['hostid'] == 0) {
					$current_host = ['id' => 0];
				}
			}

			if (array_key_exists('opcommand_hst', $operation_data)) {
				$operation['opcommand_hst'] = $operation_data['opcommand_hst'];
			}

			$operation['opcommand_hst'][] = $current_host;
		}

		if (array_key_exists('opcommand_grp', $operation_data)) {
			$operation['opcommand_grp'] = $operation_data['opcommand_grp'];
		}

		if (array_key_exists('opgroup', $operation_data)) {
			$operation['opgroup'] = $operation_data['opgroup'];
		}

		if (array_key_exists('optemplate', $operation_data)) {
			$operation['optemplate'] = $operation_data['optemplate'];
		}

		if (array_key_exists('optag', $operation_data)) {
			$operation['optag'] = $operation_data['optag'];
		}

		$data = [
			'eventsource' => $eventsource,
			'actionid' => $this->getInput('actionid', 0),
			'recovery' => $recovery,
			'operation' => $operation,
			'operation_types' => $operation_type,
			'scripts_with_warning' => $scripts_with_warning,
			'mediatype_options' => $media_types,
			'user' => ['debug_mode' => $this->getDebugMode()]
		];

		$this->setResponse(new CControllerResponseData($data));
	}

	/**
	 * Returns necessary operation 'element' data for operation popup multiselects.
	 *
	 * @param array $operation  Operation object.
	 *
	 * @return array
	 */
	private function getData(array $operation): array {
		$result = [];

		if ($operation['opmessage_grp']) {
			$user_groups = CArrayHelper::renameObjectsKeys(API::UserGroup()->get([
				'output' => ['usrgrpid', 'name'],
				'usrgrpids' => array_column($operation['opmessage_grp'], 'usrgrpid')
			]), ['usrgrpid' => 'id']);

			CArrayHelper::sort($user_groups, ['name']);

			$result['user_group'] = array_values($user_groups);
		}

		if ($operation['opmessage_usr']) {
			$users = CArrayHelper::renameObjectsKeys(API::User()->get([
				'output' => ['userid', 'username', 'name', 'surname'],
				'userids' => array_column($operation['opmessage_usr'], 'userid')
			]), ['userid' => 'id']);

			$fullnames = [];

			foreach ($users as $user) {
				$fullnames[$user['id']] = getUserFullname($user);
				$user['name'] = $fullnames[$user['id']];
				$result['users'][] = $user;
			}
			CArrayHelper::sort($result['users'], ['name']);
		}

		if ($operation['opcommand_hst']) {
			if ($operation['opcommand_hst'][0]['hostid'] == 0) {
				unset($operation['opcommand_hst'][0]);
			}

			if (count($operation['opcommand_hst']) > 0) {
				$hosts = CArrayHelper::renameObjectsKeys(API::Host()->get([
					'output' => ['hostid', 'name'],
					'hostids' => array_column($operation['opcommand_hst'], 'hostid')
				]), ['hostid' => 'id']);

				CArrayHelper::sort($hosts, ['name']);

				$result['opcommand_hst'] = array_values($hosts);
			}
		}

		if ($operation['opcommand_grp']) {
			$groups = CArrayHelper::renameObjectsKeys(API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => array_column($operation['opcommand_grp'], 'groupid')
			]), ['groupid' => 'id']);

			CArrayHelper::sort($groups, ['name']);

			$result['opcommand_grp'] = array_values($groups);
		}

		if ($operation['opgroup']) {
			$groups = CArrayHelper::renameObjectsKeys(API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => array_column($operation['opgroup'], 'groupid')
			]), ['groupid' => 'id']);

			CArrayHelper::sort($groups, ['name']);

			$result['opgroup'] = array_values($groups);
		}

		if ($operation['optemplate']) {
			$templates = CArrayHelper::renameObjectsKeys(API::Template()->get([
				'output' => ['templateid', 'name'],
				'templateids' => array_column($operation['optemplate'], 'templateid')
			]), ['templateid' => 'id']);

			CArrayHelper::sort($templates, ['name']);

			$result['optemplate'] = array_values($templates);
		}

		if ($operation['optag']) {
			CArrayHelper::sort($operation['optag'], ['tag', 'value']);

			$result['optag'] = array_values($operation['optag']);
		}

		return $result;
	}

	/**
	 * Returns default Operation.
	 *
	 * @return array
	 */
	private function defaultOperationObject(): array {
		return [
			'opmessage_usr' => [],
			'opmessage_grp' => [],
			'opmessage' => [
				'subject' => '',
				'message' => '',
				'mediatypeid' => '0',
				'default_msg' => '1'
			],
			'operationtype' => '0',
			'esc_step_from' => '1',
			'esc_step_to' => '1',
			'esc_period' => '0',
			'opcommand_hst' => [],
			'opcommand_grp' => [],
			'evaltype' => (string) CONDITION_EVAL_TYPE_AND_OR,
			'opconditions' => [],
			'opgroup' => [],
			'optag' => [],
			'optemplate' => [],
			'opinventory' => [
				'inventory_mode' => (string) HOST_INVENTORY_MANUAL
			],
			'opcommand' => [
				'scriptid' => '0'
			]
		];
	}

	/**
	 * Returns "operation type" configuration fields for given operation in given source.
	 *
	 * @param int $eventsource  Action event source.
	 * @param int $recovery     Action operation mode. Possible values:
	 *                          ACTION_OPERATION, ACTION_RECOVERY_OPERATION, ACTION_UPDATE_OPERATION
	 *
	 * @return array
	 */
	private function popupConfigOperationTypes(int $eventsource, int $recovery): array {
		$operation_type_options = [];
		$scripts_allowed = false;

		// First determine if scripts are allowed for this action type.
		foreach (getAllowedOperations($eventsource)[$recovery] as $operation_type) {
			if ($operation_type == OPERATION_TYPE_COMMAND) {
				$scripts_allowed = true;

				break;
			}
		}

		// Then remove Remote command from dropdown list.
		foreach (getAllowedOperations($eventsource)[$recovery] as $operation_type) {
			if ($operation_type == OPERATION_TYPE_COMMAND) {
				continue;
			}

			$operation_type_options[] = [
				'value' => 'cmd['.$operation_type.']',
				'name' => operation_type2str($operation_type),
				'has_warning' => false
			];
		}

		if ($scripts_allowed) {
			$db_scripts = API::Script()->get([
				'output' => ['scriptid', 'name', 'type', 'execute_on'],
				'filter' => ['scope' => ZBX_SCRIPT_SCOPE_ACTION]
			]);

			if ($db_scripts) {
				CArrayHelper::sort($db_scripts, ['name']);

				foreach ($db_scripts as $db_script) {
					$has_warning = !CSettingsHelper::isGlobalScriptsEnabled()
						&& $db_script['type'] == ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT
						&& ($db_script['execute_on'] == ZBX_SCRIPT_EXECUTE_ON_SERVER
							|| $db_script['execute_on'] == ZBX_SCRIPT_EXECUTE_ON_PROXY);

					$operation_type_options[] = [
						'value' => 'scriptid['.$db_script['scriptid'].']',
						'name' => $db_script['name'],
						'has_warning' => $has_warning
					];
				}
			}
		}

		return $operation_type_options;
	}
}
