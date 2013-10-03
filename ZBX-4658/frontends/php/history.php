<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
require_once('include/config.inc.php');
require_once('include/items.inc.php');
require_once('include/graphs.inc.php');

$page['file']	= 'history.php';
$page['title']	= 'S_HISTORY';
$page['hist_arg'] = array('itemid', 'hostid', 'groupid', 'graphid', 'period', 'dec', 'inc', 'left', 'right', 'stime', 'action');
$page['scripts'] = array('class.calendar.js','gtlc.js');

$page['type'] = detect_page_type(PAGE_TYPE_HTML);

if(isset($_REQUEST['plaintext'])) define('ZBX_PAGE_NO_MENU', 1);
else if(PAGE_TYPE_HTML == $page['type']) define('ZBX_PAGE_DO_REFRESH', 1);

include_once('include/page_header.php');

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'itemid'=>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,	'!isset({favobj})'),

		'period'=>	array(T_ZBX_INT, O_OPT,	 null,	null, null),
		'dec'=>		array(T_ZBX_INT, O_OPT,	 null,	null, null),
		'inc'=>		array(T_ZBX_INT, O_OPT,	 null,	null, null),
		'left'=>	array(T_ZBX_INT, O_OPT,	 null,	null, null),
		'right'=>	array(T_ZBX_INT, O_OPT,	 null,	null, null),
		'stime'=>	array(T_ZBX_STR, O_OPT,	 null,	null, null),

		'filter_task'=>	array(T_ZBX_STR, O_OPT,	 null,	IN(FILTER_TASK_SHOW.','.FILTER_TASK_HIDE.','.FILTER_TASK_MARK.','.FILTER_TASK_INVERT_MARK), null),
		'filter'=>		array(T_ZBX_STR, O_OPT,	 null,	null, null),
		'mark_color'=>	array(T_ZBX_STR, O_OPT,	 null,	IN(MARK_COLOR_RED.','.MARK_COLOR_GREEN.','.MARK_COLOR_BLUE), null),

		'cmbitemlist'=>	array(T_ZBX_INT, O_OPT,	 null,	DB_ID, null),

		'plaintext'=>	array(T_ZBX_STR, O_OPT,	 null,	null, null),
		'action'=>		array(T_ZBX_STR, O_OPT,	 P_SYS,	IN('"showgraph","showvalues","showlatest","add","remove"'), null),
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favref'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NOT_EMPTY,		NULL),
		'favid'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NULL,			NULL),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		NULL),
/* actions */
		'remove_log'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'reset'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'cancel'=>			array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
/* other */
		'form'=>			array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		'form_copy_to'=>	array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		'form_refresh'=>	array(T_ZBX_INT, O_OPT,	null,	null,	null),
		'fullscreen'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	null,	null)
	);
	check_fields($fields);

?>
<?php
	if(isset($_REQUEST['favobj'])){
		if('timeline' == $_REQUEST['favobj']){
			navigation_bar_calc('web.item.graph', $_REQUEST['favid'], true);
		}
		if('filter' == $_REQUEST['favobj']){
			CProfile::update('web.history.filter.state',$_REQUEST['state'], PROFILE_TYPE_INT);
		}
		if(str_in_array($_REQUEST['favobj'],array('itemid','graphid'))){
			$result = false;
			if('add' == $_REQUEST['action']){
				$result = add2favorites('web.favorite.graphids',$_REQUEST['favid'],$_REQUEST['favobj']);
				if($result){
					print('$("addrm_fav").title = "'.S_REMOVE_FROM.' '.S_FAVOURITES.'";'."\n");
					print('$("addrm_fav").onclick = function(){rm4favorites("itemid","'.$_REQUEST['favid'].'",0);}'."\n");
				}
			}
			else if('remove' == $_REQUEST['action']){
				$result = rm4favorites('web.favorite.graphids',$_REQUEST['favid'],$_REQUEST['favobj']);

				if($result){
					print('$("addrm_fav").title = "'.S_ADD_TO.' '.S_FAVOURITES.'";'."\n");
					print('$("addrm_fav").onclick = function(){ add2favorites("itemid","'.$_REQUEST['favid'].'");}'."\n");
				}
			}

			if((PAGE_TYPE_JS == $page['type']) && $result){
				print('switchElementsClass("addrm_fav","iconminus","iconplus");');
			}
		}
		// saving fixed/dynamic setting to profile
		if('timelinefixedperiod' == $_REQUEST['favobj']){
			if(isset($_REQUEST['favid'])){
				CProfile::update('web.history.timelinefixed', $_REQUEST['favid'], PROFILE_TYPE_INT);
			}
		}
	}

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		include_once('include/page_footer.php');
		exit();
	}
