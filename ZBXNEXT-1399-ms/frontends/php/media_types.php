<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/media.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Configuration of media types');
$page['file'] = 'media_types.php';
$page['hist_arg'] = array();

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'mediatypeids' =>	array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, null),
	'mediatypeid' =>	array(T_ZBX_INT, O_NO,	P_SYS,	DB_ID, 'isset({form})&&({form}=="edit")'),
	'type' =>			array(T_ZBX_INT, O_OPT, null,	IN(implode(',', array_keys(media_type2str()))), '(isset({save}))'),
	'description' =>	array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY, 'isset({save})'),
	'smtp_server' =>	array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY, 'isset({save})&&isset({type})&&({type}=='.MEDIA_TYPE_EMAIL.')'),
	'smtp_helo' =>		array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY, 'isset({save})&&isset({type})&&({type}=='.MEDIA_TYPE_EMAIL.')'),
	'smtp_email' =>		array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY, 'isset({save})&&isset({type})&&({type}=='.MEDIA_TYPE_EMAIL.')'),
	'exec_path' =>		array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY, 'isset({save})&&isset({type})&&({type}=='.MEDIA_TYPE_EXEC.'||{type}=='.MEDIA_TYPE_EZ_TEXTING.')'),
	'gsm_modem' =>		array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY, 'isset({save})&&isset({type})&&({type}=='.MEDIA_TYPE_SMS.')'),
	'username' =>		array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY, 'isset({save})&&isset({type})&&({type}=='.MEDIA_TYPE_JABBER.'||{type}=='.MEDIA_TYPE_EZ_TEXTING.')'),
	'password' =>		array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY, 'isset({save})&&isset({type})&&({type}=='.MEDIA_TYPE_JABBER.'||{type}=='.MEDIA_TYPE_EZ_TEXTING.')'),
	'status'=>			array(T_ZBX_INT, O_OPT,	null,	IN(array(MEDIA_TYPE_STATUS_ACTIVE, MEDIA_TYPE_STATUS_DISABLED)), null),
	// actions
	'save' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null),
	'delete' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null),
	'cancel' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null),
	'go' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null),
	// form
	'form' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
	'form_refresh' =>	array(T_ZBX_INT, O_OPT, null,	null,	null)
);
check_fields($fields);
validate_sort_and_sortorder('description', ZBX_SORT_UP);

$mediatypeid = get_request('mediatypeid');

/*
 * Permissions
 */
if (isset($_REQUEST['mediatypeid'])) {
	$mediaTypes = API::Mediatype()->get(array(
		'mediatypeids' => $mediatypeid,
		'output' => API_OUTPUT_EXTEND
	));
	if (empty($mediaTypes)) {
		access_deny();
	}
}
if (isset($_REQUEST['go'])) {
	if (!isset($_REQUEST['mediatypeids']) || !is_array($_REQUEST['mediatypeids'])) {
		access_deny();
	}
	else {
		$mediaTypeChk = API::Mediatype()->get(array(
			'mediatypeids' => $_REQUEST['mediatypeids'],
			'countOutput' => true
		));
		if ($mediaTypeChk != count($_REQUEST['mediatypeids'])) {
			access_deny();
		}
	}
}
$_REQUEST['go'] = get_request('go', 'none');

/*
 * Actions
 */
if (isset($_REQUEST['save'])) {
	$mediatype = array(
		'type' => get_request('type'),
		'description' => get_request('description'),
		'smtp_server' => get_request('smtp_server'),
		'smtp_helo' => get_request('smtp_helo'),
		'smtp_email' => get_request('smtp_email'),
		'exec_path' => get_request('exec_path'),
		'gsm_modem' => get_request('gsm_modem'),
		'username' => get_request('username'),
		'passwd' => get_request('password'),
		'status' => get_request('status', MEDIA_TYPE_STATUS_DISABLED)
	);

	if (is_null($mediatype['passwd'])) {
		unset($mediatype['passwd']);
	}

	if (!empty($mediatypeid)) {
		$action = AUDIT_ACTION_UPDATE;
		$mediatype['mediatypeid'] = $mediatypeid;
		$result = API::Mediatype()->update($mediatype);
		show_messages($result, _('Media type updated'), _('Cannot update media type'));
	}
	else {
		$action = AUDIT_ACTION_ADD;
		$result = API::Mediatype()->create($mediatype);
		show_messages($result, _('Media type added'), _('Cannot add media type'));
	}

	if ($result) {
		add_audit($action, AUDIT_RESOURCE_MEDIA_TYPE, 'Media type ['.$mediatype['description'].']');
		unset($_REQUEST['form']);
	}
}
elseif (isset($_REQUEST['delete']) && !empty($mediatypeid)) {
	$result = API::Mediatype()->delete($_REQUEST['mediatypeid']);
	if ($result) {
		unset($_REQUEST['form']);
	}
	show_messages($result, _('Media type deleted'), _('Cannot delete media type'));
}
elseif ($_REQUEST['go'] == 'activate') {
	$mediatypeids = get_request('mediatypeids', array());

	$options = array();

	foreach ($mediatypeids as $mediatypeid) {
		$options[] = array(
			'mediatypeid' => $mediatypeid,
			'status' => MEDIA_TYPE_STATUS_ACTIVE
		);
	}

	$go_result = API::Mediatype()->update($options);

	show_messages($go_result, _('Media type enabled'), _('Cannot enable media type'));
}
elseif ($_REQUEST['go'] == 'disable') {
	$mediatypeids = get_request('mediatypeids', array());

	$options = array();

	foreach ($mediatypeids as $mediatypeid) {
		$options[] = array(
			'mediatypeid' => $mediatypeid,
			'status' => MEDIA_TYPE_STATUS_DISABLED
		);
	}

	$go_result = API::Mediatype()->update($options);

	show_messages($go_result, _('Media type disabled'), _('Cannot disable media type'));
}
elseif ($_REQUEST['go'] == 'delete') {
	$mediatypeids = get_request('mediatypeids', array());

	$go_result = API::Mediatype()->delete($mediatypeids);

	show_messages($go_result, _('Media type deleted'), _('Cannot delete media type'));
}

