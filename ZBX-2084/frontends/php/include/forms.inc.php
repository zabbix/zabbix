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
// TODO !!! Correct the help links !!! TODO
	require_once('include/users.inc.php');

	function insert_slideshow_form(){
		$form = new CFormTable(S_SLIDESHOW, null, 'post');
		$form->setHelp('config_advanced.php');

		$form->addVar('config', 1);

		if(isset($_REQUEST['slideshowid'])){
			$form->addVar('slideshowid', $_REQUEST['slideshowid']);
		}

		$name		= get_request('name', '');
		$delay		= get_request('delay', 5);
		$steps		= get_request('steps', array());

		$new_step	= get_request('new_step', null);

		if((isset($_REQUEST['slideshowid']) && !isset($_REQUEST['form_refresh']))){
			$slideshow_data = DBfetch(DBselect('SELECT * FROM slideshows WHERE slideshowid='.$_REQUEST['slideshowid']));

			$name		= $slideshow_data['name'];
			$delay		= $slideshow_data['delay'];
			$steps		= array();
			$db_steps = DBselect('SELECT * FROM slides WHERE slideshowid='.$_REQUEST['slideshowid'].' order by step');

			while($step_data = DBfetch($db_steps)){
				$steps[$step_data['step']] = array(
						'screenid' => $step_data['screenid'],
						'delay' => $step_data['delay']
					);
			}
		}

		$form->addRow(S_NAME, new CTextBox('name', $name, 40));

		$delayBox = new CComboBox('delay', $delay);
		$delayBox->addItem(10,'10');
		$delayBox->addItem(30,'30');
		$delayBox->addItem(60,'60');
		$delayBox->addItem(120,'120');
		$delayBox->addItem(600,'600');
		$delayBox->addItem(900,'900');

		$form->addRow(S_UPDATE_INTERVAL_IN_SEC, $delayBox);

		$tblSteps = new CTableInfo(S_NO_SLIDES_DEFINED);
		$tblSteps->setHeader(array(S_SCREEN, S_DELAY, S_SORT));
		if(count($steps) > 0){
			ksort($steps);
			$first = min(array_keys($steps));
			$last = max(array_keys($steps));
		}

		foreach($steps as $sid => $s){
			if( !isset($s['screenid']) ) $s['screenid'] = 0;

			if(isset($s['delay']) && $s['delay'] > 0 )
				$s['delay'] = bold($s['delay']);
			else
				$s['delay'] = $delay;

			$up = null;
			if($sid != $first){
				$up = new CSpan(S_UP,'link');
				$up->onClick("return create_var('".$form->GetName()."','move_up',".$sid.", true);");
			}

			$down = null;
			if($sid != $last){
				$down = new CSpan(S_DOWN,'link');
				$down->onClick("return create_var('".$form->GetName()."','move_down',".$sid.", true);");
			}

			$screen_data = get_screen_by_screenid($s['screenid']);
			$name = new CSpan($screen_data['name'],'link');
			$name->onClick("return create_var('".$form->GetName()."','edit_step',".$sid.", true);");

			$tblSteps->addRow(array(
				array(new CCheckBox('sel_step[]',null,null,$sid), $name),
				$s['delay'],
				array($up, isset($up) && isset($down) ? SPACE : null, $down)
				));
		}
		$form->addVar('steps', $steps);

		$form->addRow(S_SLIDES, array(
			$tblSteps,
			!isset($new_step) ? new CButton('add_step_bttn',S_ADD,
				"return create_var('".$form->getName()."','add_step',1, true);") : null,
			(count($steps) > 0) ? new CButton('del_sel_step',S_DELETE_SELECTED) : null
			));

		if(isset($new_step)){
			if( !isset($new_step['screenid']) )	$new_step['screenid'] = 0;
			if( !isset($new_step['delay']) )	$new_step['delay'] = 0;

			if( isset($new_step['sid']) )
				$form->addVar('new_step[sid]',$new_step['sid']);

			$form->addVar('new_step[screenid]',$new_step['screenid']);

			$screen_data = get_screen_by_screenid($new_step['screenid']);

			$form->addRow(S_NEW_SLIDE, array(
					S_DELAY,
					new CNumericBox('new_step[delay]', $new_step['delay'], 5), BR(),
					new CTextBox('screen_name', $screen_data['name'], 40, 'yes'),
					new CButton('select_screen',S_SELECT,
						'return PopUp("popup.php?dstfrm='.$form->GetName().'&srctbl=screens'.
						'&dstfld1=screen_name&srcfld1=name'.
						'&dstfld2=new_step%5Bscreenid%5D&srcfld2=screenid");'),
					BR(),
					new CButton('add_step', isset($new_step['sid']) ? S_SAVE : S_ADD),
					new CButton('cancel_step', S_CANCEL)

				),
				isset($new_step['sid']) ? 'edit' : 'new');
		}

		$form->addItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST['slideshowid'])){
			$form->addItemToBottomRow(SPACE);
			$form->addItemToBottomRow(new CButton('clone',S_CLONE));
			$form->addItemToBottomRow(SPACE);
			$form->addItemToBottomRow(new CButtonDelete(S_DELETE_SLIDESHOW_Q,
				url_param('form').url_param('slideshowid').url_param('config')));
		}
		$form->addItemToBottomRow(SPACE);
		$form->addItemToBottomRow(new CButtonCancel());

		return $form;
	}

	function insert_drule_form(){

		$frm_title = S_DISCOVERY_RULE;

		if(isset($_REQUEST['druleid'])){
			if( ($rule_data = DBfetch(DBselect('SELECT * FROM drules WHERE druleid='.$_REQUEST['druleid']))))
				$frm_title = S_DISCOVERY_RULE.' "'.$rule_data['name'].'"';
		}

		$form = new CFormTable($frm_title, null, 'post');
		$form->setHelp("web.discovery.rule.php");

		if(isset($_REQUEST['druleid'])){
			$form->addVar('druleid', $_REQUEST['druleid']);
		}

		$uniqueness_criteria = -1;

		if(isset($_REQUEST['druleid']) && $rule_data && (!isset($_REQUEST["form_refresh"]) || isset($_REQUEST["register"]))){
			$proxy_hostid	= $rule_data['proxy_hostid'];
			$name		= $rule_data['name'];
			$iprange	= $rule_data['iprange'];
			$delay		= $rule_data['delay'];
			$status		= $rule_data['status'];

			//TODO init checks
			$dchecks = array();
			$db_checks = DBselect('SELECT dcheckid,type,ports,key_,snmp_community,snmpv3_securityname,'.
						'snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase'.
						' FROM dchecks'.
						' WHERE druleid='.$_REQUEST['druleid']);
			while($check_data = DBfetch($db_checks)){
				$count = array_push($dchecks, array('dcheckid' => $check_data['dcheckid'], 'type' => $check_data['type'],
						'ports' => $check_data['ports'], 'key' => $check_data['key_'],
						'snmp_community' => $check_data['snmp_community'],
						'snmpv3_securityname' => $check_data['snmpv3_securityname'],
						'snmpv3_securitylevel' => $check_data['snmpv3_securitylevel'],
						'snmpv3_authpassphrase' => $check_data['snmpv3_authpassphrase'],
						'snmpv3_privpassphrase' => $check_data['snmpv3_privpassphrase']));
				if ($check_data['dcheckid'] == $rule_data['unique_dcheckid'])
					$uniqueness_criteria = $count - 1;
			}
			$dchecks_deleted = get_request('dchecks_deleted',array());
		}
		else{
			$proxy_hostid	= get_request("proxy_hostid",0);
			$name		= get_request('name','');
			$iprange	= get_request('iprange','192.168.0.1-255');
			$delay		= get_request('delay',3600);
			$status		= get_request('status',DRULE_STATUS_ACTIVE);

			$dchecks	= get_request('dchecks',array());
			$dchecks_deleted = get_request('dchecks_deleted',array());
		}

		$new_check_type	= get_request('new_check_type', SVC_HTTP);
		$new_check_ports= get_request('new_check_ports', '80');
		$new_check_key= get_request('new_check_key', '');
		$new_check_snmp_community= get_request('new_check_snmp_community', '');
		$new_check_snmpv3_securitylevel = get_request('new_check_snmpv3_securitylevel', ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV);
		$new_check_snmpv3_securityname = get_request('new_check_snmpv3_securityname', '');
		$new_check_snmpv3_authpassphrase = get_request('new_check_snmpv3_authpassphrase', '');
		$new_check_snmpv3_privpassphrase = get_request('new_check_snmpv3_privpassphrase', '');

		$form->addRow(S_NAME, new CTextBox('name', $name, 40));
//Proxy
		$cmbProxy = new CComboBox("proxy_hostid", $proxy_hostid);

		$cmbProxy->addItem(0, S_NO_PROXY);

		$sql = 'SELECT hostid,host '.
				' FROM hosts'.
				' WHERE status IN ('.HOST_STATUS_PROXY.') '.
					' AND '.DBin_node('hostid').
				' ORDER BY host';
		$db_proxies = DBselect($sql);
		while($db_proxy = DBfetch($db_proxies))
			$cmbProxy->addItem($db_proxy['hostid'], $db_proxy['host']);

		$form->addRow(S_DISCOVERY_BY_PROXY,$cmbProxy);
//----------
		$form->addRow(S_IP_RANGE, new CTextBox('iprange', $iprange, 27));
		$form->addRow(S_DELAY.' (seconds)', new CNumericBox('delay', $delay, 8));

		$form->addVar('dchecks', $dchecks);
		$form->addVar('dchecks_deleted', $dchecks_deleted);

		$cmbUniquenessCriteria = new CComboBox('uniqueness_criteria', $uniqueness_criteria);
		$cmbUniquenessCriteria->addItem(-1, S_IP_ADDRESS);
		foreach($dchecks as $id => $data){
			$str = discovery_check2str($data['type'], $data['snmp_community'], $data['key'], $data['ports']);
			$dchecks[$id] = array(
				new CCheckBox('selected_checks[]',null,null,$id),
				$str,
				BR()
			);
			if(in_array($data['type'], array(SVC_AGENT, SVC_SNMPv1, SVC_SNMPv2, SVC_SNMPv3)))
				$cmbUniquenessCriteria->addItem($id, $str);
		}

		if(count($dchecks)){
			$dchecks[] = new CButton('delete_ckecks', S_DELETE_SELECTED);
			$form->addRow(S_CHECKS, $dchecks);
		}

		$cmbChkType = new CComboBox('new_check_type',$new_check_type,
			"if(add_variable(this, 'type_changed', 1)) submit()"
			);
		foreach(array(SVC_SSH, SVC_LDAP, SVC_SMTP, SVC_FTP, SVC_HTTP, SVC_POP, SVC_NNTP, SVC_IMAP, SVC_TCP, SVC_AGENT, SVC_SNMPv1, SVC_SNMPv2, SVC_SNMPv3, SVC_ICMPPING) as $type_int)
			$cmbChkType->addItem($type_int, discovery_check_type2str($type_int));

		if(isset($_REQUEST['type_changed'])){
			$new_check_ports = svc_default_port($new_check_type);
		}


		$external_param = new CTable();

		if($new_check_type != SVC_ICMPPING){
			$external_param->addRow(array(S_PORTS_SMALL, new CTextBox('new_check_ports', $new_check_ports, 20)));
		}
		switch($new_check_type){
			case SVC_SNMPv1:
			case SVC_SNMPv2:
				$external_param->addRow(array(S_SNMP_COMMUNITY, new CTextBox('new_check_snmp_community', $new_check_snmp_community)));
				$external_param->addRow(array(S_SNMP_OID, new CTextBox('new_check_key', $new_check_key)));

				$form->addVar('new_check_snmpv3_securitylevel', ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV);
				$form->addVar('new_check_snmpv3_securityname', '');
				$form->addVar('new_check_snmpv3_authpassphrase', '');
				$form->addVar('new_check_snmpv3_privpassphrase', '');
			break;
			case SVC_SNMPv3:
				$form->addVar('new_check_snmp_community', '');

				$external_param->addRow(array(S_SNMP_OID, new CTextBox('new_check_key', $new_check_key)));
				$external_param->addRow(array(S_SNMPV3_SECURITY_NAME, new CTextBox('new_check_snmpv3_securityname', $new_check_snmpv3_securityname)));

				$cmbSecLevel = new CComboBox('new_check_snmpv3_securitylevel', $new_check_snmpv3_securitylevel);
				$cmbSecLevel->addItem(ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV,'NoAuthPriv');
				$cmbSecLevel->addItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV,'AuthNoPriv');
				$cmbSecLevel->addItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV,'AuthPriv');

				$external_param->addRow(array(S_SNMPV3_SECURITY_LEVEL, $cmbSecLevel));
				$external_param->addRow(array(S_SNMPV3_AUTH_PASSPHRASE, new CTextBox('new_check_snmpv3_authpassphrase', $new_check_snmpv3_authpassphrase)));
				$external_param->addRow(array(S_SNMPV3_PRIV_PASSPHRASE, new CTextBox('new_check_snmpv3_privpassphrase', $new_check_snmpv3_privpassphrase), BR()));
			break;
			case SVC_AGENT:
				$form->addVar('new_check_snmp_community', '');
				$form->addVar('new_check_snmpv3_securitylevel', ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV);
				$form->addVar('new_check_snmpv3_securityname', '');
				$form->addVar('new_check_snmpv3_authpassphrase', '');
				$form->addVar('new_check_snmpv3_privpassphrase', '');
				$external_param->addRow(array(S_KEY, new CTextBox('new_check_key', $new_check_key), BR()));
			break;
			case SVC_ICMPPING:
				$form->addVar('new_check_ports', '0');
			default:
				$form->addVar('new_check_snmp_community', '');
				$form->addVar('new_check_key', '');
				$form->addVar('new_check_snmpv3_securitylevel', ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV);
				$form->addVar('new_check_snmpv3_securityname', '');
				$form->addVar('new_check_snmpv3_authpassphrase', '');
				$form->addVar('new_check_snmpv3_privpassphrase', '');
		}



		if($external_param->getNumRows() == 0) $external_param = null;
		$form->addRow(S_NEW_CHECK, array(
			$cmbChkType, SPACE,
			new CButton('add_check', S_ADD),
			$external_param
		),'new');

		$form->addRow(S_DEVICE_UNIQUENESS_CRITERIA, $cmbUniquenessCriteria);

		$cmbStatus = new CComboBox("status", $status);
		foreach(array(DRULE_STATUS_ACTIVE, DRULE_STATUS_DISABLED) as $st)
			$cmbStatus->addItem($st, discovery_status2str($st));
		$form->addRow(S_STATUS,$cmbStatus);

		$form->addItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["druleid"])){
			$form->addItemToBottomRow(SPACE);
			$form->addItemToBottomRow(new CButton("clone",S_CLONE));
			$form->addItemToBottomRow(SPACE);
			$form->addItemToBottomRow(new CButtonDelete(S_DELETE_RULE_Q,
				url_param("form").url_param("druleid")));
		}
		$form->addItemToBottomRow(SPACE);
		$form->addItemToBottomRow(new CButtonCancel());

		return $form;
	}

	function	insert_httpstep_form()
	{
		$form = new CFormTable(S_STEP_OF_SCENARIO, null, 'post');
		$form->setHelp("web.webmon.httpconf.php");

		$form->addVar('dstfrm', get_request('dstfrm', null));
		$form->addVar('stepid', get_request('stepid', null));
		$form->addVar('list_name', get_request('list_name', null));

		$stepid = get_request('stepid', null);
		$name = get_request('name', '');
		$url = get_request('url', '');
		$posts = get_request('posts', '');
		$timeout = get_request('timeout', 15);
		$required = get_request('required', '');
		$status_codes = get_request('status_codes', '');

		$form->addRow(S_NAME, new CTextBox('name', $name, 50));
		$form->addRow(S_URL, new CTextBox('url', $url, 80));
		$form->addRow(S_POST, new CTextArea('posts', $posts, 50, 10));
		$form->addRow(S_TIMEOUT, new CNumericBox('timeout', $timeout, 5));
		$form->addRow(S_REQUIRED, new CTextBox('required', $required, 80));
		$form->addRow(S_STATUS_CODES, new CTextBox('status_codes', $status_codes, 80));

		$form->addItemToBottomRow(new CButton("save", isset($stepid) ? S_SAVE : S_ADD));

		$form->addItemToBottomRow(new CButtonCancel(null,'close_window();'));

		$form->show();
	}

	function insert_httptest_form(){

		$form = new CFormTable(S_SCENARIO, null, 'post');
		$form->setName('form_scenario');

		if($_REQUEST['groupid'] > 0)
			$form->addVar('groupid', $_REQUEST['groupid']);

		$form->addVar('hostid', $_REQUEST['hostid']);

		if(isset($_REQUEST['httptestid'])){
			$form->addVar('httptestid', $_REQUEST['httptestid']);
		}

		$name		= get_request('name', '');
		$application	= get_request('application', '');
		$delay		= get_request('delay', 60);
		$status		= get_request('status', HTTPTEST_STATUS_ACTIVE);
		$agent		= get_request('agent', '');
		$macros		= get_request('macros', array());
		$steps		= get_request('steps', array());

		$authentication = get_request('authentication', HTTPTEST_AUTH_NONE);
		$http_user	= get_request('http_user', '');
		$http_password 	= get_request('http_password', '');

		if((isset($_REQUEST["httptestid"]) && !isset($_REQUEST["form_refresh"])) || isset($limited)){
			$httptest_data = DBfetch(DBselect("SELECT wt.*, a.name as application ".
				" FROM httptest wt,applications a WHERE wt.httptestid=".$_REQUEST["httptestid"].
				" AND a.applicationid=wt.applicationid"));

			$name		= $httptest_data['name'];
			$application	= $httptest_data['application'];
			$delay		= $httptest_data['delay'];
			$status		= $httptest_data['status'];
			$agent		= $httptest_data['agent'];
			$macros		= $httptest_data['macros'];

			$authentication = $httptest_data['authentication'];
			$http_user 	= $httptest_data['http_user'];
			$http_password 	= $httptest_data['http_password'];

			$steps		= array();
			$db_steps = DBselect('SELECT * FROM httpstep WHERE httptestid='.$_REQUEST["httptestid"].' order by no');
			while($step_data = DBfetch($db_steps))
			{
				$steps[] = $step_data;
			}
		}

		$form->addRow(S_APPLICATION,array(
			new CTextBox('application', $application, 40),
			SPACE,
			new CButton('select_app',S_SELECT,
				'return PopUp("popup.php?dstfrm='.$form->GetName().
				'&dstfld1=application&srctbl=applications'.
				'&srcfld1=name&only_hostid='.$_REQUEST['hostid'].'",200,300,"application");')
			));

		$form->addRow(S_NAME, new CTextBox('name', $name, 40));

		$cmbAuth = new CComboBox('authentication',$authentication,'submit();');
		$cmbAuth->addItem(HTTPTEST_AUTH_NONE,S_NONE);
		$cmbAuth->addItem(HTTPTEST_AUTH_BASIC,S_BASIC_AUTHENTICATION);

		$form->addRow(S_BASIC_AUTHENTICATION, $cmbAuth);
		if($authentication == HTTPTEST_AUTH_BASIC){
			$form->addRow(S_USER, new CTextBox('http_user', $http_user, 32));
			$form->addRow(S_PASSWORD, new CTextBox('http_password', $http_password, 40));
		}

		$form->addRow(S_UPDATE_INTERVAL_IN_SEC, new CNumericBox("delay",$delay,5));

		$cmbAgent = new CEditableComboBox('agent', $agent, 80);
// IE6
		$cmbAgent->addItem('Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 2.0.50727)',
			'Internet Explorer 6.0 on Windows XP SP2 with .NET Framework 2.0 installed');
// IE7
		$cmbAgent->addItem('Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; Trident/4.0; .NET CLR 1.1.4322; .NET CLR 2.0.50727; .NET CLR 3.0.04506.648; .NET CLR 3.5.21022)', 'Internet Explorer 7.0 on Windows XP SP3 with .NET Framework 3.5 installed');
// FF 1.5
		$cmbAgent->addItem('Mozilla/5.0 (Windows; U; Windows NT 5.1; en-GB; rv:1.8.0.7) Gecko/20060909 Firefox/1.5.0.7',
			'Mozilla Firefox 1.5.0.7 on Windows XP');
		$cmbAgent->addItem('Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.8.0.7) Gecko/20060909 Firefox/1.5.0.7',
			'Mozilla Firefox 1.5.0.7 on Linux');
// FF 2.0
		$cmbAgent->addItem('Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.18) Gecko/20081029 Firefox/2.0.0.18',
			'Mozilla Firefox 2.0.0.18 on Windows XP');
		$cmbAgent->addItem('Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.8.1.18) Gecko/20081029 Firefox/2.0.0.18',
			'Mozilla Firefox 2.0.0.18 on Linux');
// FF 3.0
		$cmbAgent->addItem('Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.1) Gecko/2008070208 Firefox/3.0.1',
			'Mozilla Firefox 3.0.1 on Windows XP');
		$cmbAgent->addItem('Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.9.0.1) Gecko/2008070208 Firefox/3.0.1',
			'Mozilla Firefox 3.0.1 on Linux');
// OP 9.0
		$cmbAgent->addItem('Opera/9.02 (Windows NT 5.1; U; en)',
			'Opera 9.02 on Windows XP');
		$cmbAgent->addItem('Opera/9.02 (X11; Linux i686; U; en)',
			'Opera 9.02 on Linux');
// OP 9.6
		$cmbAgent->addItem('Opera/9.61 (Windows NT 5.1; U; en) Presto/2.1.1',
			'Opera 9.61 on Windows XP');
		$cmbAgent->addItem('Opera/9.61 (X11; Linux i686; U; en) Presto/2.1.1',
			'Opera 9.61 on Linux');
// SF 3.1
		$cmbAgent->addItem('Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US) AppleWebKit/525.19 (KHTML, like Gecko) Version/3.1.2 Safari/525.21',
			'Safari 3.1.2 on Windows XP');
		$cmbAgent->addItem('Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_5_4; en-us) AppleWebKit/527.2+ (KHTML, like Gecko) Version/3.1.2 Safari/525.20.1',
			'Safari 3.1.2 on Intel Mac OS X 10.5.4');
		$cmbAgent->addItem('Mozilla/5.0 (iPhone; U; CPU iPhone OS 2_1 like Mac OS X; fr-fr) AppleWebKit/525.18.1 (KHTML, like Gecko) Mobile/5F136',
			'Safari on iPhone');

		$cmbAgent->addItem('Lynx/2.8.4rel.1 libwww-FM/2.14',
			'Lynx 2.8.4rel.1 on Linux');
		$cmbAgent->addItem('Googlebot/2.1 (+http://www.google.com/bot.html)',
			'Googlebot');
		$form->addRow(S_AGENT, $cmbAgent);

		$cmbStatus = new CComboBox("status", $status);
		foreach(array(HTTPTEST_STATUS_ACTIVE, HTTPTEST_STATUS_DISABLED) as $st)
			$cmbStatus->addItem($st, httptest_status2str($st));
		$form->addRow(S_STATUS,$cmbStatus);

		$form->addRow(S_VARIABLES, new CTextArea('macros', $macros, 84, 5));

		$tblSteps = new CTableInfo();
		$tblSteps->setHeader(array(S_NAME,S_TIMEOUT,S_URL,S_REQUIRED,S_STATUS,S_SORT));
		if(count($steps) > 0){
			$first = min(array_keys($steps));
			$last = max(array_keys($steps));
		}
		foreach($steps as $stepid => $s){
			if(!isset($s['name']))		$s['name'] = '';
			if(!isset($s['timeout']))	$s['timeout'] = 15;
			if(!isset($s['url']))		$s['url'] = '';
			if(!isset($s['posts']))		$s['posts'] = '';
			if(!isset($s['required']))	$s['required'] = '';

			$up = null;
			if($stepid != $first){
				$up = new CSpan(S_UP,'link');
				$up->onClick("return create_var('".$form->GetName()."','move_up',".$stepid.", true);");
			}

			$down = null;
			if($stepid != $last){
				$down = new CLink(S_DOWN,'link');
				$down->onClick("return create_var('".$form->GetName()."','move_down',".$stepid.", true);");
			}

			$name = new CSpan($s['name'],'link');
			$name->onClick('return PopUp("popup_httpstep.php?dstfrm='.$form->GetName().
				'&list_name=steps&stepid='.$stepid.
				url_param($s['name'],false,'name').
				url_param($s['timeout'],false,'timeout').
				url_param($s['url'],false,'url').
				url_param($s['posts'],false,'posts').
				url_param($s['required'],false,'required').
				url_param($s['status_codes'],false,'status_codes').
				'");');

			if(zbx_strlen($s['url']) > 70){
				$url = new CTag('span','yes', substr($s['url'],0,35).SPACE.'...'.SPACE.substr($s['url'],zbx_strlen($s['url'])-25,25));
				$url->setHint($s['url']);
			}
			else{
				$url = $s['url'];
			}

			$tblSteps->addRow(array(
				array(new CCheckBox('sel_step[]',null,null,$stepid), $name),
				$s['timeout'].SPACE.S_SEC_SMALL,
				$url,
				$s['required'],
				$s['status_codes'],
				array($up, isset($up) && isset($down) ? SPACE : null, $down)
				));
		}
		$form->addVar('steps', $steps);

		$form->addRow(S_STEPS, array(
			(count($steps) > 0) ? array ($tblSteps, BR()) : null ,
			new CButton('add_step',S_ADD,
				'return PopUp("popup_httpstep.php?dstfrm='.$form->GetName().'");'),
			(count($steps) > 0) ? new CButton('del_sel_step',S_DELETE_SELECTED) : null
			));

		$form->addItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["httptestid"])){
			$form->addItemToBottomRow(SPACE);
			$form->addItemToBottomRow(new CButton("clone",S_CLONE));
			$form->addItemToBottomRow(SPACE);
			$form->addItemToBottomRow(new CButtonDelete(S_DELETE_SCENARIO_Q,
				url_param("form").url_param("httptestid").url_param('hostid')));
		}
		$form->addItemToBottomRow(SPACE);
		$form->addItemToBottomRow(new CButtonCancel());

		return $form;
	}

	function insert_configuration_form($file){
		$type		= get_request('type',		'MYSQL');
		$server		= get_request('server',		'localhost');
		$database	= get_request('database',	'zabbix');
		$user		= get_request('user',		'root');
		$password	= get_request('password',	'');

		$form = new CFormTable(S_CONFIGURATION_OF_ZABBIX_DATABASE, null, 'post');

		$form->setHelp("install_source_web.php");
		$cmbType = new CComboBox('type', $type);
		$cmbType->addItem('MYSQL',	S_MYSQL);
		$cmbType->addItem('POSTGRESQL',	S_POSTGRESQL);
		$cmbType->addItem('ORACLE',	S_ORACLE);
		$form->addRow(S_TYPE, $cmbType);

		$form->addRow(S_HOST, new CTextBox('server', $server));
		$form->addRow(S_NAME, new CTextBox('database', $database));
		$form->addRow(S_USER, new CTextBox('user', $user));
		$form->addRow(S_PASSWORD, new CPassBox('password', $password));

		$form->addItemToBottomRow(new CButton('save',S_SAVE));

		$form->show();
	}

