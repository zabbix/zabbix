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
	include "include/bulkloader.inc.php";
	$page["file"] = "bulkloader.php";
	$page["title"] = "S_BULKLOADER_MAIN";
	$fileuploaded=0;
	show_header($page["title"],0,0);
	if(!check_anyright("Default permission","U"))
	{
		show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
		show_page_footer();
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
				$sqlResult=DBselect("select distinct(hostid) from hosts where status=". HOST_STATUS_TEMPLATE .
					" and host=".zbx_dbstr($tmpHostTemplate).
					" and mod(hostid,100)=".$ZBX_CURNODEID;
				$row=DBfetch($sqlResult);
				if($row)
				{
					$hostTemplate=$row["hostid"];
				}
				else
				{
					$hostTemplate=0;
				}

				// get groups
				$groups = array();
				foreach(explode(',',rtrim(rtrim($tmpHostGroups," "),"\n")) as $group_name)
				{
					add_host_group($group_name);
					$groupid = DBfetch(DBselect("select groupid from groups where name=".zbx_dbstr($group_name).
					" and mod(groupid,100)=".$ZBX_CURNODEID;
					if(!$groupid) continue;
					array_push($groups,$groupid["groupid"]);
				}

				//  Now that we have all the values we need process them for this host
				$result=add_host($tmpHost,$hostPort,$hostStat,$hostUseIP,$tmpHostIP,$hostTemplate,'',$groups);
				show_messages($result,'Host added: '. $tmpHost,'Cannot add host: '. $tmpHost);;

				break;
			case "USER":
				list($tmpName,$tmpSurname,$tmpAlias,$tmpPasswd,$tmpURL,$tmpAutologout,$tmpLang,$tmpRefresh,$tmpUserGroups) = explode(",",$tmpField,9);
				$autologout=@iif($tmpAutologout==NULL,900,$tmpAutologout);
				$lang=@iif($tmpLang==NULL,'en_gb',$tmpLang);
				$refresh=@iif($tmpRefresh==NULL,30,$tmpRefresh);
				$passwd=@iif($tmpPasswd==NULL,md5($tmpAlias),md5($tmpPasswd));
				$result=@iif($tmpAlias==NULL,0,add_user($tmpName,$tmpSurname,$tmpAlias,$passwd,$tmpURL,$autologout,$lang,$refresh));
				show_messages($result, S_USER_ADDED .': '. $tmpAlias, S_CANNOT_ADD_USER .': '. $tmpAlias);
				$row=DBfetch(DBselect("select distinct(userid) from users where alias='$tmpAlias'"));
				$tmpUserID=$row["userid"];
				if($tmpUserID)
				{
					foreach(explode(',',rtrim(rtrim($tmpUserGroups," "),"\n")) as $tmpGroup)
					{
						add_user_group($tmpGroup,array($tmpUserID));
					}
				}
				break;
			case "PERM":
				echo "Importing User Permissions is not yet implemented";
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
	table_row(array(
		'User Entry Format.',
		'USER,&lt;User First Name&gt;,&lt;User Surname&gt;,&lt;Login Name&gt;,&lt;Password&gt;,&lt;URL&gt;,&lt;Auto Logout Time&gt;,&lt;Language&gt;,&lt;Screen Refresh Time&gt;,&lt;Host Group(s)&gt<BR>'.
		'&nbsp;&nbsp;&nbsp;<STRONG>USER</STRONG>: This is the command to tell the bulk loader that this entry is a User<BR>'.
		'&nbsp;&nbsp;&nbsp;<STRONG>User First Name</STRONG>:  This is the users first name<BR>'.
		'&nbsp;&nbsp;&nbsp;<STRONG>User Surname</STRONG>:  This is the users last name<BR>'.
		'&nbsp;&nbsp;&nbsp;<STRONG>Login Name</STRONG>:   This is the name the user will login with.  NOTE: User will not be created if this is blank.<BR>'.
		'&nbsp;&nbsp;&nbsp;<STRONG>Password</STRONG>: This is the password that user will login with. If blank, this will default to the login name.<BR>'.
		'&nbsp;&nbsp;&nbsp;<STRONG>URL</STRONG>: This is the URL the user is redirected to upon login.<BR>'.
		'&nbsp;&nbsp;&nbsp;<STRONG>Auto Logout Time</STRONG>: This is the number of seconds before an idle user will be logged off.  If blank, this will default to 900 seconds.<BR>'.
		'&nbsp;&nbsp;&nbsp;<STRONG>Language</STRONG>: This is the the language that that Zabbix will display to the user.  If blank, this will default to en_gb. Valid Choices are;<BR>'.
			'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&gt; en_gb -- English<BR>'.
			'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&gt; fr_fr -- French<BR>'.
			'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&gt; de_de -- German<BR>'.
			'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&gt; it_it -- Itallian<BR>'.
			'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&gt; ja_ja -- Japanese<BR>'.
			'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&gt; lv_lv -- Latvian<BR>'.
			'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&gt; ru_ru -- Russian<BR>'.
			'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&gt; sp_sp -- Spanish<BR>'.
			''.
		'&nbsp;&nbsp;&nbsp;<STRONG>Screen Refresh Time</STRONG>: This is the number of seconds before monitoring pages will refresh.  If blank, this will default to 30 seconds.<BR>'.
		'&nbsp;&nbsp;&nbsp;<STRONG>User Group(s)</STRONG>: This is a comma separated list of user groups that the user belongs to.  If this contains one or more groups that are not in the Zabbix Server, the groups will be created and user added to those groups.<BR>'.

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
	show_page_footer();

?>
