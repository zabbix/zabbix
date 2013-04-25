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
	return DBselect("SELECT a.*, u.alias FROM acknowledges a LEFT JOIN users u ON u.userid=a.userid  WHERE a.eventid=$eventid");
}

function make_acktab_by_eventid($eventid){
	$table = new CTableInfo();
	$table->SetHeader(array(S_TIME,S_USER, S_COMMENTS));

	$acks = get_acknowledges_by_eventid($eventid);

	while($ack = DBfetch($acks)){
		//$users = CUser::get(array('userids' => $ack['userid'],  'output' => API_OUTPUT_EXTEND));
		//$user = reset($users);

		$table->addRow(array(
			zbx_date2str(S_ACKNOWINC_BY_EVENTS_DATE_FORMAT,$ack['clock']),
			$ack['alias'],
			new CCol(zbx_nl2br($ack['message']),'wraptext')
		));
	}

return $table;
}
?>
