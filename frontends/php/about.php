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
?>
<?php
	include "include/config.inc.php";
	$page["title"] = "S_ABOUT_ZABBIX";
	$page["file"] = "about.php";
	show_header($page["title"],0,0);
?>

<?php
	show_table_header(S_INFORMATION_ABOUT_ZABBIX);
?>

<?php
	table_begin();
	table_row(array("<a href=\"http://www.zabbix.com\">".S_HOMEPAGE_OF_ZABBIX."</a>", S_HOMEPAGE_OF_ZABBIX_DETAILS),0);
	table_row(array("<a href=\"http://www.zabbix.com/manual.php\">".S_LATEST_ZABBIX_MANUAL."</a>", S_LATEST_ZABBIX_MANUAL_DETAILS),1);
	table_row(array("<a href=\"http://sourceforge.net/project/showfiles.php?group_id=23494&release_id=40630\">".S_DOWNLOADS."</a>", S_DOWNLOADS_DETAILS),2);
	table_row(array("<a href=\"http://sourceforge.net/tracker/?atid=378686&group_id=23494&func=browse\">".S_FEATURE_REQUESTS."</a>", S_FEATURE_REQUESTS_DETAILS), 3);
	table_row(array("<a href=\"http://www.zabbix.com/forum\">".S_FORUMS."</a>", S_FORUMS_DETAILS),4);
	table_row(array("<a href=\"http://sourceforge.net/tracker/?group_id=23494&atid=378683\">".S_BUG_REPORTS."</a>", S_BUG_REPORTS_DETAILS),5);
	table_row(array("<a href=\"http://sourceforge.net/mail/?group_id=23494\">".S_MAILING_LISTS."</a>", S_MAILING_LISTS_DETAILS),6);
	table_end();
?>

<?php
	show_footer();
?>