// Insert form for User
	function insert_user_form($userid, $profile=0){
		global $ZBX_LOCALES;
		global $USER_DETAILS;

		$config = select_config();

		$frm_title = S_USER;
		if(isset($userid)){
/*			if(bccomp($userid,$USER_DETAILS['userid'])==0) $profile = 1;*/
			$options = array(
					'userids' => $userid,
					'extendoutput' => 1
				);
			if($profile) $options['nodeids'] = id2nodeid($userid);

			$users = CUser::get($options);
			$user = reset($users);

			$frm_title = S_USER.' "'.$user['alias'].'"';
		}

		if(isset($userid) && (!isset($_REQUEST['form_refresh']) || isset($_REQUEST['register']))){
			$alias		= $user['alias'];
			$name		= $user['name'];
			$surname	= $user['surname'];
			$password	= null;
			$password1	= null;
			$password2	= null;
			$url		= $user['url'];
			$autologin	= $user['autologin'];
			$autologout	= $user['autologout'];
			$lang		= $user['lang'];
			$theme		= $user['theme'];
			$refresh	= $user['refresh'];
			$rows_per_page	= $user['rows_per_page'];
			$user_type	= $user['type'];

			if($autologout > 0) $_REQUEST['autologout'] = $autologout;

			$user_groups	= array();
			$user_medias		= array();

			$sql = 'SELECT g.* '.
					' FROM usrgrp g, users_groups ug '.
					' WHERE ug.usrgrpid=g.usrgrpid '.
						' AND ug.userid='.$userid;
			$db_user_groups = DBselect($sql);

			while($db_group = DBfetch($db_user_groups)){
				$user_groups[$db_group['usrgrpid']] = $db_group['usrgrpid'];
			}

			$db_medias = DBselect('SELECT m.* FROM media m WHERE m.userid='.$userid);
			while($db_media = DBfetch($db_medias)){
				$user_medias[] = array('mediaid' => $db_media['mediaid'],
									'mediatypeid' => $db_media['mediatypeid'],
									'period' => $db_media['period'],
									'sendto' => $db_media['sendto'],
									'severity' => $db_media['severity'],
									'active' => $db_media['active']);
			}
		}
		else{
			$alias		= get_request('alias','');
			$name		= get_request('name','');
			$surname	= get_request('surname','');
			$password	= null;
			$password1	= get_request('password1', '');
			$password2	= get_request('password2', '');
			$url		= get_request('url','');

			$autologin	= get_request('autologin',0);
			$autologout	= get_request('autologout',90);

			$lang		= get_request('lang','en_gb');
			$theme		= get_request('theme','default.css');
			$refresh	= get_request('refresh',30);
			$rows_per_page	= get_request('rows_per_page',50);

			$user_type		= get_request('user_type',USER_TYPE_ZABBIX_USER);;
			$user_groups		= get_request('user_groups',array());
			$change_password	= get_request('change_password', null);
			$user_medias		= get_request('user_medias', array());
		}

		if($autologin || !isset($_REQUEST['autologout'])){
			$autologout = 0;
		}
		else if(isset($_REQUEST['autologout']) && ($autologout < 90)){
			$autologout = 90;
		}

		$perm_details	= get_request('perm_details',0);

		$media_types = array();
		$media_type_ids = array();
		foreach($user_medias as $one_media) $media_type_ids[$one_media['mediatypeid']] = 1;

		if(count($media_type_ids) > 0){
			$db_media_types = DBselect('SELECT mt.mediatypeid, mt.description '.
									' FROM media_type mt '.
									' WHERE mt.mediatypeid IN ('.implode(',',array_keys($media_type_ids)).')');

			while($db_media_type = DBfetch($db_media_types)){
				$media_types[$db_media_type['mediatypeid']] = $db_media_type['description'];
			}
		}

		$frmUser = new CFormTable($frm_title);
		$frmUser->setName('user_form');
		$frmUser->setHelp('web.users.php');
		$frmUser->addVar('config',get_request('config',0));

		if(isset($userid))	$frmUser->addVar('userid',$userid);

		if($profile==0){
			$frmUser->addRow(S_ALIAS,	new CTextBox('alias',$alias,40));
			$frmUser->addRow(S_NAME,	new CTextBox('name',$name,40));
			$frmUser->addRow(S_SURNAME,	new CTextBox('surname',$surname,40));
		}

		$auth_type = isset($userid) ? get_user_system_auth($userid) : $config['authentication_type'];

		if(ZBX_AUTH_INTERNAL == $auth_type){
			if(!isset($userid) || isset($change_password)){
				$frmUser->addRow(S_PASSWORD,	new CPassBox('password1',$password1,20));
				$frmUser->addRow(S_PASSWORD_ONCE_AGAIN,	new CPassBox('password2',$password2,20));
				if(isset($change_password))
					$frmUser->addVar('change_password', $change_password);
			}
			else{
				$passwd_but = new CButton('change_password', S_CHANGE_PASSWORD);
				if($alias == ZBX_GUEST_USER){
					$passwd_but->setAttribute('disabled','disabled');
				}
				$frmUser->addRow(S_PASSWORD, $passwd_but);
			}
		}
		// else{
			// if(!isset($userid) || isset($change_password)){
				// $frmUser->addVar('password1','');
				// $frmUser->addVar('password2','');
			// }
		// }

		if($profile==0){

			$frmUser->addVar('user_groups',$user_groups);

			if(isset($userid) && (bccomp($USER_DETAILS['userid'], $userid)==0)){
				$frmUser->addVar('user_type',$user_type);
			}
			else{
				$cmbUserType = new CComboBox('user_type', $user_type, $perm_details ? 'submit();' : null);
				$cmbUserType->addItem(USER_TYPE_ZABBIX_USER,	user_type2str(USER_TYPE_ZABBIX_USER));
				$cmbUserType->addItem(USER_TYPE_ZABBIX_ADMIN,	user_type2str(USER_TYPE_ZABBIX_ADMIN));
				$cmbUserType->addItem(USER_TYPE_SUPER_ADMIN,	user_type2str(USER_TYPE_SUPER_ADMIN));
				$frmUser->addRow(S_USER_TYPE, $cmbUserType);
			}

			$lstGroups = new CListBox('user_groups_to_del[]', null, 10);
			$lstGroups->attributes['style'] = 'width: 320px';

			$groups = CUserGroup::get(array('usrgrpids' => $user_groups, 'extendoutput' => 1));
			order_result($groups, 'name');
			foreach($groups as $num => $group){
				$lstGroups->addItem($group['usrgrpid'], $group['name']);
			}

			$frmUser->addRow(S_GROUPS,
				array(
					$lstGroups,
					BR(),
					new CButton('add_group',S_ADD,
						'return PopUp("popup_usrgrp.php?dstfrm='.$frmUser->GetName().
						'&list_name=user_groups_to_del[]&var_name=user_groups",450, 450);'),
					SPACE,
					(count($user_groups) > 0)?new CButton('del_user_group',S_DELETE_SELECTED):null
				));
		}



		$cmbLang = new CComboBox('lang',$lang);
		foreach($ZBX_LOCALES as $loc_id => $loc_name){
			$cmbLang->addItem($loc_id,$loc_name);
		}

		$frmUser->addRow(S_LANGUAGE, $cmbLang);

		$cmbTheme = new CComboBox('theme',$theme);
			$cmbTheme->addItem(ZBX_DEFAULT_CSS,S_SYSTEM_DEFAULT);
			$cmbTheme->addItem('css_ob.css',S_ORIGINAL_BLUE);
			$cmbTheme->addItem('css_bb.css',S_BLACK_AND_BLUE);

		$frmUser->addRow(S_THEME, $cmbTheme);

		$chkbx_autologin = new CCheckBox("autologin",
											$autologin,
											new CJSscript("var autologout_visible = document.getElementById('autologout_visible');
														var autologout = document.getElementById('autologout');
														if (this.checked) {
															if (autologout_visible.checked) {
																autologout_visible.checked = false;
																autologout_visible.onclick();
															}
															autologout_visible.disabled = true;
														} else {
															autologout_visible.disabled = false;
														}"), 1);
		$chkbx_autologin->setAttribute('autocomplete','off');
		$frmUser->addRow(S_AUTO_LOGIN,	$chkbx_autologin);
		$autologoutCheckBox = new CCheckBox('autologout_visible',
											($autologout == 0) ? 'no' : 'yes',
											new CJSscript("var autologout = document.getElementById('autologout');
														if (this.checked) {
															autologout.disabled = false;
														} else {
															autologout.disabled = true;
														}"));

		$autologoutTextBox = new CNumericBox("autologout", ($autologout == 0) ? '90' : $autologout, 4);
		// if autologout is disabled
		if ($autologout == 0) {
			$autologoutTextBox->setAttribute('disabled','disabled');
		}
		if($autologin != 0) {
			$autologoutCheckBox->setAttribute('disabled','disabled');
		}

		$frmUser->addRow(S_AUTO_LOGOUT, array($autologoutCheckBox, $autologoutTextBox));
		$frmUser->addRow(S_SCREEN_REFRESH,	new CNumericBox('refresh',$refresh,4));

		$frmUser->addRow(S_ROWS_PER_PAGE,	new CNumericBox('rows_per_page',$rows_per_page,3));
		$frmUser->addRow(S_URL_AFTER_LOGIN,	new CTextBox("url",$url,50));

//view Media Settings for users above "User" +++
		if(uint_in_array($USER_DETAILS['type'], array(USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN))) {
			$frmUser->addVar('user_medias', $user_medias);

			$media_table = new CTableInfo(S_NO_MEDIA_DEFINED);
			foreach($user_medias as $id => $one_media){
				if(!isset($one_media['active']) || $one_media['active']==0){
					$status = new CLink(S_ENABLED,'#','enabled');
					$status->OnClick('return create_var("'.$frmUser->GetName().'","disable_media",'.$id.', true);');
				}
				else{
					$status = new CLink(S_DISABLED,'#','disabled');
					$status->OnClick('return create_var("'.$frmUser->GetName().'","enable_media",'.$id.', true);');
				}

				$media_url = '?dstfrm='.$frmUser->GetName().
								'&media='.$id.
								'&mediatypeid='.$one_media['mediatypeid'].
								'&sendto='.urlencode($one_media['sendto']).
								'&period='.$one_media['period'].
								'&severity='.$one_media['severity'].
								'&active='.$one_media['active'];

				$media_table->addRow(array(
					new CCheckBox('user_medias_to_del['.$id.']',null,null,$id),
					new CSpan($media_types[$one_media['mediatypeid']], 'nowrap'),
					new CSpan($one_media['sendto'], 'nowrap'),
					new CSpan($one_media['period'], 'nowrap'),
					media_severity2str($one_media['severity']),
					$status,
					new CButton('edit_media',S_EDIT,'javascript: return PopUp("popup_media.php'.$media_url.'",550,400);'))
				);
			}

			$frmUser->addRow(
				S_MEDIA,
				array($media_table,
					new CButton('add_media',S_ADD,'javascript: return PopUp("popup_media.php?dstfrm='.$frmUser->GetName().'",550,400);'),
					SPACE,
					(count($user_medias) > 0) ? new CButton('del_user_media',S_DELETE_SELECTED) : null
				));
		}


		if(0 == $profile){
			$frmUser->addVar('perm_details', $perm_details);

			$link = new CSpan($perm_details?S_HIDE:S_SHOW ,'link');
			$link->onClick("return create_var('".$frmUser->GetName()."','perm_details',".($perm_details ? 0 : 1).", true);");
			$resources_list = array(
				S_RIGHTS_OF_RESOURCES,
				SPACE.'(',$link,')'
				);
			$frmUser->addSpanRow($resources_list,'right_header');

			if($perm_details){
				$group_ids = array_values($user_groups);
				if(count($group_ids) == 0) $group_ids = array(-1);
				$db_rights = DBselect('SELECT * FROM rights r WHERE '.DBcondition('r.groupid',$group_ids));

				$tmp_perm = array();
				while($db_right = DBfetch($db_rights)){
					if(isset($tmp_perm[$db_right['id']])){
						$tmp_perm[$db_right['id']] = min($tmp_perm[$db_right['id']],$db_right['permission']);
					}
					else{
						$tmp_perm[$db_right['id']] = $db_right['permission'];
					}
				}

				$user_rights = array();
				foreach($tmp_perm as $id => $perm){
					array_push($user_rights, array(
						'id'		=> $id,
						'permission'	=> $perm
						));
				}
//SDI($user_rights);
//SDI($user_type);
				$frmUser->addSpanRow(get_rights_of_elements_table($user_rights, $user_type));
			}
		}

		$frmUser->addItemToBottomRow(new CButton('save',S_SAVE));
		if(isset($userid) && $profile == 0){
			$frmUser->addItemToBottomRow(SPACE);
			$delete_b = new CButtonDelete(S_DELETE_SELECTED_USER_Q,url_param("form").url_param("config").url_param("userid"));
			if(bccomp($USER_DETAILS['userid'],$userid) == 0){
				$delete_b->setAttribute('disabled','disabled');
			}

			$frmUser->addItemToBottomRow($delete_b);
		}
		$frmUser->addItemToBottomRow(SPACE);
		$frmUser->addItemToBottomRow(new CButtonCancel(url_param("config")));
		
		return $frmUser;
	}

// Insert form for User Groups
	function insert_usergroups_form(){
		$config = select_config();

		$frm_title = S_USER_GROUP;
		if(isset($_REQUEST['usrgrpid'])){
			$usrgrp		= CUserGroup::get(array('usrgrpids' => $_REQUEST['usrgrpid'],  'extendoutput' => 1));
			$usrgrp = reset($usrgrp);

			$frm_title	= S_USER_GROUP.' "'.$usrgrp['name'].'"';
		}

		if(isset($_REQUEST['usrgrpid']) && !isset($_REQUEST['form_refresh'])){
			$name	= $usrgrp['name'];

			$users_status = $usrgrp['users_status'];
			$gui_access = $usrgrp['gui_access'];
			$api_access = $usrgrp['api_access'];
			$debug_mode = $usrgrp['debug_mode'];

			$group_users = array();
			$sql = 'SELECT DISTINCT u.userid '.
						' FROM users u,users_groups ug '.
						' WHERE u.userid=ug.userid '.
							' AND ug.usrgrpid='.$_REQUEST['usrgrpid'];

			$db_users=DBselect($sql);

			while($db_user=DBfetch($db_users))
				$group_users[$db_user['userid']] = $db_user['userid'];

			$group_rights = array();
			$sql = 'SELECT r.*, n.name as node_name, g.name as name '.
					' FROM groups g '.
						' LEFT JOIN rights r on r.id=g.groupid '.
						' LEFT JOIN nodes n on n.nodeid='.DBid2nodeid('g.groupid').
					' WHERE r.groupid='.$_REQUEST['usrgrpid'];

			$db_rights = DBselect($sql);
			while($db_right = DBfetch($db_rights)){
				if(isset($db_right['node_name']))
					$db_right['name'] = $db_right['node_name'].':'.$db_right['name'];

				$group_rights[$db_right['name']] = array(
					'permission'	=> $db_right['permission'],
					'id'		=> $db_right['id']
				);
			}
		}
		else{
			$name			= get_request('gname','');
			$users_status	= get_request('users_status',GROUP_STATUS_ENABLED);
			$gui_access	= get_request('gui_access',GROUP_GUI_ACCESS_SYSTEM);
			$api_access	= get_request('api_access',GROUP_API_ACCESS_DISABLED);
			$debug_mode	= get_request('debug_mode',GROUP_DEBUG_MODE_DISABLED);
			$group_users	= get_request('group_users',array());
			$group_rights	= get_request('group_rights',array());
		}
		$perm_details = get_request('perm_details', 0);

		ksort($group_rights);

		$frmUserG = new CFormTable($frm_title,'usergrps.php');
		$frmUserG->setHelp('web.users.groups.php');
		$frmUserG->addVar('config',get_request('config',1));

		if(isset($_REQUEST['usrgrpid'])){
			$frmUserG->addVar('usrgrpid',$_REQUEST['usrgrpid']);
		}

		$grName = new CTextBox('gname',$name,49);
		$grName->attributes['style'] = 'width: 280px';
		$frmUserG->addRow(S_GROUP_NAME,$grName);

		$frmUserG->addVar('group_rights', $group_rights);

/////////////////

// create table header +

	$selusrgrp = get_request('selusrgrp', 0);
	$cmbGroups = new CComboBox('selusrgrp', $selusrgrp, 'submit()');
	$cmbGroups->addItem(0,S_ALL_S);

	$sql = 'SELECT usrgrpid, name FROM usrgrp WHERE '.DBin_node('usrgrpid').' ORDER BY name';
	$result=DBselect($sql);
	while($row=DBfetch($result)){
		$cmbGroups->addItem($row['usrgrpid'], $row['name']);
	}
// -

// create user twinbox +
	$user_tb = new CTweenBox($frmUserG, 'group_users', $group_users, 10);

	$sql_from = '';
	$sql_where = '';
	if($selusrgrp > 0) {
		$sql_from = ', users_groups g ';
		$sql_where = ' AND u.userid=g.userid AND g.usrgrpid='.$selusrgrp;
	}
	$sql = 'SELECT DISTINCT u.userid, u.alias '.
			' FROM users u '.$sql_from.
			' WHERE '.DBcondition('u.userid', $group_users).
			' OR ('.DBin_node('u.userid').
				$sql_where.
			' ) ORDER BY u.alias';
	$result=DBselect($sql);
	while($row=DBfetch($result)){
		$user_tb->addItem($row['userid'], $row['alias']);
	}

	$frmUserG->addRow(S_USERS, $user_tb->get(S_IN.SPACE.S_GROUP,array(S_OTHER.SPACE.S_GROUPS.SPACE.'|'.SPACE, $cmbGroups)));
// -

/////////////////
/*
		$lstUsers = new CListBox('group_users_to_del[]');
		$lstUsers->attributes['style'] = 'width: 280px';

		foreach($group_users as $userid => $alias){
			$lstUsers->addItem($userid,	$alias);
		}

		$frmUserG->addRow(S_USERS,
			array(
				$lstUsers,
				BR(),
				new CButton('add_user',S_ADD,
					"return PopUp('popup_users.php?dstfrm=".$frmUserG->GetName().
					"&list_name=group_users_to_del[]&var_name=group_users',600,300);"),
				(count($group_users) > 0) ? new CButton('del_group_user',S_DELETE_SELECTED) : null
			));
*/
/////////////////

		$granted = true;
		if(isset($_REQUEST['usrgrpid'])){
			$granted = granted2update_group($_REQUEST['usrgrpid']);
		}

		if($granted){
			$cmbGUI = new CComboBox('gui_access',$gui_access);
			$cmbGUI->addItem(GROUP_GUI_ACCESS_SYSTEM,user_auth_type2str(GROUP_GUI_ACCESS_SYSTEM));
			$cmbGUI->addItem(GROUP_GUI_ACCESS_INTERNAL,user_auth_type2str(GROUP_GUI_ACCESS_INTERNAL));
			$cmbGUI->addItem(GROUP_GUI_ACCESS_DISABLED,user_auth_type2str(GROUP_GUI_ACCESS_DISABLED));
			$frmUserG->addRow(S_GUI_ACCESS, $cmbGUI);

			$cmbStat = new CComboBox('users_status',$users_status);
			$cmbStat->addItem(GROUP_STATUS_ENABLED,S_ENABLED);
			$cmbStat->addItem(GROUP_STATUS_DISABLED,S_DISABLED);

			$frmUserG->addRow(S_USERS_STATUS, $cmbStat);

		}
		else{
			$frmUserG->addVar('gui_access',$gui_access);
			$frmUserG->addRow(S_GUI_ACCESS, new CSpan(user_auth_type2str($gui_access),'green'));

			$frmUserG->addVar('users_status',GROUP_STATUS_ENABLED);
			$frmUserG->addRow(S_USERS_STATUS, new CSpan(S_ENABLED,'green'));
		}

		$cmbAPI = new CComboBox('api_access', $api_access);
		$cmbAPI->addItem(GROUP_API_ACCESS_ENABLED, S_ENABLED);
		$cmbAPI->addItem(GROUP_API_ACCESS_DISABLED, S_DISABLED);
		$frmUserG->addRow(S_API_ACCESS, $cmbAPI);

		$cmbDebug = new CComboBox('debug_mode', $debug_mode);
		$cmbDebug->addItem(GROUP_DEBUG_MODE_ENABLED, S_ENABLED);
		$cmbDebug->addItem(GROUP_DEBUG_MODE_DISABLED, S_DISABLED);
		$frmUserG->addRow(S_DEBUG_MODE, $cmbDebug);


		$table_Rights = new CTable(S_NO_RIGHTS_DEFINED,'right_table');

		$lstWrite = new CListBox('right_to_del[read_write][]'	,null	,20);
		$lstRead  = new CListBox('right_to_del[read_only][]'	,null	,20);
		$lstDeny  = new CListBox('right_to_del[deny][]'			,null	,20);

		foreach($group_rights as $name => $element_data){
			if($element_data['permission'] == PERM_DENY)			$lstDeny->addItem($name, $name);
			else if($element_data['permission'] == PERM_READ_ONLY)	$lstRead->addItem($name, $name);
			else if($element_data['permission'] == PERM_READ_WRITE)	$lstWrite->addItem($name, $name);
		}

		$table_Rights->setHeader(array(S_READ_WRITE, S_READ_ONLY, S_DENY),'header');
		$table_Rights->addRow(array(new CCol($lstWrite,'read_write'), new CCol($lstRead,'read_only'), new CCol($lstDeny,'deny')));
		$table_Rights->addRow(array(
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

		$frmUserG->addRow(S_RIGHTS,$table_Rights);

		$frmUserG->addVar('perm_details', $perm_details);

		$link = new CSpan($perm_details?S_HIDE:S_SHOW,'link');
		$link->OnClick("return create_var('".$frmUserG->GetName()."','perm_details',".($perm_details ? 0 : 1).", true);");
		$resources_list = array(
			S_RIGHTS_OF_RESOURCES,
			SPACE.'(',$link,')'
			);
		$frmUserG->addSpanRow($resources_list,'right_header');

		if($perm_details){
			$frmUserG->addSpanRow(get_rights_of_elements_table($group_rights));
		}

		$frmUserG->addItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["usrgrpid"])){
			$frmUserG->addItemToBottomRow(SPACE);
			$frmUserG->addItemToBottomRow(new CButtonDelete("Delete selected group?",
				url_param("form").url_param("config").url_param("usrgrpid")));
		}
		$frmUserG->addItemToBottomRow(SPACE);
		$frmUserG->addItemToBottomRow(new CButtonCancel(url_param("config")));
		
		return($frmUserG);
	}

	function get_rights_of_elements_table($rights=array(),$user_type=USER_TYPE_ZABBIX_USER){
		global $ZBX_LOCALNODEID;

		$table = new CTable('S_NO_ACCESSIBLE_RESOURCES', 'right_table');
		$table->setHeader(array(SPACE, S_READ_WRITE, S_READ_ONLY, S_DENY),'header');

		if(ZBX_DISTRIBUTED){
			$lst['node']['label']		= S_NODES;
			$lst['node']['read_write']	= new CListBox('nodes_write',null	,10);
			$lst['node']['read_only']	= new CListBox('nodes_read'	,null	,10);
			$lst['node']['deny']		= new CListBox('nodes_deny'	,null	,10);

			$nodes = get_accessible_nodes_by_rights($rights, $user_type, PERM_DENY, PERM_RES_DATA_ARRAY);
			foreach($nodes as $node){
				switch($node['permission']){
					case PERM_READ_ONLY:	$list_name='read_only';		break;
					case PERM_READ_WRITE:	$list_name='read_write';	break;
					default:		$list_name='deny';		break;
				}
				$lst['node'][$list_name]->addItem($node['nodeid'],$node['name']);
			}
			unset($nodes);
		}

		$lst['group']['label']		= S_HOST_GROUPS;
		$lst['group']['read_write']	= new CListBox('groups_write'	,null	,15);
		$lst['group']['read_only']	= new CListBox('groups_read'	,null	,15);
		$lst['group']['deny']		= new CListBox('groups_deny'	,null	,15);

		$groups = get_accessible_groups_by_rights($rights, $user_type, PERM_DENY, PERM_RES_DATA_ARRAY, get_current_nodeid(true));

		foreach($groups as $group){
			switch($group['permission']){
				case PERM_READ_ONLY:
					$list_name='read_only';
					break;
				case PERM_READ_WRITE:
					$list_name='read_write';
					break;
				default:
					$list_name='deny';
			}
			$lst['group'][$list_name]->addItem($group['groupid'],(!empty($group['node_name'])?$group['node_name'].':':$group['node_name']).$group['name']);
		}
		unset($groups);

		$lst['host']['label']		= S_HOSTS;
		$lst['host']['read_write']	= new CListBox('hosts_write'	,null	,15);
		$lst['host']['read_only']	= new CListBox('hosts_read'	,null	,15);
		$lst['host']['deny']		= new CListBox('hosts_deny'	,null	,15);

		$hosts = get_accessible_hosts_by_rights($rights, $user_type, PERM_DENY, PERM_RES_DATA_ARRAY, get_current_nodeid(true));

		foreach($hosts as $host){
			switch($host['permission']){
				case PERM_READ_ONLY:	$list_name='read_only';		break;
				case PERM_READ_WRITE:	$list_name='read_write';	break;
				default:		$list_name='deny';		break;
			}
			$lst['host'][$list_name]->addItem($host['hostid'], (!empty($host['node_name'])?$host['node_name'].':':$host['node_name']).$host['host']);
		}
		unset($hosts);

		foreach($lst as $name => $lists){
			$row = new CRow();
			foreach($lists as $class => $list_obj){
				$row->addItem(new CCol($list_obj, $class));
			}
			$table->addRow($row);
		}
		unset($lst);

		return $table;
	}

/* ITEMS FILTER functions { --->>> */
	function prepare_subfilter_output($data, $subfilter, $subfilter_name){

		$output = array();
		order_result($data, 'name');
		foreach($data as $id => $elem){

// subfilter is activated
			if(str_in_array($id, $subfilter)){
				$span = new CSpan($elem['name'].' ('.$elem['count'].')', 'subfilter_enabled');
				$script = "javascript: create_var('zbx_filter', '".$subfilter_name.'['.$id."]', null, true);";
				$span->onClick($script);
				$output[] = $span;
			}
// subfilter isn't activated
			else{
				$script = "javascript: create_var('zbx_filter', '".$subfilter_name.'['.$id."]', '$id', true);";

// subfilter has 0 items
				if($elem['count'] == 0){
					$span = new CSpan($elem['name'].' ('.$elem['count'].')', 'subfilter_inactive');
					$span->onClick($script);
					$output[] = $span;
				}
				else{
					// this level has no active subfilters
					if(empty($subfilter)){
						$nspan = new CSpan(' ('.$elem['count'].')', 'subfilter_active');
					}
					else{
						$nspan = new CSpan(' (+'.$elem['count'].')', 'subfilter_active');
					}
					$span = new CSpan($elem['name'], 'subfilter_disabled');
					$span->onClick($script);

					$output[] = $span;
					$output[] = $nspan;
				}
			}
			$output[] = ' , ';
		}
		array_pop($output);

		return $output;
	}

	function get_item_filter_form(&$items){

		$filter_group			= $_REQUEST['filter_group'];
		$filter_host			= $_REQUEST['filter_host'];
		$filter_application		= $_REQUEST['filter_application'];
		$filter_description		= $_REQUEST['filter_description'];
		$filter_type			= $_REQUEST['filter_type'];
		$filter_key				= $_REQUEST['filter_key'];
		$filter_snmp_community		= $_REQUEST['filter_snmp_community'];
		$filter_snmp_oid			= $_REQUEST['filter_snmp_oid'];
		$filter_snmp_port			= $_REQUEST['filter_snmp_port'];
		$filter_value_type		= $_REQUEST['filter_value_type'];
		$filter_data_type = $_REQUEST['filter_data_type'];
		$filter_delay			= $_REQUEST['filter_delay'];
		$filter_history			= $_REQUEST['filter_history'];
		$filter_trends			= $_REQUEST['filter_trends'];
		$filter_status			= $_REQUEST['filter_status'];
		$filter_templated_items			= $_REQUEST['filter_templated_items'];
		$filter_with_triggers			= $_REQUEST['filter_with_triggers'];

// subfilter
		$subfilter_hosts = $_REQUEST['subfilter_hosts'];
		$subfilter_apps = $_REQUEST['subfilter_apps'];
		$subfilter_types = $_REQUEST['subfilter_types'];
		$subfilter_value_types = $_REQUEST['subfilter_value_types'];
		$subfilter_status = $_REQUEST['subfilter_status'];
		$subfilter_templated_items = $_REQUEST['subfilter_templated_items'];
		$subfilter_with_triggers = $_REQUEST['subfilter_with_triggers'];
		$subfilter_history = $_REQUEST['subfilter_history'];
		$subfilter_trends = $_REQUEST['subfilter_trends'];
		$subfilter_interval = $_REQUEST['subfilter_interval'];

		$form = new CForm();
		$form->setAttribute('name','zbx_filter');
		$form->setAttribute('id','zbx_filter');
		$form->setMethod('get');
		$form->addVar('filter_hostid',get_request('filter_hostid',get_request('hostid')));

		$form->addVar('subfilter_hosts', $subfilter_hosts);
		$form->addVar('subfilter_apps', $subfilter_apps);
		$form->addVar('subfilter_types', $subfilter_types);
		$form->addVar('subfilter_value_types', $subfilter_value_types);
		$form->addVar('subfilter_status', $subfilter_status);
		$form->addVar('subfilter_templated_items', $subfilter_templated_items);
		$form->addVar('subfilter_with_triggers', $subfilter_with_triggers);
		$form->addVar('subfilter_history', $subfilter_history);
		$form->addVar('subfilter_trends', $subfilter_trends);
		$form->addVar('subfilter_interval', $subfilter_interval);

// FORM FOR FILTER DISPLAY {
		$table = new CTable();
		$table->setAttribute('style', 'border: 1px solid #777777; width: 100%; background-color: white;');
		$table->setCellPadding(0);
		$table->setCellSpacing(0);

// 1st col
		$col_table1 = new CTable();
		$col_table1->setClass('filter');
		$col_table1->addRow(array(bold(S_HOST_GROUP.': '),
				array(new CTextBox('filter_group', $filter_group, 20),
					new CButton('btn_group', S_SELECT, 'return PopUp("popup.php?dstfrm='.$form->GetName().
						'&dstfld1=filter_group&srctbl=host_group&srcfld1=name",450,450);', 'G'))
		));
		$col_table1->addRow(array(bold(S_HOST.': '),
				array(new CTextBox('filter_host', $filter_host, 20),
					new CButton('btn_host', S_SELECT, 'return PopUp("popup.php?dstfrm='.$form->GetName().
						'&dstfld1=filter_host&srctbl=hosts&srcfld1=host",450,450);', 'H'))
		));
		$col_table1->addRow(array(bold(S_APPLICATION.': '),
				array(new CTextBox('filter_application', $filter_application, 20),
					new CButton('btn_app', S_SELECT, 'return PopUp("popup.php?dstfrm='.$form->GetName().
						'&dstfld1=filter_application&srctbl=applications&srcfld1=name",400,300,"application");', 'A'))
		));
		$col_table1->addRow(array(array(bold(S_DESCRIPTION),SPACE.S_LIKE_SMALL),
			new CTextBox("filter_description", $filter_description, 30)));
		$col_table1->addRow(array(array(bold(S_KEY),SPACE.S_LIKE_SMALL),
			new CTextBox("filter_key", $filter_key, 30)));

// 2nd col
		$col_table2 = new CTable();
		$col_table2->setClass('filter');

		$cmbType = new CComboBox("filter_type", $filter_type, "javascript: create_var('zbx_filter', 'filter_set', '1', true); ");
		$cmbType->addItem(-1, S_ALL_SMALL);
		foreach(array(ITEM_TYPE_ZABBIX, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_SIMPLE,
			ITEM_TYPE_SNMPV1, ITEM_TYPE_SNMPV2C, ITEM_TYPE_SNMPV3, ITEM_TYPE_TRAPPER,
			ITEM_TYPE_INTERNAL, ITEM_TYPE_AGGREGATE, ITEM_TYPE_HTTPTEST,
			ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET,
			ITEM_TYPE_CALCULATED) as $it)
				$cmbType->addItem($it, item_type2str($it));
		$col_table2->addRow(array(bold(S_TYPE.': '), $cmbType));

		if(($filter_type != ITEM_TYPE_TRAPPER) && ($filter_type != ITEM_TYPE_HTTPTEST)){
			$col_table2->addRow(array(bold(S_UPDATE_INTERVAL_IN_SEC),
				new CNumericBox('filter_delay', $filter_delay, 5, null, true)));
		}
		else{
			$col_table2->addRow(SPACE, SPACE);
		}

		if(uint_in_array($filter_type, array(ITEM_TYPE_SNMPV1,ITEM_TYPE_SNMPV2C,ITEM_TYPE_SNMPV3))){
			$col_table2->addRow(array(array(bold(S_SNMP_COMMUNITY),SPACE.S_LIKE_SMALL),
				new CTextBox("filter_snmp_community", $filter_snmp_community, 16)));
			$col_table2->addRow(array(array(bold(S_SNMP_OID),SPACE.S_LIKE_SMALL),
				new CTextBox("filter_snmp_oid", $filter_snmp_oid, 40)));
			$col_table2->addRow(array(array(bold(S_SNMP_PORT),SPACE.S_LIKE_SMALL),
				new CNumericBox("filter_snmp_port", $filter_snmp_port, 5 ,null, true)));
		}
		else{
			$col_table2->addRow(array(SPACE,SPACE));
			$col_table2->addRow(array(SPACE,SPACE));
			$col_table2->addRow(array(SPACE,SPACE));
		}

// 3rd col
		$col_table3 = new CTable();
		$col_table3->setClass('filter');
		$cmbValType = new CComboBox('filter_value_type', $filter_value_type, "javascript: create_var('zbx_filter', 'filter_set', '1', true);");
			$cmbValType->addItem(-1, S_ALL_SMALL);
			$cmbValType->addItem(ITEM_VALUE_TYPE_UINT64, S_NUMERIC_UNSIGNED);
			$cmbValType->addItem(ITEM_VALUE_TYPE_FLOAT, S_NUMERIC_FLOAT);
			$cmbValType->addItem(ITEM_VALUE_TYPE_STR, S_CHARACTER);
			$cmbValType->addItem(ITEM_VALUE_TYPE_LOG, S_LOG);
			$cmbValType->addItem(ITEM_VALUE_TYPE_TEXT, S_TEXT);
			$col_table3->addRow(array(bold(S_TYPE_OF_INFORMATION.': '), $cmbValType));

		if($filter_value_type == ITEM_VALUE_TYPE_UINT64){
			$cmbDataType = new CComboBox('filter_data_type', $filter_data_type, 'submit()');
			$cmbDataType->addItem(-1, S_ALL_SMALL);
			$cmbDataType->addItem(ITEM_DATA_TYPE_DECIMAL, item_data_type2str(ITEM_DATA_TYPE_DECIMAL));
			$cmbDataType->addItem(ITEM_DATA_TYPE_OCTAL, item_data_type2str(ITEM_DATA_TYPE_OCTAL));
			$cmbDataType->addItem(ITEM_DATA_TYPE_HEXADECIMAL, item_data_type2str(ITEM_DATA_TYPE_HEXADECIMAL));
			$col_table3->addRow(array(bold(S_DATA_TYPE.': '), $cmbDataType));
		}
		else{
			$col_table3->addRow(array(SPACE,SPACE));
		}
		$col_table3->addRow(array(bold(S_KEEP_HISTORY_IN_DAYS.': '), new CNumericBox('filter_history',$filter_history,8,null,true)));
		$col_table3->addRow(array(bold(S_KEEP_TRENDS_IN_DAYS.': '), new CNumericBox('filter_trends',$filter_trends,8,null,true)));

// 4th col
		$col_table4 = new CTable();
		$col_table4->setClass('filter');

		$cmbStatus = new CComboBox('filter_status',$filter_status);
		$cmbStatus->addItem(-1,S_ALL_SMALL);
		foreach(array(ITEM_STATUS_ACTIVE,ITEM_STATUS_DISABLED,ITEM_STATUS_NOTSUPPORTED) as $st)
			$cmbStatus->addItem($st,item_status2str($st));

		$cmbBelongs = new CComboBox('filter_templated_items', $filter_templated_items);
		$cmbBelongs->addItem(-1, S_ALL_SMALL);
		$cmbBelongs->addItem(1, S_TEMPLATED_ITEMS);
		$cmbBelongs->addItem(0, S_NOT_TEMPLATED_ITEMS);

		$cmbWithTriggers = new CComboBox('filter_with_triggers', $filter_with_triggers);
		$cmbWithTriggers->addItem(-1, S_ALL_SMALL);
		$cmbWithTriggers->addItem(1, S_WITH_TRIGGERS);
		$cmbWithTriggers->addItem(0, S_WITHOUT_TRIGGERS);

		$col_table4->addRow(array(bold(S_STATUS.': '), $cmbStatus));
		$col_table4->addRow(array(bold(S_TRIGGERS.': '), $cmbWithTriggers));
		$col_table4->addRow(array(bold(S_TEMPLATE.': '), $cmbBelongs));


		$table->addRow(array(
			new CCol($col_table1, 'top'), new CCol($col_table2, 'top'), new CCol($col_table3, 'top'), new CCol($col_table4, 'top')));

		$reset = new CSpan( S_RESET,'biglink');
		$reset->onClick("javascript: clearAllForm('zbx_filter');");
		$filter = new CSpan(S_FILTER,'biglink');
		$filter->onClick("javascript: create_var('zbx_filter', 'filter_set', '1', true);");

		$div_buttons = new CDiv(array($filter, SPACE, SPACE, SPACE, $reset));
		$div_buttons->setAttribute('style', 'padding: 4px 0;');
		$footer = new CCol($div_buttons, 'center');
		$footer->setColSpan(4);

		$table->addRow($footer);
		$form->addItem($table);

// } FORM FOR FILTER DISPLAY

// SUBFILTERS {
		$header = get_thin_table_header(S_SUBFILTER.SPACE.'['.S_AFFECTS_ONLY_FILTERED_DATA_SMALL.']');
		$form->addItem($header);
		$table_subfilter = new Ctable();
		$table_subfilter->setClass('filter');


// array contains subfilters and number of items in each
		$item_params = array(
			'hosts' => array(),
			'applications' => array(),
			'types' => array(),
			'value_types' => array(),
			'status' => array(),
			'templated_items' => array(),
			'with_triggers' => array(),
			'history' => array(),
			'trends' => array(),
			'interval' => array()
		);

// generate array with values for subfilters of selected items
		foreach($items as $num => $item){
			if(zbx_empty($filter_host)){
// hosts
				$host = reset($item['hosts']);

				if(!isset($item_params['hosts'][$host['hostid']])){
					$item_params['hosts'][$host['hostid']] = array('name' => $host['host'], 'count' => 0);
				}
				$show_item = true;
				foreach($item['subfilters'] as $name => $value){
					if($name == 'subfilter_hosts') continue;
					$show_item &= $value;
				}
				if($show_item){
					$host = reset($item['hosts']);
					$item_params['hosts'][$host['hostid']]['count']++;
				}
			}

// applications
			foreach($item['applications'] as $appid => $app){
				if(!isset($item_params['applications'][$app['name']])){
					$item_params['applications'][$app['name']] = array('name' => $app['name'], 'count' => 0);
				}
			}
			$show_item = true;
			foreach($item['subfilters'] as $name => $value){
				if($name == 'subfilter_apps') continue;
				$show_item &= $value;
			}
			$sel_app = false;
			if($show_item){
// if any of item applications are selected
				foreach($item['applications'] as $app){
					if(str_in_array($app['name'], $subfilter_apps)){
						$sel_app = true;
						break;
					}
				}

				foreach($item['applications'] as $app){
					if(str_in_array($app['name'], $subfilter_apps) || !$sel_app){
						$item_params['applications'][$app['name']]['count']++;
					}
				}
			}

// types
			if($filter_type == -1){
				if(!isset($item_params['types'][$item['type']])){
					$item_params['types'][$item['type']] = array('name' => item_type2str($item['type']), 'count' => 0);
				}
				$show_item = true;
				foreach($item['subfilters'] as $name => $value){
					if($name == 'subfilter_types') continue;
					$show_item &= $value;
				}
				if($show_item){
					$item_params['types'][$item['type']]['count']++;
				}
			}

			// value types
			if($filter_value_type == -1){
				if(!isset($item_params['value_types'][$item['value_type']])){
					$item_params['value_types'][$item['value_type']] = array('name' => item_value_type2str($item['value_type']), 'count' => 0);
				}
				$show_item = true;
				foreach($item['subfilters'] as $name => $value){
					if($name == 'subfilter_value_types') continue;
					$show_item &= $value;
				}
				if($show_item){
					$item_params['value_types'][$item['value_type']]['count']++;
				}
			}

			// status
			if($filter_status == -1){
				if(!isset($item_params['status'][$item['status']])){
					$item_params['status'][$item['status']] = array('name' => item_status2str($item['status']), 'count' => 0);
				}
				$show_item = true;
				foreach($item['subfilters'] as $name => $value){
					if($name == 'subfilter_status') continue;
					$show_item &= $value;
				}
				if($show_item){
					$item_params['status'][$item['status']]['count']++;
				}
			}

// template
			if($filter_templated_items == -1){
				if(($item['templateid'] == 0) && !isset($item_params['templated_items'][0])){
					$item_params['templated_items'][0] = array('name' => S_NOT_TEMPLATED_ITEMS, 'count' => 0);
				}
				else if(($item['templateid'] > 0) && !isset($item_params['templated_items'][1])){
					$item_params['templated_items'][1] = array('name' => S_TEMPLATED_ITEMS, 'count' => 0);
				}
				$show_item = true;
				foreach($item['subfilters'] as $name => $value){
					if($name == 'subfilter_templated_items') continue;
					$show_item &= $value;
				}
				if($show_item){
					if($item['templateid'] == 0){
						$item_params['templated_items'][0]['count']++;
					}
					else{
						$item_params['templated_items'][1]['count']++;
					}
				}
			}

// with triggers
			if($filter_with_triggers == -1){
				if((count($item['triggers']) == 0) && !isset($item_params['with_triggers'][0])){
					$item_params['with_triggers'][0] = array('name' => S_WITHOUT_TRIGGERS, 'count' => 0);
				}
				else if((count($item['triggers']) > 0) && !isset($item_params['with_triggers'][1])){
					$item_params['with_triggers'][1] = array('name' => S_WITH_TRIGGERS, 'count' => 0);
				}
				$show_item = true;
				foreach($item['subfilters'] as $name => $value){
					if($name == 'subfilter_with_triggers') continue;
					$show_item &= $value;
				}
				if($show_item){
					if(count($item['triggers']) == 0){
						$item_params['with_triggers'][0]['count']++;
					}
					else{
						$item_params['with_triggers'][1]['count']++;
					}
				}
			}

// trends
			if(zbx_empty($filter_trends)){
				if(!isset($item_params['trends'][$item['trends']])){
					$item_params['trends'][$item['trends']] = array('name' => $item['trends'], 'count' => 0);
				}
				$show_item = true;
				foreach($item['subfilters'] as $name => $value){
					if($name == 'subfilter_trends') continue;
					$show_item &= $value;
				}
				if($show_item){
					$item_params['trends'][$item['trends']]['count']++;
				}
			}

// history
			if(zbx_empty($filter_history)){
				if(!isset($item_params['history'][$item['history']])){
					$item_params['history'][$item['history']] = array('name' => $item['history'], 'count' => 0);
				}
				$show_item = true;
				foreach($item['subfilters'] as $name => $value){
					if($name == 'subfilter_history') continue;
					$show_item &= $value;
				}
				if($show_item){
					$item_params['history'][$item['history']]['count']++;
				}
			}

// interval
			if(zbx_empty($filter_delay) && ($filter_type != ITEM_TYPE_TRAPPER)){
				if(!isset($item_params['interval'][$item['delay']])){
					$item_params['interval'][$item['delay']] = array('name' => $item['delay'], 'count' => 0);
				}
				$show_item = true;
				foreach($item['subfilters'] as $name => $value){
					if($name == 'subfilter_interval') continue;
					$show_item &= $value;
				}
				if($show_item){
					$item_params['interval'][$item['delay']]['count']++;
				}
			}
		}

// output
		if(zbx_empty($filter_host) && (count($item_params['hosts']) > 1)){
			$hosts_output = prepare_subfilter_output($item_params['hosts'], $subfilter_hosts, 'subfilter_hosts');
			$table_subfilter->addRow(array(S_HOSTS, $hosts_output));
		}

		if(!empty($item_params['applications']) && (count($item_params['applications']) > 1)){
			$application_output = prepare_subfilter_output($item_params['applications'], $subfilter_apps, 'subfilter_apps');
			$table_subfilter->addRow(array(S_APPLICATIONS, $application_output));
		}

		if(($filter_type == -1) && (count($item_params['types']) > 1)){
			$type_output = prepare_subfilter_output($item_params['types'], $subfilter_types, 'subfilter_types');
			$table_subfilter->addRow(array(S_TYPES, $type_output));
		}

		if(($filter_value_type == -1) && (count($item_params['value_types']) > 1)){
			$value_types_output = prepare_subfilter_output($item_params['value_types'], $subfilter_value_types, 'subfilter_value_types');
			$table_subfilter->addRow(array(S_TYPE_OF_INFORMATION, $value_types_output));
		}

		if(($filter_status == -1) && (count($item_params['status']) > 1)){
			$status_output = prepare_subfilter_output($item_params['status'], $subfilter_status, 'subfilter_status');
			$table_subfilter->addRow(array(S_STATUS, $status_output));
		}

		if(($filter_templated_items == -1) && (count($item_params['templated_items']) > 1)){
			$templated_items_output = prepare_subfilter_output($item_params['templated_items'], $subfilter_templated_items, 'subfilter_templated_items');
			$table_subfilter->addRow(array(S_TEMPLATE, $templated_items_output));
		}

		if(($filter_with_triggers == -1) && (count($item_params['with_triggers']) > 1)){
			$with_triggers_output = prepare_subfilter_output($item_params['with_triggers'], $subfilter_with_triggers, 'subfilter_with_triggers');
			$table_subfilter->addRow(array(S_WITH_TRIGGERS, $with_triggers_output));
		}

		if(zbx_empty($filter_history) && (count($item_params['history']) > 1)){
			$history_output = prepare_subfilter_output($item_params['history'], $subfilter_history, 'subfilter_history');
			$table_subfilter->addRow(array(S_HISTORY, $history_output));
		}

		if(zbx_empty($filter_trends) && (count($item_params['trends']) > 1)){
			$trends_output = prepare_subfilter_output($item_params['trends'], $subfilter_trends, 'subfilter_trends');
			$table_subfilter->addRow(array(S_TRENDS, $trends_output));
		}

		if(zbx_empty($filter_delay) && ($filter_type != ITEM_TYPE_TRAPPER) && (count($item_params['interval']) > 1)){
			$interval_output = prepare_subfilter_output($item_params['interval'], $subfilter_interval, 'subfilter_interval');
			$table_subfilter->addRow(array(S_INTERVAL, $interval_output));
		}
//} SUBFILTERS

		$form->addItem($table_subfilter);

	return $form;
	}

// Insert form for Item information
	function insert_item_form(){
		global $USER_DETAILS;

		$frmItem = new CFormTable(S_ITEM, 'items.php', 'post');
		$frmItem->setHelp('web.items.item.php');

		$hostid = get_request('form_hostid', 0);

		$description		= get_request('description','');
		$key				= get_request('key',		'');
		$host				= get_request('host',		null);
		$delay				= get_request('delay',		30);
		$history			= get_request('history',	90);
		$status				= get_request('status',		0);
		$type				= get_request('type',		0);
		$snmp_community		= get_request('snmp_community' ,'public');
		$snmp_oid			= get_request('snmp_oid',	'interfaces.ifTable.ifEntry.ifInOctets.1');
		$snmp_port			= get_request('snmp_port',	161);
		$value_type			= get_request('value_type',	ITEM_VALUE_TYPE_UINT64);
		$data_type			= get_request('data_type',	ITEM_DATA_TYPE_DECIMAL);
		$trapper_hosts		= get_request('trapper_hosts'	,'');
		$units				= get_request('units',		'');
		$valuemapid			= get_request('valuemapid',	0);
		$params				= get_request('params',		'');
		$multiplier			= get_request('multiplier',	0);
		$delta				= get_request('delta',		0);
		$trends				= get_request('trends',		365);
		$new_application	= get_request('new_application','');
		$applications		= get_request('applications',array());
		$delay_flex			= get_request('delay_flex'	,array());

		$snmpv3_securityname	= get_request('snmpv3_securityname'	,'');
		$snmpv3_securitylevel	= get_request('snmpv3_securitylevel'	,0);
		$snmpv3_authpassphrase	= get_request('snmpv3_authpassphrase'	,'');
		$snmpv3_privpassphrase	= get_request('snmpv3_privpassphrase'	,'');
		$ipmi_sensor		= get_request('ipmi_sensor'		,'');
		$authtype		= get_request('authtype'		,0);
		$username		= get_request('username'		,'');
		$password		= get_request('password'		,'');
		$publickey		= get_request('publickey'		,'');
		$privatekey		= get_request('privatekey'		,'');

		$formula	= get_request('formula'		,'1');
		$logtimefmt	= get_request('logtimefmt'	,'');

		$add_groupid	= get_request('add_groupid', get_request('groupid', 0));

		$limited = null;

		switch ($type) {
		case ITEM_TYPE_DB_MONITOR:
			if (zbx_empty($key) || $key == 'ssh.run[<unique short description>,<ip>,<port>,<encoding>]' ||
					$key == 'telnet.run[<unique short description>,<ip>,<port>,<encoding>]')
				$key = 'db.odbc.select[<unique short description>]';
			if (zbx_empty($params))
				$params = "DSN=<database source name>\nuser=<user name>\npassword=<password>\nsql=<query>";
			break;
		case ITEM_TYPE_SSH:
			if (zbx_empty($key) || $key == 'db.odbc.select[<unique short description>]' ||
					$key == 'telnet.run[<unique short description>,<ip>,<port>,<encoding>]')
				$key = 'ssh.run[<unique short description>,<ip>,<port>,<encoding>]';
			if (0 == strncmp($params, "DSN=<database source name>", 26))
				$params = '';
			break;
		case ITEM_TYPE_TELNET:
			if (zbx_empty($key) || $key == 'db.odbc.select[<unique short description>]' ||
					$key == 'ssh.run[<unique short description>,<ip>,<port>,<encoding>]')
				$key = 'telnet.run[<unique short description>,<ip>,<port>,<encoding>]';
			if (0 == strncmp($params, "DSN=<database source name>", 26))
				$params = '';
			break;
		default:
			if ($key == 'db.odbc.select[<unique short description>]' ||
					$key == 'ssh.run[<unique short description>,<ip>,<port>,<encoding>]' ||
					$key == 'telnet.run[<unique short description>,<ip>,<port>,<encoding>]')
				$key = '';
			if (0 == strncmp($params, "DSN=<database source name>", 26))
				$params = '';
			break;
		}

		if(isset($_REQUEST['itemid'])){
			$frmItem->addVar('itemid', $_REQUEST['itemid']);

			$options = array(
				'itemids' => $_REQUEST['itemid'],
				'output' => API_OUTPUT_EXTEND
			);
			$item_data = CItem::get($options);
			$item_data = reset($item_data);

			$hostid	= ($hostid > 0) ? $hostid : $item_data['hostid'];
			$limited = (($item_data['templateid'] == 0)  && ($item_data['type'] != ITEM_TYPE_HTTPTEST)) ? null : 'yes';
		}

		if(is_null($host)){
			if($hostid > 0){
				$host_info = CHost::get(array('hostids' => $hostid, 'extendoutput' => 1, 'templated_hosts' => 1));
				$host_info = reset($host_info);
				$host = $host_info['host'];
			}
			else{
				$host = S_NOT_SELECTED_SMALL;
			}
		}

		if((isset($_REQUEST['itemid']) && !isset($_REQUEST['form_refresh'])) || isset($limited)){
			$description		= $item_data['description'];
			$key				= $item_data['key_'];
//			$host				= $item_data['host'];
			$type				= $item_data['type'];
			$snmp_community		= $item_data['snmp_community'];
			$snmp_oid			= $item_data['snmp_oid'];
			$snmp_port			= $item_data['snmp_port'];
			$value_type			= $item_data['value_type'];
			$data_type			= $item_data['data_type'];
			$trapper_hosts		= $item_data['trapper_hosts'];
			$units			= $item_data['units'];
			$valuemapid		= $item_data['valuemapid'];
			$multiplier		= $item_data['multiplier'];
			$hostid			= $item_data['hostid'];
			$params			= $item_data['params'];

			$snmpv3_securityname	= $item_data['snmpv3_securityname'];
			$snmpv3_securitylevel	= $item_data['snmpv3_securitylevel'];
			$snmpv3_authpassphrase	= $item_data['snmpv3_authpassphrase'];
			$snmpv3_privpassphrase	= $item_data['snmpv3_privpassphrase'];

			$ipmi_sensor		= $item_data['ipmi_sensor'];

			$authtype		= $item_data['authtype'];
			$username		= $item_data['username'];
			$password		= $item_data['password'];
			$publickey		= $item_data['publickey'];
			$privatekey		= $item_data['privatekey'];

			$formula	= $item_data['formula'];
			$logtimefmt	= $item_data['logtimefmt'];

			$new_application = get_request('new_application',	'');

			if(!isset($limited) || !isset($_REQUEST['form_refresh'])){
				$delay		= $item_data['delay'];
				$history	= $item_data['history'];
				$status		= $item_data['status'];
				$delta		= $item_data['delta'];
				$trends		= $item_data['trends'];
				$db_delay_flex	= $item_data['delay_flex'];

				if(isset($db_delay_flex)){
					$arr_of_dellays = explode(';',$db_delay_flex);
					foreach($arr_of_dellays as $one_db_delay){
						$arr_of_delay = explode('/',$one_db_delay);
						if(!isset($arr_of_delay[0]) || !isset($arr_of_delay[1])) continue;

						array_push($delay_flex,array('delay'=>$arr_of_delay[0],'period'=>$arr_of_delay[1]));
					}
				}

				$applications = array_unique(zbx_array_merge($applications, get_applications_by_itemid($_REQUEST['itemid'])));
			}
		}

		$delay_flex_el = array();

		if($type != ITEM_TYPE_TRAPPER && $type != ITEM_TYPE_HTTPTEST){
			$i = 0;
			foreach($delay_flex as $val){
				if(!isset($val['delay']) && !isset($val['period'])) continue;

				array_push($delay_flex_el,
					array(
						new CCheckBox('rem_delay_flex[]', 'no', null,$i),
							$val['delay'],
							' sec at ',
							$val['period']
					),
					BR());
				$frmItem->addVar('delay_flex['.$i.'][delay]', $val['delay']);
				$frmItem->addVar('delay_flex['.$i.'][period]', $val['period']);
				$i++;
				if($i >= 7) break;	/* limit count of  intervals
							* 7 intervals by 30 symbols = 210 characters
							* db storage field is 256
							*/
			}
		}

		if(count($delay_flex_el)==0)
			array_push($delay_flex_el, S_NO_FLEXIBLE_INTERVALS);
		else
			array_push($delay_flex_el, new CButton('del_delay_flex',S_DELETE_SELECTED));

		if(count($applications)==0)  array_push($applications,0);

		if(isset($_REQUEST['itemid'])) {
			$frmItem->setTitle(S_ITEM." '$host:".$item_data["description"]."'");
		}
		else {
			$frmItem->setTitle(S_ITEM." '$host:$description'");
		}

		$frmItem->addVar('form_hostid', $hostid);
		$frmItem->addRow(S_HOST,array(
			new CTextBox('host',$host,32,true),
			new CButton('btn_host', S_SELECT,
				"return PopUp('popup.php?dstfrm=".$frmItem->getName().
				"&dstfld1=host&dstfld2=form_hostid&srctbl=hosts_and_templates&srcfld1=host&srcfld2=hostid',450,450);",
				'H')
			));

		$frmItem->addRow(S_DESCRIPTION, new CTextBox('description',$description,40, $limited));

		if(isset($limited)){
			$frmItem->addRow(S_TYPE,  new CTextBox('typename', item_type2str($type), 40, 'yes'));
			$frmItem->addVar('type', $type);
		}
		else{
			$cmbType = new CComboBox('type',$type,'submit()');
			foreach(array(ITEM_TYPE_ZABBIX,ITEM_TYPE_ZABBIX_ACTIVE,ITEM_TYPE_SIMPLE,
				ITEM_TYPE_SNMPV1,ITEM_TYPE_SNMPV2C,ITEM_TYPE_SNMPV3,ITEM_TYPE_TRAPPER,
				ITEM_TYPE_INTERNAL,ITEM_TYPE_AGGREGATE,ITEM_TYPE_EXTERNAL,
				ITEM_TYPE_DB_MONITOR,ITEM_TYPE_IPMI,ITEM_TYPE_SSH,ITEM_TYPE_TELNET,
				ITEM_TYPE_CALCULATED) as $it)
					$cmbType->addItem($it,item_type2str($it));
			$frmItem->addRow(S_TYPE, $cmbType);
		}

		if(($type==ITEM_TYPE_SNMPV1)||($type==ITEM_TYPE_SNMPV2C)){
			$frmItem->addVar('snmpv3_securityname',$snmpv3_securityname);
			$frmItem->addVar('snmpv3_securitylevel',$snmpv3_securitylevel);
			$frmItem->addVar('snmpv3_authpassphrase',$snmpv3_authpassphrase);
			$frmItem->addVar('snmpv3_privpassphrase',$snmpv3_privpassphrase);

			$frmItem->addRow(S_SNMP_OID, new CTextBox('snmp_oid',$snmp_oid,40,$limited));
			$frmItem->addRow(S_SNMP_COMMUNITY, new CTextBox('snmp_community',$snmp_community,16));
			$frmItem->addRow(S_SNMP_PORT, new CNumericBox('snmp_port',$snmp_port,5));
		}
		else if($type==ITEM_TYPE_SNMPV3){
			$frmItem->addVar('snmp_community',$snmp_community);

			$frmItem->addRow(S_SNMP_OID, new CTextBox('snmp_oid',$snmp_oid,40,$limited));

			$frmItem->addRow(S_SNMPV3_SECURITY_NAME, new CTextBox('snmpv3_securityname',$snmpv3_securityname,64));

			$cmbSecLevel = new CComboBox('snmpv3_securitylevel',$snmpv3_securitylevel);
				$cmbSecLevel->addItem(ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV,'NoAuthPriv');
				$cmbSecLevel->addItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV,'AuthNoPriv');
				$cmbSecLevel->addItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV,'AuthPriv');
			$frmItem->addRow(S_SNMPV3_SECURITY_LEVEL, $cmbSecLevel);

			$frmItem->addRow(S_SNMPV3_AUTH_PASSPHRASE, new CTextBox('snmpv3_authpassphrase',$snmpv3_authpassphrase,64));

			$frmItem->addRow(S_SNMPV3_PRIV_PASSPHRASE, new CTextBox('snmpv3_privpassphrase',$snmpv3_privpassphrase,64));

			$frmItem->addRow(S_SNMP_PORT, new CNumericBox('snmp_port',$snmp_port,5));
		}
		else{
			$frmItem->addVar('snmp_community',$snmp_community);
			$frmItem->addVar('snmp_oid',$snmp_oid);
			$frmItem->addVar('snmp_port',$snmp_port);
			$frmItem->addVar('snmpv3_securityname',$snmpv3_securityname);
			$frmItem->addVar('snmpv3_securitylevel',$snmpv3_securitylevel);
			$frmItem->addVar('snmpv3_authpassphrase',$snmpv3_authpassphrase);
			$frmItem->addVar('snmpv3_privpassphrase',$snmpv3_privpassphrase);
		}

		if ($type == ITEM_TYPE_IPMI)
		{
			$frmItem->addRow(S_IPMI_SENSOR, new CTextBox('ipmi_sensor', $ipmi_sensor, 64, $limited));
		}
		else
		{
			$frmItem->addVar('ipmi_sensor', $ipmi_sensor);
		}

		if(isset($limited)){
			$btnSelect = null;
		}
		else{
			$btnSelect = new CButton('btn1',S_SELECT,
				"return PopUp('popup.php?dstfrm=".$frmItem->GetName().
				"&dstfld1=key&srctbl=help_items&srcfld1=key_&itemtype=".$type."');",
				'T');
		}

		$frmItem->addRow(S_KEY, array(new CTextBox('key',$key,40,$limited), $btnSelect));

		if (ITEM_TYPE_SSH == $type) {
			$cmbAuthType = new CComboBox('authtype',$authtype,'submit()');
				$cmbAuthType->addItem(ITEM_AUTHTYPE_PASSWORD,S_PASSWORD);
				$cmbAuthType->addItem(ITEM_AUTHTYPE_PUBLICKEY,S_PUBLIC_KEY);
			$frmItem->addRow(S_AUTHENTICATION_METHOD, $cmbAuthType);
			$frmItem->addRow(S_USER_NAME, new CTextBox('username',$username,16));
			if ($authtype == ITEM_AUTHTYPE_PASSWORD) {
				$frmItem->addVar('publickey',$publickey);
				$frmItem->addVar('privatekey',$privatekey);
				$frmItem->addRow(S_PASSWORD, new CTextBox('password',$password,16));
			}
			else {
				$frmItem->addRow(S_PUBLIC_KEY_FILE, new CTextBox('publickey',$publickey,16));
				$frmItem->addRow(S_PRIVATE_KEY_FILE, new CTextBox('privatekey',$privatekey,16));
				$frmItem->addRow(S_PASSPHRASE, new CTextBox('password',$password,16));
			}
			$frmItem->addRow(S_EXECUTED_SCRIPT, new CTextArea('params',$params,60,4));
		}
		else if (ITEM_TYPE_TELNET == $type) {
			$frmItem->addVar('authtype',$authtype);
			$frmItem->addRow(S_USER_NAME, new CTextBox('username',$username,16));
			$frmItem->addVar('publickey',$publickey);
			$frmItem->addVar('privatekey',$privatekey);
			$frmItem->addRow(S_PASSWORD, new CTextBox('password',$password,16));
			$frmItem->addRow(S_EXECUTED_SCRIPT, new CTextArea('params',$params,60,4));
		}
		else{
			$frmItem->addVar('authtype',$authtype);
			$frmItem->addVar('username',$username);
			$frmItem->addVar('publickey',$publickey);
			$frmItem->addVar('privatekey',$privatekey);
			$frmItem->addVar('password',$password);

			if (ITEM_TYPE_DB_MONITOR == $type)
				$frmItem->addRow(S_PARAMS, new CTextArea('params',$params,60,4));
			else if (ITEM_TYPE_CALCULATED == $type)
				$frmItem->addRow(S_EXPRESSION, new CTextArea('params',$params,60,4));
			else
				$frmItem->addVar('params',$params);
		}

		if(isset($limited)){
			$frmItem->addVar('value_type', $value_type);
			$cmbValType = new CTextBox('value_type_name', item_value_type2str($value_type), 40, 'yes');
		}
		else{
			$cmbValType = new CComboBox('value_type',$value_type,'submit()');
			$cmbValType->addItem(ITEM_VALUE_TYPE_UINT64,	S_NUMERIC_UNSIGNED);
			$cmbValType->addItem(ITEM_VALUE_TYPE_FLOAT,	S_NUMERIC_FLOAT);
			$cmbValType->addItem(ITEM_VALUE_TYPE_STR, 	S_CHARACTER);
			$cmbValType->addItem(ITEM_VALUE_TYPE_LOG, 	S_LOG);
			$cmbValType->addItem(ITEM_VALUE_TYPE_TEXT,	S_TEXT);
		}

		$frmItem->addRow(S_TYPE_OF_INFORMATION,$cmbValType);

		if ($value_type == ITEM_VALUE_TYPE_UINT64) {
			if(isset($limited)) {
				$frmItem->addVar('data_type', $data_type);
				$cmbDataType = new CTextBox('data_type_name', item_data_type2str($data_type), 20, 'yes');
			}
			else {
				$cmbDataType = new CComboBox('data_type', $data_type, 'submit()');
				$cmbDataType->addItem(ITEM_DATA_TYPE_DECIMAL,		item_data_type2str(ITEM_DATA_TYPE_DECIMAL));
				$cmbDataType->addItem(ITEM_DATA_TYPE_OCTAL,		item_data_type2str(ITEM_DATA_TYPE_OCTAL));
				$cmbDataType->addItem(ITEM_DATA_TYPE_HEXADECIMAL, 	item_data_type2str(ITEM_DATA_TYPE_HEXADECIMAL));
			}
			$frmItem->addRow(S_DATA_TYPE,$cmbDataType);
		}
		else
			$frmItem->addVar('data_type', $data_type);

		if( ($value_type==ITEM_VALUE_TYPE_FLOAT) || ($value_type==ITEM_VALUE_TYPE_UINT64)){
			$frmItem->addRow(S_UNITS, new CTextBox('units',$units,40, $limited));

			if(isset($limited)){
				$frmItem->addVar('multiplier', $multiplier);
				$cmbMultipler = new CTextBox('multiplier_name', $multiplier ? S_CUSTOM_MULTIPLIER : S_DO_NOT_USE, 20, 'yes');
			}
			else{
				$cmbMultipler = new CComboBox('multiplier',$multiplier,'submit()');
				$cmbMultipler->addItem(0,S_DO_NOT_USE);
				$cmbMultipler->addItem(1,S_CUSTOM_MULTIPLIER);
			}
			$frmItem->addRow(S_USE_MULTIPLIER, $cmbMultipler);

		}
		else{
			$frmItem->addVar('units',$units);
			$frmItem->addVar('multiplier',$multiplier);
		}

		if( !is_numeric($formula)) $formula = 1;
		if($multiplier == 1){
			$frmItem->addRow(S_CUSTOM_MULTIPLIER, new CTextBox('formula',$formula,40,$limited));
		}
		else{
			$frmItem->addVar('formula',$formula);
		}


		if($type != ITEM_TYPE_TRAPPER && $type != ITEM_TYPE_HTTPTEST){
			$frmItem->addRow(S_UPDATE_INTERVAL_IN_SEC, new CNumericBox('delay',$delay,5));
			$frmItem->addRow(S_FLEXIBLE_INTERVALS, $delay_flex_el);
			$frmItem->addRow(S_NEW_FLEXIBLE_INTERVAL,
				array(
					S_DELAY, SPACE,
					new CNumericBox('new_delay_flex[delay]','50',5),
					S_PERIOD, SPACE,
					new CTextBox('new_delay_flex[period]','1-7,00:00-23:59',27), BR(),
					new CButton('add_delay_flex',S_ADD)
				),'new');
		}
		else{
			$frmItem->addVar('delay',$delay);
			$frmItem->addVar('delay_flex',null);
		}

		$frmItem->addRow(S_KEEP_HISTORY_IN_DAYS, array(
			new CNumericBox('history',$history,8),
			(!isset($_REQUEST['itemid'])) ? null :
				new CButtonQMessage('del_history',S_CLEAR_HISTORY,S_HISTORY_CLEARING_CAN_TAKE_A_LONG_TIME_CONTINUE_Q)
			));

		if(uint_in_array($value_type, array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64)))
			$frmItem->addRow(S_KEEP_TRENDS_IN_DAYS, new CNumericBox('trends',$trends,8));
		else
			$frmItem->addVar('trends',0);

		$cmbStatus = new CComboBox('status',$status);
		foreach(array(ITEM_STATUS_ACTIVE,ITEM_STATUS_DISABLED,ITEM_STATUS_NOTSUPPORTED) as $st)
			$cmbStatus->addItem($st,item_status2str($st));
		$frmItem->addRow(S_STATUS,$cmbStatus);

		if($value_type==ITEM_VALUE_TYPE_LOG){
			$frmItem->addRow(S_LOG_TIME_FORMAT, new CTextBox('logtimefmt',$logtimefmt,16,$limited));
		}
		else{
			$frmItem->addVar('logtimefmt',$logtimefmt);
		}

		if( ($value_type==ITEM_VALUE_TYPE_FLOAT) || ($value_type==ITEM_VALUE_TYPE_UINT64)){
			$cmbDelta= new CComboBox('delta',$delta);
			$cmbDelta->addItem(0,S_AS_IS);
			$cmbDelta->addItem(1,S_DELTA_SPEED_PER_SECOND);
			$cmbDelta->addItem(2,S_DELTA_SIMPLE_CHANGE);
			$frmItem->addRow(S_STORE_VALUE,$cmbDelta);
		}
		else{
			$frmItem->addVar('delta',0);
		}

		if(($value_type==ITEM_VALUE_TYPE_UINT64) || ($value_type == ITEM_VALUE_TYPE_STR)){
			if(isset($limited) && $type != ITEM_TYPE_HTTPTEST){
				$frmItem->addVar('valuemapid', $valuemapid);
				$map_name = S_AS_IS;
				if($map_data = DBfetch(DBselect('SELECT name FROM valuemaps WHERE valuemapid='.$valuemapid))){
					$map_name = $map_data['name'];
				}
				$cmbMap = new CTextBox('valuemap_name', $map_name, 20, 'yes');
			}
			else{
				$cmbMap = new CComboBox('valuemapid',$valuemapid);
				$cmbMap->addItem(0,S_AS_IS);
				$db_valuemaps = DBselect('SELECT * FROM valuemaps WHERE '.DBin_node('valuemapid'));
				while($db_valuemap = DBfetch($db_valuemaps))
					$cmbMap->addItem(
						$db_valuemap['valuemapid'],
						get_node_name_by_elid($db_valuemap['valuemapid'], null, ': ').$db_valuemap['name']
						);
			}

			$link = new CLink(S_THROW_MAP_SMALL,'config.php?config=6');
			$link->setAttribute('target','_blank');
			$frmItem->addRow(array(S_SHOW_VALUE.SPACE,$link),$cmbMap);

		}
		else{
			$frmItem->addVar('valuemapid',0);
		}

		if($type==ITEM_TYPE_TRAPPER){
			$frmItem->addRow(S_ALLOWED_HOSTS, new CTextBox('trapper_hosts',$trapper_hosts,40));
		}
		else{
			$frmItem->addVar('trapper_hosts',$trapper_hosts);
		}

		if($type==ITEM_TYPE_HTTPTEST){
			$app_names = get_applications_by_itemid($_REQUEST['itemid'], 'name');
			$frmItem->addRow(S_APPLICATIONS, new CTextBox('application_name',
				isset($app_names[0]) ? $app_names[0] : '', 20, $limited));
			$frmItem->addVar('applications',$applications,6);
		}
		else{

			$new_app = new CTextBox('new_application',$new_application,40);
			$frmItem->addRow(S_NEW_APPLICATION,$new_app);

			$cmbApps = new CListBox('applications[]',$applications,6);
			$cmbApps->addItem(0,'-'.S_NONE.'-');

			$sql = 'SELECT DISTINCT applicationid,name '.
					' FROM applications '.
					' WHERE hostid='.$hostid.
					' ORDER BY name';
			$db_applications = DBselect($sql);
			while($db_app = DBfetch($db_applications)){
				$cmbApps->addItem($db_app['applicationid'],$db_app['name']);
			}
			$frmItem->addRow(S_APPLICATIONS,$cmbApps);
		}

		$frmRow = array(new CButton('save',S_SAVE));
		if(isset($_REQUEST['itemid'])){
			array_push($frmRow,
				SPACE,
				new CButton('clone',S_CLONE));

			if(!isset($limited)){
				array_push($frmRow,
					SPACE,
					new CButtonDelete(S_DELETE_SELECTED_ITEM_Q,
						url_param('form').url_param('groupid').url_param('itemid'))
				);
			}
		}
		array_push($frmRow,
			SPACE,
			new CButtonCancel(url_param('groupid')));

		$frmItem->addSpanRow($frmRow,'form_row_last');

	        $cmbGroups = new CComboBox('add_groupid',$add_groupid);

			$available_groups = get_accessible_groups_by_user($USER_DETAILS,PERM_READ_ONLY);
	        $groups=DBselect('SELECT DISTINCT groupid,name '.
				' FROM groups '.
				' WHERE '.DBcondition('groupid',$available_groups).
				' ORDER BY name');
	        while($group=DBfetch($groups)){
				$cmbGroups->addItem(
					$group['groupid'],
					get_node_name_by_elid($group['groupid'], null, ': ').$group['name']
					);
	        }
		$frmItem->addRow(S_GROUP,$cmbGroups);

		$cmbAction = new CComboBox('action');
		$cmbAction->addItem('add to group',S_ADD_TO_GROUP);
		if(isset($_REQUEST['itemid'])){
			$cmbAction->addItem('update in group',S_UPDATE_IN_GROUP);
			$cmbAction->addItem('delete FROM group',S_DELETE_FROM_GROUP);
		}
		$frmItem->addItemToBottomRow($cmbAction);
		$frmItem->addItemToBottomRow(SPACE);
		$frmItem->addItemToBottomRow(new CButton('register',S_DO_SMALL));

		$frmItem->show();
	}

	function insert_mass_update_item_form($elements_array_name){
		global $USER_DETAILS;

		$frmItem = new CFormTable(S_ITEM,null,'post');
		$frmItem->setHelp('web.items.item.php');
		$frmItem->setTitle(S_MASS_UPDATE);

		$frmItem->addVar('massupdate',1);

		$frmItem->addVar('group_itemid',get_request('group_itemid',array()));
		$frmItem->addVar('config',get_request('config',0));

		$description	= get_request('description'	,'');
		$key		= get_request('key'		,'');
		$host		= get_request('host',		null);
		$delay		= get_request('delay'		,30);
		$history	= get_request('history'		,90);
		$status		= get_request('status'		,0);
		$type		= get_request('type'		,0);
		$snmp_community	= get_request('snmp_community'	,'public');
		$snmp_oid	= get_request('snmp_oid'	,'interfaces.ifTable.ifEntry.ifInOctets.1');
		$snmp_port	= get_request('snmp_port'	,161);
		$value_type	= get_request('value_type'	,ITEM_VALUE_TYPE_UINT64);
		$data_type	= get_request('data_type'	,ITEM_DATA_TYPE_DECIMAL);
		$trapper_hosts	= get_request('trapper_hosts'	,'');
		$units		= get_request('units'		,'');
		$valuemapid	= get_request('valuemapid'	,0);
		$delta		= get_request('delta'		,0);
		$trends		= get_request('trends'		,365);
		$applications	= get_request('applications'	,array());
		$delay_flex	= get_request('delay_flex'	,array());

		$snmpv3_securityname	= get_request('snmpv3_securityname'	,'');
		$snmpv3_securitylevel	= get_request('snmpv3_securitylevel'	,0);
		$snmpv3_authpassphrase	= get_request('snmpv3_authpassphrase'	,'');
		$snmpv3_privpassphrase	= get_request('snmpv3_privpassphrase'	,'');

		$formula	= get_request('formula'		,'1');
		$logtimefmt	= get_request('logtimefmt'	,'');

		$add_groupid	= get_request('add_groupid'	,get_request('groupid',0));

		$delay_flex_el = array();

		$i = 0;
		foreach($delay_flex as $val){
			if(!isset($val['delay']) && !isset($val['period'])) continue;

			array_push($delay_flex_el,
				array(
					new CCheckBox('rem_delay_flex[]', 'no', null,$i),
						$val['delay'],
						' sec at ',
						$val['period']
				),
				BR());
			$frmItem->addVar("delay_flex[".$i."][delay]", $val['delay']);
			$frmItem->addVar("delay_flex[".$i."][period]", $val['period']);
			$i++;
			if($i >= 7) break; /* limit count of  intervals
					    * 7 intervals by 30 symbols = 210 characters
					    * db storage field is 256
					    */
		}

		if(count($delay_flex_el)==0)
			array_push($delay_flex_el, S_NO_FLEXIBLE_INTERVALS);
		else
			array_push($delay_flex_el, new CButton('del_delay_flex',S_DELETE_SELECTED));

		if(count($applications)==0)  array_push($applications,0);

		$cmbType = new CComboBox('type',$type);
		foreach(array(ITEM_TYPE_ZABBIX,ITEM_TYPE_ZABBIX_ACTIVE,ITEM_TYPE_SIMPLE,ITEM_TYPE_SNMPV1,
			ITEM_TYPE_SNMPV2C,ITEM_TYPE_SNMPV3,ITEM_TYPE_TRAPPER,ITEM_TYPE_INTERNAL,
			ITEM_TYPE_AGGREGATE,ITEM_TYPE_AGGREGATE,ITEM_TYPE_EXTERNAL,ITEM_TYPE_DB_MONITOR) as $it)
				$cmbType->addItem($it, item_type2str($it));

		$frmItem->addRow(array( new CVisibilityBox('type_visible', get_request('type_visible'), 'type', S_ORIGINAL),
			S_TYPE), $cmbType);

		$frmItem->addRow(array( new CVisibilityBox('community_visible', get_request('community_visible'), 'snmp_community', S_ORIGINAL),
			S_SNMP_COMMUNITY), new CTextBox('snmp_community',$snmp_community,16));

		$frmItem->addRow(array( new CVisibilityBox('securityname_visible', get_request('securityname_visible'), 'snmpv3_securityname',
			S_ORIGINAL), S_SNMPV3_SECURITY_NAME), new CTextBox('snmpv3_securityname',$snmpv3_securityname,64));

		$cmbSecLevel = new CComboBox('snmpv3_securitylevel',$snmpv3_securitylevel);
		$cmbSecLevel->addItem(ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV,"NoAuthPriv");
		$cmbSecLevel->addItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV,"AuthNoPriv");
		$cmbSecLevel->addItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV,"AuthPriv");
		$frmItem->addRow(array( new CVisibilityBox('securitylevel_visible',  get_request('securitylevel_visible'), 'snmpv3_securitylevel',
			S_ORIGINAL), S_SNMPV3_SECURITY_LEVEL), $cmbSecLevel);
		$frmItem->addRow(array( new CVisibilityBox('authpassphrase_visible', get_request('authpassphrase_visible'),
			'snmpv3_authpassphrase', S_ORIGINAL), S_SNMPV3_AUTH_PASSPHRASE),
			new CTextBox('snmpv3_authpassphrase',$snmpv3_authpassphrase,64));

		$frmItem->addRow(array( new CVisibilityBox('privpassphras_visible', get_request('privpassphras_visible'), 'snmpv3_privpassphrase',
			S_ORIGINAL), S_SNMPV3_PRIV_PASSPHRASE), new CTextBox('snmpv3_privpassphrase',$snmpv3_privpassphrase,64));

		$frmItem->addRow(array( new CVisibilityBox('port_visible', get_request('port_visible'), 'snmp_port', S_ORIGINAL), S_SNMP_PORT),
			new CNumericBox('snmp_port',$snmp_port,5));

		$cmbValType = new CComboBox('value_type',$value_type);
		$cmbValType->addItem(ITEM_VALUE_TYPE_UINT64,	S_NUMERIC_UNSIGNED);		$cmbValType->addItem(ITEM_VALUE_TYPE_FLOAT,	S_NUMERIC_FLOAT);		$cmbValType->addItem(ITEM_VALUE_TYPE_STR, 	S_CHARACTER);		$cmbValType->addItem(ITEM_VALUE_TYPE_LOG, 	S_LOG);		$cmbValType->addItem(ITEM_VALUE_TYPE_TEXT,	S_TEXT);		$frmItem->addRow(array( new CVisibilityBox('value_type_visible', get_request('value_type_visible'), 'value_type', S_ORIGINAL),			S_TYPE_OF_INFORMATION), $cmbValType);

		$cmbDataType = new CComboBox('data_type',$data_type);
		$cmbDataType->addItem(ITEM_DATA_TYPE_DECIMAL,		item_data_type2str(ITEM_DATA_TYPE_DECIMAL));
		$cmbDataType->addItem(ITEM_DATA_TYPE_OCTAL,		item_data_type2str(ITEM_DATA_TYPE_OCTAL));
		$cmbDataType->addItem(ITEM_DATA_TYPE_HEXADECIMAL, 	item_data_type2str(ITEM_DATA_TYPE_HEXADECIMAL));
		$frmItem->addRow(array( new CVisibilityBox('data_type_visible', get_request('data_type_visible'), 'data_type', S_ORIGINAL),
			S_DATA_TYPE), $cmbDataType);

		$frmItem->addRow(array( new CVisibilityBox('units_visible', get_request('units_visible'), 'units', S_ORIGINAL), S_UNITS),
			new CTextBox('units',$units,40));

		$frmItem->addRow(array( new CVisibilityBox('formula_visible', get_request('formula_visible'), 'formula', S_ORIGINAL),
			S_CUSTOM_MULTIPLIER.' (0 - '.S_DISABLED.')'), new CTextBox('formula',$formula,40));

		$frmItem->addRow(array( new CVisibilityBox('delay_visible', get_request('delay_visible'), 'delay', S_ORIGINAL),
			S_UPDATE_INTERVAL_IN_SEC), new CNumericBox('delay',$delay,5));

		$delay_flex_el = new CSpan($delay_flex_el);
		$delay_flex_el->setAttribute('id', 'delay_flex_list');

		$frmItem->addRow(array(
						new CVisibilityBox('delay_flex_visible',
								get_request('delay_flex_visible'),
								array('delay_flex_list', 'new_delay_flex_el'),
								S_ORIGINAL),
						S_FLEXIBLE_INTERVALS), $delay_flex_el);

		$new_delay_flex_el = new CSpan(array(
										S_DELAY, SPACE,
										new CNumericBox("new_delay_flex[delay]","50",5),
										S_PERIOD, SPACE,
										new CTextBox("new_delay_flex[period]","1-7,00:00-23:59",27), BR(),
										new CButton("add_delay_flex",S_ADD)
									));
		$new_delay_flex_el->setAttribute('id', 'new_delay_flex_el');

		$frmItem->addRow(S_NEW_FLEXIBLE_INTERVAL, $new_delay_flex_el, 'new');

		$frmItem->addRow(array( new CVisibilityBox('history_visible', get_request('history_visible'), 'history', S_ORIGINAL),
			S_KEEP_HISTORY_IN_DAYS), new CNumericBox('history',$history,8));
		$frmItem->addRow(array( new CVisibilityBox('trends_visible', get_request('trends_visible'), 'trends', S_ORIGINAL),
			S_KEEP_TRENDS_IN_DAYS), new CNumericBox('trends',$trends,8));

		$cmbStatus = new CComboBox('status',$status);
		foreach(array(ITEM_STATUS_ACTIVE,ITEM_STATUS_DISABLED,ITEM_STATUS_NOTSUPPORTED) as $st)
			$cmbStatus->addItem($st,item_status2str($st));
		$frmItem->addRow(array( new CVisibilityBox('status_visible', get_request('status_visible'), 'status', S_ORIGINAL), S_STATUS),
			$cmbStatus);

		$frmItem->addRow(array( new CVisibilityBox('logtimefmt_visible', get_request('logtimefmt_visible'), 'logtimefmt', S_ORIGINAL),
			S_LOG_TIME_FORMAT), new CTextBox("logtimefmt",$logtimefmt,16));

		$cmbDelta= new CComboBox('delta',$delta);
		$cmbDelta->addItem(0,S_AS_IS);
		$cmbDelta->addItem(1,S_DELTA_SPEED_PER_SECOND);
		$cmbDelta->addItem(2,S_DELTA_SIMPLE_CHANGE);
		$frmItem->addRow(array( new CVisibilityBox('delta_visible', get_request('delta_visible'), 'delta', S_ORIGINAL),
			S_STORE_VALUE),$cmbDelta);

		$cmbMap = new CComboBox('valuemapid',$valuemapid);
		$cmbMap->addItem(0,S_AS_IS);
		$db_valuemaps = DBselect('SELECT * FROM valuemaps WHERE '.DBin_node('valuemapid'));
		while($db_valuemap = DBfetch($db_valuemaps))
			$cmbMap->addItem(
					$db_valuemap["valuemapid"],
					get_node_name_by_elid($db_valuemap["valuemapid"], null, ': ').$db_valuemap["name"]
					);

		$link = new CLink(S_THROW_MAP_SMALL,"config.php?config=6");
		$link->setAttribute("target","_blank");
		$frmItem->addRow(array( new CVisibilityBox('valuemapid_visible', get_request('valuemapid_visible'), 'valuemapid', S_ORIGINAL),
			S_SHOW_VALUE, SPACE, $link),$cmbMap);

		$frmItem->addRow(array( new CVisibilityBox('trapper_hosts_visible', get_request('trapper_hosts_visible'), 'trapper_hosts',
			S_ORIGINAL), S_ALLOWED_HOSTS), new CTextBox('trapper_hosts',$trapper_hosts,40));

		$cmbApps = new CListBox('applications[]',$applications,6);
		$cmbApps->addItem(0,'-'.S_NONE.'-');

		if(isset($_REQUEST['hostid'])){
			$sql = 'SELECT applicationid,name '.
				' FROM applications '.
				' WHERE hostid='.$_REQUEST['hostid'].
				' ORDER BY name';
			$db_applications = DBselect($sql);
			while($db_app = DBfetch($db_applications)){
				$cmbApps->addItem($db_app["applicationid"],$db_app["name"]);
			}
		}
		$frmItem->addRow(array( new CVisibilityBox('applications_visible', get_request('applications_visible'), 'applications[]',
			S_ORIGINAL), S_APPLICATIONS),$cmbApps);

		$frmItem->addItemToBottomRow(array(new CButton("update",S_UPDATE),
			SPACE, new CButtonCancel(url_param('groupid').url_param("hostid").url_param("config"))));

		$frmItem->show();
	}

	function insert_copy_elements_to_forms($elements_array_name){

		$copy_type = get_request('copy_type', 0);
		$copy_mode = get_request('copy_mode', 0);
		$filter_groupid = get_request('filter_groupid', 0);
		$group_itemid = get_request($elements_array_name, array());
		$copy_targetid = get_request('copy_targetid', array());

		if(!is_array($group_itemid) || (is_array($group_itemid) && count($group_itemid) < 1)){
			error(S_INCORRECT_LIST_OF_ITEMS);
			return;
		}

		$frmCopy = new CFormTable(count($group_itemid).' '.S_X_ELEMENTS_COPY_TO_DOT_DOT_DOT,null,'post',null,'go');
		$frmCopy->setHelp('web.items.copyto.php');
		$frmCopy->addVar($elements_array_name, $group_itemid);

		$cmbCopyType = new CComboBox('copy_type',$copy_type,'submit()');
		$cmbCopyType->addItem(0,S_HOSTS);
		$cmbCopyType->addItem(1,S_HOST_GROUPS);
		$frmCopy->addRow(S_TARGET_TYPE, $cmbCopyType);

		$target_list = array();

		$groups = CHostGroup::get(array('extendoutput'=>1, 'order'=>'name'));
		order_result($groups, 'name');
		
		if(0 == $copy_type){
			$cmbGroup = new CComboBox('filter_groupid',$filter_groupid,'submit()');
			
			foreach($groups as $gnum => $group){
				if(empty($filter_groupid)) $filter_groupid = $group['groupid'];
				$cmbGroup->addItem($group['groupid'],$group['name']);
			}

			$frmCopy->addRow('Group', $cmbGroup);

			$options = array(
				'extendoutput'=>1,
				'groupids' => $filter_groupid,
				'templated_hosts' => 1
			);
			$hosts = CHost::get($options);
			order_result($hosts, 'host');
			
			foreach($hosts as $num => $host){
				$hostid = $host['hostid'];

				array_push($target_list,array(
					new CCheckBox('copy_targetid['.$hostid.']',
						uint_in_array($hostid, $copy_targetid),
						null,
						$hostid),
					SPACE,
					$host['host'],
					BR()
				));
			}
		}
		else{
			foreach($groups as $groupid => $group){
				array_push($target_list,array(
					new CCheckBox('copy_targetid['.$group['groupid'].']',
						uint_in_array($group['groupid'], $copy_targetid),
						null,
						$group['groupid']),
					SPACE,
					$group['name'],
					BR()
					));
			}
		}

		$frmCopy->addRow(S_TARGET, $target_list);

		$cmbCopyMode = new CComboBox('copy_mode',$copy_mode);
		$cmbCopyMode->addItem(0, S_UPDATE_EXISTING_NON_LINKED_ITEMS);
		$cmbCopyMode->addItem(1, S_SKIP_EXISTING_ITEMS);
		$cmbCopyMode->setEnabled(false);
		$frmCopy->addRow(S_MODE, $cmbCopyMode);

		$frmCopy->addItemToBottomRow(new CButton("copy",S_COPY));
		$frmCopy->addItemToBottomRow(array(SPACE,
			new CButtonCancel(url_param('groupid').url_param("hostid").url_param("config"))));

		$frmCopy->show();
	}

