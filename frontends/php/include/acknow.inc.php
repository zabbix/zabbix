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
	function	get_last_alarm_by_triggerid($triggerid)
	{
		$db_alarms = DBselect("select * from alarms where triggerid=$triggerid".
			" order by clock desc");
		$row=DBfetch($db_alarms,1);
		if(!$row)	return FALSE;
		return $row;
	}

	function 	get_acknowledges_by_alarmid($alarmid)
	{
		return DBselect("select * from acknowledges where alarmid=$alarmid");
	}

	function	add_acknowledge_coment($alarmid, $userid, $message)
	{
		$result = set_alarm_acnowledged($alarmid);
		if(!$result)
			return $result;

		$acknowledgeid = get_dbid("acknowledges","acknowledgeid");

		$result =  DBexecute("insert into acknowledges (acknowledgeid,userid,alarmid,clock,message)".
			" values ($acknowledgeid,$userid,$alarmid,".time().",".zbx_dbstr($message).")");

		if(!$result)
			return $result;

		return $acknowledgeid;
	}

	function	set_alarm_acnowledged($alarmid)
	{
		return DBexecute("update alarms set acknowledged=1 where alarmid=$alarmid");
	}
?>
