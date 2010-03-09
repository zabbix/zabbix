<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
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

$page['title'] = "S_AUDIT";
$page['file'] = 'auditacts.php';
$page['hist_arg'] = array();
$page['scripts'] = array('class.calendar.js','scriptaculous.js?load=effects');

$page['type'] = detect_page_type(PAGE_TYPE_HTML);

$_REQUEST['config'] = get_request('config','auditacts.php');

include_once('include/page_header.php');
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'config'=>			array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
// filter
		'filter_rst'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN(array(0,1)),	NULL),
		'filter_set'=>		array(T_ZBX_STR, O_OPT,	P_SYS,	null,	NULL),
		'alias'=>			array(T_ZBX_STR, O_OPT,	P_SYS,	null,	NULL),
		'nav_time'=>		array(T_ZBX_INT, O_OPT,	P_UNSET_EMPTY,	null,	NULL),
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favid'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj}) && ("filter"=={favobj})'),
	);

	check_fields($fields);
	validate_sort_and_sortorder('clock',ZBX_SORT_DOWN);
?>
<?php

/* AJAX */
	if(isset($_REQUEST['favobj'])){
		if('filter' == $_REQUEST['favobj']){
			CProfile::update('web.auditacts.filter.state',$_REQUEST['state'], PROFILE_TYPE_INT);
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
		$_REQUEST['nav_time'] = time();
	}

	$_REQUEST['alias'] = get_request('alias',CProfile::get('web.auditacts.filter.alias', ''));
	$_REQUEST['nav_time'] = get_request('nav_time',CProfile::get('web.auditacts.filter.nav_time',time()));

	if(isset($_REQUEST['filter_set']) || isset($_REQUEST['filter_rst'])){
		CProfile::update('web.auditacts.filter.alias',$_REQUEST['alias'], PROFILE_TYPE_STR);
		CProfile::update('web.auditacts.filter.nav_time',$_REQUEST['nav_time'], PROFILE_TYPE_INT);
	}
	$nav_time = $_REQUEST['nav_time'];
// -------------

?>
<?php
	$alerts_wdgt = new CWidget();

// HEADER
	$frmForm = new CForm();
	$frmForm->setMethod('get');

	$cmbConf = new CComboBox('config','auditacts.php');
	$cmbConf->setAttribute('onchange','javascript: redirect(this.options[this.selectedIndex].value);');
		$cmbConf->addItem('auditlogs.php',S_LOGS);
		$cmbConf->addItem('auditacts.php',S_ACTIONS);

	$frmForm->addItem($cmbConf);

	$alerts_wdgt->addPageHeader(S_AUDIT_ACTIONS_BIG,$frmForm);

	$numrows = new CDiv();
	$numrows->setAttribute('name', 'numrows');

	$alerts_wdgt->addHeader(S_ALERTS_BIG);
	$alerts_wdgt->addHeader($numrows);
//--------

/************************* FILTER **************************/
/***********************************************************/

	$filterForm = new CFormTable();
	$filterForm->setAttribute('name','zbx_filter');
	$filterForm->setAttribute('id','zbx_filter');

	$script = new CJSscript("javascript: if(CLNDR['audit_since'].clndr.setSDateFromOuterObj()){".
							"$('nav_time').value = parseInt(CLNDR['audit_since'].clndr.sdt.getTime()/1000);}");
	$filterForm->addAction('onsubmit',$script);

	$filterForm->addVar('nav_time',($_REQUEST['nav_time']>0)?$_REQUEST['nav_time']:'');

	$row = new CRow(array(
					new CCol(S_RECIPIENT,'form_row_l'),
					new CCol(array(
								new CTextBox("alias",$_REQUEST['alias'],32),
								new CButton("btn1",S_SELECT,"return PopUp('popup.php?"."dstfrm=".$filterForm->getName()."&dstfld1=alias&srctbl=users&srcfld1=alias&real_hosts=1');",'T')
							),'form_row_r')
						));

	$filterForm->addRow($row);

	$clndr_icon = new CImg('images/general/bar/cal.gif','calendar', 16, 12, 'pointer');
	$clndr_icon->addAction('onclick',"javascript: var pos = getPosition(this); pos.top+=10; pos.left+=16; CLNDR['audit_since'].clndr.clndrshow(pos.top,pos.left);");
	$clndr_icon->setAttribute('style','vertical-align: middle;');

	$nav_clndr =  array(
						new CNumericBox('nav_day',(($_REQUEST['nav_time']>0)?date('d',$_REQUEST['nav_time']):''),2),
						new CNumericBox('nav_month',(($_REQUEST['nav_time']>0)?date('m',$_REQUEST['nav_time']):''),2),
						new CNumericBox('nav_year',(($_REQUEST['nav_time']>0)?date('Y',$_REQUEST['nav_time']):''),4),
						new CNumericBox('nav_hour',(($_REQUEST['nav_time']>0)?date('H',$_REQUEST['nav_time']):''),2),
						':',
						new CNumericBox('nav_minute',(($_REQUEST['nav_time']>0)?date('i',$_REQUEST['nav_time']):''),2),
					$clndr_icon
				);

	$filterForm->addRow(S_ACTIONS_SINCE,$nav_clndr);

	zbx_add_post_js('create_calendar(null,'.
				'["nav_day","nav_month","nav_year","nav_hour","nav_minute"],'.
				'"audit_since");');

	zbx_add_post_js('addListener($("filter_icon"),'.
				'"click",CLNDR[\'audit_since\'].clndr.clndrhide.bindAsEventListener(CLNDR[\'audit_since\'].clndr));');

//*/
	$reset = new CButton('filter_rst', S_RESET);
	$reset->setType('button');
	$reset->setAction('javascript: var uri = new Curl(location.href); uri.setArgument("filter_rst",1); location.href = uri.getUrl();');

	$filterForm->addItemToBottomRow(new CButton("filter_set", S_FILTER));
	$filterForm->addItemToBottomRow($reset);

	$alerts_wdgt->addFlicker($filterForm, CProfile::get('web.auditacts.filter.state',1));
//-------

	$options = array(
		'time_from' => $nav_time,
		'extendoutput' => 1,
		'select_mediatypes' => 1,
		'sortfield' => getPageSortField('alertid'),
		'sortorder' => getPageSortOrder(),
		'limit' => ($config['search_limit']+1)
	);


	if($_REQUEST['alias']){
		$user = CUser::getObjects(array('alias' => $_REQUEST['alias']));
		$user = reset($user);
		$options['userids'] = $user['userid'];
	}
	$alerts = CAlert::get($options);

	$table = new CTableInfo(S_NO_ACTIONS_FOUND);
	$table->setHeader(array(
			is_show_all_nodes()?S_NODES:null,
			make_sorting_header(S_TIME,'alertid'),
			S_TYPE,
			S_STATUS,
			S_RETRIES_LEFT,
			S_RECIPIENTS,
			S_MESSAGE,
			S_ERROR
			));


// sorting && paging
	order_page_result($alerts, 'alertid');
	$paging = getPagingLine($alerts);
//---------

	foreach($alerts as $num => $row){
// users
//		$user = array_pop($row['users']);
// mediatypes

		$mediatype = array_pop($row['mediatypes']);

		if($mediatype['mediatypeid'] == 0) $mediatype = array('description' => '');

		$time = date(S_DATE_FORMAT_YMDHMS,$row['clock']);

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
		$sendto=$row['sendto'];

		if ($row['alerttype'] == ALERT_TYPE_MESSAGE)
			$message = array(bold(S_SUBJECT.': '), br(), $row['subject'], br(), br(), bold(S_MESSAGE.': '), br(), $row['message']);
		else
			$message = array(bold(S_COMMAND.': '), br(), $row['message']);

		if(empty($row['error'])){
			$error=new CSpan(SPACE,'off');
		}
		else{
			$error=new CSpan($row['error'],'on');
		}

		$table->addRow(array(
			get_node_name_by_elid($row['alertid']),
			new CCol($time, 'top'),
			new CCol($mediatype['description'], 'top'),
			new CCol($status, 'top'),
			new CCol($retries, 'top'),
			new CCol($sendto, 'top'),
			new CCol($message, 'wraptext top'),
			new CCol($error, 'wraptext top')));
	}

// PAGING FOOTER
	$table = array($paging, $table, $paging);
//---------

	$alerts_wdgt->addItem($table);
	$alerts_wdgt->show();

?>
<?php

include_once('include/page_footer.php');
?>
