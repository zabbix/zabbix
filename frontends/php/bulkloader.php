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
	include "include/config.inc.php";
	include "include/forms.inc.php";
	$page["file"] = "bulkloader.php";
	$page["title"] = "S_BULKLOADER_MAIN";
	$fileuploaded=0;
	show_header($page["title"],0,0);
	if(!check_anyright("Default permission","U"))
		{
			show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
			show_footer();
			exit;
		}
	insert_confirm_javascript();
	
	if(isset($_FILES['uploadfile']))
	{
		$fileName     = $_FILES['uploadfile']['name'];
		$fileTmpName  = $_FILES['uploadfile']['tmp_name'];
		$listing=file($fileTmpName);
	}

	if(isset($_REQUEST["register"])&&($_FILES['uploadfile']['size']>0)&&($_REQUEST["register"]=="Import"))
	{
		foreach($listing as $element_number => $element)
		{
			list($inportFunc,$tmpField) = explode(",",$element,2);
			switch($inportFunc)
			{
			case "HOST":
				// HOST entry must be of the form;
				// Hostname,HostIP,HostPort,HostStatus,HostTemplate,HostZabbixServer,HostGroup(s)
				// If HostIP contains anything, it will be used as the IP address for this host.
				// If HostPort is empty, then the default port of 10050 is used.
				// HostStatus can be Template, Monitored, or Not Monitored.  Any other value is assumed to be Not Monitored.
				// HostTeample is the full name of the Template host.  If this is blank or if the host does not exist in the DB, the
				//    Bulk Loader assumes no template.
				// HostZabbixServer is the full name of the Zabbix server which will be responsible for monitoring this host.
				//    If this is blank or if the server does not exist in the DB, the Bulk Loader assumes that you have only a single
				//    Zabbix server.
				// HostGroup(s) contains the name of the group or groups, comma seperate, that this host belongs to. If this field is
				//    empty then the Bulk Loader assumes that this host belongs to no groups.  If this contains one or more groups
				//    that are not in the DB, the bulk loader will create new groups with the names defined in this field.
				
				list($tmpHost,$tmpHostIP,$tmpHostPort,$tmpHostStat,$tmpHostTemplate,$tmpHostServer,$tmpHostGroups) = explode(",",$tmpField,7);
				$hostName=@iif($tmpHost==NULL,'Unknown',$tnpHost);
				$hostUseIP=@iif($tmpHostIP==NULL,'off','on');
				$hostPort=@iif($tmpHostPort==NULL,10050,$tmpHostPort);

				//  Determine what type of host this is
				switch($tmpHostStat)
				{
				case "Template":
				case "template":
				case "3":
					$hostStat=3;
					break;
				case "Monitored":
				case "monitored":
				case "0":
					$hostStat=0;
					break;
				default:
					$hostStat=1;
				}

				//  Determine which template, if any this host is linked to
				$sql="select distinct(hostid) from hosts where status<>". HOST_STATUS_DELETED ." and host='$tmpHostTemplate'";
				$sqlResult=DBselect($sql);
				if(DBnum_rows($sqlResult)==1)
				{
					$row=DBfetch($sqlResult);
					$hostTemplate=$row["hostid"];
				}
				else
				{
					$hostTemplate=0;
				}

				//  Determine which Zabbix server this host defaults to
				$sql="select distinct(serverid) from servers where host='$tmpHostServer'";
				$sqlResult=DBselect($sql);
				if(DBnum_rows($sqlResult)==1)
				{
					$row=DBfetch($sqlResult);
					$hostServer=$row["serverid"];
				}
				else
				{
					$hostServer=1;
				}

				//  Determine which group(s) this host belongs to, create any group(s) necessary;
				$hostGroups=array();
				$groupnum=0;
//				$tmpGroupList=explode(',',rtrim(rtrim($tmpHostGroups," "),"\n"));
				foreach(explode(',',rtrim(rtrim($tmpHostGroups," "),"\n")) as $tmpGroup)
				{
					$groupnum++;
					$sqlResult=DBSelect("select distinct(groupid) from groups where name='$tmpGroup'");
					if(DBnum_rows($sqlResult)==0)
					{
						// Create new group
						$hostGroups=array_merge($hostGroups,array(add_group($tmpGroup)));
					}
					else
					{
						//  Found Existing Group;
						$row=DBFetch($sqlResult);
						$hostGroups=array_merge($hostGroups,array($row["groupid"]));
					}
				}

				//  Now that we have all the values we need process them for this host
				$result=add_host($tmpHost,$hostPort,$hostStat,$hostUseIP,$tmpHostIP,$hostTemplate,'',$hostGroups);
				show_messages($result,'Host added: '. $tmpHost,'Cannot add host: '. $tmpHost);;
				DBselect("update hosts set serverid=$hostServer where host='$tmpHost'");
				break;
			case "USER":
				echo "Importing Users is not yet implemented";
				break;
			case "ITEM":
				echo "Importing Items is not yet implemented";
				break;
			case "TRIG":
				echo "Importing Triggers is not yet implemented";
				break;
			case "ACTN":
				echo "Importing Actions is not yet implemented";
				break;
			case "SVC":
				echo "Importing IT Services is not yet implemented";
				break;
			default:
				echo "$importFunc is an unknown Function";
			}
		}

	}
	table_begin();
	table_row(array(
		"Currently, The bulk loader, only loads Host Entries."
		), 0);
	table_end();
	table_begin();
	table_row(array(
		'Host Entry Format.',
		'HOST,&lt;Hostname&gt;,&lt;Host IP&gt;,&lt;Host Port&gt;,&lt;Host Status&gt;,&lt;Template Host&gt;,&lt;Zabbix Server&gt;,&lt;Host Group(s)&gt<BR>'.
		'&nbsp;&nbsp;&nbsp;<STRONG>HOST</STRONG>: This is the command to tell the bulk loader that this entry is a host<BR>'.
		'&nbsp;&nbsp;&nbsp;<STRONG>Hostname</STRONG>:  This is the hostname you wish to use in the Zabbix UI<BR>'.
		'&nbsp;&nbsp;&nbsp;<STRONG>Host IP</STRONG>:   If Host IP contains anything, it will be used as the IP address for this host, otherwise the host will use Hostname.<BR>'.
		'&nbsp;&nbsp;&nbsp;<STRONG>Host Port</STRONG>: If HostPort contains anything it will be used as the port for the Zabbix agent, otherwise it will default to 10050.<BR>'.
		'&nbsp;&nbsp;&nbsp;<STRONG>Host Status</STRONG>: This field can be Template, Monitored, or Not Monitored.  Any thing else will be considered Not Monitored.<BR>'.
		'&nbsp;&nbsp;&nbsp;<STRONG>Template Host</STRONG>: This field contains the full name of the Template host, otherwise the host will not use a template.<BR>'.
		'&nbsp;&nbsp;&nbsp;<STRONG>Zabbix Server</STRONG>: This field contains the full name of the Zabbix server.  If it is blank or the server does not exist, it is assumed that this host in on the default Zabbix Server.<BR>'.
		'&nbsp;&nbsp;&nbsp;<STRONG>Host Group(s)</STRONG>: This field contains the name of the group or groups, comma seperated, that this host belongs to. If this field is empty then it it assumed that this host belongs to no groups.  If this contains one or more groups that are not in the Zabbix Server, the groups will be created and this host added to those groups.<BR>'.

		''
		), 2);
	table_end();
	table_begin();
	table_row(array(
		"<form method='post' enctype='multipart/form-data' action='". $page["file"] ."'><input type='hidden' name='MAX_FILE_SIZE' value='2000000' />",
		"Please select the CSV file you wish to import ",
		"<input class='button' type='file' name='uploadfile' />"
		), 5);
	table_end();
	table_begin();
	table_row(array(
		"<input class='button' type='submit' name='register' value='Import' onClick=\"return Confirm('Are you sure you wish to import this file?');\" />",
		"</form>"
		), 1);
	table_end();
	show_footer();

?>
