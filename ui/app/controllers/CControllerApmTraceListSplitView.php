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
			'profileIdx' => 'web.apm.trace.filter',
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
			'output' => ['spanid', 'parent_spanid', 'service_name', 'span_name', 'scope_name', 'timestamp', 'duration',
				'events', 'resource_attributes', 'span_attributes'],
			'time_from' => $timeline['from_ts'],
			'time_till' => $timeline['to_ts'],
			'traceids' => [$traceid],
			'sortfield' => 'timestamp',
			'sortorder' => ZBX_SORT_UP
		]);

		$trace_timestamp = str_pad((string)($trace['timestamp'] % 1000000000), 9, '0', STR_PAD_LEFT);
		$trace_start = $trace_timestamp;
		$trace_end = $trace['duration'] * SEC_PER_NANOSEC;

		$today = strtotime('today');

		$trace_view_spans = [];
		foreach ($spans as $i => $span) {
			$span_timestamp = str_pad((string)($span['timestamp'] % 1000000000), 9, '0', STR_PAD_LEFT);

			$span_start = ($span_timestamp - $trace_start) * SEC_PER_NANOSEC;
			$span_end = ($span_start + $span['duration'] * SEC_PER_NANOSEC);

			$span_events = array_map(static function (array $event) use ($trace_start) {
				$event_timestamp = explode('.', $event['timestamp']);
				$event_time = ((int) $event_timestamp[1] - $trace_start) * SEC_PER_NANOSEC;
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

			$span_count = API::ApmSpan()->get([
				'time_from' => $timeline['from_ts'],
				'time_till' => $timeline['to_ts'],
				'parent_spanids' => [$span['spanid']],
				'countOutput' => true
			]);

			$span_duration = $span['duration']
				? convertSecondsToTimeUnits($span['duration'] * SEC_PER_NANOSEC, [
					'combine_last_subsecond_parts' => true
				])
				: '0'._x('ns', 'nanosecond short');

			$clock = floor($span['timestamp'] / 1000000000);

			$timestamp = $clock >= $today
				? strtr(zbx_date2str(strtr(TIME_FORMAT_SECONDS, ['s', 's.!']), $clock), ['!' => $span_timestamp])
				: strtr(zbx_date2str(strtr(DATE_TIME_FORMAT_SECONDS, ['s' => 's.!']), $clock),
					['!' => $span_timestamp]
				);

			$trace_view_spans[$i] = [
				'index' => $i,
				'id' => $span['spanid'],
				'parent_id' => $span['parent_spanid'] ?: null,
				'name' => $span['service_name'],
				'operation' => $span['span_name'],
				'service_name' => $span['service_name'],
				'scope_name' => $span['scope_name'],
				'duration' => $span_duration,
				'timestamp' => $timestamp,
				'start' => $span_start,
				'end' => $span_end,
				'count' => $span_count ?: null,
				'events' => $span_events,
				'resource_attributes' => $span['resource_attributes'],
				'span_attributes' => $span['span_attributes']
			];
		}

		$output = json_encode([
			'trace_view' => (new CPartial('apm.trace.list.split.view'))->getOutput(),
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
}
