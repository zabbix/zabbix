<?php
/*
** Zabbix
** Copyright (C) 2000,2001,2002,2003,2004 Alexei Vladishev
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
	include 	"include/defines.inc.php";
	include 	"include/db.inc.php";

	$USER_DETAILS	="";
	$ERROR_MSG	="";

	function	nbsp($str)
	{
		return str_replace(" ","&nbsp;",$str);;
	}

	function url1_param($parameter)
	{
		global $HTTP_GET_VARS;
	
		if(isset($HTTP_GET_VARS[$parameter]))
		{
			return "$parameter=".$HTTP_GET_VARS[$parameter];
		}
		else
		{
			return "";
		}
	}

	function url_param($parameter)
	{
		global $HTTP_GET_VARS;
	
		if(isset($HTTP_GET_VARS[$parameter]))
		{
			return "&$parameter=".$HTTP_GET_VARS[$parameter];
		}
		else
		{
			return "";
		}
	}

	function getmicrotime()
	{
		list($usec, $sec) = explode(" ",microtime()); 
		return ((float)$usec + (float)$sec); 
	} 

	function	iif($bool,$a,$b)
	{
		if($bool)
		{
			return $a;
		}
		else
		{
			return $b;
		}
	}

	function	convert_units($value,$units,$multiplier)
	{
// Special processing for seconds
		if($units=="s")
		{
			$ret="";

			$t=floor($value/(365*24*3600));
			if($t>0)
			{
				$ret=$t."y";
				$value=$value-$t*(365*24*3600);
			}
			$t=floor($value/(30*24*3600));
			if($t>0)
			{
				$ret=$ret.$t."m";
				$value=$value-$t*(30*24*3600);
			}
			$t=floor($value/(24*3600));
			if($t>0)
			{
				$ret=$ret.$t."d";
				$value=$value-$t*(24*3600);
			}
			$t=floor($value/(3600));
			if($t>0)
			{
				$ret=$ret.$t."h";
				$value=$value-$t*(3600);
			}
			$t=floor($value/(60));
			if($t>0)
			{
				$ret=$ret.$t."m";
				$value=$value-$t*(60);
			}
			$ret=$ret.$value."s";
		
			return $ret;	
		}

		$u="";

		$value=$value*pow(1024,(int)$multiplier);

		if($units=="")
		{
			if(round($value)==$value)
			{
				return sprintf("%.0f",$value);
			}
			else
			{
				return sprintf("%.2f",$value);
			}
		}

		$abs=abs($value);

		if($abs<1024)
		{
			$u="";
		}
		else if($abs<1024*1024)
		{
			$u="K";
			$value=$value/1024;
		}
		else if($abs<1024*1024*1024)
		{
			$u="M";
			$value=$value/(1024*1024);
		}
		else
		{
			$u="G";
			$value=$value/(1024*1024*1024);
		}

		if(round($value)==$value)
		{
			$s=sprintf("%.0f",$value);
		}
		else
		{
			$s=sprintf("%.2f",$value);
		}

		return "$s $u$units";
	}
	
	function	get_media_count_by_userid($userid)
	{
		$sql="select count(*) as cnt from media where userid=$userid";
		$result=DBselect($sql);
		$row=DBfetch($result);
		return $row["cnt"]; 
	}
	
	function	get_action_count_by_triggerid($triggerid)
	{
		$cnt=0;

		$sql="select count(*) as cnt from actions where triggerid=$triggerid and scope=0";
		$result=DBselect($sql);
		$row=DBfetch($result);

		$cnt=$cnt+$row["cnt"];

		$sql="select count(*) as cnt from actions where scope=2";
		$result=DBselect($sql);
		$row=DBfetch($result);

		$cnt=$cnt+$row["cnt"];

		$sql="select distinct h.hostid from hosts h,items i,triggers t,functions f where h.hostid=i.hostid and i.itemid=f.itemid and f.triggerid=t.triggerid and t.triggerid=$triggerid";
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			$sql="select count(*) as cnt from actions a,hosts h,items i,triggers t,functions f where h.hostid=i.hostid and i.itemid=f.itemid and f.triggerid=t.triggerid and a.triggerid=".$row["hostid"]." and a.scope=1";
			$result2=DBselect($sql);
			$row2=DBfetch($result2);
			$cnt=$cnt+$row2["cnt"];
		}

		return $cnt; 
	}

	function	check_anyright($right,$permission)
	{
		global $USER_DETAILS;

		$sql="select permission from rights where name='Default permission' and userid=".$USER_DETAILS["userid"];
		$result=DBselect($sql);

		$default_permission="H";
		if(DBnum_rows($result)>0)
		{
			$default_permission="";
			while($row=DBfetch($result))
			{
				$default_permission=$default_permission.$row["permission"];
			}
		}
# default_permission

		$sql="select permission from rights where name='$right' and id!=0 and userid=".$USER_DETAILS["userid"];
		$result=DBselect($sql);

		$all_permissions="";
		if(DBnum_rows($result)>0)
		{
			while($row=DBfetch($result))
			{
				$all_permissions=$all_permissions.$row["permission"];
			}
		}
# all_permissions

//		echo "$all_permissions|$default_permission<br>";

		switch ($permission) {
			case 'A':
				if(strstr($all_permissions,"A"))
				{
					return 1;
				}
				if(strstr($default_permission,"H"))
				{
					return 0;
				}
				else if(strstr($default_permission,"A"))
				{
					return 1;
				}
				break;
			case 'R':
				if(strstr($all_permissions,"R"))
				{
					return 1;
				}
				else if(strstr($all_permissions,"U"))
				{
					return 1;
				}
				if(strstr($default_permission,"H"))
				{
					return 0;
				}
				else if(strstr($default_permission,"R"))
				{
					return 1;
				}
				else if(strstr($default_permission,"U"))
				{
					return 1;
				}
				break;
			case 'U':
				if(strstr($all_permissions,"U"))
				{
					return 1;
				}
				if(strstr($default_permission,"H"))
				{
					return 0;
				}
				else if(strstr($default_permission,"U"))
				{
					return 1;
				}
				break;
			default:
				return 0;
		}
		return 0;
	}

	function	check_right($right,$permission,$id)
	{
		global $USER_DETAILS;

		$sql="select permission from rights where name='Default permission' and userid=".$USER_DETAILS["userid"];
		$result=DBselect($sql);

		$default_permission="H";
		if(DBnum_rows($result)>0)
		{
			$default_permission="";
			while($row=DBfetch($result))
			{
				$default_permission=$default_permission.$row["permission"];
			}
		}
# default_permission

		$sql="select permission from rights where name='$right' and id=0 and userid=".$USER_DETAILS["userid"];
		$result=DBselect($sql);

		$group_permission="";
		if(DBnum_rows($result)>0)
		{
			while($row=DBfetch($result))
			{
				$group_permission=$group_permission.$row["permission"];
			}
		}
# group_permission

		$id_permission="";
		if($id!=0)
		{
			$sql="select permission from rights where name='$right' and id=$id and userid=".$USER_DETAILS["userid"];
			$result=DBselect($sql);
			if(DBnum_rows($result)>0)
			{
				while($row=DBfetch($result))
				{
					$id_permission=$id_permission.$row["permission"];
				}
			}
		}
# id_permission
//		echo "$id_permission|$group_permission|$default_permission<br>";

		switch ($permission) {
			case 'A':
				if(strstr($id_permission,"H"))
				{
					return 0;
				}
				else if(strstr($id_permission,"A"))
				{
					return 1;
				}
				if(strstr($group_permission,"H"))
				{
					return 0;
				}
				else if(strstr($group_permission,"A"))
				{
					return 1;
				}
				if(strstr($default_permission,"H"))
				{
					return 0;
				}
				else if(strstr($default_permission,"A"))
				{
					return 1;
				}
				break;
			case 'R':
				if(strstr($id_permission,"H"))
				{
					return 0;
				}
				else if(strstr($id_permission,"R"))
				{
					return 1;
				}
				else if(strstr($id_permission,"U"))
				{
					return 1;
				}
				if(strstr($group_permission,"H"))
				{
					return 0;
				}
				else if(strstr($group_permission,"R"))
				{
					return 1;
				}
				else if(strstr($group_permission,"U"))
				{
					return 1;
				}
				if(strstr($default_permission,"H"))
				{
					return 0;
				}
				else if(strstr($default_permission,"R"))
				{
					return 1;
				}
				else if(strstr($default_permission,"U"))
				{
					return 1;
				}
				break;
			case 'U':
				if(strstr($id_permission,"H"))
				{
					return 0;
				}
				else if(strstr($id_permission,"U"))
				{
					return 1;
				}
				if(strstr($group_permission,"H"))
				{
					return 0;
				}
				else if(strstr($group_permission,"U"))
				{
					return 1;
				}
				if(strstr($default_permission,"H"))
				{
					return 0;
				}
				else if(strstr($default_permission,"U"))
				{
					return 1;
				}
				break;
			default:
				return 0;
		}
		return 0;
	}


/*	function	check_right($right,$permission,$id)
	{
		global $USER_DETAILS;

		if($id!=0)
		{
			$sql="select * from rights where name='$right' and permission in ('H') and id=$id and userid=".$USER_DETAILS["userid"];
			$result=DBselect($sql);
			if(DBnum_rows($result)>0)
			{
				return	0;
			}
		}

		$sql="select permission from rights where name='Default permission' and userid=".$USER_DETAILS["userid"];
		$result=DBselect($sql);

		$default_permission="H";
		if(DBnum_rows($result)>0)
		{
			$default_permission="";
			while($row=DBfetch($result))
			{
				$default_permission=$default_permission.$row["permission"];
			}
		}

		if($permission=='R')
		{
			$cond="'R','U'";
		}
		else
		{
			$cond="'".$permission."'";
		}

		$sql="select * from rights where name='$right' and permission in ($cond) and (id=$id or id=0) and userid=".$USER_DETAILS["userid"];
//		echo $sql;

		$result=DBselect($sql);

		if(DBnum_rows($result)>0)
		{
			return	1;
		}
		else
		{
			if(strstr($default_permission,"A")&&($permission=="A"))
			{
				return 1;
			}
			if(strstr($default_permission,"R")&&($permission=="R"))
			{
				return 1;
			}
			if(strstr($default_permission,"U")&&($permission=="R"))
			{
				return 1;
			}
			if(strstr($default_permission,"U")&&($permission=="U"))
			{
				return 1;
			}
			return	0;
		}
	}
*/

	function	check_right_on_trigger($permission,$triggerid)
	{
                $sql="select distinct h.hostid from functions f,items i,hosts h
where h.hostid=i.hostid and i.itemid=f.itemid and f.triggerid=$triggerid";
                $result=DBselect($sql);
                $ok=0;
		while($row=DBfetch($result))
		{
			if(check_right("Host",$permission,$row["hostid"]))
			{
				$ok=1;
			}
		}
		return	$ok;
	}

	function	get_scope_description($scope)
	{
		$desc="Unknown";
		if($scope==2)
		{
			$desc="All";
		}
		elseif($scope==1)
		{
			$desc="Host";
		}
		elseif($scope==0)
		{
			$desc="Trigger";
		}
		return $desc;
	}

	function	get_service_status_description($status)
	{
		$desc="<font color=\"#00AA00\">OK</a>";
		if($status==5)
		{
			$desc="<font color=\"#FF0000\">Disaster</a>";
		}
		elseif($status==4)
		{
			$desc="<font color=\"#FF8888\">Serious&nbsp;problem</a>";
		}
		elseif($status==3)
		{
			$desc="<font color=\"#AA0000\">Average&nbsp;problem</a>";
		}
		elseif($status==2)
		{
			$desc="<font color=\"#AA5555\">Minor&nbsp;problem</a>";
		}
		elseif($status==1)
		{
			$desc="<font color=\"#00AA00\">OK</a>";
		}
		return $desc;
	}


//	The hash has form <md5sum of triggerid>,<sum of priorities>
	function	calc_trigger_hash()
	{
		$priorities=0;
		for($i=0;$i<=5;$i++)
		{
	        	$result=DBselect("select count(*) from triggers t,hosts h,items i,functions f  where t.value=1 and f.itemid=i.itemid and h.hostid=i.hostid and t.triggerid=f.triggerid and i.status=0 and t.priority=$i");
//			$priorities+=(1000^$i)*DBget_field($result,0,0);
			$priorities+=pow(100,$i)*DBget_field($result,0,0);
//			echo "$i $priorities ",DBget_field($result,0,0),"<br>";
//			echo pow(100,5)*13;
		}
		$triggerids="";
	       	$result=DBselect("select t.triggerid from triggers t,hosts h,items i,functions f  where t.value=1 and f.itemid=i.itemid and h.hostid=i.hostid and t.triggerid=f.triggerid and i.status=0");
		for($i=0;$i<DBnum_rows($result);$i++)
		{
			$triggerids="$triggerids,".DBget_field($result,$i,0);
		}
		$md5sum=md5($triggerids);

		return	"$priorities,$md5sum";
	}

	function	get_group_by_groupid($groupid)
	{
		global	$ERROR_MSG;

		$sql="select * from groups where groupid=$groupid"; 
		$result=DBselect($sql);
		if(DBnum_rows($result) == 1)
		{
			return	DBfetch($result);	
		}
		else
		{
			$ERROR_MSG="No groups with groupid=[$groupid]";
		}
		return	$result;
	}

	function	get_action_by_actionid($actionid)
	{
		global	$ERROR_MSG;

		$sql="select * from actions where actionid=$actionid"; 
		$result=DBselect($sql);
		if(DBnum_rows($result) == 1)
		{
			return	DBfetch($result);	
		}
		else
		{
			$ERROR_MSG="No action with actionid=[$actionid]";
		}
		return	$result;
	}

	function	get_user_by_userid($userid)
	{
		global	$ERROR_MSG;

		$sql="select * from users where userid=$userid"; 
		$result=DBselect($sql);
		if(DBnum_rows($result) == 1)
		{
			return	DBfetch($result);	
		}
		else
		{
			$ERROR_MSG="No user with itemid=[$userid]";
		}
		return	$result;
	}

	function	get_usergroup_by_usrgrpid($usrgrpid)
	{
		global	$ERROR_MSG;

		$result=DBselect("select usrgrpid,name from usrgrp where usrgrpid=$usrgrpid"); 
		if(DBnum_rows($result) == 1)
		{
			return	DBfetch($result);	
		}
		else
		{
			$ERROR_MSG="No user group with usrgrpid=[$usrgrpid]";
		}
		return	$result;
	}

	function	get_screen_by_screenid($screenid)
	{
		global	$ERROR_MSG;

		$sql="select * from screens where screenid=$screenid"; 
		$result=DBselect($sql);
		if(DBnum_rows($result) == 1)
		{
			return	DBfetch($result);	
		}
		else
		{
			$ERROR_MSG="No screen with screenid=[$screenid]";
		}
		return	$result;
	}

	function	get_map_by_sysmapid($sysmapid)
	{
		global	$ERROR_MSG;

		$sql="select * from sysmaps where sysmapid=$sysmapid"; 
		$result=DBselect($sql);
		if(DBnum_rows($result) == 1)
		{
			return	DBfetch($result);	
		}
		else
		{
			$ERROR_MSG="No system map with sysmapid=[$sysmapid]";
		}
		return	$result;
	}


	function	get_graph_by_graphid($graphid)
	{
		global	$ERROR_MSG;

		$sql="select * from graphs where graphid=$graphid"; 
		$result=DBselect($sql);
		if(DBnum_rows($result) == 1)
		{
			return	DBfetch($result);	
		}
		else
		{
			$ERROR_MSG="No graph with graphid=[$graphid]";
		}
		return	$graph;
	}

	function	get_item_by_itemid($itemid)
	{
		global	$ERROR_MSG;

		$sql="select * from items where itemid=$itemid"; 
		$result=DBselect($sql);
		if(DBnum_rows($result) == 1)
		{
			return	DBfetch($result);	
		}
		else
		{
			$ERROR_MSG="No item with itemid=[$itemid]";
		}
		return	$item;
	}

	function	get_function_by_functionid($functionid)
	{
		global	$ERROR_MSG;

		$sql="select * from functions where functionid=$functionid"; 
		$result=DBselect($sql);
		if(DBnum_rows($result) == 1)
		{
			return	DBfetch($result);
		}
		else
		{
			$ERROR_MSG="No function with functionid=[$functionid]";
		}
		return	$item;
	}

	function	get_trigger_by_triggerid($triggerid)
	{
		global	$ERROR_MSG;

		$sql="select triggerid,expression,description,status,priority,lastchange,dep_level,comments,url,value from triggers where triggerid=$triggerid";
		$result=DBselect($sql);
		if(DBnum_rows($result) == 1)
		{
			return	DBfetch($result);	
		}
		else
		{
			$ERROR_MSG="No trigger with triggerid=[$triggerid]";
		}
		return	$trigger;
	}

	function	select_config()
	{
		global	$ERROR_MSG;

//		$sql="select smtp_server,smtp_helo,smtp_email,alarm_history,alert_history from config";
		$sql="select alarm_history,alert_history from config";
		$result=DBselect($sql);

		if(DBnum_rows($result) == 1)
		{
			$config["alarm_history"]=DBget_field($result,0,0);
			$config["alert_history"]=DBget_field($result,0,1);
//			$config["smtp_server"]=DBget_field($result,0,0);
//			$config["smtp_helo"]=DBget_field($result,0,1);
//			$config["smtp_email"]=DBget_field($result,0,2);
//			$config["alarm_history"]=DBget_field($result,0,3);
//			$config["alert_history"]=DBget_field($result,0,4);
		}
		else
		{
			$ERROR_MSG="Unable to select configuration";
		}
		return	$config;
	}

	function	get_host_by_hostid($hostid)
	{
		global	$ERROR_MSG;

		$sql="select hostid,host,useip,ip,port,status from hosts where hostid=$hostid";
		$result=DBselect($sql);
		if(DBnum_rows($result) == 1)
		{
			$host["hostid"]=DBget_field($result,0,0);
			$host["host"]=DBget_field($result,0,1);
			$host["useip"]=DBget_field($result,0,2);
			$host["ip"]=DBget_field($result,0,3);
			$host["port"]=DBget_field($result,0,4);
			$host["status"]=DBget_field($result,0,5);
		}
		else
		{
			$ERROR_MSG="No host with hostid=[$hostid]";
		}
		return	$host;
	}

	function	get_num_of_service_childs($serviceid)
	{
		global	$ERROR_MSG;

		$sql="select count(*) from services_links where serviceupid=$serviceid";
		$result=DBselect($sql);
		return	DBget_field($result,0,0);
	}

	function	get_service_by_serviceid($serviceid)
	{
		global	$ERROR_MSG;

		$sql="select * from services where serviceid=$serviceid";
		$result=DBselect($sql);
		if(Dbnum_rows($result) == 1)
		{
			return	DBfetch($result);
		}
		else
		{
			$ERROR_MSG="No service with serviceid=[$serviceid]";
		}
		return	FALSE;
	}

	function	show_messages($bool,$msg,$errmsg)
	{
		global	$ERROR_MSG;

		if(!$bool)
		{
			$msg="ERROR:".$errmsg;
			$color="#AA0000";
		}
		else
		{
			$color="#223344";
		}
		echo "<center>";
//		echo "<font size=+1 color='$color'>";
		echo "<font color='$color'>";
		if($ERROR_MSG=="")
		{
			echo "<b>[$msg]</b>";
		}
		else
		{
			echo "<b>[$msg. $ERROR_MSG]</b>";
		}
		echo "</font>";
		echo "</center><br>";
	}

	function	show_message($msg)
	{
		show_messages(TRUE,$msg,'');
	}

	function	show_error_message($msg)
	{
		show_messages(FALSE,'',$msg);
	}

	function	validate_float($str)
	{
//		echo "Validating float:$str<br>";
		if (eregi('^([0-9]+)((\.)?)([0-9]*[KMG]{0,1})$', $str, &$arr)) 
		{
			return 0;
		}
		else
		{
			return -1;
		}
	}

