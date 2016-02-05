<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
	'right' => new CList()
];
$headerPlaintext = [];

$hostNames = [];
foreach ($this->data['items'] as $itemData) {
	$hostName = $itemData['hosts'][0]['name'];

	if (!array_key_exists($hostName, $hostNames)) {
		$hostNames[$hostName] = $hostName;
	}
}

$item = reset($this->data['items']);
$host = reset($item['hosts']);

if ($this->data['action'] != HISTORY_BATCH_GRAPH) {
	$header['left'] = [
		$host['name'],
		NAME_DELIMITER,
		$item['name_expanded']
	];
	$headerPlaintext[] = $host['name'].NAME_DELIMITER.$item['name_expanded'];
}
elseif (count($hostNames) == 1) {
	$header['left'] = [
		$host['name'],
		NAME_DELIMITER,
		$header['left']
	];
}

// don't display the action form if we view multiple items on a graph
if ($this->data['action'] != HISTORY_BATCH_GRAPH) {
	$actionForm = (new CForm('get'))
		->addVar('itemids', getRequest('itemids'));

	if (isset($_REQUEST['filter_task'])) {
		$actionForm->addVar('filter_task', $_REQUEST['filter_task']);
	}
	if (isset($_REQUEST['filter'])) {
		$actionForm->addVar('filter', $_REQUEST['filter']);
	}
	if (isset($_REQUEST['mark_color'])) {
		$actionForm->addVar('mark_color', $_REQUEST['mark_color']);
	}

	$actions = [];
	if (isset($this->data['iv_numeric'][$this->data['value_type']])) {
		$actions[HISTORY_GRAPH] = _('Graph');
	}
	$actions[HISTORY_VALUES] = _('Values');
	$actions[HISTORY_LATEST] = _('500 latest values');
	$actionForm->addItem(new CComboBox('action', $this->data['action'], 'submit()', $actions));

	if ($this->data['action'] != HISTORY_GRAPH) {
		$actionForm->addItem([
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			new CSubmit('plaintext', _('As plain text'))
		]);
	}

	$header['right']->addItem($actionForm);
}

if ($this->data['action'] != HISTORY_BATCH_GRAPH) {
	if ($this->data['action'] == HISTORY_GRAPH) {
		$header['right']->addItem(get_icon('favourite', [
			'fav' => 'web.favorite.graphids',
			'elid' => $item['itemid'],
			'elname' => 'itemid'
		]));
	}
}

$header['right']->addItem(get_icon('fullscreen', ['fullscreen' => $this->data['fullscreen']]));

// create filter
if ($this->data['action'] == HISTORY_VALUES || $this->data['action'] == HISTORY_LATEST) {
	if (isset($this->data['iv_string'][$this->data['value_type']])) {
		$filterForm = (new CFilter('web.history.filter.state'))
			->addVar('fullscreen', $this->data['fullscreen'])
			->addVar('action', $this->data['action']);
		foreach (getRequest('itemids') as $itemId) {
			$filterForm->addVar('itemids['.$itemId.']', $itemId);
		}

		$itemListbox = new CListBox('cmbitemlist[]');
		$itemsData = [];
		foreach ($this->data['items'] as $itemid => $item) {
			if (!isset($this->data['iv_string'][$item['value_type']])) {
				unset($this->data['items'][$itemid]);
				continue;
			}

			$host = reset($item['hosts']);
			$itemsData[$itemid]['id'] = $itemid;
			$itemsData[$itemid]['name'] = $host['name'].NAME_DELIMITER.$item['name_expanded'];
		}

		order_result($itemsData, 'name');
		foreach ($itemsData as $item) {
			$itemListbox->addItem($item['id'], $item['name']);
		}

		$addItemButton = (new CButton('add_log', _('Add')))
			->onClick("return PopUp('popup.php?multiselect=1&real_hosts=1".
					'&reference=itemid&srctbl=items&value_types[]='.$this->data['value_type']."&srcfld1=itemid');");
		$deleteItemButton = null;

		if (count($this->data['items']) > 1) {
			$deleteItemButton = [
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				new CButton('remove_log', _('Remove selected'))
			];
		}

		$filterColumn1 = (new CFormList())
			->addRow(_('Items list'), [$itemListbox, BR(), $addItemButton, $deleteItemButton])
			->addRow(_('Select rows with value like'),
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
	$profileIdx = false;
	$profileIdx2 = false;
	$updateProfile = false;
}
else {
	$profileIdx = 'web.item.graph';
	$profileIdx2 = reset($this->data['itemids']);
	$updateProfile = ($this->data['action'] != HISTORY_BATCH_GRAPH);
}

// create history screen
$screen = CScreenBuilder::getScreen([
	'resourcetype' => SCREEN_RESOURCE_HISTORY,
	'action' => $this->data['action'],
	'items' => $this->data['items'],
	'profileIdx' => $profileIdx,
	'profileIdx2' => $profileIdx2,
	'updateProfile' => $updateProfile,
	'period' => $this->data['period'],
	'stime' => $this->data['stime'],
	'filter' => getRequest('filter'),
	'filter_task' => getRequest('filter_task'),
	'mark_color' => getRequest('mark_color'),
	'plaintext' => $this->data['plaintext'],
	'graphtype' => $this->data['graphtype']
]);

// append plaintext to widget
if ($this->data['plaintext']) {
	foreach ($headerPlaintext as $text) {
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
			$filterForm->addVar('fullscreen', $this->data['fullscreen']);
			$filterForm->addVar('action', $this->data['action']);
			$filterForm->addVar('itemids', $this->data['itemids']);
		}

		$filterForm->addNavigator();
		$historyWidget->addItem($filterForm);
	}

	$historyWidget->addItem($screen->get());

	CScreenBuilder::insertScreenStandardJs([
		'timeline' => $screen->timeline,
		'profileIdx' => $screen->profileIdx
	]);
}

return $historyWidget;
