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
	 require_once "include/perm.inc.php";

?>
<?php
	function	check_right_on_discovery($permission)
	{
		global $USER_DETAILS;

		if( $USER_DETAILS['type'] >= USER_TYPE_ZABBIX_ADMIN )
		{
			if ( 0 == count(
					get_accessible_nodes_by_user($USER_DETAILS, $permission, null, PERM_RES_IDS_ARRAY, get_current_nodeid())
					))
				return true;
		}

		return false;
	}

	function	discovery_check_type2str($type_int)
	{
		$str_type[SVC_SSH]	= S_SSH;
		$str_type[SVC_LDAP]	= S_LDAP;
		$str_type[SVC_SMTP]	= S_SMTP;
		$str_type[SVC_FTP]	= S_FTP;
		$str_type[SVC_HTTP]	= S_HTTP;
		$str_type[SVC_POP]	= S_POP;
		$str_type[SVC_NNTP]	= S_NNTP;
		$str_type[SVC_IMAP]	= S_IMAP;
		$str_type[SVC_TCP]	= S_TCP;
		$str_type[SVC_AGENT]	= S_ZABBIX_AGENT;
		$str_type[SVC_SNMPv1]	= S_SNMPV1_AGENT;
		$str_type[SVC_SNMPv2]	= S_SNMPV2_AGENT;

		if(isset($str_type[$type_int]))
			return $str_type[$type_int];

		return S_UNKNOWN;
	}

	function	discovery_status2str($status_int)
	{
		switch($status_int)
		{
			case DRULE_STATUS_ACTIVE:	$status = S_ACTIVE;		break;
			case DRULE_STATUS_DISABLED:	$status = S_DISABLED;		break;
			default:
				$status = S_UNKNOWN;		break;
		}
		return $status;
	}

	function	discovery_status2style($status)
	{
		switch($status)
		{
			case DRULE_STATUS_ACTIVE:	$status = 'off';	break;
			case DRULE_STATUS_DISABLED:	$status = 'on';		break;
			default:
				$status = 'unknown';	break;
		}
		return $status;
	}

	function	discovery_object_status2str($status)
	{
		$str_stat[DOBJECT_STATUS_UP] = S_UP;
		$str_stat[DOBJECT_STATUS_DOWN] = S_DOWN;

		if(isset($str_stat[$status]))
			return $str_stat[$status];

		return S_UNKNOWN;
	}

	function	get_discovery_rule_by_druleid($druleid)
	{
		return DBfetch(DBselect('select * from drules where druleid='.$druleid));
	}

	function	set_discovery_rule_status($druleid, $status)
	{
		return DBexecute('update drules set status='.$status.' where druleid='.$druleid);
	}

	function	add_discovery_check($druleid, $type, $ports, $key, $snmp_community)
	{
		$dcheckid = get_dbid('dchecks', 'dcheckid');
		$result = DBexecute('insert into dchecks (dcheckid,druleid,type,ports,key_,snmp_community) '.
			' values ('.$dcheckid.','.$druleid.','.$type.','.zbx_dbstr($ports).','.
				zbx_dbstr($key).','.zbx_dbstr($snmp_community).')');

		if(!$result)
			return $result;

		return $dcheckid;
	}

	function	add_discovery_rule($name, $iprange, $delay, $status, $dchecks)
	{
		if( !validate_ip_range($iprange) )
		{
			error('Incorrect IP range.');
			return false;
		
		}

		$druleid = get_dbid('drules', 'druleid');
		$result = DBexecute('insert into drules (druleid,name,iprange,delay,status) '.
			' values ('.$druleid.','.zbx_dbstr($name).','.zbx_dbstr($iprange).','.$delay.','.$status.')');

		if($result)
		{
			DBexecute('delete from dchecks where druleid='.$druleid);
			if(isset($dchecks)) foreach($dchecks as $val)
				add_discovery_check($druleid,$val["type"],$val["ports"],$val["key"],$val["snmp_community"]);

			$result = $druleid;
		}

		return $result;
	}

	function	update_discovery_rule($druleid, $name, $iprange, $delay, $status, $dchecks)
	{
		if( !validate_ip_range($iprange) )
		{
			error('Incorrect IP range.');
			return false;
		
		}

		$result = DBexecute('update drules set name='.zbx_dbstr($name).',iprange='.zbx_dbstr($iprange).','.
			'delay='.$delay.',status='.$status.' where druleid='.$druleid);

		if($result)
		{
			DBexecute('delete from dchecks where druleid='.$druleid);
			if(isset($dchecks)) foreach($dchecks as $val)
				add_discovery_check($druleid,$val["type"],$val["ports"],$val["key"],$val["snmp_community"]);
		}
		return $result;
	}

	function	delete_discovery_rule($druleid)
	{
		if($result = DBexecute('delete from drules where druleid='.$druleid))
		{
			$result = DBexecute('delete from dchecks where druleid='.$druleid);
		}
		return $result;
	}
?>
