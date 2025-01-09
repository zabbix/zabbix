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


/**
 * Host availability widget view.
 *
 * @var CView $this
 * @var array $data
 */

const INTERFACE_AVAILABLE_TOTAL = -1;

$interface_states_fields = [
	INTERFACE_AVAILABLE_TRUE => ['name' => _('Available'), 'style' => ZBX_STYLE_HOST_AVAIL_TRUE,
		'name_in_context' => _x('Available', 'compact table header')
	],
	INTERFACE_AVAILABLE_FALSE => ['name' => _('Not available'), 'style' => ZBX_STYLE_HOST_AVAIL_FALSE,
		'name_in_context' => _x('Not available', 'compact table header')
	],
	INTERFACE_AVAILABLE_MIXED => ['name' => _('Mixed'), 'style' => ZBX_STYLE_HOST_AVAIL_MIXED,
		'name_in_context' => _x('Mixed', 'compact table header')
	],
	INTERFACE_AVAILABLE_UNKNOWN => ['name' => _('Unknown'), 'style' => ZBX_STYLE_HOST_AVAIL_UNKNOWN,
		'name_in_context' => _x('Unknown', 'compact table header')
	],
	INTERFACE_AVAILABLE_TOTAL => ['name' => _('Total'), 'style' => ZBX_STYLE_HOST_AVAIL_TOTAL,
		'name_in_context' => _x('Total', 'compact table header')
	]
];

if (count($data['interface_types']) == 1 || $data['only_totals'] == 1) {
	$counts = $data['total_hosts'];

	$table = (new CDiv())
		->addClass(ZBX_STYLE_HOST_AVAIL_TABLE)
		->addClass(ZBX_STYLE_TOTALS_LIST)
		->addClass($data['layout'] == STYLE_HORIZONTAL
			? ZBX_STYLE_TOTALS_LIST_HORIZONTAL
			: ZBX_STYLE_TOTALS_LIST_VERTICAL
		);

	foreach ($interface_states_fields as $state => $field) {
		$count = $state != INTERFACE_AVAILABLE_TOTAL ? $counts[$state] : $data['total_hosts_sum'];

		$table->addItem(
			(new CDiv([
				(new CSpan($count))->addClass(ZBX_STYLE_TOTALS_LIST_COUNT)->setTitle($count),
				(new CSpan($field['name']))->addClass(ZBX_STYLE_TOTALS_LIST_NAME)->setTitle($field['name'])
			]))->addClass($field['style'])
		);
	}
}
else {
	$header = [
		STYLE_HORIZONTAL => [''],
		STYLE_VERTICAL => ['']
	];

	foreach ($interface_states_fields as $field) {
		$header[STYLE_HORIZONTAL][] = (new CColHeader($field['name_in_context']))->addClass($field['style']);
	}

	$type_field_names = [
		'total_hosts' => _('Total Hosts'),
		INTERFACE_TYPE_AGENT_ACTIVE => _('Agent (active)'),
		INTERFACE_TYPE_AGENT => _('Agent (passive)'),
		INTERFACE_TYPE_SNMP => _('SNMP'),
		INTERFACE_TYPE_JMX => _('JMX'),
		INTERFACE_TYPE_IPMI => _('IPMI')
	];

	foreach ($type_field_names as $type => $interface_name) {
		if (!in_array($type, $data['interface_types']) && $type !== 'total_hosts') {
			continue;
		}

		$header[STYLE_VERTICAL][] = $interface_name;
	}

	$interface_states_order = [INTERFACE_AVAILABLE_TRUE, INTERFACE_AVAILABLE_FALSE, INTERFACE_AVAILABLE_MIXED,
		INTERFACE_AVAILABLE_UNKNOWN
	];

	$table = (new CTableInfo)
		->setHeader($header[$data['layout']])
		->setHeadingColumn(0)
		->addClass(ZBX_STYLE_HOST_AVAIL_TABLE);

	foreach ($type_field_names as $type => $interface_name) {
		if (!in_array($type, $data['interface_types']) && $type !== 'total_hosts') {
			continue;
		}

		$interface_data = [];
		$interface_data['name'] = $interface_name;

		$counts = $type !== 'total_hosts' ? $data['interface_type_count'][$type] : $data['total_hosts'];

		foreach ($interface_states_order as $state) {
			if ($type == INTERFACE_TYPE_AGENT_ACTIVE && $state == INTERFACE_AVAILABLE_MIXED) {
				$interface_data[$state] = new CCol('-');
			}
			else {
				$interface_data[$state] = new CCol($counts[$state]);
			}
		}

		if ($type == 'total_hosts') {
			$interface_data[INTERFACE_AVAILABLE_TOTAL] = new CCol($data['total_hosts_sum']);
		}
		else {
			$interface_data[INTERFACE_AVAILABLE_TOTAL] = new CCol($data['interface_totals'][$type]);
		}

		if ($data['layout'] == STYLE_HORIZONTAL) {
			$table->addRow($interface_data);
		}
		else {
			unset($interface_data['name']);

			foreach ($interface_data as $key => $field) {
				$rows[$key][] = $field;
			}
		}
	}

	if ($data['layout'] == STYLE_VERTICAL) {
		foreach ($interface_states_fields as $state => $field) {
			$table->addRow(
				array_merge([(new CColHeader($field['name']))->addClass($field['style'])], $rows[$state])
			);
		}
	}
}

(new CWidgetView($data))
	->addItem($table)
	->show();
