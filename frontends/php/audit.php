<?php
/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
	$page['file'] = 'audit.php';
	$page['hist_arg'] = array('prev','next');
	$page['scripts'] = array('calendar.js','scriptaculous.js?load=effects');

	$page['type'] = detect_page_type(PAGE_TYPE_HTML);

	if(PAGE_TYPE_HTML == $page['type']){
		define('ZBX_PAGE_DO_REFRESH', 1);
	}

	$_REQUEST['config'] = get_request('config',get_profile('web.audit.config',0));

include_once('include/page_header.php');
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'config'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN('0,1'),		NULL),

// actions
		'groupid'=>		array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,	NULL),
		'hostid'=>		array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,	NULL),

		'next_page'=>		array(T_ZBX_STR, O_OPT,	P_SYS,	NULL,			NULL),
		'prev_page'=>		array(T_ZBX_STR, O_OPT,	P_SYS,	NULL,			NULL),

		'prev_clock'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	null,	NULL),
		'curr_clock'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	null,	NULL),
		'next_clock'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	null,	NULL),

// filter
		'action'=>			array(T_ZBX_INT, O_OPT,	P_SYS,	BETWEEN(-1,6),	NULL),
		'resourcetype'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	BETWEEN(-1,28),	NULL),
		'filter_rst'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN(array(0,1)),	NULL),
		'filter_set'=>		array(T_ZBX_STR, O_OPT,	P_SYS,	null,	NULL),

		'userid'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,	NULL),

		'nav_time'=>	array(T_ZBX_INT, O_OPT,	P_UNSET_EMPTY,	null,	NULL),

//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favid'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj}) && ("filter"=={favobj})'),
	);

	check_fields($fields);
	validate_sort_and_sortorder('a.clock',ZBX_SORT_DOWN);

/* AJAX */
	if(isset($_REQUEST['favobj'])){
		if('filter' == $_REQUEST['favobj']){
			update_profile('web.audit.filter.state',$_REQUEST['state'], PROFILE_TYPE_INT);
		}
	}

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		exit();
	}
//--------

/* FILTER */
	if(isset($_REQUEST['filter_rst'])){
		$_REQUEST['userid'] = 0;
		$_REQUEST['action'] = -1;
		$_REQUEST['resourcetype'] = -1;

		$_REQUEST['nav_time'] = time();
	}

	$_REQUEST['userid'] = get_request('userid',get_profile('web.audit.filter.userid',0));
	$_REQUEST['action'] = get_request('action',get_profile('web.audit.filter.action',-1));
	$_REQUEST['resourcetype'] = get_request('resourcetype',get_profile('web.audit.filter.resourcetype',-1));

	$_REQUEST['nav_time'] = get_request('nav_time',get_profile('web.audit.filter.nav_time',time()));

	if(isset($_REQUEST['filter_set']) || isset($_REQUEST['filter_rst'])){
		update_profile('web.audit.filter.userid',$_REQUEST['userid']);
		update_profile('web.audit.filter.action',$_REQUEST['action'], PROFILE_TYPE_INT);
		update_profile('web.audit.filter.resourcetype',$_REQUEST['resourcetype'], PROFILE_TYPE_INT);

		update_profile('web.audit.filter.nav_time',$_REQUEST['nav_time'], PROFILE_TYPE_INT);
	}
// --------------

	$config = $_REQUEST['config'];
	update_profile('web.audit.config',$_REQUEST['config'], PROFILE_TYPE_INT);


// Navigation initialization
	$nav_time = $_REQUEST['nav_time'];

	$time_end = null;

	$prev_clock = get_request('prev_clock', array());
	$next_clock = get_request('next_clock', null);
	$curr_clock = get_request('curr_clock', $nav_time);

	$prev_page = get_request('prev_page', false);
	$next_page = get_request('next_page', false);

//SDI(array($prev_page, $next_page, $nav_time));
//SDI(array($prev_clock, $curr_clock, $next_clock));
	if($next_page){
		$prev_clock[] = $curr_clock;
		$time_end = $next_clock;
	}
	else if($prev_page){
		$next_clock = $curr_clock;
		$time_end = array_pop($prev_clock);
	}
	else if($nav_time){
		$prev_clock = array();
		$time_end = $nav_time;
	}
	else{
		$time_end  = $curr_clock;
	}

	$curr_clock = $time_end;
	$limit = $USER_DETAILS['rows_per_page'];

//SDI(array($prev_clock, $curr_clock, $next_clock, $time_end));
// end of navigation initialization
// -------------
?>
<?php

	$audit_wdgt = new CWidget();

// HEADER
	$header =($config == 0)?S_AUDIT_LOGS:S_AUDIT_ACTIONS;

	$frmForm = new CForm();
	$frmForm->setMethod('get');

	$cmbConf = new CComboBox('config',$_REQUEST['config'],'submit()');
	$cmbConf->addItem(0,S_AUDIT_LOGS);
	$cmbConf->addItem(1,S_AUDIT_ACTIONS);

	$frmForm->addItem($cmbConf);

	$audit_wdgt->addHeader($header, $frmForm);
