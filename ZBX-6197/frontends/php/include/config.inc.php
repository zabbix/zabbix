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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


require_once dirname(__FILE__).'/classes/core/Z.php';
Z::getInstance()->run();

require_once dirname(__FILE__).'/debug.inc.php';
require_once dirname(__FILE__).'/gettextwrapper.inc.php';
require_once dirname(__FILE__).'/defines.inc.php';
require_once dirname(__FILE__).'/func.inc.php';
require_once dirname(__FILE__).'/html.inc.php';

CProfiler::getInstance()->start();

require_once dirname(__FILE__).'/profiles.inc.php';
require_once dirname(__FILE__).'/../conf/maintenance.inc.php';

// abc sorting
require_once dirname(__FILE__).'/acknow.inc.php';
require_once dirname(__FILE__).'/actions.inc.php';
require_once dirname(__FILE__).'/discovery.inc.php';
require_once dirname(__FILE__).'/events.inc.php';
require_once dirname(__FILE__).'/graphs.inc.php';
require_once dirname(__FILE__).'/hosts.inc.php';
require_once dirname(__FILE__).'/httptest.inc.php';
require_once dirname(__FILE__).'/ident.inc.php';
require_once dirname(__FILE__).'/images.inc.php';
require_once dirname(__FILE__).'/items.inc.php';
require_once dirname(__FILE__).'/maintenances.inc.php';
require_once dirname(__FILE__).'/maps.inc.php';
require_once dirname(__FILE__).'/media.inc.php';
require_once dirname(__FILE__).'/nodes.inc.php';
require_once dirname(__FILE__).'/services.inc.php';
require_once dirname(__FILE__).'/sounds.inc.php';
require_once dirname(__FILE__).'/triggers.inc.php';
require_once dirname(__FILE__).'/users.inc.php';
require_once dirname(__FILE__).'/valuemap.inc.php';

global $USER_DETAILS, $USER_RIGHTS, $ZBX_PAGE_POST_JS, $page;
global $ZBX_LOCALNODEID, $ZBX_LOCMASTERID, $ZBX_CONFIGURATION_FILE, $DB;
global $ZBX_SERVER, $ZBX_SERVER_PORT;
global $ZBX_LOCALES;

$page = array();
$USER_DETAILS = array();
$USER_RIGHTS = array();
$ZBX_LOCALNODEID = 0;
$ZBX_LOCMASTERID = 0;
$ZBX_CONFIGURATION_FILE = './conf/zabbix.conf.php';
$ZBX_CONFIGURATION_FILE = realpath(dirname($ZBX_CONFIGURATION_FILE)).DIRECTORY_SEPARATOR.basename($ZBX_CONFIGURATION_FILE);

// include tactical overview modules
require_once dirname(__FILE__).'/locales.inc.php';
require_once dirname(__FILE__).'/perm.inc.php';
require_once dirname(__FILE__).'/audit.inc.php';
require_once dirname(__FILE__).'/js.inc.php';

// include validation
require_once dirname(__FILE__).'/validate.inc.php';

function zbx_err_handler($errno, $errstr, $errfile, $errline) {
	// necessary to surpress errors when calling with error control operator like @function_name()
	if (error_reporting() === 0) {
		return true;
	}

	$pathLength = strlen(__FILE__);

	$pathLength -= 22;
	$errfile = substr($errfile, $pathLength);

	error($errstr.' ['.$errfile.':'.$errline.']');
}

/*
 * start initialization
 */
set_error_handler('zbx_err_handler');
unset($show_setup);

