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
?>
<?php
require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/media.inc.php';
require_once dirname(__FILE__).'/include/users.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';
require_once dirname(__FILE__).'/include/js.inc.php';

$page['title'] = _('Configuration of users');
$page['file'] = 'users.php';
$page['hist_arg'] = array();
$page['scripts'] = array();

require_once dirname(__FILE__).'/include/page_header.php';
?>
<?php
//	VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	// users
	'userid' =>				array(T_ZBX_INT, O_NO,	P_SYS,	DB_ID,		'isset({form})&&({form}=="update")'),
	'group_userid' =>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		null),
	'filter_usrgrpid' =>	array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		null),
	'alias' =>		array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	'isset({save})', _('Alias')),
	'name' =>		array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	'isset({save})', _('Name')),
	'surname' =>		array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	'isset({save})', _('Surname')),
	'password1' =>			array(T_ZBX_STR, O_OPT,	null,	null,		'isset({save})&&(isset({form})&&({form}!="update"))&&isset({change_password})'),
	'password2' =>			array(T_ZBX_STR, O_OPT,	null,	null,		'isset({save})&&(isset({form})&&({form}!="update"))&&isset({change_password})'),
	'user_type' =>			array(T_ZBX_INT, O_OPT,	null,	IN('1,2,3'),'isset({save})'),
	'user_groups' =>		array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	null),
	'user_groups_to_del' =>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
	'user_medias' =>		array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	null),
	'user_medias_to_del' =>	array(T_ZBX_STR, O_OPT,	null,	DB_ID,		null),
	'new_groups' =>			array(T_ZBX_STR, O_OPT,	null,	null,		null),
	'new_media' =>			array(T_ZBX_STR, O_OPT,	null,	null,		null),
	'enable_media' =>		array(T_ZBX_INT, O_OPT,	null,	null,		null),
	'disable_media' =>		array(T_ZBX_INT, O_OPT,	null,	null,		null),
	'lang' =>				array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	'isset({save})'),
	'theme' =>				array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	'isset({save})'),
	'autologin' =>			array(T_ZBX_INT, O_OPT,	null,	IN('1'),	null),
	'autologout' => array(T_ZBX_INT, O_OPT,	null,	BETWEEN(90, 10000), null, _('Auto-logout (min 90 seconds)')),
	'url' =>				array(T_ZBX_STR, O_OPT,	null,	null,		'isset({save})'),
	'refresh' => array(T_ZBX_INT, O_OPT, null, BETWEEN(0, SEC_PER_HOUR), 'isset({save})', _('Refresh (in seconds)')),
	'rows_per_page' => array(T_ZBX_INT, O_OPT, null, BETWEEN(1, 999999),'isset({save})', _('Rows per page')),
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

$_REQUEST['go'] = get_request('go', 'none');

/*
 * Permissions
 */
if (isset($_REQUEST['userid'])) {
	$users = API::User()->get(array('userids' => get_request('userid'), 'output' => API_OUTPUT_EXTEND));
	if (empty($users)) {
		access_deny();
	}
}

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
/*
 * Save
 */
