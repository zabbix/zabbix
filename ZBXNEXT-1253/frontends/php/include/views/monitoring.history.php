<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/js/monitoring.history.js.php';

$historyWidget = new CWidget();
$historyWidget->addItem(SPACE);

$header = array('left' => count($this->data['items']).SPACE._('ITEMS'), 'right' => array());
$plaintextData = array('header' => array(), 'body' => array());

if (count($this->data['items']) == 1) {
	$itemName = itemName($this->data['item']);

	$header['left'] = array(new CLink($this->data['item']['hostname'], 'latest.php?hostid='.$this->data['item']['hostid']), ': ', $itemName);
	$plaintextData['header'][] = $this->data['item']['hostname'].': '.$itemName;

	if ($this->data['action'] == 'showgraph') {
		$header['right'][] = get_icon('favourite', array(
			'fav' => 'web.favorite.graphids',
			'elid' => $this->data['item']['itemid'],
			'elname' => 'itemid'
		));
	}
}

// append action form to header
$actionForm = new CForm('get');
$actionForm->addVar('itemid', $this->data['itemid']);

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

// append showvalues and showlatest actions
if ($this->data['action'] == 'showvalues' || $this->data['action'] == 'showlatest') {
	// filter
	if (isset($this->data['iv_string'][$this->data['item']['value_type']])) {
		$filterTask = get_request('filter_task', 0);
		$filter = get_request('filter', '');
		$mark_color = get_request('mark_color', 0);

		$filterForm = new CFormTable(null, null, 'get');
		$filterForm->setAttribute('name', 'zbx_filter');
		$filterForm->setAttribute('id', 'zbx_filter');
		$filterForm->addVar('action', $this->data['action']);
		$filterForm->addVar('itemid', zbx_toHash($_REQUEST['itemid']));

		$itemListbox = new CListBox('cmbitemlist[]');
		foreach ($this->data['items'] as $itemid => $item) {
			if (!isset($this->data['iv_string'][$item['value_type']])) {
				unset($this->data['items'][$itemid]);
				continue;
			}

			$host = reset($item['hosts']);
			$itemListbox->addItem($itemid, $host['name'].': '.itemName($item));
		}

		$addItemButton = new CButton('add_log', _('Add'), "return PopUp('popup.php?multiselect=1&real_hosts=1".
				'&reference=itemid&srctbl=items&value_types[]='.$this->data['item']['value_type']."&srcfld1=itemid');");
		$deleteItemButton = null;

		if (count($this->data['items']) > 1) {
			insert_js_function('removeSelectedItems');
			$deleteItemButton = new CSubmit('remove_log', _('Remove selected'), "javascript: removeSelectedItems('cmbitemlist_', 'itemid')");
		}

		$filterForm->addRow(_('Items list'), array($itemListbox, BR(), $addItemButton, $deleteItemButton));
		$filterForm->addRow(_('Select rows with value like'), new CTextBox('filter', $filter, ZBX_TEXTBOX_FILTER_SIZE));

		$taskComboBox = new CComboBox('filter_task', $filterTask, 'submit()');
		$taskComboBox->addItem(FILTER_TASK_SHOW, _('Show selected'));
		$taskComboBox->addItem(FILTER_TASK_HIDE, _('Hide selected'));
		$taskComboBox->addItem(FILTER_TASK_MARK, _('Mark selected'));
		$taskComboBox->addItem(FILTER_TASK_INVERT_MARK, _('Mark others'));

		$tasks = array($taskComboBox);

		if (str_in_array($filterTask, array(FILTER_TASK_MARK, FILTER_TASK_INVERT_MARK))) {
			$colorComboBox = new CComboBox('mark_color', $mark_color);
			$colorComboBox->addItem(MARK_COLOR_RED, _('as Red'));
			$colorComboBox->addItem(MARK_COLOR_GREEN, _('as Green'));
			$colorComboBox->addItem(MARK_COLOR_BLUE, _('as Blue'));

			$tasks[] = SPACE;
			$tasks[] = $colorComboBox;
		}

		$filterForm->addRow(_('Selected'), $tasks);
		$filterForm->addItemToBottomRow(new CSubmit('select', _('Filter')));
	}

	// body
	$isManyItems = (count($this->data['items']) > 1);

	$options = array(
		'history' => $this->data['item']['value_type'],
		'itemids' => array_keys($this->data['items']),
		'output' => API_OUTPUT_EXTEND,
		'sortorder' => ZBX_SORT_DOWN
	);
	if ($this->data['action'] == 'showlatest') {
		$options['limit'] = 500;
	}
	elseif ($this->data['action'] == 'showvalues') {
		$config = select_config();

		$options['time_from'] = $this->data['time'] - 10; // some seconds to allow script to execute
		$options['time_till'] = $this->data['till'];
		$options['limit'] = $config['search_limit'];
	}

	// text log
	if (isset($this->data['iv_string'][$this->data['item']['value_type']])) {
		$useLogItem = ($this->data['item']['value_type'] == ITEM_VALUE_TYPE_LOG);

		// is this an eventlog item? If so, we must show some additional columns
		$useEventLogItem = (strpos($this->data['item']['key_'], 'eventlog[') === 0);

		$historyTable = new CTableInfo(_('No history defined.'));
		$historyTable->setHeader(
			array(
				_('Timestamp'),
				$isManyItems ? _('Item') : null,
				$useLogItem ? _('Local time') : null,
				($useEventLogItem && $useLogItem) ? _('Source') : null,
				($useEventLogItem && $useLogItem) ? _('Severity') : null,
				($useEventLogItem && $useLogItem) ? _('Event ID') : null,
				_('Value')
			),
			'header'
		);

		if (isset($_REQUEST['filter']) && !zbx_empty($_REQUEST['filter'])
				&& in_array($_REQUEST['filter_task'], array(FILTER_TASK_SHOW, FILTER_TASK_HIDE))) {
			$options['search'] = array('value' => $_REQUEST['filter']);

			if ($_REQUEST['filter_task'] == FILTER_TASK_HIDE) {
				$options['excludeSearch'] = 1;
			}
		}
		$options['sortfield'] = 'id';
		$historyData = API::History()->get($options);

		foreach ($historyData as $data) {
			$item = $this->data['items'][$data['itemid']];
			$host = reset($item['hosts']);
			$color = null;

			if (isset($_REQUEST['filter']) && !zbx_empty($_REQUEST['filter'])) {
				$contain = zbx_stristr($data['value'], $_REQUEST['filter']);

				if (!isset($_REQUEST['mark_color'])) {
					$_REQUEST['mark_color'] = MARK_COLOR_RED;
				}
				if ($contain && $_REQUEST['filter_task'] == FILTER_TASK_MARK) {
					$color = $_REQUEST['mark_color'];
				}
				if (!$contain && $_REQUEST['filter_task'] == FILTER_TASK_INVERT_MARK) {
					$color = $_REQUEST['mark_color'];
				}

				switch ($color) {
					case MARK_COLOR_RED:
						$color = 'red';
						break;
					case MARK_COLOR_GREEN:
						$color = 'green';
						break;
					case MARK_COLOR_BLUE:
						$color = 'blue';
						break;
				}
			}

			$row = array(nbsp(zbx_date2str(_('[Y.M.d H:i:s]'), $data['clock'])));

			if ($isManyItems) {
				$row[] = $host['name'].': '.itemName($item);
			}

			if ($useLogItem) {
				$row[] = ($data['timestamp'] == 0) ? '-' : zbx_date2str(HISTORY_LOG_LOCALTIME_DATE_FORMAT, $data['timestamp']);

				// if this is a eventLog item, showing additional info
				if ($useEventLogItem) {
					$row[] = zbx_empty($data['source']) ? '-' : $data['source'];
					$row[] = ($data['severity'] == 0)
						? '-'
						: new CCol(get_item_logtype_description($data['severity']), get_item_logtype_style($data['severity']));
					$row[] = ($data['logeventid'] == 0) ? '-' : $data['logeventid'];
				}
			}

			$data['value'] = encode_log(trim($data['value'], "\r\n"));
			$row[] = new CCol($data['value'], 'pre');

			$newRow = new CRow($row);
			if (is_null($color)) {
				$min_color = 0x98;
				$max_color = 0xF8;
				$int_color = ($max_color - $min_color) / count($_REQUEST['itemid']);
				$int_color *= array_search($data['itemid'], $_REQUEST['itemid']);
				$int_color += $min_color;
				$newRow->setAttribute('style', 'background-color: '.sprintf("#%X%X%X", $int_color, $int_color, $int_color));
			}
			elseif (!is_null($color)) {
				$newRow->setAttribute('class', $color);
			}

			$historyTable->addRow($newRow);

			// plain text
			if (empty($_REQUEST['plaintext'])) {
				continue;
			}

			$plaintextData['body'][] = zbx_date2str(HISTORY_LOG_ITEM_PLAINTEXT, $data['clock']);
			$plaintextData['body'][] = "\t".$data['clock']."\t".htmlspecialchars($data['value'])."\n";
		}
	}

	// numeric, float
	else {
		$historyTable = new CTableInfo(_('No history defined.'));
		$historyTable->setHeader(array(_('Timestamp'), _('Value')));

		$options['sortfield'] = array('itemid', 'clock');
		$historyData = API::History()->get($options);

		foreach ($historyData as $data) {
			$item = $this->data['items'][$data['itemid']];
			$host = reset($item['hosts']);

			if (empty($data['value'])) {
				$data['value'] = '';
			}

			if ($item['valuemapid'] > 0) {
				$value = applyValueMap($data['value'], $item['valuemapid']);
				$value_mapped = true;
			}
			else {
				$value = $data['value'];
				$value_mapped = false;
			}

			if ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT && !$value_mapped) {
				sscanf($data['value'], '%f', $value);
			}

			$historyTable->addRow(array(
				zbx_date2str(HISTORY_ITEM_DATE_FORMAT, $data['clock']),
				zbx_nl2br($value)
			));

			if (empty($_REQUEST['plaintext'])) {
				continue;
			}

			if ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT) {
				sscanf($data['value'], '%f', $value);
			}
			else {
				$value = $data['value'];
			}

			$plaintextData['body'][] = zbx_date2str(HISTORY_PLAINTEXT_DATE_FORMAT, $data['clock']);
			$plaintextData['body'][] = "\t".$data['clock']."\t".htmlspecialchars($value)."\n";
		}
	}
}

