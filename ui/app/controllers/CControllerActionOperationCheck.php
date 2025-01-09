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


class CControllerActionOperationCheck extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'operation' =>	'array',
			'actionid' =>	'db actions.actionid',
			'row_index' =>	'int32'
		];

		$ret = $this->validateInput($fields) && $this->validateOperation();

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

	protected function validateOperation(): bool {
		$operation = $this->getInput('operation', []);
		$required_fields = ['eventsource', 'recovery', 'operationtype'];

		foreach ($required_fields as $field) {
			if (!array_key_exists($field, $operation)) {
				error(_s('Field "%1$s" is mandatory.', $field));

				return false;
			}
		}

		$eventsource = $operation['eventsource'];
		$recovery = $operation['recovery'];
		$operationtype = preg_replace('[\D]', '', $operation['operationtype']);
		$allowed_operations = getAllowedOperations($eventsource);

		if (preg_match('/\bscriptid\b/', $operation['operationtype'])) {
			$operationtype = OPERATION_TYPE_COMMAND;
		}

		if (!array_key_exists($recovery, $allowed_operations)
				|| !in_array($operationtype, $allowed_operations[$recovery])) {
			error(_s('Incorrect action operation type "%1$s" for event source "%2$s".', $operationtype, $eventsource));

			return false;
		}

		$default_msg_validator = new CLimitedSetValidator(['values' => [0, 1]]);

		if ($recovery == ACTION_OPERATION) {
			if ((array_key_exists('esc_step_from', $operation) || array_key_exists('esc_step_to', $operation))
					&& (!array_key_exists('esc_step_from', $operation)
					|| !array_key_exists('esc_step_to', $operation))) {
				error(_('Parameters "esc_step_from" and "esc_step_to" must be set together.'));

				return false;
			}

			if (array_key_exists('esc_step_from', $operation) && array_key_exists('esc_step_to', $operation)) {
				if ($operation['esc_step_from'] < 1 || $operation['esc_step_to'] < 0) {
					error(_('Incorrect action operation escalation step values.'));

					return false;
				}

				if ($operation['esc_step_from'] > $operation['esc_step_to'] && $operation['esc_step_to'] != 0) {
					error(_('Incorrect action operation escalation step values.'));

					return false;
				}
			}

			if (array_key_exists('esc_period', $operation)
					&& !validateTimeUnit($operation['esc_period'], SEC_PER_MIN, SEC_PER_WEEK, true, $error,
					['usermacros' => true])) {
				error(_s('Incorrect value for field "%1$s": %2$s.', _('Step duration'), $error));

				return false;
			}
		}

		switch ($operationtype) {
			case OPERATION_TYPE_MESSAGE:
				$has_users = array_key_exists('opmessage_usr', $operation) && $operation['opmessage_usr'];
				$has_usrgrps = array_key_exists('opmessage_grp', $operation) && $operation['opmessage_grp'];

				if (!$has_users && !$has_usrgrps) {
					error(_('No recipients specified for action operation message.'));

					return false;
				}
				// break; is not missing here

			case OPERATION_TYPE_UPDATE_MESSAGE:
				$message = array_key_exists('opmessage', $operation) ? $operation['opmessage'] : [];

				if (array_key_exists('default_msg', $message)
						&& (!$default_msg_validator->validate($message['default_msg']))) {
					error(_s('Incorrect value "%1$s" for "%2$s" field.', $message['default_msg'], _('Custom message')));

					return false;
				}
				break;

			case OPERATION_TYPE_COMMAND:
				$scriptid = preg_replace('[\D]', '', $operation['operationtype']);
				$operation['opcommand'] = ['scriptid' => $scriptid];

				if (!array_key_exists('scriptid', $operation['opcommand']) || !$operation['opcommand']['scriptid']) {
					error(_('No script specified for action operation command.'));

					return false;
				}

				if ($eventsource == EVENT_SOURCE_SERVICE) {
					break;
				}

				$has_groups = array_key_exists('opcommand_grp', $operation) && $operation['opcommand_grp'];
				$has_hosts = array_key_exists('opcommand_hst', $operation) && $operation['opcommand_hst'];

				if (!$has_groups && !$has_hosts) {
					error(_('No targets specified for action operation global script.'));

					return false;
				}
				break;

			case OPERATION_TYPE_GROUP_ADD:
			case OPERATION_TYPE_GROUP_REMOVE:
				if (!array_key_exists('opgroup', $operation) || !$operation['opgroup']) {
					error(_('Operation has no group to operate.'));

					return false;
				}
				break;

			case OPERATION_TYPE_TEMPLATE_ADD:
			case OPERATION_TYPE_TEMPLATE_REMOVE:
				if (!array_key_exists('optemplate', $operation) || !$operation['optemplate']) {
					error(_('Operation has no template to operate.'));

					return false;
				}
				break;

			case OPERATION_TYPE_HOST_INVENTORY:
				if (!array_key_exists('opinventory', $operation)
						|| !array_key_exists('inventory_mode', $operation['opinventory'])) {
					error(_('No inventory mode specified for action operation.'));

					return false;
				}

				if ($operation['opinventory']['inventory_mode'] != HOST_INVENTORY_MANUAL
						&& $operation['opinventory']['inventory_mode'] != HOST_INVENTORY_AUTOMATIC) {
					error(_('Incorrect inventory mode in action operation.'));

					return false;
				}
				break;

			case OPERATION_TYPE_HOST_TAGS_ADD:
			case OPERATION_TYPE_HOST_TAGS_REMOVE:
				// At least one tag must exist with name and must be unique. Skips checking completely empty rows.

				$tags = [];

				if (array_key_exists('optag', $operation)) {
					foreach ($operation['optag'] as $optag) {
						$tag = trim($optag['tag']);
						$value = trim($optag['value']);

						if ($tag === '' && $value === '') {
							continue;
						}

						if ($tag === '' && $value !== '') {
							error(_s('Incorrect value for field "%1$s": %2$s.', _('Tag'), _('cannot be empty')));

							return false;
						}

						if (array_key_exists($tag, $tags) && $tags[$tag] === $value) {
							error(_s('Incorrect value for field "%1$s": %2$s.', _('Tag'),
								_s('value "%1$s" already exists', '(tag, value)=('.$tag.', '.$value.')'))
							);

							return false;
						}

						$tags[$tag] = $value;
					}
				}

				if (!$tags) {
					error(_s('Incorrect value for field "%1$s": %2$s.', _('Tag'), _('cannot be empty')));

					return false;
				}
				break;
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
		$operation = $this->getInput('operation');

		if (preg_match('/\bscriptid\b/', $operation['operationtype'])) {
			$operation['opcommand']['scriptid'] = preg_replace('[\D]', '', $operation['operationtype']);
			$operationtype = OPERATION_TYPE_COMMAND;

			if (array_key_exists('opcommand_hst', $operation)) {
				foreach ($operation['opcommand_hst'] as $host) {
					if (is_array($host['hostid']) && array_key_exists('current_host', $host['hostid'])) {
						$operation['opcommand_hst'][0]['hostid'] = 0;
					}
				}
			}
		}
		else {
			$operationtype = (int) preg_replace('[\D]', '', $operation['operationtype']);
		}

		if (array_key_exists('opmessage', $operation)) {
			if (!array_key_exists('default_msg', $operation['opmessage'])
					&& ($operationtype == OPERATION_TYPE_MESSAGE
					|| $operationtype == OPERATION_TYPE_RECOVERY_MESSAGE
					|| $operationtype == OPERATION_TYPE_UPDATE_MESSAGE)) {
				$operation['opmessage']['default_msg'] = '1';

				unset($operation['opmessage']['subject'], $operation['opmessage']['message']);
			}
		}

		// When tags are added or removed, trim tag names and values, and remove empty rows.
		if ($operationtype == OPERATION_TYPE_HOST_TAGS_ADD || $operationtype == OPERATION_TYPE_HOST_TAGS_REMOVE) {
			foreach ($operation['optag'] as $idx => &$optag) {
				$tag = trim($optag['tag']);
				$value = trim($optag['value']);

				if ($tag === '' && $value === '') {
					unset($operation['optag'][$idx]);
					continue;
				}

				$optag['tag'] = $tag;
				$optag['value'] = $value;
			}
			unset($optag);
		}

		$operation['operationtype'] = $operationtype;
		$data['operation'] = $operation;
		$data['operation']['row_index'] = $this->getInput('row_index');

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}
}
