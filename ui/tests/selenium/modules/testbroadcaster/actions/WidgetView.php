<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2024 Zabbix SIA
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


namespace Modules\TestBroadcaster\Actions;

use API,
	CControllerDashboardWidgetView,
	CControllerResponseData,
	CRangeTimeParser;

class WidgetView extends CControllerDashboardWidgetView {

	protected function doAction(): void {
		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->widget->getDefaultName()),
			'objects' => self::getObjects($this->fields_values),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}

	private static function getObjects(array $fields_values): array {
		$result = [];

		$result['host_groups'] = array_column(
			API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $fields_values['groupids']
			]),
			'name',
			'groupid'
		);

		$result['hosts'] = array_column(
			API::Host()->get([
				'output' => ['hostid', 'name'],
				'hostids' => $fields_values['hostids']
			]),
			'name',
			'hostid'
		);

		$result['items'] = array_column(
			API::Item()->get([
				'output' => ['itemid', 'name'],
				'itemids' => $fields_values['itemids']
			]),
			'name',
			'itemid'
		);

		$result['item_prototypes'] = array_column(
			API::ItemPrototype()->get([
				'output' => ['itemid', 'name'],
				'itemids' => $fields_values['prototype_itemids']
			]),
			'name',
			'itemid'
		);

		$result['graphs'] = array_column(
			API::Graph()->get([
				'output' => ['graphid', 'name'],
				'graphids' => $fields_values['graphids']
			]),
			'name',
			'graphid'
		);

		$result['graph_prototypes'] = array_column(
			API::GraphPrototype()->get([
				'output' => ['graphid', 'name'],
				'graphids' => $fields_values['prototype_graphids']
			]),
			'name',
			'graphid'
		);

		$result['maps'] = array_column(
			API::Map()->get([
				'output' => ['sysmapid', 'name'],
				'sysmapids' => $fields_values['sysmapids']
			]),
			'name',
			'sysmapid'
		);

		$result['services'] = array_column(
			API::Service()->get([
				'output' => ['serviceid', 'name'],
				'serviceids' => $fields_values['serviceids']
			]),
			'name',
			'serviceid'
		);

		$result['slas'] = array_column(
			API::Sla()->get([
				'output' => ['slaid', 'name'],
				'slaids' => $fields_values['slaids']
			]),
			'name',
			'slaid'
		);

		$result['time_periods'] = [];

		$time_periods = [
			['label' => 'Last 1 hour',	'from' => 'now-1h',		'to' => 'now'],
			['label' => 'Today',		'from' => 'now/d',		'to' => 'now/d'],
			['label' => 'Yesterday',	'from' => 'now-1d/d',	'to' => 'now-1d/d'],
			['label' => '2 days ago',	'from' => 'now-2d/d',	'to' => 'now-2d/d'],
			['label' => 'This week',	'from' => 'now/w',		'to' => 'now/w'],
			['label' => 'This month',	'from' => 'now/M',		'to' => 'now/M']
		];

		$range_time_parser = new CRangeTimeParser();

		foreach ($time_periods as $time_period) {
			$range_time_parser->parse($time_period['from']);
			$from_ts = $range_time_parser->getDateTime(true)->getTimestamp();

			$range_time_parser->parse($time_period['to']);
			$to_ts = $range_time_parser->getDateTime(false)->getTimestamp();

			$result['time_periods'][] = $time_period + [
				'from_ts' => $from_ts,
				'to_ts' => $to_ts
			];
		}

		return $result;
	}
}