// Does expression match server:key.function(param) ?
	function	validate_simple_expression($expression)
	{
		global	$ERROR_MSG;

//		echo "Validating simple:$expression<br>";
// Before str()
// 		if (eregi('^\{([0-9a-zA-Z[.-.]\_\.]+)\:([]\[0-9a-zA-Z\_\/\.\,]+)\.((diff)|(min)|(max)|(last)|(prev))\(([0-9\.]+)\)\}$', $expression, &$arr)) 
//		if (eregi('^\{([0-9a-zA-Z[.-.]\_\.]+)\:([]\[0-9a-zA-Z\_\/\.\,]+)\.((diff)|(min)|(max)|(last)|(prev)|(str))\(([0-9a-zA-Z\.\_\/\,]+)\)\}$', $expression, &$arr)) 
 		if (eregi('^\{([0-9a-zA-Z\_\.-]+)\:([]\[0-9a-zA-Z\_\/\.\,\:\(\) -]+)\.([a-z]{3,9})\(([0-9a-zA-Z\_\/\.\,]+)\)\}$', $expression, &$arr)) 
		{
			$host=$arr[1];
			$key=$arr[2];
			$function=$arr[3];
			$parameter=$arr[4];

//			echo $host,"<br>";
//			echo $key,"<br>";
//			echo $function,"<br>";
//			echo $parameter,"<br>";

			$sql="select count(*) from hosts h,items i where h.host='$host' and i.key_='$key' and h.hostid=i.hostid";
			$result=DBselect($sql);
			if(DBget_field($result,0,0)!=1)
			{
				$ERROR_MSG="No such host ($host) or monitored parameter ($key)";
				return -1;
			}

			if(	($function!="last")&&
				($function!="diff")&&
				($function!="min") &&
				($function!="max") &&
				($function!="avg") &&
				($function!="sum") &&
				($function!="count") &&
				($function!="prev")&&
				($function!="delta")&&
				($function!="change")&&
				($function!="abschange")&&
				($function!="nodata")&&
				($function!="time")&&
				($function!="date")&&
				($function!="now")&&
				($function!="str"))
			{
				$ERROR_MSG="Unknown function [$function]";
				return -1;
			}


			if(( $function!="str") && (validate_float($parameter)!=0) )
			{
				$ERROR_MSG="[$parameter] is not a float";
				return -1;
			}
		}
		else
		{
			$ERROR_MSG="Expression [$expression] does not match to [server:key.func(param)]";
			return -1;
		}
		return 0;
	}

	function	validate_expression($expression)
	{
		global	$ERROR_MSG;

//		echo "Validating expression: $expression<br>";

		$ok=0;
// Replace all {server:key.function(param)} with 0
		while($ok==0)
		{
//			echo "Expression:$expression<br>";
			$arr="";
			if (eregi('^((.)*)(\{((.)*)\})((.)*)$', $expression, &$arr)) 
			{
//				for($i=0;$i<20;$i++)
//				{
//					if($arr[$i])
//						echo "  $i: ",$arr[$i],"<br>";
//				}
				if(validate_simple_expression($arr[3])!=0)
				{
					return -1;
				}
				$expression=$arr[1]."0".$arr[6];
	                }
			else
			{
				$ok=1;
			}
		}
//		echo "Result:$expression<br><hr>";

		$ok=0;
		while($ok==0)
		{
// 	Replace all <float> <sign> <float> <K|M|G> with 0
//			echo "Expression:$expression<br>";
			$arr="";
			if (eregi('^((.)*)([0-9\.]+[A-Z]{0,1})([\&\|\>\<\=\+\-\*\/\#]{1})([0-9\.]+[A-Z]{0,1})((.)*)$', $expression, &$arr)) 
			{
//				echo "OK<br>";
//				for($i=0;$i<50;$i++)
//				{
//					if($arr[$i]!="")
//						echo "  $i: ",$arr[$i],"<br>";
//				}
				if(validate_float($arr[3])!=0)
				{
					$ERROR_MSG="[".$arr[3]."] is not a float";
					return -1;
				}
				if(validate_float($arr[5])!=0)
				{
					$ERROR_MSG="[".$arr[5]."] is not a float";
					return -1;
				}
				$expression=$arr[1]."(0)".$arr[6];
	                }
			else
			{
				$ok=1;
			}


// 	Replace all (float) with 0
//			echo "Expression2:[$expression]<br>";
			$arr="";
			if (eregi('^((.)*)(\(([0-9\.]+)\))((.)*)$', $expression, &$arr)) 
			{
//				echo "OK<br>";
//				for($i=0;$i<30;$i++)
//				{
//					if($arr[$i]!="")
//						echo "  $i: ",$arr[$i],"<br>";
//				}
				if(validate_float($arr[4])!=0)
				{
					$ERROR_MSG="[".$arr[4]."] is not a float";
					return -1;
				}
				$expression=$arr[1]."0".$arr[5];
				$ok=0;
	                }
			else
			{
				$ok=1;
			}



		}
//		echo "Result:$expression<br><hr>";

		if($expression=="0")
		{
			return 0;
		}

		return 1;
	}

	function	cr()
	{
		echo "\n";
	}

	function	check_authorisation()
	{
		global	$page;
		global	$PHP_AUTH_USER,$PHP_AUTH_PW;
		global	$USER_DETAILS;
		global	$HTTP_COOKIE_VARS;
		global	$HTTP_GET_VARS;
//		global	$sessionid;

		if(isset($HTTP_COOKIE_VARS["sessionid"]))
		{
			$sessionid=$HTTP_COOKIE_VARS["sessionid"];
		}
		else
		{
			unset($sessionid);
		}

		if(isset($sessionid))
		{
			$sql="select u.userid,u.alias,u.name,u.surname from sessions s,users u where s.sessionid='$sessionid' and s.userid=u.userid and s.lastaccess+900>".time();
			$result=DBselect($sql);
			if(DBnum_rows($result)==1)
			{
//				setcookie("sessionid",$sessionid,time()+3600);
				setcookie("sessionid",$sessionid);
				$sql="update sessions set lastaccess=".time()." where sessionid='$sessionid'";
				DBexecute($sql);
				$USER_DETAILS["userid"]=DBget_field($result,0,0);
				$USER_DETAILS["alias"]=DBget_field($result,0,1);
				$USER_DETAILS["name"]=DBget_field($result,0,2);
				$USER_DETAILS["surname"]=DBget_field($result,0,3);
				return;
			}
			else
			{
				setcookie("sessionid",$sessionid,time()-3600);
				unset($sessionid);
			}
		}

                $sql="select u.userid,u.alias,u.name,u.surname from users u where u.alias='guest'";
                $result=DBselect($sql);
                if(DBnum_rows($result)==1)
                {
                        $USER_DETAILS["userid"]=DBget_field($result,0,0);
                        $USER_DETAILS["alias"]=DBget_field($result,0,1);
                        $USER_DETAILS["name"]=DBget_field($result,0,2);
                        $USER_DETAILS["surname"]=DBget_field($result,0,3);
			return;
		}

		if($page["file"]!="index.php")
		{
			echo "<meta http-equiv=\"refresh\" content=\"0; url=index.php\">";
		}
		show_special_header("Login",0,1,1);
		show_error_message("Login name or password is incorrect");
		insert_login_form();
		show_footer();
		exit;
	}

	# Header for HTML pages

	function	show_header($title,$refresh,$nomenu)
	{
		show_special_header($title,$refresh,$nomenu,0);
	}


	function	show_special_header($title,$refresh,$nomenu,$noauth)
	{
		global $page;
		global $USER_DETAILS;

		if($noauth!=1)
		{
			check_authorisation();
		}

?>
	<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
	<html>
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
	<meta name="Author" content="Alexei Vladishev">
	<link rel="stylesheet" href="css.css">

<?php
	if($USER_DETAILS['alias']=='guest')
	{
		$refresh=2*$refresh;
	}
	if($refresh!=0)
	{
		echo "<meta http-equiv=\"refresh\" content=\"$refresh\">";
		echo "<title>$title [refreshed every $refresh sec]</title>";
	}
	else
	{
		echo "<title>$title</title>";
	}

	echo "</head>";
?>


	<body>
<?php
		if($nomenu == 0)
		{
?>

	<table border=0 cellspacing=0 cellpadding=0 width=100% bgcolor=000000>
	<tr>
	<td valign="top">
		<table width=100% border=0 cellspacing=1 cellpadding=3>
		<tr>
		<td colspan=1 bgcolor=FFFFFF align=center valign=top width=15%>
<?php
				if(check_anyright("Host","R"))
				{
					echo "<a href=\"latest.php\">";
				}
				if( 	($page["file"]=="latest.php") ||
					($page["file"]=="history.php"))
				{
					echo "<b>[LATEST&nbsp;VALUES]</b></a>";
				}
				else
				{
					echo "LATEST&nbsp;VALUES</a>";
				}
?>
		</td>
		<td colspan=1 bgcolor=FFFFFF align=center valign=top width=10%>
<?php
				if(check_anyright("Host","R"))
				{
					echo "<a href=\"tr_status.php?notitle=true&onlytrue=true&noactions=true&compact=true\">";
				}
				if($page["file"]=="tr_status.php")
				{
					echo "<b>[TRIGGERS]</b></a>";
				}
				else
				{
					echo "TRIGGERS</a>";
				}
?>
		</td>
		<td colspan=1 bgcolor=FFFFFF align=center valign=top width=10%>
<?php
				if(check_anyright("Host","R"))
				{
					echo "<a href=\"queue.php\">";
				}
				if($page["file"]=="queue.php")
				{
					echo "<b>[QUEUE]</b></a>";
				}
				else
				{
					echo "QUEUE</a>";
				}
?>
		</td>
		<td colspan=1 bgcolor=FFFFFF align=center valign=top width=10%>
<?php
				if(check_anyright("Default permission","R"))
				{
					echo "<a href=\"latestalarms.php\">";
				}
?>
<?php
				if(($page["file"]=="latestalarms.php") ||
					($page["file"]=="alarms.php"))
				{
					echo "<b>[ALARMS]</b></a>";
				}
				else
				{
					echo "ALARMS</a>";
				}
?>
		</td>
		<td colspan=1 bgcolor=FFFFFF align=center valign=top width=15%>
<?php
				if(check_anyright("Default permission","R"))
				{
					echo "<a href=\"alerts.php\">";
				}
?>
<?php
				if($page["file"]=="alerts.php")
				{
					echo "<b>[ALERTS]</b></a>";
				}
				else
				{
					echo "ALERTS</a>";
				}
?>
		</td>
		<td colspan=1 bgcolor=FFFFFF align=center valign=top width=15%>
<?php
				if(check_anyright("Network map","R"))
				{
					echo "<a href=\"maps.php\">";
				}
?>
<?php
				if($page["file"]=="maps.php")
				{
					echo "<b>[NETWORK&nbsp;MAPS]</b></a>";
				}
				else
				{
					echo "NETWORK&nbsp;MAPS</a>";
				}
?>
		</td>
		<td colspan=1 bgcolor=FFFFFF align=center valign=top width=15%>
<?php
				if(check_anyright("Graph","R"))
				{
					echo "<a href=\"charts.php\">";
				}
?>
<?php
				if($page["file"]=="charts.php")
				{
					echo "<b>[GRAPHS]</b></a>";
				}
				else
				{
					echo "GRAPHS</a>";
				}
?>
		</td>
		<td colspan=1 bgcolor=FFFFFF align=center valign=top width=15%>
<?php
				if(check_anyright("Screen","R"))
				{
					echo "<a href=\"screens.php\">";
				}
?>
<?php
				if($page["file"]=="screens.php")
				{
					echo "<b>[SCREENS]</b></a>";
				}
				else
				{
					echo "SCREENS</a>";
				}
?>
		</td>

		<td colspan=1 bgcolor=FFFFFF align=center valign=top width=15%>
<?php
				if(check_anyright("Service","R"))
				{
					echo "<a href=\"srv_status.php\">";
				}
				if($page["file"]=="srv_status.php")
				{
					echo "<b>[IT&nbsp;SERVICES]</b></a>";
				}
				else
				{
					echo "IT&nbsp;SERVICES</a>";
				}
?>
		</td>
		</tr>

		<tr>
		<td colspan=2 bgcolor=FFFFFF align=center valign=top width=15%>
				<a href="index.php">
<?php
				if($page["file"]=="index.php")
				{
					echo "<b>[HOME]</b></a>";
				}
				else
				{
					echo "HOME</a>";
				}
?>
		</td>
		<td colspan=2 bgcolor=FFFFFF align=center valign=top width=15%>
<?php
				if(check_anyright("Default permission","R"))
				{
					echo "<a href=\"about.php\">";
				}
?>
<?php
				if($page["file"]=="about.php")
				{
					echo "<b>[ABOUT]</b></a>";
				}
				else
				{
					echo "ABOUT</a>";
				}
?>
		</td>
		<td colspan=2 bgcolor=FFFFFF align=center valign=top width=15%>
<?php
				if(check_anyright("Default permission","R"))
				{
					echo "<a href=\"report1.php\">";
				}
?>
<?php
				if($page["file"]=="report1.php")
				{
					echo "<b>[STATUS&nbsp;OF&nbsp;ZABBIX]</b></a>";
				}
				else
				{
					echo "STATUS&nbsp;OF&nbsp;ZABBIX</a>";
				}
?>
		</td>
		<td colspan=3 bgcolor=FFFFFF align=center valign=top width=15%>
<?php
				if(check_anyright("Host","R"))
				{
					echo "<a href=\"report2.php\">";
				}
?>
<?php
				if($page["file"]=="report2.php")
				{
					echo "<b>[AVAILABILITY&nbsp;REPORT]</b></a>";
				}
				else
				{
					echo "AVAILABILITY&nbsp;REPORT</a>";
				}
?>
		</td>
		</tr>
<?php
// Third row
		if(	check_anyright("Configuration of Zabbix","U")
			||
			check_anyright("User","U")
			||
			check_anyright("Host","U")
			||
			check_anyright("Graph","U")
			||
			check_anyright("Screen","U")
			||
			check_anyright("Network map","U")
			||
			check_anyright("Service","U")
		)
		{

?>
		<tr>
		<td colspan=1 bgcolor=FFFFFF align=center valign=top width=15%>
<?php
				if(check_anyright("Configuration of Zabbix","U"))
				{
					echo "<a href=\"config.php\">";
				}
				if($page["file"]=="config.php")
				{
					echo "<b>[CONFIG]</b></a>";
				}
				else
				{
					echo "CONFIG</a>";
				}
?>
		</td>
		<td colspan=1 bgcolor=FFFFFF align=center valign=top width=10%>
<?php
				if(check_anyright("User","U"))
				{
					echo "<a href=\"users.php\">";
				}
				if(	($page["file"]=="users.php")||
					($page["file"]=="media.php"))
				{
					echo "<b>[USERS]</b></a>";
				}
				else
				{
					echo "USERS</a>";
				}
?>
		</td>
		<td colspan=1 bgcolor=FFFFFF align=center valign=top width=10%>
<?php
				if(check_anyright("Host","U"))
				{
					echo "<a href=\"hosts.php\">";
				}
				if($page["file"]=="hosts.php")
				{
					echo "<b>[HOSTS]</b></a>";
				}
				else
				{
					echo "HOSTS</a>";
				}
?>
		</td>
		<td colspan=1 bgcolor=FFFFFF align=center valign=top width=10%>
<?php
				if(check_anyright("Host","U"))
				{
					echo "<a href=\"items.php\">";
				}
				if($page["file"]=="items.php")
				{
					echo "<b>[ITEMS]</b></a>";
				}
				else
				{
					echo "ITEMS</a>";
				}
?>
		</td>
		<td colspan=1 bgcolor=FFFFFF align=center valign=top width=15%>
<?php
				if(check_anyright("Host","U"))
				{
					echo "<a href=\"triggers.php\">";
				}
				if(	($page["file"]=="triggers.php")||
					($page["file"]=="actions.php"))
				{
					echo "<b>[TRIGGERS]</b></a>";
				}
				else
				{
					echo "TRIGGERS</a>";
				}
?>
		</td>
		<td colspan=1 bgcolor=FFFFFF align=center valign=top width=15%>
<?php
				if(check_anyright("Network map","U"))
				{
					echo "<a href=\"sysmaps.php\">";
				}
				if(	($page["file"]=="sysmaps.php")||
					($page["file"]=="sysmap.php"))
				{
					echo "<b>[NETWORK&nbsp;MAPS]</b></a>";
				}
				else
				{
					echo "NETWORK&nbsp;MAPS</a>";
				}
?>
		</td>
		<td colspan=1 bgcolor=FFFFFF align=center valign=top width=15%>
<?php
				if(check_anyright("Graph","U"))
				{
					echo "<a href=\"graphs.php\">";
				}
				if(	($page["file"]=="graphs.php")||
					($page["file"]=="graph.php"))
				{
					echo "<b>[GRAPHS]</b></a>";
				}
				else
				{
					echo "GRAPHS</a>";
				}
?>
		</td>
		<td colspan=1 bgcolor=FFFFFF align=center valign=top width=15%>
<?php
				if(check_anyright("Screen","U"))
				{
					echo "<a href=\"screenconf.php\">";
				}
				if(	($page["file"]=="screenedit.php")||
					($page["file"]=="screenconf.php"))
				{
					echo "<b>[SCREENS]</b></a>";
				}
				else
				{
					echo "SCREENS</a>";
				}
?>
		</td>

		<td colspan=1 bgcolor=FFFFFF align=center valign=top width=15%>
<?php
				if(check_anyright("Service","U"))
				{
					echo "<a href=\"services.php\">";
				}
				if($page["file"]=="services.php")
				{
					echo "<b>[IT&nbsp;SERVICES]</b></a>";
				}
				else
				{
					echo "IT&nbsp;SERVICES</a>";
				}
?>
		</td>
		</tr>
<?php
// THird row
		}
?>
		</table>
	</td>
	</tr>
	</table>

	<br>
<?php
		}
	}

	# Show values in plain text

	function	show_plaintext($itemid, $from, $till)
	{
		$item=get_item_by_itemid($itemid);
		if($item["value_type"]==0)
		{
			$sql="select clock,value from history where itemid=$itemid and clock>$from and clock<$till order by clock";
		}
		else
		{
			$sql="select clock,value from history_str where itemid=$itemid and clock>$from and clock<$till order by clock";
		}
                $result=DBselect($sql);

		echo "<PRE>\n";
		for($i=0;$i<DBnum_rows($result);$i++)
		{
			$clock=DBget_field($result,$i,0);
			$value=DBget_field($result,$i,1);
			echo date("Y-m-d H:i:s",$clock);
			echo "\t$clock\t$value\n";
		}
	}
 

	# Translate {10}>10 to something like localhost:procload.last(0)>10

	function	explode_exp ($expression, $html)
	{
#		echo "EXPRESSION:",$expression,"<Br>";

		$functionid='';
		$exp='';
		$state='';
		for($i=0;$i<strlen($expression);$i++)
		{
			if($expression[$i] == '{')
			{
				$functionid='';
				$state='FUNCTIONID';
				continue;
			}
			if($expression[$i] == '}')
			{
				$state='';
				$sql="select h.host,i.key_,f.function,f.parameter,i.itemid from items i,functions f,hosts h where functionid=$functionid and i.itemid=f.itemid and h.hostid=i.hostid";
				$res1=DBselect($sql);
				if($html == 0)
				{
					$exp=$exp."{".DBget_field($res1,0,0).":".DBget_field($res1,0,1).".".DBget_field($res1,0,2)."(".DBget_field($res1,0,3).")}";
				}
				else
				{
					$item=get_item_by_itemid(DBget_field($res1,0,4));
					if($item["value_type"] ==0) 
					{
						$exp=$exp."{<A HREF=\"history.php?action=showhistory&itemid=".DBget_field($res1,0,4)."\">".DBget_field($res1,0,0).":".DBget_field($res1,0,1)."</A>.<B>".DBget_field($res1,0,2)."(</B>".DBget_field($res1,0,3)."<B>)</B>}";
					}
					else
					{
						$exp=$exp."{<A HREF=\"history.php?action=showvalues&period=3600&itemid=".DBget_field($res1,0,4)."\">".DBget_field($res1,0,0).":".DBget_field($res1,0,1)."</A>.<B>".DBget_field($res1,0,2)."(</B>".DBget_field($res1,0,3)."<B>)</B>}";
					}
				}
				continue;
			}
			if($state == "FUNCTIONID")
			{
				$functionid=$functionid.$expression[$i];
				continue;
			}
			$exp=$exp.$expression[$i];
		}
#		echo "EXP:",$exp,"<Br>";
		return $exp;
	}

	# Translate localhost:procload.last(0)>10 to {12}>10

	function	implode_exp ($expression, $triggerid)
	{
//		echo "Expression:$expression<br>";
		$exp='';
		$state="";
		for($i=0;$i<strlen($expression);$i++)
		{
			if($expression[$i] == '{')
			{
				if($state=="")
				{
					$host='';
					$key='';
					$function='';
					$parameter='';
					$state='HOST';
					continue;
				}
			}
			if( ($expression[$i] == '}')&&($state=="") )
			{
//				echo "HOST:$host<BR>";
//				echo "KEY:$key<BR>";
//				echo "FUNCTION:$function<BR>";
//				echo "PARAMETER:$parameter<BR>";
				$state='';
		
				$sql="select i.itemid from items i,hosts h where i.key_='$key' and h.host='$host' and h.hostid=i.hostid";
#				echo $sql,"<Br>";
				$res=DBselect($sql);

				$itemid=DBget_field($res,0,0);
#				echo "ITEMID:$itemid<BR>";
	
#				$sql="select functionid,count(*) from functions where function='$function' and parameter=$parameter group by 1";
#				echo $sql,"<Br>";
#				$res=DBselect($sql);
#
#				if(DBget_field($res,0,1)>0)
#				{
#					$functionid=DBget_field($res,0,0);
#				}
#				else
#				{
					$sql="insert into functions (itemid,triggerid,function,parameter) values ($itemid,$triggerid,'$function','$parameter')";
#					echo $sql,"<Br>";
					$res=DBexecute($sql);
					if(!$res)
					{
#						echo "ERROR<br>";
						return	$res;
					}
					$functionid=DBinsert_id($res,"functions","functionid");
#				}
#				echo "FUNCTIONID:$functionid<BR>";

				$exp=$exp.'{'.$functionid.'}';

				continue;
			}
			if($expression[$i] == '(')
			{
				if($state == "FUNCTION")
				{
					$state='PARAMETER';
					continue;
				}
			}
			if($expression[$i] == ')')
			{
				if($state == "PARAMETER")
				{
					$state='';
					continue;
				}
			}
			if(($expression[$i] == ':') && ($state == "HOST"))
			{
				$state="KEY";
				continue;
			}
			if($expression[$i] == '.')
			{
				if($state == "KEY")
				{
					$state="FUNCTION";
					continue;
				}
				// Support for '.' in KEY
				if($state == "FUNCTION")
				{
					$state="FUNCTION";
					$key=$key.".".$function;
					$function="";
					continue;
				}
			}
			if($state == "HOST")
			{
				$host=$host.$expression[$i];
				continue;
			}
			if($state == "KEY")
			{
				$key=$key.$expression[$i];
				continue;
			}
			if($state == "FUNCTION")
			{
				$function=$function.$expression[$i];
				continue;
			}
			if($state == "PARAMETER")
			{
				$parameter=$parameter.$expression[$i];
				continue;
			}
			$exp=$exp.$expression[$i];
		}
		return $exp;
	}

	function	update_trigger_comments($triggerid,$comments)
	{
		global	$ERROR_MSG;

		if(!check_right("Trigger comment","U",$triggerid))
		{
			$ERROR_MSG="Insufficient permissions";
			return	0;
		}

		$comments=addslashes($comments);
		$sql="update triggers set comments='$comments' where triggerid=$triggerid";
		return	DBexecute($sql);
	}

	# Update Trigger status

	function	update_trigger_status($triggerid,$status)
	{
		global	$ERROR_MSG;

		if(!check_right_on_trigger("U",$triggerid))
		{
                        $ERROR_MSG="Insufficient permissions";
                        return 0;
		}
		add_alarm($triggerid,2);
		$sql="update triggers set status=$status where triggerid=$triggerid";
		return	DBexecute($sql);
	}


	# Update Item status

	function	update_item_status($itemid,$status)
	{
		global	$ERROR_MSG;

                if(!check_right("Item","U",0))
		{
                        $ERROR_MSG="Insufficient permissions";
                        return 0;
		}
		$sql="update items set status=$status where itemid=$itemid";
		return	DBexecute($sql);
	}

	# "Processor load on %s is 5" to "Processor load on www.sf.net is 5"
	function	expand_trigger_description_simple($triggerid)
	{
		$sql="select distinct t.description,h.host from triggers t,functions f,items i,hosts h where t.triggerid=$triggerid and f.triggerid=t.triggerid and f.itemid=i.itemid and i.hostid=h.hostid";
//		echo $sql;
		$result=DBselect($sql);
		$row=DBfetch($result);

//		$description=str_replace("%s",$row["host"],$row["description"]);

		$search=array("{HOSTNAME}");
		$replace=array($row["host"]);
//		$description = str_replace($search, $replace,$row["description"]);
		$description = str_replace("{HOSTNAME}", $row["host"],$row["description"]);

		return $description;
	}

	# "Processor load on %s is 5" to "Processor load on www.sf.net is 5"
	function	expand_trigger_description($triggerid)
	{
		$description=expand_trigger_description_simple($triggerid);
		$description=stripslashes(htmlspecialchars($description));

		return $description;
	}

	function	update_trigger_value_to_unknown_by_hostid($hostid)
	{
		$sql="select distinct t.triggerid from hosts h,items i,triggers t,functions f where f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid and h.hostid=$hostid";
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			$sql="update triggers set value=2 where triggerid=".$row["triggerid"];
			DBexecute($sql);
		}
	}

	# Update Host status

	function	update_host_status($hostid,$status)
	{
                global  $ERROR_MSG;
                if(!check_right("Host","U",0))
                {
                        $ERROR_MSG="Insufficient permissions";
                        return 0;
                }

		$sql="select status from hosts where hostid=$hostid";
		$result=DBselect($sql);
		if($status != DBget_field($result,0,0))
		{
			update_trigger_value_to_unknown_by_hostid($hostid);
			$sql="update hosts set status=$status where hostid=$hostid and status not in (".HOST_STATUS_UNREACHABLE.",".HOST_STATUS_DELETED.")";
			return	DBexecute($sql);
		}
		else
		{
			return 1;
		}
	}

	# Update Item definition

	function	update_item($itemid,$description,$key,$hostid,$delay,$history,$status,$type,$snmp_community,$snmp_oid,$value_type,$trapper_hosts,$snmp_port,$units,$multiplier,$delta)
	{
		global	$ERROR_MSG;

		if(!check_right("Item","U",$itemid))
		{
			$ERROR_MSG="Insufficient permissions";
			return 0;
		}
		if($delay<1)
		{
			$ERROR_MSG="Delay cannot be less than 1 second";
			return 0;
		}

		if( ($snmp_port<1)||($snmp_port>65535))
		{
			$ERROR_MSG="Invalid SNMP port";
			return 0;
		}

		if($value_type == ITEM_VALUE_TYPE_STR)
		{
			$delta=0;
		}

		$sql="update items set description='$description',key_='$key',hostid=$hostid,delay=$delay,history=$history,lastdelete=0,nextcheck=0,status=$status,type=$type,snmp_community='$snmp_community',snmp_oid='$snmp_oid',value_type=$value_type,trapper_hosts='$trapper_hosts',snmp_port=$snmp_port,units='$units',multiplier=$multiplier,delta=$delta where itemid=$itemid";
		return	DBexecute($sql);
	}

	# Add Action

	function	add_action( $triggerid, $userid, $good, $delay, $subject, $message, $scope, $severity, $recipient, $usrgrpid)
	{
		global	$ERROR_MSG;

		if(!check_right_on_trigger("A",$triggerid))
		{
                        $ERROR_MSG="Insufficient permissions";
                        return 0;
		}

		if($recipient == RECIPIENT_TYPE_USER)
		{
			$id = $userid;
		}
		else
		{
			$id = $usrgrpid;
		}

		if($scope==2)
		{
			$sql="insert into actions (triggerid,userid,good,delay,nextcheck,subject,message,scope,severity,recipient) values (0,$id,$good,$delay,0,'*Automatically generated*','*Automatically generated*',$scope,$severity,$recipient)";
			return	DBexecute($sql);
		}
		elseif($scope==1)
		{
			$sql="select h.hostid from triggers t,hosts h,functions f,items i where f.triggerid=t.triggerid and h.hostid=i.hostid and i.itemid=f.itemid and t.triggerid=$triggerid";
//			echo "$sql<br>";
			$result=DBselect($sql);
			while($row=DBfetch($result))
			{
				$sql="insert into actions (triggerid,userid,good,delay,nextcheck,subject,message,scope,severity,recipient) values (".$row["hostid"].",$id,$good,$delay,0,'*Automatically generated*','*Automatically generated*',$scope,$severity,$recipient)";
//				echo "$sql<br>";
				DBexecute($sql);
			}
			return TRUE;
		}
		else
		{
			$sql="insert into actions (triggerid,userid,good,delay,nextcheck,subject,message,scope,severity,recipient) values ($triggerid,$id,$good,$delay,0,'$subject','$message',$scope,$severity,$recipient)";
			return	DBexecute($sql);
		}
	}

	# Return TRUE if triggerid is a reason why the service is not OK
	# Warning: recursive function
	function	does_service_depend_on_the_service($serviceid,$serviceid2)
	{
#		echo "Serviceid:$serviceid Triggerid:$serviceid2<br>";
		$service=get_service_by_serviceid($serviceid);
#		echo "Service status:".$service["status"]."<br>";
		if($service["status"]==0)
		{
			return	FALSE;
		}
		if($serviceid==$serviceid2)
		{
			if($service["status"]>0)
			{
				return TRUE;
			}
			
		}

		$sql="select serviceupid from services_links where servicedownid=$serviceid2 and soft=0";
#		echo $sql."<br>";
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			if(does_service_depend_on_the_service($serviceid,$row["serviceupid"]) == TRUE)
			{
				return	TRUE;
			}
		}
		return	FALSE;
	}

	function	service_has_parent($serviceid)
	{
		$sql="select count(*) from services_links where servicedownid=$serviceid";
		$result=DBselect($sql);
		if(DBget_field($result,0,0)>0)
		{
			return	TRUE;
		}
		return	FALSE;
	}

	function	service_has_no_this_parent($parentid,$serviceid)
	{
		$sql="select count(*) from services_links where serviceupid=$parentid and servicedownid=$serviceid";
		$result=DBselect($sql);
		if(DBget_field($result,0,0)>0)
		{
			return	FALSE;
		}
		return	TRUE;
	}


	function	delete_service_link($linkid)
	{
		$sql="delete from services_links where linkid=$linkid";
		return DBexecute($sql);
	}

	function	delete_service($serviceid)
	{
		$sql="delete from services_links where servicedownid=$serviceid or serviceupid=$serviceid";
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}
		$sql="delete from services where serviceid=$serviceid";
		return DBexecute($sql);
	}

	function	update_service($serviceid,$name,$triggerid,$linktrigger,$algorithm,$showsla,$goodsla,$sortorder)
	{
		if( isset($linktrigger)&&($linktrigger=="on") )
		{
			// No mistake here
			$triggerid=$triggerid;
		}
		else
		{
			$triggerid='NULL';
		}
		if( isset($showsla)&&($showsla=="on") )
		{
			$showsla=1;
		}
		else
		{
			$showsla=0;
		}
		$sql="update services set name='$name',triggerid=$triggerid,status=0,algorithm=$algorithm,showsla=$showsla,goodsla=$goodsla,sortorder=$sortorder where serviceid=$serviceid";
		return	DBexecute($sql);
	}

	function	add_service($serviceid,$name,$triggerid,$linktrigger,$algorithm,$showsla,$goodsla,$sortorder)
	{
		if( isset($showsla)&&($showsla=="on") )
		{
			$showsla=1;
		}
		else
		{
			$showsla=0;
		}
		if( isset($linktrigger)&&($linktrigger=="on") )
		{
//			$trigger=get_trigger_by_triggerid($triggerid);
//			$description=$trigger["description"];
//			if( strstr($description,"%s"))
//			{
				$description=expand_trigger_description($triggerid);
//			}
			$description=addslashes($description);
			$sql="insert into services (name,triggerid,status,algorithm,showsla,goodsla,sortorder) values ('$description',$triggerid,0,$algorithm,$showsla,$goodsla,$sortorder)";
		}
		else
		{
			$sql="insert into services (name,status,algorithm,showsla,goodsla,sortorder) values ('$name',0,$algorithm,$showsla,$goodsla,$sortorder)";
		}
		$result=DBexecute($sql);
		if(!$result)
		{
			return FALSE;
		}
		$id=DBinsert_id($result,"services","serviceid");
		if(isset($serviceid))
		{
			add_service_link($id,$serviceid,0);
		}
		return $id;
	}

	function	add_host_to_services($hostid,$serviceid)
	{
		$sql="select distinct t.triggerid,t.description from triggers t,hosts h,items i,functions f where h.hostid=$hostid and h.hostid=i.hostid and i.itemid=f.itemid and f.triggerid=t.triggerid";
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			$serviceid2=add_service($serviceid,$row["description"],$row["triggerid"],"on",0,"off",99,0);
//			add_service_link($serviceid2,$serviceid,0);
		}
		return	1;
	}

	function	is_service_hardlinked($serviceid)
	{
		$sql="select count(*) from services_links where servicedownid=$serviceid and soft=0";
		$result=DBselect($sql);
		if(DBget_field($result,0,0)>0)
		{
			return	TRUE;
		}
		return	FALSE;
		
	}

	function	add_service_link($servicedownid,$serviceupid,$softlink)
	{
		global	$ERROR_MSG;

		if( ($softlink==0) && (is_service_hardlinked($servicedownid)==TRUE) )
		{
			return	FALSE;
		}

		if($servicedownid==$serviceupid)
		{
			$ERROR_MSG="Cannot link service to itself.";
			return	FALSE;
		}

		$sql="insert into services_links (servicedownid,serviceupid,soft) values ($servicedownid,$serviceupid,$softlink)";
		return	DBexecute($sql);
	}

	# Update Action

	function	update_action( $actionid, $triggerid, $userid, $good, $delay, $subject, $message, $scope, $severity, $recipient, $usrgrpid)
	{
		delete_action($actionid);
		return add_action( $triggerid, $userid, $good, $delay, $subject, $message, $scope, $severity, $recipient, $usrgrpid);
	}

	function	delete_graphs_item($gitemid)
	{
		$sql="delete from graphs_items where gitemid=$gitemid";
		return	DBexecute($sql);
	}

	# Delete Graph

	function	delete_graph($graphid)
	{
		$sql="delete from graphs_items where graphid=$graphid";
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}
		$sql="delete from graphs where graphid=$graphid";
		return	DBexecute($sql);
	}

	# Delete System Map

	function	delete_sysmap( $sysmapid )
	{
		$sql="delete from sysmaps where sysmapid=$sysmapid";
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}
		$sql="delete from sysmaps_hosts where sysmapid=$sysmapid";
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}
		$sql="delete from sysmaps_links where sysmapid=$sysmapid";
		return	DBexecute($sql);
	}

	# Delete Alert by actionid

	function	delete_alert_by_actionid( $actionid )
	{
		$sql="delete from alerts where actionid=$actionid";
		return	DBexecute($sql);
	}

	function	delete_rights_by_userid($userid )
	{
		$sql="delete from rights where userid=$userid";
		return	DBexecute($sql);
	}


	# Delete Action by userid

	function	delete_actions_by_userid( $userid )
	{
		$sql="select actionid from actions where userid=$userid";
		$result=DBexecute($sql);
		for($i=0;$i<DBnum_rows($result);$i++)
		{
			$actionid=DBget_field($result,$i,0);
			delete_alert_by_actionid($actionid);
		}

		$sql="delete from actions where userid=$userid";
		return	DBexecute($sql);
	}

	# Delete Action

	function	delete_action( $actionid )
	{
		$sql="delete from actions where actionid=$actionid";
		$result=DBexecute($sql);

		return delete_alert_by_actionid($actionid);
	}

	# Delete from History

	function	delete_history_by_itemid( $itemid )
	{
		$sql="delete from history_str where itemid=$itemid";
		DBexecute($sql);
		$sql="delete from history where itemid=$itemid";
		return	DBexecute($sql);
	}

	# Delete from Trends

	function	delete_trends_by_itemid( $itemid )
	{
		$sql="delete from trends where itemid=$itemid";
		return	DBexecute($sql);
	}

	function	delete_trigger_dependency($triggerid_down,$triggerid_up)
	{
// Why this was here?
//		$sql="select count(*) from trigger_depends where triggerid_down=$triggerid_up and triggerid_up=$triggerid_down";
//		$result=DBexecute($sql);
//		if(DBget_field($result,0,0)>0)
//		{
//			return	FALSE;
//		}

// It was wrong - was deleting all dependencies
//		$sql="select triggerid_down,triggerid_up from trigger_depends where triggerid_up=$triggerid_up or triggerid_down=$triggerid_down";
//		$result=DBexecute($sql);
//		for($i=0;$i<DBnum_rows($result);$i++)
//		{
//			$down=DBget_field($result,$i,0);
//			$up=DBget_field($result,$i,1);
//			$sql="delete from trigger_depends where triggerid_up=$up and triggerid_down=$down";
//			DBexecute($sql);
//			$sql="update triggers set dep_level=dep_level-1 where triggerid=$up";
//			DBexecute($sql);
//		}

		$sql="select triggerid_down,triggerid_up from trigger_depends where triggerid_up=$triggerid_up and triggerid_down=$triggerid_down";
		$result=DBexecute($sql);
		for($i=0;$i<DBnum_rows($result);$i++)
		{
			$down=DBget_field($result,$i,0);
			$up=DBget_field($result,$i,1);
			$sql="update triggers set dep_level=dep_level-1 where triggerid=$up";
			DBexecute($sql);
		}

		$sql="delete from trigger_depends where triggerid_up=$triggerid_up and triggerid_down=$triggerid_down";
		DBexecute($sql);

		return	TRUE;
	}

	function	insert_dependency($triggerid_down,$triggerid_up)
	{
		$sql="insert into trigger_depends (triggerid_down,triggerid_up) values ($triggerid_down,$triggerid_up)";
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}
		$sql="update triggers set dep_level=dep_level+1 where triggerid=$triggerid_up";
		$result=DBexecute($sql);
		return	$result;
	}

	// If 1 depends on 2, and 2 depends on 3, then add dependency 1->3
	function	add_additional_dependencies($triggerid_down,$triggerid_up)
	{
		$sql="select triggerid_down from trigger_depends where triggerid_up=$triggerid_down";
		$result=DBselect($sql);
		for($i=0;$i<DBnum_rows($result);$i++)
		{
			$triggerid=DBget_field($result,$i,0);
			insert_dependency($triggerid,$triggerid_up);
			add_additional_dependencies($triggerid,$triggerid_up);
		}
		$sql="select triggerid_up from trigger_depends where triggerid_down=$triggerid_up";
		$result=DBselect($sql);
		for($i=0;$i<DBnum_rows($result);$i++)
		{
			$triggerid=DBget_field($result,$i,0);
			insert_dependency($triggerid_down,$triggerid);
			add_additional_dependencies($triggerid_down,$triggerid);
		}
	}

	function	add_trigger_dependency($triggerid,$depid)
	{
		$result=insert_dependency($triggerid,$depid);;
		if(!$result)
		{
			return $result;
		}
		add_additional_dependencies($triggerid,$depid);
		return $result;
	}

	# Delete Function definition

	# Add Item definition

	function	add_item($description,$key,$hostid,$delay,$history,$status,$type,$snmp_community,$snmp_oid,$value_type,$trapper_hosts,$snmp_port,$units,$multiplier,$delta)
	{
		global	$ERROR_MSG;

		if(!check_right("Item","A",0))
		{
			$ERROR_MSG="Insufficient permissions";
			return 0;
		}

		$sql="select count(*) from items where hostid=$hostid and key_='$key'";
		$result=DBexecute($sql);
		if(DBget_field($result,0,0)>0)
		{
			$ERROR_MSG="An item with the same Key already exists for this host. The key must be unique.";
			return 0;
		}

		if($delay<1)
		{
			$ERROR_MSG="Delay cannot be less than 1 second";
			return 0;
		}

		if( ($snmp_port<1)||($snmp_port>65535))
		{
			$ERROR_MSG="Invalid SNMP port";
			return 0;
		}

		if($value_type == ITEM_VALUE_TYPE_STR)
		{
			$delta=0;
		}

		$key=addslashes($key);
		$description=addslashes($description);

		$sql="insert into items (description,key_,hostid,delay,history,lastdelete,nextcheck,status,type,snmp_community,snmp_oid,value_type,trapper_hosts,snmp_port,units,multiplier,delta) values ('$description','$key',$hostid,$delay,$history,0,0,$status,$type,'$snmp_community','$snmp_oid',$value_type,'$trapper_hosts',$snmp_port,'$units',$multiplier,$delta)";
		$result=DBexecute($sql);
		return DBinsert_id($result,"items","itemid");
	}

	# Delete Function definition

	function	delete_function_by_triggerid($triggerid)
	{
		$sql="delete from functions where triggerid=$triggerid";
		return	DBexecute($sql);
	}

	function	delete_actions_by_triggerid($triggerid)
	{
		$sql="delete from actions where triggerid=$triggerid and scope=0";
		return	DBexecute($sql);
	}

	function	delete_alarms_by_triggerid($triggerid)
	{
		$sql="delete from alarms where triggerid=$triggerid";
		return	DBexecute($sql);
	}

	# Delete Function and Trigger definitions by itemid

	function	delete_triggers_by_itemid($itemid)
	{
		$sql="select triggerid from functions where itemid=$itemid";
		$result=DBselect($sql);
		for($i=0;$i<DBnum_rows($result);$i++)
		{
			if(!delete_trigger(DBget_field($result,$i,0)))
			{
				return FALSE;
			}
		}
		$sql="delete from functions where itemid=$itemid";
		return	DBexecute($sql);
	}

	# Delete Service definitions by triggerid

	function	delete_services_by_triggerid($triggerid)
	{
		$sql="select serviceid from services where triggerid=$triggerid";
		$result=DBselect($sql);
		for($i=0;$i<DBnum_rows($result);$i++)
		{
			delete_service(DBget_field($result,$i,0));
		}
		return	TRUE;
	}

	# Activate Item

	function	activate_item($itemid)
	{
		$sql="update items set status=".ITEM_STATUS_ACTIVE." where itemid=$itemid";
		return	DBexecute($sql);
	}

	# Disable Item

	function	disable_item($itemid)
	{
		$sql="update items set status=".ITEM_STATUS_DISABLED." where itemid=$itemid";
		return	DBexecute($sql);
	}

	# Delete Item definition

	function	delete_item($itemid)
	{
		$sql="select hostid from items where itemid=$itemid";
		$result=DBselect($sql);
		$hostid=DBget_field($result,0,0);
		delete_sysmaps_host_by_hostid($hostid);

		$result=delete_triggers_by_itemid($itemid);
		if(!$result)
		{
			return	$result;
		}
		$result=delete_trends_by_itemid($itemid);
		$result=delete_history_by_itemid($itemid);
		$sql="delete from graphs_items where itemid=$itemid";
		if(!$result)
		{
			return	$result;
		}
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}
		$sql="delete from items where itemid=$itemid";
		return	DBexecute($sql);
	}

	# Add alarm

	function	add_alarm($triggerid,$value)
	{
		$sql="select max(clock) from alarms where triggerid=$triggerid";
		$result=DBselect($sql);
		$row=DBfetch($result);
		if($row[0]!="")
		{
			$sql="select value from alarms where triggerid=$triggerid and clock=".$row[0];
			$result=DBselect($sql);
			if(DBnum_rows($result) == 1)
			{
				$row=DBfetch($result);
				if($row["value"] == $value)
				{
					return 0;
				}
			}
		}

		$now=time();
		$sql="insert into alarms(triggerid,clock,value) values($triggerid,$now,$value)";
		return	DBexecute($sql);
	}

	# Add Trigger definition

	function	add_trigger($expression,$description,$priority,$status,$comments,$url)
	{
		global	$ERROR_MSG;

//		if(!check_right("Trigger","A",0))
//		{
//			$ERROR_MSG="Insufficient permissions";
//			return	0;
//		}

#		$description=addslashes($description);
		$sql="insert into triggers  (description,priority,status,comments,url,value) values ('$description',$priority,$status,'$comments','$url',2)";
#		echo $sql,"<br>";
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}
 
		$triggerid=DBinsert_id($result,"triggers","triggerid");
