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
	$page["title"] = S_ABOUT_ZABBIX;
	$page["file"] = "about.php";
	show_header($page["title"],0,0);
?>

<?php
	show_table_header(S_INFORMATION_ABOUT_ZABBIX);
?>

<TABLE BORDER=0 COLS=4 WIDTH=100% BGCOLOR="#CCCCCC" cellspacing=1 cellpadding=3>
<TR BGCOLOR=#EEEEEE>
<TD ALIGN=LEFT>
	<font face="Helvetica"><a href="http://www.zabbix.com"><?php echo S_HOMEPAGE_OF_ZABBIX; ?></a></font><br>
</TD>
<TD ALIGN=LEFT>
<?php echo S_HOMEPAGE_OF_ZABBIX_DETAILS; ?>
</TD>
</TR>
<TR BGCOLOR=#DDDDDD>
<TD ALIGN=LEFT>
	<font face="Helvetica"><a href="http://www.zabbix.com/manual.php>"<?php echo S_LATEST_ZABBIX_MANUAL; ?></a></font><br>
</TD>
<TD>
<?php echo S_LATEST_ZABBIX_MANUAL_DETAILS; ?>
</TR>
<TR BGCOLOR=#EEEEEE>
<TD ALIGN=LEFT>
	<font face="Helvetica"><a href="http://sourceforge.net/project/showfiles.php?group_id=23494&release_id=40630"><?php echo S_DOWNLOADS; ?></a></font><br>
</TD>
<TD>
<?php echo S_DOWNLOADS_DETAILS; ?>
</TR>
<TR BGCOLOR=#DDDDDD>
<TD ALIGN=LEFT>
	<font face="Helvetica"><a href="http://sourceforge.net/tracker/?atid=378686&group_id=23494&func=browse"><?php echo S_FEATURE_REQUESTS; ?></a></font><br>
</TD>
<TD>
<?php echo S_FEATURE_REQUESTS_DETAILS; ?>
</TD>
</TR>
<TR BGCOLOR=#EEEEEE>
<TD ALIGN=LEFT>
	<font face="Helvetica"><a href="http://sourceforge.net/forum/?group_id=23494"><?php echo S_FORUMS; ?></a></font><br>
</TD>
<TD>
<?php echo S_FORUMS_DETAILS; ?>
</TD>
</TR>
<TR BGCOLOR=#DDDDDD>
<TD ALIGN=LEFT>
	<font face="Helvetica"><a href="http://sourceforge.net/tracker/?group_id=23494&atid=378683"><?php echo S_BUG_REPORTS; ?></a></font><br>
</TD>
<TD>
<?php echo S_BUG_REPORTS_DETAILS; ?>
</TD>
</TR>
<TR BGCOLOR=#EEEEEE>
<TD ALIGN=LEFT>
	<font face="Helvetica"><a href="http://sourceforge.net/mail/?group_id=23494"><?php echo S_MAILING_LISTS; ?></a></font><br>
</TD>
<TD>
<?php echo S_MAILING_LISTS_DETAILS; ?>
</TD>
</TR>
</TABLE>

<?php
	show_footer();
?>
