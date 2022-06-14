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


class CControllerCorrelationEdit extends CController {

	private $correlation = [];

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'correlationid' => 'db correlation.correlationid',
			'name'          => 'db correlation.name',
			'description'   => 'db correlation.description',
			'evaltype'      => 'db correlation.evaltype|in '.implode(',', [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND, CONDITION_EVAL_TYPE_OR, CONDITION_EVAL_TYPE_EXPRESSION]),
			'status'        => 'db correlation.status|in '.implode(',', [ZBX_CORRELATION_ENABLED, ZBX_CORRELATION_DISABLED]),
			'formula'       => 'db correlation.formula',
			'op_close_new'  => 'in 1',
			'op_close_old'  => 'in 1',
			'conditions'    => 'array',
			'form_refresh'  => 'int32'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (!$this->checkAccess(CRoleHelper::UI_CONFIGURATION_EVENT_CORRELATION)) {
			return false;
		}

		if ($this->hasInput('correlationid') && !$this->hasInput('form_refresh')) {
			$correlations = API::Correlation()->get([
				'output' => ['correlationid', 'name', 'description', 'status'],
				'selectFilter' => ['formula', 'conditions', 'evaltype'],
				'selectOperations' => ['type'],
				'correlationids' => $this->getInput('correlationid'),
				'editable' => true
			]);

			if (!$correlations) {
				return false;
			}

			$this->correlation = $correlations[0];
			CArrayHelper::sort($this->correlation['filter']['conditions'], ['formulaid']);

			$op_types = array_column($this->correlation['operations'], 'type', 'type');
			$this->correlation['op_close_old'] = array_key_exists(ZBX_CORR_OPERATION_CLOSE_OLD, $op_types);
			$this->correlation['op_close_new'] = array_key_exists(ZBX_CORR_OPERATION_CLOSE_NEW, $op_types);
			$this->correlation += $this->correlation['filter'];
			unset($this->correlation['filter']);
		}

		return true;
	}

	protected function doAction() {
		$data = $this->correlation + DB::getDefaults('correlation') + [
			'new_condition' => $this->getInput('new_condition', []),
			'allowedOperations' => CCorrelationHelper::getOperationTypes(),
			'allowedConditions' => CCorrelationHelper::getConditionTypes(),
			'correlationid' => $this->getInput('correlationid', 0),
			'op_close_new' => false,
			'op_close_old' => false,
			'conditions' => []
		];

		$this->getInputs($data, ['correlationid', 'name', 'description', 'status', 'op_close_new', 'op_close_old',
			'evaltype', 'formula', 'conditions'
		]);

		$groupids = array_column($data['conditions'], 'groupid', 'groupid');
		if ($groupids) {
			$groups = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => array_keys($groupids),
				'preservekeys' => true
			]);

			$data['group_names'] = array_column($groups, 'name', 'groupid');
		}
		else {
			$data['group_names'] = [];
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Event correlation rules'));
		$this->setResponse($response);
	}
}
