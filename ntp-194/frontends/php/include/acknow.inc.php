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
	function	get_last_event_by_triggerid($triggerid)
	{
		$event_data = DBfetch(DBselect('select * from events where objectid='.$triggerid.
			' and object='.EVENT_OBJECT_TRIGGER.' order by clock desc', 1));
		if(!$event_data)
			return FALSE;
		return $event_data;
	}

	function 	&get_acknowledges_by_eventid($eventid)
	{
		return DBselect("select * from acknowledges where eventid=$eventid");
	}

	function	add_acknowledge_coment($eventid, $userid, $message)
	{
		$result = set_event_acnowledged($eventid);
		if(!$result)
			return $result;

		$nodeid = id2nodeid($eventid);
		$acknowledgeid = get_dbid("acknowledges","acknowledgeid",$nodeid);

		$result =  DBexecute("insert into acknowledges (acknowledgeid,userid,eventid,clock,message)".
			" values ($acknowledgeid,$userid,$eventid,".time().",".zbx_dbstr($message).")");

		if(!$result)
			return $result;

		return $acknowledgeid;
	}

	function	set_event_acnowledged($eventid)
	{
		return DBexecute("update events set acknowledged=1 where eventid=$eventid");
	}
?>