#		echo $triggerid,"<br>";
		add_alarm($triggerid,2);
 
		$expression=implode_exp($expression,$triggerid);
		$sql="update triggers set expression='$expression' where triggerid=$triggerid";
#		echo $sql,"<br>";
		return	DBexecute($sql);
	}

	# Delete Trigger definition

	function	delete_trigger($triggerid)
	{
		global	$ERROR_MSG;

		$sql="select count(*) from trigger_depends where triggerid_down=$triggerid or triggerid_up=$triggerid";
		$result=DBexecute($sql);
		if(DBget_field($result,0,0)>0)
		{
			$ERROR_MSG="Delete dependencies first";
			return	FALSE;
		}

		$result=delete_function_by_triggerid($triggerid);
		if(!$result)
		{
			return	$result;
		}
		$result=delete_alarms_by_triggerid($triggerid);
		if(!$result)
		{
			return	$result;
		}
		$result=delete_actions_by_triggerid($triggerid);
		if(!$result)
		{
			return	$result;
		}
		$result=delete_services_by_triggerid($triggerid);
		if(!$result)
		{
			return	$result;
		}

		$sql="update sysmaps_links set triggerid=NULL where triggerid=$triggerid";
		DBexecute($sql);

		$sql="delete from triggers where triggerid=$triggerid";
		return	DBexecute($sql);
	}

	# Update Trigger definition

	function	update_trigger($triggerid,$expression,$description,$priority,$status,$comments,$url)
	{
		global	$ERROR_MSG;

		if(!check_right_on_trigger("U",$triggerid))
		{
                        $ERROR_MSG="Insufficient permissions";
                        return 0;
		}

		$result=delete_function_by_triggerid($triggerid);
		if(!$result)
		{
			return	$result;
		}

		$expression=implode_exp($expression,$triggerid);
		add_alarm($triggerid,2);
//		$sql="update triggers set expression='$expression',description='$description',priority=$priority,status=$status,comments='$comments',url='$url' where triggerid=$triggerid";
		$sql="update triggers set expression='$expression',description='$description',priority=$priority,status=$status,comments='$comments',url='$url',value=2 where triggerid=$triggerid";
		return	DBexecute($sql);
	}

	# Update User definition

	function	update_user($userid,$name,$surname,$alias,$passwd)
	{
		global	$ERROR_MSG;

		if(!check_right("User","U",$userid))
		{
			$ERROR_MSG="Insufficient permissions";
			return 0;
		}

		if($passwd=="")
		{
			$sql="update users set name='$name',surname='$surname',alias='$alias' where userid=$userid";
		}
		else
		{
			$passwd=md5($passwd);
			$sql="update users set name='$name',surname='$surname',alias='$alias',passwd='$passwd' where userid=$userid";
		}
		return DBexecute($sql);
	}

	# Add permission

	function	add_permission($userid,$right,$permission,$id)
	{
		$sql="insert into rights (userid,name,permission,id) values ($userid,'$right','$permission',$id)";
		return DBexecute($sql);
	}

	# Add User definition

	function	add_user($name,$surname,$alias,$passwd)
	{
		global	$ERROR_MSG;

		if(!check_right("User","A",0))
		{
			$ERROR_MSG="Insufficient permissions";
			return 0;
		}

		$passwd=md5($passwd);
		$sql="insert into users (name,surname,alias,passwd) values ('$name','$surname','$alias','$passwd')";
		return DBexecute($sql);
	}

	# Update Graph

	function	update_graph($graphid,$name,$width,$height)
	{
		global	$ERROR_MSG;

		if(!check_right("Graph","U",0))
		{
			$ERROR_MSG="Insufficient permissions";
			return 0;
		}

		$sql="update graphs set name='$name',width=$width,height=$height where graphid=$graphid";
		return	DBexecute($sql);
	}

	# Update System Map

	function	update_sysmap($sysmapid,$name,$width,$height)
	{
		global	$ERROR_MSG;

		if(!check_right("Network map","U",$sysmapid))
		{
			$ERROR_MSG="Insufficient permissions";
			return 0;
		}

		$sql="update sysmaps set name='$name',width=$width,height=$height where sysmapid=$sysmapid";
		return	DBexecute($sql);
	}

	# Add Graph

	function	add_graph($name,$width,$height)
	{
		global	$ERROR_MSG;

		if(!check_right("Graph","A",0))
		{
			$ERROR_MSG="Insufficient permissions";
			return 0;
		}

		$sql="insert into graphs (name,width,height) values ('$name',$width,$height)";
		return	DBexecute($sql);
	}

	function	update_graph_item($gitemid,$itemid,$color,$drawtype,$sortorder)
	{
		$sql="update graphs_items set itemid=$itemid,color='$color',drawtype=$drawtype,sortorder=$sortorder where gitemid=$gitemid";
		return	DBexecute($sql);
	}

	function	add_item_to_graph($graphid,$itemid,$color,$drawtype,$sortorder)
	{
		$sql="insert into graphs_items (graphid,itemid,color,drawtype,sortorder) values ($graphid,$itemid,'$color',$drawtype,$sortorder)";
		return	DBexecute($sql);
	}

	# Add System Map

	function	add_sysmap($name,$width,$height)
	{
		global	$ERROR_MSG;

		if(!check_right("Network map","A",0))
		{
			$ERROR_MSG="Insufficient permissions";
			return 0;
		}

		$sql="insert into sysmaps (name,width,height) values ('$name',$width,$height)";
		return	DBexecute($sql);
	}

	function	add_link($sysmapid,$shostid1,$shostid2,$triggerid,$drawtype_off,$color_off,$drawtype_on,$color_on)
	{
		if($triggerid == 0)
		{
			$sql="insert into sysmaps_links (sysmapid,shostid1,shostid2,triggerid,drawtype_off,color_off,drawtype_on,color_on) values ($sysmapid,$shostid1,$shostid2,NULL,$drawtype_off,'$color_off',$drawtype_on,'$color_on')";
		}
		else
		{
			$sql="insert into sysmaps_links (sysmapid,shostid1,shostid2,triggerid,drawtype_off,color_off,drawtype_on,color_on) values ($sysmapid,$shostid1,$shostid2,$triggerid,$drawtype_off,'$color_off',$drawtype_on,'$color_on')";
		}
		return	DBexecute($sql);
	}

	function	delete_link($linkid)
	{
		$sql="delete from sysmaps_links where linkid=$linkid";
		return	DBexecute($sql);
	}

	# Add Host to system map

	function add_host_to_sysmap($sysmapid,$hostid,$label,$x,$y,$icon)
	{
		$sql="insert into sysmaps_hosts (sysmapid,hostid,label,x,y,icon) values ($sysmapid,$hostid,'$label',$x,$y,'$icon')";
		return	DBexecute($sql);
	}

	function	update_sysmap_host($shostid,$sysmapid,$hostid,$label,$x,$y,$icon)
	{
		$sql="update sysmaps_hosts set hostid=$hostid,label='$label',x=$x,y=$y,icon='$icon' where shostid=$shostid";
		return	DBexecute($sql);
	}

	# Add everything based on host_templateid

	function	add_using_host_template($hostid,$host_templateid)
	{
		global	$ERROR_MSG;

		if(!isset($host_templateid)||($host_templateid==0))
		{
			$ERROR_MSG="Select template first";
			return 0;
		}

		$host=get_host_by_hostid($hostid);
		$sql="select itemid from items where hostid=$host_templateid";
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			$item=get_item_by_itemid($row["itemid"]);
			$itemid=add_item($item["description"],$item["key_"],$hostid,$item["delay"],$item["history"],$item["status"],$item["type"],$item["snmp_community"],$item["snmp_oid"],$item["value_type"],"",161,$item["units"],$item["multiplier"],$item["delta"]);

			$sql="select distinct t.triggerid from triggers t,functions f where f.itemid=".$row["itemid"]." and f.triggerid=t.triggerid";
			$result2=DBselect($sql);
			while($row2=DBfetch($result2))
			{
				$trigger=get_trigger_by_triggerid($row2["triggerid"]);
// Cannot use add_trigger here
				$description=$trigger["description"];
#				$description=str_replace("%s",$host["host"],$description);	
				$sql="insert into triggers  (description,priority,status,comments,url,value) values ('".addslashes($description)."',".$trigger["priority"].",".$trigger["status"].",'".addslashes($trigger["comments"])."','".addslashes($trigger["url"])."',2)";
				$result4=DBexecute($sql);
				$triggerid=DBinsert_id($result4,"triggers","triggerid");

				$sql="select functionid from functions where triggerid=".$row2["triggerid"]." and itemid=".$row["itemid"];
				$result3=DBselect($sql);
				while($row3=DBfetch($result3))
				{
					$function=get_function_by_functionid($row3["functionid"]);
					$sql="insert into functions (itemid,triggerid,function,parameter) values ($itemid,$triggerid,'".$function["function"]."','".$function["parameter"]."')";
					$result4=DBexecute($sql);
					$functionid=DBinsert_id($result4,"functions","functionid");
					$sql="update triggers set expression='".$trigger["expression"]."' where triggerid=$triggerid";
					DBexecute($sql);
					$trigger["expression"]=str_replace("{".$row3["functionid"]."}","{".$functionid."}",$trigger["expression"]);
					$sql="update triggers set expression='".$trigger["expression"]."' where triggerid=$triggerid";
					DBexecute($sql);
				}
				# Add actions
				$sql="select actionid from actions where scope=0 and triggerid=".$row2["triggerid"];
				$result3=DBselect($sql);
				while($row3=DBfetch($result3))
				{
					$action=get_action_by_actionid($row3["actionid"]);
					$userid=$action["userid"];
					$scope=$action["scope"];
					$severity=$action["severity"];
					$good=$action["good"];
					$delay=$action["delay"];
					$subject=addslashes($action["subject"]);
					$message=addslashes($action["message"]);
					$recipient=$action["recipient"];
					$sql="insert into actions (triggerid, userid, scope, severity, good, delay, subject, message,recipient) values ($triggerid,$userid,$scope,$severity,$good,$delay,'$subject','$message',$recipient)";
//					echo "$sql<br>";
					$result4=DBexecute($sql);
					$actionid=DBinsert_id($result4,"actions","actionid");
				}
			}
		}

		return TRUE;
	}

	function	add_group_to_host($hostid,$newgroup)
	{
		$sql="insert into groups (groupid,name) values (NULL,'$newgroup')";
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}
		
		$groupid=DBinsert_id($result,"groupd","groupid");

		$sql="insert into hosts_groups (hostid,groupid) values ($hostid,$groupid)";
		$result=DBexecute($sql);

		return $result;
	}

	function	update_user_groups($usrgrpid,$users)
	{
		$count=count($users);

		$sql="delete from users_groups where usrgrpid=$usrgrpid";
		DBexecute($sql);

		for($i=0;$i<$count;$i++)
		{
			$sql="insert into users_groups (usrgrpid,userid) values ($usrgrpid,".$users[$i].")";
			DBexecute($sql);
		}
	}

	function	update_host_groups_by_groupid($groupid,$hosts)
	{
		$count=count($hosts);

		$sql="delete from hosts_groups where groupid=$groupid";
		DBexecute($sql);

		for($i=0;$i<$count;$i++)
		{
			$sql="insert into hosts_groups (hostid,groupid) values (".$hosts[$i].",$groupid)";
			DBexecute($sql);
		}
	}

	function	update_host_groups($hostid,$groups)
	{
		$count=count($groups);

		$sql="delete from hosts_groups where hostid=$hostid";
		DBexecute($sql);

		for($i=0;$i<$count;$i++)
		{
			$sql="insert into hosts_groups (hostid,groupid) values ($hostid,".$groups[$i].")";
			DBexecute($sql);
		}
	}

	function	add_host_group($name,$hosts)
	{
		global	$ERROR_MSG;

//		if(!check_right("Host","A",0))
//		{
//			$ERROR_MSG="Insufficient permissions";
//			return 0;
//		}

		$sql="select * from groups where name='$name'";
		$result=DBexecute($sql);
		if(DBnum_rows($result)>0)
		{
			$ERROR_MSG="Group '$name' already exists";
			return 0;
		}

		$sql="insert into groups (name) values ('$name')";
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}
		
		$groupid=DBinsert_id($result,"groups","groupid");

		update_host_groups_by_groupid($groupid,$hosts);

		return $result;
	}

	function	add_user_group($name,$users)
	{
		global	$ERROR_MSG;

		if(!check_right("Host","A",0))
		{
			$ERROR_MSG="Insufficient permissions";
			return 0;
		}

		$sql="select * from usrgrp where name='$name'";
		$result=DBexecute($sql);
		if(DBnum_rows($result)>0)
		{
			$ERROR_MSG="Group '$name' already exists";
			return 0;
		}

		$sql="insert into usrgrp (name) values ('$name')";
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}
		
		$usrgrpid=DBinsert_id($result,"usrgrp","usrgrpid");

		update_user_groups($usrgrpid,$users);

		return $result;
	}

	function	update_host_group($groupid,$name,$users)
	{
		global	$ERROR_MSG;

//		if(!check_right("Host","U",0))
//		{
//			$ERROR_MSG="Insufficient permissions";
//			return 0;
//		}

		$sql="select * from groups where name='$name' and groupid<>$groupid";
		$result=DBexecute($sql);
		if(DBnum_rows($result)>0)
		{
			$ERROR_MSG="Group '$name' already exists";
			return 0;
		}

		$sql="update groups set name='$name' where groupid=$groupid";
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}
		
		update_host_groups_by_groupid($groupid,$users);

		return $result;
	}

	function	update_user_group($usrgrpid,$name,$users)
	{
		global	$ERROR_MSG;

		if(!check_right("Host","U",0))
		{
			$ERROR_MSG="Insufficient permissions";
			return 0;
		}

		$sql="select * from usrgrp where name='$name' and usrgrpid<>$usrgrpid";
		$result=DBexecute($sql);
		if(DBnum_rows($result)>0)
		{
			$ERROR_MSG="Group '$name' already exists";
			return 0;
		}

		$sql="update usrgrp set name='$name' where usrgrpid=$usrgrpid";
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}
		
		update_user_groups($usrgrpid,$users);

		return $result;
	}
		
		
	# Add Host definition

	function	add_host($host,$port,$status,$useip,$ip,$host_templateid,$newgroup,$groups)
	{
		global	$ERROR_MSG;

		if(!check_right("Host","A",0))
		{
			$ERROR_MSG="Insufficient permissions";
			return 0;
		}

 		if (!eregi('^([0-9a-zA-Z\_\.-]+)$', $host, &$arr)) 
		{
			$ERROR_MSG="Hostname should contain 0-9a-zA-Z_.- characters only";
			return 0;
		}

		$sql="select * from hosts where host='$host'";
		$result=DBexecute($sql);
		if(DBnum_rows($result)>0)
		{
			$ERROR_MSG="Host '$host' already exists";
			return 0;
		}

		if( isset($useip) && ($useip=="on") )
		{
			$useip=1;
		}
		else
		{
			$useip=0;
		}
		$sql="insert into hosts (host,port,status,useip,ip,disable_until) values ('$host',$port,$status,$useip,'$ip',0)";
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}
		
		$hostid=DBinsert_id($result,"hosts","hostid");

		if($host_templateid != 0)
		{
			$result=add_using_host_template($hostid,$host_templateid);
		}
		update_host_groups($hostid,$groups);
		if($newgroup != "")
		{
			add_group_to_host($hostid,$newgroup);
		}

		update_profile("HOST_PORT",$port);
		
		return	$result;
	}

	function	update_host($hostid,$host,$port,$status,$useip,$ip,$newgroup,$groups)
	{
		global	$ERROR_MSG;

		if(!check_right("Host","U",$hostid))
		{
			$ERROR_MSG="Insufficient permissions";
			return 0;
		}

 		if (!eregi('^([0-9a-zA-Z\_\.-]+)$', $host, &$arr)) 
		{
			$ERROR_MSG="Hostname should contain 0-9a-zA-Z_.- characters only";
			return 0;
		}

		$sql="select * from hosts where host='$host' and hostid<>$hostid";
		$result=DBexecute($sql);
		if(DBnum_rows($result)>0)
		{
			$ERROR_MSG="Host '$host' already exists";
			return 0;
		}


		if($useip=="on")
		{
			$useip=1;
		}
		else
		{
			$useip=0;
		}
		$sql="update hosts set host='$host',port=$port,useip=$useip,ip='$ip' where hostid=$hostid";
		$result=DBexecute($sql);
		update_host_status($hostid, $status);
		update_host_groups($hostid,$groups);
		if($newgroup != "")
		{
			add_group_to_host($hostid,$newgroup);
		}
		return	$result;
	}

	# Delete Media definition by mediatypeid

	function	delete_media_by_mediatypeid($mediatypeid)
	{
		$sql="delete from media where mediatypeid=$mediatypeid";
		return	DBexecute($sql);
	}

	# Delete alrtes by mediatypeid

	function	delete_alerts_by_mediatypeid($mediatypeid)
	{
		$sql="delete from alerts where mediatypeid=$mediatypeid";
		return	DBexecute($sql);
	}

	# Delete media type

	function	delete_mediatype($mediatypeid)
	{
		delete_media_by_mediatypeid($mediatypeid);
		delete_alerts_by_mediatypeid($mediatypeid);
		$sql="delete from media_type where mediatypeid=$mediatypeid";
		return	DBexecute($sql);
	}

	# Update media type

	function	update_mediatype($mediatypeid,$type,$description,$smtp_server,$smtp_helo,$smtp_email,$exec_path)
	{
		$sql="update media_type set type=$type,description='$description',smtp_server='$smtp_server',smtp_helo='$smtp_helo',smtp_email='$smtp_email',exec_path='$exec_path' where mediatypeid=$mediatypeid";
		return	DBexecute($sql);
	}

	# Add Media type

	function	add_mediatype($type,$description,$smtp_server,$smtp_helo,$smtp_email,$exec_path)
	{
		$sql="insert into media_type (type,description,smtp_server,smtp_helo,smtp_email,exec_path) values ($type,'$description','$smtp_server','$smtp_helo','$smtp_email','$exec_path')";
		return	DBexecute($sql);
	}

	# Add Media definition

	function	add_media( $userid, $mediatypeid, $sendto, $severity, $active)
	{
		$c=count($severity);
		$s=0;
		for($i=0;$i<$c;$i++)
		{
			$s=$s|pow(2,(int)$severity[$i]);
		}
		$sql="insert into media (userid,mediatypeid,sendto,active,severity) values ($userid,'$mediatypeid','$sendto',$active,$s)";
		return	DBexecute($sql);
	}

	# Update Media definition

	function	update_media($mediaid, $userid, $mediatypeid, $sendto, $severity, $active)
	{
		$c=count($severity);
		$s=0;
		for($i=0;$i<$c;$i++)
		{
			$s=$s|pow(2,(int)$severity[$i]);
		}
		$sql="update media set userid=$userid, mediatypeid=$mediatypeid, sendto='$sendto', active=$active,severity=$s where mediaid=$mediaid";
		return	DBexecute($sql);
	}

	# Delete Media definition

	function	delete_media($mediaid)
	{
		$sql="delete from media where mediaid=$mediaid";
		return	DBexecute($sql);
	}

	# Delete Media definition by userid

	function	delete_media_by_userid($userid)
	{
		$sql="delete from media where userid=$userid";
		return	DBexecute($sql);
	}

	function	delete_profiles_by_userid($userid)
	{
		$sql="delete from profiles where userid=$userid";
		return	DBexecute($sql);
	}

	# Update configuration