?>
<?php
// ACTIONS
	$_REQUEST['action'] = get_request('action', 'showgraph');
	$_REQUEST['itemid'] = array_unique(zbx_toArray($_REQUEST['itemid']));

	if(isset($_REQUEST['remove_log']) && isset($_REQUEST['cmbitemlist'])){
		$itemList = array_flip($_REQUEST['cmbitemlist']);

		foreach($_REQUEST['itemid'] as $id => $itemid){
			if(count($_REQUEST['itemid']) == 1) break;
			if(isset($itemList[$itemid])) unset($_REQUEST['itemid'][$id]);
		}

		unset($_REQUEST['remove_log']);
	}
?>
<?php
// INIT
	$iv_string = array(
		ITEM_VALUE_TYPE_LOG => 1,
		ITEM_VALUE_TYPE_TEXT => 1
	);

	$iv_numeric = array(
		ITEM_VALUE_TYPE_FLOAT => 1,
		ITEM_VALUE_TYPE_UINT64 => 1
	);

	$options = array(
		'nodeids' => get_current_nodeid(),
		'itemids' => $_REQUEST['itemid'],
		'webitems' => 1,
		'select_hosts' => array('hostid','host'),
		'output' => API_OUTPUT_EXTEND
	);

	$items = CItem::get($options);
	$items = zbx_toHash($items, 'itemid');

	foreach($_REQUEST['itemid'] as $inum =>  $itemid){
		if(!isset($items[$itemid])) access_deny();
	}

	$item = reset($items);
	$host = reset($item['hosts']);
	$item['host'] = $host['host'];

// resets get params for proper page refresh
	if (isset($_REQUEST['period']) || isset($_REQUEST['stime'])) {
		navigation_bar_calc('web.item.graph', $item['itemid'], true);
		if ($_REQUEST['action'] != 'showvalues') {
			jsRedirect('history.php?action='.get_request('action', 'showgraph').'&itemid='.$item['itemid']);
			include_once('include/page_footer.php');
			exit();
		}
	}
//--

	$period = navigation_bar_calc('web.item.graph', $item['itemid']);
	$bstime = $_REQUEST['stime'];

	$time = zbxDateToTime($bstime);
	$till = $time + $period;
//----

	$historyWidget = new CWidget();
	$historyWidget->addItem(SPACE);

// HEADER
	$header = array(
		'left' => count($items).SPACE.S_ITEMS_BIG,
		'right' => array()
	);

	$ptData = array(
		'header' => array(),
		'body' => array()
	);

	if (count($items) == 1) {
		$ptData['header'][] = $item['host'].': '.item_description($item);

		$header['left'] = array(new CLink($item['host'],'latest.php?hostid='.$item['hostid']),': ',item_description($item));

		if ($_REQUEST['action'] == 'showgraph') {
			$header['right'][] = get_icon('favourite', array(
				'fav' => 'web.favorite.graphids',
				'elid' => $item['itemid'],
				'elname' => 'itemid'
			));
		}
	}

	$form = new CForm(null, 'get');
	$form->addVar('itemid', $_REQUEST['itemid']);

	if(isset($_REQUEST['filter_task']))	$form->addVar('filter_task',$_REQUEST['filter_task']);
	if(isset($_REQUEST['filter']))		$form->addVar('filter',$_REQUEST['filter']);
	if(isset($_REQUEST['mark_color']))	$form->addVar('mark_color',$_REQUEST['mark_color']);

	$cmbAction = new CComboBox('action',$_REQUEST['action'],'submit()');

	if(isset($iv_numeric[$item['value_type']])) $cmbAction->addItem('showgraph',S_GRAPH);
	$cmbAction->addItem('showvalues',S_VALUES);
	$cmbAction->addItem('showlatest',S_500_LATEST_VALUES);

	$form->addItem($cmbAction);

	if($_REQUEST['action'] != 'showgraph')
		$form->addItem(array(SPACE, new CButton('plaintext',S_AS_PLAIN_TEXT)));

	array_unshift($header['right'], $form, SPACE);
