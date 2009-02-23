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
	require_once 'include/config.inc.php';
	require_once 'include/audit.inc.php';
	require_once 'include/actions.inc.php';
	require_once 'include/users.inc.php';

	$page['title'] = "S_AUDIT";
	$page['file'] = 'audit.php';
	$page['hist_arg'] = array('prev','next');
	$page['scripts'] = array('calendar.js');

	$page['type'] = detect_page_type(PAGE_TYPE_HTML);

	if(PAGE_TYPE_HTML == $page['type']){
		define('ZBX_PAGE_DO_REFRESH', 1);
	}

	define('PAGE_SIZE', 100);

	$_REQUEST['config'] = get_request('config',get_profile('web.audit.config',0));

include_once 'include/page_header.php';
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'config'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN('0,1'),		NULL),

// actions
		'groupid'=>		array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,	NULL),
		'hostid'=>		array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,	NULL),
		'start'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	BETWEEN(0,65535).'({}%'.PAGE_SIZE.'==0)',	NULL),
		'next'=>		array(T_ZBX_STR, O_OPT,	P_SYS,	NULL,			NULL),
		'prev'=>		array(T_ZBX_STR, O_OPT,	P_SYS,	NULL,			NULL),

// filter
		'action'=>			array(T_ZBX_INT, O_OPT,	P_SYS,	BETWEEN(-1,6),	NULL),
		'resourcetype'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	BETWEEN(-1,28),	NULL),
		'filter_rst'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN(array(0,1)),	NULL),
		'filter_set'=>		array(T_ZBX_STR, O_OPT,	P_SYS,	null,	NULL),

		'userid'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,	NULL),

		'filter_timesince'=>	array(T_ZBX_INT, O_OPT,	P_UNSET_EMPTY,	null,	NULL),
		'filter_timetill'=>	array(T_ZBX_INT, O_OPT,	P_UNSET_EMPTY,	null,	NULL),

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
			update_profile('web.audit.filter.state',$_REQUEST['state']);
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

		$_REQUEST['filter_timesince'] = 0;
		$_REQUEST['filter_timetill'] = 0;
	}

	$_REQUEST['userid'] = get_request('userid',get_profile('web.audit.filter.userid',0));
	$_REQUEST['action'] = get_request('action',get_profile('web.audit.filter.action',-1));
	$_REQUEST['resourcetype'] = get_request('resourcetype',get_profile('web.audit.filter.resourcetype',-1));

	$_REQUEST['filter_timesince'] = get_request('filter_timesince',get_profile('web.audit.filter.timesince',0));
	$_REQUEST['filter_timetill'] = get_request('filter_timetill',get_profile('web.audit.filter.timetill',0));

	if(($_REQUEST['filter_timetill'] > 0) && ($_REQUEST['filter_timesince'] > $_REQUEST['filter_timetill'])){
		$tmp = $_REQUEST['filter_timesince'];
		$_REQUEST['filter_timesince'] = $_REQUEST['filter_timetill'];
		$_REQUEST['filter_timetill'] = $tmp;
	}

	if(isset($_REQUEST['filter_set']) || isset($_REQUEST['filter_rst'])){
		update_profile('web.audit.filter.userid',$_REQUEST['userid']);
		update_profile('web.audit.filter.action',$_REQUEST['action']);
		update_profile('web.audit.filter.resourcetype',$_REQUEST['resourcetype']);

		update_profile('web.audit.filter.timesince',$_REQUEST['filter_timesince']);
		update_profile('web.audit.filter.timetill',$_REQUEST['filter_timetill']);
	}
// --------------

	$config = $_REQUEST['config'];
	update_profile('web.hosts.config',$_REQUEST['config']);


	$_REQUEST['start'] = get_request('start', 0);
	$_REQUEST['start']-=(isset($_REQUEST['prev']))?PAGE_SIZE:0;
	$_REQUEST['start']+=(isset($_REQUEST['next']))?PAGE_SIZE:0;
	$_REQUEST['start']=($_REQUEST['start'])?$_REQUEST['start']:0;



?>
<?php
	$sql_cond=($_REQUEST['userid'])?(' AND a.userid='.$_REQUEST['userid'].' '):('');
	$sql_cond.=(($_REQUEST['action']>-1) && ($config == 0))?(' AND a.action='.$_REQUEST['action'].' '):('');
	$sql_cond.=(($_REQUEST['resourcetype']>-1) && ($config == 0))?(' AND a.resourcetype='.$_REQUEST['resourcetype'].' '):('');
	$sql_cond.=($_REQUEST['filter_timesince'])?' AND a.clock>'.$_REQUEST['filter_timesince']:' AND a.clock>100';
	$sql_cond.=($_REQUEST['filter_timetill'])?' AND a.clock<'.$_REQUEST['filter_timetill']:'';

	$frmForm = new CForm();
	$frmForm->SetMethod('get');

	$cmbConf = new CComboBox('config',$_REQUEST['config'],'submit()');
	$cmbConf->addItem(0,S_AUDIT_LOGS);
	$cmbConf->addItem(1,S_AUDIT_ACTIONS);

	$frmForm->addItem($cmbConf);

