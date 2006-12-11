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
	class CServerInfo extends CTable
	{
		function CServerInfo()
		{
			parent::CTable(NULL,"server_info");
		}

		function BodyToString()
		{
			global $USER_DETAILS;
			global $_SERVER;

			$this->CleanItems();

			$status = get_status();

			if($status["zabbix_server"] == S_YES)
				$server = new CSpan(S_RUNNING,"off");
			else
				$server = new CSpan(S_NOT_RUNNING,"on");

			$header = new CCol("ZABBIX ".S_SERVER_INFO,"header");
			$this->AddRow($header);
			$this->AddRow("Updated: ".date("r",time()));
			$this->AddRow(new CCol(array("Refreshed every: ".$USER_DETAILS["refresh"]." sec ",
					"(",new CLink("refresh now","http://".$_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"],"action"),")")));
			$this->AddRow(S_NUMBER_OF_USERS_SHORT.": ".$status["users_count"]."(".$status["users_online"].")");
			$this->AddRow(new CCol(array("Logged in as ", new CLink($USER_DETAILS["alias"],"profile.php","action"))));
			$this->AddRow(new CCol(array(new CLink("ZABBIX server","http://www.zabbix.com","action")," is ",$server)),"status");
			$this->AddRow(S_VALUES_STORED.": ".$status["history_count"]);
			$this->AddRow(S_TRENDS_STORED.": ".$status["trends_count"]);
			$this->AddRow(new CCol(array(S_NUMBER_OF_HOSTS_SHORT.": ".$status["hosts_count"]."(",
				new CSpan($status["hosts_count_monitored"],"off"),"/",
				new CSpan($status["hosts_count_not_monitored"],"on"),"/",
				new CSpan($status["hosts_count_template"],"unknown"),"/",
				$status["hosts_count_deleted"].")")));
			$this->AddRow(new CCol(array(S_NUMBER_OF_ITEMS_SHORT.": ".$status["items_count"]."(",
				new CSpan($status["items_count_monitored"],"off"),"/",
				new CSpan($status["items_count_disabled"],"on"),"/",
				new CSpan($status["items_count_not_supported"],"unknown"),
				")[".$status["items_count_trapper"]."]")));
			$this->AddRow(new CCol(array(S_NUMBER_OF_TRIGGERS_SHORT.": ".	$status["triggers_count"].
				"(".$status["triggers_count_enabled"]."/".$status["triggers_count_disabled"].")"."[",
				new CSpan($status["triggers_count_on"],"on"),"/",
				new CSpan($status["triggers_count_unknown"],"unknown"),"/",
				new CSpan($status["triggers_count_off"],"off"),"]"
				)));
			$this->AddRow(S_NUMBER_OF_ALARMS.": ".$status["events_count"]);
			$this->AddRow(S_NUMBER_OF_ALERTS.": ".$status["alerts_count"]);

			return parent::BodyToString();
		}
	}
?>