// TRIGGERS
	function insert_mass_update_trigger_form(){//$elements_array_name){
		$visible = get_request('visible',array());

		$priority 		= get_request('priority',	'');
		$dependencies	= get_request('dependencies',array());

		$original_templates = array();

		asort($dependencies);

		$frmMTrig = new CFormTable(S_TRIGGERS_MASSUPDATE, 'triggers.php');
		$frmMTrig->addVar('massupdate',get_request('massupdate',1));
		$frmMTrig->addVar('go',get_request('go','massupdate'));
		$frmMTrig->setAttribute('id', 'massupdate');

		$triggers = $_REQUEST['g_triggerid'];
		foreach($triggers as $id => $triggerid){
			$frmMTrig->addVar('g_triggerid['.$triggerid.']',$triggerid);
		}

		$cmbPrior = new CComboBox("priority",$priority);
		for($i = 0; $i <= 5; $i++){
			$cmbPrior->addItem($i,get_severity_description($i));
		}
		$frmMTrig->addRow(array(new CVisibilityBox('visible[priority]', isset($visible['priority']), 'priority', S_ORIGINAL), S_SEVERITY),
						$cmbPrior
					);

/* dependencies */
		$dep_el = array();
		foreach($dependencies as $val){
			array_push($dep_el,
				array(
					new CCheckBox("rem_dependence[]", 'no', null, strval($val)),
					expand_trigger_description($val)
				),
				BR());
			$frmMTrig->addVar("dependencies[]",strval($val));
		}

		if(count($dep_el)==0)
			$dep_el[] = S_NO_DEPENDENCES_DEFINED;
		else
			$dep_el[] = new CButton('del_dependence',S_DELETE_SELECTED);

//		$frmMTrig->addRow(S_THE_TRIGGER_DEPENDS_ON,$dep_el);
/* end dependencies */
/* new dependency */
		//$frmMTrig->addVar('new_dependence','0');

		$btnSelect = new CButton('btn1', S_ADD,
				"return PopUp('popup.php?dstfrm=massupdate&dstact=add_dependence".
				"&dstfld1=new_dependence[]&srctbl=triggers&objname=triggers&srcfld1=1&multiselect=1".
				"',600,450);",
				'T');

		array_push($dep_el, array(br(),$btnSelect));

		$dep_div = new CDiv($dep_el);
		$dep_div->setAttribute('id','dependency_box');

		$frmMTrig->addRow(array(new CVisibilityBox('visible[dependencies]', isset($visible['dependencies']), 'dependency_box', S_ORIGINAL),S_TRIGGER_DEPENDENCIES),
							$dep_div
						);
/* end new dependency */


		$frmMTrig->addItemToBottomRow(new CButton('mass_save',S_SAVE));
		$frmMTrig->addItemToBottomRow(SPACE);
		$frmMTrig->addItemToBottomRow(new CButtonCancel(url_param('config').url_param('groupid')));
		$frmMTrig->show();
	}

