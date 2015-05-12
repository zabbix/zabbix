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

echo '</article>'."\n";

if ($data['fullscreen'] == 0) {
	echo '<footer>'."\n";
	echo '<a href="http://www.zabbix.com" target="_blank" class="logo"></a>'."\n";
	echo 'Zabbix 2.4.1. &copy; 2001&ndash;2015, <a href="http://www.zabbix.com" target="_blank">Zabbix SIA</a>'."\n";
	echo '</footer>'."\n";
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	CProfiler::getInstance()->show();
}

insertPagePostJs();
require_once 'include/views/js/common.init.js.php';
