<?
	$DB_SERVER	="localhost";
	$DB_DATABASE	="zabbix";
	$DB_USER	="root";
	$DB_PASSWORD	="";

	$mysql=mysql_pconnect($DB_SERVER,$DB_USER,$DB_PASSWORD);
	mysql_select_db($DB_DATABASE);

	function	cr()
	{
		echo "\n";
	}

	function	check_authorisation()
	{
		global $mysql;
		global $page;
		global $PHP_AUTH_USER,$PHP_AUTH_PW;

		if(	($page["file"]=="latest.html")||
			($page["file"]=="tr_status.html")||
			($page["file"]=="queue.html")||
			($page["file"]=="latestalarms.html")||
			($page["file"]=="alerts.html")||
			($page["file"]=="history.html")||
			($page["file"]=="index.html"))
		{
			$sql="select password_required from config";
			$result=mysql_query($sql,$mysql);
			$row=mysql_fetch_row($result);

			if($row[0]==0)
			{
				return;
			}

			$sql="select count(*) from users u,groups g where u.alias='$PHP_AUTH_USER' and u.passwd='$PHP_AUTH_PW' and u.groupid=g.groupid and (g.groupid=1 or g.groupid=2)";
		}
		else
		{
			$sql="select count(*) from users u,groups g where u.alias='$PHP_AUTH_USER' and u.passwd='$PHP_AUTH_PW' and u.groupid=g.groupid and g.groupid=1";
		}
		$result=mysql_query($sql,$mysql);
		$row=mysql_fetch_row($result);

		if($row[0]!=1)
		{
			Header("WWW-authenticate: basic realm=\"Zabbix\"");
			Header("HTTP/1.0 401 Unauthorized");
			echo "Try to contact System Administrator to get additional rights :-)\n";
			exit;
		}

	}

	# Header for HTML pages

	function	show_header($title,$refresh)
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
	}
?>
	<title><? echo $title; ?></title>
	</head>
	<body bgcolor="#778899" text="#000000" link="#000000" vlink="#000000" topmargin=4 leftmargin=0 rightmargin=0 marginheight=4>

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
				if($page["file"]=="latestalarms.html")
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
		</tr>
		</table>
	</td>
	</tr>
	</table>

	<br>