//	show_table_header(S_AUDIT_BIG, $frmForm);

	$row_count = 0;
	if(0 == $config){
		$table = new CTableInfo();
		$table->setHeader(array(
				make_sorting_link(S_TIME,'a.clock'),
				make_sorting_link(S_USER,'u.alias'),
				S_IP,
				S_RESOURCE,
				S_ACTION,
				S_ID,
				S_DESCRIPTION,
				S_DETAILS));

		$sql = 'SELECT a.auditid,a.clock,u.alias,a.ip,a.resourcetype,a.action,a.resourceid,a.resourcename,a.details '.
						' FROM auditlog a, users u '.
						' WHERE u.userid=a.userid '.
							$sql_cond.
							' AND '.DBin_node('u.userid', get_current_nodeid(null, PERM_READ_ONLY)).
						order_by('a.clock,u.alias');

		$result = DBselect($sql,$_REQUEST['start']+PAGE_SIZE);
		for($i=0; $row=DBfetch($result); $i++){
			if($i<$_REQUEST['start'])	continue;

			switch($row['action']){
				case AUDIT_ACTION_ADD:
					$action = S_ADDED;
					break;
				case AUDIT_ACTION_UPDATE:
					$action = S_UPDATED;
					break;
				case AUDIT_ACTION_DELETE:
					$action = S_DELETED;
					break;
				case AUDIT_ACTION_LOGIN:
					$action = S_LOGIN;
					break;
				case AUDIT_ACTION_LOGOUT:
					$action = S_LOGOUT;
					break;
				case AUDIT_ACTION_ENABLE:
					$action = S_ENABLED;
					break;
				case AUDIT_ACTION_DISABLE:
					$action = S_DISABLED;
					break;
				default:
					$action = S_UNKNOWN_ACTION;
			}

			if ('' == $row['details'] || '0' == $row['details'])
			{
				$details = array();
				$db_details = DBselect('select table_name,field_name,oldvalue,newvalue from auditlog_details where auditid='.$row['auditid']);
				while(NULL != ($db_detail = DBfetch($db_details)))
				{
					array_push($details, array($db_detail['table_name'].'.'.$db_detail['field_name'].': '.$db_detail['oldvalue'].' => '.$db_detail['newvalue'],BR()));
				}
			}
			else
				$details = $row['details'];

			$table->addRow(array(
				date('Y.M.d H:i:s',$row['clock']),
				$row['alias'],
				$row['ip'],
				audit_resource2str($row['resourcetype']),
				$action,
				$row['resourceid'],
				$row['resourcename'],
				new CCol($details)
			));
			$row_count++;
		}
						$numrows = new CSpan(null,'info');		$numrows->addOption('name','numrows');			$header = get_table_header(array(S_AUDIT_LOGS,						new CSpan(SPACE.SPACE.'|'.SPACE.SPACE, 'divider'),						S_FOUND.': ',$numrows,)						);					show_table_header($header, $frmForm);	}
	else if(1 == $config){
		$table = get_history_of_actions($_REQUEST["start"], PAGE_SIZE, $sql_cond);		
		$row_count = $table->GetNumRows();	
		
		$numrows = new CSpan(null,'info');
		$numrows->addOption('name','numrows');	
		$header = get_table_header(array(S_AUDIT_ACTIONS,
						new CSpan(SPACE.SPACE.'|'.SPACE.SPACE, 'divider'),
						S_FOUND.': ',$numrows,)
						);			
		show_table_header($header, $frmForm);
	}

