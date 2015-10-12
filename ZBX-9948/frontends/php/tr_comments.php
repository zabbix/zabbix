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


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Trigger description');
$page['file'] = 'tr_comments.php';
$page['hist_arg'] = array('triggerid');

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'triggerid' =>	array(T_ZBX_INT, O_MAND, P_SYS,			DB_ID,	null),
	'comments' =>	array(T_ZBX_STR, O_OPT, null,			null,	'isset({update})'),
	// actions
	'update' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'cancel' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null)
);
check_fields($fields);

if (!isset($_REQUEST['triggerid'])) {
	fatal_error(_('No triggers defined.'));
}

/*
 * Permissions
 */
$trigger = API::Trigger()->get(array(
	'triggerids' => $_REQUEST['triggerid'],
	'output' => API_OUTPUT_EXTEND,
	'expandDescription' => true
));

if (!$trigger) {
	access_deny();
}

$trigger = reset($trigger);

/*
 * Actions
 */
if (hasRequest('update')) {
	DBstart();

	$result = DBexecute(
		'UPDATE triggers'.
		' SET comments='.zbx_dbstr(getRequest('comments')).
		' WHERE triggerid='.zbx_dbstr(getRequest('triggerid'))
	);

	$trigger['comments'] = $_REQUEST['comments'];

	if ($result) {
		add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_TRIGGER,
			_('Trigger').' ['.$_REQUEST['triggerid'].'] ['.$trigger['description'].'] '.
			_('Comments').' ['.$_REQUEST['comments'].']'
		);
	}

	$result = DBend($result);
	show_messages($result, _('Description updated'), _('Cannot update description'));
}
elseif (isset($_REQUEST['cancel'])) {
	jsRedirect('tr_status.php');
	exit;
}

/*
 * Display
 */
$triggerEditable = API::Trigger()->get(array(
	'triggerids' => $_REQUEST['triggerid'],
	'output' => array('triggerid'),
	'editable' => true
));

$data = array(
	'triggerid' => getRequest('triggerid'),
	'trigger' => $trigger,
	'isTriggerEditable' => !empty($triggerEditable),
	'isCommentExist' => !empty($trigger['comments'])
);

// render view
$triggerCommentView = new CView('monitoring.triggerComment', $data);
$triggerCommentView->render();
$triggerCommentView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