// Insert form for Trigger
	function insert_trigger_form(){
		$frmTrig = new CFormTable(S_TRIGGER,'triggers.php');
		$frmTrig->setHelp('config_triggers.php');

		if(isset($_REQUEST['hostid'])){
			$frmTrig->addVar('hostid',$_REQUEST['hostid']);
		}

		$dep_el=array();
		$dependencies = get_request('dependencies',array());

		$limited = null;

		if(isset($_REQUEST['triggerid'])){
			$frmTrig->addVar('triggerid',$_REQUEST['triggerid']);
			$trigger=get_trigger_by_triggerid($_REQUEST['triggerid']);

			$frmTrig->setTitle(S_TRIGGER.' "'.htmlspecialchars($trigger['description']).'"');

			$limited = $trigger['templateid'] ? 'yes' : null;
		}

		$expression		= get_request('expression'	,'');
		$description		= get_request('description'	,'');
		$type 			= get_request('type',		0);
		$priority		= get_request('priority'	,0);
		$status			= get_request('status'		,0);
		$comments		= get_request('comments'	,'');
		$url			= get_request('url'		,'');

		$expr_temp  = get_request('expr_temp','');
		$input_method = get_request('input_method',IM_ESTABLISHED);

		if((isset($_REQUEST['triggerid']) && !isset($_REQUEST['form_refresh']))  || isset($limited)){
			$description	= $trigger['description'];
			$expression	= explode_exp($trigger['expression'],0);

			if(!isset($limited) || !isset($_REQUEST['form_refresh'])){
				$type = $trigger['type'];
				$priority	= $trigger['priority'];
				$status		= $trigger['status'];
				$comments	= $trigger['comments'];
				$url		= $trigger['url'];

				$trigs=DBselect('SELECT t.triggerid,t.description,t.expression '.
							' FROM triggers t,trigger_depends d '.
							' WHERE t.triggerid=d.triggerid_up '.
								' AND d.triggerid_down='.$_REQUEST['triggerid']);

				while($trig=DBfetch($trigs)){
					if(uint_in_array($trig['triggerid'],$dependencies))	continue;
					array_push($dependencies,$trig['triggerid']);
				}
			}
		}

		$frmTrig->addRow(S_NAME, new CTextBox('description',$description,90, $limited));

		if($input_method == IM_TREE){
			$alz = analyze_expression($expression);

			if($alz !== false){
				list($outline, $node, $map) = $alz;
				if(isset($_REQUEST['expr_action']) && $node != null){

					$new_expr = remake_expression($node, $_REQUEST['expr_target_single'], $_REQUEST['expr_action'], $expr_temp, $map);
					if($new_expr !== false){
						$expression = $new_expr;
						list($outline, $node, $map) = analyze_expression($expression);
						$expr_temp = '';
					}
					else{
						show_messages();
					}
				}

				$tree = array();
				create_node_list($node, $tree);

				$frmTrig->addVar('expression', $expression);
				$exprfname = 'expr_temp';
				$exprtxt = new CTextBox($exprfname, $expr_temp, 65, 'yes');
				$macrobtn = new CButton('insert_macro', S_INSERT_MACRO, 'return call_ins_macro_menu(event);');
				$exprparam = "this.form.elements['$exprfname'].value";
			}
			else{
				$input_method = IM_FORCED;
			}
		}

		if($input_method != IM_TREE){
			$exprfname = 'expression';
			$exprtxt = new CTextBox($exprfname,$expression,75,$limited);
			$exprparam = "getSelectedText(this.form.elements['$exprfname'])";
		}

		$row = array($exprtxt,
					 new CButton('insert',$input_method == IM_TREE ? S_EDIT : S_SELECT,
								 "return PopUp('popup_trexpr.php?dstfrm=".$frmTrig->GetName().
								 "&dstfld1=${exprfname}&srctbl=expression".
								 "&srcfld1=expression&expression=' + escape($exprparam),800,200);"));

		if(isset($macrobtn)) array_push($row, $macrobtn);
		if($input_method == IM_TREE){
			array_push($row, BR());
			if(empty($outline)){
				array_push($row, new CButton('add_expression', S_ADD, ""));
			}
			else{
				array_push($row, new CButton('and_expression', S_AND_BIG, ""));
				array_push($row, new CButton('or_expression', S_OR_BIG, ""));
				array_push($row, new CButton('replace_expression', S_REPLACE, ""));
			}
		}
		$frmTrig->addVar('input_method', $input_method);
		$frmTrig->addVar('toggle_input_method', '');
		$exprtitle = array(S_EXPRESSION);

		if($input_method != IM_FORCED){
			$btn_im = new CSpan(S_TOGGLE_INPUT_METHOD,'link');
			$btn_im->setAttribute('onclick','javascript: '.
								"document.getElementById('toggle_input_method').value=1;".
								"document.getElementById('input_method').value=".(($input_method==IM_TREE)?IM_ESTABLISHED:IM_TREE).';'.
								"document.forms['".$frmTrig->getName()."'].submit();");

			$exprtitle[] = array(SPACE, '(', $btn_im, ')');
		}

		$frmTrig->addRow($exprtitle, $row);

		if($input_method == IM_TREE){
			$exp_table = new CTable();
			$exp_table->setClass('tableinfo');
			$exp_table->setAttribute('id','exp_list');
			$exp_table->setOddRowClass('even_row');
			$exp_table->setEvenRowClass('even_row');

			$exp_table->setHeader(array(S_TARGET, S_EXPRESSION, S_DELETE));

			if($node != null){
				$exprs = make_disp_tree($tree, $map, true);
				foreach($exprs as $i => $e){
					$tgt_chk = new CCheckbox('expr_target_single', ($i==0)?'yes':'no', 'check_target(this);', $e['id']);
					$del_url = new CSpan(S_DELETE,'link');
					$del_url->setAttribute('onclick', 'javascript: if(confirm("'.S_DELETE_EXPRESSION_Q.'")) {'.
										 	' delete_expression('.$e['id'] .');'.
										 	' document.forms["config_triggers.php"].submit(); '.
										'}');

					$row = new CRow(array($tgt_chk, $e['expr'], $del_url));
					$exp_table->addRow($row);
				}
			}
			else{
				$outline = '';
			}

			$frmTrig->addVar('remove_expression', '');

			$btn_test = new CButton('test_expression', S_TEST,
									"openWinCentered(".
									"'tr_testexpr.php?expression=' + encodeURIComponent(this.form.elements['expression'].value)".
									",'ExpressionTest'".
									",850,400".
									",'titlebar=no, resizable=yes, scrollbars=yes');".
									"return false;");
			if (empty($outline)) $btn_test->setAttribute('disabled', 'yes');
			$frmTrig->addRow(SPACE, array($outline,
										  BR(),BR(),
										  $exp_table,
										  $btn_test));
		}

// dependencies
		foreach($dependencies as $val){
			array_push($dep_el,
				array(
					new CCheckBox('rem_dependence['.$val.']', 'no', null, strval($val)),
					expand_trigger_description($val)
				),
				BR());
			$frmTrig->addVar('dependencies[]',strval($val));
		}

		if(count($dep_el)==0)
			array_push($dep_el,  S_NO_DEPENDENCES_DEFINED);
		else
			array_push($dep_el, new CButton('del_dependence',S_DELETE_SELECTED));
		$frmTrig->addRow(S_THE_TRIGGER_DEPENDS_ON,$dep_el);
	/* end dependencies */

	/* new dependency */
//		$frmTrig->addVar('new_dependence','0');

//		$txtCondVal = new CTextBox('trigger','',75,'yes');

		$btnSelect = new CButton('btn1',S_ADD,
				"return PopUp('popup.php?dstfrm=".$frmTrig->GetName().
							'&dstfld1=new_dependence[]'.
							'&srctbl=triggers'.
							'&multiselect=1'.
							'&dstact=add_dependence'.
							'&objname=triggers'.
							'&srcfld1=1'.
						"',750,450);",'T');


		$frmTrig->addRow(S_NEW_DEPENDENCY, $btnSelect, 'new');
// end new dependency

		$type_select = new CComboBox('type');
		$type_select->additem(TRIGGER_MULT_EVENT_DISABLED,S_NORMAL,(($type == TRIGGER_MULT_EVENT_ENABLED)?'no':'yes'));
		$type_select->additem(TRIGGER_MULT_EVENT_ENABLED,S_NORMAL.SPACE.'+'.SPACE.S_MULTIPLE_TRUE_EVENTS,(($type == TRIGGER_MULT_EVENT_ENABLED)?'yes':'no'));

		$frmTrig->addRow(S_EVENT_GENERATION,$type_select);

		$cmbPrior = new CComboBox('priority',$priority);
		for($i = 0; $i <= 5; $i++){
			$cmbPrior->addItem($i,get_severity_description($i));
		}
		$frmTrig->addRow(S_SEVERITY,$cmbPrior);

		$frmTrig->addRow(S_COMMENTS,new CTextArea("comments",$comments,90,7));
		$frmTrig->addRow(S_URL,new CTextBox("url",$url,90));
		$frmTrig->addRow(S_DISABLED,new CCheckBox("status",$status));

		$frmTrig->addItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["triggerid"])){
			$frmTrig->addItemToBottomRow(SPACE);
			$frmTrig->addItemToBottomRow(new CButton("clone",S_CLONE));
			$frmTrig->addItemToBottomRow(SPACE);
			if( !$limited ){
				$frmTrig->addItemToBottomRow(new CButtonDelete(S_DELETE_TRIGGER_Q,
					url_param("form").url_param('groupid').url_param("hostid").
					url_param("triggerid")));
			}
		}
		$frmTrig->addItemToBottomRow(SPACE);
		$frmTrig->addItemToBottomRow(new CButtonCancel(url_param('groupid').url_param("hostid")));
		$frmTrig->show();

		$jsmenu = new CPUMenu(null,170);
		$jsmenu->InsertJavaScript();
	}

	function insert_trigger_comment_form($triggerid){

		$trigger = DBfetch(DBselect('SELECT t.*, h.* '.
			' FROM triggers t, functions f, items i, hosts h '.
			' WHERE t.triggerid='.$triggerid.
				' AND f.triggerid=t.triggerid '.
				' AND f.itemid=i.itemid '.
				' AND i.hostid=h.hostid '));

		$frmComent = new CFormTable(S_COMMENTS." for ".$trigger['host']." : \"".expand_trigger_description_by_data($trigger).'"');
		$frmComent->setHelp("web.tr_comments.comments.php");
		$frmComent->addVar("triggerid",$triggerid);
		$frmComent->addRow(S_COMMENTS,new CTextArea("comments",$trigger["comments"],100,25));
		$frmComent->addItemToBottomRow(new CButton("save",S_SAVE));
		$frmComent->addItemToBottomRow(new CButtonCancel('&triggerid='.$triggerid));

		$frmComent->show();
	}

	function insert_graph_form(){

		$frmGraph = new CFormTable(S_GRAPH, null, 'post');
		$frmGraph->setName('frm_graph');
		//$frmGraph->setHelp("web.graphs.graph.php");

		$items = get_request('items', array());

		if(isset($_REQUEST['graphid'])){
			$frmGraph->addVar('graphid', $_REQUEST['graphid']);

			$options = array(
						'graphids' => $_REQUEST['graphid'],
						'extendoutput' => 1
					);
			$graphs = CGraph::get($options);
			$row = reset($graphs);

			$frmGraph->setTitle(S_GRAPH.' "'.$row['name'].'"');
		}

		if(isset($_REQUEST['graphid']) && !isset($_REQUEST['form_refresh'])){
			$name = $row['name'];
			$width = $row['width'];
			$height = $row['height'];
			$ymin_type = $row['ymin_type'];
			$ymax_type = $row['ymax_type'];
			$yaxismin = $row['yaxismin'];
			$yaxismax = $row['yaxismax'];
			$ymin_itemid = $row['ymin_itemid'];
			$ymax_itemid = $row['ymax_itemid'];
			$showworkperiod = $row['show_work_period'];
			$showtriggers = $row['show_triggers'];
			$graphtype = $row['graphtype'];
			$legend = $row['show_legend'];
			$graph3d = $row['show_3d'];
			$percent_left = $row['percent_left'];
			$percent_right = $row['percent_right'];

			$options = array(
						'graphids' => $_REQUEST['graphid'],
						'sortfield' => 'sortorder',
						'extendoutput' => 1
					);

			$items = CGraphItem::get($options);
		}
		else{
			$name = get_request('name', '');
			$graphtype = get_request('graphtype', GRAPH_TYPE_NORMAL);

			if(($graphtype == GRAPH_TYPE_PIE) || ($graphtype == GRAPH_TYPE_EXPLODED)){
				$width = get_request('width', 400);
				$height = get_request('height', 300);
			}
			else{
				$width = get_request('width', 900);
				$height = get_request('height', 200);

			}
			$ymin_type = get_request('ymin_type', GRAPH_YAXIS_TYPE_CALCULATED);
			$ymax_type = get_request('ymax_type', GRAPH_YAXIS_TYPE_CALCULATED);
			$yaxismin = get_request('yaxismin', 0.00);
			$yaxismax = get_request('yaxismax', 100.00);
			$ymin_itemid = get_request('ymin_itemid', 0);
			$ymax_itemid	= get_request('ymax_itemid', 0);
			$showworkperiod = get_request('showworkperiod', 0);
			$showtriggers	= get_request('showtriggers', 0);
			$legend = get_request('legend' ,0);
			$graph3d	= get_request('graph3d', 0);
			$visible = get_request('visible');
			$percent_left  = 0;
			$percent_right = 0;

			if(isset($visible['percent_left'])) $percent_left = get_request('percent_left', 0);
			if(isset($visible['percent_right'])) $percent_right = get_request('percent_right', 0);
		}

/* reinit $_REQUEST */
		$_REQUEST['items']		= $items;
		$_REQUEST['name']		= $name;
		$_REQUEST['width']		= $width;
		$_REQUEST['height']		= $height;

		$_REQUEST['ymin_type']		= $ymin_type;
		$_REQUEST['ymax_type']		= $ymax_type;

		$_REQUEST['yaxismin']		= $yaxismin;
		$_REQUEST['yaxismax']		= $yaxismax;

		$_REQUEST['ymin_itemid']	= $ymin_itemid;
		$_REQUEST['ymax_itemid']	= $ymax_itemid;

		$_REQUEST['showworkperiod']	= $showworkperiod;
		$_REQUEST['showtriggers']	= $showtriggers;
		$_REQUEST['graphtype']		= $graphtype;
		$_REQUEST['legend']		= $legend;
		$_REQUEST['graph3d']		= $graph3d;
		$_REQUEST['percent_left']	= $percent_left;
		$_REQUEST['percent_right']	= $percent_right;
/********************/

		if($graphtype != GRAPH_TYPE_NORMAL){
			foreach($items as $gid => $gitem){
				if($gitem['type'] == GRAPH_ITEM_AGGREGATED)
					unset($items[$gid]);
			}
		}

		$icount = count($items);
		for($i=0; $i < $icount-1;){
// check if we deletd an item
			$next = $i+1;
			while(!isset($items[$next]) && ($next < ($icount-1))) $next++;

			if(isset($items[$next]) && ($items[$i]['sortorder'] == $items[$next]['sortorder']))
				for($j=$next; $j < $icount; $j++)
					if($items[$j-1]['sortorder'] >= $items[$j]['sortorder']) $items[$j]['sortorder']++;

			$i = $next;
		}

		asort_by_key($items, 'sortorder');

		$items = array_values($items);

		$group_gid = get_request('group_gid', array());

		$frmGraph->addVar('ymin_itemid', $ymin_itemid);
		$frmGraph->addVar('ymax_itemid', $ymax_itemid);

		$frmGraph->addRow(S_NAME, new CTextBox('name', $name, 32));

		$g_width = new CNumericBox('width', $width, 5);
		$g_width->setAttribute('onblur', 'javascript: submit();');
		$frmGraph->addRow(S_WIDTH, $g_width);

		$g_height = new CNumericBox('height', $height, 5);
		$g_height->setAttribute('onblur','javascript: submit();');
		$frmGraph->addRow(S_HEIGHT, $g_height);

		$cmbGType = new CComboBox('graphtype', $graphtype, 'graphs.submit(this)');
		$cmbGType->addItem(GRAPH_TYPE_NORMAL, S_NORMAL);
		$cmbGType->addItem(GRAPH_TYPE_STACKED, S_STACKED);
		$cmbGType->addItem(GRAPH_TYPE_PIE, S_PIE);
		$cmbGType->addItem(GRAPH_TYPE_EXPLODED, S_EXPLODED);

		zbx_add_post_js('graphs.graphtype = '.$graphtype.";\n");

		$frmGraph->addRow(S_GRAPH_TYPE, $cmbGType);
		
		
// items beforehead, to get only_hostid for miny maxy items
		$only_hostid = null;
		$monitored_hosts = null;

		if(count($items)){
			$frmGraph->addVar('items', $items);

			$keys = array_keys($items);
			$first = reset($keys);
			$last = end($keys);

			$items_table = new CTableInfo();
			foreach($items as $gid => $gitem){
				//if($graphtype == GRAPH_TYPE_STACKED && $gitem['type'] == GRAPH_ITEM_AGGREGATED) continue;
				$host = get_host_by_itemid($gitem['itemid']);
				$item = get_item_by_itemid($gitem['itemid']);

				if($host['status'] == HOST_STATUS_TEMPLATE)
					$only_hostid = $host['hostid'];
				else
					$monitored_hosts = 1;

				if($gitem['type'] == GRAPH_ITEM_AGGREGATED)
					$color = '-';
				else
					$color = new CColorCell(null,$gitem['color']);


				if($gid == $first){
					$do_up = null;
				}
				else{
					$do_up = new CSpan(S_UP,'link');
					$do_up->onClick("return create_var('".$frmGraph->getName()."','move_up',".$gid.", true);");
				}

				if($gid == $last){
					$do_down = null;
				}
				else{
					$do_down = new CSpan(S_DOWN,'link');
					$do_down->onClick("return create_var('".$frmGraph->getName()."','move_down',".$gid.", true);");
				}

				$description = new CSpan($host['host'].': '.item_description($item),'link');
				$description->onClick(
					'return PopUp("popup_gitem.php?list_name=items&dstfrm='.$frmGraph->getName().
					url_param($only_hostid, false, 'only_hostid').
					url_param($monitored_hosts, false, 'monitored_hosts').
					url_param($graphtype, false, 'graphtype').
					url_param($gitem, false).
					url_param($gid,false,'gid').
					url_param(get_request('graphid',0),false,'graphid').
					'",550,400,"graph_item_form");'
				);

				if(($graphtype == GRAPH_TYPE_PIE) || ($graphtype == GRAPH_TYPE_EXPLODED)){
					$items_table->addRow(array(
							new CCheckBox('group_gid['.$gid.']',isset($group_gid[$gid])),
							$description,
							graph_item_calc_fnc2str($gitem["calc_fnc"],$gitem["type"]),
							graph_item_type2str($gitem['type'],$gitem["periods_cnt"]),
							$color,
							array( $do_up, SPACE."|".SPACE, $do_down )
						));
				}
				else{
					$items_table->addRow(array(
							new CCheckBox('group_gid['.$gid.']',isset($group_gid[$gid])),
//							$gitem['sortorder'],
							$description,
							graph_item_calc_fnc2str($gitem["calc_fnc"],$gitem["type"]),
							graph_item_type2str($gitem['type'],$gitem["periods_cnt"]),
							($gitem['yaxisside']==GRAPH_YAXIS_SIDE_LEFT)?S_LEFT:S_RIGHT,
							graph_item_drawtype2str($gitem["drawtype"],$gitem["type"]),
							$color,
							array( $do_up, ((!is_null($do_up) && !is_null($do_down)) ? SPACE."|".SPACE : ''), $do_down )
						));
				}
			}
			$dedlete_button = new CButton('delete_item', S_DELETE_SELECTED);
		}
		else{
			$items_table = $dedlete_button = null;
		}
		
		if(($graphtype == GRAPH_TYPE_NORMAL) || ($graphtype == GRAPH_TYPE_STACKED)){
			$frmGraph->addRow(S_SHOW_WORKING_TIME,new CCheckBox('showworkperiod',$showworkperiod,null,1));
			$frmGraph->addRow(S_SHOW_TRIGGERS,new CCheckBox('showtriggers',$showtriggers,null,1));

			if($graphtype == GRAPH_TYPE_NORMAL){
				$percent_left = sprintf("%2.2f",$percent_left);
				$percent_right = sprintf("%2.2f",$percent_right);

				$pr_left_input = new CTextBox('percent_left',$percent_left,'5');
				$pr_left_chkbx = new CCheckBox('visible[percent_left]',1,"javascript: ShowHide('percent_left');",1);
				if($percent_left == 0){
					$pr_left_input->setAttribute('style','display: none;');
					$pr_left_chkbx->setChecked(0);
				}

				$pr_right_input = new CTextBox('percent_right',$percent_right,'5');
				$pr_right_chkbx = new CCheckBox('visible[percent_right]',1,"javascript: ShowHide('percent_right');",1);
				if($percent_right == 0){
					$pr_right_input->setAttribute('style','display: none;');
					$pr_right_chkbx->setChecked(0);
				}

				$frmGraph->addRow(S_PERCENTILE_LINE.' ('.S_LEFT.')',array($pr_left_chkbx,$pr_left_input));

				$frmGraph->addRow(S_PERCENTILE_LINE.' ('.S_RIGHT.')',array($pr_right_chkbx,$pr_right_input));
			}

			$yaxis_min = array();

			$cmbYType = new CComboBox('ymin_type',$ymin_type,'javascript: submit();');
			$cmbYType->addItem(GRAPH_YAXIS_TYPE_CALCULATED,S_CALCULATED);
			$cmbYType->addItem(GRAPH_YAXIS_TYPE_FIXED,S_FIXED);
			$cmbYType->addItem(GRAPH_YAXIS_TYPE_ITEM_VALUE,S_ITEM);

			$yaxis_min[] = $cmbYType;

			if($ymin_type == GRAPH_YAXIS_TYPE_FIXED){
				$yaxis_min[] = new CTextBox("yaxismin",$yaxismin,9);
			}
			else if($ymin_type == GRAPH_YAXIS_TYPE_ITEM_VALUE){
				$frmGraph->addVar('yaxismin',$yaxismin);

				$ymin_name = '';
				if($ymin_itemid > 0){
					$min_host = get_host_by_itemid($ymin_itemid);
					$min_item = get_item_by_itemid($ymin_itemid);
					$ymin_name = $min_host['host'].':'.item_description($min_item);
				}

				if(count($items)){
					$yaxis_min[] = new CTextBox("ymin_name",$ymin_name,80,'yes');
					$yaxis_min[] = new CButton('yaxis_min',S_SELECT,'javascript: '.
													"return PopUp('popup.php?dstfrm=".$frmGraph->getName().
													url_param($only_hostid, false, 'only_hostid').
													url_param($monitored_hosts, false, 'monitored_hosts').
														"&dstfld1=ymin_itemid".
														"&dstfld2=ymin_name".
														"&srctbl=items".
														"&srcfld1=itemid".
														"&srcfld2=description',0,0,'zbx_popup_item');");
				}
				else{
					$yaxis_min[] = SPACE.S_ADD_GRAPH_ITEMS;
				}
			}
			else{
				$frmGraph->addVar('yaxismin', $yaxismin);
			}

			$frmGraph->addRow(S_YAXIS_MIN_VALUE, $yaxis_min);

			$yaxis_max = array();

			$cmbYType = new CComboBox("ymax_type",$ymax_type,"submit()");
			$cmbYType->addItem(GRAPH_YAXIS_TYPE_CALCULATED,S_CALCULATED);
			$cmbYType->addItem(GRAPH_YAXIS_TYPE_FIXED,S_FIXED);
			$cmbYType->addItem(GRAPH_YAXIS_TYPE_ITEM_VALUE,S_ITEM);

			$yaxis_max[] = $cmbYType;

			if($ymax_type == GRAPH_YAXIS_TYPE_FIXED){
				$yaxis_max[] = new CTextBox('yaxismax',$yaxismax,9);
			}
			else if($ymax_type == GRAPH_YAXIS_TYPE_ITEM_VALUE){
				$frmGraph->addVar('yaxismax',$yaxismax);

				$ymax_name = '';
				if($ymax_itemid > 0){
					$max_host = get_host_by_itemid($ymax_itemid);
					$max_item = get_item_by_itemid($ymax_itemid);
					$ymax_name = $max_host['host'].':'.item_description($max_item);
				}
	
				if(count($items)){
					$yaxis_max[] = new CTextBox("ymax_name",$ymax_name,80,'yes');
					$yaxis_max[] = new CButton('yaxis_max',S_SELECT,'javascript: '.
													"return PopUp('popup.php?dstfrm=".$frmGraph->getName().
													url_param($only_hostid, false, 'only_hostid').
													url_param($monitored_hosts, false, 'monitored_hosts').
														"&dstfld1=ymax_itemid".
														"&dstfld2=ymax_name".
														"&srctbl=items".
														"&srcfld1=itemid".
														"&srcfld2=description',0,0,'zbx_popup_item');");
				}
				else{
					$yaxis_max[] = SPACE.S_ADD_GRAPH_ITEMS;
				}
			}
			else{
				$frmGraph->addVar('yaxismax',$yaxismax);
			}

			$frmGraph->addRow(S_YAXIS_MAX_VALUE, $yaxis_max);
		}
		else{
			$frmGraph->addRow(S_3D_VIEW,new CCheckBox('graph3d',$graph3d,'javascript: graphs.submit(this);',1));
			$frmGraph->addRow(S_LEGEND,new CCheckBox('legend',$legend,'javascript: graphs.submit(this);',1));
		}

		$frmGraph->addRow(S_ITEMS,
				array(
					$items_table,
					new CButton('add_item',S_ADD,
						"return PopUp('popup_gitem.php?dstfrm=".$frmGraph->GetName().
						url_param($only_hostid, false, 'only_hostid').
						url_param($monitored_hosts, false, 'monitored_hosts').
						url_param($graphtype, false, 'graphtype').
						"',550,400,'graph_item_form');"),
					$dedlete_button
				));
		unset($items_table, $dedlete_button);

		$frmGraph->addItemToBottomRow(new CButton('save',S_SAVE));
		if(isset($_REQUEST['graphid'])){
			$frmGraph->addItemToBottomRow(SPACE);
			$frmGraph->addItemToBottomRow(new CButton('clone',S_CLONE));
			$frmGraph->addItemToBottomRow(SPACE);
			$frmGraph->addItemToBottomRow(new CButtonDelete(S_DELETE_GRAPH_Q,url_param('graphid').
				url_param('groupid').url_param('hostid')));
		}
		$frmGraph->addItemToBottomRow(SPACE);
		$frmGraph->addItemToBottomRow(new CButtonCancel(url_param('groupid').url_param('hostid')));

		$frmGraph->show();
	}

	function get_maintenance_form(){
		$frm_title = S_MAINTENANCE;

		if(isset($_REQUEST['maintenanceid']) && !isset($_REQUEST["form_refresh"])){
			$sql = 'SELECT m.* '.
				' FROM maintenances m '.
				' WHERE '.DBin_node('m.maintenanceid').
					' AND m.maintenanceid='.$_REQUEST['maintenanceid'];
			$maintenance = DBfetch(DBSelect($sql));

			$frm_title = S_USER.' ['.$maintenance['name'].']';

			$mname			= $maintenance['name'];
			$maintenance_type	= $maintenance['maintenance_type'];

			$active_since		= $maintenance['active_since'];
			$active_till		= $maintenance['active_till'];

			$description		= $maintenance['description'];

		}
		else{

			$mname			= get_request('mname','');
			$maintenance_type	= get_request('maintenance_type',0);

			$active_since		= get_request('active_since',time());
			$active_till		= get_request('active_till',time()+86400);

			$description		= get_request('description','');
		}

		$tblMntc = new CTable('','nowrap');

		$tblMntc->addRow(array(S_NAME, new CTextBox('mname', $mname, 50)));

/* form row generation */
		$cmbType =  new CComboBox('maintenance_type', $maintenance_type);
		$cmbType->addItem(MAINTENANCE_TYPE_NORMAL, S_WITH_DATA_COLLECTION);
		$cmbType->addItem(MAINTENANCE_TYPE_NODATA, S_NO_DATA_COLLECTION);
		$tblMntc->addRow(array(S_MAINTENANCE_TYPE, $cmbType));


/***********************************************************/

		$tblMntc->addItem(new Cvar('active_since',$active_since));
		$tblMntc->addItem(new Cvar('active_till',$active_till));

		$clndr_icon = new CImg('images/general/bar/cal.gif','calendar', 16, 12, 'pointer');

		$clndr_icon->addAction('onclick','javascript: '.
											'var pos = getPosition(this); '.
											'pos.top+=10; '.
											'pos.left+=16; '.
											"CLNDR['mntc_active_since'].clndr.clndrshow(pos.top,pos.left);");

		$filtertimetab = new CTable(null,'calendar');
		$filtertimetab->setAttribute('width','10%');

		$filtertimetab->setCellPadding(0);
		$filtertimetab->setCellSpacing(0);

		$filtertimetab->addRow(array(
								new CNumericBox('mntc_since_day',(($active_since>0)?date('d',$active_since):''),2),
								'/',
								new CNumericBox('mntc_since_month',(($active_since>0)?date('m',$active_since):''),2),
								'/',
								new CNumericBox('mntc_since_year',(($active_since>0)?date('Y',$active_since):''),4),
								SPACE,
								new CNumericBox('mntc_since_hour',(($active_since>0)?date('H',$active_since):''),2),
								':',
								new CNumericBox('mntc_since_minute',(($active_since>0)?date('i',$active_since):''),2),
								$clndr_icon
						));

		zbx_add_post_js('create_calendar(null,'.
						'["mntc_since_day","mntc_since_month","mntc_since_year","mntc_since_hour","mntc_since_minute"],'.
						'"mntc_active_since",'.
						'"active_since");');

		$clndr_icon->addAction('onclick','javascript: '.
											'var pos = getPosition(this); '.
											'pos.top+=10; '.
											'pos.left+=16; '.
											"CLNDR['mntc_active_till'].clndr.clndrshow(pos.top,pos.left);");

		$tblMntc->addRow(array(S_ACTIVE_SINCE,$filtertimetab));

		$filtertimetab = new CTable(null,'calendar');
		$filtertimetab->setAttribute('width','10%');

		$filtertimetab->setCellPadding(0);
		$filtertimetab->setCellSpacing(0);

		$filtertimetab->addRow(array(
								new CNumericBox('mntc_till_day',(($active_till>0)?date('d',$active_till):''),2),
								'/',
								new CNumericBox('mntc_till_month',(($active_till>0)?date('m',$active_till):''),2),
								'/',
								new CNumericBox('mntc_till_year',(($active_till>0)?date('Y',$active_till):''),4),
								SPACE,
								new CNumericBox('mntc_till_hour',(($active_till>0)?date('H',$active_till):''),2),
								':',
								new CNumericBox('mntc_till_minute',(($active_till>0)?date('i',$active_till):''),2),
								$clndr_icon
						));
		zbx_add_post_js('create_calendar(null,'.
						'["mntc_till_day","mntc_till_month","mntc_till_year","mntc_till_hour","mntc_till_minute"],'.
						'"mntc_active_till",'.
						'"active_till");');

		$tblMntc->addRow(array(S_ACTIVE_TILL, $filtertimetab));
//-------

		$tblMntc->addRow(array(S_DESCRIPTION, new CTextArea('description', $description,66,5)));


		$tblMaintenance = new CTableInfo();
		$tblMaintenance->addRow($tblMntc);

		$td = new CCol(array(new CButton('save',S_SAVE)));
		$td->setAttribute('colspan','2');
		$td->setAttribute('style','text-align: right;');

		if(isset($_REQUEST['maintenanceid'])){

			$td->addItem(SPACE);
			$td->addItem(new CButton('clone',S_CLONE));
			$td->addItem(SPACE);
			$td->addItem(new CButtonDelete(S_DELETE_MAINTENANCE_PERIOD_Q,url_param('form').url_param('config').url_param('maintenanceid')));

		}
		$td->addItem(SPACE);
		$td->addItem(new CButtonCancel(url_param("maintenanceid")));

		$tblMaintenance->setFooter($td);
	return $tblMaintenance;
	}

	function get_maintenance_hosts_form(&$form){
		global $USER_DETAILS;
		$tblHlink = new CTableInfo();
		$tblHlink->setAttribute('style', 'background-color: #CCC;');

		$available_hosts = get_accessible_hosts_by_user($USER_DETAILS, PERM_READ_WRITE);

//		validate_group(PERM_READ_WRITE,array('real_hosts'),'web.last.conf.groupid');

		$twb_groupid = get_request('twb_groupid', 0);

		$options = array(
			'editable' => 1,
			'extendoutput' => 1,
			'real_hosts' => 1,
		);
		$groups = CHostGroup::get($options);
		$groups = zbx_toHash($groups, 'groupid');
		order_result($groups, 'name');

		if(!isset($groups[$twb_groupid])){
			$twb_groupid = key($groups);
		}

		$cmbGroups = new CComboBox('twb_groupid', $twb_groupid, 'submit()');
		foreach($groups as $group){
			$cmbGroups->addItem($group['groupid'], $group['name']);
		}

		$hostids = get_request('hostids', array());

		$host_tb = new CTweenBox($form, 'hostids', null, 10);

		if(isset($_REQUEST['maintenanceid']) && !isset($_REQUEST['form_refresh'])){
			$sql_from = ', maintenances_hosts mh ';
			$sql_where = ' AND h.hostid=mh.hostid '.
							' AND mh.maintenanceid='.$_REQUEST['maintenanceid'];
		}
		else{
			$sql_from = '';
			$sql_where =  'AND '.DBcondition('h.hostid', $hostids);
		}

		$sql = 'SELECT DISTINCT h.hostid, h.host '.
				' FROM hosts h '.$sql_from.
				' WHERE '.DBcondition('h.hostid',$available_hosts).
					$sql_where.
					' AND h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.') '.
				' ORDER BY h.host';
		$db_hosts = DBselect($sql);
		while($host = DBfetch($db_hosts)){
			$hostids[$host['hostid']] = $host['hostid'];
			$host_tb->addItem($host['hostid'], $host['host'], true);
		}


		$options = array(
			'editable' => 1,
			'extendoutput' => 1,
			'groupids' => $twb_groupid
		);
		$group_hosts = CHost::get($options);
		order_result($group_hosts, 'host');

		foreach($group_hosts as $ghost){
			if(isset($hostids[$ghost['hostid']])) continue;
			$host_tb->addItem($ghost['hostid'], $ghost['host'], false);
		}

		$tblHlink->addRow($host_tb->Get(S_IN.SPACE.S_MAINTENANCE, array(S_OTHER.SPACE.S_HOSTS.SPACE.'|'.SPACE.S_GROUP.SPACE, $cmbGroups)));

	return $tblHlink;
	}

	function get_maintenance_groups_form($form){
		global $USER_DETAILS;

		$tblGlink = new CTableInfo();
		$tblGlink->setAttribute('style','background-color: #CCC;');

		$available_groups = get_accessible_groups_by_user($USER_DETAILS,PERM_READ_WRITE);

		$groupids = get_request('groupids', array());

		$group_tb = new CTweenBox($form,'groupids',null,10);

		if(isset($_REQUEST['maintenanceid']) && !isset($_REQUEST["form_refresh"])){
			$sql_from = ', maintenances_groups mg ';
			$sql_where = ' AND g.groupid=mg.groupid '.
							' AND mg.maintenanceid='.$_REQUEST['maintenanceid'];
		}
		else{
			$sql_from = '';
			$sql_where =  'AND '.DBcondition('g.groupid',$groupids);
		}

		$sql = 'SELECT DISTINCT g.groupid, g.name '.
				' FROM hosts h, hosts_groups hg, groups g '.$sql_from.
				' WHERE hg.groupid=g.groupid'.
					' AND h.hostid=hg.hostid '.
					' AND '.DBcondition('g.groupid',$available_groups).
					' AND h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.') '.
					$sql_where.
				' ORDER BY g.name';
