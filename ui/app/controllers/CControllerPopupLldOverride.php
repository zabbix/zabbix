<?php
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


class CControllerPopupLldOverride extends CController {

	protected function init() {
		$this->disableSIDvalidation();
	}

	protected function checkInput() {
		$fields = [
			'no' =>					'int32',
			'templated' =>			'in 0,1',
			'name' =>				'string',
			'old_name' =>			'string',
			'stop' =>				'in 0,1',
			'overrides_evaltype' =>	'in '.implode(',', [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND, CONDITION_EVAL_TYPE_OR, CONDITION_EVAL_TYPE_EXPRESSION]),
			'overrides_formula' =>	'string',
			'overrides_filters' =>	'array',
			'operations' =>			'array',
			'overrides_names' =>	'array',
			'validate' =>			'in 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$output = [];
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		return true;
	}

	protected function doAction() {
		$page_options = [
			'no' => $this->getInput('no', -1),
			'templated' => $this->getInput('templated', 0),
			'name' => $this->getInput('name', ''),
			'old_name' => $this->getInput('old_name', ''),
			'stop' => $this->getInput('stop', 0),
			'overrides_evaltype' => $this->getInput('overrides_evaltype', CONDITION_EVAL_TYPE_AND_OR),
			'overrides_formula' => $this->getInput('overrides_formula', ''),
			'overrides_filters' => $this->getInput('overrides_filters', []),
			'operations' => $this->getInput('operations', []),
			'overrides_names' => $this->getInput('overrides_names', [])
		];

		if ($this->hasInput('validate')) {
			if ($page_options['name'] === '') {
				error(_s('Incorrect value for field "%1$s": %2$s.', _('Name'), _('cannot be empty')));
			}

			if ($page_options['overrides_evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION
					&& $page_options['overrides_formula'] === '') {
				error(_s('Incorrect value for field "%1$s": %2$s.', _('Custom expression'), _('cannot be empty')));
			}

			// Validate if override names are unique.
			if ($page_options['name'] !== $page_options['old_name']) {
				foreach ($page_options['overrides_names'] as $name) {
					if ($name === $page_options['name']) {
						error(_s('Override with name "%1$s" already exists.', $name));
					}
				}
			}

			foreach ($page_options['overrides_filters'] as $i => $filter) {
				if ($filter['macro'] === '' && $filter['value'] === '') {
					unset($page_options['overrides_filters'][$i]);
				}
			}
			$page_options['overrides_filters'] = array_values($page_options['overrides_filters']);

			// Return collected error messages.
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}
			else {
				// Return valid response.
				$params = [
					'name' => $page_options['name'],
					'stop' => $page_options['stop'],
					'overrides_evaltype' => $page_options['overrides_evaltype'],
					'overrides_formula' => $page_options['overrides_formula'],
					'overrides_filters' => $page_options['overrides_filters'],
					'operations' => $page_options['operations'],
					'no' => $page_options['no']
				];

				$output = [
					'params' => $params
				];
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
			);
		}
		else {
			$data = [
				'title' => _('Override'),
				'options' => $page_options,
				'user' => [
					'debug_mode' => $this->getDebugMode()
				]
			];

			$this->setResponse(new CControllerResponseData($data));
		}
	}
}
