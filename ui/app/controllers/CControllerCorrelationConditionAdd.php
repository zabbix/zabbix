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


class CControllerCorrelationConditionAdd extends CController {

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
			'new_condition' => 'array',
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
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_EVENT_CORRELATION);
	}

	protected function doAction() {
		$data = [
			'conditions' => []
		];

		$this->getInputs($data, ['conditions', 'new_condition']);

		$data['conditions'] = $this->addCondition($data['new_condition'], $data['conditions']);

		$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
			->setArgument('action', 'correlation.edit')
		);
		$response->setFormData($data + $this->getInputAll());
		$this->setResponse($response);
	}

	/**
	 * Merges new condition into list of conditions. Duplicate records are skipped.
	 *
	 * @param array $new_condition  Condition (compressed format) to merge into conditions.
	 * @param array $conditions     Original list of conditions.
	 *
	 * @return array  Original list with merged conditions.
	 */
	private function addCondition(array $new_condition, array $conditions): array {
		$used_formulaids = array_column($conditions, 'formulaid');
		$hashes = array_flip(array_map([$this, 'hashCondition'], $conditions));

		foreach ($this->newConditions($new_condition) as $condition) {
			$hash = $this->hashCondition($condition);

			if (array_key_exists($hash, $hashes)) {
				continue;
			}

			$hashes[$hash] = true;
			$used_formulaids[] = CConditionHelper::getNextFormulaId($used_formulaids);
			$conditions[] = ['formulaid' => end($used_formulaids)] + $condition;
		}

		return $conditions;
	}

	/**
	 * Creates hash that determines the uniqueness of condition.
	 *
	 * @param array $condition
	 *
	 * @return string
	 */
	private function hashCondition(array $condition): string {
		$hash = [(string) $condition['type']];

		switch ($condition['type']) {
			case ZBX_CORR_CONDITION_OLD_EVENT_TAG:
			case ZBX_CORR_CONDITION_NEW_EVENT_TAG:
				$hash[] = $condition['tag'];
				break;

			case ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP:
				$hash[] = $condition['groupid'];
				break;

			case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
				$hash[] = $condition['oldtag'];
				$hash[] = $condition['newtag'];
				break;

			case ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE:
			case ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
				$hash[] = $condition['tag'];
				$hash[] = $condition['value'];
				break;
		}

		return json_encode($hash);
	}

	/**
	 * Unfolds compressed condition into list of conditions.
	 *
	 * @param array $new_condition  Condition in "compressed format".
	 *
	 * @return Generator
	 */
	private function newConditions(array $new_condition): Generator {
		if ($new_condition['type'] != ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP) {
			return yield $new_condition;
		}

		foreach ($new_condition['groupids'] as $groupid) {
			yield [
				'type' => ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP,
				'operator' => $new_condition['operator'],
				'groupid' => $groupid
			];
		}
	}
}
