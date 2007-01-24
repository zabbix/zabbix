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
	require_once "include/config.inc.php";
	require_once "include/hosts.inc.php";
	require_once "include/httptest.inc.php";
	require_once "include/forms.inc.php";

        $page["title"] = "S_DETAILS_OF_SCENARIO";
        $page["file"] = "httpdetails.php";

include_once "include/page_header.php";

	insert_confirm_javascript();
?>
<?php

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"httptestid"=>	array(T_ZBX_INT, O_MAND,	null,	DB_ID,		null),

		"groupid"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		"hostid"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null)
	);

	check_fields($fields);

	$accessible_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE,null,null,$ZBX_CURNODEID);

	if(!($httptest_data = DBfetch(DBselect('select ht.httptestid from httptest ht, applications a '.
		' where a.hostid in ('.$accessible_hosts.') and a.applicationid=ht.applicationid '.
		' and ht.httptestid='.$_REQUEST['httptestid']))))
	{
		access_deny();
	}
	
?>
<?php
	$lnkCancel = new CLink(S_CANCEL,'httpmon.php'.url_param('groupid').url_param('hostid'));
	show_table_header(S_DETAILS_OF_SCENARIO_BIG.' "'.bold($accessible_hosts['name']).'"',$lnkCancel);

// TABLE
	$table  = new CTableInfo();

	$db_httpsteps = DBselect('select * from httpstep where httptestid='.$httptest_data['httptestid'].' order by no');
	while($httpstep_data = DBfetch($db_httpsteps))
	{
		$information = array(
			S_STEP, ': ',
			bold($httpstep_data['name']), BR,
			S_LAST_CHECK, ": TODO: TT || UNCNOWN", BR,// TODO!!!
			S_STATUS, ": TODO: FAIL || OK || UNCNOWN", BR // TODO!!!
			);

		$chart1 = $chart2 = $chart3 = new CImg('chart3.php?period=3600&from=0&name=KEY_NAME&width=150&height=25');

		$table->AddRow(array($information, $chart1, $chart2, $chart3));
	}
	$table->AddRow(array(bold('CONCLUSION'), $chart1, $chart2, $chart3));

	$table->Show();
?>
<?php

include_once "include/page_footer.php"

?>
