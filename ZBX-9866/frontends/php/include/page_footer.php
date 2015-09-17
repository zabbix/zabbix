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


require_once dirname(__FILE__).'/config.inc.php';

// if we include footer in some function
if (!isset($page)) {
	global $page;
}
if (!isset($ZBX_PAGE_POST_JS)) {
	global $ZBX_PAGE_POST_JS;
}

if (!defined('PAGE_HEADER_LOADED')) {
	define('PAGE_HEADER_LOADED', 1);
}

// history
if (isset($page['hist_arg']) && CWebUser::$data['alias'] != ZBX_GUEST_USER && $page['type'] == PAGE_TYPE_HTML && !defined('ZBX_PAGE_NO_MENU')) {
	// if URL length is greater than DB field size, skip history update
	$url = getHistoryUrl($page);

	if ($url) {
		DBstart();
		$result = addUserHistory($page['title'], $url);
		DBend($result);
	}
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

if (in_array($page['type'], array(PAGE_TYPE_HTML_BLOCK, PAGE_TYPE_HTML))) {
	if (!is_null(CWebUser::$data) && isset(CWebUser::$data['debug_mode']) && CWebUser::$data['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
		CProfiler::getInstance()->stop();
		CProfiler::getInstance()->show();
	}
}

if ($page['type'] == PAGE_TYPE_HTML) {
	if (!defined('ZBX_PAGE_NO_MENU') && !defined('ZBX_PAGE_NO_FOOTER')) {
		$table = new CTable(null, 'textwhite bold maxwidth ui-widget-header ui-corner-all page_footer');

		$conString = (CWebUser::$data['userid'] == 0)
			? _('Not connected')
			: _s('Connected as \'%1$s\'', CWebUser::$data['alias']);

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

	require_once 'include/views/js/common.init.js.php';

	echo '</body>'."\n".
		'</html>'."\n";
}
exit;
