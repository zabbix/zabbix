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
	$page['file'] = 'auditlogs.php';
	$page['hist_arg'] = array();
	$page['scripts'] = array('class.calendar.js','gtlc.js');

	$page['type'] = detect_page_type(PAGE_TYPE_HTML);

include_once('include/page_header.php');
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
// actions
		'groupid'=>			array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,	NULL),
		'hostid'=>			array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,	NULL),
// filter
		'action'=>			array(T_ZBX_INT, O_OPT,	P_SYS,	BETWEEN(-1,6),	NULL),
		'resourcetype'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	BETWEEN(-1,30),	NULL),
		'filter_rst'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN(array(0,1)),	NULL),
		'filter_set'=>		array(T_ZBX_STR, O_OPT,	P_SYS,	null,	NULL),
		'alias' =>			array(T_ZBX_STR, O_OPT,	P_SYS,	null,	NULL),

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
			CProfile::update('web.auditlogs.filter.state',$_REQUEST['state'], PROFILE_TYPE_INT);
		}
		// saving fixed/dynamic setting to profile
		if('timelinefixedperiod' == $_REQUEST['favobj']){
			if(isset($_REQUEST['favid'])){
				CProfile::update('web.auditlogs.timelinefixed', $_REQUEST['favid'], PROFILE_TYPE_INT);
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
		$_REQUEST['action'] = -1;
		$_REQUEST['resourcetype'] = -1;
	}

	$_REQUEST['alias'] = get_request('alias', CProfile::get('web.auditlogs.filter.alias', ''));
	$_REQUEST['action'] = get_request('action', CProfile::get('web.auditlogs.filter.action',-1));
	$_REQUEST['resourcetype'] = get_request('resourcetype', CProfile::get('web.auditlogs.filter.resourcetype',-1));

	if(isset($_REQUEST['filter_set']) || isset($_REQUEST['filter_rst'])){
		CProfile::update('web.auditlogs.filter.alias', $_REQUEST['alias'], PROFILE_TYPE_STR);
		CProfile::update('web.auditlogs.filter.action', $_REQUEST['action'], PROFILE_TYPE_INT);
		CProfile::update('web.auditlogs.filter.resourcetype', $_REQUEST['resourcetype'], PROFILE_TYPE_INT);
	}
// --------------
?>
<?php

	$audit_wdgt = new CWidget();

// HEADER
	$frmForm = new CForm();
	$frmForm->setMethod('get');

	$cmbConf = new CComboBox('config', 'auditlogs.php');
	$cmbConf->setAttribute('onchange', 'javascript: redirect(this.options[this.selectedIndex].value);');
	$cmbConf->addItem('auditlogs.php', S_LOGS);
	$cmbConf->addItem('auditacts.php', S_ACTIONS);
	$frmForm->addItem($cmbConf);

	$audit_wdgt->addPageHeader(S_AUDIT_LOGS_BIG,$frmForm);

	$numrows = new CDiv();
	$numrows->setAttribute('name', 'numrows');

	$audit_wdgt->addHeader(S_LOGS_BIG);
	$audit_wdgt->addHeader($numrows);
//--------

/************************* FILTER **************************/
/***********************************************************/

	$filterForm = new CFormTable();
	$filterForm->setAttribute('name', 'zbx_filter');
	$filterForm->setAttribute('id', 'zbx_filter');

	$row = new CRow(array(
		new CCol(S_USER,'form_row_l'),
		new CCol(array(
			new CTextBox('alias', $_REQUEST['alias'], 32),
			new CButton('btn1', S_SELECT,"return PopUp('popup.php?"."dstfrm=".$filterForm->GetName()."&dstfld1=alias&srctbl=users&srcfld1=alias&real_hosts=1');",'T')
		),'form_row_r')
	));

	$filterForm->addRow($row);

	$cmbAction = new CComboBox('action',$_REQUEST['action']);
		$cmbAction->addItem(-1,S_ALL_S);
		$cmbAction->addItem(AUDIT_ACTION_LOGIN,		S_LOGIN);
		$cmbAction->addItem(AUDIT_ACTION_LOGOUT,	S_LOGOUT);
		$cmbAction->addItem(AUDIT_ACTION_ADD,		S_ADD);
		$cmbAction->addItem(AUDIT_ACTION_UPDATE,	S_UPDATE);
		$cmbAction->addItem(AUDIT_ACTION_DELETE,	S_DELETE);
		$cmbAction->addItem(AUDIT_ACTION_ENABLE,	S_ENABLE);
		$cmbAction->addItem(AUDIT_ACTION_DISABLE,	S_DISABLE);

	$filterForm->addRow(S_ACTION, $cmbAction);

	$cmbResource = new CComboBox('resourcetype',$_REQUEST['resourcetype']);
	$resources = array(-1 => S_ALL_S) + audit_resource2str();
	$cmbResource->addItems($resources);

	$filterForm->addRow(S_RESOURCE, $cmbResource);


	$reset = new CButton('filter_rst',S_RESET);
	$reset->setType('button');
	$reset->setAction('javascript: var uri = new Curl(location.href); uri.setArgument("filter_rst",1); location.href = uri.getUrl();');

	$filterForm->addItemToBottomRow(new CButton('filter_set',S_FILTER));
	$filterForm->addItemToBottomRow($reset);

	$audit_wdgt->addFlicker($filterForm, CProfile::get('web.auditlogs.filter.state',1));

	$scroll_div = new CDiv();
	$scroll_div->setAttribute('id','scrollbar_cntr');
	$audit_wdgt->addFlicker($scroll_div, CProfile::get('web.auditlogs.filter.state',1));
//-------

	$effectiveperiod = navigation_bar_calc('web.auditlogs.timeline', 0, true);
	$bstime = $_REQUEST['stime'];
	$from = zbxDateToTime($_REQUEST['stime']);
	$till = $from + $effectiveperiod;


	$sql_cond = array();
	if($_REQUEST['alias'])
		$sql_cond['alias'] = ' AND u.alias='.zbx_dbstr($_REQUEST['alias']);

	if(($_REQUEST['action']>-1))
		$sql_cond['action'] = ' AND a.action='.$_REQUEST['action'].' ';

	if(($_REQUEST['resourcetype']>-1))
		$sql_cond['resourcetype'] = ' AND a.resourcetype='.$_REQUEST['resourcetype'].' ';

	$sql_cond['from'] = ' AND a.clock>'.$from;
	$sql_cond['till'] = ' AND a.clock<'.$till;

	$actions = array();

	$table = new CTableInfo();
	$table->setHeader(array(
		S_TIME,
		S_USER,
		S_IP,
		S_RESOURCE,
		S_ACTION,
		S_ID,
		S_DESCRIPTION,
		S_DETAILS
	));

	$sql = 'SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details '.
			' FROM auditlog a, users u '.
			' WHERE u.userid=a.userid '.
				implode('', $sql_cond).
				' AND '.DBin_node('u.userid', get_current_nodeid(null, PERM_READ_ONLY)).
			' ORDER BY a.clock DESC';
	$result = DBselect($sql, ($config['search_limit']+1));

// get first log for timeline starttime
	unset($sql_cond['from']);
	unset($sql_cond['till']);

	$sql = 'SELECT a.auditid, a.clock'.
			' FROM auditlog a, users u '.
			' WHERE u.userid=a.userid '.
				implode('', $sql_cond).
				' AND '.DBin_node('u.userid', get_current_nodeid(null, PERM_READ_ONLY)).
			' ORDER BY a.clock ASC';
	$firstAudit = DBfetch(DBselect($sql, ($config['search_limit']+1)));
	$starttime = $firstAudit['clock'];

	while($row = DBfetch($result)){
		switch($row['action']){
			case AUDIT_ACTION_ADD:		$action = S_ADDED; break;
			case AUDIT_ACTION_UPDATE:	$action = S_UPDATED; break;
			case AUDIT_ACTION_DELETE:	$action = S_DELETED; break;
			case AUDIT_ACTION_LOGIN:	$action = S_LOGIN;	break;
			case AUDIT_ACTION_LOGOUT:	$action = S_LOGOUT; break;
			case AUDIT_ACTION_ENABLE:	$action = S_ENABLED; break;
			case AUDIT_ACTION_DISABLE:	$action = S_DISABLED; break;
			default: $action = S_UNKNOWN_ACTION;
		}

		$row['action'] = $action;
		$row['resourcetype'] = audit_resource2str($row['resourcetype']);

		$actions[$row['auditid']] = $row;
	}

// sorting && paging
	order_result($actions, 'clock', ZBX_SORT_DOWN);
	$paging = getPagingLine($actions);
//---------

	foreach($actions as $num => $row){
		if(empty($row['details'])){
			$details = array();
			$sql = 'SELECT table_name,field_name,oldvalue,newvalue '.
					' FROM auditlog_details '.
					' WHERE auditid='.$row['auditid'];
			$db_details = DBselect($sql);
			while($db_detail = DBfetch($db_details)){
				$details[] = array(
					$db_detail['table_name'].'.'.$db_detail['field_name'].': '.	$db_detail['oldvalue'].' => '.$db_detail['newvalue'],
					BR()
				);
			}
		}
		else{
			$details = $row['details'];
		}

		$table->addRow(array(
			zbx_date2str(S_AUDITLOGS_RECORD_DATE_FORMAT,$row['clock']),
			$row['alias'],
			$row['ip'],
			$row['resourcetype'],
			$row['action'],
			$row['resourceid'],
			$row['resourcename'],
			new CCol($details, 'wraptext')
		));
	}

// PAGING FOOTER
	$table = array($paging, $table, $paging);
//---------

	$audit_wdgt->addItem($table);
	$audit_wdgt->show();

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
		'periodFixed' => CProfile::get('web.auditlogs.timelinefixed', 1)
	);

	zbx_add_post_js('timeControl.addObject("'.$dom_graph_id.'",'.zbx_jsvalue($timeline).','.zbx_jsvalue($objData).');');
	zbx_add_post_js('timeControl.processObjects();');


include_once('include/page_footer.php');
?>
