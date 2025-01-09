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


class CControllerCorrelationUpdate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'correlationid' =>	'db correlation.correlationid|required',
			'name' =>			'db correlation.name|required|not_empty',
			'description' =>	'db correlation.description',
			'evaltype' =>		'db correlation.evaltype|required|in '.implode(',', [CONDITION_EVAL_TYPE_AND_OR,
				CONDITION_EVAL_TYPE_AND, CONDITION_EVAL_TYPE_OR, CONDITION_EVAL_TYPE_EXPRESSION
			]),
			'status' =>			'db correlation.status|in '.ZBX_CORRELATION_ENABLED,
			'formula' =>		'db correlation.formula',
			'op_close_new' =>	'in 1',
			'op_close_old' =>	'in 1',
			'conditions' =>		'required|array'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Cannot update event correlation'),
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				], JSON_THROW_ON_ERROR)])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_EVENT_CORRELATION);
	}

	protected function doAction(): void {
		$correlation = [
			'correlationid' => $this->getInput('correlationid'),
			'name' => $this->getInput('name'),
			'description' => $this->getInput('description', ''),
			'status' => $this->getInput('status', ZBX_CORRELATION_DISABLED),
			'filter' => [
				'evaltype' => $this->getInput('evaltype'),
				'conditions' => $this->getInput('conditions', [])
			],
			'operations' => []
		];

		if ($correlation['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
			if (count($correlation['filter']['conditions']) > 1) {
				$correlation['filter']['formula'] = $this->getInput('formula', '');
			}
			else {
				$correlation['filter']['evaltype'] = CONDITION_EVAL_TYPE_AND_OR;
			}
		}
		else {
			foreach ($correlation['filter']['conditions'] as &$condition) {
				unset($condition['formulaid']);
			}
			unset($condition);
		}

		foreach ($correlation['filter']['conditions'] as &$condition) {
			unset($condition['row_index']);
		}
		unset($condition);

		if ($this->hasInput('op_close_old')) {
			$correlation['operations'][] = ['type' => ZBX_CORR_OPERATION_CLOSE_OLD];
		}

		if ($this->hasInput('op_close_new')) {
			$correlation['operations'][] = ['type' => ZBX_CORR_OPERATION_CLOSE_NEW];
		}

		$result = API::Correlation()->update($correlation);

		$output = [];

		if ($result) {
			$output['success']['title'] = _('Event correlation updated');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot update event correlation'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
