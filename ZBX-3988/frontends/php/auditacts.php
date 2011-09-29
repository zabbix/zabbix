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
require_once('include/audit.inc.php');
require_once('include/actions.inc.php');
require_once('include/users.inc.php');

$page['title'] = 'S_AUDIT';
$page['file'] = 'auditacts.php';
$page['hist_arg'] = array();
$page['scripts'] = array('class.calendar.js','gtlc.js');

$page['type'] = detect_page_type(PAGE_TYPE_HTML);

include_once('include/page_header.php');
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
// filter
		'filter_rst'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN(array(0,1)),	NULL),
		'filter_set'=>		array(T_ZBX_STR, O_OPT,	P_SYS,	null,	NULL),
		'alias'=>			array(T_ZBX_STR, O_OPT,	P_SYS,	null,	NULL),

		'period'=>	array(T_ZBX_INT, O_OPT,	 null,	null, null),
		'dec'=>		array(T_ZBX_INT, O_OPT,	 null,	null, null),
		'inc'=>		array(T_ZBX_INT, O_OPT,	 null,	null, null),
		'left'=>	array(T_ZBX_INT, O_OPT,	 null,	null, null),
		'right'=>	array(T_ZBX_INT, O_OPT,	 null,	null, null),
		'stime'=>	array(T_ZBX_STR, O_OPT,	 null,	null, null),
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favref'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj}) && ("filter"=={favobj})'),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj}) && ("filter"=={favobj})'),
		'favid'=>		array(T_ZBX_INT, O_OPT, P_ACT,  null,			null),
	);

	check_fields($fields);
?>
<?php
/* AJAX */
	if(isset($_REQUEST['favobj'])){
		if('filter' == $_REQUEST['favobj']){
			CProfile::update('web.auditacts.filter.state',$_REQUEST['state'], PROFILE_TYPE_INT);
		}
		// saving fixed/dynamic setting to profile
		if('timelinefixedperiod' == $_REQUEST['favobj']){
			if(isset($_REQUEST['favid'])){
				CProfile::update('web.auditacts.timelinefixed', $_REQUEST['favid'], PROFILE_TYPE_INT);
			}
		}
	}

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		include_once('include/page_footer.php');
		exit();
	}
//--------

/* FILTER */
	if(isset($_REQUEST['filter_rst'])){
		$_REQUEST['alias'] = '';
	}

	$_REQUEST['alias'] = get_request('alias',CProfile::get('web.auditacts.filter.alias', ''));

	if(isset($_REQUEST['filter_set']) || isset($_REQUEST['filter_rst'])){
		CProfile::update('web.auditacts.filter.alias',$_REQUEST['alias'], PROFILE_TYPE_STR);
	}
// -------------

?>
<?php
	$alerts_wdgt = new CWidget();

// HEADER
	$frmForm = new CForm(null, 'get');

	$cmbConf = new CComboBox('config','auditacts.php');
	$cmbConf->setAttribute('onchange','javascript: redirect(this.options[this.selectedIndex].value);');
		$cmbConf->addItem('auditlogs.php',S_LOGS);
		$cmbConf->addItem('auditacts.php',S_ACTIONS);

	$frmForm->addItem($cmbConf);

	$alerts_wdgt->addPageHeader(S_AUDIT_ACTIONS_BIG, $frmForm);

	$numrows = new CDiv();
	$numrows->setAttribute('name', 'numrows');

	$alerts_wdgt->addHeader(S_ACTIONS_BIG);
	$alerts_wdgt->addHeader($numrows);
//--------

/************************* FILTER **************************/
/***********************************************************/

	$filterForm = new CFormTable();
	$filterForm->setAttribute('name','zbx_filter');
	$filterForm->setAttribute('id','zbx_filter');

	$row = new CRow(array(
		new CCol(S_RECIPIENT,'form_row_l'),
		new CCol(array(
			new CTextBox('alias',$_REQUEST['alias'],32),
			new CButton('btn1',S_SELECT,"return PopUp('popup.php?"."dstfrm=".$filterForm->getName()."&dstfld1=alias&srctbl=users&srcfld1=alias&real_hosts=1');",'T')
		),'form_row_r')
	));

	$filterForm->addRow($row);

	$reset = new CButton('filter_rst', S_RESET);
	$reset->setType('button');
	$reset->setAction('javascript: var uri = new Curl(location.href); uri.setArgument("filter_rst",1); location.href = uri.getUrl();');

	$filterForm->addItemToBottomRow(new CButton("filter_set", S_FILTER));
	$filterForm->addItemToBottomRow($reset);

	$alerts_wdgt->addFlicker($filterForm, CProfile::get('web.auditacts.filter.state',1));

	$scroll_div = new CDiv();
	$scroll_div->setAttribute('id','scrollbar_cntr');
	$alerts_wdgt->addFlicker($scroll_div, CProfile::get('web.auditacts.filter.state',1));
