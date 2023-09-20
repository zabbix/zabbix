<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * Host availability widget view.
 *
 * @var CView $this
 * @var array $data
 */

$type_field_names = [
	'total_hosts' => _('Total Hosts'),
	INTERFACE_TYPE_AGENT_ACTIVE => _('Agent (active)'),
	INTERFACE_TYPE_AGENT => _('Agent (passive)'),
	INTERFACE_TYPE_SNMP => _('SNMP'),
	INTERFACE_TYPE_JMX => _('JMX'),
	INTERFACE_TYPE_IPMI => _('IPMI')
];

$interface_states_order = [INTERFACE_AVAILABLE_TRUE, INTERFACE_AVAILABLE_FALSE, INTERFACE_AVAILABLE_MIXED,
	INTERFACE_AVAILABLE_UNKNOWN
];

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
	'total' => ['name' => _('Total'), 'style' => ZBX_STYLE_HOST_AVAIL_TOTAL,
		'name_in_context' => _x('Total', 'compact table header')
	]
];

$header = [
	STYLE_HORIZONTAL => [''],
	STYLE_VERTICAL => ['']
];

foreach ($interface_states_fields as $field) {
	$header[STYLE_HORIZONTAL][] = (new CColHeader($field['name_in_context']))->addClass($field['style']);
}

foreach ($type_field_names as $type => $interface_name) {
	if (!in_array($type, $data['interface_types']) && $type !== 'total_hosts') {
		continue;
	}

	$header[STYLE_VERTICAL][] = $interface_name;
}

if (count($data['interface_types']) == 1 || $data['only_totals'] == 1) {
	$counts = $data['total_hosts'];

	$table = (new CDiv())
		->addClass(ZBX_STYLE_HOST_AVAIL_WIDGET)
		->addClass(ZBX_STYLE_TOTALS_LIST)
		->addClass(($data['layout'] == STYLE_HORIZONTAL)
			? ZBX_STYLE_TOTALS_LIST_HORIZONTAL
			: ZBX_STYLE_TOTALS_LIST_VERTICAL
		);

	foreach ($interface_states_fields as $state => $field) {
		$table->addItem((new CDiv([
			(new CSpan($state != 'total' ? $counts[$state] : $data['total_hosts_sum']))
				->addClass(ZBX_STYLE_TOTALS_LIST_COUNT),
			$field['name']
		]))
			->addClass($field['style'])
		);
	}
}
else {
	$table = (new CTableInfo)
		->setHeader($header[$data['layout']])
		->setHeadingColumn(0)
		->addClass(ZBX_STYLE_HOST_AVAIL_WIDGET);

	foreach ($type_field_names as $type => $interface_name) {
		if (!in_array($type, $data['interface_types']) && $type !== 'total_hosts') {
			continue;
		}

		$interface_data = [];
		$interface_data['name'] = $interface_name;

		$counts = $type !== 'total_hosts' ? $data['interface_type_count'][$type] : $data['total_hosts'];

		foreach ($interface_states_order as $state) {
			if ($type == INTERFACE_TYPE_AGENT_ACTIVE && $state == INTERFACE_AVAILABLE_MIXED) {
				$interface_data[$state] = (new CCol(''));
			}
			else {
				$interface_data[$state] = (new CCol($counts[$state]));
			}
		}

		$interface_data['total'] = (new CCol($type == 'total_hosts'
			? $data['total_hosts_sum']
			: $data['interface_totals'][$type]
		));

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
			$table->addRow(array_merge([(new CColHeader($field['name']))->addClass($field['style'])], $rows[$state]));
		}
	}
}

(new CWidgetView($data))
	->addItem($table)
	->show();