// append showgraph action
if ($this->data['action'] == 'showgraph' && !isset($this->data['iv_string'][$this->data['item']['value_type']])) {
	$domGraphId = 'graph';
	$containerid = 'graph_cont1';
	$src = 'chart.php?itemid='.$this->data['item']['itemid'];

	$historyTable = new CTableInfo(_('No charts defined.'), 'chart');
	$graphContainer = new CCol();
	$graphContainer->setAttribute('id', $containerid);
	$historyTable->addRow($graphContainer);
}

if (str_in_array($this->data['action'], array('showvalues', 'showgraph'))) {
	$graphDims = getGraphDims();

	// nav bar
	$utime = zbxDateToTime($_REQUEST['stime']);
	$starttime = get_min_itemclock_by_itemid($this->data['item']['itemid']);
	if ($utime < $starttime) {
		$starttime = $utime;
	}

	$timeline = array(
		'starttime' => date('YmdHis', $starttime),
		'period' => $this->data['period'],
		'usertime' => date('YmdHis', $utime + $this->data['period'])
	);

	$timeControlData = array(
		'periodFixed' => CProfile::get('web.history.timelinefixed', 1),
		'sliderMaximumTimePeriod' => ZBX_MAX_PERIOD
	);

	if (!empty($domGraphId)) {
		$timeControlData['id'] = $this->data['itemid'];
		$timeControlData['domid'] = $domGraphId;
		$timeControlData['containerid'] = $containerid;
		$timeControlData['src'] = $src;
		$timeControlData['objDims'] = $graphDims;
		$timeControlData['loadSBox'] = 1;
		$timeControlData['loadImage'] = 1;
		$timeControlData['loadScroll'] = 1;
		$timeControlData['scrollWidthByImage'] = 1;
		$timeControlData['dynamic'] = 1;
	}
	else {
		$domGraphId = 'graph';
		$timeControlData['id'] = $this->data['itemid'];
		$timeControlData['domid'] = $domGraphId;
		$timeControlData['loadSBox'] = 0;
		$timeControlData['loadImage'] = 0;
		$timeControlData['loadScroll'] = 1;
		$timeControlData['dynamic'] = 0;
		$timeControlData['mainObject'] = 1;
	}
}

