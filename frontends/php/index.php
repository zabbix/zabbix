<?
	$page["title"]="Zabbix main page";
	$page["file"]="index.php";

	include "include/config.inc.php";

	if(isset($HTTP_GET_VARS["reconnect"]))
	{
		setcookie("sessionid",$HTTP_COOKIE_VARS["sessionid"],time()-3600);
		unset($HTTP_COOKIE_VARS["sessionid"]);
	}

	if(isset($HTTP_POST_VARS["register"])&&($HTTP_POST_VARS["register"]=="Enter"))
	{
		$password=md5($HTTP_POST_VARS["password"]);
		$sql="select u.userid,u.alias,u.name,u.surname from users u where u.alias='".$HTTP_POST_VARS["name"]."' and u.passwd='$password'";
		$result=DBselect($sql);
		if(DBnum_rows($result)==1)
		{
			$USER_DETAILS["userid"]=DBget_field($result,0,0);
			$USER_DETAILS["alias"]=DBget_field($result,0,1);
			$USER_DETAILS["name"]=DBget_field($result,0,2);
			$USER_DETAILS["surname"]=DBget_field($result,0,3);
			$sessionid=md5(time().$password.$HTTP_POST_VARS["name"].rand(0,10000000));
			setcookie("sessionid",$HTTP_COOKIE_VARS["sessionid"],time()+3600);
			$sql="insert into sessions (sessionid,userid,lastaccess) values ('".$HTTP_COOKIE_VARS["sessionid"]."',".$USER_DETAILS["userid"].",".time().")";
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
		echo "<div align=center>";
		echo "Press <a href=\"index.php?reconnect=1\">here</a> to disconnect/reconnect";
		echo "</div>";
	}	
?>

<?
	show_footer();
?>