//--
?>
<?php

	$itemid = $item['itemid'];

	if($_REQUEST['action']=='showvalues' || $_REQUEST['action']=='showlatest'){
// Filter
		if(isset($iv_string[$item['value_type']])){
			$filter_task = get_request('filter_task',0);
			$filter = get_request('filter','');
			$mark_color = get_request('mark_color',0);

			$filterForm = new CFormTable(null, null, 'get');
			$filterForm->setAttribute('name', 'zbx_filter');
			$filterForm->setAttribute('id', 'zbx_filter');

			$filterForm->addVar('action',$_REQUEST['action']);
			$filterForm->addVar('itemid',zbx_toHash($_REQUEST['itemid']));

			$cmbitemlist = new CListBox('cmbitemlist[]');
			foreach($items as $itemid => $item){
				if(!isset($iv_string[$item['value_type']])){
					unset($items[$itemid]);
					continue;
				}

				$host = reset($item['hosts']);
				$cmbitemlist->addItem($itemid,$host['host'].': '.item_description($item));
			}

			$addItemBttn = new CButton('add_log',S_ADD,"return PopUp('popup.php?multiselect=1".'&reference=itemid&srctbl=items&value_types[]='.$item['value_type']."&srcfld1=itemid');");
			$delItemBttn = null;

			if(count($items) > 1){
				insert_js_function('removeSelectedItems');
				$delItemBttn = new CButton('remove_log',S_REMOVE_SELECTED, "javascript: removeSelectedItems('cmbitemlist[]', 'itemid')");
			}

			$filterForm->addRow(S_ITEMS_LIST, array($cmbitemlist, BR(), $addItemBttn, $delItemBttn));

			$filterForm->addRow(S_SELECT_ROWS_WITH_VALUE_LIKE, new CTextBox('filter',$filter,25));

			$cmbFTask = new CComboBox('filter_task',$filter_task,'submit()');
			$cmbFTask->addItem(FILTER_TASK_SHOW,S_SHOW_SELECTED);
			$cmbFTask->addItem(FILTER_TASK_HIDE,S_HIDE_SELECTED);
			$cmbFTask->addItem(FILTER_TASK_MARK,S_MARK_SELECTED);
			$cmbFTask->addItem(FILTER_TASK_INVERT_MARK,S_MARK_OTHERS);

			$tmp = array($cmbFTask);

			if(str_in_array($filter_task,array(FILTER_TASK_MARK,FILTER_TASK_INVERT_MARK))){
				$cmbColor = new CComboBox('mark_color',$mark_color);
				$cmbColor->addItem(MARK_COLOR_RED,S_AS_RED);
				$cmbColor->addItem(MARK_COLOR_GREEN,S_AS_GREEN);
				$cmbColor->addItem(MARK_COLOR_BLUE,S_AS_BLUE);

				$tmp[] = SPACE;
				$tmp[] = $cmbColor;
			}

			$filterForm->addRow(S_SELECTED, $tmp);

			$filterForm->addItemToBottomRow(new CButton('select',S_FILTER));
		}
// ------

// BODY
		$fewItems = (count($items) > 1);

		$options = array(
			'history' => $item['value_type'],
			'itemids' => array_keys($items),
			'output' => API_OUTPUT_EXTEND,
			'sortorder' => ZBX_SORT_DOWN
		);

		if($_REQUEST['action']=='showlatest'){
			$options['limit'] = 500;
		}
		else if($_REQUEST['action']=='showvalues'){
			$options['time_from'] = $time - 10; // some seconds to allow script to execute
			$options['time_till'] = $till;

			$options['limit'] = $config['search_limit'];
		}

// TEXT LOG
		if(isset($iv_string[$item['value_type']])){
			$logItem = ($item['value_type'] == ITEM_VALUE_TYPE_LOG);
			// is this an eventlog item? If so, we must show some additional columns
			$eventLogItem = (strpos($item['key_'], 'eventlog[') === 0);

			$table = new CTableInfo('...');
			$table->setHeader(array(
				S_TIMESTAMP,
				$fewItems ? S_ITEM : null,
				$logItem ? S_LOCAL_TIME : null,
				(($eventLogItem && $logItem) ? S_SOURCE : null),
				(($eventLogItem && $logItem) ? S_SEVERITY : null),
				(($eventLogItem && $logItem) ? S_EVENT_ID : null),
				S_VALUE
			), 'header');

			if(isset($_REQUEST['filter']) && !zbx_empty($_REQUEST['filter']) && in_array($_REQUEST['filter_task'], array(FILTER_TASK_SHOW, FILTER_TASK_HIDE))){
				$options['search'] = array('value' => $_REQUEST['filter']);

				if($_REQUEST['filter_task'] == FILTER_TASK_HIDE)
					$options['excludeSearch'] = 1;
			}

			$options['sortfield'] = 'id';
			$hData = CHistory::get($options);

			foreach($hData as $hnum => $data){
				$color_style = null;

				$item = $items[$data['itemid']];
				$host = reset($item['hosts']);

				if(isset($_REQUEST['filter']) && !zbx_empty($_REQUEST['filter'])){
					$contain = zbx_stristr($data['value'], $_REQUEST['filter']);

					if(!isset($_REQUEST['mark_color'])) $_REQUEST['mark_color'] = MARK_COLOR_RED;

					if(($contain) && ($_REQUEST['filter_task'] == FILTER_TASK_MARK))
						$color_style = $_REQUEST['mark_color'];

					if((!$contain) && ($_REQUEST['filter_task'] == FILTER_TASK_INVERT_MARK))
						$color_style = $_REQUEST['mark_color'];

					switch($color_style){
						case MARK_COLOR_RED:	$color_style='red'; break;
						case MARK_COLOR_GREEN:	$color_style='green'; break;
						case MARK_COLOR_BLUE:	$color_style='blue'; break;
					}
				}

				$row = array(nbsp(zbx_date2str(S_HISTORY_LOG_ITEM_DATE_FORMAT, $data['clock'])));

				if($fewItems)
					$row[] = $host['host'].':'.item_description($item);

				if($logItem){
					$row[] = ($data['timestamp'] == 0) ? '-' : zbx_date2str(S_HISTORY_LOG_LOCALTIME_DATE_FORMAT, $data['timestamp']);

					// if this is a eventLog item, showing additional info
					if($eventLogItem){
						$row[] = zbx_empty($data['source']) ? '-' : $data['source'];
						$row[] = ($data['severity'] == 0)
								? '-'
								: new CCol(get_item_logtype_description($data['severity']), get_item_logtype_style($data['severity']));
						$row[] = ($data['logeventid'] == 0) ? '-' : $data['logeventid'];
					}
				}

				$data['value'] = encode_log(trim($data['value'], "\r\n"));
				$row[] = new CCol($data['value'], 'pre');


				$crow = new CRow($row);
				if(is_null($color_style)){
					$min_color = 0x98;
					$max_color = 0xF8;
					$int_color = ($max_color - $min_color) / count($_REQUEST['itemid']);
					$int_color *= array_search($data['itemid'],$_REQUEST['itemid']);
					$int_color += $min_color;
					$crow->setAttribute('style','background-color: '.sprintf("#%X%X%X",$int_color,$int_color,$int_color));
				}
				else if(!is_null($color_style)){
					$crow->setClass($color_style);
				}

				$table->addRow($crow);

// Plain Text
				if(!isset($_REQUEST['plaintext'])) continue;

				$ptData['body'][] = zbx_date2str(S_HISTORY_LOG_ITEM_PLAINTEXT,$data['clock']);
				$ptData['body'][] = "\t".$data['clock']."\t".htmlspecialchars($data['value'])."\n";
			}
		}
		else{
// NUMERIC, FLOAT
			$table = new CTableInfo();
			$table->setHeader(array(S_TIMESTAMP, S_VALUE));

			$options['sortfield'] = 'clock';
			$hData = CHistory::get($options);
			foreach($hData as $hnum => $data){
				$item = $items[$data['itemid']];
				$host = reset($item['hosts']);

				if(!isset($data['value'])) $data['value'] = '';

				if($item['valuemapid'] > 0){
					$value = replace_value_by_map($data['value'], $item['valuemapid']);
					$value_mapped = true;
				}
				else{
					$value = $data['value'];
					$value_mapped = false;
				}

				if(($item['value_type'] == ITEM_VALUE_TYPE_FLOAT) && !$value_mapped)
					sscanf($data['value'], '%f', $value);

				$table->addRow(array(
					zbx_date2str(S_HISTORY_ITEM_DATE_FORMAT, $data['clock']),
					zbx_nl2br($value)
				));

// Plaintext
				if(!isset($_REQUEST['plaintext'])) continue;

				if($item['value_type'] == ITEM_VALUE_TYPE_FLOAT) sscanf($data['value'], '%f', $value);
				else $value = $data['value'];

				$ptData['body'][] = zbx_date2str(S_HISTORY_PLAINTEXT_DATE_FORMAT, $data['clock']);
				$ptData['body'][] = "\t".$data['clock']."\t".htmlspecialchars($value)."\n";
			}
		}
	}

	if(($_REQUEST['action']=='showgraph') && !isset($iv_string[$item['value_type']])){
		$dom_graph_id = 'graph';
		$containerid = 'graph_cont1';
		$src = 'chart.php?itemid='.$item['itemid'];

		$table = new CTableInfo('...','chart');
		$graph_cont = new CCol();
		$graph_cont->setAttribute('id', $containerid);

		$table->addRow($graph_cont);
	}

	if(str_in_array($_REQUEST['action'], array('showvalues', 'showgraph'))){
		$graphDims = getGraphDims();


// NAV BAR
		$utime = zbxDateToTime($_REQUEST['stime']);
		$starttime = get_min_itemclock_by_itemid($item['itemid']);
		if($utime < $starttime) $starttime = $utime;

		$timeline = array(
			'starttime' => date('YmdHis', $starttime),
			'period' => $period,
			'usertime' => date('YmdHis', $utime + $period)
		);

		$objData = array(
			'periodFixed' => CProfile::get('web.history.timelinefixed', 1)
		);

		if(isset($dom_graph_id)){
			$objData['id'] = $_REQUEST['itemid'];
			$objData['domid'] = $dom_graph_id;
			$objData['containerid'] = $containerid;
			$objData['src'] = $src;
			$objData['objDims'] = $graphDims;
			$objData['loadSBox'] = 1;
			$objData['loadImage'] = 1;
			$objData['loadScroll'] = 1;
			$objData['scrollWidthByImage'] = 1;
			$objData['dynamic'] = 1;
		}
		else{
			$dom_graph_id = 'graph';

			$objData['id'] = $_REQUEST['itemid'];
			$objData['domid'] = $dom_graph_id;
			$objData['loadSBox'] = 0;
			$objData['loadImage'] = 0;
			$objData['loadScroll'] = 1;
			$objData['dynamic'] = 0;
			$objData['mainObject'] = 1;
		}

//-------------
	}

	if(!isset($_REQUEST['plaintext'])){
		$right = new CTable();
		$right->addRow($header['right']);

		$historyWidget->addPageHeader($header['left'], $right);

		if(isset($iv_string[$item['value_type']])){
			$historyWidget->addFlicker($filterForm, CProfile::get('web.history.filter.state',1));
		}

		$historyWidget->addItem($table);

		if(str_in_array($_REQUEST['action'], array('showvalues', 'showgraph'))){
			zbx_add_post_js('timeControl.addObject("'.$dom_graph_id.'",'.zbx_jsvalue($timeline).','.zbx_jsvalue($objData).');');
			zbx_add_post_js('timeControl.processObjects();');

			$scroll_div = new CDiv();
			$scroll_div->setAttribute('id','scrollbar_cntr');
			$historyWidget->addFlicker($scroll_div, CProfile::get('web.history.filter.state',1));
		}

		$historyWidget->show();
	}
	else{
		$span = new CSpan(null, 'textblackwhite');
		foreach($ptData['header'] as $bnum => $text){
			$span->addItem(array(new CJSscript($text), BR()));
		}

		$pre = new CTag('pre', true);
		foreach($ptData['body'] as $bnum => $text){
			$pre->addItem(new CJSscript($text));
		}
		$span->addItem($pre);

		$span->show();
	}
?>
<script type="text/javascript">
function addPopupValues(list){
	if(!isset('object', list)){
		throw("Error hash attribute 'list' doesn't contain 'object' index");
		return false;
	}

	var favorites = {'itemid': 1};
	if(isset(list.object, favorites)){
		for(var i=0; i < list.values.length; i++){
			if(!isset(i, list.values) || empty(list.values[i])) continue;

			create_var('zbx_filter', 'itemid['+list.values[i]+']', list.values[i], false);
		}

		$('zbx_filter').submit();
	}
}
//]]> -->
</script>
<?php

require_once('include/page_footer.php');

?>