//SDI($sql);
		$db_groups = DBselect($sql);
		while($group = DBfetch($db_groups)){
			$groupids[$group['groupid']] = $group['groupid'];
			$group_tb->addItem($group['groupid'],$group['name'], true);
		}


		$sql = 'SELECT DISTINCT g.* '.
				' FROM hosts h, hosts_groups hg, groups g '.
				' WHERE hg.groupid=g.groupid'.
					' AND h.hostid=hg.hostid '.
					' AND '.DBcondition('g.groupid',$available_groups).
					' AND '.DBcondition('g.groupid',$groupids,true).
					' AND h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.') '.
				' ORDER BY g.name';
		$db_groups = DBselect($sql);
		while($group = DBfetch($db_groups)){
			$group_tb->addItem($group['groupid'],$group['name'], false);
		}

		$tblGlink->addRow($group_tb->Get(S_IN.SPACE.S_MAINTENANCE,S_OTHER.SPACE.S_GROUPS));

	return $tblGlink;
	}

	function get_maintenance_periods(){
		$tblPeriod = new CTableInfo();
		$tblPeriod->setAttribute('style','background-color: #CCC;');

		if(isset($_REQUEST['maintenanceid']) && !isset($_REQUEST["form_refresh"])){

			$timeperiods = array();
			$sql = 'SELECT DISTINCT mw.maintenanceid, tp.* '.
					' FROM timeperiods tp, maintenances_windows mw '.
					' WHERE mw.maintenanceid='.$_REQUEST['maintenanceid'].
						' AND tp.timeperiodid=mw.timeperiodid '.
					' ORDER BY tp.timeperiod_type ASC';
			$db_timeperiods = DBselect($sql);
			while($timeperiod = DBfetch($db_timeperiods)){
				$timeperiods[] = $timeperiod;
			}

		}
		else {
			$timeperiods = get_request('timeperiods', array());
		}

		$tblPeriod->setHeader(array(
				new CCheckBox('all_periods',null,'checkAll("'.S_PERIOD.'","all_periods","g_timeperiodid");'),
				S_PERIOD_TYPE,
				S_SCHEDULE,
				S_PERIOD,
//				S_NEXT_RUN,
				S_ACTION
			));

//		zbx_rksort($timeperiods);
		foreach($timeperiods as $id => $timeperiod){
			$period_type = timeperiod_type2str($timeperiod['timeperiod_type']);
			$shedule_str = shedule2str($timeperiod);

			$tblPeriod->addRow(array(
				new CCheckBox('g_timeperiodid[]', 'no', null, $id),
				$period_type,
				new CCol($shedule_str, 'wraptext'),
				zbx_date2age(0,$timeperiod['period']),
//				0,
				new CButton('edit_timeperiodid['.$id.']',S_EDIT)
				));

			$tblPeriod->addItem(new Cvar('timeperiods['.$id.'][timeperiod_type]',	$timeperiod['timeperiod_type']));

			$tblPeriod->addItem(new Cvar('timeperiods['.$id.'][every]',		$timeperiod['every']));
			$tblPeriod->addItem(new Cvar('timeperiods['.$id.'][month]',		$timeperiod['month']));
			$tblPeriod->addItem(new Cvar('timeperiods['.$id.'][dayofweek]',		$timeperiod['dayofweek']));
			$tblPeriod->addItem(new Cvar('timeperiods['.$id.'][day]',		$timeperiod['day']));
			$tblPeriod->addItem(new Cvar('timeperiods['.$id.'][start_time]',	$timeperiod['start_time']));
			$tblPeriod->addItem(new Cvar('timeperiods['.$id.'][start_date]',	$timeperiod['start_date']));
			$tblPeriod->addItem(new Cvar('timeperiods['.$id.'][period]',		$timeperiod['period']));
		}
		unset($timeperiods);

		$tblPeriodFooter = new CTableInfo(null);

		$oper_buttons = array();
		if(!isset($_REQUEST['new_timeperiod'])){
			$oper_buttons[] = new CButton('new_timeperiod',S_NEW);
		}

		if($tblPeriod->ItemsCount() > 0 ){
			$oper_buttons[] = new CButton('del_timeperiod',S_DELETE_SELECTED);
		}

		$td = new CCol($oper_buttons);
		$td->setAttribute('colspan',7);
		$td->setAttribute('style','text-align: right;');


		$tblPeriodFooter->setFooter($td);
// end of condition list preparation
	return array($tblPeriod,$tblPeriodFooter);
	}

	function get_timeperiod_form(){
		$tblPeriod = new CTableInfo();

		/* init new_timeperiod variable */
		$new_timeperiod = get_request('new_timeperiod', array());

		$new = is_array($new_timeperiod);

		if(is_array($new_timeperiod) && isset($new_timeperiod['id'])){
			$tblPeriod->addItem(new Cvar('new_timeperiod[id]',$new_timeperiod['id']));
		}

		if(!is_array($new_timeperiod)){
			$new_timeperiod = array();
			$new_timeperiod['timeperiod_type'] = TIMEPERIOD_TYPE_ONETIME;
		}

		if(!isset($new_timeperiod['every']))			$new_timeperiod['every']		= 1;
		if(!isset($new_timeperiod['day']))			$new_timeperiod['day']			= 1;
		if(!isset($new_timeperiod['hour']))			$new_timeperiod['hour']			= 12;
		if(!isset($new_timeperiod['minute']))			$new_timeperiod['minute']		= 0;
		if(!isset($new_timeperiod['start_date']))		$new_timeperiod['start_date']		= 0;

		if(!isset($new_timeperiod['period_days']))		$new_timeperiod['period_days']		= 0;
		if(!isset($new_timeperiod['period_hours']))		$new_timeperiod['period_hours']		= 1;
		if(!isset($new_timeperiod['period_minutes']))		$new_timeperiod['period_minutes']	= 0;

		if(!isset($new_timeperiod['month_date_type']))	$new_timeperiod['month_date_type'] = !(bool)$new_timeperiod['day'];

// START TIME
		if(isset($new_timeperiod['start_time'])){
			$new_timeperiod['hour'] = floor($new_timeperiod['start_time'] / 3600);
			$new_timeperiod['minute'] = floor(($new_timeperiod['start_time'] - ($new_timeperiod['hour'] * 3600)) / 60);
		}
//--

// PERIOD
		if(isset($new_timeperiod['period'])){
			$new_timeperiod['period_days'] = floor($new_timeperiod['period'] / 86400);
			$new_timeperiod['period_hours'] = floor(($new_timeperiod['period'] - ($new_timeperiod['period_days'] * 86400)) / 3600);
			$new_timeperiod['period_minutes'] = floor(($new_timeperiod['period'] - $new_timeperiod['period_days'] * 86400 -
					$new_timeperiod['period_hours'] * 3600) / 60);
		}
//--

// DAYSOFWEEK
		$dayofweek = '';
		$dayofweek .= (!isset($new_timeperiod['dayofweek_mo']))?'0':'1';
		$dayofweek .= (!isset($new_timeperiod['dayofweek_tu']))?'0':'1';
		$dayofweek .= (!isset($new_timeperiod['dayofweek_we']))?'0':'1';
		$dayofweek .= (!isset($new_timeperiod['dayofweek_th']))?'0':'1';
		$dayofweek .= (!isset($new_timeperiod['dayofweek_fr']))?'0':'1';
		$dayofweek .= (!isset($new_timeperiod['dayofweek_sa']))?'0':'1';
		$dayofweek .= (!isset($new_timeperiod['dayofweek_su']))?'0':'1';

		if(isset($new_timeperiod['dayofweek'])){
			$dayofweek = zbx_num2bitstr($new_timeperiod['dayofweek'],true);
		}

		$new_timeperiod['dayofweek_mo'] = $dayofweek[0];
		$new_timeperiod['dayofweek_tu'] = $dayofweek[1];
		$new_timeperiod['dayofweek_we'] = $dayofweek[2];
		$new_timeperiod['dayofweek_th'] = $dayofweek[3];
		$new_timeperiod['dayofweek_fr'] = $dayofweek[4];
		$new_timeperiod['dayofweek_sa'] = $dayofweek[5];
		$new_timeperiod['dayofweek_su'] = $dayofweek[6];
//--

// MONTHS
		$month = '';
		$month .= (!isset($new_timeperiod['month_jan']))?'0':'1';
		$month .= (!isset($new_timeperiod['month_feb']))?'0':'1';
		$month .= (!isset($new_timeperiod['month_mar']))?'0':'1';
		$month .= (!isset($new_timeperiod['month_apr']))?'0':'1';
		$month .= (!isset($new_timeperiod['month_may']))?'0':'1';
		$month .= (!isset($new_timeperiod['month_jun']))?'0':'1';
		$month .= (!isset($new_timeperiod['month_jul']))?'0':'1';
		$month .= (!isset($new_timeperiod['month_aug']))?'0':'1';
		$month .= (!isset($new_timeperiod['month_sep']))?'0':'1';
		$month .= (!isset($new_timeperiod['month_oct']))?'0':'1';
		$month .= (!isset($new_timeperiod['month_nov']))?'0':'1';
		$month .= (!isset($new_timeperiod['month_dec']))?'0':'1';

		if(isset($new_timeperiod['month'])){
			$month = zbx_num2bitstr($new_timeperiod['month'],true);
		}

		$new_timeperiod['month_jan'] = $month[0];
		$new_timeperiod['month_feb'] = $month[1];
		$new_timeperiod['month_mar'] = $month[2];
		$new_timeperiod['month_apr'] = $month[3];
		$new_timeperiod['month_may'] = $month[4];
		$new_timeperiod['month_jun'] = $month[5];
		$new_timeperiod['month_jul'] = $month[6];
		$new_timeperiod['month_aug'] = $month[7];
		$new_timeperiod['month_sep'] = $month[8];
		$new_timeperiod['month_oct'] = $month[9];
		$new_timeperiod['month_nov'] = $month[10];
		$new_timeperiod['month_dec'] = $month[11];

//--

		$bit_dayofweek = zbx_str_revert($dayofweek);
		$bit_month = zbx_str_revert($month);

		$cmbType = new CComboBox('new_timeperiod[timeperiod_type]', $new_timeperiod['timeperiod_type'],'submit()');
			$cmbType->addItem(TIMEPERIOD_TYPE_ONETIME,	S_ONE_TIME_ONLY);
			$cmbType->addItem(TIMEPERIOD_TYPE_DAILY,	S_DAILY);
			$cmbType->addItem(TIMEPERIOD_TYPE_WEEKLY,	S_WEEKLY);
			$cmbType->addItem(TIMEPERIOD_TYPE_MONTHLY,	S_MONTHLY);

		$tblPeriod->addRow(array(S_PERIOD_TYPE, $cmbType));

		if($new_timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_DAILY){
			$tblPeriod->addItem(new Cvar('new_timeperiod[dayofweek]',bindec($bit_dayofweek)));
			$tblPeriod->addItem(new Cvar('new_timeperiod[month]',bindec($bit_month)));

			$tblPeriod->addItem(new Cvar('new_timeperiod[day]',$new_timeperiod['day']));
			$tblPeriod->addItem(new Cvar('new_timeperiod[start_date]',$new_timeperiod['start_date']));
			$tblPeriod->addItem(new Cvar('new_timeperiod[month_date_type]',$new_timeperiod['month_date_type']));

			$tblPeriod->addRow(array(S_EVERY_DAY_S,		new CNumericBox('new_timeperiod[every]', $new_timeperiod['every'], 3)));
		}
		else if($new_timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_WEEKLY){
			$tblPeriod->addItem(new Cvar('new_timeperiod[month]',bindec($bit_month)));
			$tblPeriod->addItem(new Cvar('new_timeperiod[day]',$new_timeperiod['day']));
			$tblPeriod->addItem(new Cvar('new_timeperiod[start_date]',$new_timeperiod['start_date']));
			$tblPeriod->addItem(new Cvar('new_timeperiod[month_date_type]',$new_timeperiod['month_date_type']));

			$tblPeriod->addRow(array(S_EVERY_WEEK_S,	new CNumericBox('new_timeperiod[every]', $new_timeperiod['every'], 2)));

			$tabDays = new CTable();
			$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_mo]',$dayofweek[0],null,1), S_MONDAY));
			$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_tu]',$dayofweek[1],null,1), S_TUESDAY));
			$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_we]',$dayofweek[2],null,1), S_WEDNESDAY));
			$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_th]',$dayofweek[3],null,1), S_THURSDAY));
			$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_fr]',$dayofweek[4],null,1), S_FRIDAY));
			$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_sa]',$dayofweek[5],null,1), S_SATURDAY));
			$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_su]',$dayofweek[6],null,1), S_SUNDAY));

			$tblPeriod->addRow(array(S_DAY_OF_WEEK,$tabDays));

		}
		else if($new_timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_MONTHLY){
			$tblPeriod->addItem(new Cvar('new_timeperiod[start_date]',$new_timeperiod['start_date']));

			$tabMonths = new CTable();
			$tabMonths->addRow(array(
								new CCheckBox('new_timeperiod[month_jan]',$month[0],null,1), S_JANUARY,
								SPACE,SPACE,
								new CCheckBox('new_timeperiod[month_jul]',$month[6],null,1), S_JULY
								 ));

			$tabMonths->addRow(array(
								new CCheckBox('new_timeperiod[month_feb]',$month[1],null,1), S_FEBRUARY,
								SPACE,SPACE,
								new CCheckBox('new_timeperiod[month_aug]',$month[7],null,1), S_AUGUST
								 ));

			$tabMonths->addRow(array(
								new CCheckBox('new_timeperiod[month_mar]',$month[2],null,1), S_MARCH,
								SPACE,SPACE,
								new CCheckBox('new_timeperiod[month_sep]',$month[8],null,1), S_SEPTEMBER
								 ));

			$tabMonths->addRow(array(
								new CCheckBox('new_timeperiod[month_apr]',$month[3],null,1), S_APRIL,
								SPACE,SPACE,
								new CCheckBox('new_timeperiod[month_oct]',$month[9],null,1), S_OCTOBER
								 ));

			$tabMonths->addRow(array(
								new CCheckBox('new_timeperiod[month_may]',$month[4],null,1), S_MAY,
								SPACE,SPACE,
								new CCheckBox('new_timeperiod[month_nov]',$month[10],null,1), S_NOVEMBER
								 ));

			$tabMonths->addRow(array(
								new CCheckBox('new_timeperiod[month_jun]',$month[5],null,1), S_JUNE,
								SPACE,SPACE,
								new CCheckBox('new_timeperiod[month_dec]',$month[11],null,1), S_DECEMBER
								 ));

			$tblPeriod->addRow(array(S_MONTH,	$tabMonths));

			$radioDaily = new CTag('input');
			$radioDaily->setAttribute('type','radio');
			$radioDaily->setAttribute('name','new_timeperiod[month_date_type]');
			$radioDaily->setAttribute('value','0');
			$radioDaily->setAttribute('onclick','submit()');

			$radioDaily2 = new CTag('input');
			$radioDaily2->setAttribute('type','radio');
			$radioDaily2->setAttribute('name','new_timeperiod[month_date_type]');
			$radioDaily2->setAttribute('value','1');
			$radioDaily2->setAttribute('onclick','submit()');

			if($new_timeperiod['month_date_type']){
				$radioDaily2->setAttribute('checked','checked');
			}
			else{
				$radioDaily->setAttribute('checked','checked');
			}

			$tblPeriod->addRow(array(S_DATE, array($radioDaily, S_DAY, SPACE, SPACE, $radioDaily2, S_DAY_OF_WEEK)));

			if($new_timeperiod['month_date_type'] > 0){
				$tblPeriod->addItem(new Cvar('new_timeperiod[day]',$new_timeperiod['day']));

				$cmbCount = new CComboBox('new_timeperiod[every]', $new_timeperiod['every']);
					$cmbCount->addItem(1, S_FIRST);
					$cmbCount->addItem(2, S_SECOND);
					$cmbCount->addItem(3, S_THIRD);
					$cmbCount->addItem(4, S_FOURTH);
					$cmbCount->addItem(5, S_LAST);

				$td = new CCol($cmbCount);
				$td->setColSpan(2);

				$tabDays = new CTable();
				$tabDays->addRow($td);
				$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_mo]',$dayofweek[0],null,1), S_MONDAY));
				$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_tu]',$dayofweek[1],null,1), S_TUESDAY));
				$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_we]',$dayofweek[2],null,1), S_WEDNESDAY));
				$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_th]',$dayofweek[3],null,1), S_THURSDAY));
				$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_fr]',$dayofweek[4],null,1), S_FRIDAY));
				$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_sa]',$dayofweek[5],null,1), S_SATURDAY));
				$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_su]',$dayofweek[6],null,1), S_SUNDAY));


				$tblPeriod->addRow(array(S_DAY_OF_WEEK,$tabDays));
			}
			else{
				$tblPeriod->addItem(new Cvar('new_timeperiod[dayofweek]',bindec($bit_dayofweek)));

				$tblPeriod->addRow(array(S_DAY_OF_MONTH, new CNumericBox('new_timeperiod[day]', $new_timeperiod['day'], 2)));
			}
		}
		else{
			$tblPeriod->addItem(new Cvar('new_timeperiod[every]',$new_timeperiod['every']));
			$tblPeriod->addItem(new Cvar('new_timeperiod[dayofweek]',bindec($bit_dayofweek)));
			$tblPeriod->addItem(new Cvar('new_timeperiod[month]',bindec($bit_month)));
			$tblPeriod->addItem(new Cvar('new_timeperiod[day]',$new_timeperiod['day']));
			$tblPeriod->addItem(new Cvar('new_timeperiod[hour]',$new_timeperiod['hour']));
			$tblPeriod->addItem(new Cvar('new_timeperiod[minute]',$new_timeperiod['minute']));
			$tblPeriod->addItem(new Cvar('new_timeperiod[month_date_type]',$new_timeperiod['month_date_type']));

/***********************************************************/
			$tblPeriod->addItem(new Cvar('new_timeperiod[start_date]',$new_timeperiod['start_date']));

			$clndr_icon = new CImg('images/general/bar/cal.gif','calendar', 16, 12, 'pointer');

			$clndr_icon->addAction('onclick','javascript: '.
												'var pos = getPosition(this); '.
												'pos.top+=10; '.
												'pos.left+=16; '.
												"CLNDR['new_timeperiod_date'].clndr.clndrshow(pos.top,pos.left);");

			$filtertimetab = new CTable(null,'calendar');
			$filtertimetab->setAttribute('width','10%');

			$filtertimetab->setCellPadding(0);
			$filtertimetab->setCellSpacing(0);

			$filtertimetab->addRow(array(
					new CNumericBox('new_timeperiod_day',(($new_timeperiod['start_date']>0)?date('d',$new_timeperiod['start_date']):''),2),
					'/',
					new CNumericBox('new_timeperiod_month',(($new_timeperiod['start_date']>0)?date('m',$new_timeperiod['start_date']):''),2),
					'/',
					new CNumericBox('new_timeperiod_year',(($new_timeperiod['start_date']>0)?date('Y',$new_timeperiod['start_date']):''),4),
					SPACE,
					new CNumericBox('new_timeperiod_hour',(($new_timeperiod['start_date']>0)?date('H',$new_timeperiod['start_date']):''),2),
					':',
					new CNumericBox('new_timeperiod_minute',(($new_timeperiod['start_date']>0)?date('i',$new_timeperiod['start_date']):''),2),
					$clndr_icon
					));

			zbx_add_post_js('create_calendar(null,'.
							'["new_timeperiod_day","new_timeperiod_month","new_timeperiod_year","new_timeperiod_hour","new_timeperiod_minute"],'.
							'"new_timeperiod_date",'.
							'"new_timeperiod[start_date]");');

			$clndr_icon->addAction('onclick','javascript: '.
												'var pos = getPosition(this); '.
												'pos.top+=10; '.
												'pos.left+=16; '.
												"CLNDR['mntc_active_till'].clndr.clndrshow(pos.top,pos.left);");

			$tblPeriod->addRow(array(S_DATE,$filtertimetab));

//-------
		}

		if($new_timeperiod['timeperiod_type'] != TIMEPERIOD_TYPE_ONETIME){
			$tabTime = new CTable(null,'calendar');
			$tabTime->addRow(array(new CNumericBox('new_timeperiod[hour]', $new_timeperiod['hour'], 2),':',new CNumericBox('new_timeperiod[minute]', $new_timeperiod['minute'], 2)));

			$tblPeriod->addRow(array(S_AT.SPACE.'('.S_HOUR.':'.S_MINUTE.')', $tabTime));
		}


		$perHours = new CComboBox('new_timeperiod[period_hours]',$new_timeperiod['period_hours']);
		for($i=0; $i < 25; $i++){
			$perHours->addItem($i,$i.SPACE);
		}
		$perMinutes = new CComboBox('new_timeperiod[period_minutes]',$new_timeperiod['period_minutes']);
		for($i=0; $i < 60; $i++){
			$perMinutes->addItem($i,$i.SPACE);
		}
		$tblPeriod->addRow(array(
							S_MAINTENANCE_PERIOD_LENGTH,
							array(
								new CNumericBox('new_timeperiod[period_days]',$new_timeperiod['period_days'],3),
								S_DAYS.SPACE.SPACE,
								$perHours,
								SPACE.S_HOURS,
								$perMinutes,
								SPACE.S_MINUTES
							)));
