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


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/media.inc.php';
require_once dirname(__FILE__).'/include/users.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';
require_once dirname(__FILE__).'/include/js.inc.php';

$page['title'] = _('Configuration of users');
$page['file'] = 'users.php';

require_once dirname(__FILE__).'/include/page_header.php';

$themes = array_keys(Z::getThemes());
$themes[] = THEME_DEFAULT;

//	VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = [
	// users
	'userid' =>				[T_ZBX_INT, O_NO,	P_SYS,	DB_ID,		'isset({form}) && {form} == "update"'],
	'group_userid' =>		[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'filter_usrgrpid' =>	[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'alias' =>				[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({add}) || isset({update})', _('Alias')],
	'name' =>				[T_ZBX_STR, O_OPT, null,	null,		null, _x('Name', 'user first name')],
	'surname' =>			[T_ZBX_STR, O_OPT, null,	null,		null, _('Surname')],
	'password1' =>			[T_ZBX_STR, O_OPT, null,	null,		'(isset({add}) || isset({update})) && isset({form}) && {form} != "update" && isset({change_password})'],
	'password2' =>			[T_ZBX_STR, O_OPT, null,	null,		'(isset({add}) || isset({update})) && isset({form}) && {form} != "update" && isset({change_password})'],
	'user_type' =>			[T_ZBX_INT, O_OPT, null,	IN('1,2,3'),'isset({add}) || isset({update})'],
	'user_groups' =>		[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	null],
	'user_groups_to_del' =>	[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'user_medias' =>		[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	null],
	'user_medias_to_del' =>	[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'new_groups' =>			[T_ZBX_STR, O_OPT, null,	null,		null],
	'new_media' =>			[T_ZBX_STR, O_OPT, null,	null,		null],
	'enable_media' =>		[T_ZBX_INT, O_OPT, null,	null,		null],
	'disable_media' =>		[T_ZBX_INT, O_OPT, null,	null,		null],
	'lang' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
	'theme' =>				[T_ZBX_STR, O_OPT, null,	IN('"'.implode('","', $themes).'"'), 'isset({add}) || isset({update})'],
	'autologin' =>			[T_ZBX_INT, O_OPT, null,	IN('1'),	null],
	'autologout' => 		[T_ZBX_INT, O_OPT, null,	BETWEEN(90, 10000), null, _('Auto-logout (min 90 seconds)')],
	'autologout_visible' =>	[T_ZBX_STR, O_OPT, null,	IN('1'),	null],
	'url' =>				[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'refresh' =>			[T_ZBX_INT, O_OPT, null,	BETWEEN(0, SEC_PER_HOUR), 'isset({add}) || isset({update})', _('Refresh (in seconds)')],
	'rows_per_page' =>		[T_ZBX_INT, O_OPT, null,	BETWEEN(1, 999999),'isset({add}) || isset({update})', _('Rows per page')],
	// actions
	'action' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT,	IN('"user.massdelete","user.massunblock"'),	null],
	'register' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT,	IN('"add permission","delete permission"'), null],
	'add' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'update' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'delete' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'delete_selected' =>	[T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'del_user_group' =>		[T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'del_user_media' =>		[T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'del_group_user' =>		[T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'change_password' =>	[T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'cancel' =>				[T_ZBX_STR, O_OPT, P_SYS,			null,	null],
	// form
	'form' =>				[T_ZBX_STR, O_OPT, P_SYS,			null,	null],
	'form_refresh' =>		[T_ZBX_INT, O_OPT, null,			null,	null],
	// filter
	'filter_set' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_rst' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_alias' =>		[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_name' =>		[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_surname' =>		[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_type' =>		[T_ZBX_STR, O_OPT, null,	IN([-1, USER_TYPE_ZABBIX_USER, USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN]),	null],
	// sort and sortorder
	'sort' =>				[T_ZBX_STR, O_OPT, P_SYS, IN('"alias","name","surname","type"'),		null],
	'sortorder' =>			[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];
check_fields($fields);

/*
 * Permissions
 */
if (isset($_REQUEST['userid'])) {
	$users = API::User()->get([
		'userids' => getRequest('userid'),
		'output' => API_OUTPUT_EXTEND,
		'editable' => true
	]);
	if (!$users) {
		access_deny();
	}
}
if (getRequest('filter_usrgrpid') && !API::UserGroup()->isWritable([$_REQUEST['filter_usrgrpid']])) {
	access_deny();
}

if (hasRequest('action')) {
	if (!hasRequest('group_userid') || !is_array(getRequest('group_userid'))) {
		access_deny();
	}
	else {
		$usersChk = API::User()->get([
			'output' => ['userid'],
			'userids' => getRequest('group_userid'),
			'countOutput' => true,
			'editable' => true
		]);
		if ($usersChk != count(getRequest('group_userid'))) {
			access_deny();
		}
	}
}

/*
 * Actions
 */
$config = select_config();

if (isset($_REQUEST['new_groups'])) {
	$_REQUEST['new_groups'] = getRequest('new_groups', []);
	$_REQUEST['user_groups'] = getRequest('user_groups', []);
	$_REQUEST['user_groups'] += $_REQUEST['new_groups'];

	unset($_REQUEST['new_groups']);
}
elseif (isset($_REQUEST['new_media'])) {
	$_REQUEST['user_medias'] = getRequest('user_medias', []);

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
elseif (hasRequest('add') || hasRequest('update')) {
	$isValid = true;

	$usrgrps = getRequest('user_groups', []);

	// authentication type
	if ($usrgrps) {
		$authType = getGroupAuthenticationType($usrgrps, GROUP_GUI_ACCESS_INTERNAL);
	}
	else {
		$authType = hasRequest('userid')
			? getUserAuthenticationType(getRequest('userid'), GROUP_GUI_ACCESS_INTERNAL)
			: $config['authentication_type'];
	}

	// password validation
	if ($authType != ZBX_AUTH_INTERNAL) {
		if (hasRequest('password1')) {
			show_error_message(_s('Password is unavailable for users with %1$s.', authentication2str($authType)));

			$isValid = false;
		}
		else {
			if (hasRequest('userid')) {
				$_REQUEST['password1'] = null;
				$_REQUEST['password2'] = null;
			}
			else {
				$_REQUEST['password1'] = 'zabbix';
				$_REQUEST['password2'] = 'zabbix';
			}
		}
	}
	else {
		$_REQUEST['password1'] = getRequest('password1');
		$_REQUEST['password2'] = getRequest('password2');
	}

	if ($_REQUEST['password1'] != $_REQUEST['password2']) {
		if (isset($_REQUEST['userid'])) {
			show_error_message(_('Cannot update user. Both passwords must be equal.'));
		}
		else {
			show_error_message(_('Cannot add user. Both passwords must be equal.'));
		}

		$isValid = false;
	}
	elseif (isset($_REQUEST['password1']) && $_REQUEST['alias'] == ZBX_GUEST_USER && !zbx_empty($_REQUEST['password1'])) {
		show_error_message(_('For guest, password must be empty'));

		$isValid = false;
	}
	elseif (isset($_REQUEST['password1']) && $_REQUEST['alias'] != ZBX_GUEST_USER && zbx_empty($_REQUEST['password1'])) {
		show_error_message(_('Password should not be empty'));

		$isValid = false;
	}

	if ($isValid) {
		$user = [];
		$user['alias'] = getRequest('alias');
		$user['name'] = getRequest('name');
		$user['surname'] = getRequest('surname');
		$user['passwd'] = getRequest('password1');
		$user['url'] = getRequest('url');
		$user['autologin'] = getRequest('autologin', 0);
		$user['autologout'] = hasRequest('autologout_visible') ? getRequest('autologout') : 0;
		$user['theme'] = getRequest('theme');
		$user['refresh'] = getRequest('refresh');
		$user['rows_per_page'] = getRequest('rows_per_page');
		$user['type'] = getRequest('user_type');
		$user['user_medias'] = getRequest('user_medias', []);
		$user['usrgrps'] = zbx_toObject($usrgrps, 'usrgrpid');

		if (hasRequest('lang')) {
			$user['lang'] = getRequest('lang');
		}

		DBstart();

		if (hasRequest('userid')) {
			$user['userid'] = getRequest('userid');
			$result = API::User()->update([$user]);

			if ($result) {
				$result = API::User()->updateMedia([
					'users' => $user,
					'medias' => $user['user_medias']
				]);
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

		if ($result) {
			uncheckTableRows();
		}
		show_messages($result, $messageSuccess, $messageFailed);
	}
}
elseif (isset($_REQUEST['del_user_media'])) {
	foreach (getRequest('user_medias_to_del', []) as $mediaId) {
		if (isset($_REQUEST['user_medias'][$mediaId])) {
			unset($_REQUEST['user_medias'][$mediaId]);
		}
	}
}
elseif (isset($_REQUEST['del_user_group'])) {
	foreach (getRequest('user_groups_to_del', []) as $groupId) {
		if (isset($_REQUEST['user_groups'][$groupId])) {
			unset($_REQUEST['user_groups'][$groupId]);
		}
	}
}
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['userid'])) {
	$user = reset($users);

	$result = API::User()->delete([$user['userid']]);
	unset($_REQUEST['userid'], $_REQUEST['form']);

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, _('User deleted'), _('Cannot delete user'));
}
elseif (hasRequest('action') && getRequest('action') == 'user.massunblock' && hasRequest('group_userid')) {
	$groupUserId = getRequest('group_userid');

	DBstart();

	$result = unblock_user_login($groupUserId);

	if ($result) {
		$users = API::User()->get([
			'userids' => $groupUserId,
			'output' => API_OUTPUT_EXTEND
		]);

		foreach ($users as $user) {
			info('User '.$user['alias'].' unblocked');
			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_USER, 'Unblocked user alias ['.$user['alias'].'] name ['.$user['name'].'] surname ['.$user['surname'].']');
		}
	}

	$result = DBend($result);

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, _('Users unblocked'), _('Cannot unblock users'));
}
elseif (hasRequest('action') && getRequest('action') == 'user.massdelete' && hasRequest('group_userid')) {
	$result = API::User()->delete(getRequest('group_userid'));

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, _('User deleted'), _('Cannot delete user'));
}

/*
 * Display
 */
$_REQUEST['filter_usrgrpid'] = getRequest('filter_usrgrpid', CProfile::get('web.users.filter.usrgrpid', 0));
CProfile::update('web.users.filter.usrgrpid', $_REQUEST['filter_usrgrpid'], PROFILE_TYPE_ID);

if (!empty($_REQUEST['form'])) {
	$userId = getRequest('userid', 0);

	$data = getUserFormData($userId, $config);

	$data['userid'] = $userId;
	$data['form'] = getRequest('form');
	$data['form_refresh'] = getRequest('form_refresh', 0);
	$data['autologout'] = getRequest('autologout');

	// render view
	$usersView = new CView('administration.users.edit', $data);
	$usersView->render();
	$usersView->show();
}
else {
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'alias'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	// filter
	if (hasRequest('filter_set')) {
		CProfile::update('web.user.filter_alias', getRequest('filter_alias', ''), PROFILE_TYPE_STR);
		CProfile::update('web.user.filter_name', getRequest('filter_name', ''), PROFILE_TYPE_STR);
		CProfile::update('web.user.filter_surname', getRequest('filter_surname', ''), PROFILE_TYPE_STR);
		CProfile::update('web.user.filter_type', getRequest('filter_type', -1), PROFILE_TYPE_INT);
	}
	elseif (hasRequest('filter_rst')) {
		CProfile::delete('web.user.filter_alias');
		CProfile::delete('web.user.filter_name');
		CProfile::delete('web.user.filter_surname');
		CProfile::delete('web.user.filter_type');
	}

	$filter = [
		'alias' => CProfile::get('web.user.filter_alias', ''),
		'name' => CProfile::get('web.user.filter_name', ''),
		'surname' => CProfile::get('web.user.filter_surname', ''),
		'type' => CProfile::get('web.user.filter_type', -1)
	];

	$data = [
		'config' => $config,
		'sort' => $sortField,
		'sortorder' => $sortOrder,
		'filter' => $filter
	];

	// get user groups
	$data['userGroups'] = API::UserGroup()->get([
		'output' => API_OUTPUT_EXTEND
	]);
	order_result($data['userGroups'], 'name');

	// get users
	$data['users'] = API::User()->get([
		'output' => API_OUTPUT_EXTEND,
		'selectUsrgrps' => API_OUTPUT_EXTEND,
		'search' => [
			'alias' => ($filter['alias'] === '') ? null : $filter['alias'],
			'name' => ($filter['name'] === '') ? null : $filter['name'],
			'surname' => ($filter['surname'] === '') ? null : $filter['surname']
		],
		'filter' => [
			'type' => ($filter['type'] == -1) ? null : $filter['type']
		],
		'usrgrpids' => ($_REQUEST['filter_usrgrpid'] > 0) ? $_REQUEST['filter_usrgrpid'] : null,
		'getAccess' => 1,
		'limit' => $config['search_limit'] + 1
	]);

	// sorting & paging
	order_result($data['users'], $sortField, $sortOrder);
	$data['paging'] = getPagingLine($data['users'], $sortOrder, new CUrl('users.php'));

	// set default lastaccess time to 0
	foreach ($data['users'] as $user) {
		$data['usersSessions'][$user['userid']] = ['lastaccess' => 0];
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
