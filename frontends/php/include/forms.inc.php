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
// TODO !!! Correcr the help links !!! TODO

	require_once 	"include/defines.inc.php";
	require_once 	"include/classes/graph.inc.php";
	require_once 	"include/users.inc.php";
	require_once 	"include/db.inc.php";

	function	insert_slideshow_form()
	{
		global $_REQUEST;

		$form = new CFormTable(S_SLIDESHOW, null, 'post');
		$form->SetHelp('config_advanced.php');
		
		$form->AddVar('config', 1);
			
		if(isset($_REQUEST['slideshowid']))
		{
			$form->AddVar('slideshowid', $_REQUEST['slideshowid']);
		}

		$name		= get_request('name', '');
		$delay		= get_request('delay', 5);
		$steps		= get_request('steps', array());

		$new_step	= get_request('new_step', null);
		
		if((isset($_REQUEST['slideshowid']) && !isset($_REQUEST['form_refresh'])))
		{
			$slideshow_data = DBfetch(DBselect('select * from slideshows where slideshowid='.$_REQUEST['slideshowid']));
		
			$name		= $slideshow_data['name'];
			$delay		= $slideshow_data['delay'];
			$steps		= array();
			$db_steps = DBselect('select * from slides where slideshowid='.$_REQUEST['slideshowid'].' order by step');
			while($step_data = DBfetch($db_steps))
			{
				$steps[$step_data['step']] = array(
						'screenid' => $step_data['screenid'],
						'delay' => $step_data['delay']
					);
			}
		}
		
		$form->AddRow(S_NAME, new CTextBox('name', $name, 40));
		
		$form->AddRow(S_UPDATE_INTERVAL_IN_SEC, new CNumericBox("delay",$delay,5));
		
		$tblSteps = new CTableInfo(S_NO_SLIDES_DEFINED);
		$tblSteps->SetHeader(array(S_SCREEN, S_DELAY, SPACE));
		if(count($steps) > 0)
		{
			ksort($steps);
			$first = min(array_keys($steps));
			$last = max(array_keys($steps));
		}
		foreach($steps as $sid => $s)
		{
			if( !isset($s['screenid']) ) $s['screenid'] = 0;

			if(isset($s['delay']) && $s['delay'] > 0 )
				$s['delay'] = bold($s['delay']);
			else	
				$s['delay'] = $delay;
			
			$up = null;
			if($sid != $first)
			{
				$up = new CLink(S_UP,'#','action');
				$up->OnClick("return create_var('".$form->GetName()."','move_up',".$sid.", true);");
			}
			
			$down = null;
			if($sid != $last)
			{
				$down = new CLink(S_DOWN,'#','action');
				$down->OnClick("return create_var('".$form->GetName()."','move_down',".$sid.", true);");
			}
			
			$screen_data = get_screen_by_screenid($s['screenid']);
			$name = new CLink($screen_data['name'],'#','action');
			$name->OnClick("return create_var('".$form->GetName()."','edit_step',".$sid.", true);");
			
			$tblSteps->AddRow(array(
				array(new CCheckBox('sel_step[]',null,null,$sid), $name),
				$s['delay'],
				array($up, isset($up) && isset($down) ? SPACE : null, $down)
				));
		}
		$form->AddVar('steps', $steps);

		$form->AddRow(S_SLIDES, array(
			$tblSteps,
			!isset($new_step) ? new CButton('add_step',S_ADD,
				"return create_var('".$form->GetName()."','add_step',1, true);") : null,
			(count($steps) > 0) ? new CButton('del_sel_step',S_DELETE_SELECTED) : null
			));

		if(isset($new_step))
		{
			if( !isset($new_step['screenid']) )	$new_step['screenid'] = 0;
			if( !isset($new_step['delay']) )	$new_step['delay'] = 0;

			if( isset($new_step['sid']) )
				$form->AddVar('new_step[sid]',$new_step['sid']);

			$form->AddVar('new_step[screenid]',$new_step['screenid']);

			$screen_data = get_screen_by_screenid($new_step['screenid']);

			$form->AddRow(S_NEW_SLIDE, array(
					new CTextBox('screen_name', $screen_data['name'], 25, 'yes'),
					new CButton('select_screen',S_SELECT,
						'return PopUp("popup.php?dstfrm='.$form->GetName().'&srctbl=screens'.
						'&dstfld1=screen_name&srcfld1=name'.
						'&dstfld2=new_step%5Bscreenid%5D&srcfld2=screenid");'),
					S_DELAY,
					new CNumericBox('new_step[delay]', $new_step['delay'], 5), BR,
					new CButton('add_step', isset($new_step['sid']) ? S_SAVE : S_ADD),
					new CButton('cancel_step', S_CANCEL)

				),
				isset($new_step['sid']) ? 'edit' : 'new');
		}
		
		$form->AddItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST['slideshowid']))
		{
			$form->AddItemToBottomRow(SPACE);
			$form->AddItemToBottomRow(new CButton('clone',S_CLONE));
			$form->AddItemToBottomRow(SPACE);
			$form->AddItemToBottomRow(new CButtonDelete(S_DELETE_SLIDESHOW_Q,
				url_param('form').url_param('slideshowid').url_param('config')));
		}
		$form->AddItemToBottomRow(SPACE);
		$form->AddItemToBottomRow(new CButtonCancel());

                $form->Show();
	}

	function	insert_drule_form()
	{
		global $_REQUEST;

		$frm_title = S_DISCOVERY_RULE;
			
		if(isset($_REQUEST['druleid']))
		{
			if( ($rule_data = DBfetch(DBselect("select * from drules where druleid=".$_REQUEST["druleid"]))))
				$frm_title = S_DISCOVERY_RULE." \"".$rule_data["name"]."\"";
		}
		
		$form = new CFormTable($frm_title, null, 'post');
		$form->SetHelp("web.discovery.rule.php");

		if(isset($_REQUEST['druleid']))
		{
			$form->AddVar('druleid', $_REQUEST['druleid']);
		}
		
		if(isset($_REQUEST['druleid']) && $rule_data && (!isset($_REQUEST["form_refresh"]) || isset($_REQUEST["register"])))
		{

			$name		= $rule_data['name'];
			$iprange	= $rule_data['iprange'];
			$delay		= $rule_data['delay'];
			$status		= $rule_data['status'];

			//TODO init checks
			$dchecks = array();
			$db_checks = DBselect('select * from dchecks where druleid='.$_REQUEST['druleid']);
			while($check_data = DBfetch($db_checks))
			{
				$dchecks[] = array( 'type' => $check_data['type'], 'ports' => $check_data['ports'] ,
						'key' => $check_data['key_'], 'snmp_community' => $check_data['snmp_community']);
			}
		}
		else
		{
			$name		= get_request('name','');
			$iprange	= get_request('iprange','192.168.0.1-255');
			$delay		= get_request('delay',3600);
			$status		= get_request('status',DRULE_STATUS_ACTIVE);

			$dchecks	= get_request('dchecks',array());
		}
		$new_check_type	= get_request('new_check_type', SVC_HTTP);
		$new_check_ports= get_request('new_check_ports', '80');
		$new_check_key= get_request('new_check_key', '');
		$new_check_snmp_community= get_request('new_check_snmp_community', '');

		$form->AddRow(S_NAME, new CTextBox('name', $name, 40));
		$form->AddRow(S_IP_RANGE, new CTextBox('iprange', $iprange, 27));
		$form->AddRow(S_DELAY.' (seconds)', new CNumericBox('delay', $delay, 8));

		$form->AddVar('dchecks', $dchecks);

		foreach($dchecks as $id => $data)
		{
			switch($data['type'])
			{
				case SVC_SNMPv1:
				case SVC_SNMPv2:
					$external_param = '"'.$data['snmp_community'].'":"'.$data['key'].'"';
					break;
				case SVC_AGENT:	
					$external_param = ' "'.$data['key'].'"';
					break;
				default:
					$external_param = null;
			}
			$dchecks[$id] = array(
				new CCheckBox('selected_checks[]',null,null,$id), SPACE,
				discovery_check_type2str($data['type']), SPACE,
				'('.$data['ports'].')'.SPACE.$external_param,
				BR
			);
		}

		if(count($dchecks))
		{
			$dchecks[] = new CButton('delete_ckecks', S_DELETE_SELECTED);
			$form->AddRow(S_CHECKS, $dchecks);
		}

		$cmbChkType = new CComboBox('new_check_type',$new_check_type,
			"if(add_variable(this, 'type_changed', 1)) submit()"
			);
		foreach(array(SVC_SSH, SVC_LDAP, SVC_SMTP, SVC_FTP, SVC_HTTP, SVC_POP, SVC_NNTP, SVC_IMAP, SVC_TCP, SVC_AGENT, SVC_SNMPv1, SVC_SNMPv2) as $type_int)
			$cmbChkType->AddItem($type_int, discovery_check_type2str($type_int));

		if(isset($_REQUEST['type_changed']))
		{
			switch($new_check_type)
			{
				case SVC_SSH:	$new_check_ports = 22;	break;
				case SVC_LDAP:	$new_check_ports = 389;	break;
				case SVC_SMTP:	$new_check_ports = 25;	break;
				case SVC_FTP:	$new_check_ports = 21;	break;
				case SVC_HTTP:	$new_check_ports = 80;	break;
				case SVC_POP:	$new_check_ports = 110;	break;
				case SVC_NNTP:	$new_check_ports = 119;	break;
				case SVC_IMAP:	$new_check_ports = 143;	break;
				case SVC_TCP:	$new_check_ports = 80;	break;
				case SVC_AGENT:	$new_check_ports = 10050;	break;
				case SVC_SNMPv1:$new_check_ports = 161;	break;
				case SVC_SNMPv2:$new_check_ports = 161;	break;
			}
		}
		$external_param = array();
		switch($new_check_type)
		{
			case SVC_SNMPv1:
			case SVC_SNMPv2:
				$external_param = array_merge($external_param, array(BR, S_SNMP_COMMUNITY, SPACE, new CTextBox('new_check_snmp_community', $new_check_snmp_community)));
				$external_param = array_merge($external_param, array(BR, S_SNMP_OID, new CTextBox('new_check_key', $new_check_key), BR));
				break;
			case SVC_AGENT:	
				$form->AddVar('new_check_snmp_community', '');
				$external_param = array_merge($external_param, array(BR, S_KEY, new CTextBox('new_check_key', $new_check_key), BR));
				break;
			default:
				$form->AddVar('new_check_snmp_community', '');
				$form->AddVar('new_check_key', '');
		}

		$form->AddRow(S_NEW_CHECK, array(
			$cmbChkType, SPACE,
			S_PORTS_SMALL, SPACE, new CNumericBox('new_check_ports', $new_check_ports,5),
			$external_param,
			new CButton('add_check', S_ADD)
		),'new');

		$cmbStatus = new CComboBox("status", $status);
		foreach(array(DRULE_STATUS_ACTIVE, DRULE_STATUS_DISABLED) as $st)
			$cmbStatus->AddItem($st, discovery_status2str($st));
		$form->AddRow(S_STATUS,$cmbStatus);

		$form->AddItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["druleid"]))
		{
			$form->AddItemToBottomRow(SPACE);
			$form->AddItemToBottomRow(new CButton("clone",S_CLONE));
			$form->AddItemToBottomRow(SPACE);
			$form->AddItemToBottomRow(new CButtonDelete(S_DELETE_RULE_Q,
				url_param("form").url_param("druleid")));
		}
		$form->AddItemToBottomRow(SPACE);
		$form->AddItemToBottomRow(new CButtonCancel());

		$form->Show();
	}

	function	insert_httpstep_form()
	{
		$form = new CFormTable(S_STEP_OF_SCENARIO, null, 'post');
		$form->SetHelp("web.webmon.httpconf.php");

		$form->AddVar('dstfrm', get_request('dstfrm', null));
		$form->AddVar('sid', get_request('sid', null));
		$form->AddVar('list_name', get_request('list_name', null));

		$sid = get_request('sid', null);
		$name = get_request('name', '');
		$url = get_request('url', '');
		$posts = get_request('posts', '');
		$timeout = get_request('timeout', 15);
		$required = get_request('required', '');
		$status_codes = get_request('status_codes', '');
		
		$form->AddRow(S_NAME, new CTextBox('name', $name, 50));
		$form->AddRow(S_URL, new CTextBox('url', $url, 80));
		$form->AddRow(S_POST, new CTextArea('posts', $posts, 50, 10));
		$form->AddRow(S_TIMEOUT, new CNumericBox('timeout', $timeout, 5));
		$form->AddRow(S_REQUIRED, new CTextBox('required', $required, 80));
		$form->AddRow(S_STATUS_CODES, new CTextBox('status_codes', $status_codes, 80));
		
		$form->AddItemToBottomRow(new CButton("save", isset($sid) ? S_SAVE : S_ADD));

		$form->AddItemToBottomRow(new CButtonCancel(null,'close_window();'));

		$form->show();
	}
	
	function	insert_httptest_form()
	{
		global $_REQUEST;

		$form = new CFormTable(S_SCENARIO, null, 'post');
		$form->SetHelp("web.webmon.httpconf.php");
		
		if(isset($_REQUEST["groupid"]))
			$form->AddVar("groupid",$_REQUEST["groupid"]);
			
		$form->AddVar("hostid",$_REQUEST["hostid"]);
			
		if(isset($_REQUEST["httptestid"]))
		{
			$form->AddVar("httptestid",$_REQUEST["httptestid"]);
		}

		$name		= get_request('name', '');
		$application	= get_request('application', '');
		$delay		= get_request('delay', 60);
		$status		= get_request('status', HTTPTEST_STATUS_ACTIVE);
		$agent		= get_request('agent', '');
		$macros		= get_request('macros', array());
		$steps		= get_request('steps', array());
		
		if((isset($_REQUEST["httptestid"]) && !isset($_REQUEST["form_refresh"])) || isset($limited))
		{
			$httptest_data = DBfetch(DBselect("select wt.*, a.name as application ".
				" from httptest wt,applications a where wt.httptestid=".$_REQUEST["httptestid"].
				" and a.applicationid=wt.applicationid"));
		
			$name		= $httptest_data['name'];
			$application	= $httptest_data['application'];
			$delay		= $httptest_data['delay'];
			$status		= $httptest_data['status'];
			$agent		= $httptest_data['agent'];
			$macros		= $httptest_data['macros'];
			
			$steps		= array();
			$db_steps = DBselect('select * from httpstep where httptestid='.$_REQUEST["httptestid"].' order by no');
			while($step_data = DBfetch($db_steps))
			{
				$steps[] = $step_data;
			}
		}
		
		$form->AddRow(S_APPLICATION,array(
			new CTextBox('application', $application, 40),
			SPACE,
			new CButton('select_app',S_SELECT,
				'return PopUp("popup.php?dstfrm='.$form->GetName().
				'&dstfld1=application&srctbl=applications'.
				'&srcfld1=name&only_hostid='.$_REQUEST['hostid'].'",200,300,"application");')
			));

		$form->AddRow(S_NAME, new CTextBox('name', $name, 40));
		
		$form->AddRow(S_UPDATE_INTERVAL_IN_SEC, new CNumericBox("delay",$delay,5));
		
		$cmbAgent = new CEditableComboBox('agent', $agent, 80);
		$cmbAgent->AddItem('Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 2.0.50727)',
			'Internet Explorer 6.0 on Windows XP SP2 with .NET Framework 2.0 installed');
		$cmbAgent->AddItem('Mozilla/5.0 (Windows; U; Windows NT 5.1; en-GB; rv:1.8.0.7) Gecko/20060909 Firefox/1.5.0.7',
			'Mozilla Firefox 1.5.0.7 on Windows XP');
		$cmbAgent->AddItem('Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.8.0.7) Gecko/20060909 Firefox/1.5.0.7',
			'Mozilla Firefox 1.5.0.7 on Linux');
		$cmbAgent->AddItem('Opera/9.02 (Windows NT 5.1; U; en)',
			'Opera 9.02 on Windows XP');
		$cmbAgent->AddItem('Opera/9.02 (X11; Linux i686; U; en)',
			'Opera 9.02 on Linux');
		$cmbAgent->AddItem('Lynx/2.8.4rel.1 libwww-FM/2.14',
			'Lynx 2.8.4rel.1 on Linux');
		$cmbAgent->AddItem('Googlebot/2.1 (+http://www.google.com/bot.html)',
			'Googlebot');
		$form->AddRow(S_AGENT, $cmbAgent);
		
		$cmbStatus = new CComboBox("status", $status);
		foreach(array(HTTPTEST_STATUS_ACTIVE, HTTPTEST_STATUS_DISABLED) as $st)
			$cmbStatus->AddItem($st, httptest_status2str($st));
		$form->AddRow(S_STATUS,$cmbStatus);

		$form->AddRow(S_VARIABLES, new CTextArea('macros', $macros, 84, 5));

		$tblSteps = new CTableInfo();
		$tblSteps->SetHeader(array(S_NAME,S_TIMEOUT,S_URL,S_REQUIRED,S_STATUS,SPACE));
		if(count($steps) > 0)
		{
			$first = min(array_keys($steps));
			$last = max(array_keys($steps));
		}
		foreach($steps as $sid => $s)
		{
			if(!isset($s['name']))		$s['name'] = '';
			if(!isset($s['timeout']))	$s['timeout'] = 15;
			if(!isset($s['url']))       	$s['url'] = '';
			if(!isset($s['posts']))       	$s['posts'] = '';
			if(!isset($s['required']))       $s['required'] = '';
			
			$up = null;
			if($sid != $first)
			{
				$up = new CLink(S_UP,'#','action');
				$up->OnClick("return create_var('".$form->GetName()."','move_up',".$sid.", true);");
			}
			
			$down = null;
			if($sid != $last)
			{
				$down = new CLink(S_DOWN,'#','action');
				$down->OnClick("return create_var('".$form->GetName()."','move_down',".$sid.", true);");
			}

			$name = new CLink($s['name'],'#','action');
			$name->OnClick('return PopUp("popup_httpstep.php?dstfrm='.$form->GetName().
				'&list_name=steps&sid='.$sid.
				url_param($s['name'],false,'name').
				url_param($s['timeout'],false,'timeout').
				url_param($s['url'],false,'url').
				url_param($s['posts'],false,'posts').
				url_param($s['required'],false,'required').
				url_param($s['status_codes'],false,'status_codes').
				'");');
			
			if(strlen($s['url']) > 70)
			{
				$url = new CTag('span','yes', substr($s['url'],0,35).SPACE.'...'.SPACE.substr($s['url'],strlen($s['url'])-25,25));
				$url->SetHint($s['url']);
			}
			else
			{
				$url = $s['url'];
			}

			$tblSteps->AddRow(array(
				array(new CCheckBox('sel_step[]',null,null,$sid), $name),
				$s['timeout'].SPACE.S_SEC_SMALL,
				$url,
				$s['required'],
				$s['status_codes'],
				array($up, isset($up) && isset($down) ? SPACE : null, $down)
				));
		}
		$form->AddVar('steps', $steps);

		$form->AddRow(S_STEPS, array(
			(count($steps) > 0) ? array ($tblSteps, BR) : null ,
			new CButton('add_step',S_ADD,
				'return PopUp("popup_httpstep.php?dstfrm='.$form->GetName().'");'),
			(count($steps) > 0) ? new CButton('del_sel_step',S_DELETE_SELECTED) : null
			));
		
		$form->AddItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["httptestid"]))
		{
			$form->AddItemToBottomRow(SPACE);
			$form->AddItemToBottomRow(new CButton("clone",S_CLONE));
			$form->AddItemToBottomRow(SPACE);
			$form->AddItemToBottomRow(new CButtonDelete(S_DELETE_SCENARIO_Q,
				url_param("form").url_param("httptestid").url_param('hostid')));
		}
		$form->AddItemToBottomRow(SPACE);
		$form->AddItemToBottomRow(new CButtonCancel());

                $form->Show();
	}
	
	function	insert_configuration_form($file)
	{
		$type		= get_request('type',		'MYSQL');
		$server		= get_request('server',		'localhost');
		$database	= get_request('database',	'zabbix');
		$user		= get_request('user',		'root');
		$password	= get_request('password',	'');
		
		$form = new CFormTable(S_CONFIGURATION_OF_ZABBIX_DATABASE, null, 'post');
		
		$form->SetHelp("install_source_web.php");
		$cmbType = new CComboBox('type', $type);
		$cmbType->AddItem('MYSQL',	S_MYSQL);
		$cmbType->AddItem('POSTGRESQL',	S_POSTGRESQL);
		$cmbType->AddItem('ORACLE',	S_ORACLE);
		$form->AddRow(S_TYPE, $cmbType);
		
		$form->AddRow(S_HOST, new CTextBox('server', $server));
		$form->AddRow(S_NAME, new CTextBox('database', $database));
		$form->AddRow(S_USER, new CTextBox('user', $user));
		$form->AddRow(S_PASSWORD, new CPassBox('password', $password));
		
		$form->AddItemToBottomRow(new CButton('save',S_SAVE));
		
		$form->Show();
	}
	
	function	insert_node_form()
	{
		global $_REQUEST;

		global $ZBX_CURMASTERID;
		
		$frm_title = S_NODE;
			
		if(isset($_REQUEST['nodeid']))
		{
			$node_data = get_node_by_nodeid($_REQUEST['nodeid']);

			$node_type = detect_node_type($node_data);

			$masterid	= $node_data['masterid'];

			$frm_title = S_NODE." \"".$node_data["name"]."\"";
		}
		
		$frmNode= new CFormTable($frm_title);
		$frmNode->SetHelp("node.php");

		if(isset($_REQUEST['nodeid']))
		{
			$frmNode->AddVar('nodeid', $_REQUEST['nodeid']);
		}
		
		if(isset($_REQUEST['nodeid']) && (!isset($_REQUEST["form_refresh"]) || isset($_REQUEST["register"])))
		{
			$new_nodeid	= $node_data['nodeid'];
			$name		= $node_data['name'];
			$timezone	= $node_data['timezone'];
			$ip		= $node_data['ip'];
			$port		= $node_data['port'];
			$slave_history	= $node_data['slave_history'];
			$slave_trends	= $node_data['slave_trends'];
		}
		else
		{
			$new_nodeid	= get_request('new_nodeid',0);
			$name 		= get_request('name','');
			$timezone 	= get_request('timezone', 0);
			$ip		= get_request('ip','127.0.0.1');
			$port		= get_request('port',10051);
			$slave_history	= get_request('slave_history',90);
			$slave_trends	= get_request('slave_trends',365);
			$node_type	= get_request('node_type', ZBX_NODE_REMOTE);

			$masterid	= get_request('masterid', get_current_nodeid(false));
		}

		$master_node = DBfetch(DBselect('select name from nodes where nodeid='.$masterid));

		$frmNode->AddRow(S_NAME, new CTextBox('name', $name, 40));

		$frmNode->AddRow(S_ID, new CNumericBox('new_nodeid', $new_nodeid, 10));

		if(!isset($_REQUEST['nodeid']))
		{
			$cmbNodeType = new CComboBox('node_type', $node_type, 'submit()');
			$cmbNodeType->AddItem(ZBX_NODE_REMOTE, S_REMOTE);
			if($ZBX_CURMASTERID == 0)
			{
				$cmbNodeType->AddItem(ZBX_NODE_MASTER, S_MASTER);
			}
		}
		else
		{
			$cmbNodeType = new CTextBox('node_type_name', node_type2str($node_type), null, 'yes');
		}
		$frmNode->AddRow(S_TYPE, 	$cmbNodeType);

		if($node_type == ZBX_NODE_REMOTE)
		{
			$frmNode->AddRow(S_MASTER_NODE, new CTextBox('master_name',	$master_node['name'], 40, 'yes'));
		}

		$cmbTimeZone = new CComboBox('timezone', $timezone);
		for($i = -12; $i <= 13; $i++)
		{
			$cmbTimeZone->AddItem($i, "GMT".sprintf("%+03d:00", $i));
		}
		$frmNode->AddRow(S_TIME_ZONE, $cmbTimeZone);
		$frmNode->AddRow(S_IP, new CTextBox('ip', $ip, 15));
		$frmNode->AddRow(S_PORT, new CNumericBox('port', $port,5));
		$frmNode->AddRow(S_DO_NOT_KEEP_HISTORY_OLDER_THAN, new CNumericBox('slave_history', $slave_history,6));
		$frmNode->AddRow(S_DO_NOT_KEEP_TRENDS_OLDER_THAN, new CNumericBox('slave_trends', $slave_trends,6));

		
		$frmNode->AddItemToBottomRow(new CButton('save',S_SAVE));
		if(isset($_REQUEST['nodeid']) && $node_type != ZBX_NODE_LOCAL)
		{
			$frmNode->AddItemToBottomRow(SPACE);
			$frmNode->AddItemToBottomRow(new CButtonDelete("Delete selected node?",
				url_param("form").url_param("nodeid")));
		}
		$frmNode->AddItemToBottomRow(SPACE);
		$frmNode->AddItemToBottomRow(new CButtonCancel(url_param("config")));
		$frmNode->Show();
	}
	
	function insert_new_message_form($events,$bulk)
	{
		global $USER_DETAILS;
		global $_REQUEST;
	
		if($bulk){
			$title = S_ACKNOWLEDGE_ALARM_BY;
			$btn_txt2 = S_ACKNOWLEDGE.' '.S_AND_SYMB.' '.S_RETURN;			
		}
		else if(!DBfetch(get_acknowledges_by_eventid(get_request('eventid',0))))
		{
			$title = S_ACKNOWLEDGE_ALARM_BY;
			$btn_txt = S_ACKNOWLEDGE;
			$btn_txt2 = S_ACKNOWLEDGE.' '.S_AND_SYMB.' '.S_RETURN;
		}
		else
		{
			$title = S_ADD_COMMENT_BY;
			$btn_txt = S_SAVE;
			$btn_txt2 = S_SAVE.' '.S_AND_SYMB.' '.S_RETURN;
		}

		$frmMsg= new CFormTable($title." \"".$USER_DETAILS["alias"]."\"");
		$frmMsg->SetHelp("manual.php");

		if($bulk) $frmMsg->AddVar('bulkacknowledge',1);
		
		foreach($events as $id => $eventid){
			$frmMsg->AddVar('events['.$eventid.']',$eventid);
		}

		$frmMsg->AddRow(S_MESSAGE, new CTextArea("message","",80,6));

		$frmMsg->AddItemToBottomRow(new CButton("saveandreturn",$btn_txt2));
		(isset($btn_txt))?($frmMsg->AddItemToBottomRow(new CButton("save",$btn_txt))):('');
		$frmMsg->AddItemToBottomRow(new CButtonCancel(url_param('eventid')));

		$frmMsg->Show(false);

		SetFocus($frmMsg->GetName(),'message');

		$frmMsg->Destroy();
	}

	# Insert form for User
	function	insert_user_form($userid,$profile=0)
	{
		global $_REQUEST;

		$frm_title = S_USER;
		if(isset($userid))
		{
			global $USER_DETAILS;
/*			if($userid == $USER_DETAILS['userid']) $profile = 1;*/

			$user=get_user_by_userid($userid);
			$frm_title = S_USER." \"".$user["alias"]."\"";
		}

		if(isset($userid) && (!isset($_REQUEST["form_refresh"]) || isset($_REQUEST["register"])))
		{
			$alias		= $user["alias"];
			$name		= $user["name"];
			$surname	= $user["surname"];
			$password	= null;
			$password1	= null;
			$password2	= null;
			$url		= $user["url"];
			$autologout	= $user["autologout"];
			$lang		= $user["lang"];
			$refresh	= $user["refresh"];
			$user_type	= $user["type"];

			$user_groups	= array();
			$user_medias		= array();

			$db_user_groups = DBselect('select g.* from usrgrp g, users_groups ug'.
				' where ug.usrgrpid=g.usrgrpid and ug.userid='.$userid);

			while($db_group = DBfetch($db_user_groups))
			{
				$user_groups[$db_group['usrgrpid']] = $db_group['name'];
			}

			$db_medias = DBselect('select m.* from media m where m.userid='.$userid);
			while($db_media = DBfetch($db_medias))
			{
				array_push($user_medias, 
					array(	'mediatypeid' => $db_media['mediatypeid'],
						'period' => $db_media['period'],
						'sendto' => $db_media['sendto'],
						'severity' => $db_media['severity'],
						'active' => $db_media['active']
					)
				);
			}

			$new_group_id	= 0;
			$new_group_name = '';
		}
		else
		{
			$alias		= get_request("alias","");
			$name		= get_request("name","");
			$surname	= get_request("surname","");
			$password	= null;
			$password1 	= get_request("password1", null);
			$password2 	= get_request("password2", null);
			$url 		= get_request("url","");
			$autologout	= get_request("autologout","900");
			$lang		= get_request("lang","en_gb");
			$refresh	= get_request("refresh","30");
			$user_type	= get_request("user_type",USER_TYPE_ZABBIX_USER);;
			$user_groups	= get_request("user_groups",array());
			$change_password = get_request("change_password", null);

			$user_medias		= get_request("user_medias", array());

			$new_group_id	= get_request('new_group_id', 0);
			$new_group_name = get_request('new_group_name', '');
		}

		$perm_details	= get_request('perm_details',0);

		$media_types = array();
		$media_type_ids = array();
		foreach($user_medias as $one_media) $media_type_ids[$one_media['mediatypeid']] = 1;

		if(count($media_type_ids) > 0)
		{
			$db_media_types = DBselect('select mt.mediatypeid,mt.description from media_type mt'.
				' where mt.mediatypeid in ('.implode(',',array_keys($media_type_ids)).')');

			while($db_media_type = DBfetch($db_media_types))
			{
				$media_types[$db_media_type['mediatypeid']] = $db_media_type['description'];
			}	
		}

		$frmUser = new CFormTable($frm_title);
		$frmUser->SetName('user_form');
		$frmUser->SetHelp("web.users.php");
		$frmUser->AddVar("config",get_request("config",0));

		if(isset($userid))	$frmUser->AddVar("userid",$userid);

		if($profile==0)
		{
			$frmUser->AddRow(S_ALIAS,	new CTextBox("alias",$alias,20));
			$frmUser->AddRow(S_NAME,	new CTextBox("name",$name,20));
			$frmUser->AddRow(S_SURNAME,	new CTextBox("surname",$surname,20));
		}

		if(!isset($userid) || isset($change_password))
		{
			$frmUser->AddRow(S_PASSWORD,	new CPassBox("password1",$password1,20));
			$frmUser->AddRow(S_PASSWORD_ONCE_AGAIN,	new CPassBox("password2",$password2,20));
			if(isset($change_password))
				$frmUser->AddVar('change_password', $change_password);
		}
		else
		{
			$frmUser->AddRow(S_PASSWORD,	new CButton("change_password", S_CHANGE_PASSWORD));
		}

		if($profile==0)
		{
			global $USER_DETAILS;

			$frmUser->AddVar('user_groups',$user_groups);

			if(isset($userid) && ($USER_DETAILS['userid'] == $userid))
			{
				$frmUser->AddVar('user_type',$user_type);
			}
			else
			{
				$cmbUserType = new CComboBox('user_type', $user_type, $perm_details ? 'submit();' : null);
				$cmbUserType->AddItem(USER_TYPE_ZABBIX_USER,	user_type2str(USER_TYPE_ZABBIX_USER));
				$cmbUserType->AddItem(USER_TYPE_ZABBIX_ADMIN,	user_type2str(USER_TYPE_ZABBIX_ADMIN));
				$cmbUserType->AddItem(USER_TYPE_SUPER_ADMIN,	user_type2str(USER_TYPE_SUPER_ADMIN));
				$frmUser->AddRow(S_USER_TYPE, $cmbUserType);
			}
			
			$lstGroups = new CListBox('user_groups_to_del[]');
			$lstGroups->options['style'] = 'width: 270px';

			foreach($user_groups as $groupid => $group_name)
			{
				$lstGroups->AddItem($groupid,	$group_name);
			}

			$frmUser->AddRow(S_GROUPS, 
				array(
					$lstGroups, 
					BR, 
					new CButton('add_group',S_ADD,
						'return PopUp("popup_usrgrp.php?dstfrm='.$frmUser->GetName().
						'&list_name=user_groups_to_del[]&var_name=user_groups",450, 450);'),
					SPACE,
					(count($user_groups) > 0) ? new CButton('del_user_group',S_DELETE_SELECTED) : null
				));

			$frmUser->AddVar('user_medias', $user_medias);

			$media_table = new CTable(S_NO_MEDIA_DEFINED);
			foreach($user_medias as $id => $one_media)
			{
				if(!isset($one_media["active"]) || $one_media["active"]==0)
				{
					$status = new CLink(S_ENABLED,'#','enabled');
					$status->OnClick("return create_var('".$frmUser->GetName()."','disable_media',".$id.", true);");
				}
				else
				{
					$status = new CLink(S_DISABLED,'#','disabled');
					$status->OnClick("return create_var('".$frmUser->GetName()."','enable_media',".$id.", true);");
				}

				$media_table->AddRow(array(
					new CCheckBox('user_medias_to_del[]',null,null,$id),
					new CSpan($media_types[$one_media['mediatypeid']], 'nowrap'),
					new CSpan($one_media['sendto'], 'nowrap'),
					new CSpan($one_media['period'], 'nowrap'),
					media_severity2str($one_media['severity']),
					$status)
				);
			}
			$frmUser->AddRow(S_MEDIA, array($media_table,
				new CButton('add_media',S_ADD,
						'return PopUp("popup_media.php?dstfrm='.$frmUser->GetName().'",550,400);'),
				SPACE,
				(count($user_medias) > 0) ? new CButton('del_user_media',S_DELETE_SELECTED) : null
				));
		}

		$cmbLang = new CComboBox('lang',$lang);
		$cmbLang->AddItem("en_gb",S_ENGLISH_GB);
		$cmbLang->AddItem("cn_zh",S_CHINESE_CN);
		$cmbLang->AddItem("nl_nl",S_DUTCH_NL);
		$cmbLang->AddItem("fr_fr",S_FRENCH_FR);
		$cmbLang->AddItem("de_de",S_GERMAN_DE);
		$cmbLang->AddItem("it_it",S_ITALIAN_IT);
		$cmbLang->AddItem("lv_lv",S_LATVIAN_LV);
		$cmbLang->AddItem("ru_ru",S_RUSSIAN_RU);
		$cmbLang->AddItem("sp_sp",S_SPANISH_SP);
		$cmbLang->AddItem("sv_se",S_SWEDISH_SE);
		$cmbLang->AddItem("ja_jp",S_JAPANESE_JP);
		$cmbLang->AddItem("hu_hu",S_HUNGARY_HU);

		$frmUser->AddRow(S_LANGUAGE, $cmbLang);

		$frmUser->AddRow(S_AUTO_LOGOUT_IN_SEC,	new CNumericBox("autologout",$autologout,4));
		$frmUser->AddRow(S_URL_AFTER_LOGIN,	new CTextBox("url",$url,50));
		$frmUser->AddRow(S_SCREEN_REFRESH,	new CNumericBox("refresh",$refresh,4));
	
		
		if($profile==0)
		{
			$frmUser->AddVar('perm_details', $perm_details);

			$link = new CLink($perm_details ? S_HIDE : S_SHOW ,'#','action');
			$link->OnClick("return create_var('".$frmUser->GetName()."','perm_details',".($perm_details ? 0 : 1).", true);");
			$resources_list = array(
				S_RIGHTS_OF_RESOURCES,
				SPACE.'(',$link,')'
				);
			$frmUser->AddSpanRow($resources_list,'right_header');

			if($perm_details)
			{
				$group_ids = array_keys($user_groups);
				if(count($group_ids) == 0) $group_ids = array(-1);
				$db_rights = DBselect('select * from rights r where r.groupid in ('.implode(',',$group_ids).')');

				$tmp_perm = array();
				while($db_right = DBfetch($db_rights))
				{
					if(isset($tmp_perm[$db_right['type']][$db_right['id']]))
					{
						$tmp_perm[$db_right['type']][$db_right['id']] = 
							min($tmp_perm[$db_right['type']][$db_right['id']],
								$db_right['permission']);
					}
					else
					{
						$tmp_perm[$db_right['type']][$db_right['id']] = $db_right['permission'];
					}
				}

				$user_rights = array();
				foreach($tmp_perm as $type => $res)
				{
					foreach($res as $id => $perm)
					{
						array_push($user_rights, array(	
							'type'		=> $type,
							'id'		=> $id,
							'permission'	=> $perm
							));
					}
				}
				
				$frmUser->AddSpanRow(get_rights_of_elements_table($user_rights, $user_type));
			}
		}

		$frmUser->AddItemToBottomRow(new CButton('save',S_SAVE));
		if(isset($userid) && $profile == 0)
		{
			$frmUser->AddItemToBottomRow(SPACE);
			$frmUser->AddItemToBottomRow(new CButtonDelete("Delete selected user?",
				url_param("form").url_param("config").url_param("userid")));
		}
		$frmUser->AddItemToBottomRow(SPACE);
		$frmUser->AddItemToBottomRow(new CButtonCancel(url_param("config")));
		$frmUser->Show();
	}

	# Insert form for User Groups
	function	insert_usergroups_form()
	{
		global  $_REQUEST;

		$frm_title = S_USER_GROUP;
		if(isset($_REQUEST["usrgrpid"]))
		{
			$usrgrp		= get_group_by_usrgrpid($_REQUEST["usrgrpid"]);
			$frm_title 	= S_USER_GROUP." \"".$usrgrp["name"]."\"";
		}

		if(isset($_REQUEST["usrgrpid"]) && !isset($_REQUEST["form_refresh"]))
		{
			$name	= $usrgrp["name"];

			$group_users = array();
			$db_users=DBselect("select distinct u.userid,u.alias from users u,users_groups ug ".
				"where u.userid=ug.userid and ug.usrgrpid=".$_REQUEST["usrgrpid"].
				" order by alias");

			while($db_user=DBfetch($db_users))
				$group_users[$db_user["userid"]] = $db_user['alias'];

			$group_rights = array();			
			$sqls = array(
				'select r.*,n.name as name from rights r, nodes n where r.groupid='.$_REQUEST["usrgrpid"].
					' and r.type='.RESOURCE_TYPE_NODE.' and r.id=n.nodeid',
				'select r.*, n.name as node_name, g.name as name from groups g '.
					' left join rights r on r.type='.RESOURCE_TYPE_GROUP.' and r.id=g.groupid '.
					' left join nodes n on n.nodeid='.DBid2nodeid('g.groupid').
					' where r.groupid='.$_REQUEST["usrgrpid"],
				);
			foreach($sqls as $sql)
			{
				$db_rights = DBselect($sql);
				while($db_right = DBfetch($db_rights))
				{
					if(isset($db_right['node_name']))
						$db_right['name'] = $db_right['node_name'].':'.$db_right['name'];

					$group_rights[$db_right['name']] = array(
						'type'		=> $db_right['type'],
						'permission'	=> $db_right['permission'],
						'id'		=> $db_right['id']
					);
				}
			}
		}
		else
		{
			$name		= get_request("gname","");
			$group_users	= get_request("group_users",array());
			$group_rights	= get_request("group_rights",array());
		}
		$perm_details = get_request('perm_details', 0);

		ksort($group_rights);

		$frmUserG = new CFormTable($frm_title,"users.php");
		$frmUserG->SetHelp("web.users.groups.php");
		$frmUserG->AddVar("config",get_request("config",1));

		if(isset($_REQUEST["usrgrpid"]))
		{
			$frmUserG->AddVar("usrgrpid",$_REQUEST["usrgrpid"]);
		}
		$grName = new CTextBox("gname",$name,49);
		$grName->options['style'] = 'width: 250px';
		$frmUserG->AddRow(S_GROUP_NAME,$grName);

		$frmUserG->AddVar('group_rights', $group_rights);

		$frmUserG->AddVar('group_users', $group_users);

		$lstUsers = new CListBox('group_users_to_del[]');
		$lstUsers->options['style'] = 'width: 250px';

		foreach($group_users as $userid => $alias)
		{
			$lstUsers->AddItem($userid,	$alias);
		}

		$frmUserG->AddRow(S_USERS, 
			array(
				$lstUsers, 
				BR, 
				new CButton('add_user',S_ADD,
					"return PopUp('popup_users.php?dstfrm=".$frmUserG->GetName().
					"&list_name=group_users_to_del[]&var_name=group_users',450,450);"),
				(count($group_users) > 0) ? new CButton('del_group_user',S_DELETE_SELECTED) : null
			));

		$table_Rights = new CTable(S_NO_RIGHTS_DEFINED,'right_table');

		$lstWrite = new CListBox('right_to_del[read_write][]'	,null	,20);
		$lstRead  = new CListBox('right_to_del[read_only][]'	,null	,20);
		$lstDeny  = new CListBox('right_to_del[deny][]'		,null	,20);

		foreach($group_rights as $name => $element_data)
		{
			if($element_data['permission'] == PERM_DENY)		$lstDeny->AddItem($name, $name);
			elseif ($element_data['permission'] == PERM_READ_ONLY)	$lstRead->AddItem($name, $name);
			elseif ($element_data['permission'] == PERM_READ_WRITE)	$lstWrite->AddItem($name, $name);
			
		}

		$table_Rights->SetHeader(array(S_READ_WRITE, S_READ_ONLY, S_DENY),'header');
		$table_Rights->AddRow(array(new CCol($lstWrite,'read_write'), new CCol($lstRead,'read_only'), new CCol($lstDeny,'deny')));
		$table_Rights->AddRow(array(
			array(new CButton('add_read_write',S_ADD,
					"return PopUp('popup_right.php?dstfrm=".$frmUserG->GetName().
					"&permission=".PERM_READ_WRITE."',450,450);"),
				new CButton('del_read_write',S_DELETE_SELECTED)),
			array(	new CButton('add_read_only',S_ADD,
					"return PopUp('popup_right.php?dstfrm=".$frmUserG->GetName().
					"&permission=".PERM_READ_ONLY."',450,450);"),
				new CButton('del_read_only',S_DELETE_SELECTED)),
			array(new CButton('add_deny',S_ADD,
					"return PopUp('popup_right.php?dstfrm=".$frmUserG->GetName().
					"&permission=".PERM_DENY."',450,450);"),
				new CButton('del_deny',S_DELETE_SELECTED))
			));

		$frmUserG->AddRow(S_RIGHTS,$table_Rights);

		$frmUserG->AddVar('perm_details', $perm_details);

		$link = new CLink($perm_details ? S_HIDE : S_SHOW ,'#','action');
		$link->OnClick("return create_var('".$frmUserG->GetName()."','perm_details',".($perm_details ? 0 : 1).", true);");
		$resources_list = array(
			S_RIGHTS_OF_RESOURCES,
			SPACE.'(',$link,')'
			);
		$frmUserG->AddSpanRow($resources_list,'right_header');

		if($perm_details)
		{
			$frmUserG->AddSpanRow(get_rights_of_elements_table($group_rights));
		}

		$frmUserG->AddItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["usrgrpid"]))
		{
			$frmUserG->AddItemToBottomRow(SPACE);
			$frmUserG->AddItemToBottomRow(new CButtonDelete("Delete selected group?",
				url_param("form").url_param("config").url_param("usrgrpid")));
		}
		$frmUserG->AddItemToBottomRow(SPACE);
		$frmUserG->AddItemToBottomRow(new CButtonCancel(url_param("config")));
		$frmUserG->Show();
	}

	function	get_rights_of_elements_table($rights=array(),$user_type=USER_TYPE_ZABBIX_USER)
	{
		global $ZBX_LOCALNODEID;

		$table = new CTable('S_NO_ACCESSIBLE_RESOURCES', 'right_table');
		$table->SetHeader(array(SPACE, S_READ_WRITE, S_READ_ONLY, S_DENY),'header');

		if(ZBX_DISTRIBUTED)
		{
			$lst['node']['label']		= S_NODES;
			$lst['node']['read_write']	= new CListBox('nodes_write'	,null	,6);
			$lst['node']['read_only']	= new CListBox('nodes_read'	,null	,6);
			$lst['node']['deny']		= new CListBox('nodes_deny'	,null	,6);

			$nodes = get_accessible_nodes_by_rights($rights, $user_type, PERM_DENY, PERM_MODE_GE, PERM_RES_DATA_ARRAY);

			foreach($nodes as $node)
			{
				switch($node['permission'])
				{
					case PERM_READ_ONLY:	$list_name='read_only';		break;
					case PERM_READ_WRITE:	$list_name='read_write';	break;
					default:		$list_name='deny';		break;
				}
				$lst['node'][$list_name]->AddItem($node['nodeid'],$node['name']);
			}
			unset($nodes);
		}

		$lst['group']['label']		= S_HOST_GROUPS;
		$lst['group']['read_write']	= new CListBox('groups_write'	,null	,10);
		$lst['group']['read_only']	= new CListBox('groups_read'	,null	,10);
		$lst['group']['deny']		= new CListBox('groups_deny'	,null	,10);

		$groups = get_accessible_groups_by_rights($rights, $user_type, PERM_DENY, PERM_MODE_GE, PERM_RES_DATA_ARRAY, 
			ZBX_DISTRIBUTED ? null : $ZBX_LOCALNODEID);

		foreach($groups as $group)
		{
			switch($group['permission'])
			{
				case PERM_READ_ONLY:	$list_name='read_only';		break;
				case PERM_READ_WRITE:	$list_name='read_write';	break;
				default:		$list_name='deny';		break;
			}
			$lst['group'][$list_name]->AddItem($group['groupid'],$group['node_name'].':'.$group['name']);
		}
		unset($groups);
		
		$lst['host']['label']		= S_HOSTS;
		$lst['host']['read_write']	= new CListBox('hosts_write'	,null	,15);
		$lst['host']['read_only']	= new CListBox('hosts_read'	,null	,15);
		$lst['host']['deny']		= new CListBox('hosts_deny'	,null	,15);

		$hosts = get_accessible_hosts_by_rights($rights, $user_type, PERM_DENY, PERM_MODE_GE, PERM_RES_DATA_ARRAY,
			ZBX_DISTRIBUTED ? null : $ZBX_LOCALNODEID);

		foreach($hosts as $host)
		{
			switch($host['permission'])
			{
				case PERM_READ_ONLY:	$list_name='read_only';		break;
				case PERM_READ_WRITE:	$list_name='read_write';	break;
				default:		$list_name='deny';		break;
			}
			$lst['host'][$list_name]->AddItem($host['hostid'],$host['node_name'].':'.$host['host']);
		}
		unset($hosts);
		
		foreach($lst as $name => $lists)
		{
			$row = new CRow();
			foreach($lists as $class => $list_obj)
			{
				$row->AddItem(new CCol($list_obj, $class));
			}
			$table->AddRow($row);
		}
		unset($lst);

		return $table;
	}

	function	insert_item_selection_form()
	{
		global $_REQUEST;

		if(isset($_REQUEST['form_refresh']) && isset($_REQUEST['select']))
		{
			$selection_mode			= get_request("selection_mode"			,0);
			    				                   			    
			$with_node			= empty2null(get_request("with_node"));
			$with_group			= empty2null(get_request("with_group"));
			$with_host			= empty2null(get_request("with_host"));
			$with_application		= empty2null(get_request("with_application"));
			$with_description		= empty2null(get_request("with_description"));
			$with_type			= get_request("with_type"			,-1);
			$with_key			= empty2null(get_request("with_key"));
			$with_snmp_community		= empty2null(get_request("with_snmp_community"));
			$with_snmp_oid			= empty2null(get_request("with_snmp_oid"));
			$with_snmp_port			= empty2null(get_request("with_snmp_port"));
			$with_snmpv3_securityname	= empty2null(get_request("with_snmpv3_securityname"));
			$with_snmpv3_securitylevel	= get_request("with_snmpv3_securitylevel"	,-1);
			$with_snmpv3_authpassphrase	= empty2null(get_request("with_snmpv3_authpassphrase"));
			$with_snmpv3_privpassphrase	= empty2null(get_request("with_snmpv3_privpassphrase"));
			$with_value_type		= get_request("with_value_type"			,-1);
			$with_units			= empty2null(get_request("with_units"));
			$with_formula			= empty2null(get_request("with_formula"));
			$with_delay			= empty2null(get_request("with_delay"));
			$with_history			= empty2null(get_request("with_history"));
			$with_trends			= empty2null(get_request("with_trends"));
			$with_status			= empty2null(get_request("with_status"));
			$with_logtimefmt		= empty2null(get_request("with_logtimefmt"));
			$with_delta			= empty2null(get_request("with_delta"));
			$with_trapper_hosts		= empty2null(get_request("with_trapper_hosts"));
		}
		else
		{
			$selection_mode			= get_request("selection_mode"            ,get_profile("selection_mode",		0));
			    								    
			$with_node			= empty2null(get_request("with_node"                 ,get_profile("with_node")));
			$with_group			= empty2null(get_request("with_group"                ,get_profile("with_group")));
			$with_host			= empty2null(get_request("with_host"                 ,get_profile("with_host")));
			$with_application		= empty2null(get_request("with_application"          ,get_profile("with_application")));
			$with_description		= empty2null(get_request("with_description"          ,get_profile("with_description")));
			$with_type			= get_request("with_type"                 ,get_profile("with_type",			-1));
			$with_key			= empty2null(get_request("with_key"                  ,get_profile("with_key")));
			$with_snmp_community		= empty2null(get_request("with_snmp_community"       ,get_profile("with_snmp_community")));
			$with_snmp_oid			= empty2null(get_request("with_snmp_oid"             ,get_profile("with_snmp_oid")));
			$with_snmp_port			= empty2null(get_request("with_snmp_port"            ,get_profile("with_snmp_port")));
			$with_snmpv3_securityname	= empty2null(get_request("with_snmpv3_securityname"  ,get_profile("with_snmpv3_securityname")));
			$with_snmpv3_securitylevel	= get_request("with_snmpv3_securitylevel" ,get_profile("with_snmpv3_securitylevel",	-1));
			$with_snmpv3_authpassphrase	= empty2null(get_request("with_snmpv3_authpassphrase",get_profile("with_snmpv3_authpassphrase")));
			$with_snmpv3_privpassphrase	= empty2null(get_request("with_snmpv3_privpassphrase",get_profile("with_snmpv3_privpassphrase")));
			$with_value_type		= get_request("with_value_type"           ,get_profile("with_value_type",		-1));
			$with_units			= empty2null(get_request("with_units"                ,get_profile("with_units")));
			$with_formula			= empty2null(get_request("with_formula"              ,get_profile("with_formula")));
			$with_delay			= empty2null(get_request("with_delay"                ,get_profile("with_delay")));
			$with_history			= empty2null(get_request("with_history"              ,get_profile("with_history")));
			$with_trends			= empty2null(get_request("with_trends"               ,get_profile("with_trends")));
			$with_status			= empty2null(get_request("with_status"               ,get_profile("with_status")));
			$with_logtimefmt		= empty2null(get_request("with_logtimefmt"           ,get_profile("with_logtimefmt")));
			$with_delta			= empty2null(get_request("with_delta"                ,get_profile("with_delta")));
			$with_trapper_hosts		= empty2null(get_request("with_trapper_hosts"        ,get_profile("with_trapper_hosts")));
		}

		if($selection_mode == 0)
		{
			$with_node = null;
			$with_group = null;
			//$with_host = null;
			$with_application = null;
			//$with_description = null;
			$with_type = -1;
			//$with_key = null;
			$with_snmp_community = null;
			$with_snmp_oid = null;
			$with_snmp_port = null;
			$with_snmpv3_securityname = null;
			$with_snmpv3_securitylevel = -1;
			$with_snmpv3_authpassphrase = null;
			$with_snmpv3_privpassphrase = null;
			$with_value_type = -1;
			$with_units = null;
			$with_formula = null;
			$with_delay = null;
			$with_history = null;
			$with_trends = null;
			$with_status = null;
			$with_logtimefmt = null;
			$with_delta = null;
			$with_trapper_hosts = null;
		}
		
		update_profile("selection_mode"            , $_REQUEST['selection_mode']             = $selection_mode);
							        			     			
		update_profile("with_node"                 , $_REQUEST['with_node']                  = $with_node);
		update_profile("with_group"                , $_REQUEST['with_group']                 = $with_group);
		update_profile("with_host"                 , $_REQUEST['with_host']                  = $with_host);
		update_profile("with_application"          , $_REQUEST['with_application']           = $with_application);
		update_profile("with_description"          , $_REQUEST['with_description']           = $with_description);
		update_profile("with_type"                 , $_REQUEST['with_type']                  = $with_type);
		update_profile("with_key"                  , $_REQUEST['with_key']                   = $with_key);
		update_profile("with_snmp_community"       , $_REQUEST['with_snmp_community']        = $with_snmp_community);
		update_profile("with_snmp_oid"             , $_REQUEST['with_snmp_oid']              = $with_snmp_oid);
		update_profile("with_snmp_port"            , $_REQUEST['with_snmp_port']             = $with_snmp_port);
		update_profile("with_snmpv3_securityname"  , $_REQUEST['with_snmpv3_securityname']   = $with_snmpv3_securityname);
		update_profile("with_snmpv3_securitylevel" , $_REQUEST['with_snmpv3_securitylevel']  = $with_snmpv3_securitylevel);
		update_profile("with_snmpv3_authpassphrase", $_REQUEST['with_snmpv3_authpassphrase'] = $with_snmpv3_authpassphrase);
		update_profile("with_snmpv3_privpassphrase", $_REQUEST['with_snmpv3_privpassphrase'] = $with_snmpv3_privpassphrase);
		update_profile("with_value_type"           , $_REQUEST['with_value_type']            = $with_value_type);
		update_profile("with_units"                , $_REQUEST['with_units']                 = $with_units);
		update_profile("with_formula"              , $_REQUEST['with_formula']               = $with_formula);
		update_profile("with_delay"                , $_REQUEST['with_delay']                 = $with_delay);
		update_profile("with_history"              , $_REQUEST['with_history']               = $with_history);
		update_profile("with_trends"               , $_REQUEST['with_trends']                = $with_trends);
		update_profile("with_status"               , $_REQUEST['with_status']                = $with_status);
		update_profile("with_logtimefmt"           , $_REQUEST['with_logtimefmt']            = $with_logtimefmt);
		update_profile("with_delta"                , $_REQUEST['with_delta']                 = $with_delta);
		update_profile("with_trapper_hosts"        , $_REQUEST['with_trapper_hosts']         = $with_trapper_hosts);

		$form = new CFormTable(S_ITEM_SELECTION);
		$form->SetName('frmselection');

		$form->AddVar('hostid',get_request('hostid'));
		$form->AddVar('external_filter', 1);

		$form->SetTitle(S_ITEM_SELECTION,SPACE);

		$form->AddVar('selection_mode', $selection_mode);

		$modeLink = new CLink($selection_mode == 0 ? S_ADVANCED : S_SIMPLE, '#','action');
		$modeLink->SetAction('create_var(\''.$form->GetName().'\',\'selection_mode\','.($selection_mode == 0 ? 1 : 0).',true)');
		$form->AddRow(S_SELECTION_MODE,$modeLink);

		if(ZBX_DISTRIBUTED && $selection_mode)
		{
			$form->AddRow('from '.bold(S_NODE).' like', array(
				new CTextBox('with_node',$with_node,32),
				new CButton("btn_node",S_SELECT,"return PopUp('popup.php?dstfrm=".$form->GetName().
					"&dstfld1=with_node&srctbl=nodes&srcfld1=name',450,450);",
					"G")
			));
		}

		if($selection_mode)
		{
			$form->AddRow('from '.bold(S_HOST_GROUP).' like', array(
				new CTextBox('with_group',$with_group,32),
				new CButton("btn_group",S_SELECT,"return PopUp('popup.php?dstfrm=".$form->GetName().
					"&dstfld1=with_group&srctbl=host_group&srcfld1=name',450,450);",
					"G")
			));
		}

		$form->AddRow('from '.bold(S_HOST).' like',array(
			new CTextBox('with_host',$with_host,32),
			new CButton("btn_host",S_SELECT,
				"return PopUp('popup.php?dstfrm=".$form->GetName().
				"&dstfld1=with_host&dstfld2=hostid&srctbl=hosts&srcfld1=host&srcfld2=hostid',450,450);",
				'H')
			));

		if($selection_mode)
		{
			$form->AddRow('from '.bold(S_APPLICATION).' like',array(
				new CTextBox('with_application', $with_application, 32),
				new CButton('btn_app',S_SELECT,
					'return PopUp("popup.php?dstfrm='.$form->GetName().
					'&dstfld1=with_application&srctbl=applications'.
					'&srcfld1=name",400,300,"application");',
					'A')
				));
		}

		$form->AddRow('with '.bold(S_DESCRIPTION).' like', new CTextBox("with_description",$with_description,40));

		if($selection_mode)
		{
			$cmbType = new CComboBox("with_type",$with_type, "submit()");
			$cmbType->AddItem(-1, S_ALL_SMALL);
			foreach(array(ITEM_TYPE_ZABBIX, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_SIMPLE,
				ITEM_TYPE_SNMPV1, ITEM_TYPE_SNMPV2C, ITEM_TYPE_SNMPV3, ITEM_TYPE_TRAPPER,
				ITEM_TYPE_INTERNAL, ITEM_TYPE_AGGREGATE, ITEM_TYPE_HTTPTEST) as $it)
					$cmbType->AddItem($it, item_type2str($it));
			$form->AddRow('with '.bold(S_TYPE), $cmbType);
		}

		$form->AddRow('with '.bold(S_KEY).' like', array(new CTextBox("with_key",$with_key,40)));

		if($selection_mode)
		{
			if(($with_type==ITEM_TYPE_SNMPV1)||($with_type==ITEM_TYPE_SNMPV2C)||$with_type==ITEM_TYPE_SNMPV3)
			{ 
				$form->AddRow('with '.bold(S_SNMP_COMMUNITY).' like',
					new CTextBox("with_snmp_community",$with_snmp_community,16));
				$form->AddRow('with '.bold(S_SNMP_OID).' like',
					new CTextBox("with_snmp_oid",$with_snmp_oid,40));
				$form->AddRow('with '.bold(S_SNMP_PORT).' like',
					new CNumericBox("with_snmp_port",$with_snmp_port,5,null,true));
			}

			if($with_type==ITEM_TYPE_SNMPV3)
			{
				$form->AddRow('with '.bold(S_SNMPV3_SECURITY_NAME).' like',
					new CTextBox("with_snmpv3_securityname",$with_snmpv3_securityname,64));

				$cmbSecLevel = new CComboBox("with_snmpv3_securitylevel",$with_snmpv3_securitylevel);
				$cmbSecLevel->AddItem(-1,S_ALL_SMALL);
				$cmbSecLevel->AddItem(ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV,"NoAuthPriv");
				$cmbSecLevel->AddItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV,"AuthNoPriv");
				$cmbSecLevel->AddItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV,"AuthPriv");
				$form->AddRow('with '.bold(S_SNMPV3_SECURITY_LEVEL), $cmbSecLevel);

				$form->AddRow('with '.bold(S_SNMPV3_AUTH_PASSPHRASE).' like',
					new CTextBox("with_snmpv3_authpassphrase",$with_snmpv3_authpassphrase,64));

				$form->AddRow('with '.bold(S_SNMPV3_PRIV_PASSPHRASE).' like',
					new CTextBox("with_snmpv3_privpassphrase",$with_snmpv3_privpassphrase,64));
			}


			$cmbValType = new CComboBox("with_value_type",$with_value_type,"submit()");
			$cmbValType->AddItem(-1,	S_ALL_SMALL);
			$cmbValType->AddItem(ITEM_VALUE_TYPE_UINT64,	S_NUMERIC_UINT64);
			$cmbValType->AddItem(ITEM_VALUE_TYPE_FLOAT,	S_NUMERIC_FLOAT);
			$cmbValType->AddItem(ITEM_VALUE_TYPE_STR, 	S_CHARACTER);
			$cmbValType->AddItem(ITEM_VALUE_TYPE_LOG, 	S_LOG);
			$cmbValType->AddItem(ITEM_VALUE_TYPE_TEXT,	S_TEXT);
			$form->AddRow('with '.bold(S_TYPE_OF_INFORMATION),$cmbValType);
			
			if( ($with_value_type==ITEM_VALUE_TYPE_FLOAT) || ($with_value_type==ITEM_VALUE_TYPE_UINT64))
			{
				$form->AddRow('with '.bold(S_UNITS), new CTextBox("with_units",$with_units,40));
				$form->AddRow('with '.bold(S_CUSTOM_MULTIPLIER).' like', new CTextBox("with_formula",$with_formula,40));
			}

			if($with_type != ITEM_TYPE_TRAPPER && $with_type != ITEM_TYPE_HTTPTEST)
			{
				$form->AddRow('with '.bold(S_UPDATE_INTERVAL_IN_SEC),
					new CNumericBox("with_delay",$with_delay,5,null,true));
			}

			$form->AddRow('with '.bold(S_KEEP_HISTORY_IN_DAYS),
				new CNumericBox("with_history",$with_history,8,null,true));

			$form->AddRow('with '.bold(S_KEEP_TRENDS_IN_DAYS), new CNumericBox("with_trends",$with_trends,8,null,true));

			$cmbStatus = new CComboBox("with_status",$with_status);
			$cmbStatus->AddItem(-1,S_ALL_SMALL);
			foreach(array(ITEM_STATUS_ACTIVE,ITEM_STATUS_DISABLED,ITEM_STATUS_NOTSUPPORTED) as $st)
				$cmbStatus->AddItem($st,item_status2str($st));
			$form->AddRow('with '.bold(S_STATUS),$cmbStatus);

			if($with_value_type==ITEM_VALUE_TYPE_LOG)
			{
				$form->AddRow('with '.bold(S_LOG_TIME_FORMAT), new CTextBox("with_logtimefmt",$with_logtimefmt,16));
			}

			if( ($with_value_type==ITEM_VALUE_TYPE_FLOAT) || ($with_value_type==ITEM_VALUE_TYPE_UINT64))
			{
				$cmbDelta= new CComboBox("with_delta",$with_delta);
				$cmbDelta->AddItem(-1,S_ALL_SMALL);
				$cmbDelta->AddItem(0,S_AS_IS);
				$cmbDelta->AddItem(1,S_DELTA_SPEED_PER_SECOND);
				$cmbDelta->AddItem(2,S_DELTA_SIMPLE_CHANGE);
				$form->AddRow('with '.bold(S_STORE_VALUE),$cmbDelta);
			}
			
			if($with_type==ITEM_TYPE_TRAPPER)
			{
				$form->AddRow('with '.bold(S_ALLOWED_HOSTS).' like', new CTextBox("with_trapper_hosts",$with_trapper_hosts,40));
			}
		}

		$form->AddItemToBottomRow(array(
			new CButton('select',S_SEARCH),
			new CButtonCancel('&external_filter=1')));

		$form->Show();
	}

	# Insert form for Item information
	function	insert_item_form()
	{
		global  $_REQUEST;
		global  $USER_DETAILS;

		$frmItem = new CFormTable(S_ITEM);
		$frmItem->SetHelp("web.items.item.php");

		$frmItem->AddVar("config",get_request("config",0));
		if(isset($_REQUEST["groupid"]))

		$frmItem->AddVar("hostid",$_REQUEST["hostid"]);
		$frmItem->AddVar('applications_visible',1);


		$description	= get_request("description"	,"");
		$key		= get_request("key"		,"");
		$host		= get_request("host",		null);
		$delay		= get_request("delay"		,30);
		$history	= get_request("history"		,90);
		$status		= get_request("status"		,0);
		$type		= get_request("type"		,0);
		$snmp_community	= get_request("snmp_community"	,"public");
		$snmp_oid	= get_request("snmp_oid"	,"interfaces.ifTable.ifEntry.ifInOctets.1");
		$snmp_port	= get_request("snmp_port"	,161);
		$value_type	= get_request("value_type"	,ITEM_VALUE_TYPE_UINT64);
		$trapper_hosts	= get_request("trapper_hosts"	,"");
		$units		= get_request("units"		,'');
		$valuemapid	= get_request("valuemapid"	,0);
		$multiplier	= get_request("multiplier"	,0);
		$delta		= get_request("delta"		,0);
		$trends		= get_request("trends"		,365);
		$applications	= get_request("applications"	,array());
		$delay_flex	= get_request("delay_flex"	,array());

		$snmpv3_securityname	= get_request("snmpv3_securityname"	,"");
		$snmpv3_securitylevel	= get_request("snmpv3_securitylevel"	,0);
		$snmpv3_authpassphrase	= get_request("snmpv3_authpassphrase"	,"");
		$snmpv3_privpassphrase	= get_request("snmpv3_privpassphrase"	,"");

		$formula	= get_request("formula"		,"1");
		$logtimefmt	= get_request("logtimefmt"	,"");

		$add_groupid	= get_request("add_groupid"	,get_request("groupid",0));

		$limited 	= null;

		if(is_null($host)){
			$host_info = get_host_by_hostid($_REQUEST["hostid"]);
			$host = $host_info["host"];
		}

		if(isset($_REQUEST["itemid"]))
		{
			$frmItem->AddVar("itemid",$_REQUEST["itemid"]);

			$item_data = DBfetch(DBselect("select i.*, h.host, h.hostid".
				" from items i,hosts h where i.itemid=".$_REQUEST["itemid"].
				" and h.hostid=i.hostid"));

			$limited = ($item_data['templateid'] == 0  && $item_data['type'] != ITEM_TYPE_HTTPTEST) ? null : 'yes';
		}

		if((isset($_REQUEST["itemid"]) && !isset($_REQUEST["form_refresh"])) || isset($limited))
		{
			$description	= $item_data["description"];
			$key		= $item_data["key_"];
			$host		= $item_data["host"];
			$type		= $item_data["type"];
			$snmp_community	= $item_data["snmp_community"];
			$snmp_oid	= $item_data["snmp_oid"];
			$snmp_port	= $item_data["snmp_port"];
			$value_type	= $item_data["value_type"];
			$trapper_hosts	= $item_data["trapper_hosts"];
			$units		= $item_data["units"];
			$valuemapid	= $item_data["valuemapid"];
			$multiplier	= $item_data["multiplier"];
			$hostid		= $item_data["hostid"];
			
			$snmpv3_securityname	= $item_data["snmpv3_securityname"];
			$snmpv3_securitylevel	= $item_data["snmpv3_securitylevel"];
			$snmpv3_authpassphrase	= $item_data["snmpv3_authpassphrase"];
			$snmpv3_privpassphrase	= $item_data["snmpv3_privpassphrase"];

			$formula	= $item_data["formula"];
			$logtimefmt	= $item_data["logtimefmt"];

			if(!isset($limited) || !isset($_REQUEST["form_refresh"]))
			{
				$delay		= $item_data["delay"];
				$history	= $item_data["history"];
				$status		= $item_data["status"];
				$delta		= $item_data["delta"];
				$trends		= $item_data["trends"];
				$db_delay_flex	= $item_data["delay_flex"];
				
				if(isset($db_delay_flex))
				{
					$arr_of_dellays = explode(";",$db_delay_flex);
					foreach($arr_of_dellays as $one_db_delay)
					{
						$arr_of_delay = explode("/",$one_db_delay);
						if(!isset($arr_of_delay[0]) || !isset($arr_of_delay[1])) continue;

						array_push($delay_flex,array("delay"=>$arr_of_delay[0],"period"=>$arr_of_delay[1]));
					}
				}
				
				$applications = array_unique(array_merge($applications, get_applications_by_itemid($_REQUEST["itemid"])));
			}
		}

		$delay_flex_el = array();

		if($type != ITEM_TYPE_TRAPPER && $type != ITEM_TYPE_HTTPTEST)
		{
			$i = 0;
			foreach($delay_flex as $val)
			{
				if(!isset($val["delay"]) && !isset($val["period"])) continue;

				array_push($delay_flex_el,
					array(
						new CCheckBox("rem_delay_flex[]", 'no', null,$i),
							$val["delay"],
							" sec at ",
							$val["period"]
					),
					BR);
				$frmItem->AddVar("delay_flex[".$i."][delay]", $val['delay']);
				$frmItem->AddVar("delay_flex[".$i."][period]", $val['period']);
				$i++;
				if($i >= 7) break; /* limit count of  intervals
						    * 7 intervals by 30 symbols = 210 characters
						    * db storage field is 256
						    */
			}
		}

		if(count($delay_flex_el)==0)
			array_push($delay_flex_el, "No flexible intervals");
		else
			array_push($delay_flex_el, new CButton('del_delay_flex','delete selected'));

		if(count($applications)==0)  array_push($applications,0);

		if(isset($_REQUEST["itemid"])) {
			$frmItem->SetTitle(S_ITEM." '$host:".$item_data["description"]."'");
		} else {
			$frmItem->SetTitle(S_ITEM." '$host:$description'");
		}

		$frmItem->AddRow(S_DESCRIPTION, new CTextBox("description",$description,40, $limited));

		if(isset($limited))
		{
			$frmItem->AddRow(S_TYPE,  new CTextBox("typename", item_type2str($type), 40, 'yes'));
			$frmItem->AddVar('type', $type);
		}
		else
		{
			$cmbType = new CComboBox("type",$type,"submit()");
			foreach(array(ITEM_TYPE_ZABBIX,ITEM_TYPE_ZABBIX_ACTIVE,ITEM_TYPE_SIMPLE,
				ITEM_TYPE_SNMPV1,ITEM_TYPE_SNMPV2C,ITEM_TYPE_SNMPV3,ITEM_TYPE_TRAPPER,
				ITEM_TYPE_INTERNAL,ITEM_TYPE_AGGREGATE,ITEM_TYPE_EXTERNAL) as $it)
					$cmbType->AddItem($it,item_type2str($it));
			$frmItem->AddRow(S_TYPE, $cmbType);
		}

		if(($type==ITEM_TYPE_SNMPV1)||($type==ITEM_TYPE_SNMPV2C))
		{ 
			$frmItem->AddVar("snmpv3_securityname",$snmpv3_securityname);
			$frmItem->AddVar("snmpv3_securitylevel",$snmpv3_securitylevel);
			$frmItem->AddVar("snmpv3_authpassphrase",$snmpv3_authpassphrase);
			$frmItem->AddVar("snmpv3_privpassphrase",$snmpv3_privpassphrase);

			$frmItem->AddRow(S_SNMP_COMMUNITY, new CTextBox("snmp_community",$snmp_community,16,$limited));
			$frmItem->AddRow(S_SNMP_OID, new CTextBox("snmp_oid",$snmp_oid,40,$limited));
			$frmItem->AddRow(S_SNMP_PORT, new CNumericBox("snmp_port",$snmp_port,5,$limited));
		}
		else if($type==ITEM_TYPE_SNMPV3)
		{
			$frmItem->AddVar("snmp_community",$snmp_community);

			$frmItem->AddRow(S_SNMP_OID, new CTextBox("snmp_oid",$snmp_oid,40,$limited));

			$frmItem->AddRow(S_SNMPV3_SECURITY_NAME,
				new CTextBox("snmpv3_securityname",$snmpv3_securityname,64,$limited));

			if(isset($limited))
			{
				$frmItem->AddVar("snmpv3_securitylevel", $snmpv3_securitylevel);
				switch($snmpv3_securitylevel)
				{
					case ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV:	$snmpv3_securitylevel="NoAuthPriv";	break;
					case ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV:	$snmpv3_securitylevel = "AuthNoPriv";	break;
					case ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV:	$snmpv3_securitylevel = "AuthPriv";	break;
				}
				$frmItem->AddRow(S_SNMPV3_SECURITY_LEVEL,  new CTextBox("snmpv3_securitylevel_desc", 
					$snmpv3_securitylevel, $limited));
			}
			else
			{
				$cmbSecLevel = new CComboBox("snmpv3_securitylevel",$snmpv3_securitylevel);
				$cmbSecLevel->AddItem(ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV,"NoAuthPriv");
				$cmbSecLevel->AddItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV,"AuthNoPriv");
				$cmbSecLevel->AddItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV,"AuthPriv");
				$frmItem->AddRow(S_SNMPV3_SECURITY_LEVEL, $cmbSecLevel);
			}
			$frmItem->AddRow(S_SNMPV3_AUTH_PASSPHRASE,
				new CTextBox("snmpv3_authpassphrase",$snmpv3_authpassphrase,64,$limited));

			$frmItem->AddRow(S_SNMPV3_PRIV_PASSPHRASE,
				new CTextBox("snmpv3_privpassphrase",$snmpv3_privpassphrase,64,$limited));

			$frmItem->AddRow(S_SNMP_PORT, new CNumericBox("snmp_port",$snmp_port,5,$limited));
		}
		else
		{
			$frmItem->AddVar("snmp_community",$snmp_community);
			$frmItem->AddVar("snmp_oid",$snmp_oid);
			$frmItem->AddVar("snmp_port",$snmp_port);
			$frmItem->AddVar("snmpv3_securityname",$snmpv3_securityname);
			$frmItem->AddVar("snmpv3_securitylevel",$snmpv3_securitylevel);
			$frmItem->AddVar("snmpv3_authpassphrase",$snmpv3_authpassphrase);
			$frmItem->AddVar("snmpv3_privpassphrase",$snmpv3_privpassphrase);
		}

		if(isset($limited))
		{
			$btnSelect = null;
		}
		else
		{
			$btnSelect = new CButton('btn1',S_SELECT,
				"return PopUp('popup.php?dstfrm=".$frmItem->GetName().
				"&dstfld1=key&srctbl=help_items&srcfld1=key_&itemtype=".$type."');",
				'T');
		}
		
		$frmItem->AddRow(S_KEY, array(new CTextBox("key",$key,40,$limited), $btnSelect));

		if(isset($limited))
		{
			$frmItem->AddVar("value_type", $value_type);
			$cmbValType = new CTextBox('value_type_name', item_value_type2str($value_type), 40, 'yes');
		}
		else
		{
			$cmbValType = new CComboBox("value_type",$value_type,"submit()");
			$cmbValType->AddItem(ITEM_VALUE_TYPE_UINT64,	S_NUMERIC_UINT64);
			$cmbValType->AddItem(ITEM_VALUE_TYPE_FLOAT,	S_NUMERIC_FLOAT);
			$cmbValType->AddItem(ITEM_VALUE_TYPE_STR, 	S_CHARACTER);
			$cmbValType->AddItem(ITEM_VALUE_TYPE_LOG, 	S_LOG);
			$cmbValType->AddItem(ITEM_VALUE_TYPE_TEXT,	S_TEXT);
		}
		$frmItem->AddRow(S_TYPE_OF_INFORMATION,$cmbValType);
		
		if( ($value_type==ITEM_VALUE_TYPE_FLOAT) || ($value_type==ITEM_VALUE_TYPE_UINT64))
		{
			$frmItem->AddRow(S_UNITS, new CTextBox("units",$units,40, $limited));

			if(isset($limited))
			{
				$frmItem->AddVar("multiplier", $multiplier);
				$cmbMultipler = new CTextBox('multiplier_name', $multiplier ? S_CUSTOM_MULTIPLIER : S_DO_NOT_USE, 20, 'yes');
			}
			else
			{
				$cmbMultipler = new CComboBox("multiplier",$multiplier,"submit()");
				$cmbMultipler->AddItem(0,S_DO_NOT_USE);
				$cmbMultipler->AddItem(1,S_CUSTOM_MULTIPLIER);
			}
			$frmItem->AddRow(S_USE_MULTIPLIER, $cmbMultipler);
			
		}
		else
		{
			$frmItem->AddVar("units",$units);
			$frmItem->AddVar("multiplier",$multiplier);
		}

		if( !is_numeric($formula)) $formula = 1;
		if($multiplier == 1)
		{
			$frmItem->AddRow(S_CUSTOM_MULTIPLIER, new CTextBox("formula",$formula,40,$limited));
		}
		else
		{
			$frmItem->AddVar("formula",$formula);
		}
		if($type != ITEM_TYPE_TRAPPER && $type != ITEM_TYPE_HTTPTEST)
		{
			$frmItem->AddRow(S_UPDATE_INTERVAL_IN_SEC, new CNumericBox("delay",$delay,5));
			$frmItem->AddRow("Flexible intervals (sec)", $delay_flex_el);
			$frmItem->AddRow("New flexible interval", 
				array(
					S_DELAY, SPACE,
					new CNumericBox("new_delay_flex[delay]","50",5), 
					S_PERIOD, SPACE,
					new CTextBox("new_delay_flex[period]","1-7,00:00-23:59",27), BR,
					new CButton("add_delay_flex",S_ADD)
				),'new');
		}
		else
		{
			$frmItem->AddVar("delay",$delay);
			$frmItem->AddVar("delay_flex",null);
		}

		$frmItem->AddRow(S_KEEP_HISTORY_IN_DAYS, array(
			new CNumericBox("history",$history,8),
			(!isset($_REQUEST["itemid"])) ? null :
				new CButtonQMessage("del_history",S_CLEAN_HISTORY,S_HISTORY_CLEANING_CAN_TAKE_A_LONG_TIME_CONTINUE_Q)
			));
		$frmItem->AddRow(S_KEEP_TRENDS_IN_DAYS, new CNumericBox("trends",$trends,8));

		$cmbStatus = new CComboBox("status",$status);
		foreach(array(ITEM_STATUS_ACTIVE,ITEM_STATUS_DISABLED,ITEM_STATUS_NOTSUPPORTED) as $st)
			$cmbStatus->AddItem($st,item_status2str($st));
		$frmItem->AddRow(S_STATUS,$cmbStatus);

		if($value_type==ITEM_VALUE_TYPE_LOG)
		{
			$frmItem->AddRow(S_LOG_TIME_FORMAT, new CTextBox("logtimefmt",$logtimefmt,16,$limited));
		}
		else
		{
			$frmItem->AddVar("logtimefmt",$logtimefmt);
		}

		if( ($value_type==ITEM_VALUE_TYPE_FLOAT) || ($value_type==ITEM_VALUE_TYPE_UINT64))
		{
			$cmbDelta= new CComboBox("delta",$delta);
			$cmbDelta->AddItem(0,S_AS_IS);
			$cmbDelta->AddItem(1,S_DELTA_SPEED_PER_SECOND);
			$cmbDelta->AddItem(2,S_DELTA_SIMPLE_CHANGE);
			$frmItem->AddRow(S_STORE_VALUE,$cmbDelta);
		}
		else
		{
			$frmItem->AddVar("delta",0);
		}
		
		if(($value_type==ITEM_VALUE_TYPE_UINT64) || ($value_type == ITEM_VALUE_TYPE_STR))
		{
			if(isset($limited) && $type != ITEM_TYPE_HTTPTEST)
			{
				$frmItem->AddVar("valuemapid", $valuemapid);
				$map_name = S_AS_IS;
				if($map_data = DBfetch(DBselect('select name from valuemaps where valuemapid='.$valuemapid)))
				{
					$map_name = $map_data['name'];
				}
				$cmbMap = new CTextBox("valuemap_name", $map_name, 20, 'yes');
			}
			else
			{
				$cmbMap = new CComboBox("valuemapid",$valuemapid);
				$cmbMap->AddItem(0,S_AS_IS);
				$db_valuemaps = DBselect('select * from valuemaps where '.DBin_node('valuemapid'));
				while($db_valuemap = DBfetch($db_valuemaps))
					$cmbMap->AddItem(
						$db_valuemap["valuemapid"],
						get_node_name_by_elid($db_valuemap["valuemapid"]).$db_valuemap["name"]
						);
			}
			
			$link = new CLink("throw map","config.php?config=6","action");
			$link->AddOption("target","_blank");
			$frmItem->AddRow(array(S_SHOW_VALUE.SPACE,$link),$cmbMap);
			
		}
		else
		{
			$frmItem->AddVar("valuemapid",0);
		}

		if($type==ITEM_TYPE_TRAPPER)
		{
			$frmItem->AddRow(S_ALLOWED_HOSTS, new CTextBox("trapper_hosts",$trapper_hosts,40,$limited));
		}
		else
		{
			$frmItem->AddVar("trapper_hosts",$trapper_hosts);
		}

		if($type==ITEM_TYPE_HTTPTEST)
		{
			$app_names = get_applications_by_itemid($_REQUEST["itemid"], 'name');
			$frmItem->AddRow(S_APPLICATIONS, new CTextBox("application_name",
				isset($app_names[0]) ? $app_names[0] : '', 20, $limited));
			$frmItem->AddVar("applications",$applications,6);
		}
		else
		{
			$cmbApps = new CListBox("applications[]",$applications,6);
			$cmbApps->AddItem(0,"-".S_NONE."-");
			$db_applications = DBselect("select distinct applicationid,name from applications".
				" where hostid=".$_REQUEST["hostid"]." order by name");
			while($db_app = DBfetch($db_applications))
			{
				$cmbApps->AddItem($db_app["applicationid"],$db_app["name"]);
			}
			$frmItem->AddRow(S_APPLICATIONS,$cmbApps);
		}

		$frmRow = array(new CButton("save",S_SAVE));
		if(isset($_REQUEST["itemid"]))
		{
			array_push($frmRow,
				SPACE,
				new CButton("clone",S_CLONE));

			if(!isset($limited))
			{
				array_push($frmRow,
					SPACE,
					new CButtonDelete("Delete selected item?",
						url_param("form").url_param("groupid").url_param("hostid").url_param("config").
						url_param("itemid"))
				);
			}
		}
		array_push($frmRow,
			SPACE,
			new CButtonCancel(url_param("groupid").url_param("hostid").url_param("config")));

		$frmItem->AddSpanRow($frmRow,"form_row_last");

	        $cmbGroups = new CComboBox("add_groupid",$add_groupid);		

	        $groups=DBselect("select distinct groupid,name from groups ".
			"where groupid in (".get_accessible_groups_by_user($USER_DETAILS,PERM_READ_ONLY,null,null,get_current_nodeid()).") ".
			" order by name");
	        while($group=DBfetch($groups))
	        {
			$cmbGroups->AddItem(
					$group["groupid"],
					get_node_name_by_elid($group["groupid"]).$group["name"]
					);
	        }
		$frmItem->AddRow(S_GROUP,$cmbGroups);

		$cmbAction = new CComboBox("action");
		$cmbAction->AddItem("add to group",S_ADD_TO_GROUP);
		if(isset($_REQUEST["itemid"]))
		{
			$cmbAction->AddItem("update in group",S_UPDATE_IN_GROUP);
			$cmbAction->AddItem("delete from group",S_DELETE_FROM_GROUP);
		}
		$frmItem->AddItemToBottomRow($cmbAction);
		$frmItem->AddItemToBottomRow(SPACE);
		$frmItem->AddItemToBottomRow(new CButton("register","do"));

		$frmItem->Show();
	}

	function	insert_mass_update_item_form($elements_array_name)
	{
		global  $_REQUEST;
		global  $USER_DETAILS;

		$frmItem = new CFormTable(S_ITEM,null,'post');
		$frmItem->SetHelp("web.items.item.php");
		$frmItem->SetTitle(S_MASS_UPDATE);

		$frmItem->AddVar("form_mass_update",1);

		$frmItem->AddVar("group_itemid",get_request("group_itemid",array()));
		$frmItem->AddVar("config",get_request("config",0));
		if(isset($_REQUEST["groupid"]))
			$frmItem->AddVar("groupid",$_REQUEST["groupid"]);

		$frmItem->AddVar("hostid",$_REQUEST["hostid"]);

		$description	= get_request("description"	,"");
		$key		= get_request("key"		,"");
		$host		= get_request("host",		null);
		$delay		= get_request("delay"		,30);
		$history	= get_request("history"		,90);
		$status		= get_request("status"		,0);
		$type		= get_request("type"		,0);
		$snmp_community	= get_request("snmp_community"	,"public");
		$snmp_oid	= get_request("snmp_oid"	,"interfaces.ifTable.ifEntry.ifInOctets.1");
		$snmp_port	= get_request("snmp_port"	,161);
		$value_type	= get_request("value_type"	,ITEM_VALUE_TYPE_UINT64);
		$trapper_hosts	= get_request("trapper_hosts"	,"");
		$units		= get_request("units"		,'');
		$valuemapid	= get_request("valuemapid"	,0);
		$delta		= get_request("delta"		,0);
		$trends		= get_request("trends"		,365);
		$applications	= get_request("applications"	,array());
		$delay_flex	= get_request("delay_flex"	,array());

		$snmpv3_securityname	= get_request("snmpv3_securityname"	,"");
		$snmpv3_securitylevel	= get_request("snmpv3_securitylevel"	,0);
		$snmpv3_authpassphrase	= get_request("snmpv3_authpassphrase"	,"");
		$snmpv3_privpassphrase	= get_request("snmpv3_privpassphrase"	,"");

		$formula	= get_request("formula"		,"1");
		$logtimefmt	= get_request("logtimefmt"	,"");

		$add_groupid	= get_request("add_groupid"	,get_request("groupid",0));

		$delay_flex_el = array();

		$i = 0;
		foreach($delay_flex as $val)
		{
			if(!isset($val["delay"]) && !isset($val["period"])) continue;

			array_push($delay_flex_el,
				array(
					new CCheckBox("rem_delay_flex[]", 'no', null,$i),
						$val["delay"],
						" sec at ",
						$val["period"]
				),
				BR);
			$frmItem->AddVar("delay_flex[".$i."][delay]", $val['delay']);
			$frmItem->AddVar("delay_flex[".$i."][period]", $val['period']);
			$i++;
			if($i >= 7) break; /* limit count of  intervals
					    * 7 intervals by 30 symbols = 210 characters
					    * db storage field is 256
					    */
		}

		if(count($delay_flex_el)==0)
			array_push($delay_flex_el, "No flexible intervals");
		else
			array_push($delay_flex_el, new CButton('del_delay_flex','delete selected'));

		if(count($applications)==0)  array_push($applications,0);

		$cmbType = new CComboBox('type',$type);
		foreach(array(ITEM_TYPE_ZABBIX,ITEM_TYPE_ZABBIX_ACTIVE,ITEM_TYPE_SIMPLE,ITEM_TYPE_SNMPV1,
			ITEM_TYPE_SNMPV2C,ITEM_TYPE_SNMPV3,ITEM_TYPE_TRAPPER,ITEM_TYPE_INTERNAL,
			ITEM_TYPE_AGGREGATE,ITEM_TYPE_AGGREGATE,ITEM_TYPE_EXTERNAL) as $it)
				$cmbType->AddItem($it, item_type2str($it));

		$frmItem->AddRow(array( new CVisibilityBox('type_visible', get_request('type_visible'), 'type', S_ORIGINAL),
			S_TYPE), $cmbType);

		$frmItem->AddRow(array( new CVisibilityBox('community_visible', get_request('community_visible'), 'snmp_community', S_ORIGINAL),
			S_SNMP_COMMUNITY), new CTextBox('snmp_community',$snmp_community,16));

		$frmItem->AddRow(array( new CVisibilityBox('securityname_visible', get_request('securityname_visible'), 'snmpv3_securityname',
			S_ORIGINAL), S_SNMPV3_SECURITY_NAME), new CTextBox('snmpv3_securityname',$snmpv3_securityname,64));

		$cmbSecLevel = new CComboBox('snmpv3_securitylevel',$snmpv3_securitylevel);
		$cmbSecLevel->AddItem(ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV,"NoAuthPriv");
		$cmbSecLevel->AddItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV,"AuthNoPriv");
		$cmbSecLevel->AddItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV,"AuthPriv");
		$frmItem->AddRow(array( new CVisibilityBox('securitylevel_visible',  get_request('securitylevel_visible'), 'snmpv3_securitylevel',
			S_ORIGINAL), S_SNMPV3_SECURITY_LEVEL), $cmbSecLevel);
		$frmItem->AddRow(array( new CVisibilityBox('authpassphrase_visible', get_request('authpassphrase_visible'), 
			'snmpv3_authpassphrase', S_ORIGINAL), S_SNMPV3_AUTH_PASSPHRASE),
			new CTextBox('snmpv3_authpassphrase',$snmpv3_authpassphrase,64));

		$frmItem->AddRow(array( new CVisibilityBox('privpassphras_visible', get_request('privpassphras_visible'), 'snmpv3_privpassphrase',
			S_ORIGINAL), S_SNMPV3_PRIV_PASSPHRASE), new CTextBox('snmpv3_privpassphrase',$snmpv3_privpassphrase,64));

		$frmItem->AddRow(array( new CVisibilityBox('port_visible', get_request('port_visible'), 'snmp_port', S_ORIGINAL), S_SNMP_PORT),
			new CNumericBox('snmp_port',$snmp_port,5));

		$cmbValType = new CComboBox('value_type',$value_type);
		$cmbValType->AddItem(ITEM_VALUE_TYPE_UINT64,	S_NUMERIC_UINT64);
		$cmbValType->AddItem(ITEM_VALUE_TYPE_FLOAT,	S_NUMERIC_FLOAT);
		$cmbValType->AddItem(ITEM_VALUE_TYPE_STR, 	S_CHARACTER);
		$cmbValType->AddItem(ITEM_VALUE_TYPE_LOG, 	S_LOG);
		$cmbValType->AddItem(ITEM_VALUE_TYPE_TEXT,	S_TEXT);
		$frmItem->AddRow(array( new CVisibilityBox('value_type_visible', get_request('value_type_visible'), 'value_type', S_ORIGINAL),
			S_TYPE_OF_INFORMATION), $cmbValType);
		
		$frmItem->AddRow(array( new CVisibilityBox('units_visible', get_request('units_visible'), 'units', S_ORIGINAL), S_UNITS),
			new CTextBox('units',$units,40));

		$frmItem->AddRow(array( new CVisibilityBox('formula_visible', get_request('formula_visible'), 'formula', S_ORIGINAL),
			S_CUSTOM_MULTIPLIER.' (0 - '.S_DISABLED.')'), new CTextBox('formula',$formula,40));

		$frmItem->AddRow(array( new CVisibilityBox('delay_visible', get_request('delay_visible'), 'delay', S_ORIGINAL),
			S_UPDATE_INTERVAL_IN_SEC), new CNumericBox('delay',$delay,5));

		$delay_flex_el = new CTag('a', 'yes', $delay_flex_el);
		$delay_flex_el->AddOption('name', 'delay_flex_list');
		$delay_flex_el->AddOption('style', 'text-decoration: none');
		$frmItem->AddRow(array( new CVisibilityBox('delay_flex_visible', get_request('delay_flex_visible'), 
			array('delay_flex_list', 'new_delay_flex_el'), S_ORIGINAL), S_FLEXIBLE_INTERVALS), $delay_flex_el);
		$new_delay_flex_el = new CTag('a', 'yes', 
			array(
				S_DELAY, SPACE,
				new CNumericBox("new_delay_flex[delay]","50",5), 
				S_PERIOD, SPACE,
				new CTextBox("new_delay_flex[period]","1-7,00:00-23:59",27), BR,
				new CButton("add_delay_flex",S_ADD)
			));
		$new_delay_flex_el->AddOption('name', 'new_delay_flex_el');
		$new_delay_flex_el->AddOption('style', 'text-decoration: none');
		$frmItem->AddRow(S_NEW_FLEXIBLE_INTERVAL, $new_delay_flex_el, 'new');

		$frmItem->AddRow(array( new CVisibilityBox('history_visible', get_request('history_visible'), 'history', S_ORIGINAL),
			S_KEEP_HISTORY_IN_DAYS), new CNumericBox('history',$history,8));
		$frmItem->AddRow(array( new CVisibilityBox('trends_visible', get_request('trends_visible'), 'trends', S_ORIGINAL),
			S_KEEP_TRENDS_IN_DAYS), new CNumericBox('trends',$trends,8));

		$cmbStatus = new CComboBox('status',$status);
		foreach(array(ITEM_STATUS_ACTIVE,ITEM_STATUS_DISABLED,ITEM_STATUS_NOTSUPPORTED) as $st)
			$cmbStatus->AddItem($st,item_status2str($st));
		$frmItem->AddRow(array( new CVisibilityBox('status_visible', get_request('status_visible'), 'status', S_ORIGINAL), S_STATUS),
			$cmbStatus);

		$frmItem->AddRow(array( new CVisibilityBox('logtimefmt_visible', get_request('logtimefmt_visible'), 'logtimefmt', S_ORIGINAL),
			S_LOG_TIME_FORMAT), new CTextBox("logtimefmt",$logtimefmt,16));

		$cmbDelta= new CComboBox('delta',$delta);
		$cmbDelta->AddItem(0,S_AS_IS);
		$cmbDelta->AddItem(1,S_DELTA_SPEED_PER_SECOND);
		$cmbDelta->AddItem(2,S_DELTA_SIMPLE_CHANGE);
		$frmItem->AddRow(array( new CVisibilityBox('delta_visible', get_request('delta_visible'), 'delta', S_ORIGINAL),
			S_STORE_VALUE),$cmbDelta);
		
		$cmbMap = new CComboBox('valuemapid',$valuemapid);
		$cmbMap->AddItem(0,S_AS_IS);
		$db_valuemaps = DBselect('select * from valuemaps where '.DBin_node('valuemapid'));
		while($db_valuemap = DBfetch($db_valuemaps))
			$cmbMap->AddItem(
					$db_valuemap["valuemapid"],
					get_node_name_by_elid($db_valuemap["valuemapid"]).$db_valuemap["name"]
					);
		
		$link = new CLink("throw map","config.php?config=6","action");
		$link->AddOption("target","_blank");
		$frmItem->AddRow(array( new CVisibilityBox('valuemapid_visible', get_request('valuemapid_visible'), 'valuemapid', S_ORIGINAL),
			S_SHOW_VALUE, SPACE, $link),$cmbMap);
			
		$frmItem->AddRow(array( new CVisibilityBox('trapper_hosts_visible', get_request('trapper_hosts_visible'), 'trapper_hosts',
			S_ORIGINAL), S_ALLOWED_HOSTS), new CTextBox('trapper_hosts',$trapper_hosts,40));

		$cmbApps = new CListBox('applications[]',$applications,6);
		$cmbApps->AddItem(0,"-".S_NONE."-");
		$db_applications = DBselect("select distinct applicationid,name from applications".
			" where hostid=".$_REQUEST["hostid"]." order by name");
		while($db_app = DBfetch($db_applications))
		{
			$cmbApps->AddItem($db_app["applicationid"],$db_app["name"]);
		}
		$frmItem->AddRow(array( new CVisibilityBox('applications_visible', get_request('applications_visible'), 'applications[]',
			S_ORIGINAL), S_APPLICATIONS),$cmbApps);

		$frmItem->AddItemToBottomRow(array(new CButton("update",S_UPDATE),
			SPACE, new CButtonCancel(url_param("groupid").url_param("hostid").url_param("config"))));

		$frmItem->Show();
	}

	function	insert_copy_elements_to_forms($elements_array_name)
	{
		
		$copy_type = get_request("copy_type", 0);
		$copy_mode = get_request("copy_mode", 0);
		$filter_groupid = get_request("filter_groupid", 0);
		$group_itemid = get_request($elements_array_name, array());
		$copy_targetid = get_request("copy_targetid", array());

		if(!is_array($group_itemid) || (is_array($group_itemid) && count($group_itemid) < 1))
		{
			error("Incorrect list of items.");
			return;
		}

		$frmCopy = new CFormTable(count($group_itemid).' '.S_X_ELEMENTS_COPY_TO_DOT_DOT_DOT,null,'post',null,'form_copy_to');
		$frmCopy->SetHelp('web.items.copyto.php');
		$frmCopy->AddVar($elements_array_name, $group_itemid);

		$cmbCopyType = new CComboBox('copy_type',$copy_type,'submit()');
		$cmbCopyType->AddItem(0,S_HOSTS);
		$cmbCopyType->AddItem(1,S_HOST_GROUPS);
		$frmCopy->AddRow(S_TARGET_TYPE, $cmbCopyType);

		$target_sql = 'select distinct g.groupid as target_id, g.name as target_name'.
			' from groups g, hosts_groups hg'.
			' where hg.groupid=g.groupid';

		if(0 == $copy_type)
		{
			$cmbGroup = new CComboBox('filter_groupid',$filter_groupid,'submit()');
			$cmbGroup->AddItem(0,S_ALL_SMALL);
			$groups = DBselect($target_sql);
			while($group = DBfetch($groups))
			{
				$cmbGroup->AddItem($group["target_id"],$group["target_name"]);
			}
			$frmCopy->AddRow('Group', $cmbGroup);

			$target_sql = 'select h.hostid as target_id, h.host as target_name from hosts h';
			if($filter_groupid > 0)
			{
				$target_sql .= ', hosts_groups hg where hg.hostid=h.hostid and hg.groupid='.$filter_groupid;
			}
		}

		$db_targets = DBselect($target_sql.' order by target_name');
		$target_list = array();
		while($target = DBfetch($db_targets))
		{
			array_push($target_list,array(
				new CCheckBox('copy_targetid[]',
					in_array($target['target_id'], $copy_targetid), 
					null, 
					$target['target_id']),
				SPACE,
				$target['target_name'],
				BR
				));
		}

		$frmCopy->AddRow(S_TARGET, $target_list);

		$cmbCopyMode = new CComboBox('copy_mode',$copy_mode);
		$cmbCopyMode->AddItem(0, S_UPDATE_EXISTING_NON_LINKED_ITEMS);
		$cmbCopyMode->AddItem(1, S_SKIP_EXISTING_ITEMS);
		$cmbCopyMode->SetEnabled(false);
		$frmCopy->AddRow(S_MODE, $cmbCopyMode);

		$frmCopy->AddItemToBottomRow(new CButton("copy",S_COPY));
		$frmCopy->AddItemToBottomRow(array(SPACE,
			new CButtonCancel(url_param("groupid").url_param("hostid").url_param("config"))));

		$frmCopy->Show();
	}


	function	insert_login_form()
	{
		$frmLogin = new CFormTable('Login','index.php',"post","multipart/form-data");
		$frmLogin->SetHelp('web.index.login');
		$frmLogin->AddRow('Login name', new CTextBox('name'));
		$frmLogin->AddRow('Password', new CPassBox('password'));
		$frmLogin->AddItemToBottomRow(new CButton('enter','Enter'));
		$frmLogin->Show(false);

		SetFocus($frmLogin->GetName(),"name");
		
		$frmLogin->Destroy();
	}

	# Insert form for Trigger
	function	insert_trigger_form()
	{
		global $_REQUEST;

		$frmTrig = new CFormTable(S_TRIGGER,"triggers.php");
		$frmTrig->SetHelp("config_triggers.php");

		if(isset($_REQUEST["hostid"]))
		{
			$frmTrig->AddVar("hostid",$_REQUEST["hostid"]);
		}

		$dep_el=array();
		$dependences = get_request("dependences",array());
	
		$limited = null;
		
		if(isset($_REQUEST["triggerid"]))
		{
			$frmTrig->AddVar("triggerid",$_REQUEST["triggerid"]);
			$trigger=get_trigger_by_triggerid($_REQUEST["triggerid"]);

			$frmTrig->SetTitle(S_TRIGGER." \"".htmlspecialchars($trigger["description"])."\"");

			$limited = $trigger['templateid'] ? 'yes' : null;
		}

		$expression	= get_request("expression"	,"");
		$description	= get_request("description"	,"");
		$type = get_request('type', 0);
		$priority	= get_request("priority"	,0);
		$status		= get_request("status"		,0);
		$comments	= get_request("comments"	,"");
		$url		= get_request("url"		,"");

		if((isset($_REQUEST["triggerid"]) && !isset($_REQUEST["form_refresh"]))  || isset($limited))
		{
			$description	= $trigger["description"];
			$expression	= explode_exp($trigger["expression"],0);

			if(!isset($limited) || !isset($_REQUEST["form_refresh"]))
			{
				$type = $trigger['type'];
				$priority	= $trigger["priority"];
				$status		= $trigger["status"];
				$comments	= $trigger["comments"];
				$url		= $trigger["url"];

				$trigs=DBselect("select t.triggerid,t.description,t.expression from triggers t,trigger_depends d".
					" where t.triggerid=d.triggerid_up and d.triggerid_down=".$_REQUEST["triggerid"]);
				while($trig=DBfetch($trigs))
				{
					if(in_array($trig["triggerid"],$dependences))	continue;
					array_push($dependences,$trig["triggerid"]);
				}
			}
		}

		$frmTrig->AddRow(S_NAME, new CTextBox("description",$description,90, $limited));
		$frmTrig->AddRow(S_EXPRESSION, array(
				new CTextBox("expression",$expression,75, $limited),
				($limited ? null : new CButton('insert',S_INSERT,
					"return PopUp('popup_trexpr.php?dstfrm=".$frmTrig->GetName().
					"&dstfld1=expression&srctbl=expression".
					"&srcfld1=expression&expression=' + escape(GetSelectedText(this.form.elements['expression'])),700,200);"))
			));

	/* dependences */
		foreach($dependences as $val){
			array_push($dep_el,
				array(
					new CCheckBox("rem_dependence[]", 'no', null, strval($val)),
					expand_trigger_description($val)
				),
				BR);
			$frmTrig->AddVar("dependences[]",strval($val));
		}

		if(count($dep_el)==0)
			array_push($dep_el, "No dependences defined");
		else
			array_push($dep_el, new CButton('del_dependence','delete selected'));
		$frmTrig->AddRow("The trigger depends on",$dep_el);
	/* end dependences */

		global $USER_DETAILS;
	/* new dependence */
		$frmTrig->AddVar('new_dependence','0');

		$txtCondVal = new CTextBox('trigger','',75,'yes');

		$btnSelect = new CButton('btn1',S_SELECT,
				"return PopUp('popup.php?dstfrm=".$frmTrig->GetName().
				"&dstfld1=new_dependence&dstfld2=trigger&srctbl=triggers".
				"&srcfld1=triggerid&srcfld2=description',600,450);",
				'T');
		
		$frmTrig->AddRow("New dependency",array($txtCondVal, 
			$btnSelect, BR,
			new CButton("add_dependence",S_ADD)
			),'new');
			
		$type_select = new CComboBox('type');
		$type_select->Additem(TRIGGER_MULT_EVENT_DISABLED,S_NORMAL,(($type == TRIGGER_MULT_EVENT_ENABLED)?'no':'yes'));
		$type_select->Additem(TRIGGER_MULT_EVENT_ENABLED,S_NORMAL.SPACE.'+'.SPACE.S_MULTIPLE_TRUE_EVENTS,(($type == TRIGGER_MULT_EVENT_ENABLED)?'yes':'no'));
	/* end new dependence */
		$frmTrig->AddRow(S_EVENT_GENERATION,$type_select);

		$cmbPrior = new CComboBox("priority",$priority);
		for($i = 0; $i <= 5; $i++)
		{
			$cmbPrior->AddItem($i,get_severity_description($i));
		}
		$frmTrig->AddRow(S_SEVERITY,$cmbPrior);

		$frmTrig->AddRow(S_COMMENTS,new CTextArea("comments",$comments,90,7));
		$frmTrig->AddRow(S_URL,new CTextBox("url",$url,90));
		$frmTrig->AddRow(S_DISABLED,new CCheckBox("status",$status));
 
		$frmTrig->AddItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["triggerid"]))
		{
			$frmTrig->AddItemToBottomRow(SPACE);
			$frmTrig->AddItemToBottomRow(new CButton("clone",S_CLONE));
			$frmTrig->AddItemToBottomRow(SPACE);
			if( !$limited )
			{
				$frmTrig->AddItemToBottomRow(new CButtonDelete("Delete trigger?",
					url_param("form").url_param("groupid").url_param("hostid").
					url_param("triggerid")));
			}
		}
		$frmTrig->AddItemToBottomRow(SPACE);
		$frmTrig->AddItemToBottomRow(new CButtonCancel(url_param("groupid").url_param("hostid")));
		$frmTrig->Show();
	}

	function insert_trigger_comment_form($triggerid)
	{
		$trigger	= DBfetch(DBselect('select t.*, h.* from triggers t, functions f, items i, hosts h '.
			' where t.triggerid='.$triggerid.' and f.triggerid=t.triggerid and f.itemid=i.itemid '.
			' and i.hostid=h.hostid '));

		$frmComent = new CFormTable(S_COMMENTS." for ".$trigger['host']." : \"".expand_trigger_description_by_data($trigger)."\"");
		$frmComent->SetHelp("web.tr_comments.comments.php");
		$frmComent->AddVar("triggerid",$triggerid);
		$frmComent->AddRow(S_COMMENTS,new CTextArea("comments",stripslashes($trigger["comments"]),100,25));
		$frmComent->AddItemToBottomRow(new CButton("save",S_SAVE));
		$frmComent->AddItemToBottomRow(new CButtonCancel('&triggerid='.$triggerid));

		$frmComent->Show();
	}

	function	insert_graph_form()
	{
		global  $_REQUEST;

		$frmGraph = new CFormTable(S_GRAPH,null,'post');
		$frmGraph->SetName('frm_graph');
		$frmGraph->SetHelp("web.graphs.graph.php");

		$items = get_request('items', array());

		if(isset($_REQUEST["graphid"]))
		{
			$frmGraph->AddVar("graphid",$_REQUEST["graphid"]);

			$result=DBselect("select * from graphs where graphid=".$_REQUEST["graphid"]);
			$row=DBfetch($result);
			$frmGraph->SetTitle(S_GRAPH." \"".$row["name"]."\"");
		}

		if(isset($_REQUEST["graphid"]) && !isset($_REQUEST["form_refresh"]))
		{
			$name		=$row["name"];
			$width		=$row["width"];
			$height		=$row["height"];
			$yaxistype	=$row["yaxistype"];
			$yaxismin	=$row["yaxismin"];
			$yaxismax	=$row["yaxismax"];
			$showworkperiod = $row["show_work_period"];
			$showtriggers	= $row["show_triggers"];
			$graphtype	= $row["graphtype"];

			$db_items = DBselect('select * from graphs_items where graphid='.$_REQUEST["graphid"]);
			while($item = DBfetch($db_items))
			{
				array_push($items,
					array(
						'itemid'	=> $item['itemid'],
						'drawtype'	=> $item['drawtype'],
						'sortorder'	=> $item['sortorder'],
						'color'		=> $item['color'],
						'yaxisside'	=> $item['yaxisside'],
						'calc_fnc'	=> $item['calc_fnc'],
						'type'		=> $item['type'],
						'periods_cnt'	=> $item['periods_cnt']
					));
			}
		} else {
			$name		= get_request("name"		,"");
			$width		= get_request("width"		,900);
			$height		= get_request("height"		,200);
			$yaxistype	= get_request("yaxistype"	,GRAPH_YAXIS_TYPE_CALCULATED);
			$yaxismin	= get_request("yaxismin"	,0.00);
			$yaxismax	= get_request("yaxismax"	,100.00);
			$showworkperiod = get_request("showworkperiod"	,1);
			$showtriggers	= get_request("showtriggers"	,1);
			$graphtype	= get_request("graphtype"	,GRAPH_TYPE_NORMAL);
		}

		/* reinit $_REQUEST */
		$_REQUEST['items']		= $items;
		$_REQUEST['name']		= $name;
		$_REQUEST['width']		= $width;
		$_REQUEST['height']		= $height;
		$_REQUEST['yaxistype']		= $yaxistype;
		$_REQUEST['yaxismin']		= $yaxismin;
		$_REQUEST['yaxismax']		= $yaxismax;
		$_REQUEST['showworkperiod']	= $showworkperiod;
		$_REQUEST['showtriggers']	= $showtriggers;
		$_REQUEST['graphtype']		= $graphtype;
		/********************/

		if($graphtype == GRAPH_TYPE_STACKED)
		{
			foreach($items as $gid => $gitem)
			{
				if($gitem['type'] != GRAPH_ITEM_AGGREGATED) continue;
				unset($items[$gid]);
			}
		}

		asort_by_key($items, 'sortorder');

		$group_gid = get_request('group_gid', array());
	
		$frmGraph->AddRow(S_NAME,new CTextBox("name",$name,32));
		$frmGraph->AddRow(S_WIDTH,new CNumericBox("width",$width,5));
		$frmGraph->AddRow(S_HEIGHT,new CNumericBox("height",$height,5));

		$cmbGType = new CComboBox("graphtype",$graphtype,'submit()');
		$cmbGType->AddItem(GRAPH_TYPE_NORMAL,S_NORMAL);
		$cmbGType->AddItem(GRAPH_TYPE_STACKED,S_STACKED);
		$frmGraph->AddRow(S_GRAPH_TYPE,$cmbGType);

		$frmGraph->AddRow(S_SHOW_WORKING_TIME,new CCheckBox("showworkperiod",$showworkperiod,null,1));
		$frmGraph->AddRow(S_SHOW_TRIGGERS,new CCheckBox("showtriggers",$showtriggers,null,1));

		$cmbYType = new CComboBox("yaxistype",$yaxistype,"submit()");
		$cmbYType->AddItem(GRAPH_YAXIS_TYPE_CALCULATED,S_CALCULATED);
		$cmbYType->AddItem(GRAPH_YAXIS_TYPE_FIXED,S_FIXED);
		$frmGraph->AddRow(S_YAXIS_TYPE,$cmbYType);

		if($yaxistype == GRAPH_YAXIS_TYPE_FIXED)
		{
			$frmGraph->AddRow(S_YAXIS_MIN_VALUE,new CTextBox("yaxismin",$yaxismin,9));
			$frmGraph->AddRow(S_YAXIS_MAX_VALUE,new CTextBox("yaxismax",$yaxismax,9));
		}
		else
		{
			$frmGraph->AddVar("yaxismin",$yaxismin);
			$frmGraph->AddVar("yaxismax",$yaxismax);
		}

		$only_hostid = null;
		$monitored_hosts = null;

		if(count($items))
		{
			$frmGraph->AddVar('items', $items);

			$items_table = new CTableInfo();
			foreach($items as $gid => $gitem)
			{
				if($graphtype == GRAPH_TYPE_STACKED && $gitem['type'] == GRAPH_ITEM_AGGREGATED) continue;

				$host = get_host_by_itemid($gitem['itemid']);
				$item = get_item_by_itemid($gitem['itemid']);

				if($host['status'] == HOST_STATUS_TEMPLATE) $only_hostid = $host['hostid'];
				else $monitored_hosts = 1;

				if($gitem["type"] == GRAPH_ITEM_AGGREGATED)
					$color = "-";
				else
					$color = new CColorCell(null,$gitem['color']);

				$do_up = new CLink(S_UP,'#','action');
				$do_up->OnClick("return create_var('".$frmGraph->GetName()."','move_up',".$gid.", true);");

				$do_down = new CLink(S_DOWN,'#','action');
				$do_down->OnClick("return create_var('".$frmGraph->GetName()."','move_down',".$gid.", true);");

				$description = new CLink($host['host'].': '.item_description($item["description"],$item["key_"]),'#','action');
				$description->OnClick(
						'return PopUp("popup_gitem.php?list_name=items&dstfrm='.$frmGraph->GetName().
						url_param($only_hostid, false, 'only_hostid').
						url_param($monitored_hosts, false, 'monitored_hosts').
						url_param($graphtype, false, 'graphtype').
						url_param($gitem, false).
						url_param($gid,false,'gid').
						'",550,400,"graph_item_form");');
				
				$items_table->AddRow(array(
						new CCheckBox('group_gid['.$gid.']',isset($group_gid[$gid])),
						$gitem['sortorder'],
						$description,
						graph_item_calc_fnc2str($gitem["calc_fnc"],$gitem["type"]),
						graph_item_type2str($gitem['type'],$gitem["periods_cnt"]),
						graph_item_drawtype2str($gitem["drawtype"],$gitem["type"]),
						$color,
						array( $do_up, SPACE."|".SPACE, $do_down )
					));
			}
			$dedlete_button = new CButton('delete_item', S_DELETE_SELECTED);
		}
		else
		{
			$items_table = $dedlete_button = null;
		}
		$frmGraph->AddRow(S_ITEMS,
				array(
					$items_table,
					new CButton('add_item',S_ADD,
						'return PopUp("popup_gitem.php?dstfrm='.$frmGraph->GetName().
						url_param($only_hostid, false, 'only_hostid').
						url_param($monitored_hosts, false, 'monitored_hosts').
						url_param($graphtype, false, 'graphtype').
						'",550,400,"graph_item_form");'),
					$dedlete_button
				));
		unset($items_table, $dedlete_button);

		$frmGraph->AddItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["graphid"]))
		{
			$frmGraph->AddItemToBottomRow(SPACE);
			$frmGraph->AddItemToBottomRow(new CButton("clone",S_CLONE));
			$frmGraph->AddItemToBottomRow(SPACE);
			$frmGraph->AddItemToBottomRow(new CButtonDelete(S_DELETE_GRAPH_Q,url_param("graphid").
				url_param("groupid").url_param("hostid")));
		}
		$frmGraph->AddItemToBottomRow(SPACE);
		$frmGraph->AddItemToBottomRow(new CButtonCancel(url_param("groupid").url_param("hostid")));

		$frmGraph->Show();
	}

	function	insert_graphitem_form()
	{
		global $_REQUEST;

		$frmGItem = new CFormTable(S_NEW_ITEM_FOR_THE_GRAPH);
		$frmGItem->SetName('graph_item');
		$frmGItem->SetHelp("web.graph.item.php");

		$frmGItem->AddVar('dstfrm',$_REQUEST['dstfrm']);

		$graphtype	= get_request("graphtype", 	GRAPH_TYPE_NORMAL);
		$gid		= get_request("gid",	 	null);
		$list_name	= get_request("list_name", 	null);
		$itemid		= get_request("itemid", 	0);
		$color		= get_request("color", 		'009900');
		$drawtype	= get_request("drawtype",	0);
		$sortorder	= get_request("sortorder",	0);
		$yaxisside	= get_request("yaxisside",	1);
		$calc_fnc	= get_request("calc_fnc",	2);
		$type		= get_request("type",		0);
		$periods_cnt	= get_request("periods_cnt",	5);
		$only_hostid	= get_request("only_hostid",	null);
		$monitored_hosts = get_request('monitored_hosts', null);

		$description = '';
		if($itemid > 0)
		{
			$description = DBfetch(DBselect("select * from items where itemid=".$itemid));
			$description = item_description($description['description'],$description['key_']);
		}
		
		$frmGItem->AddVar('gid',$gid);
		$frmGItem->AddVar('list_name',$list_name);
		$frmGItem->AddVar('itemid',$itemid);
		$frmGItem->AddVar('graphtype',$graphtype);
		$frmGItem->AddVar('only_hostid',$only_hostid);

		$txtCondVal = new CTextBox('description',$description,50,'yes');

		$host_condition = "";
		if(isset($only_hostid))
		{// graph for template must use only one host
			$host_condition = "&only_hostid=".$only_hostid;
		}
		else if(isset($monitored_hosts))
		{
			$host_condition = "&monitored_hosts=1";
		}

		$btnSelect = new CButton('btn1',S_SELECT,
				"return PopUp('popup.php?dstfrm=".$frmGItem->GetName().
				"&dstfld1=itemid&dstfld2=description&".
				"srctbl=items&srcfld1=itemid&srcfld2=description".$host_condition."');",
				'T');
		
		$frmGItem->AddRow(S_PARAMETER ,array($txtCondVal,$btnSelect));

		if($graphtype == GRAPH_TYPE_NORMAL)
		{
			$cmbType = new CComboBox("type",$type,"submit()");
			$cmbType->AddItem(GRAPH_ITEM_SIMPLE, S_SIMPLE);
			$cmbType->AddItem(GRAPH_ITEM_AGGREGATED, S_AGGREGATED);
			$frmGItem->AddRow(S_TYPE, $cmbType);
		}
		else
		{
			$frmGItem->AddVar("type",GRAPH_ITEM_SIMPLE);
		}

		if($type == GRAPH_ITEM_AGGREGATED)
		{
			$frmGItem->AddRow(S_AGGREGATED_PERIODS_COUNT,	new CTextBox("periods_cnt",$periods_cnt,15)); 

			$frmGItem->AddVar("calc_fnc",$calc_fnc);
			$frmGItem->AddVar("drawtype",$drawtype);
			$frmGItem->AddVar("color",$color);
		}
		else
		{
			$frmGItem->AddVar("periods_cnt",$periods_cnt);

			$cmbFnc = new CComboBox("calc_fnc",$calc_fnc,'submit();');

			if($graphtype == GRAPH_TYPE_NORMAL)
				$cmbFnc->AddItem(CALC_FNC_ALL, S_ALL_SMALL);

			$cmbFnc->AddItem(CALC_FNC_MIN, S_MIN_SMALL);
			$cmbFnc->AddItem(CALC_FNC_AVG, S_AVG_SMALL);
			$cmbFnc->AddItem(CALC_FNC_MAX, S_MAX_SMALL);
			$frmGItem->AddRow(S_FUNCTION, $cmbFnc);

			if($graphtype == GRAPH_TYPE_NORMAL)
			{
				$cmbType = new CComboBox("drawtype",$drawtype);
				foreach( graph_item_drawtypes() as $i )
				{
					$cmbType->AddItem($i,graph_item_drawtype2str($i));
				}
				$frmGItem->AddRow(S_DRAW_STYLE, $cmbType);
			}
			else
			{
				$frmGItem->AddVar("drawtype", 1);
			}

			$frmGItem->AddRow(S_COLOR, new CColor('color',$color));
		}

		$cmbYax = new CComboBox("yaxisside",$yaxisside);
		$cmbYax->AddItem(GRAPH_YAXIS_SIDE_RIGHT, S_RIGHT);
		$cmbYax->AddItem(GRAPH_YAXIS_SIDE_LEFT,	S_LEFT);
		$frmGItem->AddRow(S_YAXIS_SIDE, $cmbYax);

		$frmGItem->AddRow(S_SORT_ORDER_1_100, new CTextBox("sortorder",$sortorder,3));

		$frmGItem->AddItemToBottomRow(new CButton("save", isset($gid) ? S_SAVE : S_ADD));

		$frmGItem->AddItemToBottomRow(new CButtonCancel(null,'close_window();'));
		$frmGItem->Show();
	}

	function	insert_value_mapping_form()
	{
		global $_REQUEST;

		$frmValmap = new CFormTable(S_VALUE_MAP);
		$frmValmap->SetHelp("web.mapping.php");
		$frmValmap->AddVar("config",get_request("config",6));

		if(isset($_REQUEST["valuemapid"]))
		{
			$frmValmap->AddVar("valuemapid",$_REQUEST["valuemapid"]);
			$db_valuemaps = DBselect("select * from valuemaps".
				" where valuemapid=".$_REQUEST["valuemapid"]);

			$db_valuemap = DBfetch($db_valuemaps);

			$frmValmap->SetTitle(S_VALUE_MAP." \"".$db_valuemap["name"]."\"");
		}

		if(isset($_REQUEST["valuemapid"]) && !isset($_REQUEST["form_refresh"]))
		{
			$valuemap = array();
			$mapname = $db_valuemap["name"];
			$mappings = DBselect("select * from mappings where valuemapid=".$_REQUEST["valuemapid"]);
			while($mapping = DBfetch($mappings))
			{
				$value = array(
					"value" => $mapping["value"],
					"newvalue" => $mapping["newvalue"]);

				array_push($valuemap, $value);
			}				
		}
		else
		{
			$mapname = get_request("mapname","");
			$valuemap = get_request("valuemap",array());
		}

		$frmValmap->AddRow(S_NAME, new CTextBox("mapname",$mapname,40));

		$i = 0;
		$valuemap_el = array();
		foreach($valuemap as $value)
		{
			array_push($valuemap_el,
				array(
					new CCheckBox("rem_value[]", 'no', null, $i),
					$value["value"].SPACE.RARR.SPACE.$value["newvalue"]
				),
				BR);
			$frmValmap->AddVar("valuemap[$i][value]",$value["value"]);
			$frmValmap->AddVar("valuemap[$i][newvalue]",$value["newvalue"]);
			$i++;
		}
		if(count($valuemap_el)==0)
			array_push($valuemap_el, S_NO_MAPPING_DEFINED);
		else
			array_push($valuemap_el, new CButton('del_map','delete selected'));

		$frmValmap->AddRow(S_MAPPING, $valuemap_el);
		$frmValmap->AddRow(S_NEW_MAPPING, array(
			new CTextBox("add_value","",10),
			new CSpan(RARR,"rarr"),
			new CTextBox("add_newvalue","",10),
			SPACE,
			new CButton("add_map",S_ADD)
			),'new');

		$frmValmap->AddItemToBottomRow(new CButton('save',S_SAVE));
		if(isset($_REQUEST["valuemapid"]))
		{
			$frmValmap->AddItemToBottomRow(SPACE);
			$frmValmap->AddItemToBottomRow(new CButtonDelete("Delete selected value mapping?",
				url_param("form").url_param("valuemapid").url_param("config")));
				
		} else {
		}
		$frmValmap->AddItemToBottomRow(SPACE);
		$frmValmap->AddItemToBottomRow(new CButtonCancel(url_param("config")));
	
		$frmValmap->Show();
	}

	function	insert_action_form()
	{

include_once 'include/discovery.inc.php';

		global  $_REQUEST;

		$uid=null;

		$frmAction = new CFormTable(S_ACTION,'actionconf.php','post');
		$frmAction->SetHelp('config_actions.php');

		if(isset($_REQUEST['actionid']))
		{
			$action = get_action_by_actionid($_REQUEST['actionid']);
			$frmAction->AddVar('actionid',$_REQUEST['actionid']);
		}

		$conditions	= get_request('conditions',array());
		$operations	= get_request("operations",array());
	
		if(isset($_REQUEST['actionid']) && !isset($_REQUEST['form_refresh']))
		{
			$name		= $action['name'];
			$eventsource	= $action['eventsource'];
			$evaltype	= $action['evaltype'];
			$status 	= $action['status'];

			/* prepare conditions */
			$db_conditions = DBselect('select conditiontype, operator, value from conditions'.
				' where actionid='.$_REQUEST['actionid'].' order by conditiontype,conditionid');

			while($condition_data = DBfetch($db_conditions))
			{
				$condition_data = array(
					'type' =>		$condition_data['conditiontype'],
					'operator' =>		$condition_data['operator'],
					'value' =>		$condition_data['value']);

				if(in_array($condition_data, $conditions)) continue;
				array_push($conditions, $condition_data);
			}
			unset($condition_data, $db_conditions);

			/* prepate operations */
			$db_operations = DBselect('select operationtype,object,objectid,shortdata,longdata from operations'.
				' where actionid='.$_REQUEST['actionid'].' order by operationtype,object,operationid');

			while($operation_data = DBfetch($db_operations))
			{
				$operation_data = array(
					'operationtype'	=> $operation_data['operationtype'],
					'object'	=> $operation_data['object'],
					'objectid'	=> $operation_data['objectid'],
					'shortdata'	=> $operation_data['shortdata'],
					'longdata'	=> $operation_data['longdata']);

				if(in_array($operation_data, $operations)) continue;
				array_push($operations, $operation_data);
			}
			unset($db_operations, $operation_data);
		}
		else
		{
			$name		= get_request('name');
			$eventsource	= get_request('eventsource');
			$evaltype	= get_request('evaltype');
			$status 	= get_request('status');
		}

		$allowed_conditions = get_conditions_by_eventsource($eventsource);
		$allowed_operations = get_operations_by_eventsource($eventsource);

		/* init new_condition variable */
		$new_condition = get_request('new_condition', array());
		if( !is_array($new_condition) )	$new_condition = array();

		if( !isset($new_condition['type']) )	$new_condition['type']		= CONDITION_TYPE_TRIGGER_NAME;
		if( !isset($new_condition['operator']))	$new_condition['operator']	= CONDITION_OPERATOR_LIKE;
		if( !isset($new_condition['value']) )	$new_condition['value']		= '';

		if( !in_array($new_condition['type'], $allowed_conditions) )
			$new_condition['type'] = $allowed_conditions[0];

		/* init new_operation variable */
		$new_operation = get_request('new_operation', array());
		if( !is_array($new_operation) ) $new_operation = array();

		if( !isset($new_operation['operationtype']))	$new_operation['operationtype']	= OPERATION_TYPE_MESSAGE;
		if( !isset($new_operation['object']))		$new_operation['object']	= OPERATION_OBJECT_GROUP;
		if( !isset($new_operation['objectid']))		$new_operation['objectid']	= 0;
		if( !isset($new_operation['shortdata']))	$new_operation['shortdata']	= '{TRIGGER.NAME}: {STATUS}';
		if( !isset($new_operation['longdata']))		$new_operation['longdata']	= '{TRIGGER.NAME}: {STATUS}';

		$frmAction->AddRow(S_NAME, new CTextBox('name', $name, 50));

		/* form row generation */
		$cmbSource =  new CComboBox('eventsource', $eventsource, 'submit()');
		$cmbSource->AddItem(EVENT_SOURCE_TRIGGERS, S_TRIGGERS);
		$cmbSource->AddItem(EVENT_SOURCE_DISCOVERY, S_DISCOVERY);
		$frmAction->AddRow(S_EVENT_SOURCE, $cmbSource);

// show CONDITION LIST
		zbx_rksort($conditions);

		/* group conditions by type */
		$grouped_conditions = array();
		$cond_el = new CTable(S_NO_CONDITIONS_DEFINED);
		$i=0;
		foreach($conditions as $val)
		{
			if( !isset($val['type']) )	$val['type'] = 0;
			if( !isset($val['operator']) )	$val['operator'] = 0;
			if( !isset($val['value']) )	$val['value'] = 0;

			if( !in_array($val["type"], $allowed_conditions) ) continue;

			$label = chr(ord('A') + $i);
			$cond_el->AddRow(array('('.$label.')',array(
					new CCheckBox("g_conditionid[]", 'no', null,$i),
					get_condition_desc($val["type"], $val["operator"], $val["value"]))
				));
				
			$frmAction->AddVar("conditions[$i][type]", 	$val["type"]);
			$frmAction->AddVar("conditions[$i][operator]", 	$val["operator"]);
			$frmAction->AddVar("conditions[$i][value]", 	$val["value"]);

			$grouped_conditions[$val["type"]][] = $label;

			$i++;
		}
		unset($conditions);

		$cond_buttons = array();

		if(!isset($_REQUEST['new_condition']))
		{
			$cond_buttons[] = new CButton('new_condition',S_NEW);
		}

		if($cond_el->ItemsCount() > 0)
		{
			if($cond_el->ItemsCount() > 1)
			{

				/* prepare condition calcuation type selector */
				switch($evaltype)
				{
					case ACTION_EVAL_TYPE_AND:	$group_op = 		$glog_op = S_AND;	break;
					case ACTION_EVAL_TYPE_OR:	$group_op = 		$glog_op = S_OR;	break;
					default:			$group_op = S_OR;	$glog_op = S_AND;	break;
				}

				foreach($grouped_conditions as $id => $val)
					$grouped_conditions[$id] = '('.implode(' '.$group_op.' ', $val).')';

				$grouped_conditions = implode(' '.$glog_op.' ', $grouped_conditions);

				$cmb_calc_type = new CComboBox('evaltype', $evaltype, 'submit()');
				$cmb_calc_type->AddItem(ACTION_EVAL_TYPE_AND_OR, S_AND_OR_BIG);
				$cmb_calc_type->AddItem(ACTION_EVAL_TYPE_AND, S_AND_BIG);
				$cmb_calc_type->AddItem(ACTION_EVAL_TYPE_OR, S_OR_BIG);
				$frmAction->AddRow(S_TYPE_OF_CALCULATION, 
					array($cmb_calc_type, new CTextBox('preview', $grouped_conditions, 60,'yes')));
				unset($cmb_calc_type, $group_op, $glog_op);
				/* end of calcuation type selector */
			}
			else
			{
				$frmAction->AddVar('evaltype', ACTION_EVAL_TYPE_AND_OR);
			}
			$cond_buttons[] = new CButton('del_condition',S_DELETE_SELECTED);
		}
		else
		{
			$frmAction->AddVar('evaltype', ACTION_EVAL_TYPE_AND_OR);
		}

		$frmAction->AddRow(S_CONDITIONS, array($cond_el, $cond_buttons)); 
		unset($grouped_conditions,$cond_el,$cond_buttons);

// end of CONDITION LIST

// NEW CONDITION
		if(isset($_REQUEST['new_condition']))
		{
			$rowCondition=array();

	// add condition type
			$cmbCondType = new CComboBox('new_condition[type]',$new_condition['type'],'submit()');
			foreach($allowed_conditions as $cond)
				$cmbCondType->AddItem($cond, condition_type2str($cond));

			array_push($rowCondition,$cmbCondType);

	// add condition operation
			$cmbCondOp = new CComboBox('new_condition[operator]');
			foreach(get_operators_by_conditiontype($new_condition['type']) as $op)
				$cmbCondOp->AddItem($op, condition_operator2str($op));

			array_push($rowCondition,$cmbCondOp);


	// add condition value
			switch($new_condition['type'])
			{
				case CONDITION_TYPE_HOST_GROUP:
					$frmAction->AddVar('new_condition[value]','0');
					$rowCondition[] = array(
						new CTextBox('group','',20,'yes'),
						new CButton('btn1',S_SELECT,
							"return PopUp('popup.php?dstfrm=".$frmAction->GetName().
							"&dstfld1=new_condition%5Bvalue%5D&dstfld2=group&srctbl=host_group".
							"&srcfld1=groupid&srcfld2=name',450,450);",
							'T')
						);
					break;
				case CONDITION_TYPE_HOST:
					$frmAction->AddVar('new_condition[value]','0');
					$rowCondition[] = array(
						new CTextBox('host','',20,'yes'),
						new CButton('btn1',S_SELECT,
							"return PopUp('popup.php?dstfrm=".$frmAction->GetName().
							"&dstfld1=new_condition%5Bvalue%5D&dstfld2=host&srctbl=hosts".
							"&srcfld1=hostid&srcfld2=host',450,450);",
							'T')
						);
					break;
				case CONDITION_TYPE_TRIGGER:
					$frmAction->AddVar('new_condition[value]','0');
					$rowCondition[] = array(
						new CTextBox('trigger','',20,'yes'),
						new CButton('btn1',S_SELECT,
							"return PopUp('popup.php?dstfrm=".$frmAction->GetName().
							"&dstfld1=new_condition%5Bvalue%5D&dstfld2=trigger&srctbl=triggers".
							"&srcfld1=triggerid&srcfld2=description');",
							'T')
						);
					break;
				case CONDITION_TYPE_TRIGGER_NAME:
					$rowCondition[] = new CTextBox('new_condition[value]', "", 40);
					break;
				case CONDITION_TYPE_TRIGGER_VALUE:
					$cmbCondVal = new CComboBox('new_condition[value]');
					foreach(array(TRIGGER_VALUE_FALSE, TRIGGER_VALUE_TRUE) as $tr_val)
						$cmbCondVal->AddItem($tr_val, trigger_value2str($tr_val));

					$rowCondition[] = $cmbCondVal;
					break;
				case CONDITION_TYPE_TIME_PERIOD:
					$rowCondition[] = new CTextBox('new_condition[value]', "1-7,00:00-23:59", 40);
					break;
				case CONDITION_TYPE_TRIGGER_SEVERITY:
					$cmbCondVal = new CComboBox('new_condition[value]');
					foreach(array(TRIGGER_SEVERITY_INFORMATION,
						TRIGGER_SEVERITY_WARNING,
						TRIGGER_SEVERITY_AVERAGE,
						TRIGGER_SEVERITY_HIGH,
						TRIGGER_SEVERITY_DISASTER) as $id)
						$cmbCondVal->AddItem($id,get_severity_description($id));

					$rowCondition[] = $cmbCondVal;
					break;
				case CONDITION_TYPE_DHOST_IP:
					$rowCondition[] = new CTextBox('new_condition[value]', '192.168.0.1-127,192.168.2.1', 50);
					break;
				case CONDITION_TYPE_DSERVICE_TYPE:
					$cmbCondVal = new CComboBox('new_condition[value]');
					foreach(array(SVC_SSH, SVC_LDAP, SVC_SMTP, SVC_FTP, SVC_HTTP,
						SVC_POP, SVC_NNTP, SVC_IMAP, SVC_TCP,SVC_AGENT,SVC_SNMPv1,SVC_SNMPv2) as $svc)
						$cmbCondVal->AddItem($svc,discovery_check_type2str($svc));

					$rowCondition[] = $cmbCondVal;
					break;
				case CONDITION_TYPE_DSERVICE_PORT:
					$rowCondition[] = new CTextBox('new_condition[value]', '0-1023,1024-49151', 40);
					break;
				case CONDITION_TYPE_DSTATUS:
					$cmbCondVal = new CComboBox('new_condition[value]');
					foreach(array(DOBJECT_STATUS_UP, DOBJECT_STATUS_DOWN) as $stat)
						$cmbCondVal->AddItem($stat,discovery_object_status2str($stat));

					$rowCondition[] = $cmbCondVal;
					break;
				case CONDITION_TYPE_DUPTIME:
					$rowCondition[] = new CNumericBox('new_condition[value]','600',15);
					break;
				case CONDITION_TYPE_DVALUE:
					$rowCondition[] = new CTextBox('new_condition[value]', "", 40);
					break;
			}

			$frmAction->AddRow(S_NEW_CONDITION, array(
				$rowCondition,
				BR,
				new CButton('add_condition',S_ADD),
				new CButton('cancel_new_condition',S_CANCEL)),
				'new');
// end of NEW CONDITION
		}

		zbx_rksort($operations);
		
		$oper_el = new CTable(S_NO_OPERATIONS_DEFINED);
		foreach($operations as $id => $val)
		{
			if( !in_array($val['operationtype'], $allowed_operations) )	continue;

			$oper_details = new CSpan(get_operation_desc(SHORT_DESCRITION, $val));
			$oper_details->SetHint(nl2br(get_operation_desc(LONG_DESCRITION, $val)));

			$oper_el->AddRow(array(
				new CCol(array(
					new CCheckBox("g_operationid[]", 'no', null,$id),
					$oper_details)),
					new CButton('edit_operationid['.$id.']',S_EDIT)
				));

			$frmAction->AddVar('operations['.$id.'][operationtype]'	,$val['operationtype']	);
			$frmAction->AddVar('operations['.$id.'][object]'	,$val['object']		);
			$frmAction->AddVar('operations['.$id.'][objectid]'	,$val['objectid']	);
			$frmAction->AddVar('operations['.$id.'][shortdata]'	,$val['shortdata']	);
			$frmAction->AddVar('operations['.$id.'][longdata]'	,$val['longdata']	);
		}
		unset($operations);

		$oper_buttons = array();

		if(!isset($_REQUEST['new_operation']))
		{
			$oper_buttons[] = new CButton('new_operation',S_NEW);
		}

		if($oper_el->ItemsCount() > 0 )
		{
			$oper_buttons[] =new CButton('del_operation',S_DELETE_SELECTED);
		}

// end of condition list preparation

		$frmAction->AddRow(S_OPERATIONS, array($oper_el, $oper_buttons));
		unset($oper_el, $oper_buttons);

		if(isset($_REQUEST['new_operation']))
		{
			unset($update_mode);
			if(isset($new_operation['id']))
			{
				$frmAction->AddVar('new_operation[id]', $new_operation['id']);
				$update_mode = true;
			}

			$tblNewOperation = new CTable(null,'nowrap');

			$cmbOpType = new CComboBox('new_operation[operationtype]', $new_operation['operationtype'],'submit()');
			foreach($allowed_operations as $oper)
				$cmbOpType->AddItem($oper, operation_type2str($oper));
			$tblNewOperation->AddRow(array(S_OPERATION_TYPE, $cmbOpType));

			switch($new_operation['operationtype'])
			{
				case OPERATION_TYPE_MESSAGE:
					if( $new_operation['object'] == OPERATION_OBJECT_GROUP)
					{
						$object_srctbl = 'usrgrp';
						$object_srcfld1 = 'usrgrpid';
						$object_name = get_group_by_usrgrpid($new_operation['objectid']);
						$display_name = 'name';
					}
					else
					{
						$object_srctbl = 'users';
						$object_srcfld1 = 'userid';
						$object_name = get_user_by_userid($new_operation['objectid']);
						$display_name = 'alias';
					}

					$frmAction->AddVar('new_operation[objectid]', $new_operation['objectid']); 

					if($object_name)	$object_name = $object_name[$display_name];

					$cmbObject = new CComboBox('new_operation[object]', $new_operation['object'],'submit()');
					$cmbObject->AddItem(OPERATION_OBJECT_USER,S_SINGLE_USER);
					$cmbObject->AddItem(OPERATION_OBJECT_GROUP,S_USER_GROUP);

					$tblNewOperation->AddRow(array(S_SEND_MESSAGE_TO, array(
							$cmbObject,
							new CTextBox('object_name', $object_name, 40, 'yes'),
							new CButton('select_object',S_SELECT,
								'return PopUp("popup.php?dstfrm='.$frmAction->GetName().
								'&dstfld1=new_operation%5Bobjectid%5D&dstfld2=object_name'.
								'&srctbl='.$object_srctbl.'&srcfld1='.$object_srcfld1.'&srcfld2='.$display_name.
								'",450,450)',
								'T')
						)));

					$tblNewOperation->AddRow(array(S_SUBJECT,
						new CTextBox('new_operation[shortdata]', $new_operation['shortdata'],77)));
					$tblNewOperation->AddRow(array(S_MESSAGE,
						new CTextArea('new_operation[longdata]', $new_operation['longdata'],77,7)));
					break;
				case OPERATION_TYPE_COMMAND:
					$frmAction->AddVar('new_operation[object]',0);
					$frmAction->AddVar('new_operation[objectid]',0);
					$frmAction->AddVar('new_operation[shortdata]','');

					$tblNewOperation->AddRow(array(S_REMOTE_COMMAND,
						new CTextArea('new_operation[longdata]', $new_operation['longdata'],77,7)));
					break;
				case OPERATION_TYPE_HOST_ADD:
					$frmAction->AddVar('new_operation[object]',0);
					$frmAction->AddVar('new_operation[objectid]',0);
					$frmAction->AddVar('new_operation[shortdata]','');
					$frmAction->AddVar('new_operation[longdata]','');
					break;
				case OPERATION_TYPE_HOST_REMOVE:
					$frmAction->AddVar('new_operation[object]',0);
					$frmAction->AddVar('new_operation[objectid]',0);
					$frmAction->AddVar('new_operation[shortdata]','');
					$frmAction->AddVar('new_operation[longdata]','');
					break;
				case OPERATION_TYPE_GROUP_ADD:
					$frmAction->AddVar('new_operation[object]',0);
					$frmAction->AddVar('new_operation[objectid]',$new_operation['objectid']);
					$frmAction->AddVar('new_operation[shortdata]','');
					$frmAction->AddVar('new_operation[longdata]','');

					if($object_name= DBfetch(DBselect('select name from groups where groupid='.$new_operation['objectid'])))
					{
						$object_name = $object_name['name'];
					}
					$tblNewOperation->AddRow(array(S_GROUP, array(
							new CTextBox('object_name', $object_name, 40, 'yes'),
							new CButton('select_object',S_SELECT,
								'return PopUp("popup.php?dstfrm='.$frmAction->GetName().
								'&dstfld1=new_operation%5Bobjectid%5D&dstfld2=object_name'.
								'&srctbl=host_group&srcfld1=groupid&srcfld2=name'.
								'",450,450)',
								'T')
						)));
					break;
				case OPERATION_TYPE_GROUP_REMOVE:
					$frmAction->AddVar('new_operation[object]',0);
					$frmAction->AddVar('new_operation[objectid]',$new_operation['objectid']);
					$frmAction->AddVar('new_operation[shortdata]','');
					$frmAction->AddVar('new_operation[longdata]','');

					if($object_name= DBfetch(DBselect('select name from groups where groupid='.$new_operation['objectid'])))
					{
						$object_name = $object_name['name'];
					}
					$tblNewOperation->AddRow(array(S_GROUP, array(
							new CTextBox('object_name', $object_name, 40, 'yes'),
							new CButton('select_object',S_SELECT,
								'return PopUp("popup.php?dstfrm='.$frmAction->GetName().
								'&dstfld1=new_operation%5Bobjectid%5D&dstfld2=object_name'.
								'&srctbl=host_group&srcfld1=groupid&srcfld2=name'.
								'",450,450)',
								'T')
						)));
					break;
				case OPERATION_TYPE_TEMPLATE_ADD:
					$frmAction->AddVar('new_operation[object]',0);
					$frmAction->AddVar('new_operation[objectid]',$new_operation['objectid']);
					$frmAction->AddVar('new_operation[shortdata]','');
					$frmAction->AddVar('new_operation[longdata]','');

					if($object_name= DBfetch(DBselect('select host from hosts '.
						' where status='.HOST_STATUS_TEMPLATE.' and hostid='.$new_operation['objectid'])))
					{
						$object_name = $object_name['host'];
					}
					$tblNewOperation->AddRow(array(S_TEMPLATE, array(
							new CTextBox('object_name', $object_name, 40, 'yes'),
							new CButton('select_object',S_SELECT,
								'return PopUp("popup.php?dstfrm='.$frmAction->GetName().
								'&dstfld1=new_operation%5Bobjectid%5D&dstfld2=object_name'.
								'&srctbl=host_templates&srcfld1=hostid&srcfld2=host'.
								'",450,450)',
								'T')
						)));
					break;
				case OPERATION_TYPE_TEMPLATE_REMOVE:
					$frmAction->AddVar('new_operation[object]',0);
					$frmAction->AddVar('new_operation[objectid]',$new_operation['objectid']);
					$frmAction->AddVar('new_operation[shortdata]','');
					$frmAction->AddVar('new_operation[longdata]','');

					if($object_name= DBfetch(DBselect('select host from hosts '.
						' where status='.HOST_STATUS_TEMPLATE.' and hostid='.$new_operation['objectid'])))
					{
						$object_name = $object_name['host'];
					}
					$tblNewOperation->AddRow(array(S_TEMPLATE, array(
							new CTextBox('object_name', $object_name, 40, 'yes'),
							new CButton('select_object',S_SELECT,
								'return PopUp("popup.php?dstfrm='.$frmAction->GetName().
								'&dstfld1=new_operation%5Bobjectid%5D&dstfld2=object_name'.
								'&srctbl=host_templates&srcfld1=hostid&srcfld2=host'.
								'",450,450)',
								'T')
						)));
					break;
			}

			$tblNewOperation->AddRow(new CCol(array(
				new CButton('add_operation', isset($update_mode) ? S_SAVE : S_ADD ),
				SPACE,
				new CButton('cancel_new_operation',S_CANCEL)
				)));

			$frmAction->AddRow(
				isset($update_mode) ? S_EDIT_OPERATION : S_NEW_OPERATION,
				$tblNewOperation,
				isset($update_mode) ? 'edit' : 'new'
				);
		}

		$cmbStatus = new CComboBox('status',$status);
		$cmbStatus->AddItem(ACTION_STATUS_ENABLED,S_ENABLED);
		$cmbStatus->AddItem(ACTION_STATUS_DISABLED,S_DISABLED);
		$frmAction->AddRow(S_STATUS, $cmbStatus);

		$frmAction->AddItemToBottomRow(new CButton('save',S_SAVE));
		if(isset($_REQUEST["actionid"]))
		{
			$frmAction->AddItemToBottomRow(SPACE);
			$frmAction->AddItemToBottomRow(new CButton('clone',S_CLONE));
			$frmAction->AddItemToBottomRow(SPACE);
			$frmAction->AddItemToBottomRow(new CButtonDelete(S_DELETE_SELECTED_ACTION_Q,
						url_param('form').url_param('eventsource').
						url_param('actionid')));
				
		}
		$frmAction->AddItemToBottomRow(SPACE);
		$frmAction->AddItemToBottomRow(new CButtonCancel(url_param("actiontype")));
	
		$frmAction->Show();
	}

	function	insert_media_type_form()
	{
		global $_REQUEST;

		$type		= get_request("type",0);
		$description	= get_request("description","");
		$smtp_server	= get_request("smtp_server","localhost");
		$smtp_helo	= get_request("smtp_helo","localhost");
		$smtp_email	= get_request("smtp_email","zabbix@localhost");
		$exec_path	= get_request("exec_path","");
		$gsm_modem	= get_request("gsm_modem","/dev/ttyS0");
		$username	= get_request("username","user@server");
		$password	= get_request("password","");

		if(isset($_REQUEST["mediatypeid"]) && !isset($_REQUEST["form_refresh"]))
		{
			$result = DBselect("select * from media_type where mediatypeid=".$_REQUEST["mediatypeid"]);

			$row = DBfetch($result);
			$mediatypeid	= $row["mediatypeid"];
			$type		= get_request("type",$row["type"]);
			$description	= $row["description"];
			$smtp_server	= $row["smtp_server"];
			$smtp_helo	= $row["smtp_helo"];
			$smtp_email	= $row["smtp_email"];
			$exec_path	= $row["exec_path"];
			$gsm_modem	= $row["gsm_modem"];
			$username	= $row["username"];
			$password	= $row["passwd"];
		}

		$frmMeadia = new CFormTable(S_MEDIA);
		$frmMeadia->SetHelp("web.config.medias.php");

		if(isset($_REQUEST["mediatypeid"]))
		{
			$frmMeadia->AddVar("mediatypeid",$_REQUEST["mediatypeid"]);
		}

		$frmMeadia->AddRow(S_DESCRIPTION,new CTextBox("description",$description,30));
		$cmbType = new CComboBox("type",$type,"submit()");
		$cmbType->AddItem(ALERT_TYPE_EMAIL,S_EMAIL);
		$cmbType->AddItem(ALERT_TYPE_JABBER,S_JABBER);
		$cmbType->AddItem(ALERT_TYPE_SMS,S_SMS);
		$cmbType->AddItem(ALERT_TYPE_EXEC,S_SCRIPT);
		$frmMeadia->AddRow(S_TYPE,$cmbType);

		switch($type)
		{
		case ALERT_TYPE_EMAIL:
			$frmMeadia->AddRow(S_SMTP_SERVER,new CTextBox("smtp_server",$smtp_server,30));
			$frmMeadia->AddRow(S_SMTP_HELO,new CTextBox("smtp_helo",$smtp_helo,30));
			$frmMeadia->AddRow(S_SMTP_EMAIL,new CTextBox("smtp_email",$smtp_email,30));
			break;
		case ALERT_TYPE_SMS:
			$frmMeadia->AddRow(S_GSM_MODEM,new CTextBox("gsm_modem",$gsm_modem,50));
			break;
		case ALERT_TYPE_EXEC:
			$frmMeadia->AddRow(S_SCRIPT_NAME,new CTextBox("exec_path",$exec_path,50));
			break;
		case ALERT_TYPE_JABBER:
			$frmMeadia->AddRow(S_JABBER_IDENTIFIER, new CTextBox("username",$username,30));
			$frmMeadia->AddRow(S_PASSWORD, new CPassBox("password",$password,30));
		}

		$frmMeadia->AddItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["mediatypeid"]))
		{
			$frmMeadia->AddItemToBottomRow(SPACE);
			$frmMeadia->AddItemToBottomRow(new CButtonDelete(S_DELETE_SELECTED_MEDIA,
				url_param("form").url_param("mediatypeid")));
		}
		$frmMeadia->AddItemToBottomRow(SPACE);
		$frmMeadia->AddItemToBottomRow(new CButtonCancel());
		$frmMeadia->Show();
	}

	function	insert_image_form()
	{
		global $_REQUEST;

		$frmImages = new CFormTable(S_IMAGE,"config.php","post","multipart/form-data");
		$frmImages->SetHelp("web.config.images.php");
		$frmImages->AddVar("config",get_request("config",3));

		if(isset($_REQUEST["imageid"]))
		{
			$result=DBselect("select imageid,imagetype,name from images".
				" where imageid=".$_REQUEST["imageid"]);

			$row=DBfetch($result);
			$frmImages->SetTitle(S_IMAGE." \"".$row["name"]."\"");
			$frmImages->AddVar("imageid",$_REQUEST["imageid"]);
		}

		if(isset($_REQUEST["imageid"]) && !isset($_REQUEST["form_refresh"]))
		{
			$name		= $row["name"];
			$imagetype	= $row["imagetype"];
			$imageid	= $row["imageid"];
		}
		else
		{
			$name		= get_request("name","");
			$imagetype	= get_request("imagetype",1);
			$imageid	= get_request("imageid",0);
		}

		$frmImages->AddRow(S_NAME,new CTextBox("name",$name,64));
	
		$cmbImg = new CComboBox("imagetype",$imagetype);
		$cmbImg->AddItem(1,S_ICON);
		$cmbImg->AddItem(2,S_BACKGROUND);
		$frmImages->AddRow(S_TYPE,$cmbImg);

		$frmImages->AddRow(S_UPLOAD,new CFile("image"));

		if($imageid > 0)
		{
			$frmImages->AddRow(S_IMAGE,new CLink(
				new CImg("image.php?width=640&height=480&imageid=".$imageid,"no image",null),
				"image.php?imageid=".$row["imageid"]));
		}

		$frmImages->AddItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["imageid"]))
		{
			$frmImages->AddItemToBottomRow(SPACE);
			$frmImages->AddItemToBottomRow(new CButtonDelete(S_DELETE_SELECTED_IMAGE,
				url_param("form").url_param("config").url_param("imageid")));
		}
		$frmImages->AddItemToBottomRow(SPACE);
		$frmImages->AddItemToBottomRow(new CButtonCancel(url_param("config")));
		$frmImages->Show();
	}

	function insert_screen_form()
	{
		global $_REQUEST;

		$frm_title = S_SCREEN;
		if(isset($_REQUEST["screenid"]))
		{
			$result=DBselect("select screenid,name,hsize,vsize from screens g".
				" where screenid=".$_REQUEST["screenid"]);
			$row=DBfetch($result);
			$frm_title = S_SCREEN." \"".$row["name"]."\"";
		}
		if(isset($_REQUEST["screenid"]) && !isset($_REQUEST["form_refresh"]))
		{
			$name=$row["name"];
			$hsize=$row["hsize"];
			$vsize=$row["vsize"];
		}
		else
		{
			$name=get_request("name","");
			$hsize=get_request("hsize",1);
			$vsize=get_request("bsize",1);
		}
		$frmScr = new CFormTable($frm_title,"screenconf.php");
		$frmScr->SetHelp("web.screenconf.screen.php");

		$frmScr->AddVar('config', 0);

		if(isset($_REQUEST["screenid"]))
		{
			$frmScr->AddVar("screenid",$_REQUEST["screenid"]);
		}
		$frmScr->AddRow(S_NAME, new CTextBox("name",$name,32));
		$frmScr->AddRow(S_COLUMNS, new CNumericBox("hsize",$hsize,3));
		$frmScr->AddRow(S_ROWS, new CNumericBox("vsize",$vsize,3));

		$frmScr->AddItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["screenid"]))
		{
			/* $frmScr->AddItemToBottomRow(SPACE);
			$frmScr->AddItemToBottomRow(new CButton('clone',S_CLONE)); !!! TODO */
			$frmScr->AddItemToBottomRow(SPACE);
			$frmScr->AddItemToBottomRow(new CButtonDelete(S_DELETE_SCREEN_Q,
				url_param("form").url_param("screenid")));
		}
		$frmScr->AddItemToBottomRow(SPACE);
		$frmScr->AddItemToBottomRow(new CButtonCancel());
		$frmScr->Show();	
	}

	function&	get_screen_item_form()
	{
		global $_REQUEST;
		global $USER_DETAILS;

		$form = new CFormTable(S_SCREEN_CELL_CONFIGURATION,"screenedit.php#form");
		$form->SetHelp("web.screenedit.cell.php");

		if(isset($_REQUEST["screenitemid"]))
		{
			$iresult=DBSelect("select * from screens_items".
				" where screenid=".$_REQUEST["screenid"].
				" and screenitemid=".$_REQUEST["screenitemid"]);

			$form->AddVar("screenitemid",$_REQUEST["screenitemid"]);
		} else {
			$form->AddVar("x",$_REQUEST["x"]);
			$form->AddVar("y",$_REQUEST["y"]);
		}

		if(isset($_REQUEST["screenitemid"]) && !isset($_REQUEST["form_refresh"]))
		{
			$irow = DBfetch($iresult);
			$resourcetype	= $irow["resourcetype"];
			$resourceid	= $irow["resourceid"];
			$width		= $irow["width"];
			$height		= $irow["height"];
			$colspan	= $irow["colspan"];
			$rowspan	= $irow["rowspan"];
			$elements	= $irow["elements"];
			$valign		= $irow["valign"];
			$halign		= $irow["halign"];
			$style		= $irow["style"];
			$url		= $irow["url"];
		}
		else
		{
			$resourcetype	= get_request("resourcetype",	0);
			$resourceid	= get_request("resourceid",	0);
			$width		= get_request("width",		500);
			$height		= get_request("height",		100);
			$colspan	= get_request("colspan",	0);
			$rowspan	= get_request("rowspan",	0);
			$elements	= get_request("elements",	25);
			$valign		= get_request("valign",		VALIGN_DEFAULT);
			$halign		= get_request("halign",		HALIGN_DEFAULT);
			$style		= get_request("style",		0);
			$url		= get_request("url",		"");
		}

		$form->AddVar("screenid",$_REQUEST["screenid"]);

		$cmbRes = new CCombobox("resourcetype",$resourcetype,"submit()");
		$cmbRes->AddItem(SCREEN_RESOURCE_GRAPH,		S_GRAPH);
		$cmbRes->AddItem(SCREEN_RESOURCE_SIMPLE_GRAPH,	S_SIMPLE_GRAPH);
		$cmbRes->AddItem(SCREEN_RESOURCE_PLAIN_TEXT,	S_PLAIN_TEXT);
		$cmbRes->AddItem(SCREEN_RESOURCE_MAP,		S_MAP);
		$cmbRes->AddItem(SCREEN_RESOURCE_SCREEN,	S_SCREEN);
		$cmbRes->AddItem(SCREEN_RESOURCE_SERVER_INFO,	S_SERVER_INFO);
		$cmbRes->AddItem(SCREEN_RESOURCE_HOSTS_INFO,	S_HOSTS_INFO);
		$cmbRes->AddItem(SCREEN_RESOURCE_TRIGGERS_INFO,	S_TRIGGERS_INFO);
		$cmbRes->AddItem(SCREEN_RESOURCE_TRIGGERS_OVERVIEW,	S_TRIGGERS_OVERVIEW);
		$cmbRes->AddItem(SCREEN_RESOURCE_DATA_OVERVIEW,		S_DATA_OVERVIEW);
		$cmbRes->AddItem(SCREEN_RESOURCE_CLOCK,		S_CLOCK);
		$cmbRes->AddItem(SCREEN_RESOURCE_URL,		S_URL);
		$cmbRes->AddItem(SCREEN_RESOURCE_ACTIONS,	S_HISTORY_OF_ACTIONS);
                $cmbRes->AddItem(SCREEN_RESOURCE_EVENTS,       S_HISTORY_OF_EVENTS);
		$form->AddRow(S_RESOURCE,$cmbRes);

		if($resourcetype == SCREEN_RESOURCE_GRAPH)
		{
	// User-defined graph
			$result = DBselect("select distinct g.graphid,g.name,n.name as node_name, h.host".
				" from graphs g left join graphs_items gi on g.graphid=gi.graphid left join items i on gi.itemid=i.itemid ".
				" left join hosts h on h.hostid=i.hostid left join nodes n on n.nodeid=".DBid2nodeid("g.graphid").
				" where i.hostid not in (".get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,PERM_MODE_LT).")".
				" order by node_name,host,name,graphid"
				);

			$cmbGraphs = new CComboBox("resourceid",$resourceid);
			while($row=DBfetch($result))
			{
				$row["node_name"] = isset($row["node_name"]) ? "(".$row["node_name"].") " : '';
				$name = $row["node_name"].$row["host"].":".$row["name"];
				$cmbGraphs->AddItem($row["graphid"],$name);
			}

			$form->AddRow(S_GRAPH_NAME,$cmbGraphs);
		}
		elseif($resourcetype == SCREEN_RESOURCE_SIMPLE_GRAPH)
		{
	// Simple graph
			$result=DBselect("select n.name as node_name,h.host,i.description,i.itemid,i.key_ from hosts h,items i ".
				" left join nodes n on n.nodeid=".DBid2nodeid("i.itemid").
				" where h.hostid=i.hostid ".
				" and h.status=".HOST_STATUS_MONITORED." and i.status=".ITEM_STATUS_ACTIVE.
				" and i.hostid not in (".get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,PERM_MODE_LT).")".
				" order by node_name,h.host,i.description");


			$cmbItems = new CCombobox("resourceid",$resourceid);
			while($row=DBfetch($result))
			{
				$description_=item_description($row["description"],$row["key_"]);
				$row["node_name"] = isset($row["node_name"]) ? "(".$row["node_name"].") " : '';
				$cmbItems->AddItem($row["itemid"],$row["node_name"].$row["host"].": ".$description_);

			}
			$form->AddRow(S_PARAMETER,$cmbItems);
		}
		elseif($resourcetype == SCREEN_RESOURCE_MAP)
		{
	// Map
			$result=DBselect("select n.name as node_name, s.sysmapid,s.name from sysmaps s".
				" left join nodes n on n.nodeid=".DBid2nodeid("s.sysmapid").
				" order by name ");

			$cmbMaps = new CComboBox("resourceid",$resourceid);
			while($row=DBfetch($result))
			{
				if(!sysmap_accessiable($row["sysmapid"],PERM_READ_ONLY)) continue;
				$row["node_name"] = isset($row["node_name"]) ? "(".$row["node_name"].") " : '';
				$cmbMaps->AddItem($row["sysmapid"],$row["node_name"].$row["name"]);
			}

			$form->AddRow(S_MAP,$cmbMaps);
		}
		elseif($resourcetype == SCREEN_RESOURCE_PLAIN_TEXT)
		{
	// Plain text
			$result=DBselect("select n.name as node_name,h.host,i.description,i.itemid,i.key_ from hosts h,items i".
				" left join nodes n on n.nodeid=".DBid2nodeid("i.itemid").
				" where h.hostid=i.hostid and h.status=".HOST_STATUS_MONITORED." and i.status=".ITEM_STATUS_ACTIVE.
				" and i.hostid not in (".get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,PERM_MODE_LT).")".
				" order by node_name,h.host,i.description");

			$cmbHosts = new CComboBox("resourceid",$resourceid);
			while($row=DBfetch($result))
			{
				$description_=item_description($row["description"],$row["key_"]);
				$row["node_name"] = isset($row["node_name"]) ? "(".$row["node_name"].") " : '';
				$cmbHosts->AddItem($row["itemid"],$row["node_name"].$row["host"].": ".$description_);

			}

			$form->AddRow(S_PARAMETER,$cmbHosts);
			$form->AddRow(S_SHOW_LINES, new CNumericBox("elements",$elements,2));
		}
                elseif($resourcetype == SCREEN_RESOURCE_ACTIONS)
                {
        // History of actions
                        $form->AddRow(S_SHOW_LINES, new CNumericBox("elements",$elements,2));
			$form->AddVar("resourceid",0);
                }
                elseif($resourcetype == SCREEN_RESOURCE_EVENTS)
                {
        // History of events
                        $form->AddRow(S_SHOW_LINES, new CNumericBox("elements",$elements,2));
                        $form->AddVar("resourceid",0);
                }
		elseif(in_array($resourcetype,array(SCREEN_RESOURCE_TRIGGERS_OVERVIEW,SCREEN_RESOURCE_DATA_OVERVIEW)))
		{
	// Overiews
			$cmbGroup = new CComboBox("resourceid",$resourceid);

			$cmbGroup->AddItem(0,S_ALL_SMALL);
			$result=DBselect("select distinct n.name as node_name,g.groupid,g.name from hosts_groups hg,hosts h,groups g ".
				" left join nodes n on n.nodeid=".DBid2nodeid("g.groupid").
				" where g.groupid in (".get_accessible_groups_by_user($USER_DETAILS,PERM_READ_ONLY).")".
				" and g.groupid=hg.groupid and hg.hostid=h.hostid and h.status=".HOST_STATUS_MONITORED.
				" order by node_name,g.name");
			while($row=DBfetch($result))
			{
				$row["node_name"] = isset($row["node_name"]) ? "(".$row["node_name"].") " : '';
				$cmbGroup->AddItem($row["groupid"],$row["node_name"].$row["name"]);
			}
			$form->AddRow(S_GROUP,$cmbGroup);

		}
		elseif($resourcetype == SCREEN_RESOURCE_SCREEN)
		{
			$cmbScreens = new CComboBox("resourceid",$resourceid);
			$result=DBselect("select distinct n.name as node_name,s.screenid,s.name from screens s".
				" left join nodes n on n.nodeid=".DBid2nodeid("s.screenid").
				" order by node_name,s.name");
			while($row=DBfetch($result))
			{
				if(!screen_accessiable($row["screenid"], PERM_READ_ONLY)) continue;
				if(check_screen_recursion($_REQUEST["screenid"],$row["screenid"]))
					continue;
				$row["node_name"] = isset($row["node_name"]) ? "(".$row["node_name"].") " : '';
				$cmbScreens->AddItem($row["screenid"],$row["node_name"].$row["name"]);

			}

			$form->AddRow(S_SCREEN,$cmbScreens);
		}
		else // SCREEN_RESOURCE_HOSTS_INFO,  SCREEN_RESOURCE_TRIGGERS_INFO,  SCREEN_RESOURCE_CLOCK
		{
			$form->AddVar("resourceid",0);
		}

		if(in_array($resourcetype,array(SCREEN_RESOURCE_HOSTS_INFO,SCREEN_RESOURCE_TRIGGERS_INFO)))
		{
			$cmbStyle = new CComboBox("style", $style);
			$cmbStyle->AddItem(STYLE_HORISONTAL,	S_HORISONTAL);
			$cmbStyle->AddItem(STYLE_VERTICAL,	S_VERTICAL);
			$form->AddRow(S_STYLE,	$cmbStyle);
		}
		elseif($resourcetype == SCREEN_RESOURCE_CLOCK)
		{
			$cmbStyle = new CComboBox("style", $style);
			$cmbStyle->AddItem(TIME_TYPE_LOCAL,	S_LOCAL_TIME);
			$cmbStyle->AddItem(TIME_TYPE_SERVER,	S_SERVER_TIME);
			$form->AddRow(S_TIME_TYPE,	$cmbStyle);
		}
		else
		{
			$form->AddVar("style",	0);
		}

		if(in_array($resourcetype,array(SCREEN_RESOURCE_URL)))
		{
			$form->AddRow(S_URL, new CTextBox("url",$url,60));
		}
		else
		{
			$form->AddVar("url",	"");
		}

		if(in_array($resourcetype,array(SCREEN_RESOURCE_GRAPH,SCREEN_RESOURCE_SIMPLE_GRAPH,SCREEN_RESOURCE_CLOCK,SCREEN_RESOURCE_URL)))
		{
			$form->AddRow(S_WIDTH,	new CNumericBox("width",$width,5));
			$form->AddRow(S_HEIGHT,	new CNumericBox("height",$height,5));
		}
		else
		{
			$form->AddVar("width",	0);
			$form->AddVar("height",	0);
		}

		if(in_array($resourcetype,array(SCREEN_RESOURCE_GRAPH,SCREEN_RESOURCE_SIMPLE_GRAPH,SCREEN_RESOURCE_MAP,
			SCREEN_RESOURCE_CLOCK,SCREEN_RESOURCE_URL)))
		{
			$cmbHalign = new CComboBox("halign",$halign);
			$cmbHalign->AddItem(HALIGN_CENTER,	S_CENTER);
			$cmbHalign->AddItem(HALIGN_LEFT,	S_LEFT);
			$cmbHalign->AddItem(HALIGN_RIGHT,	S_RIGHT);
			$form->AddRow(S_HORISONTAL_ALIGN,	$cmbHalign);
		}
		else
		{
			$form->AddVar("halign",	0);
		}

		$cmbValign = new CComboBox("valign",$valign);
		$cmbValign->AddItem(VALIGN_MIDDLE,	S_MIDDLE);
		$cmbValign->AddItem(VALIGN_TOP,		S_TOP);
		$cmbValign->AddItem(VALIGN_BOTTOM,	S_BOTTOM);
		$form->AddRow(S_VERTICAL_ALIGN,	$cmbValign);

		$form->AddRow(S_COLUMN_SPAN,	new CNumericBox("colspan",$colspan,2));
		$form->AddRow(S_ROW_SPAN,	new CNumericBox("rowspan",$rowspan,2));

		$form->AddItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["screenitemid"]))
		{
			$form->AddItemToBottomRow(SPACE);
			$form->AddItemToBottomRow(new CButtonDelete(null,
				url_param("form").url_param("screenid").url_param("screenitemid")));
		}
		$form->AddItemToBottomRow(SPACE);
		$form->AddItemToBottomRow(new CButtonCancel(url_param("screenid")));
		return $form;
	}

	function	insert_media_form()
	{	/* NOTE: only NEW media is acessed */

		global $_REQUEST;

		$severity	= get_request("severity",array(0,1,2,3,4,5));
		$sendto		= get_request("sendto","");
		$mediatypeid	= get_request("mediatypeid",0);
		$active		= get_request("active",0);
		$period		= get_request("period","1-7,00:00-23:59");

		$frmMedia = new CFormTable(S_NEW_MEDIA);
		$frmMedia->SetHelp("web.media.php");

		$frmMedia->AddVar("dstfrm",$_REQUEST["dstfrm"]);

		$cmbType = new CComboBox("mediatypeid",$mediatypeid);
		$types=DBselect("select mediatypeid,description from media_type".
				' where '.DBin_node('mediatypeid').' order by type');
		while($type=DBfetch($types))
		{
			$cmbType->AddItem(
					$type["mediatypeid"],
					get_node_name_by_elid($type["mediatypeid"]).$type["description"]
					);
		}
		$frmMedia->AddRow(S_TYPE,$cmbType);

		$frmMedia->AddRow(S_SEND_TO,new CTextBox("sendto",$sendto,20));	
		$frmMedia->AddRow(S_WHEN_ACTIVE,new CTextBox("period",$period,48));	
	
		$frm_row = array();
		for($i=0; $i<=5; $i++){
			array_push($frm_row, 
				array(
					new CCheckBox(
						"severity[]",
						in_array($i,$severity)?'yes':'no', 
						null,		/* action */
						$i),		/* value */
					get_severity_description($i)
				),
				BR);
		}
		$frmMedia->AddRow(S_USE_IF_SEVERITY,$frm_row);

		$cmbStat = new CComboBox("active",$active);
		$cmbStat->AddItem(0,S_ENABLED);
		$cmbStat->AddItem(1,S_DISABLED);
		$frmMedia->AddRow("Status",$cmbStat);
	
		$frmMedia->AddItemToBottomRow(new CButton("add", S_ADD));
		$frmMedia->AddItemToBottomRow(SPACE);
		$frmMedia->AddItemToBottomRow(new CButtonCancel(null, 'close_window();'));
		$frmMedia->Show();
	}

	function	insert_housekeeper_form()
	{
		$config=select_config();
		
		$frmHouseKeep = new CFormTable(S_HOUSEKEEPER,"config.php");
		$frmHouseKeep->SetHelp("web.config.housekeeper.php");
		$frmHouseKeep->AddVar("config",get_request("config",0));

		$frmHouseKeep->AddRow(S_DO_NOT_KEEP_ACTIONS_OLDER_THAN,
			new CNumericBox("alert_history",$config["alert_history"],5));

		$frmHouseKeep->AddRow(S_DO_NOT_KEEP_EVENTS_OLDER_THAN,
			new CNumericBox("event_history",$config["event_history"],5));

		$frmHouseKeep->AddItemToBottomRow(new CButton("save",S_SAVE));
		$frmHouseKeep->Show();
	}

	function	insert_work_period_form()
	{
		$config=select_config();
		
		$frmHouseKeep = new CFormTable(S_WORKING_TIME,"config.php");
		$frmHouseKeep->SetHelp("web.config.workperiod.php");
		$frmHouseKeep->AddVar("config",get_request("config",7));

		$frmHouseKeep->AddRow(S_WORKING_TIME,
			new CTextBox("work_period",$config["work_period"],35));

		$frmHouseKeep->AddItemToBottomRow(new CButton("save",S_SAVE));
		$frmHouseKeep->Show();
	}

	function	insert_event_ack_form()
	{
		$config=select_config();
		
		$frmEventAck = new CFormTable(S_ACKNOWLEDGES,"config.php");
//		$frmEventAck->SetHelp("web.config.workperiod.php");
		$frmEventAck->AddVar("config",get_request("config",8));

		$exp_select = new CComboBox('ack_enable');

		$exp_select->AddItem(EVENT_ACK_ENABLED,S_ENABLED,$config['ack_enable']?'yes':'no');
		$exp_select->AddItem(EVENT_ACK_DISABLED,S_DISABLED,$config['ack_enable']?'no':'yes');

		$frmEventAck->AddRow(S_EVENT_ACKNOWLEDGES,$exp_select);
			
		$frmEventAck->AddRow(S_SHOW_EVENTS_NOT_OLDER.SPACE.'('.S_DAYS.')',
			new CTextBox('ack_expire',$config['ack_expire'],5));

		$frmEventAck->AddItemToBottomRow(new CButton("save",S_SAVE));
		$frmEventAck->Show();
	}

	function	insert_other_parameters_form()
	{
		$config=select_config();
		
		$frmHouseKeep = new CFormTable(S_OTHER_PARAMETERS,'config.php');
		$frmHouseKeep->SetHelp('web.config.other.php');
		$frmHouseKeep->AddVar('config',get_request('config',5));
		
		$frmHouseKeep->AddRow(S_REFRESH_UNSUPPORTED_ITEMS,
			new CNumericBox('refresh_unsupported',$config['refresh_unsupported'],5));

		$cmbUsrGrp = new CComboBox('alert_usrgrpid', $config['alert_usrgrpid']);
		$cmbUsrGrp->AddItem(0, S_NONE);
		$result=DBselect('select usrgrpid,name from usrgrp'.
				' where '.DBin_node('usrgrpid').
				' order by name');
		while($row=DBfetch($result))
			$cmbUsrGrp->AddItem(
					$row['usrgrpid'],
					get_node_name_by_elid($row['usrgrpid']).$row['name']
					);
		$frmHouseKeep->AddRow(S_USER_GROUP_FOR_DATABASE_DOWN_MESSAGE,$cmbUsrGrp);

		$frmHouseKeep->AddItemToBottomRow(new CButton('save',S_SAVE));
		$frmHouseKeep->Show();
	}

	function	insert_host_form($show_only_tmp=0)
	{
		global $USER_DETAILS;
		global $_REQUEST;

		$groups= get_request("groups",array());

		$newgroup	= get_request("newgroup","");

		$host 	= get_request("host",	"");
		$port 	= get_request("port",	get_profile("HOST_PORT",10050));
		$status	= get_request("status",	HOST_STATUS_MONITORED);
		$useip	= get_request("useip",	0);
		$dns	= get_request("dns",	"");
		$ip	= get_request("ip",	"0.0.0.0");

		$useprofile = get_request("useprofile","no");

		$devicetype	= get_request("devicetype","");
		$name		= get_request("name","");
		$os		= get_request("os","");
		$serialno	= get_request("serialno","");
		$tag		= get_request("tag","");
		$macaddress	= get_request("macaddress","");
		$hardware	= get_request("hardware","");
		$software	= get_request("software","");
		$contact	= get_request("contact","");
		$location	= get_request("location","");
		$notes		= get_request("notes","");

		$templates	= get_request("templates",array());
		$clear_templates = get_request('clear_templates',array());

		$frm_title	= $show_only_tmp ? S_TEMPLATE : S_HOST;

		if(isset($_REQUEST["hostid"]))
		{
			$db_host=get_host_by_hostid($_REQUEST["hostid"]);
			$frm_title	.= SPACE."\"".$db_host["host"]."\"";

			$original_templates = get_templates_by_hostid($_REQUEST["hostid"]);
		}
		else
		{
			$original_templates = array();
		}

		if(isset($_REQUEST["hostid"]) && !isset($_REQUEST["form_refresh"]))
		{

			$host	= $db_host["host"];
			$port	= $db_host["port"];
			$status	= $db_host["status"];
			$useip	= $db_host["useip"];
			$dns	= $db_host["dns"];
			$ip	= $db_host["ip"];

// add groups
			$db_groups=DBselect("select distinct groupid from hosts_groups where hostid=".$_REQUEST["hostid"].
				" and groupid in (".
				get_accessible_groups_by_user($USER_DETAILS,PERM_READ_LIST,null,null,get_current_nodeid()).
				") ");
			while($db_group=DBfetch($db_groups)){
				if(in_array($db_group["groupid"],$groups)) continue;
				array_push($groups, $db_group["groupid"]);
			}
// read profile
			$db_profiles = DBselect("select * from hosts_profiles where hostid=".$_REQUEST["hostid"]);

			$useprofile = "no";
			$db_profile = DBfetch($db_profiles);
			if($db_profile)
			{
				$useprofile = "yes";


				$devicetype	= $db_profile["devicetype"];
				$name		= $db_profile["name"];
				$os		= $db_profile["os"];
				$serialno	= $db_profile["serialno"];
				$tag		= $db_profile["tag"];
				$macaddress	= $db_profile["macaddress"];
				$hardware	= $db_profile["hardware"];
				$software	= $db_profile["software"];
				$contact	= $db_profile["contact"];
				$location	= $db_profile["location"];
				$notes		= $db_profile["notes"];
			}
			$templates = $original_templates;
		}

		$clear_templates = array_intersect($clear_templates, array_keys($original_templates));
		$clear_templates = array_diff($clear_templates,array_keys($templates));
		asort($templates);

		$frmHost = new CFormTable($frm_title,"hosts.php");
		$frmHost->SetHelp("web.hosts.host.php");
		$frmHost->AddVar("config",get_request("config",0));

		$frmHost->AddVar('clear_templates',$clear_templates);

		if(isset($_REQUEST["hostid"]))		$frmHost->AddVar("hostid",$_REQUEST["hostid"]);
		if(isset($_REQUEST["groupid"]))		$frmHost->AddVar("groupid",$_REQUEST["groupid"]);
		
		$frmHost->AddRow(S_NAME,new CTextBox("host",$host,20));

		$frm_row = array();
		
		$db_groups=DBselect("select distinct groupid,name from groups ".
			" where groupid in (".
			get_accessible_groups_by_user($USER_DETAILS,PERM_READ_LIST,null,null,get_current_nodeid()).
			") order by name");
		while($db_group=DBfetch($db_groups))
		{
			array_push($frm_row,
				array(
					new CCheckBox("groups[]",
						in_array($db_group["groupid"],$groups) ? 'yes' : 'no', 
						null,
						$db_group["groupid"]
						),
					get_node_name_by_elid($db_group["groupid"]).$db_group["name"]
				),
				BR);
		}
		$frmHost->AddRow(S_GROUPS,$frm_row);

		$frmHost->AddRow(S_NEW_GROUP,new CTextBox("newgroup",$newgroup),'new');

// onChange does not work on some browsers: MacOS, KDE browser
		if($show_only_tmp)
		{
			$frmHost->AddVar("useip",0);
			$frmHost->AddVar("ip","0.0.0.0");
			$frmHost->AddVar("dns","");
		}
		else
		{
			$frmHost->AddRow(S_DNS_NAME,new CTextBox("dns",$dns,"40"));
			$frmHost->AddRow(S_IP_ADDRESS,new CTextBox("ip",$ip,"15"));

			$cmbConnectBy = new CComboBox('useip', $useip);
			$cmbConnectBy->AddItem(0, S_DNS_NAME);
			$cmbConnectBy->AddItem(1, S_IP_ADDRESS);
			$frmHost->AddRow(S_CONNECT_TO,$cmbConnectBy);
		}


		if($show_only_tmp)
		{
			$port = "10050";
			$status = HOST_STATUS_TEMPLATE;

			$frmHost->AddVar("port",$port);
			$frmHost->AddVar("status",$status);
		}
		else
		{
			$frmHost->AddRow(S_PORT,new CNumericBox("port",$port,5));	

			$cmbStatus = new CComboBox("status",$status);
			$cmbStatus->AddItem(HOST_STATUS_MONITORED,	S_MONITORED);
			$cmbStatus->AddItem(HOST_STATUS_NOT_MONITORED,	S_NOT_MONITORED);
			$frmHost->AddRow(S_STATUS,$cmbStatus);	
		}

		$template_table = new CTable();
		$template_table->SetCellPadding(0);
		$template_table->SetCellSpacing(0);

		foreach($templates as $id => $temp_name)
		{
			$frmHost->AddVar('templates['.$id.']',$temp_name);
			$template_table->AddRow(array(
					$temp_name,
					new CButton('unlink['.$id.']',S_UNLINK),
					isset($original_templates[$id]) ? new CButton('unlink_and_clear['.$id.']',S_UNLINK_AND_CLEAR) : SPACE
					)
				);
		}

		$frmHost->AddRow(S_LINK_WITH_TEMPLATE, array($template_table,
				new CButton('add_template',S_ADD,
					"return PopUp('popup.php?dstfrm=".$frmHost->GetName().
					"&dstfld1=new_template&srctbl=templates&srcfld1=hostid&srcfld2=host".
					url_param($templates,false,'existed_templates')."',450,450)",
					'T')
				));
	
		if($show_only_tmp)
		{
			$useprofile = "no";
			$frmHost->AddVar("useprofile",$useprofile);
		}
		else
		{
			$frmHost->AddRow(S_USE_PROFILE,new CCheckBox("useprofile",$useprofile,"submit()"));
		}
		if($useprofile=="yes")
		{
			$frmHost->AddRow(S_DEVICE_TYPE,new CTextBox("devicetype",$devicetype,61));
			$frmHost->AddRow(S_NAME,new CTextBox("name",$name,61));
			$frmHost->AddRow(S_OS,new CTextBox("os",$os,61));
			$frmHost->AddRow(S_SERIALNO,new CTextBox("serialno",$serialno,61));
			$frmHost->AddRow(S_TAG,new CTextBox("tag",$tag,61));
			$frmHost->AddRow(S_MACADDRESS,new CTextBox("macaddress",$macaddress,61));
			$frmHost->AddRow(S_HARDWARE,new CTextArea("hardware",$hardware,60,4));
			$frmHost->AddRow(S_SOFTWARE,new CTextArea("software",$software,60,4));
			$frmHost->AddRow(S_CONTACT,new CTextArea("contact",$contact,60,4));
			$frmHost->AddRow(S_LOCATION,new CTextArea("location",$location,60,4));
			$frmHost->AddRow(S_NOTES,new CTextArea("notes",$notes,60,4));
		}
		else
		{
			$frmHost->AddVar("devicetype",	$devicetype);
			$frmHost->AddVar("name",	$name);
			$frmHost->AddVar("os",		$os);
			$frmHost->AddVar("serialno",	$serialno);
			$frmHost->AddVar("tag",		$tag);
			$frmHost->AddVar("macaddress",	$macaddress);
			$frmHost->AddVar("hardware",	$hardware);
			$frmHost->AddVar("software",	$software);
			$frmHost->AddVar("contact",	$contact);
			$frmHost->AddVar("location",	$location);
			$frmHost->AddVar("notes",	$notes);
		}

		$frmHost->AddItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["hostid"]))
		{
			$frmHost->AddItemToBottomRow(SPACE);
			$frmHost->AddItemToBottomRow(new CButton("clone",S_CLONE));
			$frmHost->AddItemToBottomRow(SPACE);
			$frmHost->AddItemToBottomRow(
				new CButtonDelete(S_DELETE_SELECTED_HOST_Q,
					url_param("form").url_param("config").url_param("hostid").
					url_param("groupid")
				)
			);

			if($show_only_tmp)
			{
				$frmHost->AddItemToBottomRow(SPACE);
				$frmHost->AddItemToBottomRow(
					new CButtonQMessage('delete_and_clear',
						'Delete and clear',
                                        	S_DELETE_SELECTED_HOSTS_Q,
						url_param("form").url_param("config").url_param("hostid").
						url_param("groupid")
					)
				);
			}
		}
		$frmHost->AddItemToBottomRow(SPACE);
		$frmHost->AddItemToBottomRow(new CButtonCancel(url_param("config").url_param("groupid")));
		$frmHost->Show();
	}

	# Insert form for Host Groups
	function	insert_hostgroups_form()
	{
		global  $_REQUEST;
		global	$USER_DETAILS;

		$hosts = get_request("hosts",array());
		$frm_title = S_HOST_GROUP;
		if(isset($_REQUEST["groupid"]))
		{
			$group = get_hostgroup_by_groupid($_REQUEST["groupid"]);
			$frm_title = S_HOST_GROUP." \"".$group["name"]."\"";
		}
		if(isset($_REQUEST["groupid"]) && !isset($_REQUEST["form_refresh"]))
		{
			$name=$group["name"];
			$db_hosts=DBselect("select distinct h.hostid,host from hosts h, hosts_groups hg".
				" where h.status<>".HOST_STATUS_DELETED.
				" and h.hostid=hg.hostid".
				" and hg.groupid=".$_REQUEST["groupid"].
				" order by host");
			while($db_host=DBfetch($db_hosts))
			{
				if(in_array($db_host["hostid"],$hosts)) continue;
				array_push($hosts, $db_host["hostid"]);
			}
		}
		else
		{
			$name=get_request("gname","");
		}
		$frmHostG = new CFormTable($frm_title,"hosts.php");
		$frmHostG->SetHelp("web.hosts.group.php");
		$frmHostG->AddVar("config",get_request("config",1));
		if(isset($_REQUEST["groupid"]))
		{
			$frmHostG->AddVar("groupid",$_REQUEST["groupid"]);
		}

		$frmHostG->AddRow(S_GROUP_NAME,new CTextBox("gname",$name,30));

		$cmbHosts = new CListBox("hosts[]",$hosts,10);
		$db_hosts=DBselect("select distinct hostid,host from hosts".
			" where status<>".HOST_STATUS_DELETED.
			" and hostid in (".get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,null,null,get_current_nodeid()).")".
			" order by host");
		while($db_host=DBfetch($db_hosts))
		{
			$cmbHosts->AddItem(
					$db_host["hostid"],
					get_node_name_by_elid($db_host["hostid"]).$db_host["host"]
					);
		}
		$frmHostG->AddRow(S_HOSTS,$cmbHosts);

		$frmHostG->AddItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["groupid"]))
		{
			$frmHostG->AddItemToBottomRow(SPACE);
			$frmHostG->AddItemToBottomRow(new CButton("clone",S_CLONE));
			$frmHostG->AddItemToBottomRow(SPACE);
			$frmHostG->AddItemToBottomRow(
				new CButtonDelete("Delete selected group?",
					url_param("form").url_param("config").url_param("groupid")
				)
			);
		}
		$frmHostG->AddItemToBottomRow(SPACE);
		$frmHostG->AddItemToBottomRow(new CButtonCancel(url_param("config")));
		$frmHostG->Show();
	}

	# Insert host profile ReadOnly form
	function	insert_host_profile_form()
	{
		global $_REQUEST;

		$frmHostP = new CFormTable(S_HOST_PROFILE);
		$frmHostP->SetHelp("web.host_profile.php");

		$result=DBselect("select * from hosts_profiles where hostid=".$_REQUEST["hostid"]);

		$row=DBfetch($result);
		if($row)
		{

			$devicetype=$row["devicetype"];
			$name=$row["name"];
			$os=$row["os"];
			$serialno=$row["serialno"];
			$tag=$row["tag"];
			$macaddress=$row["macaddress"];
			$hardware=$row["hardware"];
			$software=$row["software"];
			$contact=$row["contact"];
			$location=$row["location"];
			$notes=$row["notes"];

			$frmHostP->AddRow(S_DEVICE_TYPE,new CTextBox("devicetype",$devicetype,61,'yes'));
			$frmHostP->AddRow(S_NAME,new CTextBox("name",$name,61,'yes'));
			$frmHostP->AddRow(S_OS,new CTextBox("os",$os,61,'yes'));
			$frmHostP->AddRow(S_SERIALNO,new CTextBox("serialno",$serialno,61,'yes'));
			$frmHostP->AddRow(S_TAG,new CTextBox("tag",$tag,61,'yes'));
			$frmHostP->AddRow(S_MACADDRESS,new CTextBox("macaddress",$macaddress,61,'yes'));
			$frmHostP->AddRow(S_HARDWARE,new CTextArea("hardware",$hardware,60,4,'yes'));
			$frmHostP->AddRow(S_SOFTWARE,new CTextArea("software",$software,60,4,'yes'));
			$frmHostP->AddRow(S_CONTACT,new CTextArea("contact",$contact,60,4,'yes'));
			$frmHostP->AddRow(S_LOCATION,new CTextArea("location",$location,60,4,'yes'));
			$frmHostP->AddRow(S_NOTES,new CTextArea("notes",$notes,60,4,'yes'));
		}
		else
		{
			$frmHostP->AddSpanRow("Profile for this host is missing","form_row_c");
		}
		$frmHostP->AddItemToBottomRow(new CButtonCancel(url_param("groupid")));
		$frmHostP->Show();
	}

	function insert_application_form()
	{
		global $_REQUEST;

		$frm_title = "New Application";

		if(isset($_REQUEST["applicationid"]))
		{
			$result=DBselect("select * from applications where applicationid=".$_REQUEST["applicationid"]);
			$row=DBfetch($result);
			$frm_title = "Application: \"".$row["name"]."\"";
		}
		if(isset($_REQUEST["applicationid"]) && !isset($_REQUEST["form_refresh"]))
		{
			$appname = $row["name"];
			$apphostid = $row["hostid"];
		}
		else
		{
			$appname = get_request("appname","");
			$apphostid = get_request("apphostid",get_request("hostid",0));
		}

		$db_host = get_host_by_hostid($apphostid,1 /* no error message */);
		if($db_host)
		{
			$apphost = $db_host["host"];
		}
		else
		{
			$apphost = "";
			$apphostid = 0;
		}

		$frmApp = new CFormTable($frm_title);
		$frmApp->SetHelp("web.applications.php");

		if(isset($_REQUEST["applicationid"]))
			$frmApp->AddVar("applicationid",$_REQUEST["applicationid"]);

		$frmApp->AddRow(S_NAME,new CTextBox("appname",$appname,32));

		$frmApp->AddVar("apphostid",$apphostid);

		if(!isset($_REQUEST["applicationid"]))
		{ // anly new application can select host
			$frmApp->AddRow(S_HOST,array(
				new CTextBox("apphost",$apphost,32,'yes'),
				new CButton("btn1",S_SELECT,
					"return PopUp('popup.php?dstfrm=".$frmApp->GetName().
					"&dstfld1=apphostid&dstfld2=apphost&srctbl=hosts&srcfld1=hostid&srcfld2=host',450,450);",
					'T')
				));
		}

		$frmApp->AddItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["applicationid"]))
		{
			$frmApp->AddItemToBottomRow(SPACE);
			$frmApp->AddItemToBottomRow(new CButtonDelete("Delete this application?",
					url_param("config").url_param("hostid").url_param("groupid").
					url_param("form").url_param("applicationid")));
		}
		$frmApp->AddItemToBottomRow(SPACE);
		$frmApp->AddItemToBottomRow(new CButtonCancel(url_param("config").url_param("hostid").url_param("groupid")));

		$frmApp->Show();

	}

	function insert_map_form()
	{
		global $_REQUEST;

		$frm_title = "New system map";

		if(isset($_REQUEST["sysmapid"]))
		{
			$result=DBselect("select * from sysmaps where sysmapid=".$_REQUEST["sysmapid"]);
			$row=DBfetch($result);
			$frm_title = "System map: \"".$row["name"]."\"";
		}
		if(isset($_REQUEST["sysmapid"]) && !isset($_REQUEST["form_refresh"]))
		{
			$name		= $row["name"];
			$width		= $row["width"];
			$height		= $row["height"];
			$backgroundid	= $row["backgroundid"];
			$label_type	= $row["label_type"];
			$label_location	= $row["label_location"];
		}
		else
		{
			$name		= get_request("name","");
			$width		= get_request("width",800);
			$height		= get_request("height",600);
			$backgroundid	= get_request("backgroundid",0);
			$label_type	= get_request("label_type",0);
			$label_location	= get_request("label_location",0);
		}


		$frmMap = new CFormTable($frm_title,"sysmaps.php");
		$frmMap->SetHelp("web.sysmaps.map.php");

		if(isset($_REQUEST["sysmapid"]))
			$frmMap->AddVar("sysmapid",$_REQUEST["sysmapid"]);

		$frmMap->AddRow(S_NAME,new CTextBox("name",$name,32));
		$frmMap->AddRow(S_WIDTH,new CNumericBox("width",$width,5));
		$frmMap->AddRow(S_HEIGHT,new CNumericBox("height",$height,5));

		$cmbImg = new CComboBox("backgroundid",$backgroundid);
		$cmbImg->AddItem(0,"No image...");
		$result=DBselect('select * from images where imagetype=2 and '.DBin_node('imageid').' order by name');
		while($row=DBfetch($result))
		{
			$cmbImg->AddItem(
					$row["imageid"],
					get_node_name_by_elid($row["imageid"]).$row["name"]
					);
		}
		$frmMap->AddRow(S_BACKGROUND_IMAGE,$cmbImg);

		$cmbLabel = new CComboBox("label_type",$label_type);
		$cmbLabel->AddItem(0,S_LABEL);
		$cmbLabel->AddItem(1,S_IP_ADDRESS);
		$cmbLabel->AddItem(2,S_ELEMENT_NAME);
		$cmbLabel->AddItem(3,S_STATUS_ONLY);
		$cmbLabel->AddItem(4,S_NOTHING);
		$frmMap->AddRow(S_ICON_LABEL_TYPE,$cmbLabel);

		$cmbLocation = new CComboBox("label_location",$label_location);

		$cmbLocation->AddItem(0,S_BOTTOM);
		$cmbLocation->AddItem(1,S_LEFT);
		$cmbLocation->AddItem(2,S_RIGHT);
		$cmbLocation->AddItem(3,S_TOP);
		$frmMap->AddRow(S_ICON_LABEL_LOCATION,$cmbLocation);

		$frmMap->AddItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["sysmapid"]))
		{
			$frmMap->AddItemToBottomRow(SPACE);
			$frmMap->AddItemToBottomRow(new CButtonDelete("Delete system map?",
					url_param("form").url_param("sysmapid")));
		}
		$frmMap->AddItemToBottomRow(SPACE);
		$frmMap->AddItemToBottomRow(new CButtonCancel());

		$frmMap->Show();
		
	}

	function insert_map_element_form()
	{
		global $USER_DETAILS;

		$frmEl = new CFormTable("New map element","sysmap.php");
		$frmEl->SetHelp("web.sysmap.host.php");
		$frmEl->AddVar("sysmapid",$_REQUEST["sysmapid"]);

		if(isset($_REQUEST["selementid"]))
		{
			$frmEl->AddVar("selementid",$_REQUEST["selementid"]);

			$element = get_sysmaps_element_by_selementid($_REQUEST["selementid"]);
			$frmEl->SetTitle("Map element \"".$element["label"]."\"");
		}

		if(isset($_REQUEST["selementid"]) && !isset($_REQUEST["form_refresh"]))
		{
			$elementid	= $element["elementid"];
			$elementtype	= $element["elementtype"];
			$label		= $element["label"];
			$x		= $element["x"];
			$y		= $element["y"];
			$url		= $element["url"];
			$iconid_off	= $element["iconid_off"];
			$iconid_on	= $element["iconid_on"];
			$iconid_unknown	= $element["iconid_unknown"];
			$label_location	= $element["label_location"];
			if(is_null($label_location)) $label_location = -1;
		}
		else
		{
			$elementid 	= get_request("elementid", 	0);
			$elementtype	= get_request("elementtype", 	SYSMAP_ELEMENT_TYPE_HOST);
			$label		= get_request("label",		"");
			$x		= get_request("x",		0);
			$y		= get_request("y",		0);
			$url		= get_request("url",		"");
			$iconid_off	= get_request("iconid_off",	0);
			$iconid_on	= get_request("iconid_on",	0);
			$iconid_unknown	= get_request("iconid_unknown",	0);
			$label_location	= get_request("label_location",	"-1");
		}

		$cmbType = new CComboBox("elementtype",$elementtype,"submit()");

		$denyed_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,PERM_MODE_LT);
		$allowed_groups = get_accessible_groups_by_user($USER_DETAILS,PERM_READ_ONLY);
		
		$db_hosts = DBselect("select distinct n.name as node_name,h.hostid,h.host from hosts h".
			" left join nodes n on n.nodeid=".DBid2nodeid("h.hostid").
			" where h.hostid not in(".$denyed_hosts.")".
			" order by node_name,h.host");
		if($db_hosts)
			$cmbType->AddItem(SYSMAP_ELEMENT_TYPE_HOST,	S_HOST);

		$db_maps = DBselect("select sysmapid from sysmaps where sysmapid!=".$_REQUEST["sysmapid"]);
		if(DBfetch($db_maps))
			$cmbType->AddItem(SYSMAP_ELEMENT_TYPE_MAP,	S_MAP);

		$cmbType->AddItem(SYSMAP_ELEMENT_TYPE_TRIGGER,		S_TRIGGER);
		$cmbType->AddItem(SYSMAP_ELEMENT_TYPE_HOST_GROUP,	S_HOST_GROUP);

		$frmEl->AddRow(S_TYPE,$cmbType);

		$frmEl->AddRow("Label", new CTextBox("label", $label, 32));

		$cmbLocation = new CComboBox("label_location",$label_location);
		$cmbLocation->AddItem(-1,'-');
		$cmbLocation->AddItem(0,S_BOTTOM);
		$cmbLocation->AddItem(1,S_LEFT);
		$cmbLocation->AddItem(2,S_RIGHT);
		$cmbLocation->AddItem(3,S_TOP);
		$frmEl->AddRow(S_LABEL_LOCATION,$cmbLocation);

		if($elementtype==SYSMAP_ELEMENT_TYPE_HOST) 
		{
			$host = "";

			$host_info = DBfetch(DBselect("select distinct n.name as node_name,h.hostid,h.host from hosts h ".
				" left join nodes n on n.nodeid=".DBid2nodeid("h.hostid").
				" where h.hostid not in(".$denyed_hosts.") and  hostid=".$elementid.
				" order by node_name,h.host"));
			if($host_info)
				$host = $host_info["host"];
			else
				$elementid=0;

			if($elementid==0)
			{
				$host = "";
				$elementid = 0;
			}

			$frmEl->AddVar("elementid",$elementid);
			$frmEl->AddRow(S_HOST, array(
				new CTextBox("host",$host,32,'yes'),
				new CButton("btn1",S_SELECT,"return PopUp('popup.php?dstfrm=".$frmEl->GetName().
					"&dstfld1=elementid&dstfld2=host&srctbl=hosts&srcfld1=hostid&srcfld2=host',450,450);",
					"T")
			));
		}
		elseif($elementtype==SYSMAP_ELEMENT_TYPE_MAP)
		{
			$cmbMaps = new CComboBox("elementid",$elementid);
			$db_maps = DBselect('select distinct n.name as node_name,s.sysmapid,s.name from sysmaps s'.
					' left join nodes n on n.nodeid='.DBid2nodeid("s.sysmapid").
					' order by node_name,s.name');
			while($db_map = DBfetch($db_maps))
			{
				if(!sysmap_accessiable($db_map["sysmapid"],PERM_READ_ONLY)) continue;
				$node_name = isset($db_map['node_name']) ? '('.$db_map['node_name'].') ' : '';
				$cmbMaps->AddItem($db_map["sysmapid"],$node_name.$db_map["name"]);
			}
			$frmEl->AddRow(S_MAP, $cmbMaps);
		}
		elseif($elementtype==SYSMAP_ELEMENT_TYPE_TRIGGER)
		{
			$trigger = "";
			$trigger_info = DBfetch(DBselect("select distinct n.name as node_name,h.hostid,h.host,t.*".
				" from triggers t left join functions f on t.triggerid=f.triggerid ".
				" left join items i on i.itemid=f.itemid left join hosts h on h.hostid=i.hostid ".
				" left join nodes n on n.nodeid=".DBid2nodeid("t.triggerid").
				" where h.hostid not in (".$denyed_hosts.") and t.triggerid=".$elementid.
				" order by node_name,h.host,t.description"));
			
			if($trigger_info)
				$trigger = expand_trigger_description_by_data($trigger_info);
			else
				$elementid=0;

			if($elementid==0)
			{
				$trigger = "";
				$elementid = 0;
			}

			$frmEl->AddVar("elementid",$elementid);
			$frmEl->AddRow(S_TRIGGER, array(
				new CTextBox("trigger",$trigger,32,'yes'),
				new CButton("btn1",S_SELECT,"return PopUp('popup.php?dstfrm=".$frmEl->GetName().
					"&dstfld1=elementid&dstfld2=trigger&srctbl=triggers&srcfld1=triggerid&srcfld2=description');",
					"T")
			));
		}
		elseif($elementtype==SYSMAP_ELEMENT_TYPE_HOST_GROUP)
		{
			$group = "";

			$group_info = DBfetch(DBselect("select distinct n.name as node_name,g.groupid,g.name from groups g ".
				" left join nodes n on n.nodeid=".DBid2nodeid("g.groupid").
				" where g.groupid in (".$allowed_groups.") and g.groupid=".$elementid.
				" order by node_name,g.name"));

			if($group_info)
				$group = $group_info["name"];
			else
				$elementid=0;

			if($elementid==0)
			{
				$group = "";
				$elementid = 0;
			}

			$frmEl->AddVar("elementid",$elementid);
			$frmEl->AddRow(S_HOST_GROUP, array(
				new CTextBox("group",$group,32,'yes'),
				new CButton("btn1",S_SELECT,"return PopUp('popup.php?dstfrm=".$frmEl->GetName().
					"&dstfld1=elementid&dstfld2=group&srctbl=host_group&srcfld1=groupid&srcfld2=name',450,450);",
					"T")
			));
		}

		$cmbIconOff	= new CComboBox("iconid_off",$iconid_off);
		$cmbIconOn	= new CComboBox("iconid_on",$iconid_on);
		$cmbIconUnknown	= new CComboBox("iconid_unknown",$iconid_unknown);
		$result = DBselect('select * from images where imagetype=1 and '.DBin_node('imageid').' order by name');
		while($row=DBfetch($result))
		{
			$row["name"] = get_node_name_by_elid($row["imageid"]).$row["name"];

			$cmbIconOff->AddItem($row["imageid"],$row["name"]);
			$cmbIconOn->AddItem($row["imageid"],$row["name"]);
			$cmbIconUnknown->AddItem($row["imageid"],$row["name"]);
		}
		$frmEl->AddRow(S_ICON_OFF,$cmbIconOff);
		$frmEl->AddRow(S_ICON_ON,$cmbIconOn);
		$frmEl->AddRow(S_ICON_UNKNOWN,$cmbIconUnknown);

		$frmEl->AddRow("Coordinate X", new CNumericBox("x", $x, 5));
		$frmEl->AddRow("Coordinate Y", new CNumericBox("y", $y, 5));
		$frmEl->AddRow(S_URL, new CTextBox("url", $url, 64));

		$frmEl->AddItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["selementid"]))
		{
			$frmEl->AddItemToBottomRow(SPACE);
			$frmEl->AddItemToBottomRow(new CButtonDelete("Delete element?",url_param("form").
				url_param("selementid").url_param("sysmapid")));
		}
		$frmEl->AddItemToBottomRow(SPACE);
		$frmEl->AddItemToBottomRow(new CButtonCancel(url_param("sysmapid")));

		$frmEl->Show();
	}

	function insert_map_link_form()
	{
		global $_REQUEST;

		$frmCnct = new CFormTable("New connector","sysmap.php");
		$frmCnct->SetHelp("web.sysmap.connector.php");
		$frmCnct->AddVar("sysmapid",$_REQUEST["sysmapid"]);

		if(isset($_REQUEST["linkid"]))
		{
			$frmCnct->AddVar("linkid",$_REQUEST["linkid"]);
			$db_links = DBselect("select * from sysmaps_links where linkid=".$_REQUEST["linkid"]);
			$db_link = DBfetch($db_links);
		}

		if(isset($_REQUEST["linkid"]) && !isset($_REQUEST["form_refresh"]))
		{
			$selementid1	= $db_link["selementid1"];
			$selementid2	= $db_link["selementid2"];
			$triggerid	= $db_link["triggerid"];
			$drawtype_off	= $db_link["drawtype_off"];
			$drawtype_on	= $db_link["drawtype_on"];
			$color_off	= $db_link["color_off"];
			$color_on	= $db_link["color_on"];

			if(is_null($triggerid)) $triggerid = 0;
		}
		else
		{
			$selementid1	= get_request("selementid1",	0);
			$selementid2	= get_request("selementid2",	0);
			$triggerid	= get_request("triggerid",	0);
			$drawtype_off	= get_request("drawtype_off",	0);
			$drawtype_on	= get_request("drawtype_on",	0);
			$color_off	= get_request("color_off",	0);
			$color_on	= get_request("color_on",	0);
		}

/* START comboboxes preparations */
		$cmbElements1 = new CComboBox("selementid1",$selementid1);
		$cmbElements2 = new CComboBox("selementid2",$selementid2);
		$db_selements = DBselect("select selementid,label,elementid,elementtype from sysmaps_elements".
			" where sysmapid=".$_REQUEST["sysmapid"]);
		while($db_selement = DBfetch($db_selements))
		{
			$label = $db_selement["label"];
			if($db_selement["elementtype"] == SYSMAP_ELEMENT_TYPE_HOST)
			{
				$db_host = get_host_by_hostid($db_selement["elementid"]);
				$label .= ":".$db_host["host"];
			}
			elseif($db_selement["elementtype"] == SYSMAP_ELEMENT_TYPE_MAP)
			{
				$db_map = get_sysmap_by_sysmapid($db_selement["elementid"]);
				$label .= ":".$db_map["name"];
			}
			elseif($db_selement["elementtype"] == SYSMAP_ELEMENT_TYPE_TRIGGER)
			{
				if($db_selement["elementid"]>0)
				{
					$label .= ":".expand_trigger_description($db_selement["elementid"]);
				}
			}
			elseif($db_selement["elementtype"] == SYSMAP_ELEMENT_TYPE_HOST_GROUP)
			{
				if($db_selement["elementid"]>0)
				{
					$db_group = DBfetch(DBselect('select name from groups where groupid='.$db_selement["elementid"]));
					$label .= ":".$db_group['name'];
				}
			}
			$cmbElements1->AddItem($db_selement["selementid"],$label);
			$cmbElements2->AddItem($db_selement["selementid"],$label);
		}

		$cmbType_off = new CComboBox("drawtype_off",$drawtype_off);
		$cmbType_on = new CComboBox("drawtype_on",$drawtype_on);
		foreach(map_link_drawtypes() as $i)
		{
			$value = map_link_drawtype2str($i);
			$cmbType_off->AddItem($i, $value);
			$cmbType_on->AddItem($i, $value);
		}

		
		$cmbColor_off = new CComboBox("color_off",$color_off);
		$cmbColor_on = new CComboBox("color_on",$color_on);
		foreach(array('Black','Blue','Cyan','Dark Blue','Dark Green',
			'Dark Red','Dark Yellow','Green','Red','White','Yellow') as $value)
		{
			$cmbColor_off->AddItem($value, $value);
			$cmbColor_on->AddItem($value, $value);
		}
/* END preparation */

		$frmCnct->AddRow("Element 1",$cmbElements1);
		$frmCnct->AddRow("Element 2",$cmbElements2);

		$frmCnct->AddVar('triggerid',$triggerid);

		if($triggerid > 0)
			$trigger = expand_trigger_description($triggerid);
		else
			$trigger = "";

		$txtTrigger = new CTextBox('trigger',$trigger,60,'yes');

		$btnSelect = new CButton('btn1',S_SELECT,
			"return PopUp('popup.php?dstfrm=".$frmCnct->GetName().
			"&dstfld1=triggerid&dstfld2=trigger&srctbl=triggers&srcfld1=triggerid&srcfld2=description');",
			'T');
		$frmCnct->AddRow("Link status indicator",array($txtTrigger, $btnSelect));

		$frmCnct->AddRow("Type (OFF)",$cmbType_off);
		$frmCnct->AddRow("Color (OFF)",$cmbColor_off);

		$frmCnct->AddRow("Type (ON)",$cmbType_on);
		$frmCnct->AddRow("Color (ON)",$cmbColor_on);

		$frmCnct->AddItemToBottomRow(new CButton("save_link",S_SAVE));
		if(isset($_REQUEST["linkid"]))
		{
			$frmCnct->AddItemToBottomRow(SPACE);
			$frmCnct->AddItemToBottomRow(new CButtonDelete("Delete link?",
				url_param("linkid").url_param("sysmapid")));
		}
		$frmCnct->AddItemToBottomRow(SPACE);
		$frmCnct->AddItemToBottomRow(new CButtonCancel(url_param("sysmapid")));
		
		$frmCnct->Show();
	}

	function insert_command_result_form($scriptid,$hostid)
	{
		$result = execute_script($scriptid,$hostid);

		$script_info = DBfetch(DBselect("select name from scripts where scriptid=$scriptid"));

		$frmResult = new CFormTable($script_info["name"].': '.script_make_command($scriptid,$hostid));
		$message = $result["message"];
		if($result["flag"] != 0) {
			error($message);
			$message = "";
		}
		$frmResult->AddRow(S_RESULT,new CTextArea("message",$message,100,25,'yes'));
		$frmResult->AddItemToBottomRow(new CButton('close',S_CLOSE,'close_window();'));

		$frmResult->Show();
	}
?>
