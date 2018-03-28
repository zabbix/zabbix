<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/js/monitoring.history.js.php';

$historyWidget = new CWidget();

$header = [
	'left' => _n('%1$s item', '%1$s items', count($this->data['items'])),
	'right' => (new CForm('get'))
		->addVar('itemids', getRequest('itemids'))
		->addVar('page', 1)
		->addVar('fullscreen', $data['fullscreen'] ? '1' : null)
];
$header_row = [];
$first_item = reset($this->data['items']);
$host_name = $first_item['hosts'][0]['name'];
$same_host = true;
$items_numeric = true;

foreach ($data['items'] as $item) {
	$same_host = ($same_host && $host_name === $item['hosts'][0]['name']);
	$items_numeric = ($items_numeric && array_key_exists($item['value_type'], $data['iv_numeric']));
}

if (count($data['items']) == 1 || $same_host) {
	$header['left'] = [
		$host_name,
		NAME_DELIMITER,
		count($data['items']) == 1 ? $item['name_expanded'] : $header['left']
	];
	$header_row[] = implode('', $header['left']);
}
else {
	$header_row[] = $header['left'];
}

if (hasRequest('filter_task')) {
	$header['right']->addVar('filter_task', getRequest('filter_task'));
}
if (hasRequest('filter')) {
	$header['right']->addVar('filter', getRequest('filter'));
}
if (hasRequest('mark_color')) {
	$header['right']->addVar('mark_color', getRequest('mark_color'));
}

$actions = [
	HISTORY_GRAPH => _('Graph'),
	HISTORY_VALUES => _('Values'),
	HISTORY_LATEST => _('500 latest values')
];
if (!$items_numeric) {
	unset($actions[HISTORY_GRAPH]);
}
elseif (count($this->data['items']) > 1) {
	unset($actions[HISTORY_LATEST]);
}

