<?
	include 	"include/db.inc";

	$USER_DETAILS	="";
	$ERROR_MSG	="";

	function	check_right($right,$permission,$id)
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


//	The hash has form <md5sum of triggerid>,<sum of priorities>
	function	calc_trigger_hash()
	{
		$priorities=0;
		for($i=0;$i<=5;$i++)
		{
	        	$result=DBselect("select count(*) from triggers t,hosts h,items i,functions f  where t.istrue=1 and f.itemid=i.itemid and h.hostid=i.hostid and t.triggerid=f.triggerid and i.status in (0,2) and t.priority=$i");
			$priorities+=(1000^$i)*DBget_field($result,0,0);
		}
		$triggerids="";
	       	$result=DBselect("select t.triggerid from triggers t,hosts h,items i,functions f  where t.istrue=1 and f.itemid=i.itemid and h.hostid=i.hostid and t.triggerid=f.triggerid and i.status in (0,2)");
		for($i=0;$i<DBnum_rows($result);$i++)
		{
			$triggerids="$triggerids,".DBget_field($result,$i,0);
		}
		$md5sum=md5($triggerids);

		return	"$priorities,$md5sum";
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

//		$sql="select itemid,type,snmp_community,snmp_oid,hostid,description,key_,delay,history,lastdelete, nextcheck,lastvalue, lastclock, prevvalue, status, value_type from items where itemid=$itemid"; 
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

		$sql="select triggerid,expression,description,istrue,priority,lastchange,dep_level,comments,url from triggers where triggerid=$triggerid";
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

		$sql="select smtp_server,smtp_helo,smtp_email,alarm_history,alert_history from config";
		$result=DBselect($sql);

		if(DBnum_rows($result) == 1)
		{
			$config["smtp_server"]=DBget_field($result,0,0);
			$config["smtp_helo"]=DBget_field($result,0,1);
			$config["smtp_email"]=DBget_field($result,0,2);
			$config["alarm_history"]=DBget_field($result,0,3);
			$config["alert_history"]=DBget_field($result,0,4);
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
		echo "<font face='Helvetica' size=+1 color='$color'>";
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
		if (eregi('^([0-9]+)((\.)?)([0-9]*)$', $str, &$arr)) 
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
//		if (eregi('^\{([0-9a-zA-Z\_\.]+)\:([0-9a-zA-Z\_]+)\.((diff)|(min)|(max)|(last)|(nodata))\(([0-9\.]+)\)\}$', $expression, &$arr)) 
		if (eregi('^\{([0-9a-zA-Z[.-.]\_\.]+)\:([]\[0-9a-zA-Z\_\/\.]+)\.((diff)|(min)|(max)|(last)|(prev))\(([0-9\.]+)\)\}$', $expression, &$arr)) 
		{
			$host=$arr[1];
			$key=$arr[2];
			$function=$arr[3];
			$parameter=$arr[9];

			$sql="select count(*) from hosts h,items i where h.host='$host' and i.key_='$key' and h.hostid=i.hostid";
			$result=DBselect($sql);
			if(DBget_field($result,0,0)!=1)
			{
				$ERROR_MSG="No such host ($host) or monitored parameter ($key)";
				return -1;
			}

			if(validate_float($parameter)!=0)
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
// 	Replace all <float> <sign> <float> with 0
//			echo "Expression:$expression<br>";
			$arr="";
			if (eregi('^((.)*)([0-9\.]+)([\&\|\>\<\=]{1})([0-9\.]+)((.)*)$', $expression, &$arr)) 
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
				$expression=$arr[1]."0".$arr[6];
	                }
			else
			{
				$ok=1;
			}


// 	Replace all (float) with 0
//			echo "Expression2:$expression<br>";
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
		global	$reconnect;	
		global	$page;
		global	$PHP_AUTH_USER,$PHP_AUTH_PW;
		global	$USER_DETAILS;

//		if(!isset($PHP_AUTH_USER))
//		{
//			$PHP_AUTH_USER="guest";
//			$PHP_AUTH_PW="";
//		}

		$passwd=md5($PHP_AUTH_PW);

		$sql="select u.userid,u.alias,u.name,u.surname from users u where u.alias='$PHP_AUTH_USER' and u.passwd='$passwd'";
		$result=DBselect($sql);
		if(DBnum_rows($result)==1)
		{
			$USER_DETAILS["userid"]=DBget_field($result,0,0);
			$USER_DETAILS["alias"]=DBget_field($result,0,1);
			$USER_DETAILS["name"]=DBget_field($result,0,2);
			$USER_DETAILS["surname"]=DBget_field($result,0,3);
		}

		if(DBnum_rows($result)!=1)
		{
			Header("WWW-authenticate: basic realm=\"Zabbix\"");
			Header("HTTP/1.0 401 Unauthorized");

			echo "Try to contact Zabbix Administrator to get additional rights :-)\n";
			exit;
		}
	}

	# Header for HTML pages

	function	show_header($title,$refresh,$nomenu)
	{
		global $page;

		check_authorisation();

?>
	<!doctype html public "-//W3C//DTD HTML 3.2//EN">
	<html>
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
	<meta name="Author" content="Alexei Vladishev">
<?
	if($refresh!=0)
	{
		echo "<meta http-equiv=\"refresh\" content=\"$refresh\">";
		echo "<title>$title [refreshed every $refresh sec]</title>";
	}
	else
	{
		echo "<title>$title</title>";
	}
	echo "<head>";
?>
	<body bgcolor="#778899" text="#000000" link="#000000" vlink="#000000" topmargin=4 leftmargin=0 rightmargin=0 marginheight=4>
<?
		if($nomenu == 0)
		{
?>

	<table border=0 cellspacing=0 cellpadding=0 width="100%" bgcolor=000000>
	<tr>
	<td valign="top">
		<table width="100%" border=0 cellspacing=1 cellpadding=3>
		<tr>
		<td colspan=1 bgcolor=FFFFFF align=center valign="top" width="15%">
			<font face="Arial,Helvetica" size=2>
<?
				if(check_right("Host","R",0))
				{
					echo "<a href=\"latest.php\">";
				}
				if( 	($page["file"]=="latest.php") ||
					($page["file"]=="history.php"))
				{
					echo "<b>[LATEST VALUES]</b></a>";
				}
				else
				{
					echo "LATEST VALUES</a>";
				}
?>
			</font>
		</td>
		<td colspan=1 bgcolor=FFFFFF align=center valign="top" width="10%">
			<font face="Arial,Helvetica" size=2>
<?
				if(check_right("Host","R",0))
				{
					echo "<a href=\"tr_status.php?notitle=true&onlytrue=true&noactions=true&compact=true\">";
				}
				if($page["file"]=="tr_status.php")
				{
					echo "<b>[STATUS OF TRIGGERS]</b></a>";
				}
				else
				{
					echo "STATUS OF TRIGGERS</a>";
				}
?>
			</font>
		</td>
		<td colspan=1 bgcolor=FFFFFF align=center valign="top" width="10%">
			<font face="Arial,Helvetica" size=2>
<?
				if(check_right("Host","R",0))
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
			</font>
		</td>
		<td colspan=1 bgcolor=FFFFFF align=center valign="top" width="10%">
			<font face="Arial,Helvetica" size=2>
				<a href="latestalarms.php">
<?
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
			</font>
		</td>
		<td colspan=1 bgcolor=FFFFFF align=center valign="top" width="15%">
			<font face="Arial,Helvetica" size=2>
				<a href="alerts.php">
<?
				if($page["file"]=="alerts.php")
				{
					echo "<b>[ALERT HISTORY]</b></a>";
				}
				else
				{
					echo "ALERT HISTORY</a>";
				}
?>
			</font>
		</td>
		<td colspan=1 bgcolor=FFFFFF align=center valign="top" width="15%">
			<font face="Arial,Helvetica" size=2>
				<a href="maps.php">
<?
				if($page["file"]=="maps.php")
				{
					echo "<b>[NETWORK MAPS]</b></a>";
				}
				else
				{
					echo "NETWORK MAPS</a>";
				}
?>
			</font>
		</td>
		<td colspan=1 bgcolor=FFFFFF align=center valign="top" width="15%">
			<font face="Arial,Helvetica" size=2>
				<a href="charts.php">
<?
				if($page["file"]=="charts.php")
				{
					echo "<b>[GRAPHS]</b></a>";
				}
				else
				{
					echo "GRAPHS</a>";
				}
?>
			</font>
		</td>

		<td colspan=1 bgcolor=FFFFFF align=center valign="top" width="15%">
			<font face="Arial,Helvetica" size=2>
<?
				if($page["file"]=="srv_status.php")
				{
					echo "<b>[IT SERVICES]</b></a>";
				}
				else
				{
					echo "IT SERVICES</a>";
				}
?>
			</font>
		</td>

		</tr>
		<tr>
		<td colspan=2 bgcolor=FFFFFF align=center valign="top" width="15%">
			<font face="Arial,Helvetica" size=2>
				<a href="index.php">
<?
				if($page["file"]=="index.php")
				{
					echo "<b>[HOME]</b></a>";
				}
				else
				{
					echo "HOME</a>";
				}
?>
			</font>
		</td>
		<td colspan=2 bgcolor=FFFFFF align=center valign="top" width="15%">
			<font face="Arial,Helvetica" size=2>
				<a href="about.php">
<?
				if($page["file"]=="about.php")
				{
					echo "<b>[ABOUT]</b></a>";
				}
				else
				{
					echo "ABOUT</a>";
				}
?>
			</font>
		</td>
		<td colspan=2 bgcolor=FFFFFF align=center valign="top" width="15%">
			<font face="Arial,Helvetica" size=2>
				<a href="report1.php">
<?
				if($page["file"]=="report1.php")
				{
					echo "<b>[STATUS OF ZABBIX]</b></a>";
				}
				else
				{
					echo "STATUS OF ZABBIX</a>";
				}
?>
			</font>
		</td>
		<td colspan=2 bgcolor=FFFFFF align=center valign="top" width="15%">
			<font face="Arial,Helvetica" size=2>
				<a href="report2.php">
<?
				if($page["file"]=="report2.php")
				{
					echo "<b>[AVAILABILITY REPORT]</b></a>";
				}
				else
				{
					echo "AVAILABILITY REPORT</a>";
				}
?>
			</font>
		</td>
		</tr>
		<tr>
		<td colspan=1 bgcolor=FFFFFF align=center valign="top" width="15%">
			<font face="Arial,Helvetica" size=2>
<?
				if(check_right("Configuration of Zabbix","R",0))
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
			</font>
		</td>
		<td colspan=1 bgcolor=FFFFFF align=center valign="top" width="10%">
			<font face="Arial,Helvetica" size=2>
<?
				if(check_right("User","R",0))
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
			</font>
		</td>
		<td colspan=1 bgcolor=FFFFFF align=center valign="top" width="10%">
			<font face="Arial,Helvetica" size=2>
<?
				if(check_right("Host","R",0))
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
			</font>
		</td>
		<td colspan=1 bgcolor=FFFFFF align=center valign="top" width="10%">
			<font face="Arial,Helvetica" size=2>
<?
				if(check_right("Host","R",0))
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
			</font>
		</td>
		<td colspan=1 bgcolor=FFFFFF align=center valign="top" width="15%">
			<font face="Arial,Helvetica" size=2>
<?
				if(check_right("Host","R",0))
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
			</font>
		</td>
		<td colspan=1 bgcolor=FFFFFF align=center valign="top" width="15%">
			<font face="Arial,Helvetica" size=2>
				<a href="sysmaps.php">
<?
				if(	($page["file"]=="sysmaps.php")||
					($page["file"]=="sysmap.php"))
				{
					echo "<b>[NETWORK MAPS]</b></a>";
				}
				else
				{
					echo "NETWORK MAPS</a>";
				}
?>
			</font>
		</td>
		<td colspan=1 bgcolor=FFFFFF align=center valign="top" width="15%">
			<font face="Arial,Helvetica" size=2>
				<a href="graphs.php">
<?
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
			</font>
		</td>

		<td colspan=1 bgcolor=FFFFFF align=center valign="top" width="15%">
			<font face="Arial,Helvetica" size=2>
<?
				if($page["file"]=="services.php")
				{
					echo "<b>[IT SERVICES]</b></a>";
				}
				else
				{
					echo "IT SERVICES</a>";
				}
?>
			</font>
		</td>

		</tr>
		</table>
	</td>
	</tr>
	</table>

	<br>
<?
		}
	}

	# Show values in plain text

	function	show_plaintext($itemid, $from, $till)
	{
		$result=DBselect("select clock,value from history where itemid=$itemid and clock>$from and clock<$till order by clock");

		echo "<PRE>";
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
					$sql="insert into functions (itemid,triggerid,function,parameter) values ($itemid,$triggerid,'$function',$parameter)";
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
			if($expression[$i] == ':')
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
		add_alarm($triggerid,3);
		$sql="update triggers set istrue=$status where triggerid=$triggerid";
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

	# Update Host status

	function	update_host_status($hostid,$status)
	{
                global  $ERROR_MSG;
                if(!check_right("Host","U",0))
                {
                        $ERROR_MSG="Insufficient permissions";
                        return 0;
                }

		$sql="update hosts set status=$status where hostid=$hostid";
		return	DBexecute($sql);
	}

	# Update Item definition

	function	update_item($itemid,$description,$key,$hostid,$delay,$history,$status,$type,$snmp_community,$snmp_oid,$value_type)
	{
		global	$ERROR_MSG;

		if(!check_right("Item","U",$itemid))
		{
			$ERROR_MSG="Insufficient permissions";
			return 0;
		}

		$sql="update items set description='$description',key_='$key',hostid=$hostid,delay=$delay,history=$history,lastdelete=0,nextcheck=0,status=$status,type=$type,snmp_community='$snmp_community',snmp_oid='$snmp_oid',value_type=$value_type where itemid=$itemid";
		return	DBexecute($sql);
	}

	# Add Action

	function	add_action( $triggerid, $userid, $good, $delay, $subject, $message )
	{
		global	$ERROR_MSG;

		if(!check_right_on_trigger("A",$triggerid))
		{
                        $ERROR_MSG="Insufficient permissions";
                        return 0;
		}

		$sql="insert into actions (triggerid,userid,good,delay,nextcheck,subject,message) values ($triggerid,$userid,$good,$delay,0,'$subject','$message')";
		return	DBexecute($sql);
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

	function	update_service($serviceid,$name,$triggerid,$linktrigger)
	{
		if( isset($linktrigger)&&($linktrigger=="on") )
		{
			$sql="update services set name='$name',triggerid=$triggerid,status=0 where serviceid=$serviceid";
		}
		else
		{
			$sql="update services set name='$name',triggerid=NULL,status=0 where serviceid=$serviceid";
		}
		return	DBexecute($sql);
	}

	function	add_service($name,$triggerid,$linktrigger)
	{
		if( isset($linktrigger)&&($linktrigger=="on") )
		{
			$sql="insert into services (serviceid,name,triggerid,status) values (NULL,'$name',$triggerid,0)";
		}
		else
		{
			$sql="insert into services (serviceid,name,status) values (NULL,'$name',0)";
		}
		$result=DBexecute($sql);
		return DBinsert_id($result,"services","serviceid");
	}

	function	add_host_to_services($hostid,$serviceid)
	{
		$sql="select t.triggerid,t.description from triggers t,hosts h,items i,functions f where h.hostid=$hostid and h.hostid=i.hostid and i.itemid=f.itemid and f.triggerid=t.triggerid";
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			$serviceid2=add_service($row["description"],$row["triggerid"],"on");
			add_service_link($serviceid2,$serviceid,0);
		}
		return	1;
	}

	function	add_service_link($servicedownid,$serviceupid,$softlink)
	{
		if(isset($softlink) && ($softlink=="on"))
		{
			$sql="insert into services_links (servicedownid,serviceupid,soft) values ($servicedownid,$serviceupid,1)";
		}
		else
		{
			$sql="insert into services_links (servicedownid,serviceupid,soft) values ($servicedownid,$serviceupid,0)";
		}
		return	DBexecute($sql);
	}

	# Update Action

	function	update_action( $actionid, $userid, $good, $delay, $subject, $message )
	{
		$sql="update actions set userid=$userid,good=$good,delay=$delay,nextcheck=0,subject='$subject',message='$message' where actionid=$actionid";
		return	DBexecute($sql);
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

		$sql="delete from history where itemid=$itemid";
		return	DBexecute($sql);
	}

	function	delete_trigger_dependency($triggerid_down,$triggerid_up)
	{

		$sql="select count(*) from trigger_depends where triggerid_down=$triggerid_up and triggerid_up=$triggerid_down";
		$result=DBexecute($sql);
		if(DBget_field($result,0,0)>0)
		{
			return	FALSE;
		}

		$sql="select triggerid_down,triggerid_up from trigger_depends where triggerid_up=$triggerid_up or triggerid_down=$triggerid_down";
		$result=DBexecute($sql);
		for($i=0;$i<DBnum_rows($result);$i++)
		{
			$down=DBget_field($result,$i,0);
			$up=DBget_field($result,$i,1);
			$sql="delete from trigger_depends where triggerid_up=$up and triggerid_down=$down";
			DBexecute($sql);
			$sql="update triggers set dep_level=dep_level-1 where triggerid=$up";
			DBexecute($sql);
		}
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

	function	add_item($description,$key,$hostid,$delay,$history,$status,$type,$snmp_community,$snmp_oid,$value_type)
	{
		global	$ERROR_MSG;

		if(!check_right("Item","A",0))
		{
			$ERROR_MSG="Insufficient permissions";
			return 0;
		}

		$sql="insert into items (description,key_,hostid,delay,history,lastdelete,nextcheck,status,type,snmp_community,snmp_oid,value_type) values ('$description','$key',$hostid,$delay,$history,0,0,$status,$type,'$snmp_community','$snmp_oid',$value_type)";
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
		$sql="delete from actions where triggerid=$triggerid";
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

	# Delete Item definition

	function	delete_item($itemid)
	{
		$sql="select hostid from items where itemid=$itemid";
		$result=DBselect($sql);
		$hostid=DBget_field($result,0,0);
		delete_sysmaps_host($hostid);

		$result=delete_triggers_by_itemid($itemid);
		if(!$result)
		{
			return	$result;
		}
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

	function	add_alarm($triggerid,$istrue)
	{
		$sql="select max(clock) from alarms";
		$result=DBselect($sql);
		$row=DBfetch($result);
		if($row[0]!="")
		{
			$sql="select istrue from alarms where clock=".$row[0];
			$result=DBselect($sql);
			if(DBnum_rows($result) == 1)
			{
				$row=DBfetch($result);
				if($row["istrue"] == $istrue)
				{
					return 0;
				}
			}
		}

		$now=time();
		$sql="insert into alarms(triggerid,clock,istrue) values($triggerid,$now,$istrue)";
		return	DBexecute($sql);
	}

	# Add Trigger definition

	function	add_trigger($expression,$description,$priority,$istrue,$comments,$url)
	{
		global	$ERROR_MSG;

//		if(!check_right("Trigger","A",0))
//		{
//			$ERROR_MSG="Insufficient permissions";
//			return	0;
//		}

		$description=addslashes($description);
		$sql="insert into triggers  (description,priority,istrue,comments,url) values ('$description',$priority,$istrue,'$comments','$url')";
#		echo $sql,"<br>";
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}
 
		$triggerid=DBinsert_id($result,"triggers","triggerid");
#		echo $triggerid,"<br>";
		add_alarm($triggerid,3);
 
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

		$sql="delete from triggers where triggerid=$triggerid";
		return	DBexecute($sql);
	}

	# Update Trigger definition

	function	update_trigger($triggerid,$expression,$description,$priority,$istrue,$comments,$url)
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
		if($istrue != 2)
		{
			$istrue="istrue";
		}
		else
		{
			add_alarm($triggerid,3);
		}
		$sql="update triggers set expression='$expression',description='$description',priority=$priority,istrue=$istrue,comments='$comments',url='$url' where triggerid=$triggerid";
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
		$sql="insert into rights (rightid,userid,name,permission,id) values (NULL,$userid,'$right','$permission',$id)";
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

	# Add Items and Triggers from template

	function	add_from_templates($hostid,$host)
	{
		$result=DBselect("select itemtemplateid,description,key_,delay,value_type from items_template");
		for($i=0;$i<DBnum_rows($result);$i++) 
		{
			$itemtemplateid=DBget_field($result,$i,0);
			$description=DBget_field($result,$i,1);
			$key=DBget_field($result,$i,2);
			$delay=DBget_field($result,$i,3);
			$value_type=DBget_field($result,$i,4);

			$itemid=add_item($description,$key,$hostid,$delay,30*24*3600,0,0,"","",$value_type);

			$result2=DBselect("select triggertemplateid,description,expression from triggers_template where itemtemplateid=$itemtemplateid");
			for($j=0;$j<DBnum_rows($result2);$j++)
			{
				$itemtemplateid=DBget_field($result2,$j,0);
				$description=DBget_field($result2,$j,1);
				$expression=DBget_field($result2,$j,2);

				for($z=0;$z<strlen($expression);$z++)
				{
					if($expression[$z] == ':')
					{
						$expression=substr($expression,0,$z)."$host:$key".substr($expression,$z+1);
						
						break;
					}
				}
				$description=sprintf($description,$host);

				add_trigger($expression,$description,0,0,"","");
			}
		}
		return	TRUE;
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

	function	add_item_to_graph($graphid,$itemid,$color)
	{
		$sql="insert into graphs_items (graphid,itemid,color) values ($graphid,$itemid,'$color')";
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

	function	add_link($sysmapid,$shostid1,$shostid2)
	{
		$sql="insert into sysmaps_links (sysmapid,shostid1,shostid2) values ($sysmapid,$shostid1,$shostid2)";
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
		$sql="select itemid from items where hostid=$host_templateid";
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			$item=get_item_by_itemid($row["itemid"]);
			$itemid=add_item($item["description"],$item["key_"],$hostid,$item["delay"],$item["history"],$item["status"],$item["type"],$item["snmp_community"],$item["snmp_oid"],$item["value_type"]);

			$sql="select distinct t.triggerid from triggers t,functions f where f.itemid=".$row["itemid"]." and f.triggerid=t.triggerid";
			$result2=DBselect($sql);
			while($row2=DBfetch($result2))
			{
				$trigger=get_trigger_by_triggerid($row2["triggerid"]);
// Cannot use add_trigger here
				$sql="insert into triggers  (description,priority,istrue,comments,url) values ('".$trigger["description"]."',".$trigger["priority"].",".$trigger["istrue"].",'".$trigger["comments"]."','".$trigger["url"]."')";
				$result4=DBexecute($sql);
				$triggerid=DBinsert_id($result4,"triggers","triggerid");

				$sql="select functionid from functions where triggerid=".$row2["triggerid"]." and itemid=".$row["itemid"];
				$result3=DBselect($sql);
				while($row3=DBfetch($result3))
				{
					$function=get_function_by_functionid($row3["functionid"]);
					$sql="insert into functions (itemid,triggerid,function,parameter) values ($itemid,$triggerid,'".$function["function"]."',".$function["parameter"].")";
					$result4=DBexecute($sql);
					$functionid=DBinsert_id($result4,"functions","functionid");
					$sql="update triggers set expression='".$trigger["expression"]."' where triggerid=$triggerid";
					DBexecute($sql);
					$trigger["expression"]=str_replace("{".$row3["functionid"]."}","{".$functionid."}",$trigger["expression"]);
					$sql="update triggers set expression='".$trigger["expression"]."' where triggerid=$triggerid";
					DBexecute($sql);
				}
			}
		}

		return SUCCEED;
	}

	# Add Host definition

	function	add_host($host,$port,$status,$template,$useip,$ip,$host_templateid)
	{
		global	$ERROR_MSG;

		if(!check_right("Host","A",0))
		{
			$ERROR_MSG="Insufficient permissions";
			return 0;
		}

		if(($template=="true") && ($host_templateid!=0))
		{
			$ERROR_MSG="Choose either 'Add zabbix_agent parameters' or 'Use host as template' option";
			return	0;
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

		if($template=="true")
		{
			$result=add_from_templates($hostid,$host);
		}
		if($host_templateid != 0)
		{
			$result=add_using_host_template($hostid,$host_templateid);
		}
		return	$result;
	}

	function	update_host($hostid,$host,$port,$status,$useip,$ip)
	{
		global	$ERROR_MSG;

		if(!check_right("Host","U",$hostid))
		{
			$ERROR_MSG="Insufficient permissions";
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
		$sql="update hosts set host='$host',port=$port,status=$status,useip=$useip,ip='$ip' where hostid=$hostid";
		$result=DBexecute($sql);
		return	$result;
	}


	# Add Media definition

	function	add_media( $userid, $type, $sendto)
	{
		$sql="insert into media (userid,type,sendto,active) values ($userid,'$type','$sendto',0)";
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

	# Update configuration

	function	update_config($smtp_server,$smtp_helo,$smtp_email,$alarm_history,$alert_history)
	{
		global	$ERROR_MSG;

		if(!check_right("Configuration of Zabbix","U",0))
		{
			$ERROR_MSG="Insufficient permissions";
			return	0;
		}

		$sql="update config set smtp_server='$smtp_server',smtp_helo='$smtp_helo',smtp_email='$smtp_email',alarm_history=$alarm_history,alert_history=$alert_history";
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

	# Delete Host from sysmap definition

	function	delete_sysmaps_host($shostid)
	{
		$sql="delete from sysmaps_hosts where shostid=$shostid";
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}
		$sql="delete from sysmaps_links where shostid1=$shostid or shostid2=$shostid";
		return	DBexecute($sql);
	}

	# Delete Host

	function	delete_host($hostid)
	{
		$sql="select itemid from items where hostid=$hostid";
		$result=DBselect($sql);
		if(!$result)
		{
			return	$result;
		}
		for($i=0;$i<DBnum_rows($result);$i++)
		{
			if(!delete_item(DBget_field($result,$i,0)))
			{
				return	FALSE;
			}
		}
		$sql="delete from hosts where hostid=$hostid";
		return	DBexecute($sql);
	}

	# Delete User permission

	function	delete_permission($rightid)
	{
		$sql="delete from rights where rightid=$rightid";
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

		$sql="delete from users where userid=$userid";
		return DBexecute($sql);
	}

	function	show_table_h_delimiter()
	{
		echo "</font>";
		cr();
		echo "</td>";
		cr();
		echo "<td colspan=1 bgcolor=FFFFFF align=center valign=\"top\">";
		cr();
		echo "	<font face=\"Arial,Helvetica\" size=2>";
		cr();
	}

	function	show_table2_h_delimiter()
	{
		echo "</font>";
		cr();
		echo "</td>";
		cr();
		echo "<td colspan=1 bgcolor=CCCCCC align=left valign=\"top\">";
		cr();
		echo "	<font face=\"Arial,Helvetica\" size=-1>";
		cr();
	}

	function	show_table_v_delimiter()
	{
		echo "</font>";
		cr();
		echo "</td>";
		cr();
		echo "</tr>";
		cr();
		echo "<tr>";
		cr();
		echo "<td colspan=1 bgcolor=FFFFFF align=center valign=\"top\">";
		cr();
		echo "<font face=\"Arial,Helvetica\" size=2>";
		cr();
	}

	function	show_table2_v_delimiter()
	{
		echo "</font>";
		cr();
		echo "</td>";
		cr();
		echo "</tr>";
		cr();
		echo "<tr>";
		cr();
		echo "<td colspan=1 bgcolor=CCCCCC align=left valign=\"top\">";
		cr();
		echo "<font face=\"Arial,Helvetica\" size=-1>";
		cr();
	}

	function	show_table3_v_delimiter()
	{
		echo "</font>";
		cr();
		echo "</td>";
		cr();
		echo "</tr>";
		cr();
		echo "<tr>";
		cr();
		echo "<td colspan=1 bgcolor=99AABB align=left valign=\"top\">";
		cr();
		echo "<font face=\"Arial,Helvetica\" size=-1>";
		cr();
	}


	function	show_table2_v_delimiter2()
	{
		echo "</font>";
		cr();
		echo "</td>";
		cr();
		echo "</tr>";
		cr();
		echo "<tr>";
		cr();
		echo "<td colspan=2 bgcolor=\"99AABB\" align=right valign=\"top\">";
		cr();
		echo "<font face=\"Arial,Helvetica\" size=-1>";
		cr();
	}



	function	show_table2_header_begin()
	{
		echo "<center>";
		cr();
		echo "<table border=0 cellspacing=0 cellpadding=0 width=\"50%\" bgcolor=000000>";
		cr();
		echo "<tr>";
		cr();
		echo "<td valign=\"top\">";
		cr();
		echo "<table width=\"100%\" border=0 cellspacing=1 cellpadding=3>";
		cr();
		echo "<tr>";
		cr();
		echo "<td colspan=2 bgcolor=99AABB align=center valign=\"top\">";
		cr();
		echo "	<font face=\"Arial,Helvetica\" size=+1>";
		cr();
	}

	function	show_table_header_begin()
	{
		echo "<table border=0 cellspacing=0 cellpadding=0 width=\"100%\" bgcolor=000000>";
		cr();
		echo "<tr>";
		cr();
		echo "<td valign=\"top\">";
		cr();
		echo "<table width=\"100%\" border=0 cellspacing=1 cellpadding=3>";
		cr();
		echo "<tr>";
		cr();
		echo "<td colspan=1 bgcolor=99AABB align=center valign=\"top\">";
		cr();
		echo "	<font face=\"Arial,Helvetica\" size=+1>";
		cr();
	}

	function	show_table2_header_end()
	{
		echo "	</font>";
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
		echo "</center>";
		cr();
	}

	function	show_table_header_end()
	{
		echo "	</font>";
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

	# Show History Graph

	function	show_history($itemid,$from,$period)
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
		echo("<hr>");
		echo "<center>";
		echo "<IMG SRC=\"chart.php?itemid=$itemid&period=$period&from=$from\">"; 
		echo "</center>";
		echo("<hr>");

 
		echo "<center>";
		//  Start of <<< WEEK >>> 
		$tmp=$from+12*14;
		echo("[<A HREF=\"history.php?action=showhistory&itemid=$itemid&from=$tmp&period=$period\">");
		echo("Week back</A>] ");

		//  Start of <<< 12h >>> 
		$tmp=$from+12;
		echo("[<A HREF=\"history.php?action=showhistory&itemid=$itemid&from=$tmp&period=$period\">");
		echo("12h back</A>] ");
		//  End of <<< 12h >>> 
		
		//  HOUR BACK
		$tmp=$from+1;
		echo("[<A HREF=\"history.php?action=showhistory&itemid=$itemid&from=$tmp&period=$period\">");
		echo("1h back</A>] ");

		//  Start of --- 
		$tmp=$period+3600;
		echo("[<A HREF=\"history.php?action=showhistory&itemid=$itemid&from=$from&period=$tmp\">");
		echo("+1h</A>] ");

		if ($period>3600) 
		{
			$tmp=$period-3600;
			echo("[<A HREF=\"history.php?action=showhistory&itemid=$itemid&from=$from&period=$tmp\">");
			echo("-1h</A>] ");
		}
		else
		{
			echo("[-1h]");
		}
		//  End of ------ 

		if ($from>0) // HOUR FORWARD
		{
			$tmp=$from-1;
			echo("[<A HREF=\"history.php?action=showhistory&itemid=$itemid&from=$tmp&period=$period\">");
			echo("1h forward</A>] ");
		}
		else
		{
			echo("[1h forward]");  
		}


		if (isset($From) && ($From>0))
		{
			$tmp=$from-12;
			echo("[<A HREF=\"history.php?action=showhistory&itemid=$itemid&from=$tmp&period=$period\">");
			echo("12h forward</A>] ");
		}
		else
		{
			echo("[12h forward]");
		}

		if (isset($From) && ($From>0))
		{
			$tmp=$from-12*14;
			echo("[<A HREF=\"history.php?action=showhistory&itemid=$itemid&from=$tmp&period=$period\">");
			echo("Week forward</A>] ");
		}
		else
		{
			echo("[Week forward]");
		}
		echo "</center>";
	}

	# Show history
	function	show_freehist($itemid,$period)
	{

		echo "<br>";
		show_table2_header_begin();
		echo "Choose period";

		show_table2_v_delimiter();
		echo "<form method=\"post\" action=\"history.php\">";
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
		echo "<input type=\"submit\" name=\"action\" value=\"showvalues\"> to see values in plain text";

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
		echo "<input type=\"submit\" name=\"action\" value=\"plaintext\">";

		show_table2_header_end();

		show_footer();
	}

	# Insert form for Item information
	function	insert_item_form($itemid)
	{
		if(isset($itemid))
		{
			$result=DBselect("select i.description, i.key_, h.host, h.port, i.delay, i.history, i.status, i.type, i.snmp_community,i.snmp_oid,i.value_type from items i,hosts h where i.itemid=$itemid and h.hostid=i.hostid");
		
			$description=DBget_field($result,0,0);
			$key=DBget_field($result,0,1);
			$host=DBget_field($result,0,2);
			$port=DBget_field($result,0,3);
			$delay=DBget_field($result,0,4);
			$history=DBget_field($result,0,5);
			$status=DBget_field($result,0,6);
			$type=DBget_field($result,0,7);
			$snmp_community=DBget_field($result,0,8);
			$snmp_oid=DBget_field($result,0,9);
			$value_type=DBget_field($result,0,10);
		}
		else
		{
			$description="";
			$key="";
			$host="";
			$port=10000;
			$delay=30;
			$history=30*24*3600;
			$status=0;
			$type=0;
			$snmp_community="public";
			$snmp_oid="interfaces.ifTable.ifEntry.ifInOctets.1";
			$value_type=0;
		}

		echo "<br>";

		show_table2_header_begin();
		echo "Item configuration";
 
		show_table2_v_delimiter();
		echo "<form method=\"post\" action=\"items.php\">";
		echo "<input name=\"itemid\" type=hidden value=$itemid size=8>";
		echo "Description";
		show_table2_h_delimiter();
		echo "<input name=\"description\" value=\"$description\"size=40>";

		show_table2_v_delimiter();
		echo "Host";
		show_table2_h_delimiter();
		echo "<select name=\"hostid\" value=\"3\">";
	        $result=DBselect("select hostid,host from hosts order by host");
	        for($i=0;$i<DBnum_rows($result);$i++)
	        {
	                $hostid_=DBget_field($result,$i,0);
	                $host_=DBget_field($result,$i,1);
			if($host==$host_)
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
		echo "<SELECT NAME=\"type\" value=\"$type\" size=\"1\">";
		echo "<OPTION VALUE=\"0\"";
		if($type==0) echo "SELECTED";
		echo ">Zabbix agent";
		echo "<OPTION VALUE=\"1\"";
		if($type==1) echo "SELECTED";
		echo ">SNMPv1 agent";
		echo "</SELECT>";
 
		show_table2_v_delimiter();
		echo "SNMP community (for SNMP only)";
		show_table2_h_delimiter();
		echo "<input name=\"snmp_community\" value=\"$snmp_community\" size=16>";

		show_table2_v_delimiter();
		echo "SNMP OID (for SNMP only)";
		show_table2_h_delimiter();
		echo "<input name=\"snmp_oid\" value=\"$snmp_oid\" size=40>";

		show_table2_v_delimiter();
		echo "Key";
		show_table2_h_delimiter();
		echo "<input name=\"key\" value=\"$key\" size=40>";

		show_table2_v_delimiter();
		echo "Delay";
		show_table2_h_delimiter();
		echo "<input name=\"delay\" value=\"$delay\" size=5>";

		show_table2_v_delimiter();
		echo "Keep history (in sec)";
		show_table2_h_delimiter();
		echo "<input name=\"history\" value=\"$history\" size=8>";

		show_table2_v_delimiter();
		echo "Status";
		show_table2_h_delimiter();
		echo "<SELECT NAME=\"status\" value=\"$status\" size=\"1\">";
		echo "<OPTION VALUE=\"0\"";
		if($status==0) echo "SELECTED";
		echo ">Monitored";
		echo "<OPTION VALUE=\"1\"";
		if($status==1) echo "SELECTED";
		echo ">Disabled";
		echo "<OPTION VALUE=\"2\"";
		if($status==2) echo "SELECTED";
		echo ">Trapper";
		echo "<OPTION VALUE=\"3\"";
		if($status==3) echo "SELECTED";
		echo ">Not supported";
		echo "</SELECT>";

		show_table2_v_delimiter();
		echo "Type of information";
		show_table2_h_delimiter();
		echo "<SELECT NAME=\"value_type\" value=\"$value_type\" size=\"1\">";
		echo "<OPTION VALUE=\"0\"";
		if($value_type==0) echo "SELECTED";
		echo ">Numeric";
		echo "<OPTION VALUE=\"1\"";
		if($value_type==1) echo "SELECTED";
		echo ">Character";
		echo "</SELECT>";
 
		show_table2_v_delimiter2();
		echo "<input type=\"submit\" name=\"register\" value=\"add\">";
		if(isset($itemid))
		{
			echo "<input type=\"submit\" name=\"register\" value=\"update\">";
			echo "<input type=\"submit\" name=\"register\" value=\"delete\">";
		}
 
		show_table2_header_end();
?>
<?
?>
</TR>
</TABLE>

</CENTER>
</FORM>

</BODY>
</HTML>
<?
	}

	# Insert form for User permissions
	function	insert_permissions_form($userid)
	{
		echo "<br>";

		show_table2_header_begin();
		echo "New permission";

		show_table2_v_delimiter();
		echo "<form method=\"post\" action=\"users.php\">";
		if(isset($userid))
		{
			echo "<input name=\"userid\" type=\"hidden\" value=\"$userid\" size=8>";
		}
		echo "Resource";
		show_table2_h_delimiter();
		echo "<select name=\"right\">";
		echo "<option value=\"Configuration of Zabbix\">Configuration of Zabbix";
		echo "<option value=\"Default permission\">Default permission";
		echo "<option value=\"Graph\">Graph";
		echo "<option value=\"Host\">Host";
		echo "<option value=\"Item\">Item";
		echo "<option value=\"Network map\">Network map";
		echo "<option value=\"Trigger comment\">Trigger's comment";
		echo "<option value=\"User\">User";
		echo "</select>";

		show_table2_v_delimiter();
		echo "Permission";
		show_table2_h_delimiter();
		echo "<select name=\"permission\">";
		echo "<option value=\"R\">Read-only";
		echo "<option value=\"U\">Read-write";
		echo "<option value=\"H\">Hide";
		echo "<option value=\"A\">Add";
		echo "</select>";

		show_table2_v_delimiter();
		echo "Resource ID (0 for all)";
		show_table2_h_delimiter();
		echo "<input name=\"id\" value=\"0\" size=4>";

		show_table2_v_delimiter2();
		echo "<input type=\"submit\" name=\"register\" value=\"add permission\">";
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
		echo "New user";

		show_table2_v_delimiter();
		echo "<form method=\"post\" action=\"users.php\">";
		if(isset($userid))
		{
			echo "<input name=\"userid\" type=\"hidden\" value=\"$userid\" size=8>";
		}
		echo "Alias";
		show_table2_h_delimiter();
		echo "<input name=\"alias\" value=\"$alias\" size=20>";

		show_table2_v_delimiter();
		echo "Name";
		show_table2_h_delimiter();
		echo "<input name=\"name\" value=\"$name\" size=20>";

		show_table2_v_delimiter();
		echo "Surname";
		show_table2_h_delimiter();
		echo "<input name=\"surname\" value=\"$surname\" size=20>";

		show_table2_v_delimiter();
		echo "Password";
		show_table2_h_delimiter();
		echo "<input type=\"password\" name=\"password1\" value=\"$password\" size=20>";

		show_table2_v_delimiter();
		echo "Password (once again)";
		show_table2_h_delimiter();
		echo "<input type=\"password\" name=\"password2\" value=\"$password\" size=20>";

		show_table2_v_delimiter2();
		echo "<input type=\"submit\" name=\"register\" value=\"add\">";
		if(isset($userid))
		{
			echo "<input type=\"submit\" name=\"register\" value=\"update\">";
			echo "<input type=\"submit\" name=\"register\" value=\"delete\">";
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
			$description=$trigger["description"];
			$priority=$trigger["priority"];
			$istrue=$trigger["istrue"];
			$comments=$trigger["comments"];
			$url=$trigger["url"];
		}
		else
		{
			$expression="";
			$description="";
			$priority=0;
			$istrue=3;
			$comments="";
			$url="";
		}
		
		echo "<br>";

		show_table2_header_begin();
		echo "Trigger configuration";
 
		show_table2_v_delimiter();
		if(isset($hostid))
		{
			echo "<form method=\"post\" action=\"triggers.php?hostid=$hostid\">";
		}
		else
		{
			echo "<form method=\"post\" action=\"triggers.php\">";
		}
		echo "<input name=\"triggerid\" type=hidden value=$triggerid size=8>";
		echo "Description";
		show_table2_h_delimiter();
		echo "<input name=\"description\" value=\"$description\" size=70>";

		show_table2_v_delimiter();
		echo "Expression";
		show_table2_h_delimiter();
		echo "<input name=\"expression\" value=\"$expression\" size=70>";

		show_table2_v_delimiter();
		echo "Priority";
		show_table2_h_delimiter();
		echo "<SELECT NAME=\"priority\" size=\"1\">";
		echo "<OPTION VALUE=\"0\" "; if($priority==0) echo "SELECTED"; echo ">Not classified";
		echo "<OPTION VALUE=\"1\" "; if($priority==1) echo "SELECTED"; echo ">Just for information";
		echo "<OPTION VALUE=\"2\" "; if($priority==2) echo "SELECTED"; echo ">Warning";
		echo "<OPTION VALUE=\"3\" "; if($priority==3) echo "SELECTED"; echo ">Average";
		echo "<OPTION VALUE=\"4\" "; if($priority==4) echo "SELECTED"; echo ">High";
		echo "<OPTION VALUE=\"5\" "; if($priority==5) echo "SELECTED"; echo ">Disaster";
		echo "</SELECT>";

		show_table2_v_delimiter();
		echo "Comments";
		show_table2_h_delimiter();
 		echo "<TEXTAREA NAME=\"comments\" COLS=70 ROWS=\"7\" WRAP=\"SOFT\">$comments</TEXTAREA>";

		show_table2_v_delimiter();
		echo "URL";
		show_table2_h_delimiter();
		echo "<input name=\"url\" value=\"$url\" size=70>";

		show_table2_v_delimiter();
		echo "Disabled";
		show_table2_h_delimiter();
		echo "<INPUT TYPE=\"CHECKBOX\" ";
		if($istrue==2) { echo " CHECKED "; }
		echo "NAME=\"disabled\"  VALUE=\"true\">";

 
		show_table2_v_delimiter2();
		echo "<input type=\"submit\" name=\"register\" value=\"add\">";
		if(isset($triggerid))
		{
			echo "<input type=\"submit\" name=\"register\" value=\"update\">";
			echo "<input type=\"submit\" name=\"register\" value=\"delete\">";
		}

		if(isset($triggerid))
		{
			show_table2_v_delimiter();
			echo "The trigger depends on";
			show_table2_h_delimiter();
			$sql="select t.triggerid,t.description from triggers t,trigger_depends d where t.triggerid=d.triggerid_up and d.triggerid_down=$triggerid";
			$result1=DBselect($sql);
			echo "<SELECT NAME=\"dependency\" size=\"1\">";
			for($i=0;$i<DBnum_rows($result1);$i++)
			{
				$depid=DBget_field($result1,$i,0);
				$depdescr=DBget_field($result1,$i,1);
				echo "<OPTION VALUE=\"$depid\">$depdescr";
			}
			echo "</SELECT>";

			show_table2_v_delimiter();
			echo "New dependency";
			show_table2_h_delimiter();
			$sql="select t.triggerid,t.description from triggers t where t.triggerid!=$triggerid order by t.description";
			$result=DBselect($sql);
			echo "<SELECT NAME=\"depid\" size=\"1\">";
			for($i=0;$i<DBnum_rows($result);$i++)
			{
				$depid=DBget_field($result,$i,0);
				$depdescr=DBget_field($result,$i,1);
				echo "<OPTION VALUE=\"$depid\">$depdescr";
			}
			echo "</SELECT>";

			show_table2_v_delimiter2();
			if(isset($triggerid))
			{
				echo "<input type=\"submit\" name=\"register\" value=\"add dependency\">";
				if(DBnum_rows($result1)>0)
				{
					echo "<input type=\"submit\" name=\"register\" value=\"delete dependency\">";
				}
			}
		}

		echo "</form>";
		show_table2_header_end();
	}


	function	show_footer()
	{
		echo "<br>";
		echo "<table border=0 cellpadding=1 cellspacing=0 width=\"100%\" align=center>";
		echo "<tr>";
		echo "<td bgcolor=\"#000000\">";
		echo "<table border=\"0\" cellspacing=\"0\" cellpadding=\"3\" width=\"100%\" bgcolor=\"#666666\">";
		echo "<tr><td align=center>";
		echo "<font face=\"Arial,Helvetica\" size=1>";
		echo "<a href=\"http://zabbix.sourceforge.net\">ZABBIX</a> Copyright 2000,2001,2002 by <a href=\"mailto:alex@gobbo.caves.lv\">Alexei Vladishev</a>";
		echo "</font>";
		echo "</td></tr>";
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

	        $result=DBselect("select count(*) from alarms");
		$stat["alarms_count"]=DBget_field($result,0,0);

	        $result=DBselect("select count(*) from alerts");
		$stat["alerts_count"]=DBget_field($result,0,0);

	        $result=DBselect("select count(*) from triggers");
		$stat["triggers_count"]=DBget_field($result,0,0);

	        $result=DBselect("select count(*) from triggers where istrue!=2");
		$stat["triggers_count_enabled"]=DBget_field($result,0,0);

	        $result=DBselect("select count(*) from triggers where istrue=2");
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

	        $result=DBselect("select count(*) from users");
		$stat["users_count"]=DBget_field($result,0,0);


		return $stat;
	}

	function	calculate_availability($triggerid)
	{
	        $result=DBselect("select count(*),min(clock),max(clock) from alarms where triggerid=$triggerid");
		if(DBget_field($result,0,0)>0)
		{
			$min=DBget_field($result,0,1);
			$max=DBget_field($result,0,2);
		}
		else
		{
			$max=time();
			$min=$max-24*3600;
		}

		$sql="select clock,istrue from alarms where triggerid=$triggerid and clock>=$min and clock<=$max";
		$result=DBselect($sql);

// -1,0,1
		$state=-1;
		$true_time=0;
		$false_time=0;
		$unknown_time=0;
		$time=$min;
		$max=time();
		for($i=0;$i<DBnum_rows($result);$i++)
		{
			$clock=DBget_field($result,$i,0);
			$istrue=DBget_field($result,$i,1);

			$diff=$clock-$time;

			$time=$clock;

			if($state==-1)
			{
				$state=$istrue;
				if($state == 0)
				{
					$false_time+=$diff;
				}
				if($state == 1)
				{
					$true_time+=$diff;
				}
				if($state == 3)
				{
					$unknown_time+=$diff;
				}
			}
			else if($state==0)
			{
				$false_time+=$diff;
				$state=$istrue;
			}
			else if($state==1)
			{
				$true_time+=$diff;
				$state=$istrue;
			}
			else if($state==3)
			{
				$unknown_time+=$diff;
				$state=$istrue;
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

		$total_time=$true_time+$false_time+$unknown_time;
		$ret["true_time"]=$true_time;
		$ret["false_time"]=$false_time;
		$ret["unknown_time"]=$unknown_time;
		$ret["true"]=(100*$true_time)/$total_time;
		$ret["false"]=(100*$false_time)/$total_time;
		$ret["unknown"]=(100*$unknown_time)/$total_time;
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
?>
