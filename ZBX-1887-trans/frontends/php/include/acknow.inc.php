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

function get_last_event_by_triggerid($triggerid){
	$event_data = DBfetch(DBselect('SELECT * '.
				' FROM events '.
				' WHERE objectid='.$triggerid.
					' and object='.EVENT_OBJECT_TRIGGER.
				' ORDER BY objectid desc, object desc, eventid desc', 1));
	if(!$event_data)
		return FALSE;
return $event_data;
}

function get_acknowledges_by_eventid($eventid){
	return DBselect("select * from acknowledges where eventid=$eventid");
}

function add_acknowledge_coment($eventid, $userid, $message){
	$result = set_event_acnowledged($eventid);
	if(!$result)
		return $result;

	$acknowledgeid = get_dbid("acknowledges","acknowledgeid");

	$result =  DBexecute("insert into acknowledges (acknowledgeid,userid,eventid,clock,message)".
		" values ($acknowledgeid,$userid,$eventid,".time().",".zbx_dbstr($message).")");

	if(!$result)
		return $result;

	return $acknowledgeid;
}

function set_event_acnowledged($eventid){
	return DBexecute("update events set acknowledged=1 where eventid=$eventid");
}

function make_acktab_by_eventid($eventid){
	$table = new CTableInfo();
	$table->SetHeader(array(S_TIME,S_USER, S_COMMENTS));

	$acks = get_acknowledges_by_eventid($eventid);

	while($ack = DBfetch($acks)){
		$user = CUser::get(array('userids' => $ack['userid'],  'extendoutput' => 1));
		$user = reset($user);

		$table->addRow(array(
			date(S_DATE_FORMAT_YMDHMS,$ack['clock']),
			$user['alias'],
			new CCol(zbx_nl2br($ack['message']),'wraptext')
		));
	}

return $table;
}
?>