if (defined('ZBX_DENY_GUI_ACCESS')) {
	if (isset($ZBX_GUI_ACCESS_IP_RANGE) && is_array($ZBX_GUI_ACCESS_IP_RANGE)) {
		$user_ip = (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
		if (!str_in_array($user_ip, $ZBX_GUI_ACCESS_IP_RANGE)) {
			$DENY_GUI = true;
		}
	}
	else {
		$DENY_GUI = true;
	}
}

if (file_exists($ZBX_CONFIGURATION_FILE) && !isset($_COOKIE['ZBX_CONFIG']) && !isset($DENY_GUI)) {
	$config = new CConfigFile($ZBX_CONFIGURATION_FILE);
	if ($config->load()) {
		$config->makeGlobal();
	}
	else {
		$show_warning = true;
		define('ZBX_DISTRIBUTED', false);
		if (!defined('ZBX_PAGE_NO_AUTHORIZATION')) {
			define('ZBX_PAGE_NO_AUTHORIZATION', true);
		}
		error($config->error);
	}

	require_once dirname(__FILE__).'/db.inc.php';

	if (!isset($show_warning)) {
		$error = '';
		if (!DBconnect($error)) {
			$_REQUEST['message'] = $error;

			define('ZBX_DISTRIBUTED', false);
			if (!defined('ZBX_PAGE_NO_AUTHORIZATION')) {
				define('ZBX_PAGE_NO_AUTHORIZATION', true);
			}
			$show_warning = true;
		}
		else {
			global $ZBX_LOCALNODEID, $ZBX_LOCMASTERID;

			// init LOCAL NODE ID
			if ($local_node_data = DBfetch(DBselect('SELECT n.* FROM nodes n WHERE n.nodetype=1 ORDER BY n.nodeid'))) {
				$ZBX_LOCALNODEID = $local_node_data['nodeid'];
				$ZBX_LOCMASTERID = $local_node_data['masterid'];
				$ZBX_NODES[$local_node_data['nodeid']] = $local_node_data;
				define('ZBX_DISTRIBUTED', true);
			}
			else {
				define('ZBX_DISTRIBUTED', false);
			}
			unset($local_node_data);
		}
		unset($error);
	}
}
else {
	if (file_exists($ZBX_CONFIGURATION_FILE)) {
		ob_start();
		include $ZBX_CONFIGURATION_FILE;
		ob_end_clean();
	}

	require_once dirname(__FILE__).'/db.inc.php';

	if (!defined('ZBX_PAGE_NO_AUTHORIZATION')) {
		define('ZBX_PAGE_NO_AUTHORIZATION', true);
	}
	define('ZBX_DISTRIBUTED', false);
	$show_setup = true;
}

if (!defined('ZBX_PAGE_NO_AUTHORIZATION') && !defined('ZBX_RPC_REQUEST')) {
	if (!CWebUser::checkAuthentication(get_cookie('zbx_sessionid'))) {
		include('index.php');
		exit();
	}

	if (function_exists('bindtextdomain')) {
		// initializing gettext translations depending on language selected by user
		$locales = zbx_locale_variants(CWebUser::$data['lang']);
		$locale_found = false;
		foreach ($locales as $locale) {
			putenv('LC_ALL='.$locale);
			putenv('LANG='.$locale);
			putenv('LANGUAGE='.$locale);

			if (setlocale(LC_ALL, $locale)) {
				$locale_found = true;
				CWebUser::$data['locale'] = $locale;
				break;
			}
		}

		if (!$locale_found && CWebUser::$data['lang'] != 'en_GB' && CWebUser::$data['lang'] != 'en_gb') {
			error('Locale for language "'.CWebUser::$data['lang'].'" is not found on the web server. Tried to set: '.implode(', ', $locales).'. Unable to translate Zabbix interface.');
		}
		bindtextdomain('frontend', 'locale');
		bind_textdomain_codeset('frontend', 'UTF-8');
		textdomain('frontend');
	}
	else {
		error('Your PHP has no gettext support. Zabbix translations are not available.');
	}

	// numeric Locale to default
	setlocale(LC_NUMERIC, array('C', 'POSIX', 'en', 'en_US', 'en_US.UTF-8', 'English_United States.1252', 'en_GB', 'en_GB.UTF-8'));
}
else {
	CWebUser::setDefault();
}

// should be after locale initialization
require_once dirname(__FILE__).'/translateDefines.inc.php';

set_zbx_locales();

// init mb strings if it's available
init_mbstrings();

// ajax - do not need warnings or errors
if ((isset($DENY_GUI) || isset($show_setup) || isset($show_warning)) && PAGE_TYPE_HTML <> detect_page_type()) {
	header('Ajax-response: false');
	exit();
}

if (isset($DENY_GUI)) {
	unset($show_warning);
	require_once dirname(__FILE__).'/../warning.php';
}

if (isset($show_setup)) {
	unset($show_setup);
	require_once dirname(__FILE__).'/../setup.php';
}
elseif (isset($show_warning)) {
	unset($show_warning);
	require_once dirname(__FILE__).'/../warning.php';
}

function access_deny() {
	require_once dirname(__FILE__).'/page_header.php';

	if (CWebUser::$data['alias'] != ZBX_GUEST_USER) {
		show_error_message(_('No permissions to referred object or it does not exist!'));
	}
	else {
		$url = new CUrl(!empty($_REQUEST['request']) ? $_REQUEST['request'] : '');
		$url->setArgument('sid', null);
		$url = urlencode($url->toString());

		$warning = new CWarning(_('You are not logged in.'), array(
			_('You cannot view this URL as a'),
			SPACE,
			bold(ZBX_GUEST_USER),
			'. ',
			_('You must login to view this page.'),
			BR(),
			_('If you think this message is wrong, please consult your administrators about getting the necessary permissions.')
		));
		$warning->setButtons(array(
			new CButton('login', _('Login'), 'javascript: document.location = "index.php?request='.$url.'";', 'formlist'),
			new CButton('back', _('Cancel'), 'javascript: window.history.back();', 'formlist')
		));
		$warning->show();
	}

	require_once dirname(__FILE__).'/page_footer.php';
}

function detect_page_type($default = PAGE_TYPE_HTML) {
	if (isset($_REQUEST['output'])) {
		switch (strtolower($_REQUEST['output'])) {
			case 'text':
				return PAGE_TYPE_TEXT;
			case 'ajax':
				return PAGE_TYPE_JS;
			case 'json':
				return PAGE_TYPE_JSON;
			case 'json-rpc':
				return PAGE_TYPE_JSON_RPC;
			case 'html':
				return PAGE_TYPE_HTML_BLOCK;
			case 'img':
				return PAGE_TYPE_IMAGE;
			case 'css':
				return PAGE_TYPE_CSS;
		}
	}

	return $default;
}

function show_messages($bool = true, $okmsg = null, $errmsg = null) {
	global $page, $ZBX_MESSAGES;

	if (!defined('PAGE_HEADER_LOADED')) {
		return null;
	}
	if (defined('ZBX_API_REQUEST')) {
		return null;
	}
	if (!isset($page['type'])) {
		$page['type'] = PAGE_TYPE_HTML;
	}

	$message = array();
	$width = 0;
	$height= 0;

	if (!$bool && !is_null($errmsg)) {
		$msg = _('ERROR').': '.$errmsg;
	}
	elseif ($bool && !is_null($okmsg)) {
		$msg = $okmsg;
	}

	if (isset($msg)) {
		switch ($page['type']) {
			case PAGE_TYPE_IMAGE:
				array_push($message, array(
					'text' => $msg,
					'color' => (!$bool) ? array('R' => 255, 'G' => 0, 'B' => 0) : array('R' => 34, 'G' => 51, 'B' => 68),
					'font' => 2
				));
				$width = max($width, imagefontwidth(2) * zbx_strlen($msg) + 1);
				$height += imagefontheight(2) + 1;
				break;
			case PAGE_TYPE_XML:
				echo htmlspecialchars($msg)."\n";
				break;
			case PAGE_TYPE_HTML:
			default:
				$msg_tab = new CTable($msg, ($bool ? 'msgok' : 'msgerr'));
				$msg_tab->setCellPadding(0);
				$msg_tab->setCellSpacing(0);

				$row = array();

				$msg_col = new CCol(bold($msg), 'msg_main msg');
				$msg_col->setAttribute('id', 'page_msg');
				$row[] = $msg_col;

				if (isset($ZBX_MESSAGES) && !empty($ZBX_MESSAGES)) {
					$msg_details = new CDiv(_('Details'), 'blacklink');
					$msg_details->setAttribute('onclick', 'javascript: showHide("msg_messages", IE ? "block" : "table");');
					$msg_details->setAttribute('title', _('Maximize').'/'._('Minimize'));
					array_unshift($row, new CCol($msg_details, 'clr'));
				}
				$msg_tab->addRow($row);
				$msg_tab->show();
				break;
		}
	}

	if (isset($ZBX_MESSAGES) && !empty($ZBX_MESSAGES)) {
		if ($page['type'] == PAGE_TYPE_IMAGE) {
			$msg_font = 2;
			foreach ($ZBX_MESSAGES as $msg) {
				if ($msg['type'] == 'error') {
					array_push($message, array(
						'text' => $msg['message'],
						'color' => array('R' => 255, 'G' => 55, 'B' => 55),
						'font' => $msg_font
					));
				}
				else {
					array_push($message, array(
						'text' => $msg['message'],
						'color' => array('R' => 155, 'G' => 155, 'B' => 55),
						'font' => $msg_font
					));
				}
				$width = max($width, imagefontwidth($msg_font) * zbx_strlen($msg['message']) + 1);
				$height += imagefontheight($msg_font) + 1;
			}
		}
		elseif ($page['type'] == PAGE_TYPE_XML) {
			foreach ($ZBX_MESSAGES as $msg) {
				echo '['.$msg['type'].'] '.$msg['message']."\n";
			}
		}
		else {
			$lst_error = new CList(null,'messages');
			foreach ($ZBX_MESSAGES as $msg) {
				$lst_error->addItem($msg['message'], $msg['type']);
				$bool = ($bool && 'error' != zbx_strtolower($msg['type']));
			}
			$msg_show = 6;
			$msg_count = count($ZBX_MESSAGES);
			if ($msg_count > $msg_show) {
				$msg_count = $msg_show * 16;
				$lst_error->setAttribute('style', 'height: '.$msg_count.'px;');
			}
			$tab = new CTable(null, ($bool ? 'msgok' : 'msgerr'));
			$tab->setCellPadding(0);
			$tab->setCellSpacing(0);
			$tab->setAttribute('id', 'msg_messages');
			$tab->setAttribute('style', 'width: 100%;');
			if (isset($msg_tab) && $bool) {
				$tab->setAttribute('style', 'display: none;');
			}
			$tab->addRow(new CCol($lst_error, 'msg'));
			$tab->show();
		}
		$ZBX_MESSAGES = null;
	}

	if ($page['type'] == PAGE_TYPE_IMAGE && count($message) > 0) {
		$width += 2;
		$height += 2;
		$canvas = imagecreate($width, $height);
		imagefilledrectangle($canvas, 0, 0, $width, $height, imagecolorallocate($canvas, 255, 255, 255));

		foreach ($message as $id => $msg) {
			$message[$id]['y'] = 1 + (isset($previd) ? $message[$previd]['y'] + $message[$previd]['h'] : 0);
			$message[$id]['h'] = imagefontheight($msg['font']);
			imagestring(
				$canvas,
				$msg['font'],
				1,
				$message[$id]['y'],
				$msg['text'],
				imagecolorallocate($canvas, $msg['color']['R'], $msg['color']['G'], $msg['color']['B'])
			);
			$previd = $id;
		}
		imageOut($canvas);
		imagedestroy($canvas);
	}
}

function show_message($msg) {
	show_messages(true, $msg, '');
}

function show_error_message($msg) {
	show_messages(false, '', $msg);
}

function info($msgs) {
	global $ZBX_MESSAGES;

	zbx_value2array($msgs);
	if (is_null($ZBX_MESSAGES)) {
		$ZBX_MESSAGES = array();
	}
	foreach ($msgs as $msg) {
		array_push($ZBX_MESSAGES, array('type' => 'info', 'message' => $msg));
	}
}

function error($msgs) {
	global $ZBX_MESSAGES;

	if (is_null($ZBX_MESSAGES)) {
		$ZBX_MESSAGES = array();
	}

	$msgs = zbx_toArray($msgs);
	foreach ($msgs as $msg) {
		if (isset(CWebUser::$data['debug_mode']) && !is_object($msg) && !CWebUser::$data['debug_mode']) {
			$msg = preg_replace('/^\[.+?::.+?\]/', '', $msg);
		}
		array_push($ZBX_MESSAGES, array('type' => 'error', 'message' => $msg));
	}
}

function clear_messages($count = null) {
	global $ZBX_MESSAGES;

	$result = array();
	if (!is_null($count)) {
		while ($count-- > 0) {
			array_unshift($result, array_pop($ZBX_MESSAGES));
		}
	}
	else {
		$result = $ZBX_MESSAGES;
		$ZBX_MESSAGES = null;
	}
	return $result;
}

function fatal_error($msg) {
	require_once dirname(__FILE__).'/page_header.php';
	show_error_message($msg);
	require_once dirname(__FILE__).'/page_footer.php';
}

function get_tree_by_parentid($parentid, &$tree, $parent_field, $level = 0) {
	if (empty($tree)) {
		return $tree;
	}

	$level++;
	if ($level > 32) {
		return array();
	}

	$result = array();
	if (isset($tree[$parentid])) {
		$result[$parentid] = $tree[$parentid];
	}

	$tree_ids = array_keys($tree);

	foreach ($tree_ids as $key => $id) {
		$child = $tree[$id];
		if (bccomp($child[$parent_field], $parentid) == 0) {
			$result[$id] = $child;
			$childs = get_tree_by_parentid($id, $tree, $parent_field, $level); // attention recursion !!!
			$result += $childs;
		}
	}
	return $result;
}

function parse_period($str) {
	$out = null;
	$str = trim($str, ';');
	$periods = explode(';', $str);
	foreach ($periods as $period) {
		if (!preg_match('/^([1-7])-([1-7]),([0-9]{1,2}):([0-9]{1,2})-([0-9]{1,2}):([0-9]{1,2})$/', $period, $arr)) {
			return null;
		}

		for ($i = $arr[1]; $i <= $arr[2]; $i++) {
			if (!isset($out[$i])) {
				$out[$i] = array();
			}
			array_push($out[$i], array(
				'start_h' => $arr[3],
				'start_m' => $arr[4],
				'end_h' => $arr[5],
				'end_m' => $arr[6]
			));
		}
	}
	return $out;
}

function get_status() {
	$status = array(
		'triggers_count' => 0,
		'triggers_count_enabled' => 0,
		'triggers_count_disabled' => 0,
		'triggers_count_off' => 0,
		'triggers_count_on' => 0,
		'triggers_count_unknown' => 0,
		'items_count' => 0,
		'items_count_monitored' => 0,
		'items_count_disabled' => 0,
		'items_count_not_supported' => 0,
		'hosts_count' => 0,
		'hosts_count_monitored' => 0,
		'hosts_count_not_monitored' => 0,
		'hosts_count_template' => 0,
		'users_online' => 0,
		'qps_total' => 0
	);

	// server
	$status['zabbix_server'] = zabbixIsRunning() ? _('Yes') : _('No');

	// triggers
	$dbTriggers = DBselect(
		'SELECT COUNT(DISTINCT t.triggerid) AS cnt,t.status,t.value'.
		' FROM triggers t'.
			' INNER JOIN functions f ON t.triggerid=f.triggerid'.
			' INNER JOIN items i ON f.itemid=i.itemid'.
			' INNER JOIN hosts h ON i.hostid=h.hostid'.
		' WHERE i.status='.ITEM_STATUS_ACTIVE.
			' AND h.status='.HOST_STATUS_MONITORED.
		' GROUP BY t.status,t.value');
	while ($dbTrigger = DBfetch($dbTriggers)) {
		switch ($dbTrigger['status']) {
			case TRIGGER_STATUS_ENABLED:
				switch ($dbTrigger['value']) {
					case TRIGGER_VALUE_FALSE:
						$status['triggers_count_off'] = $dbTrigger['cnt'];
						break;
					case TRIGGER_VALUE_TRUE:
						$status['triggers_count_on'] = $dbTrigger['cnt'];
						break;
					case TRIGGER_VALUE_UNKNOWN:
						$status['triggers_count_unknown'] = $dbTrigger['cnt'];
						break;
				}
				break;
			case TRIGGER_STATUS_DISABLED:
				$status['triggers_count_disabled'] += $dbTrigger['cnt'];
				break;
		}
	}
	$status['triggers_count_enabled'] = $status['triggers_count_off'] + $status['triggers_count_on']
		+ $status['triggers_count_unknown'];
	$status['triggers_count'] = $status['triggers_count_enabled'] + $status['triggers_count_disabled'];

	// items
	$dbItems = DBselect(
		'SELECT COUNT(*) AS cnt,i.status'.
		' FROM items i'.
			' INNER JOIN hosts h ON i.hostid=h.hostid'.
		' WHERE h.status='.HOST_STATUS_MONITORED.
			' AND '.dbConditionInt('i.status', array(ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED, ITEM_STATUS_NOTSUPPORTED)).
		' GROUP BY i.status');
	while ($dbItem = DBfetch($dbItems)) {
		switch ($dbItem['status']) {
			case ITEM_STATUS_ACTIVE:
				$status['items_count_monitored'] = $dbItem['cnt'];
				break;
			case ITEM_STATUS_DISABLED:
				$status['items_count_disabled'] = $dbItem['cnt'];
				break;
			case ITEM_STATUS_NOTSUPPORTED:
				$status['items_count_not_supported'] = $dbItem['cnt'];
				break;
		}
	}
	$status['items_count'] = $status['items_count_monitored'] + $status['items_count_disabled']
		+ $status['items_count_not_supported'];

	// hosts
	$dbHosts = DBselect(
		'SELECT COUNT(*) AS cnt,h.status'.
		' FROM hosts h'.
		' WHERE h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.','.HOST_STATUS_TEMPLATE.' )'.
		' GROUP BY h.status');
	while ($dbHost = DBfetch($dbHosts)) {
		switch ($dbHost['status']) {
			case HOST_STATUS_MONITORED:
				$status['hosts_count_monitored'] = $dbHost['cnt'];
				break;
			case HOST_STATUS_NOT_MONITORED:
				$status['hosts_count_not_monitored'] = $dbHost['cnt'];
				break;
			case HOST_STATUS_TEMPLATE:
				$status['hosts_count_template'] = $dbHost['cnt'];
				break;
		}
	}
	$status['hosts_count'] = $status['hosts_count_monitored'] + $status['hosts_count_not_monitored']
		+ $status['hosts_count_template'];

	// users
	$row = DBfetch(DBselect('SELECT COUNT(*) AS usr_cnt FROM users u WHERE '.DBin_node('u.userid')));
	$status['users_count'] = $row['usr_cnt'];
	$status['users_online'] = 0;

	$db_sessions = DBselect(
		'SELECT s.userid,s.status,MAX(s.lastaccess) AS lastaccess'.
		' FROM sessions s'.
		' WHERE '.DBin_node('s.userid').
			' AND s.status='.ZBX_SESSION_ACTIVE.
		' GROUP BY s.userid,s.status'
	);
	while ($session = DBfetch($db_sessions)) {
		if (($session['lastaccess'] + ZBX_USER_ONLINE_TIME) >= time()) {
			$status['users_online']++;
		}
	}

	// comments: !!! Don't forget sync code with C !!!
	$row = DBfetch(DBselect(
		'SELECT SUM(1.0/i.delay) AS qps'.
		' FROM items i,hosts h'.
		' WHERE i.status='.ITEM_STATUS_ACTIVE.
			' AND i.hostid=h.hostid'.
			' AND h.status='.HOST_STATUS_MONITORED.
			' AND i.delay<>0'
	));
	$status['qps_total'] = round($row['qps'], 2);

	return $status;
}

