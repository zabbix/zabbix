<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

$header = array('left' => count($this->data['items']).SPACE._('ITEMS'), 'right' => array());
$headerPlaintext = array();

if (count($this->data['items']) == 1) {
	$header['left'] = array(new CLink($this->data['item']['hostname'], 'latest.php?hostid='.$this->data['item']['hostid']), NAME_DELIMITER, $this->data['item']['name_expanded']);
	$headerPlaintext[] = $this->data['item']['hostname'].NAME_DELIMITER.$this->data['item']['name_expanded'];

	if ($this->data['action'] == 'showgraph') {
		$header['right'][] = get_icon('favourite', array(
			'fav' => 'web.favorite.graphids',
			'elid' => $this->data['item']['itemid'],
			'elname' => 'itemid'
		));
	}
}

$header['right'][] = SPACE;
$header['right'][] = get_icon('fullscreen', array('fullscreen' => $this->data['fullscreen']));

// append action form to header
$actionForm = new CForm('get');
$actionForm->addVar('itemid', $_REQUEST['itemid']);

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
if (isset($this->data['iv_numeric'][$this->data['item']['value_type']])) {
	$actionComboBox->addItem('showgraph', _('Graph'));
}
$actionComboBox->addItem('showvalues', _('Values'));
$actionComboBox->addItem('showlatest', _('500 latest values'));
$actionForm->addItem($actionComboBox);

if ($this->data['action'] != 'showgraph') {
	$actionForm->addItem(array(SPACE, new CSubmit('plaintext', _('As plain text'))));
}

array_unshift($header['right'], $actionForm, SPACE);

// create filter
if ($this->data['action'] == 'showvalues' || $this->data['action'] == 'showlatest') {
	if (isset($this->data['iv_string'][$this->data['item']['value_type']])) {
		$filterForm = new CFormTable(null, null, 'get');
		$filterForm->setAttribute('name', 'zbx_filter');
		$filterForm->setAttribute('id', 'zbx_filter');
		$filterForm->addVar('action', $this->data['action']);
		$filterForm->addVar('itemid', zbx_toHash($_REQUEST['itemid']));

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
				'&reference=itemid&srctbl=items&value_types[]='.$this->data['item']['value_type']."&srcfld1=itemid');");
		$deleteItemButton = null;

		if (count($this->data['items']) > 1) {
			$deleteItemButton = new CSubmit('remove_log', _('Remove selected'), "javascript: removeSelectedItems('cmbitemlist_', 'itemid')");
		}

		$filterForm->addRow(_('Items list'), array($itemListbox, BR(), $addItemButton, $deleteItemButton));
		$filterForm->addRow(_('Select rows with value like'), new CTextBox('filter', get_request('filter', ''), ZBX_TEXTBOX_FILTER_SIZE));

		$filterTask = get_request('filter_task', 0);

		$taskComboBox = new CComboBox('filter_task', $filterTask, 'submit()');
		$taskComboBox->addItem(FILTER_TASK_SHOW, _('Show selected'));
		$taskComboBox->addItem(FILTER_TASK_HIDE, _('Hide selected'));
		$taskComboBox->addItem(FILTER_TASK_MARK, _('Mark selected'));
		$taskComboBox->addItem(FILTER_TASK_INVERT_MARK, _('Mark others'));
		$tasks = array($taskComboBox);

		if (str_in_array($filterTask, array(FILTER_TASK_MARK, FILTER_TASK_INVERT_MARK))) {
			$colorComboBox = new CComboBox('mark_color', get_request('mark_color', 0));
			$colorComboBox->addItem(MARK_COLOR_RED, _('as Red'));
			$colorComboBox->addItem(MARK_COLOR_GREEN, _('as Green'));
			$colorComboBox->addItem(MARK_COLOR_BLUE, _('as Blue'));

			$tasks[] = SPACE;
			$tasks[] = $colorComboBox;
		}

		$filterForm->addRow(_('Selected'), $tasks);
		$filterForm->addItemToBottomRow(new CSubmit('select', _('Filter')));
	}
}

// create history screen
$screen = CScreenBuilder::getScreen(array(
	'resourcetype' => SCREEN_RESOURCE_HISTORY,
	'action' => $this->data['action'],
	'items' => $this->data['items'],
	'item' => $this->data['item'],
	'itemids' => $this->data['itemids'],
	'profileIdx' => 'web.item.graph',
	'profileIdx2' => reset($this->data['itemids']),
	'period' => $this->data['period'],
	'stime' => $this->data['stime'],
	'filter' => get_request('filter'),
	'filter_task' => get_request('filter_task'),
	'mark_color' => get_request('mark_color'),
	'plaintext' => $this->data['plaintext']
));

// append plaintext to widget
if ($this->data['plaintext']) {
	$plaintextSpan = new CSpan(null, 'textblackwhite');

	foreach ($headerPlaintext as $text) {
		$plaintextSpan->addItem(array(new CJSscript($text), BR()));
	}

	$screen = $screen->get();

	$pre = new CTag('pre', true);
	foreach ($screen as $text) {
		$pre->addItem(new CJSscript($text));
	}
	$plaintextSpan->addItem($pre);
	$historyWidget->addItem($plaintextSpan);
}

// append graph to widget
else {
	$right = new CTable();
	$right->addRow($header['right']);

	$historyWidget->addPageHeader($header['left'], $right);
	$historyWidget->addItem(SPACE);

	if (isset($this->data['iv_string'][$this->data['item']['value_type']])) {
		$historyWidget->addFlicker($filterForm, CProfile::get('web.history.filter.state', 1));
	}

	$historyTable = new CTable(null, 'maxwidth');
	$historyTable->addRow($screen->get());

	$historyWidget->addItem($historyTable);

	if ($this->data['action'] == 'showvalues' || $this->data['action'] == 'showgraph') {
		$historyWidget->addFlicker(new CDiv(null, null, 'scrollbar_cntr'), CProfile::get('web.history.filter.state', 1));

		CScreenBuilder::insertScreenStandardJs(array(
			'timeline' => $screen->timeline,
			'profileIdx' => $screen->profileIdx
		));
	}
}

return $historyWidget;
