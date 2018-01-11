<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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


require_once dirname(__FILE__).'/config.inc.php';

// if we include footer in some function
if (!isset($page)) {
	global $page;
}

if (!defined('PAGE_HEADER_LOADED')) {
	define('PAGE_HEADER_LOADED', 1);
}

// last page
if (!defined('ZBX_PAGE_NO_MENU') && $page['file'] != 'profile.php') {
	CProfile::update('web.paging.lastpage', $page['file'], PROFILE_TYPE_STR);
}

if (CProfile::isModified()) {
	DBstart();
	$result = CProfile::flush();
	DBend($result);
}

// end transactions if they have not been closed already
if (isset($DB) && isset($DB['TRANSACTIONS']) && $DB['TRANSACTIONS'] != 0) {
	error(_('Transaction has not been closed. Aborting...'));
	DBend(false);
}

show_messages();

if ($page['type'] == PAGE_TYPE_HTML) {
	// end of article div
	echo '</main>'."\n";
	if (!defined('ZBX_PAGE_NO_MENU')) {
		makePageFooter()->show();
	}
	insertPagePostJs();
	require_once 'include/views/js/common.init.js.php';

	if (in_array($page['type'], [PAGE_TYPE_HTML_BLOCK, PAGE_TYPE_HTML])) {
		if (!is_null(CWebUser::$data) && isset(CWebUser::$data['debug_mode'])
				&& CWebUser::$data['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
			CProfiler::getInstance()->stop();
			CProfiler::getInstance()->show();
			makeDebugButton()->show();
		}
	}

	echo '</body></html>';
}

exit;