//	function	update_config($smtp_server,$smtp_helo,$smtp_email,$alarm_history,$alert_history)
	function	update_config($alarm_history,$alert_history)
	{
		global	$ERROR_MSG;

		if(!check_right("Configuration of Zabbix","U",0))
		{
			$ERROR_MSG="Insufficient permissions";
			return	0;
		}

//		$sql="update config set smtp_server='$smtp_server',smtp_helo='$smtp_helo',smtp_email='$smtp_email',alarm_history=$alarm_history,alert_history=$alert_history";
		$sql="update config set alarm_history=$alarm_history,alert_history=$alert_history";
		return	DBexecute($sql);
	}


	# Activate Media

	function	activate_media($mediaid)
	{
		$sql="update media set active=0 where mediaid=$mediaid";
		return	DBexecute($sql);
	}

	# Disactivate Media

	function	disactivate_media($mediaid)
	{
		$sql="update media set active=1 where mediaid=$mediaid";
		return	DBexecute($sql);
	}

	function	delete_sysmaps_host_by_hostid($hostid)
	{
		$sql="select shostid from sysmaps_hosts where hostid=$hostid";
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			$sql="delete from sysmaps_links where shostid1=".$row["shostid"]." or shostid2".$row["shostid"];
			DBexecute($sql);
		}
		$sql="delete from sysmaps_hosts where hostid=$hostid";
		return DBexecute($sql);
	}

	# Delete Host from sysmap definition

	function	delete_sysmaps_host($shostid)
	{
		$sql="delete from sysmaps_links where shostid1=$shostid or shostid2=$shostid";
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}
		$sql="delete from sysmaps_hosts where shostid=$shostid";
		return	DBexecute($sql);
	}

	function	delete_groups_by_hostid($hostid)
	{
		$sql="select groupid from hosts_groups where hostid=$hostid";
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			$sql="delete from hosts_groups where hostid=$hostid and groupid=".$row["groupid"];
			DBexecute($sql);
			$sql="select count(*) as count from hosts_groups where groupid=".$row["groupid"];
			$result2=DBselect($sql);
			$row2=DBfetch($result2);
			if($row2["count"]==0)
			{
				$sql="delete from groups where groupid=".$row["groupid"];
				DBexecute($sql);
			}
		}
	}

	# Delete Host

	function	delete_host($hostid)
	{
		global $DB_TYPE;

		if($DB_TYPE=="MYSQL")
		{
			$sql="update hosts set status=".HOST_STATUS_DELETED.",host=concat(host,\" [DELETED]\") where hostid=$hostid";
		}
		else
		{
			$sql="update hosts set status=".HOST_STATUS_DELETED.",host=host||' [DELETED]' where hostid=$hostid";
		}
		return	DBexecute($sql);

//		$sql="select itemid from items where hostid=$hostid";
//		$result=DBselect($sql);
//		if(!$result)
//		{
//			return	$result;
//		}
//		for($i=0;$i<DBnum_rows($result);$i++)
//		{
//			if(!delete_item(DBget_field($result,$i,0)))
//			{
//				return	FALSE;
//			}
//		}
//		delete_groups_by_hostid($hostid);
//		$sql="delete from hosts where hostid=$hostid";
//		return	DBexecute($sql);
	}

	# Delete User permission

	function	delete_permission($rightid)
	{
		$sql="delete from rights where rightid=$rightid";
		return DBexecute($sql);
	}

	function	delete_user_group($usrgrpid)
	{
		global	$ERROR_MSG;

		$sql="delete from users_groups where usrgrpid=$usrgrpid";
		DBexecute($sql);
		$sql="delete from usrgrp where usrgrpid=$usrgrpid";
		return DBexecute($sql);
	}

	function	delete_host_group($groupid)
	{
		global	$ERROR_MSG;

		$sql="delete from hosts_groups where groupid=$groupid";
		DBexecute($sql);
		$sql="delete from groups where groupid=$groupid";
		return DBexecute($sql);
	}

	# Delete User definition

	function	delete_user($userid)
	{
		global	$ERROR_MSG;

		$sql="select * from users where userid=$userid and alias='guest'";
		$result=DBselect($sql);
		if(DBnum_rows($result) == 1)
		{
			$ERROR_MSG="Cannot delete user 'guest'";
			return	0;
		}

		delete_media_by_userid($userid);
		delete_actions_by_userid($userid);
		delete_rights_by_userid($userid);
		delete_profiles_by_userid($userid);

		$sql="delete from users_groups where userid=$userid";
		DBexecute($sql);
		$sql="delete from users where userid=$userid";
		return DBexecute($sql);
	}

	function	show_table_h_delimiter()
	{
//		echo "</font>";
		cr();
		echo "</td>";
		cr();
		echo "<td colspan=1 bgcolor=FFFFFF align=center valign=\"top\">";
		cr();
//		echo "	<font size=2>";
		cr();
	}

	function	show_table2_h_delimiter()
	{
//		echo "</font>";
		cr();
		echo "</td>";
		cr();
		echo "<td colspan=1 bgcolor=CCCCCC align=left valign=\"top\">";
		cr();
//		echo "	<font size=-1>";
		cr();
	}

	function	show_table_v_delimiter()
	{
//		echo "</font>";
		cr();
		echo "</td>";
		cr();
		echo "</tr>";
		cr();
		echo "<tr>";
		cr();
		echo "<td colspan=1 bgcolor=FFFFFF align=center valign=\"top\">";
		cr();
//		echo "<font size=2>";
		cr();
	}

	function	show_table2_v_delimiter()
	{
//		echo "</font>";
		cr();
		echo "</td>";
		cr();
		echo "</tr>";
		cr();
		echo "<tr>";
		cr();
		echo "<td colspan=1 bgcolor=CCCCCC align=left valign=\"top\">";
		cr();
//		echo "<font size=-1>";
		cr();
	}

	function	show_table3_v_delimiter()
	{
//		echo "</font>";
		cr();
		echo "</td>";
		cr();
		echo "</tr>";
		cr();
		echo "<tr>";
		cr();
		echo "<td colspan=1 bgcolor=99AABB align=left valign=\"top\">";
		cr();
//		echo "<font size=-1>";
		cr();
	}


	function	show_table2_v_delimiter2()
	{
//		echo "</font>";
		cr();
		echo "</td>";
		cr();
		echo "</tr>";
		cr();
		echo "<tr>";
		cr();
		echo "<td colspan=2 bgcolor=\"99AABB\" align=right valign=\"top\">";
		cr();
//		echo "<font size=-1>";
		cr();
	}



	function	show_table2_header_begin()
	{
		echo "<table border=0 align=center cellspacing=0 cellpadding=0 width=50% bgcolor=000000>";
		cr();
		echo "<tr>";
		cr();
		echo "<td valign=\"top\">";
		cr();
		echo "<table width=100% border=0 cellspacing=1 cellpadding=3>";
		cr();
		echo "<tr>";
		cr();
		echo "<td colspan=2 bgcolor=99AABB align=center valign=\"top\">";
		cr();
//		echo "	<font size=+1>";
		cr();
	}

	function	show_table_header_begin()
	{
		echo "<table border=0 align=center cellspacing=0 cellpadding=0 width=100% bgcolor=000000>";
		cr();
		echo "<tr>";
		cr();
		echo "<td valign=\"top\">";
		cr();
		echo "<table width=100% border=0 cellspacing=1 cellpadding=3>";
		cr();
		echo "<tr>";
		cr();
		echo "<td colspan=1 bgcolor=99AABB align=center valign=\"top\">";
		cr();
//		echo "	<font size=+1>";
		cr();
	}

	function	show_table2_header_end()
	{
//		echo "	</font>";
		cr();
		echo "</td>";
		cr();
		echo "</tr>";
		cr();
		echo "</table>";
		cr();
		echo "</td>";
		cr();
		echo "</tr>";
		cr();
		echo "</table>";
		cr();
	}

	function	show_table_header_end()
	{
//		echo "	</font>";
		cr();
		echo "</td>";
		cr();
		echo "</tr>";
		cr();
		echo "</table>";
		cr();
		echo "</td>";
		cr();
		echo "</tr>";
		cr();
		echo "</table>";
		cr();
	}

	function	show_table_header($title)
	{
		show_table_header_begin();
		cr();
		echo $title;
		cr();
		show_table_header_end();
		cr();
	}

	function	insert_time_navigator($itemid,$period,$from)
	{
		$descr=array("January","February","March","April","May","June",
			"July","August","September","October","November","December");
		$sql="select min(clock),max(clock) from history where itemid=$itemid";
		$result=DBselect($sql);

		if(DBnum_rows($result) == 0)
		{
			$min=time(NULL);
			$max=time(NULL);
		}
		else
		{
			$min=DBget_field($result,0,0);
			$max=DBget_field($result,0,1);
		}

		$now=time()-3600*$from-$period;

		$year_min=date("Y",$min);   
		$year_max=date("Y",$max);

		$year_now=date("Y",$now);
		$month_now=date("m",$now);
		$day_now=date("d",$now);
		$hour_now=date("H",$now);

		echo "<form method=\"put\" action=\"history.php\">";
		echo "<input name=\"itemid\" type=\"hidden\" value=$itemid size=8>";
		echo "<input name=\"action\" type=\"hidden\" value=\"showhistory\" size=8>";

		echo "Year";
		echo "<select name=\"year\">";
	        for($i=$year_min;$i<=$year_max;$i++)
	        {
			if($i==$year_now)
			{	
	               		echo "<option value=\"$i\" selected>$i";
			}
			else
			{
	               		echo "<option value=\"$i\">$i";
			}
	        }
		echo "</select>";

		echo "Month";
		echo "<select name=\"month\">";
	        for($i=1;$i<=12;$i++)
	        {
			if($i==$month_now)
			{	
	               		echo "<option value=\"$i\" selected>".$descr[$i-1];
			}
			else
			{
	               		echo "<option value=\"$i\">".$descr[$i-1];
			}
	        }
		echo "</select>";

		echo "Day";
		echo "<select name=\"day\">";
	        for($i=1;$i<=31;$i++)
	        {
			if($i==$day_now)
			{	
	               		echo "<option value=\"$i\" selected>$i";
			}
			else
			{
	               		echo "<option value=\"$i\">$i";
			}
	        }
		echo "</select>";

		echo "Hour";
		echo "<select name=\"hour\">";
	        for($i=0;$i<=23;$i++)
	        {
			if($i==$hour_now)
			{	
	               		echo "<option value=\"$i\" selected>$i";
			}
			else
			{
	               		echo "<option value=\"$i\">$i";
			}
	        }
		echo "</select>";

		echo "Period:";
		echo "<select name=\"period\">";
		if($period==3600)
		{
			echo "<option value=\"3600\" selected>1 hour";
		}
		else
		{
			echo "<option value=\"3600\">1 hour";
		}
		if($period==10800)
		{
			echo "<option value=\"10800\" selected>3 hours";
		}
		else
		{
			echo "<option value=\"10800\">3 hours";
		}
		if($period==21600)
		{
			echo "<option value=\"21600\" selected>6 hours";
		}
		else
		{
			echo "<option value=\"21600\">6 hours";
		}
		echo "</select>";

		echo "<input class=\"button\" type=\"submit\" name=\"action\" value=\"showhistory\">";

		echo "</form>";
	}

	# Show History Graph

	function	show_history($itemid,$from,$period,$diff)
	{
		if (!isset($from))
		{
			$from=0;
			$till="NOW";
		}
		else
		{
			$till=time(NULL)-$from*3600;
			$till=date("d M - H:i:s",$till);   
		}

		if (!isset($period))
		{ 
			$period=3600;
			show_table_header("TILL $till (LAST HOUR)");
		}
		else
		{
			$tmp=$period/3600;
			show_table_header("TILL $till ($tmp HOURs)");
		}
//		echo("<hr>");
		echo "<center>";
		echo "<TABLE BORDER=0 COLS=4 WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
		echo "<TR BGCOLOR=#EEEEEE>";
		echo "<TR BGCOLOR=#DDDDDD>";
		echo "<TD ALIGN=CENTER>";

		if($diff==0)
		{
			echo "<script language=\"JavaScript\">";
			echo "if (navigator.appName == \"Microsoft Internet Explorer\")";
			echo "{";
			echo " document.write(\"<IMG SRC='chart.php?itemid=$itemid&period=$period&from=$from&width=\"+(document.body.clientWidth-108)+\"'>\")";
			echo "}";
			echo "else if (navigator.appName == \"Netscape\")";
			echo "{";
			echo " document.write(\"<IMG SRC='chart.php?itemid=$itemid&period=$period&from=$from&width=\"+(document.width-108)+\"'>\")";
			echo "}";
			echo "else";
			echo "{";
			echo " document.write(\"<IMG SRC='chart.php?itemid=$itemid&period=$period&from=$from'>\")";
			echo "}";
			echo "</script>";
		}
		else
		{
			echo "<script language=\"JavaScript\">";
			echo "if (navigator.appName == \"Microsoft Internet Explorer\")";
			echo "{";
			echo " document.write(\"<IMG SRC='chart_diff.php?itemid=$itemid&period=$period&from=$from&width=\"+(document.body.clientWidth-108)+\"'>\")";
			echo "}";
			echo "else if (navigator.appName == \"Netscape\")";
			echo "{";
			echo " document.write(\"<IMG SRC='chart_diff.php?itemid=$itemid&period=$period&from=$from&width=\"+(document.width-108)+\"'>\")";
			echo "}";
			echo "else";
			echo "{";
			echo " document.write(\"<IMG SRC='chart_diff.php?itemid=$itemid&period=$period&from=$from'>\")";
			echo "}";
			echo "</script>";
		}
		echo "</TD>";
		echo "</TR>";
		echo "</TABLE>";
		echo "</center>";
		echo("<hr>");
		insert_time_navigator($itemid,$period,$from);
		echo("<hr>");
	}

	# Show history
	function	show_freehist($itemid,$period)
	{

		echo "<br>";
		show_table2_header_begin();
		echo "Choose period";

		show_table2_v_delimiter();
		echo "<form method=\"get\" action=\"history.php\">";
		echo "<input name=\"itemid\" type=\"hidden\" value=$itemid size=8>";
		echo "Period in seconds";
		show_table2_h_delimiter();
		echo "<input name=\"period\" value=\"7200\" size=8>";

		show_table2_v_delimiter();
		echo "From (in hours)";
		show_table2_h_delimiter();
		echo "<input name=\"from\" value=\"24\" size=8>";

		show_table2_v_delimiter2();
		echo "Press ";
		echo "<input class=\"button\" type=\"submit\" name=\"action\" value=\"showvalues\"> to see values in plain text";

		show_table2_header_end();

		show_footer();
	}

	# Show in plain text
	function	show_plaintxt($itemid,$period)
	{
		show_table2_header_begin();
		echo "Data in plain text format";

		show_table2_v_delimiter();
		echo "<form method=\"get\" action=\"history.php\">";
		echo "<input name=\"itemid\" type=\"Hidden\" value=$itemid size=8>";
		echo "<input name=\"itemid\" type=\"Hidden\" value=$itemid size=8>";
		echo "From: (yyyy/mm/dd - HH:MM)";
		show_table2_h_delimiter();
		echo "<input name=\"fromyear\" value=\"",date("Y"),"\" size=5>/";
		echo "<input name=\"frommonth\" value=\"",date("m"),"\" size=3>/";
		echo "<input name=\"fromday\" value=\"",date("d"),"\" size=3> - ";
		echo "<input name=\"fromhour\" value=\"0\" size=3>:";
		echo "<input name=\"frommin\" value=\"00\" size=3>";

		show_table2_v_delimiter();
		echo "Till: (yyyy/mm/dd - HH:MM)";
		show_table2_h_delimiter();
		echo "<input name=\"tillyear\" value=\"",date("Y"),"\" size=5>/";
		echo "<input name=\"tillmonth\" value=\"",date("m"),"\" size=3>/";
		echo "<input name=\"tillday\" value=\"",date("d"),"\" size=3> - ";
		echo "<input name=\"tillhour\" value=\"23\" size=3>:";
		echo "<input name=\"tillmin\" value=\"59\" size=3>";

		show_table2_v_delimiter2();
		echo "Press to see data in ";
		echo "<input class=\"button\" type=\"submit\" name=\"action\" value=\"plaintext\">";

		show_table2_header_end();

		show_footer();
	}

	# Insert form for Item information
	function	insert_item_form()
	{
		global  $HTTP_GET_VARS;

		$description=@iif(isset($HTTP_GET_VARS["description"]),$HTTP_GET_VARS["description"],"");
		$key=@iif(isset($HTTP_GET_VARS["key"]),$HTTP_GET_VARS["key"],"");
		$host=@iif(isset($HTTP_GET_VARS["host"]),$HTTP_GET_VARS["host"],"");
		$port=@iif(isset($HTTP_GET_VARS["port"]),$HTTP_GET_VARS["port"],10000);
		$delay=@iif(isset($HTTP_GET_VARS["delay"]),$HTTP_GET_VARS["delay"],30);
		$history=@iif(isset($HTTP_GET_VARS["history"]),$HTTP_GET_VARS["history"],365);
		$status=@iif(isset($HTTP_GET_VARS["status"]),$HTTP_GET_VARS["status"],0);
		$type=@iif(isset($HTTP_GET_VARS["type"]),$HTTP_GET_VARS["type"],0);
		$snmp_community=@iif(isset($HTTP_GET_VARS["snmp_community"]),$HTTP_GET_VARS["snmp_community"],"public");
		$snmp_oid=@iif(isset($HTTP_GET_VARS["snmp_oid"]),$HTTP_GET_VARS["snmp_oid"],"interfaces.ifTable.ifEntry.ifInOctets.1");
		$value_type=@iif(isset($HTTP_GET_VARS["value_type"]),$HTTP_GET_VARS["value_type"],0);
		$trapper_hosts=@iif(isset($HTTP_GET_VARS["trapper_hosts"]),$HTTP_GET_VARS["trapper_hosts"],"");
		$snmp_port=@iif(isset($HTTP_GET_VARS["snmp_port"]),$HTTP_GET_VARS["snmp_port"],161);
		$units=@iif(isset($HTTP_GET_VARS["units"]),$HTTP_GET_VARS["units"],'');
		$multiplier=@iif(isset($HTTP_GET_VARS["multiplier"]),$HTTP_GET_VARS["multiplier"],0);
		$hostid=@iif(isset($HTTP_GET_VARS["hostid"]),$HTTP_GET_VARS["hostid"],0);
		$delta=@iif(isset($HTTP_GET_VARS["delta"]),$HTTP_GET_VARS["delta"],0);

		if(isset($HTTP_GET_VARS["register"])&&($HTTP_GET_VARS["register"] == "change"))
		{
			$result=DBselect("select i.description, i.key_, h.host, h.port, i.delay, i.history, i.status, i.type, i.snmp_community,i.snmp_oid,i.value_type,i.trapper_hosts,i.snmp_port,i.units,i.multiplier,h.hostid,i.delta from items i,hosts h where i.itemid=".$HTTP_GET_VARS["itemid"]." and h.hostid=i.hostid");
		
			$description=DBget_field($result,0,0);
			$key=DBget_field($result,0,1);
			$host=DBget_field($result,0,2);
			$port=DBget_field($result,0,3);
			$delay=DBget_field($result,0,4);
			$history=DBget_field($result,0,5);
			$status=DBget_field($result,0,6);
			$type=iif(isset($HTTP_GET_VARS["type"]),isset($HTTP_GET_VARS["type"]),DBget_field($result,0,7));
			$snmp_community=DBget_field($result,0,8);
			$snmp_oid=DBget_field($result,0,9);
			$value_type=DBget_field($result,0,10);
			$trapper_hosts=DBget_field($result,0,11);
			$snmp_port=DBget_field($result,0,12);
			$units=DBget_field($result,0,13);
			$multiplier=DBget_field($result,0,14);
			$hostid=DBget_field($result,0,15);
			$delta=DBget_field($result,0,16);
		}

		echo "<br>";

		show_table2_header_begin();
		echo "Item";
 
		show_table2_v_delimiter();
		echo "<form method=\"get\" action=\"items.php\">";
		if(isset($HTTP_GET_VARS["itemid"]))
		{
			echo "<input class=\"biginput\" name=\"itemid\" type=hidden value=".$HTTP_GET_VARS["itemid"].">";
		}
		echo "Description";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"description\" value=\"$description\"size=40>";

		show_table2_v_delimiter();
		echo "Host";
		show_table2_h_delimiter();
		echo "<select class=\"biginput\" name=\"hostid\" value=\"3\">";
	        $result=DBselect("select hostid,host from hosts order by host");
	        for($i=0;$i<DBnum_rows($result);$i++)
	        {
	                $hostid_=DBget_field($result,$i,0);
	                $host_=DBget_field($result,$i,1);
			if($hostid==$hostid_)
			{
	                	echo "<option value=\"$hostid_\" selected>$host_";
			}
			else
			{
	                	echo "<option value=\"$hostid_\">$host_";
			}
	        }
		echo "</select>";

		show_table2_v_delimiter();
		echo "Type";
		show_table2_h_delimiter();
		echo "<SELECT class=\"biginput\" NAME=\"type\" value=\"$type\" size=\"1\" onChange=\"submit()\">";
		echo "<OPTION VALUE=\"0\"";
		if($type==0) echo "SELECTED";
		echo ">Zabbix agent";
		echo "<OPTION VALUE=\"3\"";
		if($type==3) echo "SELECTED";
		echo ">Simple check";
		echo "<OPTION VALUE=\"1\"";
		if($type==1) echo "SELECTED";
		echo ">SNMPv1 agent";
		echo "<OPTION VALUE=\"4\"";
		if($type==4) echo "SELECTED";
		echo ">SNMPv2 agent";
		echo "<OPTION VALUE=\"2\"";
		if($type==2) echo "SELECTED";
		echo ">Zabbix trapper";
		echo "<OPTION VALUE=\"5\"";
		if($type==5) echo "SELECTED";
		echo ">Zabbix internal";
		echo "</SELECT>";

		if(($type==1)||($type==4))
		{ 
			show_table2_v_delimiter();
			echo nbsp("SNMP community");
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"snmp_community\" value=\"$snmp_community\" size=16>";

			show_table2_v_delimiter();
			echo nbsp("SNMP OID");
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"snmp_oid\" value=\"$snmp_oid\" size=40>";

			show_table2_v_delimiter();
			echo nbsp("SNMP port");
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"snmp_port\" value=\"$snmp_port\" size=5>";
		}
		else
		{
			echo "<input class=\"biginput\" name=\"snmp_community\" type=hidden value=\"$snmp_community\">";
			echo "<input class=\"biginput\" name=\"snmp_oid\" type=hidden value=\"$snmp_oid\">";
			echo "<input class=\"biginput\" name=\"snmp_port\" type=hidden value=\"$snmp_port\">";
		}

		show_table2_v_delimiter();
		echo "Key";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"key\" value=\"$key\" size=40>";

		show_table2_v_delimiter();
		echo "Units";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"units\" value=\"$units\" size=10>";

		show_table2_v_delimiter();
		echo "Multiplier";
		show_table2_h_delimiter();
		echo "<SELECT class=\"biginput\" NAME=\"multiplier\" value=\"$multiplier\" size=\"1\">";
		echo "<OPTION VALUE=\"0\"";
		if($multiplier==0) echo "SELECTED";
		echo ">-";
		echo "<OPTION VALUE=\"1\"";
		if($multiplier==1) echo "SELECTED";
		echo ">K (1024)";
		echo "<OPTION VALUE=\"2\"";
		if($multiplier==2) echo "SELECTED";
		echo ">M (1024^2)";
		echo "<OPTION VALUE=\"3\"";
		if($multiplier==3) echo "SELECTED";
		echo ">G (1024^3)";
		echo "</SELECT>";

		if($type!=2)
		{
			show_table2_v_delimiter();
			echo nbsp("Update interval (in sec)");
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"delay\" value=\"$delay\" size=5>";
		}
		else
		{
			echo "<input class=\"biginput\" name=\"delay\" type=hidden value=\"$delay\">";
		}

		show_table2_v_delimiter();
		echo nbsp("Keep history (in days)");
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"history\" value=\"$history\" size=8>";

		show_table2_v_delimiter();
		echo "Status";
		show_table2_h_delimiter();
		echo "<SELECT class=\"biginput\" NAME=\"status\" value=\"$status\" size=\"1\">";
		echo "<OPTION VALUE=\"0\"";
		if($status==0) echo "SELECTED";
		echo ">Monitored";
		echo "<OPTION VALUE=\"1\"";
		if($status==1) echo "SELECTED";
		echo ">Disabled";
#		echo "<OPTION VALUE=\"2\"";
#		if($status==2) echo "SELECTED";
#		echo ">Trapper";
		echo "<OPTION VALUE=\"3\"";
		if($status==3) echo "SELECTED";
		echo ">Not supported";
		echo "</SELECT>";

		show_table2_v_delimiter();
		echo nbsp("Type of information");
		show_table2_h_delimiter();
		echo "<SELECT class=\"biginput\" NAME=\"value_type\" value=\"$value_type\" size=\"1\">";
		echo "<OPTION VALUE=\"0\"";
		if($value_type==0) echo "SELECTED";
		echo ">Numeric";
		echo "<OPTION VALUE=\"1\"";
		if($value_type==1) echo "SELECTED";
		echo ">Character";
		echo "</SELECT>";

		show_table2_v_delimiter();
		echo nbsp("Store value");
		show_table2_h_delimiter();
		echo "<SELECT class=\"biginput\" NAME=\"delta\" value=\"$delta\" size=\"1\">";
		echo "<OPTION VALUE=\"0\"";
		if($delta==0) echo "SELECTED";
		echo ">As is";
		echo "<OPTION VALUE=\"1\"";
		if($delta==1) echo "SELECTED";
		echo ">Delta";
		echo "</SELECT>";

		if($type==2)
		{
			show_table2_v_delimiter();
			echo nbsp("Allowed hosts");
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"trapper_hosts\" value=\"$trapper_hosts\" size=40>";
		}
		else
		{
			echo "<input class=\"biginput\" name=\"trapper_hosts\" type=hidden value=\"$trapper_hosts\">";
		}
 
		show_table2_v_delimiter2();
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add\">";
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add to all hosts\" onClick=\"return Confirm('Add item to all hosts?');\">";
		if(isset($HTTP_GET_VARS["itemid"]))
		{
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete\" onClick=\"return Confirm('Delete selected item?');\">";
		}
 
		show_table2_header_end();
?>
<?php
?>
</TR>
</TABLE>

</CENTER>
</FORM>

</BODY>
</HTML>
<?php
	}

	# Insert form for Host Groups
	function	insert_hostgroups_form($groupid)
	{
		global  $HTTP_GET_VARS;

		if(isset($groupid))
		{
			$groupid=get_group_by_groupid($groupid);
	
			$name=$groupid["name"];
		}
		else
		{
			$name="";
		}

		show_table2_header_begin();
		echo "Host group";

		show_table2_v_delimiter();
		echo "<form method=\"get\" action=\"hosts.php\">";
		if(isset($HTTP_GET_VARS["groupid"]))
		{
			echo "<input name=\"groupid\" type=\"hidden\" value=\"".$HTTP_GET_VARS["groupid"]."\" size=8>";
		}
		echo "Group name";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"name\" value=\"$name\" size=30>";

		show_table2_v_delimiter();
		echo "Hosts";
		show_table2_h_delimiter();
		echo "<select multiple class=\"biginput\" name=\"hosts[]\" size=\"5\">";
		$result=DBselect("select distinct hostid,host from hosts order by host");
		while($row=DBfetch($result))
		{
			if(isset($HTTP_GET_VARS["groupid"]))
			{
				$sql="select count(*) as count from hosts_groups where hostid=".$row["hostid"]." and groupid=".$HTTP_GET_VARS["groupid"];
				$result2=DBselect($sql);
				$row2=DBfetch($result2);
				if($row2["count"]==0)
				{
					echo "<option value=\"".$row["hostid"]."\">".$row["host"];
				}
				else
				{
					echo "<option value=\"".$row["hostid"]."\" selected>".$row["host"];
				}
			}
			else
			{
				echo "<option value=\"".$row["hostid"]."\">".$row["host"];
			}
		}
		echo "</select>";

		show_table2_v_delimiter2();
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add group\">";
		if(isset($HTTP_GET_VARS["groupid"]))
		{
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update group\">";
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete group\" onClick=\"return Confirm('Delete selected group?');\">";
		}
		echo "</form>";
		show_table2_header_end();
	}

	# Insert form for User Groups
	function	insert_usergroups_form($usrgrpid)
	{
		global  $HTTP_GET_VARS;

		if(isset($usrgrpid))
		{
			$usrgrp=get_usergroup_by_usrgrpid($usrgrpid);
	
			$name=$usrgrp["name"];
		}
		else
		{
			$name="";
		}

		show_table2_header_begin();
		echo "User group";

		show_table2_v_delimiter();
		echo "<form method=\"get\" action=\"users.php\">";
		if(isset($usrgrpid))
		{
			echo "<input name=\"usrgrpid\" type=\"hidden\" value=\"$usrgrpid\" size=8>";
		}
		echo "Group name";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"name\" value=\"$name\" size=30>";

		show_table2_v_delimiter();
		echo "Users";
		show_table2_h_delimiter();
		echo "<select multiple class=\"biginput\" name=\"users[]\" size=\"5\">";
		$result=DBselect("select distinct userid,alias from users order by alias");
		while($row=DBfetch($result))
		{
			if(isset($HTTP_GET_VARS["usrgrpid"]))
			{
				$sql="select count(*) as count from users_groups where userid=".$row["userid"]." and usrgrpid=".$HTTP_GET_VARS["usrgrpid"];
				$result2=DBselect($sql);
				$row2=DBfetch($result2);
				if($row2["count"]==0)
				{
					echo "<option value=\"".$row["userid"]."\">".$row["alias"];
				}
				else
				{
					echo "<option value=\"".$row["userid"]."\" selected>".$row["alias"];
				}
			}
			else
			{
				echo "<option value=\"".$row["userid"]."\">".$row["alias"];
			}
		}
		echo "</select>";

		show_table2_v_delimiter2();
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add group\">";
		if(isset($HTTP_GET_VARS["usrgrpid"]))
		{
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update group\">";
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete group\" onClick=\"return Confirm('Delete selected group?');\">";
		}
		echo "</form>";
		show_table2_header_end();
	}

	# Insert form for User permissions
	function	insert_permissions_form($userid)
	{
		echo "<br>";

		show_table2_header_begin();
		echo "New permission";

		show_table2_v_delimiter();
		echo "<form method=\"get\" action=\"users.php\">";
		if(isset($userid))
		{
			echo "<input name=\"userid\" type=\"hidden\" value=\"$userid\" size=8>";
		}
		echo "Resource";
		show_table2_h_delimiter();
		echo "<select class=\"biginput\" name=\"right\">";
		echo "<option value=\"Configuration of Zabbix\">Configuration of Zabbix";
		echo "<option value=\"Default permission\">Default permission";
		echo "<option value=\"Graph\">Graph";
		echo "<option value=\"Host\">Host";
		echo "<option value=\"Screen\">Screen";
		echo "<option value=\"Service\">IT Service";
		echo "<option value=\"Item\">Item";
		echo "<option value=\"Network map\">Network map";
		echo "<option value=\"Trigger comment\">Trigger's comment";
		echo "<option value=\"User\">User";
		echo "</select>";

		show_table2_v_delimiter();
		echo "Permission";
		show_table2_h_delimiter();
		echo "<select class=\"biginput\" name=\"permission\">";
		echo "<option value=\"R\">Read-only";
		echo "<option value=\"U\">Read-write";
		echo "<option value=\"H\">Hide";
		echo "<option value=\"A\">Add";
		echo "</select>";

		show_table2_v_delimiter();
		echo "Resource ID (0 for all)";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"id\" value=\"0\" size=4>";

		show_table2_v_delimiter2();
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add permission\">";
		show_table2_header_end();
	}

	function	insert_login_form()
	{
		global	$HTTP_GET_VARS;

		show_table2_header_begin();
		echo "Login";

		show_table2_v_delimiter();
		echo "<form method=\"post\" action=\"index.php\">";

		echo "Login name";
		show_table2_h_delimiter();
//		echo "<input name=\"name\" value=\"".$HTTP_GET_VARS["name"]."\" size=20>";
		echo "<input class=\"biginput\" name=\"name\" value=\"\" size=20>";

		show_table2_v_delimiter();
		echo "Password";
		show_table2_h_delimiter();
//		echo "<input type=\"password\" name=\"password\" value=\"$password\" size=20>";
		echo "<input class=\"biginput\" type=\"password\" name=\"password\" value=\"\" size=20>";

		show_table2_v_delimiter2();
		echo "<input class=\"button\" class=\"button\" type=\"submit\" name=\"register\" value=\"Enter\">";
		show_table2_header_end();
	}


	# Insert form for User
	function	insert_user_form($userid)
	{
		if(isset($userid))
		{
			$result=DBselect("select u.alias,u.name,u.surname,u.passwd from users u where u.userid=$userid");
	
			$alias=DBget_field($result,0,0);
			$name=DBget_field($result,0,1);
			$surname=DBget_field($result,0,2);
#			$password=DBget_field($result,0,3);
			$password="";
		}
		else
		{
			$alias="";
			$name="";
			$surname="";
			$password="";
		}

		show_table2_header_begin();
		echo "User";

		show_table2_v_delimiter();
		echo "<form method=\"get\" action=\"users.php\">";
		if(isset($userid))
		{
			echo "<input class=\"biginput\" name=\"userid\" type=\"hidden\" value=\"$userid\" size=8>";
		}
		echo "Alias";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"alias\" value=\"$alias\" size=20>";

		show_table2_v_delimiter();
		echo "Name";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"name\" value=\"$name\" size=20>";

		show_table2_v_delimiter();
		echo "Surname";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"surname\" value=\"$surname\" size=20>";

		show_table2_v_delimiter();
		echo "Password";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" type=\"password\" name=\"password1\" value=\"$password\" size=20>";

		show_table2_v_delimiter();
		echo nbsp("Password (once again)");
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" type=\"password\" name=\"password2\" value=\"$password\" size=20>";

		show_table2_v_delimiter2();
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add\">";
		if(isset($userid))
		{
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete\" onClick=\"return Confirm('Delete selected user?');\">";
		}

		show_table2_header_end();
	}

	# Insert form for Problem
	function	insert_problem_form($problemid)
	{
		echo "<br>";

		show_table2_header_begin();
		echo "Problem definition";
		show_table2_v_delimiter();
		echo "<form method=\"post\" action=\"helpdesk.php\">";
		echo "<input name=\"problemid\" type=hidden value=$problemid size=8>";
		echo "Description";
		show_table2_h_delimiter();
		echo "<input name=\"description\" value=\"$description\" size=70>";

		show_table2_v_delimiter();
		echo "Severity";
		show_table2_h_delimiter();
		echo "<SELECT NAME=\"priority\" size=\"1\">";
		echo "<OPTION VALUE=\"0\" "; if($priority==0) echo "SELECTED"; echo ">Not classified";
		echo "<OPTION VALUE=\"1\" "; if($priority==1) echo "SELECTED"; echo ">Information";
		echo "<OPTION VALUE=\"2\" "; if($priority==2) echo "SELECTED"; echo ">Warning";
		echo "<OPTION VALUE=\"3\" "; if($priority==3) echo "SELECTED"; echo ">Average";
		echo "<OPTION VALUE=\"4\" "; if($priority==4) echo "SELECTED"; echo ">High";
		echo "<OPTION VALUE=\"5\" "; if($priority==5) echo "SELECTED"; echo ">Disaster";
		echo "</SELECT>";

		show_table2_v_delimiter();
		echo "Status";
		show_table2_h_delimiter();
		echo "<SELECT NAME=\"status\" value=\"$status\" size=\"1\">";
		echo "<OPTION VALUE=\"0\"";
		if($status==0) echo "SELECTED";
		echo ">Opened";
		echo "<OPTION VALUE=\"1\"";
		if($status==1) echo "SELECTED";
		echo ">Closed";
		echo "</SELECT>";

		show_table2_v_delimiter2();
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add\">";
		if(isset($problemid))
		{
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete\">";
		}

		show_table2_header_end();
	}

	# Insert form for Trigger
	function	insert_trigger_form($hostid,$triggerid)
	{
		if(isset($triggerid))
		{
			$trigger=get_trigger_by_triggerid($triggerid);
	
			$expression=explode_exp($trigger["expression"],0);
			$description=htmlspecialchars(stripslashes($trigger["description"]));
			$priority=$trigger["priority"];
			$status=$trigger["status"];
			$comments=$trigger["comments"];
			$url=$trigger["url"];
		}
		else
		{
			$expression="";
			$description="";
			$priority=0;
			$status=0;
			$comments="";
			$url="";
		}
		
		echo "<br>";

		show_table2_header_begin();
		echo "Trigger configuration";
 
		show_table2_v_delimiter();
		if(isset($hostid))
		{
			echo "<form method=\"get\" action=\"triggers.php?hostid=$hostid\">";
		}
		else
		{
			echo "<form method=\"get\" action=\"triggers.php\">";
		}
		echo "<input class=\"biginput\" name=\"triggerid\" type=hidden value=$triggerid size=8>";
		echo "Description";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"description\" value=\"$description\" size=70>";

		show_table2_v_delimiter();
		echo "Expression";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"expression\" value=\"$expression\" size=70>";

		show_table2_v_delimiter();
		echo "Severity";
		show_table2_h_delimiter();
		echo "<SELECT class=\"biginput\" NAME=\"priority\" size=\"1\">";
		echo "<OPTION VALUE=\"0\" "; if($priority==0) echo "SELECTED"; echo ">Not classified";
		echo "<OPTION VALUE=\"1\" "; if($priority==1) echo "SELECTED"; echo ">Information";
		echo "<OPTION VALUE=\"2\" "; if($priority==2) echo "SELECTED"; echo ">Warning";
		echo "<OPTION VALUE=\"3\" "; if($priority==3) echo "SELECTED"; echo ">Average";
		echo "<OPTION VALUE=\"4\" "; if($priority==4) echo "SELECTED"; echo ">High";
		echo "<OPTION VALUE=\"5\" "; if($priority==5) echo "SELECTED"; echo ">Disaster";
		echo "</SELECT>";

		show_table2_v_delimiter();
		echo "Comments";
		show_table2_h_delimiter();
 		echo "<TEXTAREA class=\"biginput\" NAME=\"comments\" COLS=70 ROWS=\"7\" WRAP=\"SOFT\">$comments</TEXTAREA>";

		show_table2_v_delimiter();
		echo "URL";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"url\" value=\"$url\" size=70>";

		show_table2_v_delimiter();
		echo "Disabled";
		show_table2_h_delimiter();
		echo "<INPUT TYPE=\"CHECKBOX\" ";
		if($status==1) { echo " CHECKED "; }
		echo "NAME=\"disabled\"  VALUE=\"true\">";

 
		show_table2_v_delimiter2();
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add\">";
		if(isset($triggerid))
		{
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete\" onClick=\"return Confirm('Delete trigger?');\">";
		}

		if(isset($triggerid))
		{
			show_table2_v_delimiter();
			echo "The trigger depends on";
			show_table2_h_delimiter();
			$sql="select t.triggerid,t.description from triggers t,trigger_depends d where t.triggerid=d.triggerid_up and d.triggerid_down=$triggerid";
			$result1=DBselect($sql);
			echo "<SELECT class=\"biginput\" NAME=\"dependency\" size=\"1\">";
			for($i=0;$i<DBnum_rows($result1);$i++)
			{
				$depid=DBget_field($result1,$i,0);
//				$depdescr=DBget_field($result1,$i,1);
//				if( strstr($depdescr,"%s"))
//				{
					$depdescr=expand_trigger_description($depid);
//				}
				echo "<OPTION VALUE=\"$depid\">$depdescr";
			}
			echo "</SELECT>";

			show_table2_v_delimiter();
			echo "New dependency";
			show_table2_h_delimiter();
			$sql="select t.triggerid,t.description from triggers t where t.triggerid!=$triggerid order by t.description";
			$result=DBselect($sql);
			echo "<SELECT class=\"biginput\" NAME=\"depid\" size=\"1\">";
			for($i=0;$i<DBnum_rows($result);$i++)
			{
				$depid=DBget_field($result,$i,0);
//				$depdescr=DBget_field($result,$i,1);

//				if( strstr($depdescr,"%s"))
//				{
					$depdescr=expand_trigger_description($depid);
//				}
				echo "<OPTION VALUE=\"$depid\">$depdescr";
			}
			echo "</SELECT>";

			show_table2_v_delimiter2();
			if(isset($triggerid))
			{
				echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add dependency\">";
				if(DBnum_rows($result1)>0)
				{
					echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete dependency\">";
				}
			}
		}

		echo "</form>";
		show_table2_header_end();
	}

	function	show_footer()
	{
		global $USER_DETAILS;

		echo "<br>";
		echo "<table border=0 cellpadding=1 cellspacing=0 width=100% align=center>";
		echo "<tr>";
		echo "<td bgcolor=\"#000000\">";
		echo "<table border=\"0\" cellspacing=\"0\" cellpadding=\"3\" width=100% bgcolor=\"#666666\">";
		echo "<tr><td align=center>";
		echo "<a href=\"http://www.zabbix.com\">ZABBIX 1.0</a> Copyright 2000-2004 by <a href=\"mailto:alex@gobbo.caves.lv\">Alexei Vladishev</a>";
		echo "</td>";
		echo "<td align=right width=15%>";
		echo "| Connected as ".$USER_DETAILS["alias"];
		echo "</td>";
		echo "</tr>";
		echo "</table>";
		echo "</td>";
		echo "</tr>";
		echo "</table>";

		echo "</body>";
	}

	function	get_stats()
	{
	        $result=DBselect("select count(*) from history");
		$stat["history_count"]=DBget_field($result,0,0);

	        $result=DBselect("select count(*) from trends");
		$stat["trends_count"]=DBget_field($result,0,0);

	        $result=DBselect("select count(*) from alarms");
		$stat["alarms_count"]=DBget_field($result,0,0);

	        $result=DBselect("select count(*) from alerts");
		$stat["alerts_count"]=DBget_field($result,0,0);

	        $result=DBselect("select count(*) from triggers");
		$stat["triggers_count"]=DBget_field($result,0,0);

	        $result=DBselect("select count(*) from triggers where status=0");
		$stat["triggers_count_enabled"]=DBget_field($result,0,0);

	        $result=DBselect("select count(*) from triggers where status=1");
		$stat["triggers_count_disabled"]=DBget_field($result,0,0);

	        $result=DBselect("select count(*) from items");
		$stat["items_count"]=DBget_field($result,0,0);

	        $result=DBselect("select count(*) from items where status=0");
		$stat["items_count_active"]=DBget_field($result,0,0);

	        $result=DBselect("select count(*) from items where status=1");
		$stat["items_count_not_active"]=DBget_field($result,0,0);

	        $result=DBselect("select count(*) from items where status=3");
		$stat["items_count_not_supported"]=DBget_field($result,0,0);

	        $result=DBselect("select count(*) from items where status=2");
		$stat["items_count_trapper"]=DBget_field($result,0,0);

	        $result=DBselect("select count(*) from hosts");
		$stat["hosts_count"]=DBget_field($result,0,0);

	        $result=DBselect("select count(*) from hosts where status=0");
		$stat["hosts_count_monitored"]=DBget_field($result,0,0);

	        $result=DBselect("select count(*) from hosts where status=1");
		$stat["hosts_count_not_monitored"]=DBget_field($result,0,0);

	        $result=DBselect("select count(*) from hosts where status=3");
		$stat["hosts_count_template"]=DBget_field($result,0,0);

	        $result=DBselect("select count(*) from users");
		$stat["users_count"]=DBget_field($result,0,0);


		return $stat;
	}

	function	get_last_service_value($serviceid,$clock)
	{
	       	$sql="select count(*),max(clock) from service_alarms where serviceid=$serviceid and clock<=$clock";
//		echo " $sql<br>";
		
	        $result=DBselect($sql);
		if(DBget_field($result,0,0)>0)
		{
	       		$sql="select value from service_alarms where serviceid=$serviceid and clock=".DBget_field($result,0,1);
		        $result2=DBselect($sql);
// Assuring that we get very latest service value. There could be several with the same timestamp
//			$value=DBget_field($result2,0,0);
			for($i=0;$i<DBnum_rows($result2);$i++)
			{
				$value=DBget_field($result2,$i,0);
			}
		}
		else
		{
			$value=0;
		}
		return $value;
	}

	function	calculate_service_availability($serviceid,$period_start,$period_end)
	{
	       	$sql="select count(*),min(clock),max(clock) from service_alarms where serviceid=$serviceid and clock>=$period_start and clock<=$period_end";
		
		$sql="select clock,value from service_alarms where serviceid=$serviceid and clock>=$period_start and clock<=$period_end";
		$result=DBselect($sql);

// -1,0,1
		$state=get_last_service_value($serviceid,$period_start);
		$problem_time=0;
		$ok_time=0;
		$time=$period_start;
		for($i=0;$i<DBnum_rows($result);$i++)
		{
			$clock=DBget_field($result,$i,0);
			$value=DBget_field($result,$i,1);

			$diff=$clock-$time;

			$time=$clock;
#state=0,1 (OK), >1 PROBLEMS 

			if($state<=1)
			{
				$ok_time+=$diff;
				$state=$value;
			}
			else
			{
				$problem_time+=$diff;
				$state=$value;
			}
		}
//		echo $problem_time,"-",$ok_time,"<br>";

		if(DBnum_rows($result)==0)
		{
			if(get_last_service_value($serviceid,$period_start)<=1)
			{
				$ok_time=$period_end-$period_start;
			}
			else
			{
				$problem_time=$period_end-$period_start;
			}
		}
		else
		{
			if($state<=1)
			{
				$ok_time=$ok_time+$period_end-$time;
			}
			else
			{
				$problem_time=$problem_time+$period_end-$time;
			}
		}

//		echo $problem_time,"-",$ok_time,"<br>";

		$total_time=$problem_time+$ok_time;
		if($total_time==0)
		{
			$ret["problem_time"]=0;
			$ret["ok_time"]=0;
			$ret["problem"]=0;
			$ret["ok"]=0;
		}
		else
		{
			$ret["problem_time"]=$problem_time;
			$ret["ok_time"]=$ok_time;
			$ret["problem"]=(100*$problem_time)/$total_time;
			$ret["ok"]=(100*$ok_time)/$total_time;
		}
		return $ret;
	}

	// If $period_start=$period_end=0, then take maximum period
	function	calculate_availability($triggerid,$period_start,$period_end)
	{
		if(($period_start==0)&&($period_end==0))
		{
	        	$sql="select count(*),min(clock),max(clock) from alarms where triggerid=$triggerid";
		}
		else
		{
	        	$sql="select count(*),min(clock),max(clock) from alarms where triggerid=$triggerid and clock>=$period_start and clock<=$period_end";
		}
//		echo $sql,"<br>";

		
	        $result=DBselect($sql);
		if(DBget_field($result,0,0)>0)
		{
			$min=DBget_field($result,0,1);
			$max=DBget_field($result,0,2);
		}
		else
		{
			if(($period_start==0)&&($period_end==0))
			{
				$max=time();
				$min=$max-24*3600;
			}
			else
			{
				$ret["true_time"]=0;
				$ret["false_time"]=0;
				$ret["unknown_time"]=0;
				$ret["true"]=0;
				$ret["false"]=0;
				$ret["unknown"]=100;
				return $ret;
			}
		}

		$sql="select clock,value from alarms where triggerid=$triggerid and clock>=$min and clock<=$max";
//		echo " $sql<br>";
		$result=DBselect($sql);

//		echo $sql,"<br>";

// -1,0,1
		$state=-1;
		$true_time=0;
		$false_time=0;
		$unknown_time=0;
		$time=$min;
		if(($period_start==0)&&($period_end==0))
		{
			$max=time();
		}
		for($i=0;$i<DBnum_rows($result);$i++)
		{
			$clock=DBget_field($result,$i,0);
			$value=DBget_field($result,$i,1);

			$diff=$clock-$time;

			$time=$clock;

			if($state==-1)
			{
				$state=$value;
				if($state == 0)
				{
					$false_time+=$diff;
				}
				if($state == 1)
				{
					$true_time+=$diff;
				}
				if($state == 2)
				{
					$unknown_time+=$diff;
				}
			}
			else if($state==0)
			{
				$false_time+=$diff;
				$state=$value;
			}
			else if($state==1)
			{
				$true_time+=$diff;
				$state=$value;
			}
			else if($state==2)
			{
				$unknown_time+=$diff;
				$state=$value;
			}
		}

		if(DBnum_rows($result)==0)
		{
			$false_time=$max-$min;
		}
		else
		{
			if($state==0)
			{
				$false_time=$false_time+$max-$time;
			}
			elseif($state==1)
			{
				$true_time=$true_time+$max-$time;
			}
			elseif($state==3)
			{
				$unknown_time=$unknown_time+$max-$time;
			}

		}
//		echo "$true_time $false_time $unknown_time";

		$total_time=$true_time+$false_time+$unknown_time;
		if($total_time==0)
		{
			$ret["true_time"]=0;
			$ret["false_time"]=0;
			$ret["unknown_time"]=0;
			$ret["true"]=0;
			$ret["false"]=0;
			$ret["unknown"]=100;
		}
		else
		{
			$ret["true_time"]=$true_time;
			$ret["false_time"]=$false_time;
			$ret["unknown_time"]=$unknown_time;
			$ret["true"]=(100*$true_time)/$total_time;
			$ret["false"]=(100*$false_time)/$total_time;
			$ret["unknown"]=(100*$unknown_time)/$total_time;
		}
		return $ret;
	}

	function	get_resource_name($permission,$id)
	{
		$res="-";
		if($permission=="Graph")
		{
			if(isset($id)&&($id!=0))
			{
				$host=get_graph_by_graphid($id);
				$res=$host["name"];
			}
			else
			{
				$res="All graphs";
			}
		}
		else if($permission=="Host")
		{
			if(isset($id)&&($id!=0))
			{
				$host=get_host_by_hostid($id);
				$res=$host["host"];
			}
			else
			{
				$res="All hosts";
			}
		}
		else if($permission=="Screen")
		{
			if(isset($id)&&($id!=0))
			{
				$screen=get_screen_by_screenid($id);
				$res=$screen["name"];
			}
			else
			{
				$res="All hosts";
			}
		}
		else if($permission=="Item")
		{
			if(isset($id)&&($id!=0))
			{
				$item=get_item_by_itemid($id);
				$host=get_host_by_hostid($item["hostid"]);
				$res=$host["host"].":".$item["description"];
			}
			else
			{
				$res="All items";
			}
		}
		else if($permission=="User")
		{
			if(isset($id)&&($id!=0))
			{
				$user=get_user_by_userid($id);
				$res=$user["alias"];
			}
			else
			{
				$res="All users";
			}
		}
		else if($permission=="Network map")
		{
			if(isset($id)&&($id!=0))
			{
				$user=get_map_by_sysmapid($id);
				$res=$user["name"];
			}
			else
			{
				$res="All maps";
			}
		}
		return $res;
	}

	function	get_profile($idx,$default_value)
	{
		global $USER_DETAILS;

		if($USER_DETAILS["alias"]=="guest")
		{
			return $default_value;
		}

		$sql="select value from profiles where userid=".$USER_DETAILS["userid"]." and idx='$idx'";
		$result=DBselect($sql);

		if(DBnum_rows($result)==0)
		{
			return $default_value;
		}
		else
		{
			$row=DBfetch($result);
			return $row["value"];
		}
	}

	function	update_profile($idx,$value)
	{
		global $USER_DETAILS;

		if($USER_DETAILS["alias"]=="guest")
		{
			return;
		}

		$sql="select value from profiles where userid=".$USER_DETAILS["userid"]." and idx='$idx'";
		$result=DBselect($sql);

		if(DBnum_rows($result)==0)
		{
			$sql="insert into profiles (userid,idx,value) values (".$USER_DETAILS["userid"].",'$idx','$value')";
			DBexecute($sql);
		}
		else
		{
			$row=DBfetch($result);
			$sql="update profiles set value='$value' where userid=".$USER_DETAILS["userid"]." and idx='$idx'";
			DBexecute($sql);
		}
	}


        function        add_screen($name,$cols,$rows)
        {
                global  $ERROR_MSG;

                if(!check_right("Screen","A",0))
                {
                        $ERROR_MSG="Insufficient permissions";
                        return 0;
                }

                $sql="insert into screens (name,cols,rows) values ('$name',$cols,$rows)";
                return  DBexecute($sql);
        }

        function        update_screen($screenid,$name,$cols,$rows)
        {
                global  $ERROR_MSG;

                if(!check_right("Screen","U",0))
                {
                        $ERROR_MSG="Insufficient permissions";
                        return 0;
                }

                $sql="update screens set name='$name',cols=$cols,rows=$rows where screenid=$screenid";
                return  DBexecute($sql);
        }

        function        delete_screen($screenid)
        {
                $sql="delete from screens_items where screenid=$screenid";
                $result=DBexecute($sql);
                if(!$result)
                {
                        return  $result;
                }
                $sql="delete from screens where screenid=$screenid";
                return  DBexecute($sql);
        }

        function add_screen_item($resource,$screenid,$x,$y,$resourceid,$width,$height)
        {
                $sql="delete from screens_items where screenid=$screenid and x=$x and y=$y";
                DBexecute($sql);
                $sql="insert into screens_items (resource,screenid,x,y,resourceid,width,height) values ($resource,$screenid,$x,$y,$resourceid,$width,$height)";
                return  DBexecute($sql);
        }

        function update_screen_item($screenitemid,$resource,$resourceid,$width,$height)
        {
                $sql="update screens_items set resource=$resource,resourceid=$resourceid,width=$width,height=$height where screenitemid=$screenitemid";
                return  DBexecute($sql);
        }

        function delete_screen_item($screenitemid)
        {
                $sql="delete from screens_items where screenitemid=$screenitemid";
                return  DBexecute($sql);
        }

        function get_drawtype_description($drawtype)
        {
		if($drawtype==0)
			return "Line";
		if($drawtype==1)
			return "Filled region";
		if($drawtype==2)
			return "Bold line";
		if($drawtype==3)
			return "Dot";
		if($drawtype==4)
			return "Dashed line";
		return "Unknown";
        }

	function insert_confirm_javascript()
	{
		echo "<SCRIPT LANGUAGE=\"JavaScript\">";

		echo "function Confirm(msg)";
		echo "{";
		echo "	if(confirm( msg))";
		echo "	{";
		echo "		return true;";
		echo "	}";
		echo "	else";
		echo "	{";
		echo "		return false;";
		echo "	}";
		echo "}";
		echo "</SCRIPT>";
	}

	function get_map_imagemap($sysmapid)
	{
		$map="\n<map name=links>";
		$result=DBselect("select h.host,sh.shostid,sh.sysmapid,sh.hostid,sh.label,sh.x,sh.y,h.status from sysmaps_hosts sh,hosts h where sh.sysmapid=$sysmapid and h.hostid=sh.hostid");
		for($i=0;$i<DBnum_rows($result);$i++)
		{
			$host=DBget_field($result,$i,0);
			$shostid=DBget_field($result,$i,1);
			$sysmapid=DBget_field($result,$i,2);
			$hostid=DBget_field($result,$i,3);
			$label=DBget_field($result,$i,4);
			$x=DBget_field($result,$i,5);
			$y=DBget_field($result,$i,6);
			$status=DBget_field($result,$i,7);

			if( ($status==0)||($status==2))
			{
				if(function_exists("imagecreatetruecolor")&&@imagecreatetruecolor(1,1))
				{
					$map=$map."\n<area shape=rect coords=$x,$y,".($x+48).",".($y+48)." href=\"tr_status.php?hostid=$hostid&noactions=true&onlytrue=true&compact=true\" alt=\"$host\">";
				}
				else
				{
					$map=$map."\n<area shape=rect coords=$x,$y,".($x+32).",".($y+32)." href=\"tr_status.php?hostid=$hostid&noactions=true&onlytrue=true&compact=true\" alt=\"$host\">";
				}
			}
		}
		$map=$map."\n</map>";
		return $map;
	}

/* Use ImageSetStyle+ImageLIne instead of bugged ImageDashedLine */
	if(function_exists("imagesetstyle"))
	{
		function DashedLine($image,$x1,$y1,$x2,$y2,$color)
		{
// Style for dashed lines
//			$style = array($color, $color, $color, $color, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT);
			$style = array($color, $color, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT);
			ImageSetStyle($image, $style);
			ImageLine($image,$x1,$y1,$x2,$y2,IMG_COLOR_STYLED);
		}
	}
	else
	{
		function DashedLine($image,$x1,$y1,$x2,$y2,$color)
		{
			ImageDashedLine($image,$x1,$y1,$x2,$y2,$color);
		}
	}

?>