function zabbixIsRunning() {
	global $ZBX_SERVER, $ZBX_SERVER_PORT;

	if (empty($ZBX_SERVER) || empty ($ZBX_SERVER_PORT)) {
		return false;
	}

	$result = (bool) fsockopen($ZBX_SERVER, $ZBX_SERVER_PORT, $errnum, $errstr, ZBX_SOCKET_TIMEOUT);
	if (!$result) {
		clear_messages();
	}

	return $result;
}

function set_image_header($format = null) {
	global $IMAGE_FORMAT_DEFAULT;

	if (is_null($format)) {
		$format = $IMAGE_FORMAT_DEFAULT;
	}

	if (IMAGE_FORMAT_JPEG == $format) {
		header('Content-type:  image/jpeg');
	}
	if (IMAGE_FORMAT_TEXT == $format) {
		header('Content-type:  text/html');
	}
	else {
		header('Content-type:  image/png');
	}

	header('Expires: Mon, 17 Aug 1998 12:51:50 GMT');
}

function imageOut(&$image, $format = null) {
	global $page, $IMAGE_FORMAT_DEFAULT;

	if (is_null($format)) {
		$format = $IMAGE_FORMAT_DEFAULT;
	}

	ob_start();

	if (IMAGE_FORMAT_JPEG == $format) {
		imagejpeg($image);
	}
	else {
		imagepng($image);
	}

	$imageSource = ob_get_contents();
	ob_end_clean();

	if ($page['type'] != PAGE_TYPE_IMAGE) {
		session_start();
		$imageId = md5(strlen($imageSource));
		$_SESSION['image_id'] = array();
		$_SESSION['image_id'][$imageId] = $imageSource;
		session_write_close();
	}

	switch ($page['type']) {
		case PAGE_TYPE_IMAGE:
			echo $imageSource;
			break;
		case PAGE_TYPE_JSON:
			$json = new CJSON();
			echo $json->encode(array('result' => $imageId));
			break;
		case PAGE_TYPE_TEXT:
		default:
			echo $imageId;
	}
}

function encode_log($data) {
	return (defined('ZBX_LOG_ENCODING_DEFAULT') && function_exists('mb_convert_encoding'))
		? mb_convert_encoding($data, _('UTF-8'), ZBX_LOG_ENCODING_DEFAULT)
		: $data;
}

// function used in defines, so can't move it to func.inc.php
function zbx_stripslashes($value) {
	if (is_array($value)) {
		foreach ($value as $id => $data) {
			$value[$id] = zbx_stripslashes($data);
		}
	}
	elseif (is_string($value)) {
		$value = stripslashes($value);
	}
	return $value;
}

function no_errors() {
	global $ZBX_MESSAGES;

	foreach ($ZBX_MESSAGES as $message) {
		if ($message['type'] == 'error') {
			return false;
		}
	}

	return true;
}