if (!isset($_REQUEST['plaintext']) ) {
	$right = new CTable();
	$right->addRow($header['right']);

	$historyWidget->addPageHeader($header['left'], $right);

	if (isset($this->data['iv_string'][$this->data['item']['value_type']])) {
		$historyWidget->addFlicker($filterForm, CProfile::get('web.history.filter.state', 1));
	}

	$historyWidget->addItem($historyTable);

	if (str_in_array($this->data['action'], array('showvalues', 'showgraph'))) {
		zbx_add_post_js('timeControl.addObject("'.$domGraphId.'", '.zbx_jsvalue($timeline).', '.zbx_jsvalue($timeControlData).');');
		zbx_add_post_js('timeControl.processObjects();');

		$scroll_div = new CDiv();
		$scroll_div->setAttribute('id', 'scrollbar_cntr');
		$historyWidget->addFlicker($scroll_div, CProfile::get('web.history.filter.state', 1));
	}

	return $historyWidget;
}
else {
	$historySpan = new CSpan(null, 'textblackwhite');

	foreach ($plaintextData['header'] as $text) {
		$historySpan->addItem(array(new CJSscript($text), BR()));
	}

	$pre = new CTag('pre', true);
	foreach ($plaintextData['body'] as $text) {
		$pre->addItem(new CJSscript($text));
	}

	$historySpan->addItem($pre);

	return $historySpan;
}
