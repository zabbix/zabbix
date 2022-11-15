<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


class CControllerPopupActionOperationGet extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableSIDvalidation();
	}

	protected function checkInput() {
		$eventsource = [
			EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION,
			EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE
		];

		$fields = [
			'esc_period' =>			'db actions.esc_period|not_empty',
			'operations'=>			'array',
			'recovery_operations'=>	'array',
			'update_operations'=>	'array',
			'new_operation' =>		'array',
			'eventsource' =>		'required|db actions.eventsource|in '.implode(',', $eventsource),
			'actionid'=>			'db actions.actionid'
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

	protected function checkPermissions() {
		if ($this->getUserType() >= USER_TYPE_ZABBIX_ADMIN) {
			if (!$this->getInput('actionid', '0')) {
				return true;
			}

			return (bool) API::Action()->get([
				'output' => [],
				'actionids' => $this->getInput('actionid'),
				'editable' => true
			]);
		}

		return false;
	}

	protected function doAction() {
		$data['esc_period'] = $this->hasInput('esc_period')
			? $this->getInput('esc_period')
			: DB::getDefault('actions', 'esc_period');

		$eventsource = $this->getInput('eventsource');

		$new_operation = $this->hasInput('new_operation')
			? $this->getInput('new_operation')['operation']
			: null;

		if ($new_operation) {
			if ($new_operation['recovery'] == ACTION_OPERATION) {
				$data['recovery'] = ACTION_OPERATION;
				$data['operations'] = $this->hasInput('operations') ? $this->getInput('operations') : [];

				if ((int) $new_operation['row_index'] !== -1) {
					$data['operations'][(int) $new_operation['row_index']] = $new_operation;
				}
				else {
					$data['operations'][] = $new_operation;
				}
			}
			elseif ($new_operation['recovery'] == ACTION_RECOVERY_OPERATION) {
				$data['recovery'] = ACTION_RECOVERY_OPERATION;
				$data['operations'] = $this->hasInput('recovery_operations')
					? $this->getInput('recovery_operations')
					: [];

				if ((int) $new_operation['row_index'] !== -1) {
					$data['operations'][(int) $new_operation['row_index']] = $new_operation;
				}
				else {
					$data['operations'][] = $new_operation;
				}
			}
			elseif ($new_operation['recovery'] == ACTION_UPDATE_OPERATION) {
				$data['recovery'] = ACTION_UPDATE_OPERATION;
				$data['operations'] = $this->hasInput('update_operations')
					? $this->getInput('update_operations')
					: [];

				if ((int) $new_operation['row_index'] !== -1) {
					$data['operations'][$new_operation['row_index']] = $new_operation;
				}
				else {
					$data['operations'][] = $new_operation;
				}
			}
		}
		else {
			$data['recovery'] = ACTION_OPERATION;
			$data['operations'] = $this->hasInput('operations') ? $this->getInput('operations') : [];
		}

		foreach ($data['operations'] as $operation) {
			if ((int) $operation['recovery'] === ACTION_OPERATION) {
				$data['action']['operations'][] = $operation;
				sortOperations($eventsource, $data['action']['operations']);
			}
			if ((int) $operation['recovery'] == ACTION_RECOVERY_OPERATION) {
				$data['action']['recovery_operations'][] = $operation;
				CArrayHelper::sort($data['action']['recovery_operations'], ['operationtype']);
			}
			if ((int) $operation['recovery'] == ACTION_UPDATE_OPERATION) {
				$data['action']['update_operations'][] = $operation;
				CArrayHelper::sort($data['action']['update_operations'], ['operationtype']);
			}
		}

		$data['descriptions'] = getActionOperationData([$data['action']], $operation['recovery']);
		$data['allowedOperations'] = getAllowedOperations($eventsource);
		$data['eventsource'] = $eventsource;
		$data['action']['esc_period'] = $data['esc_period'];
		$data['action']['eventsource'] = $eventsource;

		$this->setResponse(new CControllerResponseData($data));
	}
}
