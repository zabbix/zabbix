<?
	$page["title"]="Zabbix main page";
	$page["file"]="index.php";

	include "include/config.inc.php";

	if(isset($reconnect))
	{
		setcookie("sessionid",$sessionid,time()-3600);
		unset($sessionid);
	}

	if(isset($register)&&($register=="Enter"))
	{
		$password=md5($password);
		$sql="select u.userid,u.alias,u.name,u.surname from users u where u.alias='$name' and u.passwd='$password'";
		$result=DBselect($sql);
		if(DBnum_rows($result)==1)
		{
			$USER_DETAILS["userid"]=DBget_field($result,0,0);
			$USER_DETAILS["alias"]=DBget_field($result,0,1);
			$USER_DETAILS["name"]=DBget_field($result,0,2);
			$USER_DETAILS["surname"]=DBget_field($result,0,3);
			$sessionid=md5(time().$password.$name.rand(0,10000000));
			setcookie("sessionid",$sessionid,time()+3600);
			$sql="insert into sessions (sessionid,userid,lastaccess) values ('$sessionid',".$USER_DETAILS["userid"].",".time().")";
			DBexecute($sql);
		}
	}

	show_header($page["title"],0,0);

?>

<?
	if(!isset($sessionid))
	{
		insert_login_form();
	}
	else
	{
		echo "<center>";
		echo "Press <a href=\"index.php?reconnect=1\">here</a> to reconnect";
		echo "</center>";
	}	
//	echo "<center>";
//	echo "<font face=\"arial,helvetica\" size=2>";
//	echo "Connected as ".$USER_DETAILS["alias"]."</b>";
//	echo "</font>";
//	echo "</center>";
?>

<?
	show_footer();
?>