//			$tabPeriod = new CTable();
//			$tabPeriod->addRow(S_DAYS)
//			$tblPeriod->addRow(array(S_AT.SPACE.'('.S_HOUR.':'.S_MINUTE.')', $tabTime));

		$td = new CCol(array(
			new CButton('add_timeperiod', $new ? S_SAVE : S_ADD),
			SPACE,
			new CButton('cancel_new_timeperiod',S_CANCEL)
			));

		$td->setAttribute('colspan','3');
		$td->setAttribute('style','text-align: right;');

		$tblPeriod->setFooter($td);

	return $tblPeriod;
	}

	function get_act_action_form($action=null){
		$tblAct = new CTable('','nowrap');

		if(isset($_REQUEST['actionid']) && empty($action)){
			$action = get_action_by_actionid($_REQUEST['actionid']);
		}

		if(isset($_REQUEST['actionid']) && !isset($_REQUEST['form_refresh'])){
			$name		= $action['name'];
			$eventsource	= $action['eventsource'];
			$esc_period	= $action['esc_period'];
			$status		= $action['status'];

			$def_shortdata	= $action['def_shortdata'];
			$def_longdata	= $action['def_longdata'];

			$recovery_msg	= $action['recovery_msg'];
			$r_shortdata	= $action['r_shortdata'];
			$r_longdata	= $action['r_longdata'];

			if($esc_period) $_REQUEST['escalation'] = 1;
		}
		else{
			if(isset($_REQUEST['escalation']) && (0 == $_REQUEST['esc_period']))
				$_REQUEST['esc_period'] = 3600;

			$name		= get_request('name');
			$eventsource	= get_request('eventsource');
			$esc_period	= get_request('esc_period',0);
			$status 	= get_request('status');

			$def_shortdata	= get_request('def_shortdata', ACTION_DEFAULT_MSG);
			$def_longdata	= get_request('def_longdata', ACTION_DEFAULT_MSG);

			$recovery_msg	= get_request('recovery_msg',0);
			$r_shortdata	= get_request('r_shortdata', ACTION_DEFAULT_MSG);
			$r_longdata	= get_request('r_longdata', ACTION_DEFAULT_MSG);

			if(!$esc_period) unset($_REQUEST['escalation']);
		}

		$tblAct->addRow(array(S_NAME, new CTextBox('name', $name, 50)));

		/* form row generation */

		$cmbSource =  new CComboBox('eventsource', $eventsource, 'submit()');
		$cmbSource->addItem(EVENT_SOURCE_TRIGGERS, S_TRIGGERS);
		$cmbSource->addItem(EVENT_SOURCE_DISCOVERY, S_DISCOVERY);
		$cmbSource->addItem(EVENT_SOURCE_AUTO_REGISTRATION, S_AUTO_REGISTRATION);
		$tblAct->addRow(array(S_EVENT_SOURCE, $cmbSource));


		if(EVENT_SOURCE_TRIGGERS == $eventsource){
			$tblAct->addRow(array(S_ENABLE_ESCALATIONS, new CCheckBox('escalation',isset($_REQUEST['escalation']),'javascript: submit();',1)));

			if(isset($_REQUEST['escalation'])){
				$tblAct->addRow(array(S_PERIOD.' ('.S_SECONDS_SMALL.')', array(new CNumericBox('esc_period', $esc_period, 6, 'no'), '['.S_MIN_SMALL.' 60]')));
			}
			else{
				$tblAct->addItem(new CVar('esc_period',$esc_period));
			}
		}
		else{
			$tblAct->addItem(new CVar('esc_period',$esc_period));
		}

		if(!isset($_REQUEST['escalation'])){
			unset($_REQUEST['new_opcondition']);
		}

		$tblAct->addRow(array(S_DEFAULT_SUBJECT, new CTextBox('def_shortdata', $def_shortdata, 50)));
		$tblAct->addRow(array(S_DEFAULT_MESSAGE, new CTextArea('def_longdata', $def_longdata,50,5)));

		if(EVENT_SOURCE_TRIGGERS == $eventsource){
			$tblAct->addRow(array(S_RECOVERY_MESSAGE, new CCheckBox('recovery_msg',$recovery_msg,'javascript: submit();',1)));
			if($recovery_msg){
				$tblAct->addRow(array(S_RECOVERY_SUBJECT, new CTextBox('r_shortdata', $r_shortdata, 50)));
				$tblAct->addRow(array(S_RECOVERY_MESSAGE, new CTextArea('r_longdata', $r_longdata,50,5)));
			}
			else{
				$tblAct->addItem(new CVar('r_shortdata', $r_shortdata));
				$tblAct->addItem(new CVar('r_longdata', $r_longdata));
			}
		}
		else{
			unset($_REQUEST['recovery_msg']);
		}

		$cmbStatus = new CComboBox('status',$status);
		$cmbStatus->addItem(ACTION_STATUS_ENABLED,S_ENABLED);
		$cmbStatus->addItem(ACTION_STATUS_DISABLED,S_DISABLED);
		$tblAct->addRow(array(S_STATUS, $cmbStatus));

		$tblAction = new CTableInfo();
		$tblAction->addRow($tblAct);

		$td = new CCol(array(new CButton('save',S_SAVE)));
		$td->setAttribute('colspan','2');
		$td->setAttribute('style','text-align: right;');

		if(isset($_REQUEST["actionid"])){
			$td->addItem(SPACE);
			$td->addItem(new CButton('clone',S_CLONE));
			$td->addItem(SPACE);
			$td->addItem(new CButtonDelete(S_DELETE_SELECTED_ACTION_Q,
						url_param('form').url_param('eventsource').
						url_param('actionid')));

		}
		$td->addItem(SPACE);
		$td->addItem(new CButtonCancel(url_param("actiontype")));

		$tblAction->setFooter($td);
	return $tblAction;
	}

	function get_act_condition_form($action=null){
		$tblCond = new CTable('','nowrap');

		if(isset($_REQUEST['actionid']) && empty($action)){
			$action = get_action_by_actionid($_REQUEST['actionid']);
		}

		$conditions	= get_request('conditions',array());

		if(isset($_REQUEST['actionid']) && !isset($_REQUEST['form_refresh'])){
			$eventsource	= $action['eventsource'];
			$evaltype	= $action['evaltype'];

			/* prepare conditions */
			$sql = 'SELECT conditiontype, operator, value '.
					' FROM conditions '.
					' WHERE actionid='.$_REQUEST['actionid'].
					' ORDER BY conditiontype,conditionid';
			$db_conditions = DBselect($sql);
			while($condition_data = DBfetch($db_conditions)){
				$condition_data = array(
					'type' =>		$condition_data['conditiontype'],
					'operator' =>		$condition_data['operator'],
					'value' =>		$condition_data['value']);

				if(str_in_array($condition_data, $conditions)) continue;
				array_push($conditions, $condition_data);
			}
			unset($condition_data, $db_conditions);
		}
		else{
			$evaltype	= get_request('evaltype');
			$eventsource	= get_request('eventsource');
		}

		$allowed_conditions = get_conditions_by_eventsource($eventsource);

// show CONDITION LIST
		zbx_rksort($conditions);

		/* group conditions by type */
		$grouped_conditions = array();
		$cond_el = new CTable(S_NO_CONDITIONS_DEFINED);
		$i=0;

		foreach($conditions as $id => $val){
			if( !isset($val['type']) )	$val['type'] = 0;
			if( !isset($val['operator']) )	$val['operator'] = 0;
			if( !isset($val['value']) )	$val['value'] = 0;

			if( !str_in_array($val["type"], $allowed_conditions) ) continue;

			$label = chr(ord('A') + $i);
			$cond_el->addRow(array('('.$label.')',array(
					new CCheckBox('g_conditionid[]', 'no', null,$i),
					get_condition_desc($val['type'], $val['operator'], $val['value']))
				));

			$tblCond->addItem(new CVar("conditions[$i][type]", 	$val["type"]));
			$tblCond->addItem(new CVar("conditions[$i][operator]", 	$val["operator"]));
			$tblCond->addItem(new CVar("conditions[$i][value]", 	$val["value"]));

			$grouped_conditions[$val["type"]][] = $label;

			$i++;
		}
		unset($conditions);

		$cond_buttons = array();

		if(!isset($_REQUEST['new_condition'])){
			$cond_buttons[] = new CButton('new_condition',S_NEW);
		}

		if($cond_el->ItemsCount() > 0){
			if($cond_el->ItemsCount() > 1){

				/* prepare condition calcuation type selector */
				switch($evaltype){
					case ACTION_EVAL_TYPE_AND:	$group_op = 		$glog_op = S_AND;	break;
					case ACTION_EVAL_TYPE_OR:	$group_op = 		$glog_op = S_OR;	break;
					default:			$group_op = S_OR;	$glog_op = S_AND;	break;
				}

				foreach($grouped_conditions as $id => $val)
					$grouped_conditions[$id] = '('.implode(' '.$group_op.' ', $val).')';

				$grouped_conditions = implode(' '.$glog_op.' ', $grouped_conditions);

				$cmb_calc_type = new CComboBox('evaltype', $evaltype, 'submit()');
				$cmb_calc_type->addItem(ACTION_EVAL_TYPE_AND_OR, S_AND_OR_BIG);
				$cmb_calc_type->addItem(ACTION_EVAL_TYPE_AND, S_AND_BIG);
				$cmb_calc_type->addItem(ACTION_EVAL_TYPE_OR, S_OR_BIG);
				$tblCond->addRow(array(S_TYPE_OF_CALCULATION, array($cmb_calc_type, new CTextBox('preview', $grouped_conditions, 60,'yes'))));
				unset($cmb_calc_type, $group_op, $glog_op);
				/* end of calculation type selector */
			}
			else{
				$tblCond->addItem(new CVar('evaltype', ACTION_EVAL_TYPE_AND_OR));
			}
			$cond_buttons[] = new CButton('del_condition',S_DELETE_SELECTED);
		}
		else{
			$tblCond->addItem(new CVar('evaltype', ACTION_EVAL_TYPE_AND_OR));
		}

		$tblCond->addRow(array(S_CONDITIONS, $cond_el));

		$tblConditions = new CTableInfo();
		$tblConditions->addRow($tblCond);

		$td = new CCol($cond_buttons);
		$td->setAttribute('colspan','2');
		$td->setAttribute('style','text-align: right;');

		$tblConditions->setFooter($td);
		unset($grouped_conditions,$cond_el,$cond_buttons);
// end of CONDITION LIST
	return $tblConditions;
	}

	function get_act_new_cond_form($action=null){
		$tblCond = new CTable('','nowrap');

		if(isset($_REQUEST['actionid']) && empty($action)){
			$action = get_action_by_actionid($_REQUEST['actionid']);
		}

		if(isset($_REQUEST['actionid']) && !isset($_REQUEST['form_refresh'])){
			$eventsource	= $action['eventsource'];
			$evaltype	= $action['evaltype'];
		}
		else{
			$evaltype	= get_request('evaltype');
			$eventsource	= get_request('eventsource');
		}

		$allowed_conditions = get_conditions_by_eventsource($eventsource);
		/* init new_condition variable */
		$new_condition = get_request('new_condition', array());
		if( !is_array($new_condition) )	$new_condition = array();

		if( !isset($new_condition['type']) )	$new_condition['type']		= CONDITION_TYPE_TRIGGER_NAME;
		if( !isset($new_condition['operator']))	$new_condition['operator']	= CONDITION_OPERATOR_LIKE;
		if( !isset($new_condition['value']) )	$new_condition['value']		= '';

		if( !str_in_array($new_condition['type'], $allowed_conditions) )
			$new_condition['type'] = $allowed_conditions[0];

// NEW CONDITION

		$rowCondition=array();

// add condition type
		$cmbCondType = new CComboBox('new_condition[type]',$new_condition['type'],'submit()');
		foreach($allowed_conditions as $cond)
			$cmbCondType->addItem($cond, condition_type2str($cond));

		array_push($rowCondition,$cmbCondType);

// add condition operation
		$cmbCondOp = new CComboBox('new_condition[operator]');
		foreach(get_operators_by_conditiontype($new_condition['type']) as $op)
			$cmbCondOp->addItem($op, condition_operator2str($op));

		array_push($rowCondition,$cmbCondOp);


// add condition value
		switch($new_condition['type']){
			case CONDITION_TYPE_HOST_GROUP:
				$tblCond->addItem(new CVar('new_condition[value]','0'));
				$rowCondition[] = array(
					new CTextBox('group','',20,'yes'),
					new CButton('btn1',S_SELECT,
						"return PopUp('popup.php?writeonly=1&dstfrm=".S_ACTION.
						"&dstfld1=new_condition%5Bvalue%5D&dstfld2=group&srctbl=host_group".
						"&srcfld1=groupid&srcfld2=name',450,450);",
						'T')
					);
				break;
			case CONDITION_TYPE_HOST_TEMPLATE:
				$tblCond->addItem(new CVar('new_condition[value]','0'));
				$rowCondition[] = array(
					new CTextBox('host','',20,'yes'),
					new CButton('btn1',S_SELECT,
						"return PopUp('popup.php?writeonly=1&dstfrm=".S_ACTION.
						"&dstfld1=new_condition%5Bvalue%5D&dstfld2=host&srctbl=host_templates".
						"&srcfld1=hostid&srcfld2=host',450,450);",
						'T')
					);
				break;
			case CONDITION_TYPE_HOST:
				$tblCond->addItem(new CVar('new_condition[value]','0'));
				$rowCondition[] = array(
					new CTextBox('host','',20,'yes'),
					new CButton('btn1',S_SELECT,
						"return PopUp('popup.php?writeonly=1&dstfrm=".S_ACTION.
						"&dstfld1=new_condition%5Bvalue%5D&dstfld2=host&srctbl=hosts".
						"&srcfld1=hostid&srcfld2=host',450,450);",
						'T')
					);
				break;
			case CONDITION_TYPE_TRIGGER:
				$tblCond->addItem(new CVar('new_condition[value]','0'));

				$rowCondition[] = array(
					new CTextBox('trigger','',20,'yes'),
					new CButton('btn1',S_SELECT,
						"return PopUp('popup.php?writeonly=1&dstfrm=".S_ACTION.
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
					$cmbCondVal->addItem($tr_val, trigger_value2str($tr_val));

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
					$cmbCondVal->addItem($id,get_severity_description($id));

				$rowCondition[] = $cmbCondVal;
				break;
			case CONDITION_TYPE_MAINTENANCE:
				$rowCondition[] = new CCol(S_MAINTENANCE_SMALL);
				break;
			case CONDITION_TYPE_NODE:
				$tblCond->addItem(new CVar('new_condition[value]','0'));
				$rowCondition[] = array(
					new CTextBox('node','',20,'yes'),
					new CButton('btn1',S_SELECT,
						"return PopUp('popup.php?writeonly=1&dstfrm=".S_ACTION.
						"&dstfld1=new_condition%5Bvalue%5D&dstfld2=node&srctbl=nodes".
						"&srcfld1=nodeid&srcfld2=name',450,450);",
						'T')
					);
				break;
			case CONDITION_TYPE_DRULE:
				$tblCond->addItem(new CVar('new_condition[value]','0'));
				$rowCondition[] = array(
					new CTextBox('drule','',20,'yes'),
					new CButton('btn1',S_SELECT,
						"return PopUp('popup.php?dstfrm=".S_ACTION.
						"&dstfld1=new_condition%5Bvalue%5D&dstfld2=drule&srctbl=drules".
						"&srcfld1=druleid&srcfld2=name',450,450);",
						'T')
					);
				break;
			case CONDITION_TYPE_DCHECK:
				$tblCond->addItem(new CVar('new_condition[value]','0'));
				$rowCondition[] = array(
					new CTextBox('dcheck','',50,'yes'),
					new CButton('btn1',S_SELECT,
						"return PopUp('popup.php?writeonly=1&dstfrm=".S_ACTION.
						"&dstfld1=new_condition%5Bvalue%5D&dstfld2=dcheck&srctbl=dchecks".
						"&srcfld1=dcheckid&srcfld2=name',450,450);",
						'T')
					);
				break;
			case CONDITION_TYPE_PROXY:
				$tblCond->addItem(new CVar('new_condition[value]','0'));
				$rowCondition[] = array(
					new CTextBox('proxy','',20,'yes'),
					new CButton('btn1',S_SELECT,
						"return PopUp('popup.php?writeonly=1&dstfrm=".S_ACTION.
						"&dstfld1=new_condition%5Bvalue%5D&dstfld2=proxy&srctbl=proxies".
						"&srcfld1=hostid&srcfld2=host',450,450);",
						'T')
					);
				break;
			case CONDITION_TYPE_DHOST_IP:
				$rowCondition[] = new CTextBox('new_condition[value]', '192.168.0.1-127,192.168.2.1', 50);
				break;
			case CONDITION_TYPE_DSERVICE_TYPE:
				$cmbCondVal = new CComboBox('new_condition[value]');
				foreach(array(SVC_SSH, SVC_LDAP, SVC_SMTP, SVC_FTP, SVC_HTTP,
					SVC_POP, SVC_NNTP, SVC_IMAP, SVC_TCP,SVC_AGENT,SVC_SNMPv1,SVC_SNMPv2,SVC_SNMPv3,
					SVC_ICMPPING) as $svc)
					$cmbCondVal->addItem($svc,discovery_check_type2str($svc));

				$rowCondition[] = $cmbCondVal;
				break;
			case CONDITION_TYPE_DSERVICE_PORT:
				$rowCondition[] = new CTextBox('new_condition[value]', '0-1023,1024-49151', 40);
				break;
			case CONDITION_TYPE_DSTATUS:
				$cmbCondVal = new CComboBox('new_condition[value]');
				foreach(array(DOBJECT_STATUS_UP, DOBJECT_STATUS_DOWN, DOBJECT_STATUS_DISCOVER,
						DOBJECT_STATUS_LOST) as $stat)
					$cmbCondVal->addItem($stat,discovery_object_status2str($stat));

				$rowCondition[] = $cmbCondVal;
				break;
			case CONDITION_TYPE_DOBJECT:
				$cmbCondVal = new CComboBox('new_condition[value]');
				foreach(array(EVENT_OBJECT_DHOST, EVENT_OBJECT_DSERVICE) as $object)
					$cmbCondVal->addItem($object, discovery_object2str($object));

				$rowCondition[] = $cmbCondVal;
				break;
			case CONDITION_TYPE_DUPTIME:
				$rowCondition[] = new CNumericBox('new_condition[value]','600',15);
				break;
			case CONDITION_TYPE_DVALUE:
				$rowCondition[] = new CTextBox('new_condition[value]', "", 40);
				break;
			case CONDITION_TYPE_APPLICATION:
				$rowCondition[] = new CTextBox('new_condition[value]', "", 40);
				break;
			case CONDITION_TYPE_HOST_NAME:
				$rowCondition[] = new CTextBox('new_condition[value]', "", 40);
				break;
		}

		$tblCond->addRow($rowCondition);

		$tblConditions = new CTableInfo();

		$tblConditions->addRow($tblCond);

		$td = new CCol(array(new CButton('add_condition',S_ADD),new CButton('cancel_new_condition',S_CANCEL)));
		$td->setAttribute('colspan','3');
		$td->setAttribute('style','text-align: right;');

		$tblConditions->setFooter($td);
		unset($grouped_conditions,$cond_el,$cond_buttons);
// end of NEW CONDITION
	return $tblConditions;
	}

	function get_act_operations_form($action=null){
		$tblOper = new CTableInfo(S_NO_OPERATIONS_DEFINED);
		$tblOper->setAttribute('style','background-color: #AAA;');

		if(isset($_REQUEST['actionid']) && empty($action)){
			$action = get_action_by_actionid($_REQUEST['actionid']);
		}

		$operations	= get_request('operations',array());

		if(isset($_REQUEST['actionid']) && !isset($_REQUEST['form_refresh'])){
			$eventsource = $action['eventsource'];
			$evaltype	= $action['evaltype'];
			$esc_period	= $action['esc_period'];


			/* prepate operations */
			$sql = 'SELECT * '.
					' FROM operations'.
					' WHERE actionid='.$_REQUEST['actionid'].
					' ORDER BY esc_step_from,operationtype,object,operationid';
			$db_operations = DBselect($sql);

			while($operation_data = DBfetch($db_operations)){
				$operation_data = array(
					'operationtype'	=>	$operation_data['operationtype'],
					'operationid'	=>	$operation_data['operationid'],
					'object'	=>	$operation_data['object'],
					'objectid'	=>	$operation_data['objectid'],
					'shortdata'	=>	$operation_data['shortdata'],
					'longdata'	=>	$operation_data['longdata'],
					'esc_period'	=>	$operation_data['esc_period'],
					'esc_step_from'	=>	$operation_data['esc_step_from'],
					'esc_step_to'	=>	$operation_data['esc_step_to'],
					'default_msg'	=>	$operation_data['default_msg'],
					'evaltype'	=>	$operation_data['evaltype']);

				$operation_data['opconditions'] = array();

				$sql = 'SELECT * FROM opconditions WHERE operationid='.$operation_data['operationid'];
				$db_opconds = DBselect($sql);
				while($db_opcond = DBfetch($db_opconds)){

					$operation_data['opconditions'][] = $db_opcond;
				}

				$sql = 'SELECT * from opmediatypes WHERE operationid='.$operation_data['operationid'];
				$db_opmtypes = DBSelect($sql);
				if($db_opmtype = DBfetch($db_opmtypes)){
					$operation_data['mediatypeid'] = $db_opmtype['mediatypeid'];
				}

				if(str_in_array($operation_data, $operations)) continue;
				array_push($operations, $operation_data);
			}
			unset($db_operations, $operation_data);
		}
		else{
			$eventsource	= get_request('eventsource');
			$evaltype	= get_request('evaltype');
			$esc_period	= get_request('esc_period');
		}

		$esc_step_from = array();
		$objects_tmp = array();
		$objectids_tmp = array();
		foreach($operations as $key => $operation) {
			$esc_step_from[$key] = $operation['esc_step_from'];
			$objects_tmp[$key] = $operation['object'];
			$objectids_tmp[$key] = $operation['objectid'];
		}

		array_multisort($esc_step_from, SORT_ASC, SORT_NUMERIC, $objects_tmp, SORT_DESC, $objectids_tmp, SORT_ASC, $operations);

		$tblOper->setHeader(array(
				new CCheckBox('all_operations',null,'checkAll("'.S_ACTION.'","all_operations","g_operationid");'),
				isset($_REQUEST['escalation'])?S_STEPS:null,
				S_DETAILS,
				isset($_REQUEST['escalation'])?S_PERIOD.' ('.S_SEC_SMALL.')':null,
				isset($_REQUEST['escalation'])?S_DELAY:null,
				S_ACTION
			));

		$allowed_operations = get_operations_by_eventsource($eventsource);

		$delay = count_operations_delay($operations,$esc_period);
		foreach($operations as $id => $val){
			if( !str_in_array($val['operationtype'], $allowed_operations) )	continue;

			if(!isset($val['default_msg'])) $val['default_msg'] = 0;
			if(!isset($val['opconditions'])) $val['opconditions'] = array();
			if(!isset($val['mediatypeid'])) $val['mediatypeid'] = 0;

			$oper_details = new CSpan(get_operation_desc(SHORT_DESCRITION, $val));
			$oper_details->setHint(nl2br(get_operation_desc(LONG_DESCRITION, $val)));

			$esc_steps_txt = null;
			$esc_period_txt = null;
			$esc_delay_txt = null;

			if($val['esc_step_from'] < 1) $val['esc_step_from'] = 1;

			if(isset($_REQUEST['escalation'])){
				$esc_steps_txt = $val['esc_step_from'].' - '.$val['esc_step_to'];
				/* Display N-N as N */
				$esc_steps_txt = ($val['esc_step_from']==$val['esc_step_to'])?
					$val['esc_step_from']:$val['esc_step_from'].' - '.$val['esc_step_to'];

				$esc_period_txt = $val['esc_period']?$val['esc_period']:S_DEFAULT;
				$esc_delay_txt = $delay[$val['esc_step_from']]?convert_units($delay[$val['esc_step_from']],'uptime'):S_IMMEDIATELY;
			}

			$tblOper->addRow(array(
				new CCheckBox("g_operationid[]", 'no', null,$id),
				$esc_steps_txt,
				$oper_details,
				$esc_period_txt,
				$esc_delay_txt,
				new CButton('edit_operationid['.$id.']',S_EDIT)
				));

			$tblOper->addItem(new CVar('operations['.$id.'][operationtype]'	,$val['operationtype']));
			$tblOper->addItem(new CVar('operations['.$id.'][object]'	,$val['object']	));
			$tblOper->addItem(new CVar('operations['.$id.'][objectid]'	,$val['objectid']));
			$tblOper->addItem(new CVar('operations['.$id.'][mediatypeid]'	,$val['mediatypeid']));
			$tblOper->addItem(new CVar('operations['.$id.'][shortdata]'	,$val['shortdata']));
			$tblOper->addItem(new CVar('operations['.$id.'][longdata]'	,$val['longdata']));
			$tblOper->addItem(new CVar('operations['.$id.'][esc_period]'	,$val['esc_period']	));
			$tblOper->addItem(new CVar('operations['.$id.'][esc_step_from]'	,$val['esc_step_from']));
			$tblOper->addItem(new CVar('operations['.$id.'][esc_step_to]'	,$val['esc_step_to']));
			$tblOper->addItem(new CVar('operations['.$id.'][default_msg]'	,$val['default_msg']));
			$tblOper->addItem(new CVar('operations['.$id.'][evaltype]'	,$val['evaltype']));

			foreach($val['opconditions'] as $opcondid => $opcond){
				foreach($opcond as $field => $value)
					$tblOper->addItem(new CVar('operations['.$id.'][opconditions]['.$opcondid.']['.$field.']',$value));
			}
		}
		unset($operations);

		$tblOperFooter = new CTableInfo(null);

		$oper_buttons = array();
		if(!isset($_REQUEST['new_operation'])){
			$oper_buttons[] = new CButton('new_operation',S_NEW);
		}

		if($tblOper->ItemsCount() > 0 ){
			$oper_buttons[] = new CButton('del_operation',S_DELETE_SELECTED);
		}

		$td = new CCol($oper_buttons);
		$td->setAttribute('colspan',isset($_REQUEST['escalation'])?6:3);
		$td->setAttribute('style','text-align: right;');


		$tblOperFooter->setFooter($td);
// end of condition list preparation
	return array($tblOper,$tblOperFooter);
	}

	function get_act_new_oper_form($action=null){
		$tblOper = new CTableInfo();

		if(isset($_REQUEST['actionid']) && empty($action)){
			$action = get_action_by_actionid($_REQUEST['actionid']);
		}

		$operations	= get_request('operations', array());

		if(isset($_REQUEST['actionid']) && !isset($_REQUEST['form_refresh'])){
			$eventsource = $action['eventsource'];
		}
		else{
			$eventsource = get_request('eventsource');
		}

		$allowed_operations = get_operations_by_eventsource($eventsource);

		/* init new_operation variable */
		$new_operation = get_request('new_operation', array());

		if(!is_array($new_operation)){
			$new_operation = array();
			$new_operation['default_msg'] = 1;
		}

		if(!isset($new_operation['operationtype']))	$new_operation['operationtype']	= OPERATION_TYPE_MESSAGE;
		if(!isset($new_operation['object']))		$new_operation['object']	= OPERATION_OBJECT_GROUP;
		if(!isset($new_operation['objectid']))		$new_operation['objectid']	= 0;
		if(!isset($new_operation['mediatypeid']))	$new_operation['mediatypeid']	= 0;
		if(!isset($new_operation['shortdata']))		$new_operation['shortdata']	= '{TRIGGER.NAME}: {STATUS}';
		if(!isset($new_operation['longdata']))		$new_operation['longdata']	= '{TRIGGER.NAME}: {STATUS}';
		if(!isset($new_operation['esc_step_from']))	$new_operation['esc_step_from'] = 1;
		if(!isset($new_operation['esc_step_to']))	$new_operation['esc_step_to'] = 1;
		if(!isset($new_operation['esc_period']))	$new_operation['esc_period'] = 0;

		if(!isset($new_operation['evaltype']))		$new_operation['evaltype']	= 0;
		if(!isset($new_operation['opconditions']))	$new_operation['opconditions'] = array();
		if(!isset($new_operation['default_msg']))	$new_operation['default_msg'] = 0;


		unset($update_mode);

		$evaltype	= $new_operation['evaltype'];

		if(isset($new_operation['id'])){
			$tblOper->addItem(new CVar('new_operation[id]', $new_operation['id']));
			$update_mode = true;
		}

		$tblNewOperation = new CTable(null,'nowrap');

		if(isset($_REQUEST['escalation'])){
			$tblStep = new CTable(null,'nowrap');

			$step_from = new CNumericBox('new_operation[esc_step_from]', $new_operation['esc_step_from'],4);
			$step_from->addAction('onchange','javascript:'.$step_from->getAttribute('onchange').' if(this.value == 0) this.value=1;');

			$tblStep->addRow(array(S_FROM, $step_from));
			$tblStep->addRow(array(
								S_TO,
								new CCol(array(
									new CNumericBox('new_operation[esc_step_to]', $new_operation['esc_step_to'],4),
									' [0-'.S_INFINITY.']'))
							));

			$tblStep->addRow(array(
								S_PERIOD,
								new CCol(array(
									new CNumericBox('new_operation[esc_period]', $new_operation['esc_period'],5),
									' [0-'.S_DEFAULT.']'))
							));

			$tblNewOperation->addRow(array(S_STEP,$tblStep));
		}
		else{
			$tblOper->addItem(new CVar('new_operation[esc_period]'	,	$new_operation['esc_period']));
			$tblOper->addItem(new CVar('new_operation[esc_step_from]',	$new_operation['esc_step_from']));
			$tblOper->addItem(new CVar('new_operation[esc_step_to]',	$new_operation['esc_step_to']));

			$tblOper->addItem(new CVar('new_operation[evaltype]',		$new_operation['evaltype']));
		}

		$cmbOpType = new CComboBox('new_operation[operationtype]', $new_operation['operationtype'],'submit()');
		foreach($allowed_operations as $oper)
			$cmbOpType->addItem($oper, operation_type2str($oper));
			
		$tblNewOperation->addRow(array(S_OPERATION_TYPE, $cmbOpType));

		switch($new_operation['operationtype']){
			case OPERATION_TYPE_MESSAGE:
				if( $new_operation['object'] == OPERATION_OBJECT_GROUP){
					$object_srctbl = 'usrgrp';
					$object_srcfld1 = 'usrgrpid';

					$object_name = CUserGroup::get(array('usrgrpids' => $new_operation['objectid'],  'extendoutput' => 1));
					$object_name = reset($object_name);

					$display_name = 'name';
				}
				else{
					$object_srctbl = 'users';
					$object_srcfld1 = 'userid';

					$object_name = CUser::get(array('userids' => $new_operation['objectid'],  'extendoutput' => 1));
					$object_name = reset($object_name);

					$display_name = 'alias';
				}

				$tblOper->addItem(new CVar('new_operation[objectid]', $new_operation['objectid']));

				if($object_name) $object_name = $object_name[$display_name];

				$cmbObject = new CComboBox('new_operation[object]', $new_operation['object'],'submit()');
				$cmbObject->addItem(OPERATION_OBJECT_USER,S_SINGLE_USER);
				$cmbObject->addItem(OPERATION_OBJECT_GROUP,S_USER_GROUP);

				$tblNewOperation->addRow(array(S_SEND_MESSAGE_TO, array(
						$cmbObject,
						new CTextBox('object_name', $object_name, 40, 'yes'),
						new CButton('select_object',S_SELECT,
							'return PopUp("popup.php?dstfrm='.S_ACTION.
							'&dstfld1=new_operation%5Bobjectid%5D'.
							'&dstfld2=object_name'.
							'&srctbl='.$object_srctbl.
							'&srcfld1='.$object_srcfld1.
							'&srcfld2='.$display_name.
							'&submit=1'.
							'",450,450)',
							'T')
					)));

				$cmbMediaType = new CComboBox('new_operation[mediatypeid]', $new_operation['mediatypeid'], 'submit()');
				$cmbMediaType->addItem(0, S_MINUS_ALL_MINUS);

				if (OPERATION_OBJECT_USER == $new_operation['object']) {
					$sql = 'SELECT DISTINCT mt.mediatypeid,mt.description,m.userid '.
							' FROM media_type mt, media m '.
							' WHERE '.DBin_node('mt.mediatypeid').
								' AND m.mediatypeid=mt.mediatypeid '.
								' AND m.userid='.$new_operation['objectid'].
								' AND m.active='.ACTION_STATUS_ENABLED.
							' ORDER BY mt.description';
					$db_mediatypes = DBselect($sql);
					while($db_mediatype = DBfetch($db_mediatypes)){
						$cmbMediaType->addItem($db_mediatype['mediatypeid'], $db_mediatype['description']);
					}
				}
				else{
					$sql = 'SELECT mt.mediatypeid, mt.description'.
							' FROM media_type mt '.
							' WHERE '.DBin_node('mt.mediatypeid').
							' ORDER BY mt.description';
					$db_mediatypes = DBselect($sql);
					while($db_mediatype = DBfetch($db_mediatypes)){
						$cmbMediaType->addItem($db_mediatype['mediatypeid'], $db_mediatype['description']);
					}
				}

				$tblNewOperation->addRow(array(S_SEND_ONLY_TO, $cmbMediaType));

				if(OPERATION_OBJECT_USER == $new_operation['object']){
					$media_table = new CTableInfo(S_NO_MEDIA_DEFINED);

					$sql = 'SELECT mt.description,m.sendto,m.period,m.severity '.
							' FROM media_type mt,media m '.
							' WHERE '.DBin_node('mt.mediatypeid').
								' AND mt.mediatypeid=m.mediatypeid '.
								' AND m.userid='.$new_operation['objectid'].
								($new_operation['mediatypeid'] ? ' AND m.mediatypeid='.$new_operation['mediatypeid'] : '').
								' AND m.active='.ACTION_STATUS_ENABLED.
							' ORDER BY mt.description,m.sendto';
					$db_medias = DBselect($sql);
					while($db_media = DBfetch($db_medias)){
						$media_table->addRow(array(
								new CSpan($db_media['description'], 'nowrap'),
								new CSpan($db_media['sendto'], 'nowrap'),
								new CSpan($db_media['period'], 'nowrap'),
								media_severity2str($db_media['severity'])
								));
					}

					$tblNewOperation->addRow(array(S_USER_MEDIAS, $media_table));
				}

				$tblNewOperation->addRow(array(S_DEFAULT_MESSAGE, new CCheckBox('new_operation[default_msg]', $new_operation['default_msg'], 'javascript: submit();',1)));

				if(!$new_operation['default_msg']){
					$tblNewOperation->addRow(array(S_SUBJECT, new CTextBox('new_operation[shortdata]', $new_operation['shortdata'], 77)));
					$tblNewOperation->addRow(array(S_MESSAGE, new CTextArea('new_operation[longdata]', $new_operation['longdata'],77,7)));
				}
				else{
					$tblOper->addItem(new CVar('new_operation[shortdata]',$new_operation['shortdata']));
					$tblOper->addItem(new CVar('new_operation[longdata]',$new_operation['longdata']));
				}
				break;
			case OPERATION_TYPE_COMMAND:
				$tblOper->addItem(new CVar('new_operation[object]',0));
				$tblOper->addItem(new CVar('new_operation[objectid]',0));
				$tblOper->addItem(new CVar('new_operation[shortdata]',''));

				$tblNewOperation->addRow(array(S_REMOTE_COMMAND,
					new CTextArea('new_operation[longdata]', $new_operation['longdata'],77,7)));
				break;
			case OPERATION_TYPE_HOST_ADD:
				$tblOper->addItem(new CVar('new_operation[object]',0));
				$tblOper->addItem(new CVar('new_operation[objectid]',0));
				$tblOper->addItem(new CVar('new_operation[shortdata]',''));
				$tblOper->addItem(new CVar('new_operation[longdata]',''));
				break;
			case OPERATION_TYPE_HOST_REMOVE:
				$tblOper->addItem(new CVar('new_operation[object]',0));
				$tblOper->addItem(new CVar('new_operation[objectid]',0));
				$tblOper->addItem(new CVar('new_operation[shortdata]',''));
				$tblOper->addItem(new CVar('new_operation[longdata]',''));
				break;
			case OPERATION_TYPE_HOST_ENABLE:
				$tblOper->addItem(new CVar('new_operation[object]',0));
				$tblOper->addItem(new CVar('new_operation[objectid]',0));
				$tblOper->addItem(new CVar('new_operation[shortdata]',''));
				$tblOper->addItem(new CVar('new_operation[longdata]',''));
				break;
			case OPERATION_TYPE_HOST_DISABLE:
				$tblOper->addItem(new CVar('new_operation[object]',0));
				$tblOper->addItem(new CVar('new_operation[objectid]',0));
				$tblOper->addItem(new CVar('new_operation[shortdata]',''));
				$tblOper->addItem(new CVar('new_operation[longdata]',''));
				break;
			case OPERATION_TYPE_GROUP_ADD:
				$tblOper->addItem(new CVar('new_operation[object]',0));
				$tblOper->addItem(new CVar('new_operation[objectid]',$new_operation['objectid']));
				$tblOper->addItem(new CVar('new_operation[shortdata]',''));
				$tblOper->addItem(new CVar('new_operation[longdata]',''));

				if($object_name= DBfetch(DBselect('select name FROM groups WHERE groupid='.$new_operation['objectid']))){
					$object_name = $object_name['name'];
				}
				$tblNewOperation->addRow(array(S_GROUP, array(
						new CTextBox('object_name', $object_name, 40, 'yes'),
						new CButton('select_object',S_SELECT,
							'return PopUp("popup.php?dstfrm='.S_ACTION.
							'&dstfld1=new_operation%5Bobjectid%5D&dstfld2=object_name'.
							'&srctbl=host_group&srcfld1=groupid&srcfld2=name'.
							'",450,450)',
							'T')
					)));
				break;
			case OPERATION_TYPE_GROUP_REMOVE:
				$tblOper->addItem(new CVar('new_operation[object]',0));
				$tblOper->addItem(new CVar('new_operation[objectid]',$new_operation['objectid']));
				$tblOper->addItem(new CVar('new_operation[shortdata]',''));
				$tblOper->addItem(new CVar('new_operation[longdata]',''));

				if($object_name= DBfetch(DBselect('select name FROM groups WHERE groupid='.$new_operation['objectid']))){
					$object_name = $object_name['name'];
				}
				$tblNewOperation->addRow(array(S_GROUP, array(
						new CTextBox('object_name', $object_name, 40, 'yes'),
						new CButton('select_object',S_SELECT,
							'return PopUp("popup.php?dstfrm='.S_ACTION.
							'&dstfld1=new_operation%5Bobjectid%5D&dstfld2=object_name'.
							'&srctbl=host_group&srcfld1=groupid&srcfld2=name'.
							'",450,450)',
							'T')
					)));
				break;
			case OPERATION_TYPE_TEMPLATE_ADD:
				$tblOper->addItem(new CVar('new_operation[object]',0));
				$tblOper->addItem(new CVar('new_operation[objectid]',$new_operation['objectid']));
				$tblOper->addItem(new CVar('new_operation[shortdata]',''));
				$tblOper->addItem(new CVar('new_operation[longdata]',''));

				if($object_name= DBfetch(DBselect('SELECT host FROM hosts '.
					' WHERE status='.HOST_STATUS_TEMPLATE.' AND hostid='.$new_operation['objectid'])))
				{
					$object_name = $object_name['host'];
				}
				$tblNewOperation->addRow(array(S_TEMPLATE, array(
						new CTextBox('object_name', $object_name, 40, 'yes'),
						new CButton('select_object',S_SELECT,
							'return PopUp("popup.php?dstfrm='.S_ACTION.
							'&dstfld1=new_operation%5Bobjectid%5D&dstfld2=object_name'.
							'&srctbl=host_templates&srcfld1=hostid&srcfld2=host'.
							'",450,450)',
							'T')
					)));
				break;
			case OPERATION_TYPE_TEMPLATE_REMOVE:
				$tblOper->addItem(new CVar('new_operation[object]',0));
				$tblOper->addItem(new CVar('new_operation[objectid]',$new_operation['objectid']));
				$tblOper->addItem(new CVar('new_operation[shortdata]',''));
				$tblOper->addItem(new CVar('new_operation[longdata]',''));

				if($object_name= DBfetch(DBselect('SELECT host FROM hosts '.
					' WHERE status='.HOST_STATUS_TEMPLATE.' AND hostid='.$new_operation['objectid'])))
				{
					$object_name = $object_name['host'];
				}
				$tblNewOperation->addRow(array(S_TEMPLATE, array(
						new CTextBox('object_name', $object_name, 40, 'yes'),
						new CButton('select_object',S_SELECT,
							'return PopUp("popup.php?dstfrm='.S_ACTION.
							'&dstfld1=new_operation%5Bobjectid%5D&dstfld2=object_name'.
							'&srctbl=host_templates&srcfld1=hostid&srcfld2=host'.
							'",450,450)',
							'T')
					)));
				break;
		}

// new Operation conditions
		if(isset($_REQUEST['escalation'])){
			$tblCond = new CTable();

			$opconditions	= $new_operation['opconditions'];
			$allowed_opconditions = get_opconditions_by_eventsource($eventsource);

// show opcondition LIST
			zbx_rksort($opconditions);

			/* group opconditions by type */
			$grouped_opconditions = array();
			$cond_el = new CTable(S_NO_CONDITIONS_DEFINED);
			$i=0;

			foreach($opconditions as $val){
				if( !isset($val['conditiontype']) )	$val['conditiontype'] = 0;
				if( !isset($val['operator']) )	$val['operator'] = 0;
				if( !isset($val['value']) )	$val['value'] = 0;

				if( !str_in_array($val["conditiontype"], $allowed_opconditions) ) continue;

				$label = chr(ord('A') + $i);
				$cond_el->addRow(array('('.$label.')',array(
						new CCheckBox("g_opconditionid[]", 'no', null,$i),
						get_condition_desc($val["conditiontype"], $val["operator"], $val["value"]))
					));

				$tblCond->addItem(new CVar("new_operation[opconditions][$i][conditiontype]", 	$val["conditiontype"]));
				$tblCond->addItem(new CVar("new_operation[opconditions][$i][operator]", 	$val["operator"]));
				$tblCond->addItem(new CVar("new_operation[opconditions][$i][value]", 	$val["value"]));

				$grouped_opconditions[$val["conditiontype"]][] = $label;

				$i++;
			}
			unset($opconditions);

			$cond_buttons = array();

			if(!isset($_REQUEST['new_opcondition'])){
				$cond_buttons[] = new CButton('new_opcondition',S_NEW);
			}

			if($cond_el->ItemsCount() > 0){
				if($cond_el->ItemsCount() > 1){

					/* prepare opcondition calcuation type selector */
					switch($evaltype){
						case ACTION_EVAL_TYPE_AND:	$group_op = 		$glog_op = S_AND;	break;
						case ACTION_EVAL_TYPE_OR:	$group_op = 		$glog_op = S_OR;	break;
						default:			$group_op = S_OR;	$glog_op = S_AND;	break;
					}

					foreach($grouped_opconditions as $id => $val)
						$grouped_opconditions[$id] = '('.implode(' '.$group_op.' ', $val).')';

					$grouped_opconditions = implode(' '.$glog_op.' ', $grouped_opconditions);

					$cmb_calc_type = new CComboBox('new_operation[evaltype]', $evaltype, 'submit()');
					$cmb_calc_type->addItem(ACTION_EVAL_TYPE_AND_OR, S_AND_OR_BIG);
					$cmb_calc_type->addItem(ACTION_EVAL_TYPE_AND, S_AND_BIG);
					$cmb_calc_type->addItem(ACTION_EVAL_TYPE_OR, S_OR_BIG);

					$tblNewOperation->addRow(array(S_TYPE_OF_CALCULATION, new CCol(array($cmb_calc_type,new CTextBox('preview', $grouped_opconditions, 60,'yes')))));

					unset($cmb_calc_type, $group_op, $glog_op);
					/* end of calculation type selector */
				}
				else{
					$tblCond->addItem(new CVar('new_operation[evaltype]', ACTION_EVAL_TYPE_AND_OR));
				}
				$cond_buttons[] = new CButton('del_opcondition',S_DELETE_SELECTED);
			}
			else{
				$tblCond->addItem(new CVar('new_operation[evaltype]', ACTION_EVAL_TYPE_AND_OR));
			}

			$tblCond->addRow($cond_el);
			$tblCond->addRow(new CCol($cond_buttons));

// end of opcondition LIST
			$tblNewOperation->addRow(array(S_CONDITIONS, $tblCond));
			unset($grouped_opconditions,$cond_el,$cond_buttons,$tblCond);
		}

		$tblOper->addRow($tblNewOperation);


		$td = new CCol(array(
			new CButton('add_operation', isset($update_mode)?S_SAVE:S_ADD),
			SPACE,
			new CButton('cancel_new_operation',S_CANCEL)
			));

		$td->setAttribute('colspan','3');
		$td->setAttribute('style','text-align: right;');

		$tblOper->setFooter($td);

	return $tblOper;
	}

	function get_oper_new_cond_form($action=null){
		$tblCond = new CTable('','nowrap');

		if(isset($_REQUEST['actionid']) && empty($action)){
			$action = get_action_by_actionid($_REQUEST['actionid']);
		}

		if(isset($_REQUEST['actionid']) && !isset($_REQUEST['form_refresh'])){
			$eventsource	= $action['eventsource'];
			$evaltype	= $action['evaltype'];
		}
		else{
			$evaltype	= get_request('evaltype');
			$eventsource	= get_request('eventsource');
		}

		$allowed_conditions = get_opconditions_by_eventsource($eventsource);
		/* init new_condition variable */
		$new_opcondition = get_request('new_opcondition', array());
		if( !is_array($new_opcondition) )	$new_opcondition = array();

		if( !isset($new_opcondition['conditiontype']) )		$new_opcondition['conditiontype']	= CONDITION_TYPE_EVENT_ACKNOWLEDGED;
		if( !isset($new_opcondition['operator']))		$new_opcondition['operator']		= CONDITION_OPERATOR_LIKE;
		if( !isset($new_opcondition['value']) )			$new_opcondition['value']		= 0;

		if( !str_in_array($new_opcondition['conditiontype'], $allowed_conditions) )
			$new_opcondition['conditiontype'] = $allowed_conditions[0];

// NEW CONDITION
		$rowCondition=array();

// add condition type
		$cmbCondType = new CComboBox('new_opcondition[conditiontype]',$new_opcondition['conditiontype'],'submit()');
		foreach($allowed_conditions as $cond)
			$cmbCondType->addItem($cond, condition_type2str($cond));

		array_push($rowCondition,$cmbCondType);

// add condition operation
		$cmbCondOp = new CComboBox('new_opcondition[operator]');
		foreach(get_operators_by_conditiontype($new_opcondition['conditiontype']) as $op)
			$cmbCondOp->addItem($op, condition_operator2str($op));

		array_push($rowCondition,$cmbCondOp);

// add condition value
		switch($new_opcondition['conditiontype']){
			case CONDITION_TYPE_EVENT_ACKNOWLEDGED:
				$cmbCondVal = new CComboBox('new_opcondition[value]',$new_opcondition['value']);
				$cmbCondVal->addItem(0, S_NOT_ACK);
				$cmbCondVal->addItem(1, S_ACK);
				$rowCondition[] = $cmbCondVal;
				break;
		}

		$tblCond->addRow($rowCondition);

		$tblConditions = new CTableInfo();

		$tblConditions->addRow($tblCond);

		$td = new CCol(array(new CButton('add_opcondition',S_ADD),new CButton('cancel_new_opcondition',S_CANCEL)));
		$td->setAttribute('colspan','3');
		$td->setAttribute('style','text-align: right;');

		$tblConditions->setFooter($td);
		unset($grouped_conditions,$cond_el,$cond_buttons);
// end of NEW CONDITION
	return $tblConditions;
	}

	function insert_media_type_form(){

		$type		= get_request('type',0);
		$description	= get_request('description','');
		$smtp_server	= get_request('smtp_server','localhost');
		$smtp_helo	= get_request('smtp_helo','localhost');
		$smtp_email	= get_request('smtp_email','zabbix@localhost');
		$exec_path	= get_request('exec_path','');
		$gsm_modem	= get_request('gsm_modem','/dev/ttyS0');
		$username	= get_request('username','user@server');
		$password	= get_request('password','');

		if(isset($_REQUEST['mediatypeid']) && !isset($_REQUEST['form_refresh'])){
			$result = DBselect('select * FROM media_type WHERE mediatypeid='.$_REQUEST['mediatypeid']);

			$row = DBfetch($result);
			$mediatypeid	= $row['mediatypeid'];
			$type		= get_request('type',$row['type']);
			$description	= $row['description'];
			$smtp_server	= $row['smtp_server'];
			$smtp_helo	= $row['smtp_helo'];
			$smtp_email	= $row['smtp_email'];
			$exec_path	= $row['exec_path'];
			$gsm_modem	= $row['gsm_modem'];
			$username	= $row['username'];
			$password	= $row['passwd'];
		}

		$frmMeadia = new CFormTable(S_MEDIA);
		$frmMeadia->setHelp('web.config.medias.php');

		if(isset($_REQUEST['mediatypeid'])){
			$frmMeadia->addVar('mediatypeid',$_REQUEST['mediatypeid']);
		}

		$frmMeadia->addRow(S_DESCRIPTION,new CTextBox('description',$description,30));
		$cmbType = new CComboBox('type',$type,'submit()');
		$cmbType->addItem(MEDIA_TYPE_EMAIL,S_EMAIL);
		$cmbType->addItem(MEDIA_TYPE_JABBER,S_JABBER);
		$cmbType->addItem(MEDIA_TYPE_SMS,S_SMS);
		$cmbType->addItem(MEDIA_TYPE_EXEC,S_SCRIPT);
		$frmMeadia->addRow(S_TYPE,$cmbType);

		switch($type){
		case MEDIA_TYPE_EMAIL:
			$frmMeadia->addRow(S_SMTP_SERVER,new CTextBox('smtp_server',$smtp_server,30));
			$frmMeadia->addRow(S_SMTP_HELO,new CTextBox('smtp_helo',$smtp_helo,30));
			$frmMeadia->addRow(S_SMTP_EMAIL,new CTextBox('smtp_email',$smtp_email,30));
			break;
		case MEDIA_TYPE_SMS:
			$frmMeadia->addRow(S_GSM_MODEM,new CTextBox('gsm_modem',$gsm_modem,50));
			break;
		case MEDIA_TYPE_EXEC:
			$frmMeadia->addRow(S_SCRIPT_NAME,new CTextBox('exec_path',$exec_path,50));
			break;
		case MEDIA_TYPE_JABBER:
			$frmMeadia->addRow(S_JABBER_IDENTIFIER, new CTextBox('username',$username,30));
			$frmMeadia->addRow(S_PASSWORD, new CPassBox('password',$password,30));
		}

		$frmMeadia->addItemToBottomRow(new CButton('save',S_SAVE));
		if(isset($_REQUEST['mediatypeid'])){
			$frmMeadia->addItemToBottomRow(SPACE);
			$frmMeadia->addItemToBottomRow(new CButtonDelete(S_DELETE_SELECTED_MEDIA,
				url_param('form').url_param('mediatypeid')));
		}
		$frmMeadia->addItemToBottomRow(SPACE);
		$frmMeadia->addItemToBottomRow(new CButtonCancel());

	return $frmMeadia;
	}

	function import_screen_form($rules){

		$form = new CFormTable(S_IMPORT, null, 'post', 'multipart/form-data');
		$form->addRow(S_IMPORT_FILE, new CFile('import_file'));

		$table = new CTable();
		$table->setHeader(array(S_ELEMENT, S_UPDATE.SPACE.S_EXISTING, S_ADD.SPACE.S_MISSING), 'bold');

		$titles = array('screens' => S_SCREEN);

		foreach($titles as $key => $title){
			$cbExist = new CCheckBox('rules['.$key.'][exist]', isset($rules[$key]['exist']));

			if($key == 'template')
				$cbMissed = null;
			else
				$cbMissed = new CCheckBox('rules['.$key.'][missed]', isset($rules[$key]['missed']));

			$table->addRow(array($title, $cbExist, $cbMissed));
		}

		$form->addRow(S_RULES, $table);

		$form->addItemToBottomRow(new CButton('import', S_IMPORT));
		return $form;
	}

	function insert_screen_form(){

		$frm_title = S_SCREEN;
		if(isset($_REQUEST['screenid'])){
			$result=DBselect('SELECT screenid,name,hsize,vsize '.
						' FROM screens g '.
						' WHERE screenid='.$_REQUEST['screenid']);
			$row=DBfetch($result);
			$frm_title = S_SCREEN.' "'.$row['name'].'"';
		}
		if(isset($_REQUEST['screenid']) && !isset($_REQUEST['form_refresh'])){
			$name=$row['name'];
			$hsize=$row['hsize'];
			$vsize=$row['vsize'];
		}
		else{
			$name=get_request('name','');
			$hsize=get_request('hsize',1);
			$vsize=get_request('bsize',1);
		}

		$frmScr = new CFormTable($frm_title,'screenconf.php');
		$frmScr->setHelp('web.screenconf.screen.php');

		$frmScr->addVar('config', 0);

		if(isset($_REQUEST['screenid'])){
			$frmScr->addVar('screenid',$_REQUEST['screenid']);
		}
		$frmScr->addRow(S_NAME, new CTextBox('name',$name,32));
		$frmScr->addRow(S_COLUMNS, new CNumericBox('hsize',$hsize,3));
		$frmScr->addRow(S_ROWS, new CNumericBox('vsize',$vsize,3));

		$frmScr->addItemToBottomRow(new CButton('save',S_SAVE));
		if(isset($_REQUEST['screenid'])){
			/* $frmScr->addItemToBottomRow(SPACE);
			$frmScr->addItemToBottomRow(new CButton('clone',S_CLONE)); !!! TODO */
			$frmScr->addItemToBottomRow(SPACE);
			$frmScr->addItemToBottomRow(new CButtonDelete(S_DELETE_SCREEN_Q,
				url_param('form').url_param('screenid')));
		}
		$frmScr->addItemToBottomRow(SPACE);
		$frmScr->addItemToBottomRow(new CButtonCancel());
		
		return $frmScr;
	}

// HOSTS
	function insert_mass_update_host_form(){//$elements_array_name){
		global $USER_DETAILS;

		$visible = get_request('visible', array());

		$groups = get_request('groups', array());

		$newgroup = get_request('newgroup', '');

		$host 		= get_request('host',	'');
		$port 		= get_request('port',	CProfile::get('HOST_PORT',10050));
		$status		= get_request('status',	HOST_STATUS_MONITORED);
		$useip		= get_request('useip',	1);
		$dns		= get_request('dns',	'');
		$ip			= get_request('ip',	'0.0.0.0');
		$proxy_hostid	= get_request('proxy_hostid','');

		$useipmi	= get_request('useipmi', 'no');
		$ipmi_ip	= get_request('ipmi_ip', '');
		$ipmi_port	= get_request('ipmi_port', 623);
		$ipmi_authtype	= get_request('ipmi_authtype', -1);
		$ipmi_privilege	= get_request('ipmi_privilege', 2);
		$ipmi_username	= get_request('ipmi_username', '');
		$ipmi_password	= get_request('ipmi_password', '');

		
		$useprofile	= get_request('useprofile', 'no');
		$host_profile = get_request('host_profile', array());
		$profile_fields = array(
			'devicetype' => S_DEVICE_TYPE, 
			'name' => S_NAME, 
			'os' => S_OS, 
			'serialno' => S_SERIALNO, 
			'tag' => S_TAG,
			'macaddress' => S_MACADDRESS, 
			'hardware' => S_HARDWARE, 
			'software' => S_SOFTWARE,
			'contact' => S_CONTACT, 
			'location' => S_LOCATION, 
			'notes' => S_NOTES
		);
		foreach($profile_fields as $field => $caption){
			if(!isset($host_profile[$field])) $host_profile[$field] = '';
		}

// BEGIN: HOSTS PROFILE EXTENDED Section
		$useprofile_ext = get_request('useprofile_ext','no');
		$ext_host_profiles = get_request('ext_host_profiles', array());

		$ext_profiles_fields = array(
			'device_alias'=>S_DEVICE_ALIAS,
			'device_type'=>S_DEVICE_TYPE,
			'device_chassis'=>S_DEVICE_CHASSIS,
			'device_os'=>S_DEVICE_OS,
			'device_os_short'=>S_DEVICE_OS_SHORT,
			'device_hw_arch'=>S_DEVICE_HW_ARCH,
			'device_serial'=>S_DEVICE_SERIAL,
			'device_model'=>S_DEVICE_MODEL,
			'device_tag'=>S_DEVICE_TAG,
			'device_vendor'=>S_DEVICE_VENDOR,
			'device_contract'=>S_DEVICE_CONTRACT,
			'device_who'=>S_DEVICE_WHO,
			'device_status'=>S_DEVICE_STATUS,
			'device_app_01'=>S_DEVICE_APP_01,
			'device_app_02'=>S_DEVICE_APP_02,
			'device_app_03'=>S_DEVICE_APP_03,
			'device_app_04'=>S_DEVICE_APP_04,
			'device_app_05'=>S_DEVICE_APP_05,
			'device_url_1'=>S_DEVICE_URL_1,
			'device_url_2'=>S_DEVICE_URL_2,
			'device_url_3'=>S_DEVICE_URL_3,
			'device_networks'=>S_DEVICE_NETWORKS,
			'device_notes'=>S_DEVICE_NOTES,
			'device_hardware'=>S_DEVICE_HARDWARE,
			'device_software'=>S_DEVICE_SOFTWARE,
			'ip_subnet_mask'=>S_IP_SUBNET_MASK,
			'ip_router'=>S_IP_ROUTER,
			'ip_macaddress'=>S_IP_MACADDRESS,
			'oob_ip'=>S_OOB_IP,
			'oob_subnet_mask'=>S_OOB_SUBNET_MASK,
			'oob_router'=>S_OOB_ROUTER,
			'date_hw_buy'=>S_DATE_HW_BUY,
			'date_hw_install'=>S_DATE_HW_INSTALL,
			'date_hw_expiry'=>S_DATE_HW_EXPIRY,
			'date_hw_decomm'=>S_DATE_HW_DECOMM,
			'site_street_1'=>S_SITE_STREET_1,
			'site_street_2'=>S_SITE_STREET_2,
			'site_street_3'=>S_SITE_STREET_3,
			'site_city'=>S_SITE_CITY,
			'site_state'=>S_SITE_STATE,
			'site_country'=>S_SITE_COUNTRY,
			'site_zip'=>S_SITE_ZIP,
			'site_rack'=>S_SITE_RACK,
			'site_notes'=>S_SITE_NOTES,
			'poc_1_name'=>S_POC_1_NAME,
			'poc_1_email'=>S_POC_1_EMAIL,
			'poc_1_phone_1'=>S_POC_1_PHONE_1,
			'poc_1_phone_2'=>S_POC_1_PHONE_2,
			'poc_1_cell'=>S_POC_1_CELL,
			'poc_1_screen'=>S_POC_1_SCREEN,
			'poc_1_notes'=>S_POC_1_NOTES,
			'poc_2_name'=>S_POC_2_NAME,
			'poc_2_email'=>S_POC_2_EMAIL,
			'poc_2_phone_1'=>S_POC_2_PHONE_1,
			'poc_2_phone_2'=>S_POC_2_PHONE_2,
			'poc_2_cell'=>S_POC_2_CELL,
			'poc_2_screen'=>S_POC_2_SCREEN,
			'poc_2_notes'=>S_POC_2_NOTES
		);

		foreach($ext_profiles_fields as $field => $caption){
			if(!isset($ext_host_profiles[$field])) $ext_host_profiles[$field] = '';
		}

// END:   HOSTS PROFILE EXTENDED Section

		$templates	= get_request('templates',array());
		natsort($templates);

		$frm_title	= S_HOST.SPACE.S_MASS_UPDATE;

		$frmHost = new CFormTable($frm_title,'hosts.php');
		$frmHost->setHelp('web.hosts.host.php');
		$frmHost->addVar('go', 'massupdate');

		$hosts = $_REQUEST['hosts'];
		foreach($hosts as $id => $hostid){
			$frmHost->addVar('hosts['.$hostid.']',$hostid);
		}

//		$frmItem->addRow(array( new CVisibilityBox('visible[type]', isset($visible['type']), 'type', S_ORIGINAL),S_TYPE), $cmbType);

		$frmHost->addRow(S_NAME,S_ORIGINAL);

		$available_groups = get_accessible_groups_by_user($USER_DETAILS,PERM_READ_LIST);
		$grp_tb = new CTweenBox($frmHost,'groups',$groups,6);
		$db_groups=DBselect('SELECT DISTINCT groupid,name '.
						' FROM groups '.
						' WHERE '.DBcondition('groupid',$available_groups).
						' ORDER BY name');

		while($db_group=DBfetch($db_groups)){
			$grp_tb->addItem($db_group['groupid'],$db_group['name']);
		}

		$frmHost->addRow(array(new CVisibilityBox('visible[groups]', isset($visible['groups']), $grp_tb->GetName(), S_ORIGINAL),S_GROUPS),
						$grp_tb->Get(S_IN.SPACE.S_GROUPS,S_OTHER.SPACE.S_GROUPS)
					);

		$frmHost->addRow(array(new CVisibilityBox('visible[newgroup]', isset($visible['newgroup']), 'newgroup', S_ORIGINAL),S_NEW_GROUP),
						new CTextBox('newgroup',$newgroup),
						'new'
					);

// onchange does not work on some browsers: MacOS, KDE browser
		$frmHost->addRow(array(new CVisibilityBox('visible[dns]', isset($visible['dns']), 'dns', S_ORIGINAL),S_DNS_NAME),
						new CTextBox('dns',$dns,'40')
					);

		$frmHost->addRow(array(new CVisibilityBox('visible[ip]', isset($visible['ip']), 'ip', S_ORIGINAL),S_IP_ADDRESS),
						new CTextBox('ip',$ip,defined('ZBX_HAVE_IPV6')?39:15)
					);

		$cmbConnectBy = new CComboBox('useip', $useip);
		$cmbConnectBy->addItem(0, S_DNS_NAME);
		$cmbConnectBy->addItem(1, S_IP_ADDRESS);

		$frmHost->addRow(array(new CVisibilityBox('visible[useip]', isset($visible['useip']), 'useip', S_ORIGINAL),S_CONNECT_TO),
						$cmbConnectBy
					);

		$frmHost->addRow(array(new CVisibilityBox('visible[port]', isset($visible['port']), 'port', S_ORIGINAL),S_AGENT_PORT),						new CNumericBox('port',$port,5)
					);

//Proxy
		$cmbProxy = new CComboBox('proxy_hostid', $proxy_hostid);

		$cmbProxy->addItem(0, S_NO_PROXY);

		$sql = 'SELECT hostid,host '.
				' FROM hosts '.
				' WHERE status IN ('.HOST_STATUS_PROXY.') '.
					' AND '.DBin_node('hostid').
				' ORDER BY host';
		$db_proxies = DBselect($sql);
		while ($db_proxy = DBfetch($db_proxies))
			$cmbProxy->addItem($db_proxy['hostid'], $db_proxy['host']);

		$frmHost->addRow(array(
						new CVisibilityBox('visible[proxy_hostid]', isset($visible['proxy_hostid']), 'proxy_hostid', S_ORIGINAL),
						S_MONITORED_BY_PROXY),
						$cmbProxy
					);
//----------

		$cmbStatus = new CComboBox('status',$status);
		$cmbStatus->addItem(HOST_STATUS_MONITORED,	S_MONITORED);
		$cmbStatus->addItem(HOST_STATUS_NOT_MONITORED,	S_NOT_MONITORED);

		$frmHost->addRow(array(new CVisibilityBox('visible[status]', isset($visible['status']), 'status', S_ORIGINAL),S_STATUS), $cmbStatus);

// LINK TEMPLATES {{{
		$template_table = new CTable();
		$template_table->setAttribute('name','template_table');
		$template_table->setAttribute('id','template_table');

		$template_table->setCellPadding(0);
		$template_table->setCellSpacing(0);

		foreach($templates as $id => $temp_name){
			$frmHost->addVar('templates['.$id.']',$temp_name);
			$template_table->addRow(array(
				new CCheckBox('templates_rem['.$id.']', 'no', null, $id),
				$temp_name,
			));
		}

		$template_table->addRow(array(
			new CButton('add_template', S_ADD, "return PopUp('popup.php?dstfrm=".$frmHost->GetName().
				"&dstfld1=new_template&srctbl=templates&srcfld1=hostid&srcfld2=host".
				url_param($templates,false,'existed_templates')."',450,450)"),
			new CButton('unlink', S_REMOVE)
		));

		$vbox = new CVisibilityBox('visible[template_table]', isset($visible['template_table']), 'template_table', S_ORIGINAL);
		$vbox->setAttribute('id', 'cb_tpladd');
		if(isset($visible['template_table_r'])) $vbox->setAttribute('disabled', 'disabled');
		$action = $vbox->getAttribute('onclick');
		$action .= 'if($("cb_tplrplc").disabled) $("cb_tplrplc").enable(); else $("cb_tplrplc").disable();';
		$vbox->setAttribute('onclick', $action);
		
		$frmHost->addRow(array($vbox, S_LINK_ADDITIONAL_TEMPLATES), $template_table, 'T');
// }}} LINK TEMPLATES


// RELINK TEMPLATES {{{
		$template_table_r = new CTable();
		$template_table_r->setAttribute('name','template_table_r');
		$template_table_r->setAttribute('id','template_table_r');

		$template_table_r->setCellPadding(0);
		$template_table_r->setCellSpacing(0);

		foreach($templates as $id => $temp_name){
			$frmHost->addVar('templates['.$id.']',$temp_name);
			$template_table_r->addRow(array(
				new CCheckBox('templates_rem['.$id.']', 'no', null, $id),
				$temp_name,
			));
		}

		$template_table_r->addRow(array(
			new CButton('add_template', S_ADD, "return PopUp('popup.php?dstfrm=".$frmHost->GetName().
				"&dstfld1=new_template&srctbl=templates&srcfld1=hostid&srcfld2=host".
				url_param($templates,false,'existed_templates')."',450,450)"),
			new CButton('unlink', S_REMOVE)
		));
		
		$vbox = new CVisibilityBox('visible[template_table_r]', isset($visible['template_table_r']), 'template_table_r', S_ORIGINAL);
		$vbox->setAttribute('id', 'cb_tplrplc');
		if(isset($visible['template_table'])) $vbox->setAttribute('disabled', 'disabled');
		$action = $vbox->getAttribute('onclick');
		$action .= 'if($("cb_tpladd").disabled) $("cb_tpladd").enable(); else $("cb_tpladd").disable();';	
		$vbox->setAttribute('onclick', $action);

		$frmHost->addRow(array($vbox, S_RELINK_TEMPLATES),	$template_table_r, 'T');
// }}} RELINK TEMPLATES


		$frmHost->addRow(array(
			new CVisibilityBox('visible[useipmi]', isset($visible['useipmi']), 'useipmi', S_ORIGINAL), S_USEIPMI),
			new CCheckBox('useipmi', $useipmi, 'submit()')
		);

		if($useipmi == 'yes'){
			$frmHost->addRow(array(
				new CVisibilityBox('visible[ipmi_ip]', isset($visible['ipmi_ip']), 'ipmi_ip', S_ORIGINAL), S_IPMI_IP_ADDRESS),
				new CTextBox('ipmi_ip', $ipmi_ip, defined('ZBX_HAVE_IPV6') ? 39 : 15)
			);

			$frmHost->addRow(array(
				new CVisibilityBox('visible[ipmi_port]', isset($visible['ipmi_port']), 'ipmi_port', S_ORIGINAL), S_IPMI_PORT),
				new CNumericBox('ipmi_port', $ipmi_port, 15)
			);

			$cmbIPMIAuthtype = new CComboBox('ipmi_authtype', $ipmi_authtype);
			$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_DEFAULT,	S_AUTHTYPE_DEFAULT);
			$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_NONE,		S_AUTHTYPE_NONE);
			$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_MD2,		S_AUTHTYPE_MD2);
			$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_MD5,		S_AUTHTYPE_MD5);
			$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_STRAIGHT,	S_AUTHTYPE_STRAIGHT);
			$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_OEM,		S_AUTHTYPE_OEM);
			$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_RMCP_PLUS,	S_AUTHTYPE_RMCP_PLUS);
			$frmHost->addRow(array(
				new CVisibilityBox('visible[ipmi_authtype]', isset($visible['ipmi_authtype']), 'ipmi_authtype', S_ORIGINAL), S_IPMI_AUTHTYPE),
				$cmbIPMIAuthtype
			);

			$cmbIPMIPrivilege = new CComboBox('ipmi_privilege', $ipmi_privilege);
			$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_CALLBACK,	S_PRIVILEGE_CALLBACK);
			$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_USER,		S_PRIVILEGE_USER);
			$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_OPERATOR,	S_PRIVILEGE_OPERATOR);
			$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_ADMIN,	S_PRIVILEGE_ADMIN);
			$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_OEM,		S_PRIVILEGE_OEM);
			$frmHost->addRow(array(
				new CVisibilityBox('visible[ipmi_privilege]', isset($visible['ipmi_privilege']), 'ipmi_privilege', S_ORIGINAL), S_IPMI_PRIVILEGE),
				$cmbIPMIPrivilege
			);

			$frmHost->addRow(array(
				new CVisibilityBox('visible[ipmi_username]', isset($visible['ipmi_username']), 'ipmi_username', S_ORIGINAL), S_IPMI_USERNAME),
				new CTextBox('ipmi_username', $ipmi_username, 16)
			);

			$frmHost->addRow(array(
				new CVisibilityBox('visible[ipmi_password]', isset($visible['ipmi_password']), 'ipmi_password', S_ORIGINAL), S_IPMI_PASSWORD),
				new CTextBox('ipmi_password', $ipmi_password, 20)
			);
		}

		$frmHost->addRow(array(
					new CVisibilityBox('visible[useprofile]', isset($visible['useprofile']), 'useprofile', S_ORIGINAL),S_USE_PROFILE),
					new CCheckBox('useprofile',$useprofile,'submit()')
				);