if ($_REQUEST['go'] != 'none' && isset($go_result) && $go_result) {
	$url = new CUrl();
	$path = $url->getPath();
	insert_js('cookie.eraseArray("'.$path.'")');
}

/*
 * Display
 */
$data['form'] = get_request('form');
if (!empty($data['form'])) {
	$data['mediatypeid'] = $mediatypeid;
	$data['form_refresh'] = get_request('form_refresh', 0);

	if (isset($data['mediatypeid']) && empty($_REQUEST['form_refresh'])) {
		$mediatype = reset($mediaTypes);

		$data['type'] = $mediatype['type'];
		$data['description'] = $mediatype['description'];
		$data['smtp_server'] = $mediatype['smtp_server'];
		$data['smtp_helo'] = $mediatype['smtp_helo'];
		$data['smtp_email'] = $mediatype['smtp_email'];
		$data['exec_path'] = $mediatype['exec_path'];
		$data['gsm_modem'] = $mediatype['gsm_modem'];
		$data['username'] = $mediatype['username'];
		$data['password'] = $mediatype['passwd'];
		$data['status'] = $mediatype['status'];
	}
	else {
		$data['type'] = get_request('type', MEDIA_TYPE_EMAIL);
		$data['description'] = get_request('description', '');
		$data['smtp_server'] = get_request('smtp_server', 'localhost');
		$data['smtp_helo'] = get_request('smtp_helo', 'localhost');
		$data['smtp_email'] = get_request('smtp_email', 'zabbix@localhost');
		$data['exec_path'] = get_request('exec_path', '');
		$data['gsm_modem'] = get_request('gsm_modem', '/dev/ttyS0');
		$data['username'] = get_request('username', ($data['type'] == MEDIA_TYPE_EZ_TEXTING) ? 'username' : 'user@server');
		$data['password'] = get_request('password', '');
		$data['status'] = get_request('status', MEDIA_TYPE_STATUS_ACTIVE);
	}

	// render view
	$mediaTypeView = new CView('administration.mediatypes.edit', $data);
	$mediaTypeView->render();
	$mediaTypeView->show();
}
else {
	// get media types
	$options = array(
		'output' => API_OUTPUT_EXTEND,
		'preservekeys' => true,
		'editable' => true,
		'limit' => ($config['search_limit'] + 1)
	);
	$data['mediatypes'] = API::Mediatype()->get($options);

	// get media types used in actions
	$options = array(
		'mediatypeids' => zbx_objectValues($data['mediatypes'], 'mediatypeid'),
		'output' => array('actionid', 'name'),
		'preservekeys' => 1
	);
	$actions = API::Action()->get($options);
	foreach ($data['mediatypes'] as $number => $mediatype) {
		$data['mediatypes'][$number]['listOfActions'] = array();
		foreach ($actions as $actionid => $action) {
			if (!empty($action['mediatypeids'])) {
				foreach ($action['mediatypeids'] as $actionMediaTypeId) {
					if ($mediatype['mediatypeid'] == $actionMediaTypeId) {
						$data['mediatypes'][$number]['listOfActions'][] = array('actionid' => $actionid, 'name' => $action['name']);
					}
				}
			}
		}
		$data['mediatypes'][$number]['usedInActions'] = !isset($mediatype['listOfActions']);

		// allow sort by mediatype name
		$data['mediatypes'][$number]['typeid'] = $data['mediatypes'][$number]['type'];
		$data['mediatypes'][$number]['type'] = media_type2str($data['mediatypes'][$number]['type']);
	}

	// sort data
	order_result($data['mediatypes'], getPageSortField('description'), getPageSortOrder());
	$data['paging'] = getPagingLine($data['mediatypes']);

	// render view
	$mediaTypeView = new CView('administration.mediatypes.list', $data);
	$mediaTypeView->render();
	$mediaTypeView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
