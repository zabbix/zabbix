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

	function local_generateHeader() {
// Only needed for zbx_construct_menu;
global $page;

		header('Content-Type: text/html; charset=UTF-8');
//		session_start();

		// construct menu
		$main_menu = array();
		$sub_menus = array();

		$denied_page_requested = zbx_construct_menu($main_menu, $sub_menus, $page);

		// render the "Deny access" page
		if ($denied_page_requested) {
			access_deny(ACCESS_DENY_PAGE);
		}

		$pageHeader = new CView('general.page.header');
		echo $pageHeader->getOutput();

		$pageTop = new CView('general.page.top');
		echo $pageTop->getOutput();

		$data['main_menu'] = $main_menu;
		$data['sub_menus'] = $sub_menus;
		$pageMenu = new CView('general.page.menu', $data);
		echo $pageMenu->getOutput();

	// create history
	// if (isset($page['hist_arg']) && CWebUser::$data['alias'] != ZBX_GUEST_USER && $page['type'] == PAGE_TYPE_HTML && !defined('ZBX_PAGE_NO_MENU')) {
		$table = new CTable(null, 'history left');
		$table->addRow(new CRow(array(
			new CCol(_('History').':', 'caption'),
			get_user_history()
		)));
		$table->show();
	// }
	// elseif ($page['type'] == PAGE_TYPE_HTML && !defined('ZBX_PAGE_NO_MENU')) {
	//	echo SBR;
	// }

		if ($page['type'] == PAGE_TYPE_HTML && $showGuiMessaging) {
			zbx_add_post_js('initMessages({});');
		}

		// if a user logs in after several unsuccessful attempts, display a warning
		if ($failedAttempts = CProfile::get('web.login.attempt.failed', 0)) {
			$attempip = CProfile::get('web.login.attempt.ip', '');
			$attempdate = CProfile::get('web.login.attempt.clock', 0);

			$error_msg = _n('%4$s failed login attempt logged. Last failed attempt was from %1$s on %2$s at %3$s.',
				'%4$s failed login attempts logged. Last failed attempt was from %1$s on %2$s at %3$s.',
				$attempip,
				zbx_date2str(DATE_FORMAT, $attempdate),
				zbx_date2str(TIME_FORMAT, $attempdate),
				$failedAttempts
			);
			error($error_msg);

			CProfile::update('web.login.attempt.failed', 0, PROFILE_TYPE_INT);
		}
		show_messages();

	}

	function local_generateFooter() {
		$data['alias'] = CWebUser::$data['alias'];
		$data['userid'] = CWebUser::$data['userid'];
		$pageFooter = new CView('general.page.footer', $data);
		echo $pageFooter->getOutput();
		echo '</body>'."\n".'</html>'."\n";
	}

	function local_showMessage() {
		$msg = null;
		$msg_err = null;

		if (isset($_SESSION['msg'])) {
			$msg = $_SESSION['msg'];
			unset($_SESSION['msg']);
		}
		if (isset($_SESSION['msg_err'])) {
			$msg_err = $_SESSION['msg_err'];
			unset($_SESSION['msg_err']);
		}

		if (null != $msg) {
			show_message($msg);
		}
		else if (null != $msg_err) {
			show_error_message($msg_err);
		}
	}

	local_generateHeader();
	local_showMessage();
	echo $data['main_block'];
	local_generateFooter();
