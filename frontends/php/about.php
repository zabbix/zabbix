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
	include "include/config.inc.php";
	$page["title"] = "About Zabbix";
	$page["file"] = "about.php";
	show_header($page["title"],0,0);
?>

<?php
	show_table_header("Information about Zabbix (v1.0)");
?>

<TABLE BORDER=0 COLS=4 WIDTH=100% BGCOLOR="#CCCCCC" cellspacing=1 cellpadding=3>
<TR BGCOLOR=#EEEEEE>
<TD ALIGN=LEFT>
	<font face="Helvetica"><a href="http://www.zabbix.com">Homepage of Zabbix</a></font><br>
</TD>
<TD ALIGN=LEFT>
	This is home page of Zabbix.
</TD>
</TR>
<TR BGCOLOR=#DDDDDD>
<TD ALIGN=LEFT>
	<font face="Helvetica"><a href="http://www.zabbix.com/#manual">Latest Zabbix Manual</a></font><br>
</TD>
<TD>
	Latest version of the Manual.
</TR>
<TR BGCOLOR=#EEEEEE>
<TD ALIGN=LEFT>
	<font face="Helvetica"><a href="http://sourceforge.net/project/showfiles.php?group_id=23494&release_id=40630">Downloads</a></font><br>
</TD>
<TD>
	Latest Zabbix release can be found here.
</TR>
<TR BGCOLOR=#DDDDDD>
<TD ALIGN=LEFT>
	<font face="Helvetica"><a href="http://sourceforge.net/tracker/?atid=378686&group_id=23494&func=browse">Feature requests</a></font><br>
</TD>
<TD>
	If you need additional functionality, go here.
</TD>
</TR>
<TR BGCOLOR=#EEEEEE>
<TD ALIGN=LEFT>
	<font face="Helvetica"><a href="http://sourceforge.net/forum/?group_id=23494">Forums</a></font><br>
</TD>
<TD>
	Zabbix-related discussion.
</TD>
</TR>
<TR BGCOLOR=#DDDDDD>
<TD ALIGN=LEFT>
	<font face="Helvetica"><a href="http://sourceforge.net/tracker/?group_id=23494&atid=378683">Bug reports</a></font><br>
</TD>
<TD>
	Bug in Zabbix ? Please, report it.
</TD>
</TR>
<TR BGCOLOR=#EEEEEE>
<TD ALIGN=LEFT>
	<font face="Helvetica"><a href="http://sourceforge.net/mail/?group_id=23494">Mailing lists</a></font><br>
</TD>
<TD>
	Zabbix-related mailing lists.
</TD>
</TR>
</TABLE>

<?php
	show_footer();
?>
