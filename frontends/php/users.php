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


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/media.inc.php';
require_once dirname(__FILE__).'/include/users.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';
require_once dirname(__FILE__).'/include/js.inc.php';

$page['title'] = _('Configuration of users');
$page['file'] = 'users.php';
$page['hist_arg'] = array();

require_once dirname(__FILE__).'/include/page_header.php';

$themes = array_keys(Z::getThemes());
$themes[] = THEME_DEFAULT;

//	VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	// users
	'userid' =>				array(T_ZBX_INT, O_NO,	P_SYS,	DB_ID,		'isset({form})&&{form}=="update"'),
	'group_userid' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'filter_usrgrpid' =>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'alias' =>				array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({save})', _('Alias')),
	'name' =>				array(T_ZBX_STR, O_OPT, null,	null,		null, _('Name')),
	'surname' =>			array(T_ZBX_STR, O_OPT, null,	null,		null, _('Surname')),
	'password1' =>			array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})&&isset({form})&&{form}!="update"&&isset({change_password})'),
	'password2' =>			array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})&&isset({form})&&{form}!="update"&&isset({change_password})'),
	'user_type' =>			array(T_ZBX_INT, O_OPT, null,	IN('1,2,3'),'isset({save})'),
	'user_groups' =>		array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	null),
	'user_groups_to_del' =>	array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'user_medias' =>		array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	null),
	'user_medias_to_del' =>	array(T_ZBX_STR, O_OPT, null,	DB_ID,		null),
	'new_groups' =>			array(T_ZBX_STR, O_OPT, null,	null,		null),
	'new_media' =>			array(T_ZBX_STR, O_OPT, null,	null,		null),
	'enable_media' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	'disable_media' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	'lang' =>				array(T_ZBX_STR, O_OPT, null,	null,		null),
	'theme' =>				array(T_ZBX_STR, O_OPT, null,	IN('"'.implode('","', $themes).'"'), 'isset({save})'),
	'autologin' =>			array(T_ZBX_INT, O_OPT, null,	IN('1'),	null),
	'autologout' => 		array(T_ZBX_INT, O_OPT, null,	BETWEEN(90, 10000), null, _('Auto-logout (min 90 seconds)')),
	'autologout_visible' =>	array(T_ZBX_STR, O_OPT, P_SYS, null, null, 'isset({save})'),
	'url' =>				array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})'),
	'refresh' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, SEC_PER_HOUR), 'isset({save})', _('Refresh (in seconds)')),
	'rows_per_page' =>		array(T_ZBX_INT, O_OPT, null,	BETWEEN(1, 999999),'isset({save})', _('Rows per page')),
	// actions
	'go' =>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'register' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	IN('"add permission","delete permission"'), null),
	'save' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'delete' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'delete_selected' =>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'del_user_group' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'del_user_media' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'del_group_user' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'change_password' =>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'cancel' =>				array(T_ZBX_STR, O_OPT, P_SYS,			null,	null),
	// form
	'form' =>				array(T_ZBX_STR, O_OPT, P_SYS,			null,	null),
	'form_refresh' =>		array(T_ZBX_STR, O_OPT, null,			null,	null)
);
check_fields($fields);
validate_sort_and_sortorder('alias', ZBX_SORT_UP);

/*
 * Permissions
 */
if (isset($_REQUEST['userid'])) {
	$users = API::User()->get(array(
		'userids' => get_request('userid'),
		'output' => API_OUTPUT_EXTEND,
		'editable' => true
	));
	if (!$users) {
		access_deny();
	}
}
if (get_request('filter_usrgrpid') && !API::UserGroup()->isWritable(array($_REQUEST['filter_usrgrpid']))) {
	access_deny();
}

if (isset($_REQUEST['go'])) {
	if (!isset($_REQUEST['group_userid']) || !is_array($_REQUEST['group_userid'])) {
		access_deny();
	}
	else {
		$usersChk = API::User()->get(array(
			'output' => array('userid'),
			'userids' => $_REQUEST['group_userid'],
			'countOutput' => true,
			'editable' => true
		));
		if ($usersChk != count($_REQUEST['group_userid'])) {
			access_deny();
		}
	}
}

/*
 * Actions
 */
$_REQUEST['go'] = get_request('go', 'none');