elseif (isset($_REQUEST['save'])) {
	$config = select_config();
	$auth_type = isset($_REQUEST['userid']) ? get_user_system_auth($_REQUEST['userid']) : $config['authentication_type'];

	if (isset($_REQUEST['userid']) && ZBX_AUTH_INTERNAL != $auth_type) {
		$_REQUEST['password1'] = $_REQUEST['password2'] = null;
	}
	elseif (!isset($_REQUEST['userid']) && ZBX_AUTH_INTERNAL != $auth_type) {
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
		$user['autologout'] = get_request('autologout', 0);
		$user['lang'] = get_request('lang');
		$user['theme'] = get_request('theme');
		$user['refresh'] = get_request('refresh');
		$user['rows_per_page'] = get_request('rows_per_page');
		$user['type'] = get_request('user_type');
		$user['user_medias'] = get_request('user_medias', array());

		$usrgrps = get_request('user_groups', array());
		$usrgrps = zbx_toObject($usrgrps, 'usrgrpid');
		$user['usrgrps'] = $usrgrps;

		if (isset($_REQUEST['userid'])) {
			$action = AUDIT_ACTION_UPDATE;
			$user['userid'] = $_REQUEST['userid'];

			DBstart();
			$result = API::User()->update(array($user));
			if ($result) {
				$result = API::User()->updateMedia(array('users' => $user, 'medias' => $user['user_medias']));
			}
			$result = DBend($result);
			show_messages($result, _('User updated'), _('Cannot update user'));
		}
		else {
			$action = AUDIT_ACTION_ADD;

			DBstart();
			$result = DBend(API::User()->create($user));
			show_messages($result, _('User added'), _('Cannot add user'));
		}
		if ($result) {
			add_audit($action, AUDIT_RESOURCE_USER, 'User alias ['.$_REQUEST['alias'].'] name ['.$_REQUEST['name'].'] surname ['.$_REQUEST['surname'].']');
			unset($_REQUEST['form']);
		}
	}
}
/*
 * Delete user media
 */
elseif (isset($_REQUEST['del_user_media'])) {
	$user_medias_to_del = get_request('user_medias_to_del', array());
	foreach ($user_medias_to_del as $mediaid) {
		if (isset($_REQUEST['user_medias'][$mediaid])) {
			unset($_REQUEST['user_medias'][$mediaid]);
		}
	}
}
/*
 * Delete user group
 */
elseif (isset($_REQUEST['del_user_group'])) {
	$user_groups_to_del = get_request('user_groups_to_del', array());
	foreach ($user_groups_to_del as $groupid) {
		if (isset($_REQUEST['user_groups'][$groupid])) {
			unset($_REQUEST['user_groups'][$groupid]);
		}
	}
}
/*
 * Delete
 */
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['userid'])) {
	$user = reset($users);
	$result = API::User()->delete($users);
	show_messages($result, _('User deleted'), _('Cannot delete user'));
	if ($result) {
		add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_USER, 'User alias ['.$user['alias'].'] name ['.$user['name'].'] surname ['.$user['surname'].']');
		unset($_REQUEST['userid'], $_REQUEST['form']);
	}
}
/*
 * Add USER to GROUP
 */
elseif (isset($_REQUEST['grpaction']) && isset($_REQUEST['usrgrpid']) && isset($_REQUEST['userid']) && $_REQUEST['grpaction'] == 1) {
	$user = reset($users);

	$group = API::UserGroup()->get(array('usrgrpids' => $_REQUEST['usrgrpid'], 'output' => API_OUTPUT_EXTEND));
	$group = reset($group);

	DBstart();
	$result = add_user_to_group($_REQUEST['userid'], $_REQUEST['usrgrpid']);
	$result = DBend($result);

	show_messages($result, _('User updated'), _('Cannot update user'));
	if ($result) {
		add_audit(AUDIT_ACTION_ADD, AUDIT_RESOURCE_USER_GROUP, 'User alias ['.$user['alias'].'] name ['.$user['name'].'] surname ['.$user['surname'].']');
		unset($_REQUEST['usrgrpid'], $_REQUEST['userid']);
	}
	unset($_REQUEST['grpaction'], $_REQUEST['form']);
}
/*
 * Remove USER from GROUP
 */
elseif (isset($_REQUEST['grpaction']) && isset($_REQUEST['usrgrpid']) && isset($_REQUEST['userid']) && $_REQUEST['grpaction'] == 0) {
	$user = reset($users);

	$group = API::UserGroup()->get(array('usrgrpids' => $_REQUEST['usrgrpid'], 'output' => API_OUTPUT_EXTEND));
	$group = reset($group);

	DBstart();
	$result = remove_user_from_group($_REQUEST['userid'], $_REQUEST['usrgrpid']);
	$result = DBend($result);

	show_messages($result, _('User updated'), _('Cannot update user'));
	if ($result) {
		add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_USER_GROUP, 'User alias ['.$user['alias'].'] name ['.$user['name'].'] surname ['.$user['surname'].']');
		unset($_REQUEST['usrgrpid'], $_REQUEST['userid']);
	}
	unset($_REQUEST['grpaction'], $_REQUEST['form']);
}
/*
 * Go unblock
 */
