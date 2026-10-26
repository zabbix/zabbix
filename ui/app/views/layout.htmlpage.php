<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


/**
 * @var CView $this
 * @var array $data
 */

function local_showDocumentHead(array $data): void {
	header('Content-Type: text/html; charset=UTF-8');
	header('X-Content-Type-Options: nosniff');
	header('X-XSS-Protection: 1; mode=block');

	if (strcasecmp($data['config']['x_frame_options'], 'null') != 0) {
		$x_frame_options = $data['config']['x_frame_options'];

		if (strcasecmp($x_frame_options, 'SAMEORIGIN') == 0) {
			header('X-Frame-Options: SAMEORIGIN');
		}
		elseif (strcasecmp($x_frame_options, 'DENY') == 0) {
			header('X-Frame-Options: DENY');
		}
		else {
			header('Content-Security-Policy: frame-ancestors '.$x_frame_options);
		}
	}

	echo (new CPartial('layout.htmlpage.header', [
		'javascript' => [
			'files' => $data['javascript']['files']
		],
		'stylesheet' => [
			'files' => $data['stylesheet']['files']
		],
		'page' => [
			'title' => $data['page']['title']
		],
		'user' => [
			'lang' => CWebUser::$data['lang'],
			'theme' => CWebUser::$data['theme']
		],
		'web_layout_mode' => $data['web_layout_mode'],
		'config' => [
			'server_check_interval' => $data['config']['server_check_interval']
		]
	]))->getOutput();
}

function local_showSkipToMainContentLink(): void {
	echo (new CLink(_('Skip to main content'), '#'.CHtmlPage::PAGE_TITLE_ID))
		->addClass(ZBX_STYLE_BTN)
		->addClass('skip-link');
}

function local_showSidebar(array $data): void {
	global $ZBX_SERVER_NAME;

	if ($data['web_layout_mode'] == ZBX_LAYOUT_NORMAL) {
		echo (new CPartial('layout.htmlpage.aside', [
			'server_name' => isset($ZBX_SERVER_NAME) ? $ZBX_SERVER_NAME : ''
		]))->getOutput();
	}
}

function local_showContent(array $data): void {
	$messages = get_prepared_messages(['with_current_messages' => true]);

	echo unpack_object($messages);

	echo $data['main_block'];

	echo makeServerStatusOutput()->toString();
}

local_showDocumentHead($data);

echo '<body>';

if ($data['web_layout_mode'] != ZBX_LAYOUT_KIOSKMODE) {
	local_showSkipToMainContentLink();
}

local_showSidebar($data);

local_showContent($data);

require_once 'include/views/js/common.init.js.php';

insertPagePostJs();

echo '</body></html>';
