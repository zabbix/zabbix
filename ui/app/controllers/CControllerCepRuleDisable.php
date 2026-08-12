<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CControllerCepRuleDisable extends CController {
	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_CEPRULES);
	}

	protected function checkInput(): bool {
		$fields = [
			'cepruleids' => 'array_db cep_rule.cep_ruleid',
			'correlationids' => 'array_db correlation.correlationid'
		];

		$ret = $this->validateInput($fields);

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

	protected function doAction(): void {
		$cepruleids = $this->getInput('cepruleids', []);
		$correlationids = $this->getInput('correlationids', []);
		$ceprules = [];
		$correlations = [];

		foreach ($cepruleids as $cepruleid) {
			$ceprules[] = [
				'cep_ruleid' => $cepruleid,
				'status' => CCepRuleHelper::STATUS_DISABLED
			];
		}

		foreach ($correlationids as $correlationid) {
			$correlations[] = [
				'correlationid' => $correlationid,
				'status' => ZBX_CORRELATION_DISABLED
			];
		}

		$result_cep = !$ceprules || API::CepRule()->update($ceprules);
		$result_correlation = !$correlations || API::Correlation()->update($correlations);

		$result = $result_cep && $result_correlation;

		$output = [];
		$updated = count($ceprules) + count($correlations);

		if ($result) {
			$output['success']['title'] = _n('Event processing rule disabled',
				'Event processing rules disabled', $updated
			);

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _n('Cannot disable event processing rule',
					'Cannot disable event processing rules', $updated
				),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];

			$keep_cepruleids = array_column(API::CepRule()->get([
				'output' => ['cep_ruleid'],
				'cep_ruleids' => $cepruleids,
				'editable' => true
			]), 'cep_ruleid');

			$keep_correlationids = array_column(API::Correlation()->get([
				'output' => ['correlationid'],
				'correlationids' => $correlationids,
				'editable' => true
			]), 'correlationid');

			$output['keepids'] = [...$keep_cepruleids,
				...array_map(fn(string $correlationid) => "legacy-$correlationid", $keep_correlationids)
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
