<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


show_messages();

if ($data['fullscreen'] == 0) {
	$table = new CTable(null, 'textwhite bold maxwidth ui-widget-header ui-corner-all page_footer');

	$conString = _s('Connected as \'%1$s\'', $data['user']['alias']);

	$table->addRow(array(
		new CCol(new CLink(
			_s('Zabbix %1$s Copyright %2$s-%3$s by Zabbix SIA',
			ZABBIX_VERSION, ZABBIX_COPYRIGHT_FROM, ZABBIX_COPYRIGHT_TO),
			ZABBIX_HOMEPAGE, 'highlight', null, true), 'center'),
			new CCol(array(
				new CSpan(SPACE.SPACE.'|'.SPACE.SPACE, 'divider'),
				new CSpan($conString, 'footer_sign')
			), 'right')
		));

	$table->show();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	CProfiler::getInstance()->show();
}

insertPagePostJs();
require_once 'include/views/js/common.init.js.php';
