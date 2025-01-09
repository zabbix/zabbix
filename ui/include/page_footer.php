<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
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
if (!defined('ZBX_PAGE_NO_MENU')) {
	CProfile::update('web.paging.lastpage', $page['file'], PROFILE_TYPE_STR);
}

// end transactions if they have not been closed already
if (isset($DB) && isset($DB['TRANSACTIONS']) && $DB['TRANSACTIONS'] != 0) {
	error(_('Transaction has not been closed. Aborting...'));
	DBend(false);
}

// Display unexpected messages (if any) generated while processing the output.
echo get_prepared_messages(['with_current_messages' => true]);

if ($page['type'] == PAGE_TYPE_HTML) {
	makeServerStatusOutput()->show();

	if (in_array($page['type'], [PAGE_TYPE_HTML_BLOCK, PAGE_TYPE_HTML])) {
		if (!is_null(CWebUser::$data) && isset(CWebUser::$data['debug_mode'])
				&& CWebUser::$data['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
			CProfiler::getInstance()->stop();
			CProfiler::getInstance()->show();

			(new CSimpleButton())
				->addClass(ZBX_STYLE_BTN_DEBUG)
				->show();
		}
	}

	if (!defined('ZBX_PAGE_NO_MENU')) {
		makePageFooter()->show();
	}

	insertPagePostJs(true);

	if (CWebUser::isLoggedIn()) {
		require_once 'include/views/js/common.init.js.php';
	}

	echo '</div></body></html>';
}

session_write_close();
exit();
