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
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->disableCsrfValidation();
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_APM_TRACES);
	}

	protected static function getValidationRules(): array {
		return ['object', 'fields' => [
			'traceid' => ['string', 'required'],
			'timestamp' => ['integer', 'required']
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());

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
		$traceid = $this->getInput('traceid');
		$time_from = $this->getInput('timestamp') - 1;
		$time_to = $this->getInput('timestamp') + 1;

		$traces = API::ApmTrace()->get([
			'time_from' => $time_from,
			'time_till' => $time_to,
			'traceids' => [$traceid]
		]);
		$trace = $traces[0] ?? null;

		if (!$trace) {
			throw new Exception(_('Trace not found.'));
		}

		$spans = API::ApmSpan()->get([
			'output' => ['spanid', 'parent_spanid', 'service_name', 'span_name', 'scope_name', 'timestamp', 'duration',
				'events', 'resource_attributes', 'span_attributes'],
			'time_from' => $time_from,
			'time_till' => $time_to,
			'traceids' => [$traceid],
			'sortfield' => 'timestamp',
			'sortorder' => ZBX_SORT_UP
		]);

		$span_counts = [];
		foreach ($spans as $span) {
			if (!$span['parent_spanid']) {
				continue;
			}

			$span_counts[$span['parent_spanid']] ??= 0;
			$span_counts[$span['parent_spanid']]++;
		}

		$trace_timestamp = $this->formatNs($trace['timestamp']);
		$trace_start = $trace_timestamp;
		$trace_end = $trace['duration'] * SEC_PER_NANOSEC;

		$today = strtotime('today');

		$trace_view_spans = [];
		foreach ($spans as $i => $span) {
			$span_timestamp = $this->formatNs($span['timestamp']);

			$span_start = ($span_timestamp - $trace_start) * SEC_PER_NANOSEC;
			$span_end = ($span_start + $span['duration'] * SEC_PER_NANOSEC);

			$span_events = array_map(static function (array $event) use ($trace_start) {
				$event_timestamp = $this->formatNs($event['timestamp']);
				$event_time = ($event_timestamp - $trace_start) * SEC_PER_NANOSEC;
				$event_duration = $event_time
					? convertSecondsToTimeUnits($event_time, ['combine_last_subsecond_parts' => true])
					: '0'._x('ns', 'nanosecond short');

				return [
					'time' => $event_time,
					'duration' => $event_duration,
					'name' => $event['name'],
					'attributes' => $event['attributes']
				];
			}, $span['events']);

			$span_duration = $span['duration']
				? convertSecondsToTimeUnits($span['duration'] * SEC_PER_NANOSEC, [
					'combine_last_subsecond_parts' => true
				])
				: '0'._x('ns', 'nanosecond short');

			$trace_view_spans[$i] = [
				'index' => $i,
				'id' => $span['spanid'],
				'parent_id' => $span['parent_spanid'] ?: null,
				'name' => $span['service_name'],
				'operation' => $span['span_name'],
				'service_name' => $span['service_name'],
				'scope_name' => $span['scope_name'],
				'duration' => $span_duration,
				'timestamp' => $this->formatTimeNs($span['timestamp'], $today),
				'start' => $span_start,
				'end' => $span_end,
				'count' => $span_counts[$span['spanid']] ?? null,
				'events' => $span_events,
				'resource_attributes' => $span['resource_attributes'],
				'span_attributes' => $span['span_attributes']
			];
		}

		$output = json_encode([
			'trace_view' => (new CPartial('apm.trace.list.split.view', ['trace' => $trace]))->getOutput(),
			'trace_view_data' => [
				'id' => $traceid,
				'start' => 0,
				'end' => $trace_end,
				'selected_start' => 0,
				'selected_end' => $trace_end,
				'spans' => $trace_view_spans
			]
		]);

		$this->setResponse(
			(new CControllerResponseData(['main_block' => $output]))
				->disableView()
		);
	}

	private function formatTimeNs($time_unix, $today, $clock = null): string {
		if ($clock === null) {
			$clock = floor($time_unix / 1000000000);
		}

		$ns = $this->formatNs($time_unix);

		return $clock >= $today
			? strtr(zbx_date2str(strtr(TIME_FORMAT_SECONDS, ['s' => 's.!']), $clock), ['!' => $ns])
			: strtr(zbx_date2str(strtr(DATE_TIME_FORMAT_SECONDS, ['s' => 's.!']), $clock),
				['!' => $ns]
			);
	}

	private function formatNs($ns): int {
		return (int) str_pad((string) ($ns % 1000000000), 9, '0', STR_PAD_LEFT);
	}
}
