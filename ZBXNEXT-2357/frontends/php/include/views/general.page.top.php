<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

$help = new CLink(_('Help'), 'http://www.zabbix.com/documentation/', 'small_font', null, 'nosid');
$help->setTarget('_blank');
$support = new CLink(_('Get support'), 'http://www.zabbix.com/support.php', 'small_font', null, 'nosid');
$support->setTarget('_blank');

$printview = new CLink(_('Print'), '', 'small_font print-link', null, 'nosid');

$page_header_r_col = array($help, '|', $support, '|', $printview, '|');

if (!CWebUser::isGuest()) {
	array_push($page_header_r_col, new CLink(_('Profile'), 'profile.php', 'small_font', null, 'nosid'), '|');
}

if (isset(CWebUser::$data['debug_mode']) && CWebUser::$data['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	$debug = new CLink(_('Debug'), '#debug', 'small_font', null, 'nosid');
	$d_script = " if (!isset('state', this)) { this.state = 'none'; }".
		" if (this.state == 'none') { this.state = 'block'; }".
		" else { this.state = 'none'; }".
		" showHideByName('zbx_debug_info', this.state);";
	$debug->setAttribute('onclick', 'javascript: '.$d_script);
	array_push($page_header_r_col, $debug, '|');
}

if (CWebUser::isGuest()) {
	$page_header_r_col[] = array(new CLink(_('Login'), 'index.php?reconnect=1', 'small_font', null, null));
}
else {
	// it is not possible to logout from HTTP authentication
	// TODO uncomment when ready
/*	$chck = $page['file'] == 'authentication.php' && isset($_REQUEST['save'], $_REQUEST['config']);
	if ($chck && $_REQUEST['config'] == ZBX_AUTH_HTTP || !$chck && isset($config) && $config['authentication_type'] == ZBX_AUTH_HTTP) {
		$logout =  new CLink(_('Logout'), '', 'small_font', null, 'nosid');
		$logout->setHint(_s('It is not possible to logout from HTTP authentication.'), null, false);
	}
	else {
		$logout =  new CLink(_('Logout'), 'index.php?reconnect=1', 'small_font', null, null);
	}*/
	$logout =  new CLink(_('Logout'), 'index.php?reconnect=1', 'small_font', null, null);
	array_push($page_header_r_col, $logout);
}

$logo = new CLink(new CDiv(SPACE, 'zabbix_logo'), 'http://www.zabbix.com/', 'image', null, 'nosid');
$logo->setTarget('_blank');

$top_page_row = array(
	new CCol($logo, 'page_header_l'),
	new CCol($page_header_r_col, 'maxwidth page_header_r')
);

unset($logo, $page_header_r_col, $help, $support);

$table = new CTable(null, 'maxwidth page_header');
$table->setCellSpacing(0);
$table->setCellPadding(5);
$table->addRow($top_page_row);
$table->show();
