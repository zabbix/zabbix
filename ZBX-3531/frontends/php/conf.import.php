<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once 'include/config.inc.php';
require_once 'include/page_header.php';

$fields = array(
	'rules' => array(T_ZBX_STR, O_OPT, null, null,	null),
	'import' => array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null),
);
check_fields($fields);


$data = array();
if (isset($_REQUEST['form_refresh'])) {
	$data['rules'] = get_request('rules', array());
}
else {
	$data['rules'] = array(
		'hosts' => array('exist' => true, 'missed' => true),
		'templates' => array('exist' => true, 'missed' => true),
		'template_linkages' => array('exist' => true, 'missed' => true),
		'items' => array('exist' => true, 'missed' => true),
		'discoveryrules' => array('exist' => true, 'missed' => true),
		'triggers' => array('exist' => true, 'missed' => true),
		'graphs' => array('exist' => true, 'missed' => true),
		'screens' => array('exist' => true, 'missed' => true),
		'maps' => array('exist' => true, 'missed' => true),
		'images' => array('exist' => false, 'missed' => false),
	);
}

if (isset($_FILES['import_file']) && is_file($_FILES['import_file']['tmp_name'])) {
	$result = zbxXML::import($_FILES['import_file']['tmp_name']);

	show_messages($result, _('Imported successfully'), _('Import failed'));
}



$view = new CView('conf.import', $data);
$view->render();
$view->show();

require_once('include/page_footer.php');