//--------
	$sql_cond = '';
	if($_REQUEST['userid'])
		$sql_cond.=' AND a.userid='.$_REQUEST['userid'].' ';

	if(($_REQUEST['action']>-1) && ($config == 0))
		$sql_cond.=' AND a.action='.$_REQUEST['action'].' ';

	if(($_REQUEST['resourcetype']>-1) && ($config == 0))
		$sql_cond.=' AND a.resourcetype='.$_REQUEST['resourcetype'].' ';

	$sql_cond.=' AND a.clock>1000000000 AND a.clock<'.$time_end;

	if(0 == $config){
		$count = 0;
		$last_clock = null;

		$actions = array();
		$clock = array();

		$table = new CTableInfo();
		$table->setHeader(array(
				make_sorting_link(S_TIME,'clock'),
				make_sorting_link(S_USER,'alias'),
				make_sorting_link(S_IP,'ip'),
				make_sorting_link(S_RESOURCE,'resourcetype'),
				make_sorting_link(S_ACTION,'action'),
				S_ID,
				S_DESCRIPTION,
				S_DETAILS));

		$sql = 'SELECT a.auditid,a.clock,u.alias,a.ip,a.resourcetype,a.action,a.resourceid,a.resourcename,a.details '.
						' FROM auditlog a, users u '.
						' WHERE u.userid=a.userid '.
							$sql_cond.
							' AND '.DBin_node('u.userid', get_current_nodeid(null, PERM_READ_ONLY)).
						' ORDER BY a.clock DESC';
		$result = DBselect($sql, $limit);
		while($row=DBfetch($result)){
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

			$count++;
			$clock[] = $row['clock'];
			$actions[] = $row;
		}

		$last_clock = !empty($clock)?min($clock):null;
		order_page_result($actions, 'clock', ZBX_SORT_DOWN);

		foreach($actions as $num => $row){
			if(empty($row['details'])){
				$details = array();
				$sql = 'SELECT table_name,field_name,oldvalue,newvalue '.
						' FROM auditlog_details '.
						' WHERE auditid='.$row['auditid'];
				$db_details = DBselect($sql);
				while($db_detail = DBfetch($db_details)){
					$details[] = array($db_detail['table_name'].'.'.
										$db_detail['field_name'].': '.
										$db_detail['oldvalue'].' => '.
										$db_detail['newvalue'],BR());
				}
			}
			else{
				$details = $row['details'];
			}

			$table->addRow(array(
				date('Y.M.d H:i:s',$row['clock']),
				$row['alias'],
				$row['ip'],
				$row['resourcetype'],
				$row['action'],
				$row['resourceid'],
				$row['resourcename'],
				new CCol($details, 'wraptext')
			));
		}
	}
	else if(1 == $config){
		$table = get_history_of_actions($limit, $last_clock, $sql_cond);
		$count = $table->getNumRows();
	}

// Navigation
	$next_clock = $last_clock;

	$navForm = new CForm('audit.php');
	$navForm->setMethod('get');

	$navForm->addVar('config',$_REQUEST['config']);

	$navForm->addVar('prev_clock',$prev_clock);
	$navForm->addVar('curr_clock',$curr_clock);
	$navForm->addVar('next_clock',$next_clock);

/*
	$prev_page = new CDiv(SPACE,'arrowleft');
	$prev_page->setAttribute('title','Previous page');
	$prev_page->addAction('onclick',new CScript("javascript: alert('left');"));

	$next_page = new CDiv(SPACE,'arrowright');
	$next_page->setAttribute('title','Next page');
	$next_page->addAction('onclick',new CScript("javascript: alert('right');"));
//*/
//*
	$prev_page = new CButton('prev_page','« '.S_BACK);
	if(empty($prev_clock)) $prev_page->setAttribute('disabled','disabled');

	$next_page = new CButton('next_page',S_NEXT.' »');
	if($count < $limit) $next_page->setAttribute('disabled','disabled');
//*/
	$navForm->addItem(array($prev_page,SPACE,$next_page));

	$navigation = $navForm;
//------------