// BEGIN: HOSTS PROFILE EXTENDED Section
		$frmHost->addRow(array(
			new CVisibilityBox('visible[useprofile_ext]', isset($visible['useprofile_ext']), 'useprofile_ext', S_ORIGINAL),S_USE_EXTENDED_PROFILE),
			new CCheckBox('useprofile_ext',$useprofile_ext,'submit()')
		);
// END:   HOSTS PROFILE EXTENDED Section

		if($useprofile==='yes'){
			if($useprofile === 'yes'){
				foreach($profile_fields as $field => $caption){
					$frmHost->addRow(array(
						new CVisibilityBox('visible['.$field.']', isset($visible[$field]), 'host_profile['.$field.']', S_ORIGINAL), $caption),
						new CTextBox('host_profile['.$field.']',$host_profile[$field],80)
					);
				}
			}
			else{
				foreach($profile_fields as $field => $caption){
					$frmHost->addVar('host_profile['.$field.']', $host_profile[$field]);
				}
			}
		}

// BEGIN: HOSTS PROFILE EXTENDED Section
		if($useprofile_ext=='yes'){
			foreach($ext_profiles_fields as $prof_field => $caption){
				$frmHost->addRow(array(
					new CVisibilityBox('visible['.$prof_field.']', isset($visible[$prof_field]), 'ext_host_profiles['.$prof_field.']', S_ORIGINAL),$caption),
					new CTextBox('ext_host_profiles['.$prof_field.']',$ext_host_profiles[$prof_field],80)
				);
			}
		}
		else{
			foreach($ext_profiles_fields as $prof_field => $caption){
				$frmHost->addVar('ext_host_profiles['.$prof_field.']',	$ext_host_profiles[$prof_field]);
			}
		}
// END:   HOSTS PROFILE EXTENDED Section

		$frmHost->addItemToBottomRow(new CButton('masssave',S_SAVE));
		$frmHost->addItemToBottomRow(SPACE);
		$frmHost->addItemToBottomRow(new CButtonCancel(url_param('config').url_param('groupid')));
		
		return $frmHost;
	}

// Host form
	function insert_host_form(){
		global $USER_DETAILS;

		$host_groups = get_request('groups', array());
		if(isset($_REQUEST['groupid']) && ($_REQUEST['groupid']>0) && !uint_in_array($_REQUEST['groupid'], $host_groups)){
			array_push($host_groups, $_REQUEST['groupid']);
		}

		$available_groups = get_accessible_groups_by_user($USER_DETAILS,PERM_READ_WRITE);

		$newgroup	= get_request('newgroup','');

		$host 		= get_request('host',	'');
		$port 		= get_request('port',	CProfile::get('HOST_PORT',10050));
		$status		= get_request('status',	HOST_STATUS_MONITORED);
		$useip		= get_request('useip',	1);
		$dns		= get_request('dns',	'');
		$ip		= get_request('ip',	'0.0.0.0');
		$proxy_hostid	= get_request('proxy_hostid','');

		$useipmi	= get_request('useipmi','no');
		$ipmi_ip	= get_request('ipmi_ip','');
		$ipmi_port	= get_request('ipmi_port',623);
		$ipmi_authtype	= get_request('ipmi_authtype',-1);
		$ipmi_privilege	= get_request('ipmi_privilege',2);
		$ipmi_username	= get_request('ipmi_username','');
		$ipmi_password	= get_request('ipmi_password','');

		$useprofile = get_request('useprofile','no');

		$devicetype	= get_request('devicetype','');
		$name		= get_request('name','');
		$os			= get_request('os','');
		$serialno	= get_request('serialno','');
		$tag		= get_request('tag','');
		$macaddress	= get_request('macaddress','');
		$hardware	= get_request('hardware','');
		$software	= get_request('software','');
		$contact	= get_request('contact','');
		$location	= get_request('location','');
		$notes		= get_request('notes','');

		$_REQUEST['hostid'] = get_request('hostid', 0);
// BEGIN: HOSTS PROFILE EXTENDED Section
		$useprofile_ext		= get_request('useprofile_ext','no');
		$ext_host_profiles	= get_request('ext_host_profiles',array());
// END:   HOSTS PROFILE EXTENDED Section

		$templates		= get_request('templates',array());
		$clear_templates	= get_request('clear_templates',array());

		$frm_title = S_HOST;
		if($_REQUEST['hostid']>0){
			$db_host = get_host_by_hostid($_REQUEST['hostid']);
			$frm_title	.= SPACE.' ['.$db_host['host'].']';

			$original_templates = get_templates_by_hostid($_REQUEST['hostid']);
		}
		else{
			$original_templates = array();
		}

		if(($_REQUEST['hostid']>0) && !isset($_REQUEST['form_refresh'])){
			$proxy_hostid		= $db_host['proxy_hostid'];
			$host			= $db_host['host'];
			$port			= $db_host['port'];
			$status			= $db_host['status'];
			$useip			= $db_host['useip'];
			$useipmi		= $db_host['useipmi'] ? 'yes' : 'no';
			$ip			= $db_host['ip'];
			$dns			= $db_host['dns'];
			$ipmi_ip		= $db_host['ipmi_ip'];

			$ipmi_port		= $db_host['ipmi_port'];
			$ipmi_authtype		= $db_host['ipmi_authtype'];
			$ipmi_privilege		= $db_host['ipmi_privilege'];
			$ipmi_username		= $db_host['ipmi_username'];
			$ipmi_password		= $db_host['ipmi_password'];

// add groups
			$options = array('hostids' => $_REQUEST['hostid']);
			$host_groups = CHostGroup::get($options);
			$host_groups = zbx_objectValues($host_groups, 'groupid');

// read profile
			$db_profiles = DBselect('SELECT * FROM hosts_profiles WHERE hostid='.$_REQUEST['hostid']);

			$useprofile = 'no';
			$db_profile = DBfetch($db_profiles);
			if($db_profile){
				$useprofile = 'yes';

				$devicetype	= $db_profile['devicetype'];
				$name		= $db_profile['name'];
				$os		= $db_profile['os'];
				$serialno	= $db_profile['serialno'];
				$tag		= $db_profile['tag'];
				$macaddress	= $db_profile['macaddress'];
				$hardware	= $db_profile['hardware'];
				$software	= $db_profile['software'];
				$contact	= $db_profile['contact'];
				$location	= $db_profile['location'];
				$notes		= $db_profile['notes'];
			}

// BEGIN: HOSTS PROFILE EXTENDED Section
			$useprofile_ext = 'no';

			$db_profiles_alt = DBselect('SELECT * FROM hosts_profiles_ext WHERE hostid='.$_REQUEST['hostid']);
			if($ext_host_profiles = DBfetch($db_profiles_alt)){
				$useprofile_ext = 'yes';
			}
			else{
				$ext_host_profiles = array();
			}
// END:   HOSTS PROFILE EXTENDED Section

			$templates = $original_templates;
		}

		$ext_profiles_fields = array(
				'device_alias'=>S_DEVICE_ALIAS,
				'device_type'=>S_DEVICE_TYPE,
				'device_chassis'=>S_DEVICE_CHASSIS,
				'device_os'=>S_DEVICE_OS,
				'device_os_short'=>S_DEVICE_OS_SHORT,
				'device_hw_arch'=>S_DEVICE_HW_ARCH,
				'device_serial'=>S_DEVICE_SERIAL,
				'device_model'=>S_DEVICE_MODEL,
				'device_tag'=>S_DEVICE_TAG,
				'device_vendor'=>S_DEVICE_VENDOR,
				'device_contract'=>S_DEVICE_CONTRACT,
				'device_who'=>S_DEVICE_WHO,
				'device_status'=>S_DEVICE_STATUS,
				'device_app_01'=>S_DEVICE_APP_01,
				'device_app_02'=>S_DEVICE_APP_02,
				'device_app_03'=>S_DEVICE_APP_03,
				'device_app_04'=>S_DEVICE_APP_04,
				'device_app_05'=>S_DEVICE_APP_05,
				'device_url_1'=>S_DEVICE_URL_1,
				'device_url_2'=>S_DEVICE_URL_2,
				'device_url_3'=>S_DEVICE_URL_3,
				'device_networks'=>S_DEVICE_NETWORKS,
				'device_notes'=>S_DEVICE_NOTES,
				'device_hardware'=>S_DEVICE_HARDWARE,
				'device_software'=>S_DEVICE_SOFTWARE,
				'ip_subnet_mask'=>S_IP_SUBNET_MASK,
				'ip_router'=>S_IP_ROUTER,
				'ip_macaddress'=>S_IP_MACADDRESS,
				'oob_ip'=>S_OOB_IP,
				'oob_subnet_mask'=>S_OOB_SUBNET_MASK,
				'oob_router'=>S_OOB_ROUTER,
				'date_hw_buy'=>S_DATE_HW_BUY,
				'date_hw_install'=>S_DATE_HW_INSTALL,
				'date_hw_expiry'=>S_DATE_HW_EXPIRY,
				'date_hw_decomm'=>S_DATE_HW_DECOMM,
				'site_street_1'=>S_SITE_STREET_1,
				'site_street_2'=>S_SITE_STREET_2,
				'site_street_3'=>S_SITE_STREET_3,
				'site_city'=>S_SITE_CITY,
				'site_state'=>S_SITE_STATE,
				'site_country'=>S_SITE_COUNTRY,
				'site_zip'=>S_SITE_ZIP,
				'site_rack'=>S_SITE_RACK,
				'site_notes'=>S_SITE_NOTES,
				'poc_1_name'=>S_POC_1_NAME,
				'poc_1_email'=>S_POC_1_EMAIL,
				'poc_1_phone_1'=>S_POC_1_PHONE_1,
				'poc_1_phone_2'=>S_POC_1_PHONE_2,
				'poc_1_cell'=>S_POC_1_CELL,
				'poc_1_screen'=>S_POC_1_SCREEN,
				'poc_1_notes'=>S_POC_1_NOTES,
				'poc_2_name'=>S_POC_2_NAME,
				'poc_2_email'=>S_POC_2_EMAIL,
				'poc_2_phone_1'=>S_POC_2_PHONE_1,
				'poc_2_phone_2'=>S_POC_2_PHONE_2,
				'poc_2_cell'=>S_POC_2_CELL,
				'poc_2_screen'=>S_POC_2_SCREEN,
				'poc_2_notes'=>S_POC_2_NOTES
			);


		foreach($ext_profiles_fields as $field => $caption){
			if(!isset($ext_host_profiles[$field])) $ext_host_profiles[$field] = '';
		}

		$clear_templates = array_intersect($clear_templates, array_keys($original_templates));
		$clear_templates = array_diff($clear_templates,array_keys($templates));
		natcasesort($templates);

		$frmHost = new CForm('hosts.php', 'post');
		$frmHost->setName('web.hosts.host.php.');
//		$frmHost->setHelp('web.hosts.host.php');
//		$frmHost->addVar('config',get_request('config',0));
		$frmHost->addVar('form', get_request('form', 1));
		$from_rfr = get_request('form_refresh',0);
		$frmHost->addVar('form_refresh', $from_rfr+1);
		$frmHost->addVar('clear_templates', $clear_templates);

// HOST WIDGET {
		$host_tbl = new CTable('', 'tablestripped');
		$host_tbl->setOddRowClass('form_odd_row');
		$host_tbl->setEvenRowClass('form_even_row');

		if($_REQUEST['hostid']>0) $frmHost->addVar('hostid', $_REQUEST['hostid']);
		if($_REQUEST['groupid']>0) $frmHost->addVar('groupid', $_REQUEST['groupid']);

		$host_tbl->addRow(array(S_NAME, new CTextBox('host',$host,54)));

		$grp_tb = new CTweenBox($frmHost, 'groups', $host_groups, 10);

		$all_groups = CHostGroup::get(array('editable' => 1, 'extendoutput' => 1));
		order_result($all_groups, 'name');
		foreach($all_groups as $group){
			$grp_tb->addItem($group['groupid'], $group['name']);
		}

		$host_tbl->addRow(array(S_GROUPS,$grp_tb->get(S_IN.SPACE.S_GROUPS,S_OTHER.SPACE.S_GROUPS)));

		$host_tbl->addRow(array(S_NEW_GROUP, new CTextBox('newgroup',$newgroup)));

// onchange does not work on some browsers: MacOS, KDE browser
		$host_tbl->addRow(array(S_DNS_NAME,new CTextBox('dns',$dns,'40')));
		if(defined('ZBX_HAVE_IPV6')){
			$host_tbl->addRow(array(S_IP_ADDRESS,new CTextBox('ip',$ip,'39')));
		}
		else{
			$host_tbl->addRow(array(S_IP_ADDRESS,new CTextBox('ip',$ip,'15')));
		}

		$cmbConnectBy = new CComboBox('useip', $useip);
		$cmbConnectBy->addItem(0, S_DNS_NAME);
		$cmbConnectBy->addItem(1, S_IP_ADDRESS);
		$host_tbl->addRow(array(S_CONNECT_TO,$cmbConnectBy));

		$host_tbl->addRow(array(S_AGENT_PORT,new CNumericBox('port',$port,5)));

//Proxy
		$cmbProxy = new CComboBox('proxy_hostid', $proxy_hostid);

		$cmbProxy->addItem(0, S_NO_PROXY);
		$options = array('extendoutput' => 1);
		$db_proxies = CProxy::get($options);
		order_result($db_proxies, 'host');

		foreach($db_proxies as $proxy){
			$cmbProxy->addItem($proxy['proxyid'], $proxy['host']);
		}

		$host_tbl->addRow(array(S_MONITORED_BY_PROXY, $cmbProxy));
//----------

		$cmbStatus = new CComboBox('status',$status);
		$cmbStatus->addItem(HOST_STATUS_MONITORED,	S_MONITORED);
		$cmbStatus->addItem(HOST_STATUS_NOT_MONITORED,	S_NOT_MONITORED);
		$host_tbl->addRow(array(S_STATUS,$cmbStatus));

		$host_tbl->addRow(array(S_USEIPMI, new CCheckBox('useipmi', $useipmi, 'submit()')));

		if($useipmi == 'yes'){
			$host_tbl->addRow(array(S_IPMI_IP_ADDRESS, new CTextBox('ipmi_ip', $ipmi_ip, defined('ZBX_HAVE_IPV6') ? 39 : 15)));
			$host_tbl->addRow(array(S_IPMI_PORT, new CNumericBox('ipmi_port', $ipmi_port, 5)));

			$cmbIPMIAuthtype = new CComboBox('ipmi_authtype', $ipmi_authtype);
			$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_DEFAULT,	S_AUTHTYPE_DEFAULT);
			$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_NONE,		S_AUTHTYPE_NONE);
			$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_MD2,		S_AUTHTYPE_MD2);
			$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_MD5,		S_AUTHTYPE_MD5);
			$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_STRAIGHT,	S_AUTHTYPE_STRAIGHT);
			$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_OEM,		S_AUTHTYPE_OEM);
			$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_RMCP_PLUS,	S_AUTHTYPE_RMCP_PLUS);
			$host_tbl->addRow(array(S_IPMI_AUTHTYPE, $cmbIPMIAuthtype));

			$cmbIPMIPrivilege = new CComboBox('ipmi_privilege', $ipmi_privilege);
			$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_CALLBACK,	S_PRIVILEGE_CALLBACK);
			$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_USER,		S_PRIVILEGE_USER);
			$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_OPERATOR,	S_PRIVILEGE_OPERATOR);
			$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_ADMIN,	S_PRIVILEGE_ADMIN);
			$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_OEM,		S_PRIVILEGE_OEM);
			$host_tbl->addRow(array(S_IPMI_PRIVILEGE, $cmbIPMIPrivilege));

			$host_tbl->addRow(array(S_IPMI_USERNAME, new CTextBox('ipmi_username', $ipmi_username, 16)));
			$host_tbl->addRow(array(S_IPMI_PASSWORD, new CTextBox('ipmi_password', $ipmi_password, 20)));
		}
		else{
			$frmHost->addVar('ipmi_ip', $ipmi_ip);
			$frmHost->addVar('ipmi_port', $ipmi_port);
			$frmHost->addVar('ipmi_authtype', $ipmi_authtype);
			$frmHost->addVar('ipmi_privilege', $ipmi_privilege);
			$frmHost->addVar('ipmi_username', $ipmi_username);
			$frmHost->addVar('ipmi_password', $ipmi_password);
		}

		if($_REQUEST['form'] == 'full_clone'){
// Host items
			$items_lbx = new CListBox('items',null,8);
			$items_lbx->setAttribute('disabled','disabled');

			$sql = 'SELECT * '.
					' FROM items '.
					' WHERE hostid='.$_REQUEST['hostid'].
						' AND templateid=0 '.
					' ORDER BY description';
			$host_items_res = DBselect($sql);
			while($host_item = DBfetch($host_items_res)){
				$item_description = item_description($host_item);
				$items_lbx->addItem($host_item['itemid'],$item_description);
			}

			if($items_lbx->ItemsCount() < 1) $items_lbx->setAttribute('style','width: 200px;');
			$host_tbl->addRow(array(S_ITEMS, $items_lbx));

// Host triggers
			$available_triggers = get_accessible_triggers(PERM_READ_ONLY, array($_REQUEST['hostid']), PERM_RES_IDS_ARRAY);

			$trig_lbx = new CListBox('triggers',null,8);
			$trig_lbx->setAttribute('disabled','disabled');

			$sql = 'SELECT DISTINCT t.* '.
					' FROM triggers t, items i, functions f'.
					' WHERE i.hostid='.$_REQUEST['hostid'].
						' AND f.itemid=i.itemid '.
						' AND t.triggerid=f.triggerid '.
						' AND '.DBcondition('t.triggerid', $available_triggers).
						' AND t.templateid=0 '.
					' ORDER BY t.description';

			$host_trig_res = DBselect($sql);
			while($host_trig = DBfetch($host_trig_res)){
				$trig_description = expand_trigger_description($host_trig["triggerid"]);
				$trig_lbx->addItem($host_trig['triggerid'],$trig_description);
			}

			if($trig_lbx->ItemsCount() < 1) $trig_lbx->setAttribute('style','width: 200px;');
			$host_tbl->addRow(array(S_TRIGGERS, $trig_lbx));

// Host graphs
			$available_graphs = get_accessible_graphs(PERM_READ_ONLY, array($_REQUEST['hostid']), PERM_RES_IDS_ARRAY);

			$graphs_lbx = new CListBox('graphs',null,8);
			$graphs_lbx->setAttribute('disabled','disabled');

			$def_items = array();
			$sql = 'SELECT DISTINCT g.* '.
						' FROM graphs g, graphs_items gi,items i '.
						' WHERE '.DBcondition('g.graphid',$available_graphs).
							' AND gi.graphid=g.graphid '.
							' AND g.templateid=0 '.
							' AND i.itemid=gi.itemid '.
							' AND i.hostid='.$_REQUEST['hostid'].
						' ORDER BY g.name';

			$host_graph_res = DBselect($sql);
			while($host_graph = DBfetch($host_graph_res)){
				$graphs_lbx->addItem($host_graph['graphid'],$host_graph['name']);
			}

			if($graphs_lbx->ItemsCount() < 1) $graphs_lbx->setAttribute('style','width: 200px;');

			$host_tbl->addRow(array(S_GRAPHS, $graphs_lbx));
		}

		$host_footer = array();
		$host_footer[] = new CButton('save', S_SAVE);
		if(($_REQUEST['hostid']>0) && ($_REQUEST['form'] != 'full_clone')){
			array_push($host_footer, SPACE, new CButton('clone', S_CLONE), SPACE, new CButton('full_clone', S_FULL_CLONE), SPACE,
				new CButtonDelete(S_DELETE_SELECTED_HOST_Q, url_param('form').url_param('hostid').url_param('groupid')));
		}
		array_push($host_footer, SPACE, new CButtonCancel(url_param('groupid')));

		$host_footer = new CCol($host_footer);
		$host_footer->setColSpan(2);
		$host_tbl->setFooter($host_footer);

		$host_wdgt = new CWidget();
		$host_wdgt->setClass('header');
		$host_wdgt->addHeader($frm_title);
		$host_wdgt->addItem($host_tbl);
// } HOST WIDGET

