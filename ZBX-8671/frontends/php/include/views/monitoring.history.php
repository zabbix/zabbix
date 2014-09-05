<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

$historyWidget = new CWidget(null, 'history');

$header = array(
	'left' => _n('%1$s ITEM', '%1$s ITEMS', count($this->data['items'])),
	'right' => array()
);
$headerPlaintext = array();

if ($this->data['action'] != HISTORY_BATCH_GRAPH) {
	$item = reset($this->data['items']);
	$host = reset($item['hosts']);

	$header['left'] = array(
		new CLink($host['name'], 'latest.php?filter_set=1&hostids[]='.$item['hostid']),
		NAME_DELIMITER,
		$item['name_expanded']
	);
	$headerPlaintext[] = $host['name'].NAME_DELIMITER.$item['name_expanded'];

	if ($this->data['action'] == HISTORY_GRAPH) {
		$header['right'][] = get_icon('favourite', array(
			'fav' => 'web.favorite.graphids',
			'elid' => $item['itemid'],
			'elname' => 'itemid'
		));
	}
}

$header['right'][] = ' ';
$header['right'][] = get_icon('fullscreen', array('fullscreen' => $this->data['fullscreen']));

// don't display the action form if we view multiple items on a graph
if ($this->data['action'] != HISTORY_BATCH_GRAPH) {
	$actionForm = new CForm('get');
	$actionForm->addVar('itemids', getRequest('itemids'));

	if (isset($_REQUEST['filter_task'])) {
		$actionForm->addVar('filter_task', $_REQUEST['filter_task']);
	}
	if (isset($_REQUEST['filter'])) {
		$actionForm->addVar('filter', $_REQUEST['filter']);
	}
	if (isset($_REQUEST['mark_color'])) {
		$actionForm->addVar('mark_color', $_REQUEST['mark_color']);
	}

	$actionComboBox = new CComboBox('action', $this->data['action'], 'submit()');
	if (isset($this->data['iv_numeric'][$this->data['value_type']])) {
		$actionComboBox->addItem(HISTORY_GRAPH, _('Graph'));
	}
	$actionComboBox->addItem(HISTORY_VALUES, _('Values'));
	$actionComboBox->addItem(HISTORY_LATEST, _('500 latest values'));
	$actionForm->addItem($actionComboBox);

	if ($this->data['action'] != HISTORY_GRAPH) {
		$actionForm->addItem(array(' ', new CSubmit('plaintext', _('As plain text'))));
	}

	array_unshift($header['right'], $actionForm, ' ');
}