elseif ($_REQUEST['go'] == 'unblock' && isset($_REQUEST['group_userid'])) {
	$group_userid = get_request('group_userid', array());

	DBstart();
	$go_result = unblock_user_login($group_userid);
	$go_result = DBend($go_result);
	if ($go_result) {
		$users = API::User()->get(array('userids' => $group_userid, 'output' => API_OUTPUT_EXTEND));
		foreach ($users as $unum => $user) {
			info('User '.$user['alias'].' unblocked');
			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_USER, 'Unblocked user alias ['.$user['alias'].'] name ['.$user['name'].'] surname ['.$user['surname'].']');
		}
	}
	show_messages($go_result, _('Users unblocked'), _('Cannot unblock users'));
}
/*
 * Go delete
 */
elseif ($_REQUEST['go'] == 'delete' && isset($_REQUEST['group_userid'])) {
	$go_result = false;

	$group_userid = get_request('group_userid', array());
	$db_users = API::User()->get(array('userids' => $group_userid, 'output' => API_OUTPUT_EXTEND));
	$db_users = zbx_toHash($db_users, 'userid');

	DBstart();
	foreach ($group_userid as $ugnum => $userid) {
		if (!isset($db_users[$userid])) {
			continue;
		}
		$user_data = $db_users[$userid];
		$go_result |= (bool) API::User()->delete($user_data);
		if ($go_result) {
			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_USER, 'User alias ['.$user_data['alias'].'] name ['.$user_data['name'].'] surname ['.$user_data['surname'].']');
		}
	}
	$go_result = DBend($go_result);
	show_messages($go_result, _('User deleted'), _('Cannot delete user'));
}

if ($_REQUEST['go'] != 'none' && isset($go_result) && $go_result) {
	$url = new CUrl();
	$path = $url->getPath();
	insert_js('cookie.eraseArray("'.$path.'")');
}
?>
<?php
$_REQUEST['filter_usrgrpid'] = get_request('filter_usrgrpid', CProfile::get('web.users.filter.usrgrpid', 0));
CProfile::update('web.users.filter.usrgrpid', $_REQUEST['filter_usrgrpid'], PROFILE_TYPE_ID);

if (!empty($_REQUEST['form'])) {
	$userid = get_request('userid');
	$data = getUserFormData($userid);
	$data['userid'] = $userid;
	$data['form'] = get_request('form');
	$data['form_refresh'] = get_request('form_refresh', 0);

	// render view
	$usersView = new CView('administration.users.edit', $data);
	$usersView->render();
	$usersView->show();
}
else {
	// get user groups
	$options = array(
		'output' => API_OUTPUT_EXTEND,
		'sortfield' => 'name'
	);
	$data['userGroups'] = API::UserGroup()->get($options);

	// get users
	$options = array(
		'output' => API_OUTPUT_EXTEND,
		'selectUsrgrps' => API_OUTPUT_EXTEND,
		'getAccess' => 1,
		'limit' => $config['search_limit'] + 1
	);
	if ($_REQUEST['filter_usrgrpid'] > 0) {
		$options['usrgrpids'] = $_REQUEST['filter_usrgrpid'];
	}
	$data['users'] = API::User()->get($options);

	// sort users
	order_result($data['users'], getPageSortField('alias'), getPageSortOrder());
	$data['paging'] = getPagingLine($data['users']);

	// set default lastaccess time to 0
	foreach ($data['users'] as $user) {
		$data['usersSessions'][$user['userid']] = array('lastaccess' => 0);
	}
	$sql = 'SELECT s.userid,MAX(s.lastaccess) AS lastaccess,s.status'.
			' FROM sessions s'.
			' WHERE '.DBcondition('s.userid', zbx_objectValues($data['users'], 'userid')).
			' GROUP BY s.userid,s.status';
	$db_sessions = DBselect($sql);
	while ($session = DBfetch($db_sessions)) {
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
?>