// TEMPLATES{
		$template_tbl = new CTableInfo(S_NO_TEMPLATES_LINKED, 'tablestripped');
		$template_tbl->setOddRowClass('form_odd_row');
		$template_tbl->setEvenRowClass('form_even_row');

		foreach($templates as $id => $temp_name){
			$frmHost->addVar('templates['.$id.']', $temp_name);
			$template_tbl->addRow(new CCol(array(
				new CCheckBox('templates_rem['.$id.']', 'no', null, $id),
				$temp_name))
			);
		}

		$footer = new CCol(array(
			new CButton('add_template', S_ADD,
				"return PopUp('popup.php?dstfrm=".$frmHost->getName().
				"&dstfld1=new_template&srctbl=templates&srcfld1=hostid&srcfld2=host".
				url_param($templates,false,'existed_templates')."',450,450)",
				'T'),
			SPACE,
			new CButton('unlink', S_UNLINK),
			SPACE,
			new CButton('unlink_and_clear', S_UNLINK_AND_CLEAR)
		));
		//$footer->setColSpan(2);

		$template_tbl->setFooter($footer);

		$template_wdgt = new CWidget();
		$template_wdgt->setClass('header');
		$template_wdgt->addHeader(S_LINKED_TEMPLATES);
		$template_wdgt->addItem($template_tbl);

// } TEMPLATES


// MACROS WIDGET {
		$macros_wdgt = get_macros_widget($_REQUEST['hostid']);
// } MACROS WIDGET


// PROFILE WIDGET {
		$profile_tbl = new CTable('', 'tablestripped');
		$profile_tbl->setOddRowClass('form_odd_row');
		$profile_tbl->setEvenRowClass('form_even_row');

		$profile_tbl->addRow(array(S_USE_PROFILE,new CCheckBox('useprofile',$useprofile,'submit()')));

		if($useprofile == 'yes'){
			$profile_tbl->addRow(array(S_DEVICE_TYPE,new CTextBox('devicetype',$devicetype,61)));
			$profile_tbl->addRow(array(S_NAME,new CTextBox('name',$name,61)));
			$profile_tbl->addRow(array(S_OS,new CTextBox('os',$os,61)));
			$profile_tbl->addRow(array(S_SERIALNO,new CTextBox('serialno',$serialno,61)));
			$profile_tbl->addRow(array(S_TAG,new CTextBox('tag',$tag,61)));
			$profile_tbl->addRow(array(S_MACADDRESS,new CTextBox('macaddress',$macaddress,61)));
			$profile_tbl->addRow(array(S_HARDWARE,new CTextArea('hardware',$hardware,60,4)));
			$profile_tbl->addRow(array(S_SOFTWARE,new CTextArea('software',$software,60,4)));
			$profile_tbl->addRow(array(S_CONTACT,new CTextArea('contact',$contact,60,4)));
			$profile_tbl->addRow(array(S_LOCATION,new CTextArea('location',$location,60,4)));
			$profile_tbl->addRow(array(S_NOTES,new CTextArea('notes',$notes,60,4)));
		}
		else{
			$frmHost->addVar('devicetype', $devicetype);
			$frmHost->addVar('name',$name);
			$frmHost->addVar('os',$os);
			$frmHost->addVar('serialno',$serialno);
			$frmHost->addVar('tag',	$tag);
			$frmHost->addVar('macaddress',$macaddress);
			$frmHost->addVar('hardware',$hardware);
			$frmHost->addVar('software',$software);
			$frmHost->addVar('contact',$contact);
			$frmHost->addVar('location',$location);
			$frmHost->addVar('notes',$notes);
		}

		$profile_wdgt = new CWidget();
		$profile_wdgt->setClass('header');
		$profile_wdgt->addHeader(S_PROFILE);
		$profile_wdgt->addItem($profile_tbl);
// } PROFILE WIDGET

// EXT PROFILE WIDGET {
		$ext_profile_tbl = new CTable('', 'tablestripped');
		$ext_profile_tbl->setOddRowClass('form_odd_row');
		$ext_profile_tbl->setEvenRowClass('form_even_row');
		$ext_profile_tbl->addRow(array(S_USE_EXTENDED_PROFILE,new CCheckBox('useprofile_ext',$useprofile_ext,'submit()','yes')));

		foreach($ext_profiles_fields as $prof_field => $caption){
			if($useprofile_ext == 'yes'){
				$ext_profile_tbl->addRow(array($caption,new CTextBox('ext_host_profiles['.$prof_field.']',$ext_host_profiles[$prof_field],40)));
			}
			else{
				$frmHost->addVar('ext_host_profiles['.$prof_field.']',	$ext_host_profiles[$prof_field]);
			}
		}

		$ext_profile_wdgt = new CWidget();
		$ext_profile_wdgt->setClass('header');
		$ext_profile_wdgt->addHeader(S_EXTENDED_HOST_PROFILE);
		$ext_profile_wdgt->addItem($ext_profile_tbl);
// } EXT PROFILE WIDGET

		$left_table = new CTable();
		$left_table->setCellPadding(4);
		$left_table->setCellSpacing(4);
		$left_table->addRow($host_wdgt);

		$right_table = new CTable();
		$right_table->setCellPadding(4);
		$right_table->setCellSpacing(4);
		$right_table->addRow($template_wdgt);
		$right_table->addRow($macros_wdgt);
		$right_table->addRow($profile_wdgt);
		$right_table->addRow($ext_profile_wdgt);


		$td_l = new CCol($left_table);
		$td_l->setAttribute('valign','top');
		$td_r = new CCol($right_table);
		$td_r->setAttribute('valign','top');

		$outer_table = new CTable();
		$outer_table->addRow(array($td_l, $td_r));

		$frmHost->addItem($outer_table);
		return $frmHost;
	}

// Insert host profile ReadOnly form
	function insert_host_profile_form(){

		$frmHostP = new CFormTable(S_HOST_PROFILE);
		$frmHostP->setHelp("web.host_profile.php");

		$table_titles = array(
				'devicetype' => S_DEVICE_TYPE, 'name' => S_NAME, 'os' => S_OS, 'serialno' => S_SERIALNO,
				'tag' => S_TAG, 'macaddress' => S_MACADDRESS, 'hardware' => S_HARDWARE, 'software' => S_SOFTWARE,
				'contact' => S_CONTACT, 'location' => S_LOCATION, 'notes' => S_NOTES);

		$sql_fields = implode(', ', array_keys($table_titles)); //generate string of fields to get from DB

		$sql = 'SELECT '.$sql_fields.' FROM hosts_profiles WHERE hostid='.$_REQUEST['hostid'];
		$result = DBselect($sql);

		if($row=DBfetch($result)) {
			foreach($row as $key => $value) {
				if(!zbx_empty($value)) {
					$frmHostP->addRow($table_titles[$key], new CTextBox($key, $value, 61, 'yes'));
				}
			}
		}
		else{
			$frmHostP->addSpanRow(S_PROFILE_FOR_THIS_HOST_IS_MISSING,"form_row_c");
		}
		$frmHostP->addItemToBottomRow(new CButtonCancel(url_param('groupid').url_param('prof_type')));
	return $frmHostP;
	}

// BEGIN: HOSTS PROFILE EXTENDED Section
	function insert_host_profile_ext_form(){

		$frmHostPA = new CFormTable(S_EXTENDED_HOST_PROFILE);
		$frmHostPA->setHelp('web.host_profile_alt.php');

		$table_titles = array(
				'device_alias' => S_DEVICE_ALIAS, 'device_type' => S_DEVICE_TYPE, 'device_chassis' => S_DEVICE_CHASSIS, 'device_os' => S_DEVICE_OS,
				'device_os_short' => S_DEVICE_OS_SHORT, 'device_hw_arch' => S_DEVICE_HW_ARCH, 'device_serial' => S_DEVICE_SERIAL,
				'device_model' => S_DEVICE_MODEL, 'device_tag' => S_DEVICE_TAG, 'device_vendor' => S_DEVICE_VENDOR, 'device_contract' => S_DEVICE_CONTRACT,
				'device_who' => S_DEVICE_WHO, 'device_status' => S_DEVICE_STATUS, 'device_app_01' => S_DEVICE_APP_01, 'device_app_02' => S_DEVICE_APP_02,
				'device_app_03' => S_DEVICE_APP_03, 'device_app_04' => S_DEVICE_APP_04, 'device_app_05' => S_DEVICE_APP_05, 'device_url_1' => S_DEVICE_URL_1,
				'device_url_2' => S_DEVICE_URL_2, 'device_url_3' => S_DEVICE_URL_3, 'device_networks' => S_DEVICE_NETWORKS, 'device_notes' => S_DEVICE_NOTES,
				'device_hardware' => S_DEVICE_HARDWARE, 'device_software' => S_DEVICE_SOFTWARE, 'ip_subnet_mask' => S_IP_SUBNET_MASK, 'ip_router' => S_IP_ROUTER,
				'ip_macaddress' => S_IP_MACADDRESS, 'oob_ip' => S_OOB_IP, 'oob_subnet_mask' => S_OOB_SUBNET_MASK, 'oob_router' => S_OOB_ROUTER,
				'date_hw_buy' => S_DATE_HW_BUY, 'date_hw_install' => S_DATE_HW_INSTALL, 'date_hw_expiry' => S_DATE_HW_EXPIRY, 'date_hw_decomm' => S_DATE_HW_DECOMM,
				'site_street_1' => S_SITE_STREET_1, 'site_street_2' => S_SITE_STREET_2, 'site_street_3' => S_SITE_STREET_3, 'site_city' => S_SITE_CITY,
				'site_state' => S_SITE_STATE, 'site_country' => S_SITE_COUNTRY, 'site_zip' => S_SITE_ZIP, 'site_rack' => S_SITE_RACK,
				'site_notes' => S_SITE_NOTES, 'poc_1_name' => S_POC_1_NAME, 'poc_1_email' => S_POC_1_EMAIL, 'poc_1_phone_1' => S_POC_1_PHONE_1,
				'poc_1_phone_2' => S_POC_1_PHONE_2, 'poc_1_cell' => S_POC_1_CELL, 'poc_1_notes' => S_POC_1_NOTES, 'poc_2_name' => S_POC_2_NAME,
				'poc_2_email' => S_POC_2_EMAIL, 'poc_2_phone_1' => S_POC_2_PHONE_1, 'poc_2_phone_2' => S_POC_2_PHONE_2, 'poc_2_cell' => S_POC_2_CELL,
				'poc_2_screen' => S_POC_2_SCREEN, 'poc_2_notes' => S_POC_2_NOTES);

		$sql_fields = implode(', ', array_keys($table_titles)); //generate string of fields to get from DB

		$result=DBselect('SELECT '.$sql_fields.' FROM hosts_profiles_ext WHERE hostid='.$_REQUEST['hostid']);

		if($row=DBfetch($result)) {
			foreach($row as $key => $value) {
				if(!zbx_empty($value)) {
					$frmHostPA->addRow($table_titles[$key], new CTextBox('ext_host_profiles['.$key.']', $value, 61, 'yes'));
				}
			}
		}
		else{
			$frmHostPA->addSpanRow('Extended Profile for this host is missing','form_row_c');
		}
		$frmHostPA->addItemToBottomRow(new CButtonCancel(url_param('groupid').url_param('prof_type')));
	return $frmHostPA;
	}
// END:   HOSTS PROFILE EXTENDED Section

 	function insert_template_link_form($available_hosts){
		global $USER_DETAILS;

 		$frm_title = S_TEMPLATE_LINKAGE;

 		if($_REQUEST['hostid']>0){
 			$template = get_host_by_hostid($_REQUEST['hostid']);
 			$frm_title.= ' ['.$template['host'].']';
 		}

 		if(($_REQUEST['hostid']>0) && !isset($_REQUEST['form_refresh'])){
 			$name=$template['host'];
 		}
 		else{
 			$name=get_request("tname",'');
 		}

		$available_groups = get_accessible_groups_by_user($USER_DETAILS,PERM_READ_WRITE);

		$selected_grp = get_request('twb_groupid', 0);
		$selected_grp = isset($available_groups[$selected_grp]) ? $selected_grp :  reset($available_groups);

		$cmbGroups = new CComboBox('twb_groupid', $selected_grp, 'submit()');
//		$cmbGroups->addItem(0,S_ALL_S);
		$sql = 'SELECT DISTINCT g.groupid, g.name '.
				' FROM groups g, hosts_groups hg, hosts h '.
				' WHERE '.DBcondition('g.groupid',$available_groups).
					' AND g.groupid=hg.groupid '.
					' AND h.hostid=hg.hostid'.
					' AND h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.','.HOST_STATUS_TEMPLATE.') '.
				' ORDER BY g.name';
		$result=DBselect($sql);
		while($row=DBfetch($result)){
			$cmbGroups->addItem($row['groupid'],$row['name']);
		}

 		$frmHostT = new CFormTable($frm_title,'hosts.php');
 		$frmHostT->setHelp('web.hosts.group.php');
 		$frmHostT->addVar('config',get_request('config',2));
 		if($_REQUEST['hostid']>0){
 			$frmHostT->addVar('hostid',$_REQUEST['hostid']);
 		}

 		$frmHostT->addRow(S_TEMPLATE,new CTextBox('tname',$name,60,'yes'));

		$hosts_in_tpl = array();
		$sql_where = '';
		if(isset($_REQUEST['form_refresh'])){

			$saved_hosts = get_request('hosts', array());
			$hosts_in_tpl = array_intersect($available_hosts, $saved_hosts);

			$sql = 'SELECT DISTINCT h.hostid,h.host '.
				' FROM hosts h'.
				' WHERE '.DBcondition('h.hostid',$hosts_in_tpl).
				' ORDER BY h.host';
			$db_hosts=DBselect($sql);
			while($db_host=DBfetch($db_hosts)){
				$hosts_in_tpl[$db_host['hostid']] = $db_host['hostid'];
			}
		}
		else{
			$sql = 'SELECT DISTINCT h.hostid,h.host '.
				' FROM hosts h,hosts_templates ht'.
				' WHERE (ht.templateid='.$_REQUEST['hostid'].
					' AND h.hostid=ht.hostid'.
					' AND h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.','.HOST_STATUS_TEMPLATE.') '.
					' AND '.DBcondition('h.hostid',$available_hosts).' )'.
				' ORDER BY h.host';
			$db_hosts=DBselect($sql);
			while($db_host=DBfetch($db_hosts)){
				$hosts_in_tpl[$db_host['hostid']] = $db_host['hostid'];
			}
		}

 		$cmbHosts = new CTweenBox($frmHostT,'hosts',$hosts_in_tpl,25);


		$sql = 'SELECT DISTINCT h.hostid,h.host '.
			' FROM hosts h, hosts_groups hg'.
 			' WHERE ( h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.','.HOST_STATUS_TEMPLATE.') '.
 				' AND '.DBcondition('h.hostid',$available_hosts).
				' AND hg.groupid='.$selected_grp.
				' AND h.hostid=hg.hostid)'.
				' OR '.DBcondition('h.hostid', $hosts_in_tpl).
 			' ORDER BY h.host';
 		$db_hosts=DBselect($sql);

 		while($db_host=DBfetch($db_hosts)){
 			$cmbHosts->addItem($db_host['hostid'],get_node_name_by_elid($db_host['hostid'], null, ': ').$db_host['host']);
 		}

 		$frmHostT->addRow(S_HOSTS,$cmbHosts->Get(S_HOSTS.SPACE.S_IN,array(S_OTHER.SPACE.S_HOSTS.SPACE.'|'.SPACE.S_GROUP.SPACE,$cmbGroups)));

 		$frmHostT->addItemToBottomRow(new CButton('save',S_SAVE));
 		$frmHostT->addItemToBottomRow(SPACE);
 		$frmHostT->addItemToBottomRow(new CButtonCancel(url_param('config')));
 		$frmHostT->show();
	}

	function import_map_form($rules){

		$form = new CFormTable(S_IMPORT, null, 'post', 'multipart/form-data');
		$form->addRow(S_IMPORT_FILE, new CFile('import_file'));

		$table = new CTable();
		$table->setHeader(array(S_ELEMENT, S_UPDATE.SPACE.S_EXISTING, S_ADD.SPACE.S_MISSING), 'bold');

		$titles = array('maps' => S_MAP);

		foreach($titles as $key => $title){
			$cbExist = new CCheckBox('rules['.$key.'][exist]', isset($rules[$key]['exist']));

			if($key == 'template')
				$cbMissed = null;
			else
				$cbMissed = new CCheckBox('rules['.$key.'][missed]', isset($rules[$key]['missed']));

			$table->addRow(array($title, $cbExist, $cbMissed));
		}

		$form->addRow(S_RULES, $table);

		$form->addItemToBottomRow(new CButton('import', S_IMPORT));
		return $form;
	}

	function insert_map_form(){
		$frm_title = 'New system map';

		if(isset($_REQUEST['sysmapid'])){
			$result=DBselect('SELECT * FROM sysmaps WHERE sysmapid='.$_REQUEST['sysmapid']);
			$row=DBfetch($result);
			$frm_title = 'System map: "'.$row['name'].'"';
		}

		if(isset($_REQUEST['sysmapid']) && !isset($_REQUEST['form_refresh'])){
			$name		= $row['name'];
			$width		= $row['width'];
			$height		= $row['height'];
			$backgroundid	= $row['backgroundid'];
			$label_type	= $row['label_type'];
			$label_location	= $row['label_location'];
			$highlight = ($row['highlight']%2);
			
			$expproblem = ($row['highlight'] > 1) ? 0 : 1;
		}
		else{
			$name		= get_request('name','');
			$width		= get_request('width',800);
			$height		= get_request('height',600);
			$backgroundid	= get_request('backgroundid',0);
			$label_type	= get_request('label_type',0);
			$label_location	= get_request('label_location',0);
			$highlight = get_request('highlight',0);
			
			$expproblem = isset($_REQUEST['form_refresh']) ? get_request('expproblem',0) : 1;
		}

		$frmMap = new CFormTable($frm_title,'sysmaps.php');
		$frmMap->setHelp('web.sysmaps.map.php');

		if(isset($_REQUEST['sysmapid']))
			$frmMap->addVar('sysmapid',$_REQUEST['sysmapid']);

		$frmMap->addRow(S_NAME,new CTextBox('name',$name,32));
		$frmMap->addRow(S_WIDTH,new CNumericBox('width',$width,5));
		$frmMap->addRow(S_HEIGHT,new CNumericBox('height',$height,5));

		$cmbImg = new CComboBox('backgroundid',$backgroundid);
		$cmbImg->addItem(0,S_NO_IMAGE.'...');

		$result=DBselect('SELECT * FROM images WHERE imagetype=2 AND '.DBin_node('imageid').' order by name');
		while($row=DBfetch($result)){
			$cmbImg->addItem(
					$row['imageid'],
					get_node_name_by_elid($row['imageid'], null, ': ').$row['name']
					);
		}

		$frmMap->addRow(S_BACKGROUND_IMAGE,$cmbImg);

		$frmMap->addRow(S_ICON_HIGHLIGHTING, new CCheckBox('highlight',$highlight,null,1));
		
		$frmMap->addRow(S_EXPAND_SINGLE_PROBLEM, new CCheckBox('expproblem',$expproblem,null,1));

		$cmbLabel = new CComboBox('label_type',$label_type);
		$cmbLabel->addItem(0,S_LABEL);
		$cmbLabel->addItem(1,S_IP_ADDRESS);
		$cmbLabel->addItem(2,S_ELEMENT_NAME);
		$cmbLabel->addItem(3,S_STATUS_ONLY);
		$cmbLabel->addItem(4,S_NOTHING);
		$frmMap->addRow(S_ICON_LABEL_TYPE,$cmbLabel);

		$cmbLocation = new CComboBox('label_location',$label_location);

		$cmbLocation->addItem(0,S_BOTTOM);
		$cmbLocation->addItem(1,S_LEFT);
		$cmbLocation->addItem(2,S_RIGHT);
		$cmbLocation->addItem(3,S_TOP);
		$frmMap->addRow(S_ICON_LABEL_LOCATION,$cmbLocation);

		$frmMap->addItemToBottomRow(new CButton('save',S_SAVE));

		if(isset($_REQUEST['sysmapid'])){
			$frmMap->addItemToBottomRow(SPACE);
			$frmMap->addItemToBottomRow(new CButtonDelete(S_DELETE_SYSTEM_MAP_Q,
					url_param('form').url_param('sysmapid')));
		}

		$frmMap->addItemToBottomRow(SPACE);
		$frmMap->addItemToBottomRow(new CButtonCancel());

		return $frmMap;
	}

	function insert_command_result_form($scriptid,$hostid){
		$result = execute_script($scriptid,$hostid);

		$sql = 'SELECT name '.
				' FROM scripts '.
				' WHERE scriptid='.$scriptid;
		$script_info = DBfetch(DBselect($sql));

		$frmResult = new CFormTable($script_info['name'].': '.script_make_command($scriptid,$hostid));
		$message = $result['value'];
		if($result['response'] == 'failed'){
			error($message);
			$message = '';
		}

		$frmResult->addRow(S_RESULT,new CTextArea('message',$message,100,25,'yes'));
		$frmResult->addItemToBottomRow(new CButton('close',S_CLOSE,'window.close();'));
		$frmResult->show();
	}

	function get_regexp_form(){
		$frm_title = S_REGULAR_EXPRESSION;

		if(isset($_REQUEST['regexpid']) && !isset($_REQUEST["form_refresh"])){
			$sql = 'SELECT re.* '.
				' FROM regexps re '.
				' WHERE '.DBin_node('re.regexpid').
					' AND re.regexpid='.$_REQUEST['regexpid'];
			$regexp = DBfetch(DBSelect($sql));

			$frm_title .= ' ['.$regexp['name'].']';

			$rename			= $regexp['name'];
			$test_string	= $regexp['test_string'];

			$expressions = array();
			$sql = 'SELECT e.* '.
					' FROM expressions e '.
					' WHERE '.DBin_node('e.expressionid').
						' AND e.regexpid='.$regexp['regexpid'].
					' ORDER BY e.expression_type';

			$db_exps = DBselect($sql);
			while($exp = DBfetch($db_exps)){
				$expressions[] = $exp;
			}
		}
		else{
			$rename			= get_request('rename','');
			$test_string	= get_request('test_string','');

			$expressions 	= get_request('expressions',array());
		}

		$tblRE = new CTable('','nowrap');
		$tblRE->addStyle('border-left: 1px #AAA solid; border-right: 1px #AAA solid; background-color: #EEE; padding: 2px; padding-left: 6px; padding-right: 6px;');

		$tblRE->addRow(array(S_NAME, new CTextBox('rename', $rename, 60)));
		$tblRE->addRow(array(S_TEST_STRING, new CTextArea('test_string', $test_string, 66, 5)));

		$tabExp = new CTableInfo();

		$td1 = new CCol(S_EXPRESSION);
		$td1->addStyle('background-color: #CCC;');
		$td2 = new CCol(S_EXPECTED_RESULT);
		$td2->addStyle('background-color: #CCC;');
		$td3 = new CCol(S_RESULT);
		$td3->addStyle('background-color: #CCC;');

		$tabExp->setHeader(array($td1,$td2,$td3));

		$final_result = !empty($test_string);

		foreach($expressions as $id => $expression){

			$results = array();
			$paterns = array($expression['expression']);

			if(!empty($test_string)){
				if($expression['expression_type'] == EXPRESSION_TYPE_ANY_INCLUDED){
					$paterns = explode($expression['exp_delimiter'],$expression['expression']);
				}

				if(uint_in_array($expression['expression_type'], array(EXPRESSION_TYPE_TRUE,EXPRESSION_TYPE_FALSE))){
					if($expression['case_sensitive'])
//						$results[$id] = ereg($paterns[0],$test_string);
						$results[$id] = preg_match('/'.$paterns[0].'/',$test_string);
					else
//						$results[$id] = eregi($paterns[0],$test_string);
						$results[$id] = preg_match('/'.$paterns[0].'/i',$test_string);

					if($expression['expression_type'] == EXPRESSION_TYPE_TRUE)
						$final_result &= $results[$id];
					else
						$final_result &= !$results[$id];
				}
				else{
					$results[$id] = true;

					$tmp_result = false;
					if($expression['case_sensitive']){
						foreach($paterns as $pid => $patern){
							$tmp_result |= (zbx_strstr($test_string,$patern) !== false);
						}
					}
					else{
						foreach($paterns as $pid => $patern){
							$tmp_result |= (zbx_stristr($test_string,$patern) !== false);
						}
					}

					if(uint_in_array($expression['expression_type'], array(EXPRESSION_TYPE_INCLUDED, EXPRESSION_TYPE_ANY_INCLUDED)))
						$results[$id] &= $tmp_result;
					else if($expression['expression_type'] == EXPRESSION_TYPE_NOT_INCLUDED){
						$results[$id] &= !$tmp_result;
					}
					$final_result &= $results[$id];
				}
			}

			if(isset($results[$id]) && $results[$id])
				$exp_res = new CSpan(S_TRUE_BIG,'green bold');
			else
				$exp_res = new CSpan(S_FALSE_BIG,'red bold');

			$expec_result = expression_type2str($expression['expression_type']);
			if(EXPRESSION_TYPE_ANY_INCLUDED == $expression['expression_type'])
				$expec_result.=' ('.S_DELIMITER."='".$expression['exp_delimiter']."')";

			$tabExp->addRow(array(
						$expression['expression'],
						$expec_result,
						$exp_res
					));
		}

		$td = new CCol(S_COMBINED_RESULT,'bold');
		$td->setColSpan(2);

		if($final_result)
			$final_result = new CSpan(S_TRUE_BIG,'green bold');
		else
			$final_result = new CSpan(S_FALSE_BIG,'red bold');

		$tabExp->addRow(array(
					$td,
					$final_result
				));

		$tblRE->addRow(array(S_RESULT,$tabExp));

		$tblFoot = new CTableInfo(null);

		$td = new CCol(array(new CButton('save',S_SAVE)));
		$td->setColSpan(2);
		$td->addStyle('text-align: right;');

		$td->addItem(SPACE);
		$td->addItem(new CButton('test',S_TEST));

		if(isset($_REQUEST['regexpid'])){
			$td->addItem(SPACE);
			$td->addItem(new CButton('clone',S_CLONE));

			$td->addItem(SPACE);
			$td->addItem(new CButtonDelete(S_DELETE_REGULAR_EXPRESSION_Q,url_param('form').url_param('config').url_param('regexpid')));
		}

		$td->addItem(SPACE);
		$td->addItem(new CButtonCancel(url_param("regexpid")));

		$tblFoot->setFooter($td);

	return array($tblRE,$tblFoot);
	}

	function get_expressions_tab(){

		if(isset($_REQUEST['regexpid']) && !isset($_REQUEST["form_refresh"])){
			$expressions = array();
			$sql = 'SELECT e.* '.
					' FROM expressions e '.
					' WHERE '.DBin_node('e.expressionid').
						' AND e.regexpid='.$_REQUEST['regexpid'].
					' ORDER BY e.expression_type';

			$db_exps = DBselect($sql);
			while($exp = DBfetch($db_exps)){
				$expressions[] = $exp;
			}
		}
		else{
			$expressions 	= get_request('expressions',array());
		}

		$tblExp = new CTableInfo();
		$tblExp->setHeader(array(
				new CCheckBox('all_expressions',null,'checkAll("Regular expression","all_expressions","g_expressionid");'),
				S_EXPRESSION,
				S_EXPECTED_RESULT,
				S_CASE_SENSITIVE,
				S_EDIT
			));

//		zbx_rksort($timeperiods);
		foreach($expressions as $id => $expression){

			$exp_result = expression_type2str($expression['expression_type']);
			if(EXPRESSION_TYPE_ANY_INCLUDED == $expression['expression_type'])
				$exp_result.=' ('.S_DELIMITER."='".$expression['exp_delimiter']."')";

			$tblExp->addRow(array(
				new CCheckBox('g_expressionid[]', 'no', null, $id),
				$expression['expression'],
				$exp_result,
				$expression['case_sensitive']?S_YES:S_NO,
				new CButton('edit_expressionid['.$id.']',S_EDIT)
				));


			$tblExp->addItem(new Cvar('expressions['.$id.'][expression]',		$expression['expression']));
			$tblExp->addItem(new Cvar('expressions['.$id.'][expression_type]',	$expression['expression_type']));
			$tblExp->addItem(new Cvar('expressions['.$id.'][case_sensitive]',	$expression['case_sensitive']));
			$tblExp->addItem(new Cvar('expressions['.$id.'][exp_delimiter]',	$expression['exp_delimiter']));
		}

		$buttons = array();
		if(!isset($_REQUEST['new_expression'])){
			$buttons[] = new CButton('new_expression',S_NEW);
			$buttons[] = new CButton('delete_expression',S_DELETE);
		}

		$td = new CCol($buttons);
		$td->setAttribute('colspan','5');
		$td->setAttribute('style','text-align: right;');


		$tblExp->setFooter($td);

	return $tblExp;
	}

	function get_expression_form(){
		$tblExp = new CTable();

		/* init new_timeperiod variable */
		$new_expression = get_request('new_expression', array());

		if(is_array($new_expression) && isset($new_expression['id'])){
			$tblExp->addItem(new Cvar('new_expression[id]',$new_expression['id']));
		}

		if(!is_array($new_expression)){
			$new_expression = array();
		}

		if(!isset($new_expression['expression']))			$new_expression['expression']		= '';
		if(!isset($new_expression['expression_type']))		$new_expression['expression_type']	= EXPRESSION_TYPE_INCLUDED;
		if(!isset($new_expression['case_sensitive']))		$new_expression['case_sensitive']	= 0;
		if(!isset($new_expression['exp_delimiter']))		$new_expression['exp_delimiter']	= ',';

		$tblExp->addRow(array(S_EXPRESSION, new CTextBox('new_expression[expression]',$new_expression['expression'],60)));

		$cmbType = new CComboBox('new_expression[expression_type]',$new_expression['expression_type'],'javascript: submit();');
		$cmbType->addItem(EXPRESSION_TYPE_INCLUDED,expression_type2str(EXPRESSION_TYPE_INCLUDED));
		$cmbType->addItem(EXPRESSION_TYPE_ANY_INCLUDED,expression_type2str(EXPRESSION_TYPE_ANY_INCLUDED));
		$cmbType->addItem(EXPRESSION_TYPE_NOT_INCLUDED,expression_type2str(EXPRESSION_TYPE_NOT_INCLUDED));
		$cmbType->addItem(EXPRESSION_TYPE_TRUE,expression_type2str(EXPRESSION_TYPE_TRUE));
		$cmbType->addItem(EXPRESSION_TYPE_FALSE,expression_type2str(EXPRESSION_TYPE_FALSE));

		$tblExp->addRow(array(S_EXPRESSION_TYPE,$cmbType));

		if(EXPRESSION_TYPE_ANY_INCLUDED == $new_expression['expression_type']){
			$cmbDelimiter = new CComboBox('new_expression[exp_delimiter]',$new_expression['exp_delimiter']);
			$cmbDelimiter->addItem(',',',');
			$cmbDelimiter->addItem('.','.');
			$cmbDelimiter->addItem('/','/');

			$tblExp->addRow(array(S_DELIMITER,$cmbDelimiter));
		}
		else{
			$tblExp->addItem(new Cvar('new_expression[exp_delimiter]',$new_expression['exp_delimiter']));
		}

		$chkbCase = new CCheckBox('new_expression[case_sensitive]', $new_expression['case_sensitive'],null,1);

		$tblExp->addRow(array(S_CASE_SENSITIVE,$chkbCase));

		$tblExpFooter = new CTableInfo($tblExp);

		$oper_buttons = array();

		$oper_buttons[] = new CButton('add_expression',isset($new_expression['id'])?S_SAVE:S_ADD);
		$oper_buttons[] = new CButton('cancel_new_expression',S_CANCEL);

		$td = new CCol($oper_buttons);
		$td->setAttribute('colspan',2);
		$td->setAttribute('style','text-align: right;');

		$tblExpFooter->setFooter($td);
// end of condition list preparation
	return $tblExpFooter;
	}

/**
* returns Ctable object with host header
*
* {@source}
* @access public
* @static
* @version 1
*
* @param string $hostid
* @param array $elemnts [items, triggers, graphs, applications]
* @return object
*/
	function get_header_host_table($hostid, $elements){
		$header_host_opt = array(
			'hostids' => $hostid,
			'extendoutput' => 1,
			'templated_hosts' => 1,
		);
		if(str_in_array('items', $elements))
			$header_host_opt['select_items'] = 1;
		if(str_in_array('triggers', $elements))
			$header_host_opt['select_triggers'] = 1;
		if(str_in_array('graphs', $elements))
			$header_host_opt['select_graphs'] = 1;
		if(str_in_array('applications', $elements))
			$header_host_opt['select_applications'] = 1;

		$header_host = CHost::get($header_host_opt);
		$header_host = array_pop($header_host);


		$description = array();
		if($header_host['proxy_hostid']){
			$proxy = get_host_by_hostid($header_host['proxy_hostid']);
			$description[] = $proxy['host'].':';
		}
		$description[] = $header_host['host'];

		if(str_in_array('items', $elements)){
			$items = array(new CLink(S_ITEMS, 'items.php?hostid='.$header_host['hostid']),
				' ('.count($header_host['items']).')');
		}
		if(str_in_array('triggers', $elements)){
			$triggers = array(new CLink(S_TRIGGERS, 'triggers.php?hostid='.$header_host['hostid']),
				' ('.count($header_host['triggers']).')');
		}
		if(str_in_array('graphs', $elements)){
			$graphs = array(new CLink(S_GRAPHS, 'graphs.php?hostid='.$header_host['hostid']),
				' ('.count($header_host['graphs']).')');
		}
		if(str_in_array('applications', $elements)){
			$applications = array(new CLink(S_APPLICATIONS, 'applications.php?hostid='.$header_host['hostid']),
				' ('.count($header_host['applications']).')');
		}

		$tbl_header_host = new CTable();
		if($header_host['status'] == HOST_STATUS_TEMPLATE){

			$tbl_header_host->addRow(array(
				new CLink(bold(S_TEMPLATE_LIST), 'templates.php?templateid='.$header_host['hostid'].url_param('groupid')),
				(str_in_array('applications', $elements) ? $applications : null),
				(str_in_array('items', $elements) ? $items : null),
				(str_in_array('triggers', $elements) ? $triggers : null),
				(str_in_array('graphs', $elements) ? $graphs : null),
				array(bold(S_TEMPLATE.': '), $description)
			));
		}
		else{
			$dns = empty($header_host['dns']) ? '-' : $header_host['dns'];
			$ip = empty($header_host['ip']) ? '-' : $header_host['ip'];
			$port = empty($header_host['port']) ? '-' : $header_host['port'];
			if(1 == $header_host['useip'])
				$ip = bold($ip);
			else
				$dns = bold($dns);

			switch($header_host['status']){
				case HOST_STATUS_MONITORED:
					$status=new CSpan(S_MONITORED, 'off');
					break;
				case HOST_STATUS_NOT_MONITORED:
					$status=new CSpan(S_NOT_MONITORED, 'off');
					break;
				default:
					$status=S_UNKNOWN;
			}

			if($header_host['available'] == HOST_AVAILABLE_TRUE)
				$available=new CSpan(S_AVAILABLE,'off');
			else if($header_host['available'] == HOST_AVAILABLE_FALSE)
				$available=new CSpan(S_NOT_AVAILABLE,'on');
			else if($header_host['available'] == HOST_AVAILABLE_UNKNOWN)
				$available=new CSpan(S_UNKNOWN,'unknown');

			$tbl_header_host->addRow(array(
				new CLink(bold(S_HOST_LIST), 'hosts.php?hostid='.$header_host['hostid'].url_param('groupid')),
				(str_in_array('applications', $elements) ? $applications : null),
				(str_in_array('items', $elements) ? $items : null),
				(str_in_array('triggers', $elements) ? $triggers : null),
				(str_in_array('graphs', $elements) ? $graphs : null),
				array(bold(S_HOST.': '),$description),
				array(bold(S_DNS.': '), $dns),
				array(bold(S_IP.': '), $ip),
				array(bold(S_PORT.': '), $port),
				array(bold(S_STATUS.': '), $status),
				array(bold(S_AVAILABILITY.': '), $available)
			));
		}
		$tbl_header_host->setClass('infobox');

		return $tbl_header_host;
	}


	function get_macros_widget($hostid = null){

		if(isset($_REQUEST['form_refresh'])){
			$macros = get_request('macros', array());
		}
		else if($hostid > 0){
			$macros = CUserMacro::get(array('extendoutput' => 1, 'hostids' => $hostid));
		}
		else{
			$macros = array();
		}
		order_result($macros, 'macro');

		$macros_tbl = new CTable('', 'tablestripped');
		$macros_tbl->setOddRowClass('form_odd_row');
		$macros_tbl->setEvenRowClass('form_even_row');

		foreach($macros as $macroid => $macro){
			$macros_tbl->addItem(new CVar('macros['.$macro['macro'].'][macro]', $macro['macro']));
			$macros_tbl->addItem(new CVar('macros['.$macro['macro'].'][value]', $macro['value']));
			$macros_tbl->addRow(array(
				new CCheckBox('macros_rem['.$macro['macro'].']', 'no', null, $macro['macro']),
				$macro['macro'],
				SPACE.RARR.SPACE,
				$macro['value']
			));
		}

		$add_macro = array(
			S_NEW,
			new CTextBox('macro_new', get_request('macro_new', ''), 20),
			SPACE.RARR.SPACE,
			new CTextBox('value_new', get_request('value_new', ''), 20)
		);

		$macros_tbl->addRow($add_macro);


		$delete_btn = new CButton('macros_del', S_DELETE_SELECTED);
		if(count($macros) == 0){
			$delete_btn->setAttribute('disabled', 'disabled');
		}

		$footer = new CCol(array(new CButton('macro_add', S_ADD), SPACE, $delete_btn));
		$footer->setColSpan(4);

		$macros_tbl->setFooter($footer);

		$macros_wdgt = new CWidget();
		$macros_wdgt->setClass('header');
		$macros_wdgt->addHeader(S_MACROS);
		$macros_wdgt->addItem($macros_tbl);

		return $macros_wdgt;
	}
?>