/************************* FILTER **************************/
/***********************************************************/

	$prev = 'Prev 100';
	$next = 'Next 100';
	if($_REQUEST['start'] > 0){
		$prev = new Clink('Prev '.PAGE_SIZE, 'audit.php?prev=1'.url_param('start').url_param('config'),'styled');
	}

	if($table->GetNumRows() >= PAGE_SIZE){
		$next = new Clink('Next '.PAGE_SIZE, 'audit.php?next=1'.url_param('start').url_param('config'),'styled');
	}

	$filterForm = new CFormTable(S_FILTER);//,'events.php?filter_set=1','POST',null,'sform');
	$filterForm->addOption('name','zbx_filter');
	$filterForm->addOption('id','zbx_filter');
	$filterForm->setMethod('get');

	$script = new CScript("javascript: if(CLNDR['audit_since'].clndr.setSDateFromOuterObj()){".
							"$('filter_timesince').value = parseInt(CLNDR['audit_since'].clndr.sdt.getTime()/1000);}".
						"if(CLNDR['audit_till'].clndr.setSDateFromOuterObj()){".
							"$('filter_timetill').value = parseInt(CLNDR['audit_till'].clndr.sdt.getTime()/1000);}"
						);
	$filterForm->addAction('onsubmit',$script);

	$filterForm->addVar('filter_timesince',($_REQUEST['filter_timesince']>0)?$_REQUEST['filter_timesince']:'');
	$filterForm->addVar('filter_timetill',($_REQUEST['filter_timetill']>0)?$_REQUEST['filter_timetill']:'');

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
			$cmbAction->addItem(AUDIT_ACTION_LOGIN,	S_LOGIN);
			$cmbAction->addItem(AUDIT_ACTION_LOGOUT,	S_LOGOUT);
			$cmbAction->addItem(AUDIT_ACTION_ADD,	S_ADD);
			$cmbAction->addItem(AUDIT_ACTION_UPDATE,	S_UPDATE);
			$cmbAction->addItem(AUDIT_ACTION_DELETE,	S_DELETE);
			$cmbAction->addItem(AUDIT_ACTION_ENABLE,	S_ENABLE);
			$cmbAction->addItem(AUDIT_ACTION_DISABLE,S_DISABLE);

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
	$clndr_icon->AddAction('onclick',"javascript: var pos = getPosition(this); pos.top+=10; pos.left+=16; CLNDR['audit_since'].clndr.clndrshow(pos.top,pos.left);");

	$filtertimetab = new CTable();
	$filtertimetab->AddOption('width','10%');
	$filtertimetab->SetCellPadding(0);
	$filtertimetab->SetCellSpacing(0);

	$filtertimetab->AddRow(array(
							S_FROM,
							new CNumericBox('filter_since_day',(($_REQUEST['filter_timesince']>0)?date('d',$_REQUEST['filter_timesince']):''),2),
							'/',
							new CNumericBox('filter_since_month',(($_REQUEST['filter_timesince']>0)?date('m',$_REQUEST['filter_timesince']):''),2),
							'/',
							new CNumericBox('filter_since_year',(($_REQUEST['filter_timesince']>0)?date('Y',$_REQUEST['filter_timesince']):''),4),
							new CNumericBox('filter_since_hour',(($_REQUEST['filter_timesince']>0)?date('H',$_REQUEST['filter_timesince']):''),2),
							':',
							new CNumericBox('filter_since_minute',(($_REQUEST['filter_timesince']>0)?date('i',$_REQUEST['filter_timesince']):''),2),
							$clndr_icon
					));
	zbx_add_post_js('create_calendar(null,["filter_since_day","filter_since_month","filter_since_year","filter_since_hour","filter_since_minute"],"audit_since");');

	$clndr_icon->AddAction('onclick',"javascript: var pos = getPosition(this); pos.top+=10; pos.left+=16; CLNDR['audit_till'].clndr.clndrshow(pos.top,pos.left);");
	$filtertimetab->AddRow(array(
							S_TILL,
							new CNumericBox('filter_till_day',(($_REQUEST['filter_timetill']>0)?date('d',$_REQUEST['filter_timetill']):''),2),
							'/',
							new CNumericBox('filter_till_month',(($_REQUEST['filter_timetill']>0)?date('m',$_REQUEST['filter_timetill']):''),2),
							'/',
							new CNumericBox('filter_till_year',(($_REQUEST['filter_timetill']>0)?date('Y',$_REQUEST['filter_timetill']):''),4),
							new CNumericBox('filter_till_hour',(($_REQUEST['filter_timetill']>0)?date('H',$_REQUEST['filter_timetill']):''),2),
							':',
							new CNumericBox('filter_till_minute',(($_REQUEST['filter_timetill']>0)?date('i',$_REQUEST['filter_timetill']):''),2),
							$clndr_icon
					));
	zbx_add_post_js('create_calendar(null,["filter_till_day","filter_till_month","filter_till_year","filter_till_hour","filter_till_minute"],"audit_till");');

	zbx_add_post_js('addListener($("filter_icon"),"click",CLNDR[\'audit_since\'].clndr.clndrhide.bindAsEventListener(CLNDR[\'audit_since\'].clndr));'.
					'addListener($("filter_icon"),"click",CLNDR[\'audit_till\'].clndr.clndrhide.bindAsEventListener(CLNDR[\'audit_till\'].clndr));'
					);
	$row_count++;

	$filterForm->addRow(S_PERIOD, $filtertimetab);
//*/
	$reset = new CButton("filter_rst",S_RESET);
	$reset->SetType('button');
	$reset->SetAction('javascript: var uri = new Curl(location.href); uri.setArgument("filter_rst",1); location.href = uri.getUrl();');

	$filterForm->AddItemToBottomRow(new CButton("filter_set",S_FILTER));
	$filterForm->AddItemToBottomRow($reset);

	$navigation = array(
						new CSpan(array('&laquo; ',$prev),'textcolorstyles'),
						new CSpan(' | ','divider'),
						new CSpan(array($next,' &raquo;'),'textcolorstyles'));

	$filter = create_filter(S_FILTER,$navigation,$filterForm,'tr_filter',get_profile('web.audit.filter.state',0));
	$filter->Show();
//-------

	$table->show();

	show_thin_table_header(SPACE,$navigation);
	zbx_add_post_js('insert_in_element("numrows","'.--$row_count.'");');

?>

<?php

include_once "include/page_footer.php";

?>