/************************* FILTER **************************/
/***********************************************************/

	$filterForm = new CFormTable();
	$filterForm->setAttribute('name','zbx_filter');
	$filterForm->setAttribute('id','zbx_filter');

	$script = new CScript("javascript: if(CLNDR['audit_since'].clndr.setSDateFromOuterObj()){".
							"$('nav_time').value = parseInt(CLNDR['audit_since'].clndr.sdt.getTime()/1000);}");
	$filterForm->addAction('onsubmit',$script);

	$filterForm->addVar('nav_time',($_REQUEST['nav_time']>0)?$_REQUEST['nav_time']:'');
	$filterForm->addVar('config',$_REQUEST['config']);

	$filterForm->addVar('userid',$_REQUEST['userid']);

	if(isset($_REQUEST['userid']) && ($_REQUEST['userid']>0)){
		$user = get_user_by_userid($_REQUEST['userid']);
	}
	else{
		$user['alias'] = '';
	}
	$row = new CRow(array(
					new CCol(($config==1)?S_RECIPIENT:S_USER,'form_row_l'),
					new CCol(array(
								new CTextBox("user",$user['alias'],32,'yes'),
								new CButton("btn1",S_SELECT,"return PopUp('popup.php?"."dstfrm=".$filterForm->GetName()."&dstfld1=userid&dstfld2=user"."&srctbl=users&srcfld1=userid&srcfld2=alias&real_hosts=1');",'T')
							),'form_row_r')
						));

	$filterForm->addRow($row);

	if($config == 0){
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
			$cmbResource->addItem(-1,S_ALL_S);
			$cmbResource->addItem(AUDIT_RESOURCE_USER,			S_USER);
	//		$cmbResource->addItem(AUDIT_RESOURCE_ZABBIX,		S_ZABBIX);
			$cmbResource->addItem(AUDIT_RESOURCE_ZABBIX_CONFIG,	S_ZABBIX_CONFIG);
			$cmbResource->addItem(AUDIT_RESOURCE_MEDIA_TYPE,	S_MEDIA_TYPE);
			$cmbResource->addItem(AUDIT_RESOURCE_HOST,			S_HOST);
			$cmbResource->addItem(AUDIT_RESOURCE_ACTION,		S_ACTION);
			$cmbResource->addItem(AUDIT_RESOURCE_GRAPH,			S_GRAPH);
			$cmbResource->addItem(AUDIT_RESOURCE_GRAPH_ELEMENT,		S_GRAPH_ELEMENT);
	//		$cmbResource->addItem(AUDIT_RESOURCE_ESCALATION,		S_ESCALATION);
	//		$cmbResource->addItem(AUDIT_RESOURCE_ESCALATION_RULE,	S_ESCALATION_RULE);
	//		$cmbResource->addItem(AUDIT_RESOURCE_AUTOREGISTRATION,	S_AUTOREGISTRATION);
			$cmbResource->addItem(AUDIT_RESOURCE_USER_GROUP,	S_USER_GROUP);
			$cmbResource->addItem(AUDIT_RESOURCE_APPLICATION,	S_APPLICATION);
			$cmbResource->addItem(AUDIT_RESOURCE_TRIGGER,		S_TRIGGER);
			$cmbResource->addItem(AUDIT_RESOURCE_HOST_GROUP,	S_HOST_GROUP);
			$cmbResource->addItem(AUDIT_RESOURCE_ITEM,			S_ITEM);
			$cmbResource->addItem(AUDIT_RESOURCE_IMAGE,			S_IMAGE);
			$cmbResource->addItem(AUDIT_RESOURCE_VALUE_MAP,		S_VALUE_MAP);
			$cmbResource->addItem(AUDIT_RESOURCE_IT_SERVICE,	S_IT_SERVICE);
			$cmbResource->addItem(AUDIT_RESOURCE_MAP,			S_MAP);
			$cmbResource->addItem(AUDIT_RESOURCE_SCREEN,		S_SCREEN);
			$cmbResource->addItem(AUDIT_RESOURCE_NODE,			S_NODE);
			$cmbResource->addItem(AUDIT_RESOURCE_SCENARIO,		S_SCENARIO);
			$cmbResource->addItem(AUDIT_RESOURCE_DISCOVERY_RULE,S_DISCOVERY_RULE);
			$cmbResource->addItem(AUDIT_RESOURCE_SLIDESHOW,		S_SLIDESHOW);
			$cmbResource->addItem(AUDIT_RESOURCE_SCRIPT,		S_SCRIPT);
			$cmbResource->addItem(AUDIT_RESOURCE_PROXY,			S_PROXY);
			$cmbResource->addItem(AUDIT_RESOURCE_MAINTENANCE,	S_MAINTENANCE);
			$cmbResource->addItem(AUDIT_RESOURCE_REGEXP,		S_REGULAR_EXPRESSION);

		$filterForm->addRow(S_RESOURCE, $cmbResource);
	}
//*
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

	$filterForm->addRow(S_ACTIONS_BEFORE,$nav_clndr);

	zbx_add_post_js('create_calendar(null,'.
				'["nav_day","nav_month","nav_year","nav_hour","nav_minute"],'.
				'"audit_since");');

	zbx_add_post_js('addListener($("filter_icon"),'.
				'"click",CLNDR[\'audit_since\'].clndr.clndrhide.bindAsEventListener(CLNDR[\'audit_since\'].clndr));');

//*/
	$reset = new CButton('filter_rst',S_RESET);
	$reset->setType('button');
	$reset->setAction('javascript: var uri = new Curl(location.href); uri.setArgument("filter_rst",1); location.href = uri.getUrl();');

	$filterForm->addItemToBottomRow(new CButton("filter_set",S_FILTER));
	$filterForm->addItemToBottomRow($reset);

	$audit_wdgt->addFlicker($filterForm, get_profile('web.audit.filter.state',1));
//-------

	$nav = get_thin_table_header($navigation);

	$audit_wdgt->addItem(array($nav, $table, $nav));
	$audit_wdgt->show();
?>
<?php

include_once "include/page_footer.php";

?>
