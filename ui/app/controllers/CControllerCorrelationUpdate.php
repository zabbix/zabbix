<?php declare(strict_types=1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


class CControllerCorrelationUpdate extends CController {

	protected function checkInput() {
		$fields = [
			'correlationid' => 'db correlation.correlationid|required',
			'name'          => 'db correlation.name|required|not_empty',
			'description'   => 'db correlation.description',
			'evaltype'      => 'db correlation.evaltype|required|in '.implode(',', [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND, CONDITION_EVAL_TYPE_OR, CONDITION_EVAL_TYPE_EXPRESSION]),
			'status'        => 'db correlation.status|required|in '.implode(',', [ZBX_CORRELATION_ENABLED, ZBX_CORRELATION_DISABLED]),
			'formula'       => 'db correlation.formula',
			'op_close_new'  => 'in 1',
			'op_close_old'  => 'in 1',
			'conditions'    => 'array',
			'form_refresh'  => 'int32'
		];

		$ret = $this->validateInput($fields);
		$error = $this->getValidationError();

		if (!$ret) {
			switch ($error) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
						->setArgument('action', 'correlation.edit')
					);
					$response->setFormData($this->getInputAll());
					CMessageHelper::setErrorTitle(_('Cannot update correlation'));
					$this->setResponse($response);
					break;

				case self::VALIDATION_FATAL_ERROR:
					$this->setResponse(new CControllerResponseFatal());
					break;
			}
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (!$this->checkAccess(CRoleHelper::UI_CONFIGURATION_EVENT_CORRELATION)) {
			return false;
		}

		return (bool) API::Correlation()->get([
			'output' => [],
			'correlationid' => $this->getInput('correlationid'),
			'editable' => true
		]);
	}

	protected function doAction() {
		$data = [
			'conditions' => [],
			'status' => ZBX_CORRELATION_DISABLED
		];

		$this->getInputs($data, ['correlationid', 'name', 'description', 'evaltype', 'status', 'formula',
			'op_close_new', 'op_close_old', 'conditions', 'new_condition'
		]);

		$correlation = [
			'correlationid' => $data['correlationid'],
			'name' => $data['name'],
			'description' => $data['description'],
			'status' => $data['status'],
			'filter' => [
				'evaltype' => $data['evaltype'],
				'formula' => $data['formula'],
				'conditions' => $data['conditions']
			],
			'operations' => []
		];

		if ($correlation['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION
				&& count($correlation['filter']['conditions']) < 2) {
			$correlation['filter']['formula'] = '';
			$correlation['filter']['evaltype'] = CONDITION_EVAL_TYPE_AND_OR;
		}

		if (array_key_exists('op_close_old', $data)) {
			$correlation['operations'][] = ['type' => ZBX_CORR_OPERATION_CLOSE_OLD];
		}

		if (array_key_exists('op_close_new', $data)) {
			$correlation['operations'][] = ['type' => ZBX_CORR_OPERATION_CLOSE_NEW];
		}

		$result = API::Correlation()->update($correlation);

		if ($result) {
			$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
				->setArgument('action', 'correlation.list')
				->setArgument('page', CPagerHelper::loadPage('correlation.list', null))
			);
			$response->setFormData(['uncheck' => '1']);
			CMessageHelper::setSuccessTitle(_('Correlation updated'));
		}
		else {
			$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
				->setArgument('action', 'correlation.edit')
			);
			$response->setFormData($this->getInputAll());
			CMessageHelper::setErrorTitle(_('Cannot update correlation'));
		}

		$this->setResponse($response);
	}
}
