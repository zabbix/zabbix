<?php
/*
** ZABBIX
** Copyright (C) 2000-2008 SIA Zabbix
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
	require_once('include/config.inc.php');
	require_once('include/blocks.inc.php');

	$page['title'] = "S_STATUS_OF_ZABBIX";
	$page['file'] = 'report1.php';
	$page['hist_arg'] = array();

include_once('include/page_header.php');

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array();

	check_fields($fields);
?>
<?php
	$rprt_wdgt = new CWidget();
	$rprt_wdgt->addPageHeader(S_STATUS_OF_ZABBIX_BIG);

	$rprt_wdgt->addHeader(S_REPORT_BIG);
	$rprt_wdgt->addItem(BR());

	$rprt_wdgt->addItem(make_status_of_zbx());

	$rprt_wdgt->show();
?>
<?php

include_once('include/page_footer.php');

?>