<?
	}

	# Show values in plain text

	function	show_plaintext($itemid, $from, $till)
	{
		global $mysql;

		$result=mysql_query("select clock,value from history where itemid=$itemid and clock>$from and clock<$till order by clock",$mysql);

		echo "<PRE>";
		while($row=mysql_fetch_row($result))
		{
			$clock=$row[0];
			$value=$row[1];
			echo date("Y-m-d H:i:s",$clock);
			echo "\t$clock\t$value\n";
		}
	}
 

	# Translate {10}>10 to something like localhost:procload.last(0)>10

	function	explode_exp ($expression, $html)
	{
		global	$mysql;

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
				$res1=mysql_query($sql,$mysql);
				$row1=mysql_fetch_row($res1);
				if($html == 0)
				{
					$exp=$exp."{".$row1[0].":".$row1[1].".".$row1[2]."(".$row1[3].")}";
				}
				else
				{
					$exp=$exp."{<A HREF=\"history.html?action=showhistory&itemid=$row1[4]\">".$row1[0].":".$row1[1]."</A>.<B>".$row1[2]."(</B>".$row1[3]."<B>)</B>}";
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
		global	$mysql;

		$exp='';
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
				$res=mysql_query($sql,$mysql);
				$row=mysql_fetch_row($res);

				$itemid=$row[0];
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
					$sql="insert into functions (functionid,itemid,triggerid,function,parameter) values (NULL,$itemid,$triggerid,'$function',$parameter)";
#					echo $sql,"<Br>";
					$res=mysql_query($sql,$mysql);
					$functionid=mysql_insert_id($mysql);	
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

	# Update Trigger status

	function	update_trigger_status($triggerid,$status)
	{
		global	$mysql;

		$sql="update triggers set istrue=$status where triggerid=$triggerid";
		$result=mysql_query($sql,$mysql);
	}

	# Update Item status

	function	update_item_status($itemid,$status)
	{
		global	$mysql;

		$sql="update items set status=$status where itemid=$itemid";
		$result=mysql_query($sql,$mysql);
	}

	# Update Host status

	function	update_host_status($hostid,$status)
	{
		global	$mysql;

		$sql="update hosts set status=$status where hostid=$hostid";
		$result=mysql_query($sql,$mysql);
	}

	# Update Item definition

	function	update_item($itemid,$description,$key,$hostid,$delay,$history,$status)
	{
		global	$mysql;

		$sql="update items set description='$description',key_='$key',hostid=$hostid,delay=$delay,history=$history,lastdelete=0,nextcheck=0,status=$status where itemid=$itemid";
		$result=mysql_query($sql,$mysql);
	}

	# Add Action

	function	add_action( $triggerid, $userid, $good, $delay, $subject, $message )
	{
		global	$mysql;

		$sql="insert into actions (actionid,triggerid,userid,good,delay,nextcheck,subject,message) values (NULL,$triggerid,$userid,$good,$delay,0,'$subject','$message')";
		$result=mysql_query($sql,$mysql);
	}

	# Update Action

	function	update_action( $actionid, $userid, $good, $delay, $subject, $message )
	{
		global	$mysql;

		$sql="update actions set userid=$userid,good=$good,delay=$delay,nextcheck=0,subject='$subject',message='$message' where actionid=$actionid";
		$result=mysql_query($sql,$mysql);
	}

	# Delete Action by userid

	function	delete_actions_by_userid( $userid )
	{
		global	$mysql;

		$sql="delete from actions where userid=$userid";
		$result=mysql_query($sql,$mysql);
	}

	# Delete Action

	function	delete_action( $actionid )
	{
		global	$mysql;

		$sql="delete from actions where actionid=$actionid";
		$result=mysql_query($sql,$mysql);
	}

	# Delete from History

	function	delete_history_by_itemid( $itemid )
	{
		global	$mysql;


		$sql="delete from history where itemid=$itemid";
		$result=mysql_query($sql,$mysql);
	}

	# Add Item definition

	function	add_item($description,$key,$hostid,$delay,$history,$status)
	{
		global	$mysql;

		$sql="insert into items (itemid,description,key_,hostid,delay,history,lastdelete,nextcheck,status) values (NULL,'$description','$key',$hostid,$delay,$history,0,0,$status)";
		$result=mysql_query($sql,$mysql);
		return mysql_insert_id($mysql);
	}

	# Delete Function definition

	function	delete_function_by_triggerid($triggerid)
	{
		global	$mysql;

		$sql="delete from functions where triggerid=$triggerid";
		$result=mysql_query($sql,$mysql);
	}

	# Delete Function and Trigger definitions by itemid

	function	delete_triggers_functions_by_itemid($itemid)
	{
		global	$mysql;

		$sql="select triggerid from functions where itemid=$itemid";
		$result=mysql_query($sql,$mysql);
		while($row=mysql_fetch_row($result))
		{
			delete_function_by_triggerid($row[0]);
		}

		$sql="delete from functions where itemid=$itemid";
		$result=mysql_query($sql,$mysql);
	}

	# Delete Item definition

	function	delete_item($itemid)
	{
		global	$mysql;

		$sql="delete from items where itemid=$itemid";
		$result=mysql_query($sql,$mysql);

		delete_triggers_functions_by_itemid($itemid);
		delete_history_by_itemid($itemid);
	}

	# Add alarm

	function	add_alarm($triggerid,$istrue)
	{
		global	$mysql;

		$now=mktime();
		$sql="insert into alarms(triggerid,clock,istrue) values($triggerid,$now,$istrue)";
		$result=mysql_query($sql,$mysql);
	}

	# Add Trigger definition

	function	add_trigger($expression,$description,$priority,$istrue,$comments)
	{
		global	$mysql;

		$sql="insert into triggers  (triggerid,description,priority,istrue,comments) values (NULL,'$description',$priority,$istrue,'$comments')";
#		echo $sql,"<Br>";
		$result=mysql_query($sql,$mysql);
 
		$triggerid=mysql_insert_id($mysql);
 
		$expression=implode_exp($expression,$triggerid);
		$sql="update triggers set expression='$expression' where triggerid=$triggerid";
#		echo $sql,"<Br>";
		$result=mysql_query($sql,$mysql);
	}

	# Delete Trigger definition

	function	delete_trigger($triggerid)
	{
		global	$mysql;

		$sql="delete from triggers where triggerid=$triggerid";
		$result=mysql_query($sql,$mysql);
		
		delete_function_by_triggerid($triggerid);
	}

	# Update Trigger definition

	function	update_trigger($triggerid,$expression,$description,$priority,$istrue,$comments)
	{
		global	$mysql;

		delete_trigger($triggerid);

		$triggerid=add_trigger($expression,$description,$priority,$istrue,$comments);
	}

	# Add User definition

	function	add_user($groupid,$name,$surname,$alias,$passwd)
	{
		global	$mysql;

		$sql="insert into users (userid,groupid,name,surname,alias,passwd) values (NULL,$groupid,'$name','$surname','$alias','$passwd')";
		$result=mysql_query($sql,$mysql);

		return	$result;
	}

	# Add Items and Triggers from template

	function	add_from_templates($hostid,$platformid,$host)
	{
		global	$mysql;

		$result=mysql_query("select itemtemplateid,description,key_,delay from items_template where platformid=$platformid",$mysql);
		while($row=mysql_fetch_row($result))
		{
			$itemtemplateid=$row[0];
			$description=$row[1];
			$key=$row[2];
			$delay=$row[3];

			$itemid=add_item($description,$key,$hostid,$delay,24*3600,0);

			$result2=mysql_query("select triggertemplateid,description,expression from triggers_template where itemtemplateid=$itemtemplateid",$mysql);
			while($row2=mysql_fetch_row($result2))
			{
				$itemtemplateid=$row2[0];
				$description=$row2[1];
				$expression=$row2[2];

				for($i=0;$i<strlen($expression);$i++)
				{
					if($expression[$i] == ':')
					{
						$expression=substr($expression,0,$i)."$host:$key".substr($expression,$i);
						
						break;
					}
				}

				add_trigger($expression,$description,0,2,"");
			}
		}
	}

	# Add Host definition

	function	add_host($platformid,$host,$port,$status)
	{
		global	$mysql;

		$sql="insert into hosts (hostid,platformid,host,port,status) values (NULL,$platformid,'$host',$port,$status)";
		$result=mysql_query($sql,$mysql);
		$hostid=mysql_insert_id($mysql);

		add_from_templates($hostid,$platformid,$host);

	}

	# Add Media definition

	function	add_media( $userid, $type, $sendto)
	{
		global	$mysql;

		$sql="insert into media (userid,mediaid,type,sendto,active) values ($userid,NULL,'$type','$sendto',0)";
		$result=mysql_query($sql,$mysql);
	}

	# Delete Media definition

	function	delete_media($mediaid)
	{
		global	$mysql;

		$sql="delete from media where mediaid=$mediaid";
		$result=mysql_query($sql,$mysql);
	}

	# Delete Media definition by userid

	function	delete_media_by_userid($userid)
	{
		global	$mysql;

		$sql="delete from media where userid=$userid";
		$result=mysql_query($sql,$mysql);
	}

	# Update configuration

	function	update_config($smtp_server,$smtp_helo,$smtp_email,$password_required)
	{
		global	$mysql;

		$sql="update config set smtp_server=\"$smtp_server\",smtp_helo=\"$smtp_helo\",smtp_email=\"$smtp_email\",password_required=$password_required";
		$result=mysql_query($sql,$mysql);
	}


	# Activate Media

	function	activate_media($mediaid)
	{
		global	$mysql;

		$sql="update media set active=0 where mediaid=$mediaid";
		$result=mysql_query($sql,$mysql);
	}

	# Disactivate Media

	function	disactivate_media($mediaid)
	{
		global	$mysql;

		$sql="update media set active=1 where mediaid=$mediaid";
		$result=mysql_query($sql,$mysql);
	}

	# Delete Host

	function	delete_host($hostid)
	{
		global	$mysql;

		$sql="select itemid from items where hostid=$hostid";
		$result=mysql_query($sql,$mysql);
		while($row=mysql_fetch_row($result))
		{
			delete_item($row[0]);
		}

		$sql="delete from hosts where hostid=$hostid";
		$result=mysql_query($sql,$mysql);
	}

	# Delete User definition

	function	delete_user($userid)
	{
		global	$mysql;

		delete_media_by_userid($userid);
		delete_actions_by_userid($userid);

		$sql="delete from users where userid=$userid";
		$result=mysql_query($sql,$mysql);
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

		echo "<center>";

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
		echo "<IMG SRC=\"chart.html?itemid=$itemid&period=$period&from=$from\">"; 
		echo("<hr>");

 
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


		if ($From>0) // 12h FORWARD
		{
			$tmp=$from-12;
			echo("[<A HREF=\"history.html?action=showhistory&itemid=$itemid&from=$tmp&period=$period\">");
			echo("12h forward</A>] ");
		}
		else
		{
			echo("[12h forward]");
		}

		if ($From>0) // WEEK Forward
		{
			$tmp=$from-12*14;
			echo("[<A HREF=\"history.html?action=showhistory&itemid=$itemid&from=$tmp&period=$period\">");
			echo("Week forward</A>] ");
		}
		else
		{
			echo("[Week forward]");
		}

		echo("</body></html>\n");
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
		global	$mysql;

		if(isset($itemid))
		{
			mysql_select_db("monitor");
			$result=mysql_query("select i.description, i.key_, h.host, h.port, i.delay, i.history, i.status from items i,hosts h where i.itemid=$itemid and h.hostid=i.hostid",$mysql);
			$row=mysql_fetch_row($result);
		
			$description=$row[0];
			$key=$row[1];
			$host=$row[2];
			$port=$row[3];
			$delay=$row[4];
			$history=$row[5];
			$status=$row[6];
		}
		if( !isset($port) )
		{
			$port=10000;
		}
		if( !isset($delay) )
		{
			$delay=30;
		}
		if( !isset($history) )
		{
			$history=30*24*3600;
		}
		if( !isset($status) )
		{
			$status=0;
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
	        $result=mysql_query("select hostid,host from hosts order by host",$mysql);
	        while($row=mysql_fetch_row($result))
	        {
	                $hostid_=$row[0];
	                $host_=$row[1];
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
		echo "Key";
		show_table2_h_delimiter();
		echo "<input name=\"key\" value=\"$key\"size=40>";

		show_table2_v_delimiter();
		echo "Delay";
		show_table2_h_delimiter();
		echo "<input name=\"delay\" value=\"$delay\"size=5>";

		show_table2_v_delimiter();
		echo "History";
		show_table2_h_delimiter();
		echo "<input name=\"history\" value=\"$history\"size=8>";

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

	# Insert form for Trigger
	function	insert_trigger_form($triggerid)
	{
		global	$mysql;

		if(isset($triggerid))
		{
			$result=mysql_query("select expression,description,priority,istrue,comments from triggers where triggerid=$triggerid",$mysql);
			$row=mysql_fetch_row($result);
	
			if(!isset($expression)) $expression=$row[0];
			$expression=explode_exp($expression,0);

			if(!$description) $description=$row[1];
			if(!$priority) $priority=$row[2];
			if(!$istrue) $istrue=$row[3];
			if(!$comments) $comments=$row[6];
		}
		
		echo "<br>";

		show_table2_header_begin();
		echo "Trigger configuration";
 
		show_table2_v_delimiter();
		echo "<form method=\"post\" action=\"triggers.html\">";
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
		if($istrue==2) { echo "CHECKED"; }
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
	}
?>