// create filter
if ($this->data['action'] == HISTORY_VALUES || $this->data['action'] == HISTORY_LATEST) {
	if (isset($this->data['iv_string'][$this->data['value_type']])) {
		$filterForm = new CFormTable(null, null, 'get');
		$filterForm->setTableClass('formtable old-filter');
		$filterForm->setAttribute('name', 'zbx_filter');
		$filterForm->setAttribute('id', 'zbx_filter');
		$filterForm->addVar('action', $this->data['action']);
		foreach (getRequest('itemids') as $itemId) {
			$filterForm->addVar('itemids[]', $itemId, 'filter_itemids_'.$itemId);
		}

		$itemListbox = new CListBox('cmbitemlist[]');
		$itemsData = array();
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

		$addItemButton = new CButton('add_log', _('Add'), "return PopUp('popup.php?multiselect=1&real_hosts=1".
				'&reference=itemid&srctbl=items&value_types[]='.$this->data['value_type']."&srcfld1=itemid');");
		$deleteItemButton = null;

		if (count($this->data['items']) > 1) {
			$deleteItemButton = new CSubmit('remove_log', _('Remove selected'));
		}

		$filterForm->addRow(_('Items list'), array($itemListbox, BR(), $addItemButton, $deleteItemButton));
		$filterForm->addRow(_('Select rows with value like'), new CTextBox('filter', getRequest('filter', ''), ZBX_TEXTBOX_FILTER_SIZE));

		$filterTask = getRequest('filter_task', 0);

		$taskComboBox = new CComboBox('filter_task', $filterTask, 'submit()');
		$taskComboBox->addItem(FILTER_TASK_SHOW, _('Show selected'));
		$taskComboBox->addItem(FILTER_TASK_HIDE, _('Hide selected'));
		$taskComboBox->addItem(FILTER_TASK_MARK, _('Mark selected'));
		$taskComboBox->addItem(FILTER_TASK_INVERT_MARK, _('Mark others'));
		$tasks = array($taskComboBox);

		if (str_in_array($filterTask, array(FILTER_TASK_MARK, FILTER_TASK_INVERT_MARK))) {
			$colorComboBox = new CComboBox('mark_color', getRequest('mark_color', 0));
			$colorComboBox->addItem(MARK_COLOR_RED, _('as Red'));
			$colorComboBox->addItem(MARK_COLOR_GREEN, _('as Green'));
			$colorComboBox->addItem(MARK_COLOR_BLUE, _('as Blue'));

			$tasks[] = ' ';
			$tasks[] = $colorComboBox;
		}

		$filterForm->addRow(_('Selected'), $tasks);
		$filterForm->addItemToBottomRow(new CSubmit('select', _('Filter')));
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
$screen = CScreenBuilder::getScreen(array(
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
));

// append plaintext to widget
if ($this->data['plaintext']) {
	$plaintextSpan = new CSpan(null, 'textblackwhite');

	foreach ($headerPlaintext as $text) {
		$plaintextSpan->addItem(array(new CJsScript($text), BR()));
	}

	$screen = $screen->get();

	$pre = new CTag('pre', true);
	foreach ($screen as $text) {
		$pre->addItem(new CJsScript($text));
	}
	$plaintextSpan->addItem($pre);
	$historyWidget->addItem($plaintextSpan);
}
else {
	$right = new CTable();
	$right->addRow($header['right']);

	$historyWidget->addPageHeader($header['left'], $right);
	$historyWidget->addItem(BR());

	if (isset($this->data['iv_string'][$this->data['value_type']])) {
		$historyWidget->addFlicker($filterForm, CProfile::get('web.history.filter.state', 1));
	}

	$historyTable = new CTable(null, 'maxwidth');
	$historyTable->addRow($screen->get());

	$historyWidget->addItem($historyTable);

	if (in_array($this->data['action'], array(HISTORY_VALUES, HISTORY_GRAPH, HISTORY_BATCH_GRAPH))) {
		// time bar
		$filter = array(
			new CDiv(null, null, 'scrollbar_cntr')
		);

		// display the graph type filter for graphs with multiple items
		if ($this->data['action'] == HISTORY_BATCH_GRAPH) {
			$filterTable = new CTable('', 'filter');

			$graphType = array(
				new CRadioButton('graphtype', GRAPH_TYPE_NORMAL, null, 'graphtype_'.GRAPH_TYPE_NORMAL,
					($this->data['graphtype'] == GRAPH_TYPE_NORMAL)
				),
				new CLabel(_('Normal'), 'graphtype_'.GRAPH_TYPE_NORMAL),
				new CRadioButton('graphtype', GRAPH_TYPE_STACKED, null, 'graphtype_'.GRAPH_TYPE_STACKED,
					($this->data['graphtype'] == GRAPH_TYPE_STACKED)
				),
				new CLabel(_('Stacked'), 'graphtype_'.GRAPH_TYPE_STACKED)
			);
			$filterTable->addRow(array(
				new CCol(bold(_('Graph type').':'), 'label'),
				new CCol(new CSpan($graphType, 'jqueryinputset'), 'buttoncol')
			));

			$filterForm = new CForm('GET');
			$filterForm->setAttribute('name', 'zbx_filter');
			$filterForm->setAttribute('id', 'zbx_filter');
			$filterForm->addVar('action', $this->data['action']);
			$filterForm->addVar('itemids', $this->data['itemids']);
			$filterForm->addItem($filterTable);

			$filter[] = $filterForm;
		}

		$historyWidget->addFlicker($filter, CProfile::get('web.history.filter.state', 1));

		CScreenBuilder::insertScreenStandardJs(array(
			'timeline' => $screen->timeline,
			'profileIdx' => $screen->profileIdx
		));
	}
}

return $historyWidget;
