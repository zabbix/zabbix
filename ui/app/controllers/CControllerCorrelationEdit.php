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


class CControllerCorrelationEdit extends CController {

	/**
	 * @var mixed
	 */
	private $correlation = [];

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'correlationid' => 'db correlation.correlationid'
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

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_CONFIGURATION_EVENT_CORRELATION)) {
			return false;
		}

		if ($this->hasInput('correlationid')) {
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

			$op_types = array_column($this->correlation['operations'], 'type', 'type');
			$this->correlation['op_close_old'] = array_key_exists(ZBX_CORR_OPERATION_CLOSE_OLD, $op_types);
			$this->correlation['op_close_new'] = array_key_exists(ZBX_CORR_OPERATION_CLOSE_NEW, $op_types);
			$this->correlation += $this->correlation['filter'];
			unset($this->correlation['filter']);
		}

		return true;
	}

	protected function doAction(): void {
		$data = $this->correlation + DB::getDefaults('correlation') + [
			'correlationid' => null,
			'op_close_new' => false,
			'op_close_old' => false,
			'conditions' => []
		];

		foreach ($data['conditions'] as &$condition) {
			$condition += [
				'operator' => array_key_exists('operator', $condition)
					? (int) $condition['operator']
					: CONDITION_OPERATOR_EQUAL,
				'conditiontype' => (int) $condition['type']
			];
			$condition['operator_name'] = CCorrelationHelper::getLabelByOperator($condition['operator']);

			unset($condition['type']);
		}
		unset($condition);

		$groupids = array_column($data['conditions'], 'groupid', 'groupid');
		$group_names = [];

		if ($groupids) {
			$groups = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => array_keys($groupids),
				'preservekeys' => true
			]);

			$group_names = array_column($groups, 'name', 'groupid');
		}

		foreach ($data['conditions'] as &$condition) {
			if (array_key_exists('groupid', $condition)
					&& array_key_exists($condition['groupid'], $group_names)) {
				$condition['groupid'] = [$condition['groupid'] => $group_names[$condition['groupid']]];
			}
		}
		unset($condition);

		$data['conditions'] = array_values($data['conditions']);
		$data['user'] = ['debug_mode' => $this->getDebugMode()];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Event correlation rules'));
		$this->setResponse($response);
	}
}
