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


class CControllerCorrelationCreate extends CController {

	protected function checkInput() {
		$fields = [
			'name'         => 'db correlation.name|required|not_empty',
			'description'  => 'db correlation.description',
			'evaltype'     => 'db correlation.evaltype|required|in '.implode(',', [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND, CONDITION_EVAL_TYPE_OR, CONDITION_EVAL_TYPE_EXPRESSION]),
			'status'       => 'db correlation.status|required|in '.implode(',', [ZBX_CORRELATION_ENABLED, ZBX_CORRELATION_DISABLED]),
			'formula'      => 'db correlation.formula',
			'op_close_new' => 'in 1',
			'op_close_old' => 'in 1',
			'conditions'   => 'array',
			'form_refresh' => 'int32'
		];

		$ret = $this->validateInput($fields);
		$error = $this->getValidationError();

		if (!$ret) {
			switch ($error) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect(
						(new CUrl('zabbix.php'))->setArgument('action', 'correlation.edit')
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
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_EVENT_CORRELATION);
	}

	protected function doAction() {
		$correlation = [
			'name' => $this->getInput('name'),
			'description' => $this->getInput('description', ''),
			'status' => $this->getInput('status'),
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

		if ($this->hasInput('op_close_old')) {
			$correlation['operations'][] = ['type' => ZBX_CORR_OPERATION_CLOSE_OLD];
		}

		if ($this->hasInput('op_close_new')) {
			$correlation['operations'][] = ['type' => ZBX_CORR_OPERATION_CLOSE_NEW];
		}

		$result = API::Correlation()->create($correlation);

		if ($result) {
			$response = new CControllerResponseRedirect(
				(new CUrl('zabbix.php'))
					->setArgument('action', 'correlation.list')
					->setArgument('page', CPagerHelper::loadPage('correlation.list', null))
			);
			$response->setFormData(['uncheck' => '1']);
			CMessageHelper::setSuccessTitle(_('Correlation added'));
		}
		else {
			$response = new CControllerResponseRedirect(
				(new CUrl('zabbix.php'))->setArgument('action', 'correlation.edit')
			);
			$response->setFormData($this->getInputAll());
			CMessageHelper::setErrorTitle(_('Cannot add correlation'));
		}

		$this->setResponse($response);
	}
}