//-------

	$table = new CTableInfo(S_NO_ACTIONS_FOUND);
	$table->setHeader(array(
		is_show_all_nodes()?S_NODES:null,
		S_TIME,
		S_TYPE,
		S_STATUS,
		S_RETRIES_LEFT,
		S_RECIPIENTS,
		S_MESSAGE,
		S_ERROR
	));

	$effectiveperiod = navigation_bar_calc('web.auditacts.timeline',0, true);
	$bstime = $_REQUEST['stime'];
	$from = zbxDateToTime($_REQUEST['stime']);
	$till = $from + $effectiveperiod;

	$options = array(
		'time_from' => $from,
		'time_till' => $till,
		'output' => API_OUTPUT_EXTEND,
		'select_mediatypes' => API_OUTPUT_EXTEND,
		'sortfield' => 'alertid',
		'sortorder' => ZBX_SORT_DOWN,
		'limit' => ($config['search_limit']+1)
	);

	if($_REQUEST['alias']){
		$users = CUser::get(array('filter' => array('alias' => $_REQUEST['alias'])));
		$options['userids'] = zbx_objectValues($users, 'userid');
	}

	$alerts = CAlert::get($options);

// get first event for selected filters, to get starttime for timeline bar
	unset($options['userids']);
	unset($options['time_from']);
	unset($options['time_till']);
	unset($options['select_mediatypes']);
	$options['limit'] = 1;
	$options['sortorder'] = ZBX_SORT_UP;
	$firstAlert = CAlert::get($options);
	$firstAlert = reset($firstAlert);
	$starttime = $firstAlert ? $firstAlert['clock'] : time()-3600;


	$paging = getPagingLine($alerts);

	foreach($alerts as $num => $row){
		$mediatype = array_pop($row['mediatypes']);

		if($mediatype['mediatypeid'] == 0) $mediatype = array('description' => '');

		if($row['status'] == ALERT_STATUS_SENT){
			if ($row['alerttype'] == ALERT_TYPE_MESSAGE)
				$status=new CSpan(S_SENT,'green');
			else
				$status=new CSpan(S_EXECUTED,'green');
			$retries=new CSpan(SPACE,'green');
		}
		else if($row['status'] == ALERT_STATUS_NOT_SENT){
			$status=new CSpan(S_IN_PROGRESS,'orange');
			$retries=new CSpan(ALERT_MAX_RETRIES - $row['retries'],'orange');
		}
		else{
			$status=new CSpan(S_NOT_SENT,'red');
			$retries=new CSpan(0,'red');
		}

		if($row['alerttype'] == ALERT_TYPE_MESSAGE)
			$message = array(bold(S_SUBJECT.': '), br(), $row['subject'], br(), br(), bold(S_MESSAGE.': '), br(), $row['message']);
		else
			$message = array(bold(S_COMMAND.': '), br(), $row['message']);

		$error = empty($row['error']) ? new CSpan(SPACE,'off') : new CSpan($row['error'],'on');

		$table->addRow(array(
			get_node_name_by_elid($row['alertid']),
			new CCol(zbx_date2str(S_AUDITACTS_DESCRIPTION_DATE_FORMAT,$row['clock']), 'top'),
			new CCol($mediatype['description'], 'top'),
			new CCol($status, 'top'),
			new CCol($retries, 'top'),
			new CCol($row['sendto'], 'top'),
			new CCol($message, 'wraptext top'),
			new CCol($error, 'wraptext top')));
	}

// PAGING FOOTER
	$table = array($paging, $table, $paging);
//---------

	$alerts_wdgt->addItem($table);
	$alerts_wdgt->show();

// NAV BAR
	$timeline = array(
		'period' => $effectiveperiod,
		'starttime' => date('YmdHis', $starttime),
		'usertime' => null
	);

	if(isset($_REQUEST['stime'])){
		$timeline['usertime'] = date('YmdHis', zbxDateToTime($_REQUEST['stime']) + $timeline['period']);
	}

	$dom_graph_id = 'events';
	$objData = array(
		'id' => 'timeline_1',
		'domid' => $dom_graph_id,
		'loadSBox' => 0,
		'loadImage' => 0,
		'loadScroll' => 1,
		'dynamic' => 0,
		'mainObject' => 1,
		'periodFixed' => CProfile::get('web.auditacts.timelinefixed', 1)
	);

	zbx_add_post_js('timeControl.addObject("'.$dom_graph_id.'",'.zbx_jsvalue($timeline).','.zbx_jsvalue($objData).');');
	zbx_add_post_js('timeControl.processObjects();');


include_once('include/page_footer.php');
?>
