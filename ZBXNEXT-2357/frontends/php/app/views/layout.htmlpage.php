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

require_once 'include/menu.inc.php';

	function local_generateHeader($data) {
// Only needed for zbx_construct_menu;
global $page;

		header('Content-Type: text/html; charset=UTF-8');

		// construct menu
		$main_menu = array();
		$sub_menus = array();

		$denied_page_requested = zbx_construct_menu($main_menu, $sub_menus, $page, $data['controller']['action']);

		// render the "Deny access" page
		if ($denied_page_requested) {
			access_deny(ACCESS_DENY_PAGE);
		}

		$pageHeader = new CView('layout.htmlpage.header', $data);
		echo $pageHeader->getOutput();

		$pageTop = new CView('layout.htmlpage.top', $data);
		echo $pageTop->getOutput();

		if ($data['fullscreen'] == 0) {
			$data['main_menu'] = $main_menu;
			$data['sub_menus'] = $sub_menus;
			$pageMenu = new CView('layout.htmlpage.menu', $data);
			echo $pageMenu->getOutput();

			// create history
			$table = new CTable(null, 'history left');
			$table->addRow(new CRow(array(
				new CCol(_('History').':', 'caption'),
				get_user_history()
			)));
			$table->show();

			// Should be replaced with addPostJS() at some point
			zbx_add_post_js('initMessages({});');

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
	}

	function local_generateFooter($data) {
		$data['alias'] = CWebUser::$data['alias'];
		$data['userid'] = CWebUser::$data['userid'];
		$pageFooter = new CView('layout.htmlpage.footer', $data);
		echo $pageFooter->getOutput();
		echo '</body>'."\n".'</html>'."\n";
	}

	function local_showMessage() {
		global $ZBX_MESSAGES;

		if (!isset($_SESSION['messageOk']) && !isset($_SESSION['messageError'])) {
			return;
		}

		$messageOk = null;
		$messageError = null;

		if (isset($_SESSION['messages'])) {
			$ZBX_MESSAGES = $_SESSION['messages'];
			unset($_SESSION['messages']);
		}
		if (isset($_SESSION['messageOk'])) {
			$messageOk = $_SESSION['messageOk'];
			unset($_SESSION['messageOk']);
		}
		if (isset($_SESSION['messageError'])) {
			$messageError = $_SESSION['messageError'];
			unset($_SESSION['messageError']);
		}

		if ($messageOk !== null) {
			show_messages(true, $messageOk);
		}
		else {
			show_messages(false, null, $messageError);
		}

	}

	local_generateHeader($data);
	local_showMessage();
	echo $data['javascript']['pre'];
	echo $data['main_block'];

	// Add post JS code
	echo "<script type=\"text/javascript\">\n";
	echo "jQuery(document).ready(function() {\n";
	echo $data['javascript']['post'];
	echo "});\n";
	echo "</script>\n";

	local_generateFooter($data);

show_messages();