if (isset($_REQUEST['new_groups'])) {
	$_REQUEST['new_groups'] = get_request('new_groups', array());
	$_REQUEST['user_groups'] = get_request('user_groups', array());
	$_REQUEST['user_groups'] += $_REQUEST['new_groups'];

	unset($_REQUEST['new_groups']);
}
elseif (isset($_REQUEST['new_media'])) {
	$_REQUEST['user_medias'] = get_request('user_medias', array());

	array_push($_REQUEST['user_medias'], $_REQUEST['new_media']);
}
elseif (isset($_REQUEST['user_medias']) && isset($_REQUEST['enable_media'])) {
	if (isset($_REQUEST['user_medias'][$_REQUEST['enable_media']])) {
		$_REQUEST['user_medias'][$_REQUEST['enable_media']]['active'] = 0;
	}
}
elseif (isset($_REQUEST['user_medias']) && isset($_REQUEST['disable_media'])) {
	if (isset($_REQUEST['user_medias'][$_REQUEST['disable_media']])) {
		$_REQUEST['user_medias'][$_REQUEST['disable_media']]['active'] = 1;
	}
}
elseif (isset($_REQUEST['save'])) {
	$config = select_config();

	$authType = isset($_REQUEST['userid']) ? get_user_system_auth($_REQUEST['userid']) : $config['authentication_type'];

	if (isset($_REQUEST['userid']) && ZBX_AUTH_INTERNAL != $authType) {
		$_REQUEST['password1'] = $_REQUEST['password2'] = null;
	}
	elseif (!isset($_REQUEST['userid']) && ZBX_AUTH_INTERNAL != $authType) {
		$_REQUEST['password1'] = $_REQUEST['password2'] = 'zabbix';
	}
	else {
		$_REQUEST['password1'] = get_request('password1', null);
		$_REQUEST['password2'] = get_request('password2', null);
	}

	if ($_REQUEST['password1'] != $_REQUEST['password2']) {
		if (isset($_REQUEST['userid'])) {
			show_error_message(_('Cannot update user. Both passwords must be equal.'));
		}
		else {
			show_error_message(_('Cannot add user. Both passwords must be equal.'));
		}
	}
	elseif (isset($_REQUEST['password1']) && $_REQUEST['alias'] == ZBX_GUEST_USER && !zbx_empty($_REQUEST['password1'])) {
		show_error_message(_('For guest, password must be empty'));
	}
	elseif (isset($_REQUEST['password1']) && $_REQUEST['alias'] != ZBX_GUEST_USER && zbx_empty($_REQUEST['password1'])) {
		show_error_message(_('Password should not be empty'));
	}
	else {
		$user = array();
		$user['alias'] = get_request('alias');
		$user['name'] = get_request('name');
		$user['surname'] = get_request('surname');
		$user['passwd'] = get_request('password1');
		$user['url'] = get_request('url');
		$user['autologin'] = get_request('autologin', 0);
		$user['autologout'] = hasRequest('autologout_visible') ? getRequest('autologout') : 0;
		$user['theme'] = get_request('theme');
		$user['refresh'] = get_request('refresh');
		$user['rows_per_page'] = get_request('rows_per_page');
		$user['type'] = get_request('user_type');
		$user['user_medias'] = get_request('user_medias', array());
		$user['usrgrps'] = zbx_toObject(get_request('user_groups', array()), 'usrgrpid');

		if (hasRequest('lang')) {
			$user['lang'] = getRequest('lang');
		}

		DBstart();

		if (isset($_REQUEST['userid'])) {
			$user['userid'] = $_REQUEST['userid'];
			$result = API::User()->update(array($user));

			if ($result) {
				$result = API::User()->updateMedia(array(
					'users' => $user,
					'medias' => $user['user_medias']
				));
			}

			$messageSuccess = _('User updated');
			$messageFailed = _('Cannot update user');
			$auditAction = AUDIT_ACTION_UPDATE;
		}
		else {
			$result = API::User()->create($user);

			$messageSuccess = _('User added');
			$messageFailed = _('Cannot add user');
			$auditAction = AUDIT_ACTION_ADD;
		}

		if ($result) {
			add_audit($auditAction, AUDIT_RESOURCE_USER,
				'User alias ['.$_REQUEST['alias'].'] name ['.$_REQUEST['name'].'] surname ['.$_REQUEST['surname'].']'
			);
			unset($_REQUEST['form']);
		}

		$result = DBend($result);
		show_messages($result, $messageSuccess, $messageFailed);
		clearCookies($result);
	}
}
elseif (isset($_REQUEST['del_user_media'])) {
	foreach (get_request('user_medias_to_del', array()) as $mediaId) {
		if (isset($_REQUEST['user_medias'][$mediaId])) {
			unset($_REQUEST['user_medias'][$mediaId]);
		}
	}
}
elseif (isset($_REQUEST['del_user_group'])) {
	foreach (get_request('user_groups_to_del', array()) as $groupId) {
		if (isset($_REQUEST['user_groups'][$groupId])) {
			unset($_REQUEST['user_groups'][$groupId]);
		}
	}
}
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['userid'])) {
	$user = reset($users);

	DBstart();

	$result = API::User()->delete(array($user['userid']));

	if ($result) {
		add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_USER, 'User alias ['.$user['alias'].'] name ['.$user['name'].'] surname ['.$user['surname'].']');
		unset($_REQUEST['userid'], $_REQUEST['form']);
	}

	$result = DBend($result);
	show_messages($result, _('User deleted'), _('Cannot delete user'));
	clearCookies($result);
}
elseif ($_REQUEST['go'] == 'unblock' && isset($_REQUEST['group_userid'])) {
	$groupUserId = get_request('group_userid', array());

	DBstart();

	$result = unblock_user_login($groupUserId);

	if ($result) {
		$users = API::User()->get(array(
			'userids' => $groupUserId,
			'output' => API_OUTPUT_EXTEND
		));

		foreach ($users as $user) {
			info('User '.$user['alias'].' unblocked');
			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_USER, 'Unblocked user alias ['.$user['alias'].'] name ['.$user['name'].'] surname ['.$user['surname'].']');
		}
	}

	$result = DBend($result);
	show_messages($result, _('Users unblocked'), _('Cannot unblock users'));
	clearCookies($result);
}
elseif ($_REQUEST['go'] == 'delete' && isset($_REQUEST['group_userid'])) {
	$goResult = false;

	$groupUserId = get_request('group_userid', array());

	$dbUsers = API::User()->get(array(
		'userids' => $groupUserId,
		'output' => API_OUTPUT_EXTEND
	));
	$dbUsers = zbx_toHash($dbUsers, 'userid');

	DBstart();

	foreach ($groupUserId as $userId) {
		if (!isset($dbUsers[$userId])) {
			continue;
		}

		$goResult |= (bool) API::User()->delete(array($userId));

		if ($goResult) {
			$userData = $dbUsers[$userId];

			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_USER, 'User alias ['.$userData['alias'].'] name ['.$userData['name'].'] surname ['.$userData['surname'].']');
		}
	}

	$goResult = DBend($goResult);

	show_messages($goResult, _('User deleted'), _('Cannot delete user'));
	clearCookies($goResult);
}

