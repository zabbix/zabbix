<?
	include 	"include/db.inc";

	$USER_DETAILS	="";

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
//		echo "Validating simple:$expression<br>";
//		if (eregi('^\{([0-9a-zA-Z\_\.]+)\:([0-9a-zA-Z\_]+)\.((diff)|(min)|(max)|(last)|(nodata))\(([0-9\.]+)\)\}$', $expression, &$arr)) 
		if (eregi('^\{([0-9a-zA-Z\_\.]+)\:([]\[0-9a-zA-Z\_\/]+)\.((diff)|(min)|(max)|(last)|(nodata))\(([0-9\.]+)\)\}$', $expression, &$arr)) 
		{
			$host=$arr[1];
			$key=$arr[2];
			$function=$arr[3];
			$parameter=$arr[9];

			$sql="select count(*) from hosts h,items i where h.host='$host' and i.key_='$key' and h.hostid=i.hostid";
			$result=DBselect($sql);
			if(DBget_field($result,0,0)!=1)
			{
				return -1;
			}

			if(validate_float($parameter)!=0)
			{
				return -1;
			}
		}
		else
		{
			return -1;
		}
		return 0;
	}

	function	validate_expression($expression)
	{
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
// 	Replace all float sign float with 0
//			echo "Expression:$expression<br>";
			$arr="";
			if (eregi('^((.)*)([0-9\.]+)([\&\|\>\<]{1})([0-9\.]+)((.)*)$', $expression, &$arr)) 
			{
//				echo "OK<br>";
//				for($i=0;$i<50;$i++)
//				{
//					if($arr[$i]!="")
//						echo "  $i: ",$arr[$i],"<br>";
//				}
				if(validate_float($arr[3])!=0)
				{
					return -1;
				}
				if(validate_float($arr[5])!=0)
				{
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

		return $expression;
	}

	function	cr()
	{
		echo "\n";
	}

	function	check_authorisation()
	{
		global $page;
		global $PHP_AUTH_USER,$PHP_AUTH_PW;
		global $USER_DETAILS;

		$sql="select g.groupid,u.userid,u.alias,u.name,u.surname from users u,groups g where u.alias='$PHP_AUTH_USER' and u.passwd='$PHP_AUTH_PW' and u.groupid=g.groupid";
		$result=DBselect($sql);
		if(DBnum_rows($result)==1)
		{
			$USER_DETAILS["groupid"]=DBget_field($result,0,0);
			$USER_DETAILS["userid"]=DBget_field($result,0,1);
			$USER_DETAILS["alias"]=DBget_field($result,0,2);
			$USER_DETAILS["name"]=DBget_field($result,0,3);
			$USER_DETAILS["surname"]=DBget_field($result,0,4);
		}
		

		if(	($page["file"]=="latest.html")||
			($page["file"]=="tr_status.html")||
			($page["file"]=="queue.html")||
			($page["file"]=="latestalarms.html")||
			($page["file"]=="alarms.html")||
			($page["file"]=="alerts.html")||
			($page["file"]=="history.html")||
			($page["file"]=="maps.html")||
			($page["file"]=="map.html")||
			($page["file"]=="charts.html")||
			($page["file"]=="chart.html")||
			($page["file"]=="about.html")||
			($page["file"]=="index.html"))
		{
			$sql="select password_required from config";
			$result=DBselect($sql);

			if(DBget_field($result,0,0)==0)
			{
				return;
			}

			$sql="select count(*) from users u,groups g where u.alias='$PHP_AUTH_USER' and u.passwd='$PHP_AUTH_PW' and u.groupid=g.groupid and (g.groupid=1 or g.groupid=2)";
		}
		else
		{
			$sql="select count(*) from users u,groups g where u.alias='$PHP_AUTH_USER' and u.passwd='$PHP_AUTH_PW' and u.groupid=g.groupid and g.groupid=1";
		}
		$result=DBselect($sql);

//		echo "==",DBget_field($result,0,0),"==";

		if(DBget_field($result,0,0)!=1)
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
				<a href="latest.html">
<?
				if( 	($page["file"]=="latest.html") ||
					($page["file"]=="history.html"))
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
				<a href="tr_status.html?notitle=true&onlytrue=true&noactions=true&compact=true">
<?
				if($page["file"]=="tr_status.html")
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
				<a href="queue.html">
<?
				if($page["file"]=="queue.html")
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
				<a href="latestalarms.html">
<?
				if(($page["file"]=="latestalarms.html") ||
					($page["file"]=="alarms.html"))
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
				<a href="alerts.html">
<?
				if($page["file"]=="alerts.html")
				{
					echo "<b>[ALERTS]</b></a>";
				}
				else
				{
					echo "ALERTS</a>";
				}
?>
			</font>
		</td>
		<td colspan=1 bgcolor=FFFFFF align=center valign="top" width="15%">
			<font face="Arial,Helvetica" size=2>
				<a href="maps.html">
<?
				if($page["file"]=="maps.html")
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
				<a href="charts.html">
<?
				if($page["file"]=="charts.html")
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
		</tr>
		<tr>
		<td colspan=2 bgcolor=FFFFFF align=center valign="top" width="15%">
			<font face="Arial,Helvetica" size=2>
				<a href="about.html">
<?
				if($page["file"]=="about.html")
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
				<a href="report1.html">
<?
				if($page["file"]=="report1.html")
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
		<td colspan=3 bgcolor=FFFFFF align=center valign="top" width="15%">
			<font face="Arial,Helvetica" size=2>
				<a href="report2.html">
<?
				if($page["file"]=="report2.html")
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
				<a href="config.html">
<?
				if($page["file"]=="config.html")
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
				<a href="users.html">
<?
				if(	($page["file"]=="users.html")||
					($page["file"]=="media.html"))
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
				<a href="hosts.html">
<?
				if($page["file"]=="hosts.html")
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
				<a href="items.html">
<?
				if($page["file"]=="items.html")
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
				<a href="triggers.html">
<?
				if(	($page["file"]=="triggers.html")||
					($page["file"]=="actions.html"))
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
				<a href="sysmaps.html">
<?
				if(	($page["file"]=="sysmaps.html")||
					($page["file"]=="sysmap.html"))
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
				<a href="graphs.html">
<?
				if(	($page["file"]=="graphs.html")||
					($page["file"]=="graph.html"))
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
					$exp=$exp."{<A HREF=\"history.html?action=showhistory&itemid=".DBget_field($res1,0,4)."\">".DBget_field($res1,0,0).":".DBget_field($res1,0,1)."</A>.<B>".DBget_field($res1,0,2)."(</B>".DBget_field($res1,0,3)."<B>)</B>}";
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
#				echo "HOST:$host<BR>";
#				echo "KEY:$key<BR>";
#				echo "FUNCTION:$function<BR>";
#				echo "PARAMETER:$parameter<BR>";
				$state='';
		
				$sql="select i.itemid from items i,hosts h where i.key_='$key' and h.host='$host' and h.hostid=i.hostid";
#				echo $sql,"<Br>";
				$res=DBselect($sql);

				$itemid=DBget_field($res,0,0);
#				echo "ITEMID:$itemid<BR>";
	
#				$sql="select functionid from functions where function='$function' and parameter=$parameter";
#				echo $sql,"<Br>";
#				$res=mysql_query($sql,$mysql);
#				$row=mysql_fetch_row($res);

#				if($row)
#				{
#					$functionid=$row[0];
#				}
#				else
#				{
					$sql="insert into functions (itemid,triggerid,function,parameter) values ($itemid,$triggerid,'$function',$parameter)";
#					echo $sql,"<Br>";
					$res=DBexecute($sql);
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
		$comments=addslashes($comments);
		$sql="update triggers set comments='$comments' where triggerid=$triggerid";
		$result=DBexecute($sql);
	}

	# Update Trigger status

	function	update_trigger_status($triggerid,$status)
	{
		$sql="update triggers set istrue=$status where triggerid=$triggerid";
		$result=DBexecute($sql);
	}

	# Update Item status

	function	update_item_status($itemid,$status)
	{
		$sql="update items set status=$status where itemid=$itemid";
		$result=DBexecute($sql);
	}

	# Update Host status

	function	update_host_status($hostid,$status)
	{
		$sql="update hosts set status=$status where hostid=$hostid";
		$result=DBexecute($sql);
	}

	# Update Item definition

	function	update_item($itemid,$description,$key,$hostid,$delay,$history,$status,$type,$snmp_community,$snmp_oid)
	{
		$sql="update items set description='$description',key_='$key',hostid=$hostid,delay=$delay,history=$history,lastdelete=0,nextcheck=0,status=$status,type=$type,snmp_community='$snmp_community',snmp_oid='$snmp_oid' where itemid=$itemid";
		$result=DBexecute($sql);
	}

	# Add Action

	function	add_action( $triggerid, $userid, $good, $delay, $subject, $message )
	{
		$sql="insert into actions (triggerid,userid,good,delay,nextcheck,subject,message) values ($triggerid,$userid,$good,$delay,0,'$subject','$message')";
		$result=DBexecute($sql);
	}

	# Update Action

	function	update_action( $actionid, $userid, $good, $delay, $subject, $message )
	{
		$sql="update actions set userid=$userid,good=$good,delay=$delay,nextcheck=0,subject='$subject',message='$message' where actionid=$actionid";
		$result=DBexecute($sql);
	}

	function	delete_graphs_item($gitemid)
	{
		$sql="delete from graphs_items where gitemid=$gitemid";
		$result=DBexecute($sql);
	}

	# Delete Graph

	function	delete_graph($graphid)
	{
		$sql="delete from graphs_items where graphid=$graphid";
		$result=DBexecute($sql);
		$sql="delete from graphs where graphid=$graphid";
		$result=DBexecute($sql);
	}

	# Delete System Map

	function	delete_sysmap( $sysmapid )
	{
		$sql="delete from sysmaps where sysmapid=$sysmapid";
		$result=DBexecute($sql);
		$sql="delete from sysmaps_hosts where sysmapid=$sysmapid";
		$result=DBexecute($sql);
		$sql="delete from sysmaps_links where sysmapid=$sysmapid";
		$result=DBexecute($sql);
	}

	# Delete Action by userid

	function	delete_actions_by_userid( $userid )
	{
		$sql="delete from actions where userid=$userid";
		$result=DBexecute($sql);
	}

	# Delete Action

	function	delete_action( $actionid )
	{
		$sql="delete from actions where actionid=$actionid";
		$result=DBexecute($sql);
	}

	# Delete from History

	function	delete_history_by_itemid( $itemid )
	{

		$sql="delete from history where itemid=$itemid";
		$result=DBexecute($sql);
	}

	# Add Item definition

	function	add_item($description,$key,$hostid,$delay,$history,$status,$type,$snmp_community,$snmp_oid)
	{
		$sql="insert into items (description,key_,hostid,delay,history,lastdelete,nextcheck,status,type,snmp_community,snmp_oid) values ('$description','$key',$hostid,$delay,$history,0,0,$status,$type,'$snmp_community','$snmp_oid')";
		$result=DBexecute($sql);
		return DBinsert_id($result,"items","itemid");
	}

	# Delete Function definition

	function	delete_function_by_triggerid($triggerid)
	{
		$sql="delete from functions where triggerid=$triggerid";
		$result=DBexecute($sql);
	}

	function	delete_actions_by_triggerid($triggerid)
	{
		$sql="delete from actions where triggerid=$triggerid";
		$result=DBexecute($sql);
	}

	function	delete_alarms_by_triggerid($triggerid)
	{
		$sql="delete from alarms where triggerid=$triggerid";
		$result=DBexecute($sql);
	}

	# Delete Function and Trigger definitions by itemid

	function	delete_triggers_functions_by_itemid($itemid)
	{
		$sql="select triggerid from functions where itemid=$itemid";
		$result=DBselect($sql);
		for($i=0;$i<DBnum_rows($result);$i++)
		{
			delete_trigger(DBget_field($result,$i,0));
		}
		$sql="delete from functions where itemid=$itemid";
		$result=DBexecute($sql);

	}

	# Delete Item definition

	function	delete_item($itemid)
	{

		delete_triggers_functions_by_itemid($itemid);
		delete_history_by_itemid($itemid);
		$sql="delete from graphs_items where itemid=$itemid";
		$result=DBexecute($sql);
		$sql="delete from items where itemid=$itemid";
		$result=DBexecute($sql);
	}

	# Add alarm

	function	add_alarm($triggerid,$istrue)
	{
		$now=time();
		$sql="insert into alarms(triggerid,clock,istrue) values($triggerid,$now,$istrue)";
		$result=DBexecute($sql);
	}

	# Add Trigger definition

	function	add_trigger($expression,$description,$priority,$istrue,$comments)
	{
		$description=addslashes($description);
		$sql="insert into triggers  (description,priority,istrue,comments) values ('$description',$priority,$istrue,'$comments')";
#		echo $sql,"<Br>";
		$result=DBexecute($sql);
 
		$triggerid=DBinsert_id($result,"triggers","triggerid");
 
		$expression=implode_exp($expression,$triggerid);
		$sql="update triggers set expression='$expression' where triggerid=$triggerid";
#		echo $sql,"<Br>";
		$result=DBexecute($sql);
	}

	# Delete Trigger definition

	function	delete_trigger($triggerid)
	{
		delete_function_by_triggerid($triggerid);
		delete_alarms_by_triggerid($triggerid);
		delete_actions_by_triggerid($triggerid);

		$sql="delete from triggers where triggerid=$triggerid";
		$result=DBexecute($sql);
	}

	# Update Trigger definition

	function	update_trigger($triggerid,$expression,$description,$priority,$istrue,$comments)
	{
#		delete_trigger($triggerid);
#		$triggerid=add_trigger($expression,$description,$priority,$istrue,$comments);

		delete_function_by_triggerid($triggerid);

		$expression=implode_exp($expression,$triggerid);
		$sql="update triggers set expression='$expression',description='$description',priority=$priority,istrue=$istrue,comments='$comments' where triggerid=$triggerid";
#		echo $sql,"<Br>";
		$result=DBexecute($sql);
	}

	# Update User definition

	function	update_user($userid,$groupid,$name,$surname,$alias,$password)
	{
		$sql="update users set groupid=$groupid,name='$name',surname='$surname',alias='$alias',passwd='$password' where userid=$userid";
		$result=DBexecute($sql);
		return	$result;
	}


	# Add User definition

	function	add_user($groupid,$name,$surname,$alias,$passwd)
	{
		$sql="insert into users (groupid,name,surname,alias,passwd) values ($groupid,'$name','$surname','$alias','$passwd')";
		$result=DBexecute($sql);

		return	$result;
	}

	# Add Items and Triggers from template

	function	add_from_templates($hostid,$host)
	{
		$result=DBselect("select itemtemplateid,description,key_,delay from items_template");
		for($i=0;$i<DBnum_rows($result);$i++) 
		{
			$itemtemplateid=DBget_field($result,$i,0);
			$description=DBget_field($result,$i,1);
			$key=DBget_field($result,$i,2);
			$delay=DBget_field($result,$i,3);

			$itemid=add_item($description,$key,$hostid,$delay,30*24*3600,0,0,"","");

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

				add_trigger($expression,$description,0,0,"");
			}
		}
	}

	# Update Graph

	function	update_graph($graphid,$name,$width,$height)
	{
		$sql="update graphs set name='$name',width=$width,height=$height where graphid=$graphid";
		$result=DBexecute($sql);
	}

	# Update System Map

	function	update_sysmap($sysmapid,$name,$width,$height)
	{
		$sql="update sysmaps set name='$name',width=$width,height=$height where sysmapid=$sysmapid";
		$result=DBexecute($sql);
	}

	# Add Graph

	function	add_graph($name,$width,$height)
	{
		$sql="insert into graphs (name,width,height) values ('$name',$width,$height)";
		$result=DBexecute($sql);
	}

	function	add_item_to_graph($graphid,$itemid,$color)
	{
		$sql="insert into graphs_items (graphid,itemid,color) values ($graphid,$itemid,'$color')";
		$result=DBexecute($sql);
	}

	# Add System Map

	function	add_sysmap($name,$width,$height)
	{
		$sql="insert into sysmaps (name,width,height) values ('$name',$width,$height)";
		$result=DBexecute($sql);
	}

	function	add_link($sysmapid,$shostid1,$shostid2)
	{
		$sql="insert into sysmaps_links (sysmapid,shostid1,shostid2) values ($sysmapid,$shostid1,$shostid2)";
		$result=DBexecute($sql);
	}

	function	delete_link($linkid)
	{
		$sql="delete from sysmaps_links where linkid=$linkid";
		$result=DBexecute($sql);
	}

	# Add Host to system map

	function add_host_to_sysmap($sysmapid,$hostid,$label,$x,$y,$icon)
	{
		$sql="insert into sysmaps_hosts (sysmapid,hostid,label,x,y,icon) values ($sysmapid,$hostid,'$label',$x,$y,'$icon')";
		$result=DBexecute($sql);
	}

	function	update_sysmap_host($shostid,$sysmapid,$hostid,$label,$x,$y,$icon)
	{
		$sql="update sysmaps_hosts set hostid=$hostid,label='$label',x=$x,y=$y,icon='$icon' where shostid=$shostid";
		$result=DBexecute($sql);
	}

	# Add Host definition

	function	add_host($host,$port,$status,$template)
	{
		$sql="insert into hosts (host,port,status) values ('$host',$port,$status)";
		$result=DBexecute($sql);
		$hostid=DBinsert_id($result,"hosts","hostid");

		if($template=="true")
		{
			add_from_templates($hostid,$host);
		}

	}

	# Add Media definition

	function	add_media( $userid, $type, $sendto)
	{
		$sql="insert into media (userid,type,sendto,active) values ($userid,'$type','$sendto',0)";
		$result=DBexecute($sql);
	}

	# Delete Media definition

	function	delete_media($mediaid)
	{
		$sql="delete from media where mediaid=$mediaid";
		$result=DBexecute($sql);
	}

	# Delete Media definition by userid

	function	delete_media_by_userid($userid)
	{
		$sql="delete from media where userid=$userid";
		$result=DBexecute($sql);
	}

	# Update configuration

	function	update_config($smtp_server,$smtp_helo,$smtp_email,$password_required)
	{
		$sql="update config set smtp_server='$smtp_server',smtp_helo='$smtp_helo',smtp_email='$smtp_email',password_required=$password_required";
		$result=DBexecute($sql);
	}


	# Activate Media

	function	activate_media($mediaid)
	{
		$sql="update media set active=0 where mediaid=$mediaid";
		$result=DBexecute($sql);
	}

	# Disactivate Media

	function	disactivate_media($mediaid)
	{
		$sql="update media set active=1 where mediaid=$mediaid";
		$result=DBexecute($sql);
	}

	# Delete Host from sysmap definition

	function	delete_sysmaps_host($shostid)
	{
		$sql="delete from sysmaps_hosts where shostid=$shostid";
		$result=DBexecute($sql);
		$sql="delete from sysmaps_links where shostid1=$shostid or shostid2=$shostid";
		$result=DBexecute($sql);
	}

	# Delete Host

	function	delete_host($hostid)
	{
		$sql="select itemid from items where hostid=$hostid";
		$result=DBselect($sql);
		for($i=0;$i<DBnum_rows($result);$i++)
		{
			delete_item(DBget_field($result,$i,0));
		}

		$sql="delete from hosts where hostid=$hostid";
		$result=DBexecute($sql);
	}

	# Delete User definition

	function	delete_user($userid)
	{
		delete_media_by_userid($userid);
		delete_actions_by_userid($userid);

		$sql="delete from users where userid=$userid";
		$result=DBexecute($sql);
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
		echo "<IMG SRC=\"chart.html?itemid=$itemid&period=$period&from=$from\">"; 
		echo "</center>";
		echo("<hr>");

 
		echo "<center>";
		//  Start of <<< WEEK >>> 
		$tmp=$from+12*14;
		echo("[<A HREF=\"history.html?action=showhistory&itemid=$itemid&from=$tmp&period=$period\">");
		echo("Week back</A>] ");

		//  Start of <<< 12h >>> 
		$tmp=$from+12;
		echo("[<A HREF=\"history.html?action=showhistory&itemid=$itemid&from=$tmp&period=$period\">");
		echo("12h back</A>] ");
		//  End of <<< 12h >>> 
		
		//  HOUR BACK
		$tmp=$from+1;
		echo("[<A HREF=\"history.html?action=showhistory&itemid=$itemid&from=$tmp&period=$period\">");
		echo("1h back</A>] ");

		//  Start of --- 
		$tmp=$period+3600;
		echo("[<A HREF=\"history.html?action=showhistory&itemid=$itemid&from=$from&period=$tmp\">");
		echo("+1h</A>] ");

		if ($period>3600) 
		{
			$tmp=$period-3600;
			echo("[<A HREF=\"history.html?action=showhistory&itemid=$itemid&from=$from&period=$tmp\">");
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
			echo("[<A HREF=\"history.html?action=showhistory&itemid=$itemid&from=$tmp&period=$period\">");
			echo("1h forward</A>] ");
		}
		else
		{
			echo("[1h forward]");  
		}


		if (isset($From) && ($From>0))
		{
			$tmp=$from-12;
			echo("[<A HREF=\"history.html?action=showhistory&itemid=$itemid&from=$tmp&period=$period\">");
			echo("12h forward</A>] ");
		}
		else
		{
			echo("[12h forward]");
		}

		if (isset($From) && ($From>0))
		{
			$tmp=$from-12*14;
			echo("[<A HREF=\"history.html?action=showhistory&itemid=$itemid&from=$tmp&period=$period\">");
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
		echo "<form method=\"post\" action=\"history.html\">";
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
		echo "<input type=\"submit\" name=\"action\" value=\"showvalues\"> to see graph";

		show_table2_header_end();

		show_footer();
	}

	# Show in plain text
	function	show_plaintxt($itemid,$period)
	{
		show_table2_header_begin();
		echo "Data in plain text format";

		show_table2_v_delimiter();
		echo "<form method=\"get\" action=\"history.html\">";
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
			$result=DBselect("select i.description, i.key_, h.host, h.port, i.delay, i.history, i.status, i.type, i.snmp_community,i.snmp_oid from items i,hosts h where i.itemid=$itemid and h.hostid=i.hostid");
		
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
		}

		echo "<br>";

		show_table2_header_begin();
		echo "Item configuration";
 
		show_table2_v_delimiter();
		echo "<form method=\"post\" action=\"items.html\">";
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

	# Insert form for User
	function	insert_user_form($userid)
	{
		if(isset($userid))
		{
			$result=DBselect("select u.alias,u.name,u.surname,u.passwd,g.groupid from users u,groups g where u.groupid=g.groupid and u.userid=$userid");
	
			$alias=DBget_field($result,0,0);
			$name=DBget_field($result,0,1);
			$surname=DBget_field($result,0,2);
			$password=DBget_field($result,0,3);
			$groupid_=DBget_field($result,0,4);
		}
		else
		{
			$alias="";
			$name="";
			$surname="";
			$password="";
			$groupid_=0;
		}

		show_table2_header_begin();
		echo "New user";

		show_table2_v_delimiter();
		echo "<form method=\"post\" action=\"users.html\">";
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
		echo "User group";
		show_table2_h_delimiter();
		echo "<select name=\"groupid\">";
		$result=DBselect("select groupid,name from groups order by name");
		for($i=0;$i<DBnum_rows($result);$i++)
		{
			$groupid=DBget_field($result,$i,0);
			$name=DBget_field($result,$i,1);
			if($groupid_==$groupid)
			{
				echo "<option value=\"$groupid\" selected>$name";
			}
			else
			{
				echo "<option value=\"$groupid\">$name";
			}
		}
		echo "</select>";

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
			$result=DBselect("select expression,description,priority,istrue,comments from triggers where triggerid=$triggerid");
	
			$expression=DBget_field($result,0,0);
			$expression=explode_exp($expression,0);

			$description=DBget_field($result,0,1);
			$priority=DBget_field($result,0,2);
			$istrue=DBget_field($result,0,3);
			$comments=DBget_field($result,0,4);
		}
		else
		{
			$expression="";
			$description="";
			$priority=0;
			$istrue=0;
			$comments="";
		}
		
		echo "<br>";

		show_table2_header_begin();
		echo "Trigger configuration";
 
		show_table2_v_delimiter();
		if(isset($hostid))
		{
			echo "<form method=\"post\" action=\"triggers.html?hostid=$hostid\">";
		}
		else
		{
			echo "<form method=\"post\" action=\"triggers.html\">";
		}
		echo "<input name=\"triggerid\" type=hidden value=$triggerid size=8>";
		echo "Description";
		show_table2_h_delimiter();
		echo "<input name=\"description\" value=\"$description\"size=70>";

		show_table2_v_delimiter();
		echo "Expression";
		show_table2_h_delimiter();
		echo "<input name=\"expression\" value=\"$expression\"size=70>";

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
 		echo "<TEXTAREA NAME=\"comments\" COLS=54 ROWS=\"5\" WRAP=\"SOFT\">$comments</TEXTAREA>";

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
		show_table2_header_end();
	}


	function	show_footer()
	{
		echo "<table border=0 cellpadding=1 cellspacing=5 width=\"100%\" align=center>";
		echo "<tr>";
		echo "<td bgcolor=\"#000000\">";
		echo "<table border=\"0\" cellspacing=\"0\" cellpadding=\"3\" width=\"100%\" bgcolor=\"#666666\">";
		echo "<tr><td align=center>";
		echo "<font face=\"Arial,Helvetica\" size=1>";
		echo "<a href=\"http://zabbix.sourceforge.net\">ZABBIX</a> Copyright 2000,2001 by <a href=\"mailto:alex@gobbo.caves.lv\">Alexei Vladishev</a>";
		echo "</font>";
		echo "</td></tr>";
		echo "</table>";
		echo "</table>";

		echo "</body>";
	}

	function	get_stats()
	{
	        $result=DBselect("select count(*) from history");
		$stat["history_count"]=DBget_field($result,0,0);

	        $result=DBselect("select count(*) from triggers");
		$stat["triggers_count"]=DBget_field($result,0,0);

	        $result=DBselect("select count(*) from items");
		$stat["items_count"]=DBget_field($result,0,0);

	        $result=DBselect("select count(*) from hosts");
		$stat["hosts_count"]=DBget_field($result,0,0);

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
			else
			{
				$true_time=$true_time+$max-$time;
			}
		}

		$ret["true_time"]=$true_time;
		$ret["false_time"]=$false_time;
		$ret["true"]=(100*$true_time)/($true_time+$false_time);
		$ret["false"]=(100*$false_time)/($true_time+$false_time);
		return $ret;
	}

?>
