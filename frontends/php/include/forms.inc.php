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
	include_once 	"include/defines.inc.php";
	include_once 	"include/db.inc.php";

	# Insert form for User
	function	insert_user_form($userid)
	{
		if(isset($userid))
		{
			$result=DBselect("select u.alias,u.name,u.surname,u.passwd,u.url from users u where u.userid=$userid");
	
			$alias=DBget_field($result,0,0);
			$name=DBget_field($result,0,1);
			$surname=DBget_field($result,0,2);
#			$password=DBget_field($result,0,3);
			$password="";
			$url=DBget_field($result,0,4);
		}
		else
		{
			$alias="";
			$name="";
			$surname="";
			$password="";
			$url="";
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

		show_table2_v_delimiter();
		echo "URL (after login)";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"url\" value=\"$url\" size=50>";

		show_table2_v_delimiter2();
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add\">";
		if(isset($userid))
		{
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete\" onClick=\"return Confirm('Delete selected user?');\">";
		}

		show_table2_header_end();
	}
?>