/*
 * Display
 */
$_REQUEST['filter_usrgrpid'] = get_request('filter_usrgrpid', CProfile::get('web.users.filter.usrgrpid', 0));
CProfile::update('web.users.filter.usrgrpid', $_REQUEST['filter_usrgrpid'], PROFILE_TYPE_ID);

if (!empty($_REQUEST['form'])) {
	$userId = get_request('userid');

	$data = getUserFormData($userId);

	$data['userid'] = $userId;
	$data['form'] = get_request('form');
	$data['form_refresh'] = get_request('form_refresh', 0);
	$data['autologout'] = getRequest('autologout');

	// render view
	$usersView = new CView('administration.users.edit', $data);
	$usersView->render();
	$usersView->show();
}
else {
	$data = array(
		'config' => $config
	);

	// get user groups
	$data['userGroups'] = API::UserGroup()->get(array(
		'output' => API_OUTPUT_EXTEND
	));
	order_result($data['userGroups'], 'name');

	// get users
	$data['users'] = API::User()->get(array(
		'usrgrpids' => ($_REQUEST['filter_usrgrpid'] > 0) ? $_REQUEST['filter_usrgrpid'] : null,
		'output' => API_OUTPUT_EXTEND,
		'selectUsrgrps' => API_OUTPUT_EXTEND,
		'getAccess' => 1,
		'limit' => $config['search_limit'] + 1
	));

	// sorting & apging
	order_result($data['users'], getPageSortField('alias'), getPageSortOrder());
	$data['paging'] = getPagingLine($data['users'], array('userid'));

	// set default lastaccess time to 0
	foreach ($data['users'] as $user) {
		$data['usersSessions'][$user['userid']] = array('lastaccess' => 0);
	}

	$dbSessions = DBselect(
		'SELECT s.userid,MAX(s.lastaccess) AS lastaccess,s.status'.
		' FROM sessions s'.
		' WHERE '.dbConditionInt('s.userid', zbx_objectValues($data['users'], 'userid')).
		' GROUP BY s.userid,s.status'
	);
	while ($session = DBfetch($dbSessions)) {
		if ($data['usersSessions'][$session['userid']]['lastaccess'] < $session['lastaccess']) {
			$data['usersSessions'][$session['userid']] = $session;
		}
	}

	// render view
	$usersView = new CView('administration.users.list', $data);
	$usersView->render();
	$usersView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