$action_list = new CList();
$view_type = [
	new CLabel(_('View as')),
	(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
	new CComboBox('action', $this->data['action'], 'submit()', $actions),
];

if ($data['action'] !== HISTORY_GRAPH && $data['action'] !== HISTORY_BATCH_GRAPH) {
	$view_type[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
	$view_type[] = new CSubmit('plaintext', _('As plain text'));
}

$action_list->addItem($view_type);

if ($this->data['action'] == HISTORY_GRAPH && count($data['items']) == 1) {
	$action_list->addItem(get_icon('favourite', [
		'fav' => 'web.favorite.graphids',
		'elid' => $item['itemid'],
		'elname' => 'itemid'
	]));
}

$action_list->addItem([
	(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
	get_icon('fullscreen', ['fullscreen' => $this->data['fullscreen']])
]);

$header['right']->addItem($action_list);

// create filter
if ($this->data['action'] == HISTORY_LATEST || $this->data['action'] == HISTORY_VALUES) {
	if (array_key_exists($this->data['value_type'], $this->data['iv_string'])) {
		$filterForm = (new CFilter('web.history.filter.state'))
			->addVar('fullscreen', $this->data['fullscreen'] ? '1' : null)
			->addVar('action', $this->data['action']);

		$items_data = [];
		foreach ($this->data['items'] as $itemid => $item) {
			if (!array_key_exists($item['value_type'], $this->data['iv_string'])) {
				unset($this->data['items'][$itemid]);
				continue;
			}

			$items_data[$itemid] = [
				'id' => $itemid,
				'name' => $item['hosts'][0]['name'].NAME_DELIMITER.$item['name_expanded']
			];
		}

		CArrayHelper::sort($items_data, ['name']);

		$filterColumn1 = (new CFormList())
			->addRow(_('Items list'),
				(new CMultiSelect([
					'name' => 'itemids[]',
					'objectName' => 'items',
					'multiple' => true,
					'popup' => [
						'parameters' => [
							'srctbl' => 'items',
							'srcfld1' => 'itemid',
							'dstfld1' => 'itemids_',
							'real_hosts' => '1',
							'value_types' => [$data['value_type']]
						]
					],
					'data' => $items_data,
				]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			)
			->addRow(_('Value'),
				(new CTextBox('filter', getRequest('filter', '')))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
			);

		$filterTask = getRequest('filter_task', 0);

		$tasks = [new CComboBox('filter_task', $filterTask, 'submit()', [
			FILTER_TASK_SHOW => _('Show selected'),
			FILTER_TASK_HIDE => _('Hide selected'),
			FILTER_TASK_MARK => _('Mark selected'),
			FILTER_TASK_INVERT_MARK => _('Mark others')
		])];

		if (str_in_array($filterTask, [FILTER_TASK_MARK, FILTER_TASK_INVERT_MARK])) {
			$tasks[] = ' ';
			$tasks[] = new CComboBox('mark_color', getRequest('mark_color', 0), null, [
				MARK_COLOR_RED => _('as Red'),
				MARK_COLOR_GREEN => _('as Green'),
				MARK_COLOR_BLUE => _('as Blue')
			]);
		}

		$filterColumn1->addRow(_('Selected'), $tasks);
		$filterForm->addColumn($filterColumn1);
	}
}

// for batch graphs don't remember the time selection in the profiles
if ($this->data['action'] == HISTORY_BATCH_GRAPH) {
	$profileIdx = null;
	$profileIdx2 = null;
	$updateProfile = false;
}
else {
	$profileIdx = 'web.item.graph';
	$profileIdx2 = reset($this->data['itemids']);
	$updateProfile = ($this->data['period'] !== null || $this->data['stime'] !== null || $this->data['isNow'] !== null);
}

// create history screen
$screen = CScreenBuilder::getScreen([
	'resourcetype' => SCREEN_RESOURCE_HISTORY,
	'action' => $this->data['action'],
	'itemids' => $data['itemids'],
	'profileIdx' => $profileIdx,
	'profileIdx2' => $profileIdx2,
	'updateProfile' => $updateProfile,
	'period' => $this->data['period'],
	'stime' => $this->data['stime'],
	'isNow' => $this->data['isNow'],
	'filter' => getRequest('filter'),
	'filter_task' => getRequest('filter_task'),
	'mark_color' => getRequest('mark_color'),
	'plaintext' => $this->data['plaintext'],
	'graphtype' => $this->data['graphtype']
]);

// append plaintext to widget
if ($this->data['plaintext']) {
	foreach ($header_row as $text) {
		$historyWidget->addItem([new CSpan($text), BR()]);
	}

	$screen = $screen->get();

	$pre = new CPre();
	foreach ($screen as $text) {
		$pre->addItem([$text, BR()]);
	}
	$historyWidget->addItem($pre);
}
else {
	$historyWidget->setTitle($header['left'])
		->setControls($header['right']);

	if (isset($this->data['iv_string'][$this->data['value_type']])) {
		$filterForm->addNavigator();
	}

	if (in_array($this->data['action'], [HISTORY_VALUES, HISTORY_GRAPH, HISTORY_BATCH_GRAPH])) {
		if(!isset($filterForm)) {
			$filterForm = new CFilter('web.history.filter.state');
		}

		// display the graph type filter for graphs with multiple items
		if ($this->data['action'] == HISTORY_BATCH_GRAPH) {
			$filterForm->addColumn(
				(new CFormList())->addRow(_('Graph type'),
					(new CRadioButtonList('graphtype', (int) $this->data['graphtype']))
						->addValue(_('Normal'), GRAPH_TYPE_NORMAL)
						->addValue(_('Stacked'), GRAPH_TYPE_STACKED)
						->setModern(true)
				)
			);
			$filterForm->removeButtons();
			$filterForm->addVar('fullscreen', $this->data['fullscreen'] ? '1' : null);
			$filterForm->addVar('action', $this->data['action']);
			$filterForm->addVar('itemids', $this->data['itemids']);
		}

		$filterForm->addNavigator();
		$historyWidget->addItem($filterForm);
	}

	$historyWidget->addItem($screen->get());

	if ($data['action'] !== HISTORY_LATEST) {
		CScreenBuilder::insertScreenStandardJs([
			'timeline' => $screen->timeline,
			'profileIdx' => $screen->profileIdx,
			'profileIdx2' => $screen->profileIdx2
		]);
	}
}

return $historyWidget;
