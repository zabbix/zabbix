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


/**
 * Test Listener widget view.
 *
 * @var CView $this
 * @var array $data
 */

$sections_def = [
	'groupid'			=> ['label' => 'Host group',		'type' => CWidgetsData::DATA_TYPE_HOST_GROUP_ID],
	'groupids'			=> ['label' => 'Host groups',		'type' => CWidgetsData::DATA_TYPE_HOST_GROUP_IDS],
	'hostid'			=> ['label' => 'Host',				'type' => CWidgetsData::DATA_TYPE_HOST_ID],
	'hostids'			=> ['label' => 'Hosts',				'type' => CWidgetsData::DATA_TYPE_HOST_IDS],
	'itemid'			=> ['label' => 'Item',				'type' => CWidgetsData::DATA_TYPE_ITEM_ID],
	'itemids'			=> ['label' => 'Items',				'type' => CWidgetsData::DATA_TYPE_ITEM_IDS],
	'prototype_itemid'	=> ['label' => 'Item prototype',	'type' => CWidgetsData::DATA_TYPE_ITEM_PROTOTYPE_ID],
	'graphid'			=> ['label' => 'Graph',				'type' => CWidgetsData::DATA_TYPE_GRAPH_ID],
	'prototype_graphid'	=> ['label' => 'Graph prototype',	'type' => CWidgetsData::DATA_TYPE_GRAPH_PROTOTYPE_ID],
	'sysmapid'			=> ['label' => 'Map',				'type' => CWidgetsData::DATA_TYPE_MAP_ID],
	'serviceid'			=> ['label' => 'Service',			'type' => CWidgetsData::DATA_TYPE_SERVICE_ID],
	'slaid'				=> ['label' => 'SLA',				'type' => CWidgetsData::DATA_TYPE_SLA_ID],
	'time_period'		=> ['label' => 'Time period',		'type' => CWidgetsData::DATA_TYPE_TIME_PERIOD]
];

$sections = [];

foreach ($data['referred_fields'] as $name => $source_label) {
	if (array_key_exists($name, $sections_def)) {
		$sections[] = makeSection($name, $sections_def[$name]['type'], $sections_def[$name]['label'], $source_label);
	}
}

(new CWidgetView($data))
	->addItem(
		(new CDiv([
			(new CDiv($sections))->addClass('sections'),
			(new CDiv([
				'Broadcasts received',
				(new CTextArea('broadcasts'))->removeId()
			]))->addClass('broadcasts')
		]))->addClass('js-view')
	)
	->show();

function makeSection(string $name, string $type, string $data_label, string $source_label): CDiv {
	return (new CDiv([
		(new CDiv([
			$data_label,
			new CSpan($source_label)
		]))->addClass('js-label'),
		(new CTextBox())
			->removeId()
			->addClass('js-buffer')
			->setAttribute('data-queue', '1'),
		(new CSimpleButton('↩'))
			->addClass('js-feedback')
			->setAttribute('title', 'Send feedback')
			->setAttribute('data-queue', '1')
			->setEnabled(false),
		(new CTextBox())
			->removeId()
			->addClass('js-buffer')
			->setAttribute('data-queue', '2'),
		(new CSimpleButton('↩'))
			->addClass('js-feedback')
			->setAttribute('title', 'Send feedback')
			->setAttribute('data-queue', '2')
			->setEnabled(false)
	]))
		->addClass('js-section')
		->setAttribute('data-name', $name)
		->setAttribute('data-type', $type);
}
