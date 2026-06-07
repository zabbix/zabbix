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


class CControllerCepRuleEnable extends CControllerCepRuleGeneral {
	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
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
				'status' => CCepRuleHelper::STATUS_ENABLED
			];
		}

		foreach ($correlationids as $correlationid) {
			$correlations[] = [
				'correlationid' => $correlationid,
				'status' => ZBX_CORRELATION_ENABLED
			];
		}

		$result_cep = !$ceprules || API::CepRule()->update($ceprules);
		$result_correlation = !$correlations || API::Correlation()->update($correlations);

		$result = $result_cep && $result_correlation;

		$output = [];
		$updated = count($ceprules) + count($correlations);

		if ($result) {
			$output['success']['title'] = _n('Complex event processing rule enabled',
				'Complex event processing rules enabled', $updated
			);

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _n('Cannot enable complex event processing rule',
					'Cannot enable complex event processing rules', $updated
				),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];

			$keep_cepruleids = array_keys(API::CepRule()->get([
				'output' => [],
				'correlationids' => $cepruleids,
				'editable' => true,
				'preservekeys' => true
			]));

			$keep_correlationids = array_keys(API::Correlation()->get([
				'output' => [],
				'correlationids' => $correlationids,
				'editable' => true,
				'preservekeys' => true
			]));

			$output['keepids'] = [...$keep_cepruleids,
				...array_map(fn(string $correlationid) => "legacy-$correlationid", $keep_correlationids)
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
