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

require_once('include/perm.inc.php');

?>
<?php
	function check_right_on_discovery($permission){
		global $USER_DETAILS;

		if( $USER_DETAILS['type'] >= USER_TYPE_ZABBIX_ADMIN ){
			if(count(get_accessible_nodes_by_user($USER_DETAILS, $permission, PERM_RES_IDS_ARRAY)))
				return true;
		}
	return false;
	}

	function svc_default_port($type_int){
		$port = 0;

		switch($type_int){
			case SVC_SSH: $port = '22'; break;
			case SVC_LDAP: $port = '389'; break;
			case SVC_SMTP: $port = '25'; break;
			case SVC_FTP: $port = '21'; break;
			case SVC_HTTP: $port = '80'; break;
			case SVC_POP: $port = '110'; break;
			case SVC_NNTP: $port = '119'; break;
			case SVC_IMAP: $port = '143'; break;
			case SVC_AGENT: $port = '10050'; break;
			case SVC_SNMPv1: $port = '161'; break;
			case SVC_SNMPv2: $port = '161'; break;
			case SVC_SNMPv3: $port = '161'; break;
		}
		return $port;
	}

	function discovery_check_type2str($type=null){
		$discovery_types = array(
			SVC_SSH => S_SSH,
			SVC_LDAP => S_LDAP,
			SVC_SMTP => S_SMTP,
			SVC_FTP => S_FTP,
			SVC_HTTP => S_HTTP,
			SVC_POP => S_POP,
			SVC_NNTP => S_NNTP,
			SVC_IMAP => S_IMAP,
			SVC_TCP => S_TCP,
			SVC_AGENT => S_ZABBIX_AGENT,
			SVC_SNMPv1 => S_SNMPV1_AGENT,
			SVC_SNMPv2 => S_SNMPV2_AGENT,
			SVC_SNMPv3 => S_SNMPV3_AGENT,
			SVC_ICMPPING => S_ICMPPING,
		);

		if(is_null($type)){
			order_result($discovery_types);
			return $discovery_types;
		}
		else if(isset($discovery_types[$type]))
			return $discovery_types[$type];
		else
			return S_UNKNOWN;
	}

	function discovery_check2str($type, $snmp_community, $key_, $port){
		$external_param = '';

		switch($type){
			case SVC_SNMPv1:
			case SVC_SNMPv2:
			case SVC_SNMPv3:
			case SVC_AGENT:
				$external_param = ' "'.$key_.'"';
				break;
		}
		$result = discovery_check_type2str($type);
		if((svc_default_port($type) != $port) || ($type == SVC_TCP))
			$result .= ' ('.$port.')';
		$result .= $external_param;

		return $result;
	}

	function discovery_port2str($type_int, $port){
		$port_def = svc_default_port($type_int);

		if($port != $port_def){
			return ' ('.$port.')';
		}

	return '';
	}

	function discovery_status2str($status_int){
		switch($status_int){
			case DRULE_STATUS_ACTIVE:	$status = S_ACTIVE;		break;
			case DRULE_STATUS_DISABLED:	$status = S_DISABLED;		break;
			default:
				$status = S_UNKNOWN;		break;
		}

	return $status;
	}

	function discovery_status2style($status){
		switch($status){
			case DRULE_STATUS_ACTIVE:	$status = 'off';	break;
			case DRULE_STATUS_DISABLED:	$status = 'on';		break;
			default:
				$status = 'unknown';	break;
		}

	return $status;
	}

	function discovery_object_status2str($status){
		$str_stat[DOBJECT_STATUS_UP] = S_UP;
		$str_stat[DOBJECT_STATUS_DOWN] = S_DOWN;
		$str_stat[DOBJECT_STATUS_DISCOVER] = S_DISCOVERED;
		$str_stat[DOBJECT_STATUS_LOST] = S_LOST;

		if(isset($str_stat[$status]))
			return $str_stat[$status];

		return S_UNKNOWN;
	}

	function get_discovery_rule_by_druleid($druleid){
		return DBfetch(DBselect('select * from drules where druleid='.$druleid));
	}

	function set_discovery_rule_status($druleid, $status){
		return DBexecute('update drules set status='.$status.' where druleid='.$druleid);
	}

	function add_discovery_check($druleid, $type, $ports, $key, $snmp_community,
			$snmpv3_securityname, $snmpv3_securitylevel, $snmpv3_authpassphrase, $snmpv3_privpassphrase)
	{
		// no need to store those items in DB if they will not be used
		if($snmpv3_securitylevel == ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV){
			$snmpv3_authpassphrase = '';
			$snmpv3_privpassphrase = '';
		}
		if($snmpv3_securitylevel == ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV){
			$snmpv3_privpassphrase = '';
		}

		$dcheckid = get_dbid('dchecks', 'dcheckid');
		$result = DBexecute('insert into dchecks (dcheckid,druleid,type,ports,key_,snmp_community'.
				',snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase) '.
				' values ('.$dcheckid.','.$druleid.','.$type.','.zbx_dbstr($ports).','.
				zbx_dbstr($key).','.zbx_dbstr($snmp_community).','.zbx_dbstr($snmpv3_securityname).','.
				$snmpv3_securitylevel.','.zbx_dbstr($snmpv3_authpassphrase).','.zbx_dbstr($snmpv3_privpassphrase).')');

		if(!$result)
			return $result;

		return $dcheckid;
	}

	function add_discovery_rule($proxy_hostid, $name, $iprange, $delay, $status, $dchecks, $uniqueness_criteria){
		if(!validate_ip_range($iprange)){
			error(S_INCORRECT_IP_RANGE);
			return false;
		}

		// checking to the duplicate of the name
		if(CDRule::exists(array('name' => $name))){
			error(S_DISCOVERY_RULE.SPACE.'['.$name.']'.SPACE.S_ALREADY_EXISTS_SMALL);
			return false;
		}

		$druleid = get_dbid('drules', 'druleid');
		$result = DBexecute('insert into drules (druleid,proxy_hostid,name,iprange,delay,status) '.
			' values ('.$druleid.','.$proxy_hostid.','.zbx_dbstr($name).','.zbx_dbstr($iprange).','.$delay.','.$status.')');

		if($result && isset($dchecks)){
			$unique_dcheckid = 0;
			foreach($dchecks as $id => $data){
				$data['dcheckid'] = add_discovery_check($druleid, $data['type'], $data['ports'], $data['key'],
						$data['snmp_community'], $data['snmpv3_securityname'], $data['snmpv3_securitylevel'],
						$data['snmpv3_authpassphrase'], $data['snmpv3_privpassphrase']);
				if ($uniqueness_criteria == $id && $data['dcheckid'])
					$unique_dcheckid = $data['dcheckid'];
			}
			DBexecute('UPDATE drules'.
					' SET unique_dcheckid='.$unique_dcheckid.
					' WHERE druleid='.$druleid);

			$result = $druleid;
		}

		return $result;
	}

	function update_discovery_rule($druleid, $proxy_hostid, $name, $iprange, $delay, $status, $dchecks,	$uniqueness_criteria, $dchecks_deleted){
		if( !validate_ip_range($iprange) ){
			error(S_INCORRECT_IP_RANGE);
			return false;

		}

		// checking to the duplicate of the name
		$drule = get_discovery_rule_by_druleid($druleid);
		if(strcmp($drule['name'], $name)){
			if(CDRule::exists(array('name' => $name))){
				error(S_DISCOVERY_RULE.SPACE.'['.$name.']'.SPACE.S_ALREADY_EXISTS_SMALL);
				return false;
			}
		}

		$result = DBexecute('update drules set proxy_hostid='.$proxy_hostid.',name='.zbx_dbstr($name).',iprange='.zbx_dbstr($iprange).','.
			'delay='.$delay.',status='.$status.' where druleid='.$druleid);

		if($result && isset($dchecks)){
			$unique_dcheckid = 0;
			foreach($dchecks as $id => $data){
				if(!isset($data['dcheckid'])){
					$data['dcheckid'] = add_discovery_check($druleid, $data['type'], $data['ports'], $data['key'],
							$data['snmp_community'], $data['snmpv3_securityname'], $data['snmpv3_securitylevel'],
							$data['snmpv3_authpassphrase'], $data['snmpv3_privpassphrase']);
				}
				if ($uniqueness_criteria == $id && $data['dcheckid'])
					$unique_dcheckid = $data['dcheckid'];
			}

			DBexecute('UPDATE drules'.
					' SET unique_dcheckid='.$unique_dcheckid.
					' WHERE druleid='.$druleid);
		}

		if($result && isset($dchecks_deleted) && !empty($dchecks_deleted))
			delete_discovery_check($dchecks_deleted);

	return $result;
	}

	function delete_discovery_check($dcheckids){
		$actionids = array();
// conditions
		$sql = 'SELECT DISTINCT actionid '.
				' FROM conditions '.
				' WHERE conditiontype='.CONDITION_TYPE_DCHECK.
					' AND '.DBcondition('value', $dcheckids, false, true);	// FIXED[POSIBLE value type violation]!!!

		$db_actions = DBselect($sql);
		while($db_action = DBfetch($db_actions))
			$actionids[] = $db_action['actionid'];

// disabling actions with deleted conditions
		if (!empty($actionids)){
			DBexecute('UPDATE actions '.
					' SET status='.ACTION_STATUS_DISABLED.
					' WHERE '.DBcondition('actionid', $actionids));

// delete action conditions
			DBexecute('DELETE FROM conditions '.
					' WHERE conditiontype='.CONDITION_TYPE_DCHECK.
					' AND '.DBcondition('value', $dcheckids, false, true));	// FIXED[POSIBLE value type violation]!!!
		}

		DBexecute('DELETE FROM dservices WHERE '.DBcondition('dcheckid', $dcheckids));

		DBexecute('DELETE FROM dchecks WHERE '.DBcondition('dcheckid', $dcheckids));
	}

	function delete_discovery_rule($druleid){
		$result = true;

		$actionids = array();
// conditions
		$sql = 'SELECT DISTINCT actionid '.
				' FROM conditions '.
				' WHERE conditiontype='.CONDITION_TYPE_DRULE.
					" AND value='$druleid'";

		$db_actions = DBselect($sql);
		while($db_action = DBfetch($db_actions))
			$actionids[] = $db_action['actionid'];

// disabling actions with deleted conditions
		if(!empty($actionids)){
			DBexecute('UPDATE actions '.
					' SET status='.ACTION_STATUS_DISABLED.
					' WHERE '.DBcondition('actionid', $actionids));

// delete action conditions
			DBexecute('DELETE FROM conditions '.
					' WHERE conditiontype='.CONDITION_TYPE_DRULE.
					" AND value='$druleid'");
		}

		if($result){
			$db_dhosts = DBselect('select dhostid from dhosts'.
					' where druleid='.$druleid.' and '.DBin_node('dhostid'));

			while ($result && ($db_dhost = DBfetch($db_dhosts)))
				$result = DBexecute('delete from dservices where'.
						' dhostid='.$db_dhost['dhostid']);
		}

		if ($result)
			$result = DBexecute('delete from dhosts where druleid='.$druleid);

		if ($result)
			$result = DBexecute('delete from dchecks where druleid='.$druleid);

		if ($result)
			$result = DBexecute('delete from drules where druleid='.$druleid);

		return $result;
	}
?>
