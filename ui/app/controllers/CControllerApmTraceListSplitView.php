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


class CControllerApmTraceListSplitView extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableCsrfValidation();
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_APM_TRACES);
	}

	protected function checkInput(): bool {
		$fields = [
			'traceid' => 'required|string'
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
		$timeline = getTimeSelectorPeriod([
			'profileIdx' => 'web.apm.trace.list.filter',
			'profileIdx2' => 0,
			'from' => $this->hasInput('from') ? $this->getInput('from') : null,
			'to' => $this->hasInput('to') ? $this->getInput('to') : null
		]);

		$traceid = $this->getInput('traceid');

		$traces = API::ApmTrace()->get([
			'time_from' => $timeline['from_ts'],
			'time_till' => $timeline['to_ts'],
			'traceids' => [$traceid]
		]);
		$trace = $traces[0] ?? null;

		$spans = API::ApmSpan()->get([
			'time_from' => $timeline['from_ts'],
			'time_till' => $timeline['to_ts'],
			'traceids' => [$traceid]
		]);

		$span_tree = $this->buildSpanTree($spans);

		$view = (new CPartial('apm.trace.list.split.view', [
			'trace' => $trace,
			'span_tree' => $span_tree
		]));

		$output = [
			'main_block' => $view->getOutput()
		];

		$this->setResponse(new CControllerResponseData($output));
	}

	private function buildSpanTree(array $spans): array {
		$map = [];
		$roots = [];

		foreach ($spans as $span) {
			$span['children'] = [];
			$map[$span['spanid']] = $span;
		}

		foreach ($map as &$node) {
			$parent_spanid = $node['parent_spanid'];

			if (!empty($parent_spanid) && array_key_exists($parent_spanid, $map)) {
				$map[$parent_spanid]['children'][] = $node;
			} else {
				$roots[] = $node;
			}
		}
		unset($node);

		return $roots;
	}
}
