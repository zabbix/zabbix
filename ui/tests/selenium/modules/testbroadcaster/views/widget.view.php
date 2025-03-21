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
 * Test Broadcaster widget view.
 *
 * @var CView $this
 * @var array $data
 */

(new CWidgetView($data))
	->addItem(
		(new CDiv([
			(new CDiv([
				makeSection('Host group',		CWidgetsData::DATA_TYPE_HOST_GROUP_ID,		$data['objects']['host_groups']),
				makeSection('Host groups',		CWidgetsData::DATA_TYPE_HOST_GROUP_IDS,		$data['objects']['host_groups']),
				makeSection('Host',				CWidgetsData::DATA_TYPE_HOST_ID,			$data['objects']['hosts']),
				makeSection('Hosts',			CWidgetsData::DATA_TYPE_HOST_IDS,			$data['objects']['hosts']),
				makeSection('Item',				CWidgetsData::DATA_TYPE_ITEM_ID,			$data['objects']['items']),
				makeSection('Items',			CWidgetsData::DATA_TYPE_ITEM_IDS,			$data['objects']['items']),
				makeSection('Item prototype',	CWidgetsData::DATA_TYPE_ITEM_PROTOTYPE_ID,	$data['objects']['item_prototypes']),
				makeSection('Graph',			CWidgetsData::DATA_TYPE_GRAPH_ID,			$data['objects']['graphs']),
				makeSection('Graph prototype',	CWidgetsData::DATA_TYPE_GRAPH_PROTOTYPE_ID,	$data['objects']['graph_prototypes']),
				makeSection('Map',				CWidgetsData::DATA_TYPE_MAP_ID,				$data['objects']['maps']),
				makeSection('Service',			CWidgetsData::DATA_TYPE_SERVICE_ID,			$data['objects']['services']),
				makeSection('SLA',				CWidgetsData::DATA_TYPE_SLA_ID,				$data['objects']['slas']),
				makeSection('Time period',		CWidgetsData::DATA_TYPE_TIME_PERIOD,		$data['objects']['time_periods'])
			]))->addClass('sections'),
			(new CDiv([
				'Feedbacks received',
				new CTextArea('feedbacks')
			]))->addClass('feedbacks')
		]))->addClass('js-view')
	)
	->show();

function makeSection(string $title, string $type, array $objects): CDiv {
	return (new CDiv([
		(new CDiv($title))
			->addClass('js-label'),
		(new CDiv(makeObjects($type, $objects)))
			->addClass('js-objects'),
		(new CTextBox())
			->removeId()
			->addClass('js-buffer'),
		(new CSimpleButton('↪'))
			->addClass('js-broadcast')
			->setAttribute('title', 'Broadcast')
	]))
		->addClass('js-section')
		->setAttribute('data-type', $type);
}

function makeObjects(string $type, array $objects): array {
	$buttons = [
		(new CSimpleButton('Default'))
			->setAttribute('data-type', 'default')
			->addClass('on')
	];

	switch ($type) {
		case CWidgetsData::DATA_TYPE_TIME_PERIOD:
			foreach ($objects as $time_period) {
				$buttons[] = (new CSimpleButton($time_period['label']))
					->setAttribute('data-from', $time_period['from'])
					->setAttribute('data-from_ts', $time_period['from_ts'])
					->setAttribute('data-to', $time_period['to'])
					->setAttribute('data-to_ts', $time_period['to_ts']);
			}
			break;

		default:
			foreach ($objects as $id => $label) {
				$buttons[] = (new CSimpleButton($label))->setAttribute('data-id', $id);
			}
			break;
	}

	return $buttons;
}
