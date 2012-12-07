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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Trigger comments');
$page['file'] = 'tr_comments.php';

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'triggerid' =>	array(T_ZBX_INT, O_MAND, P_SYS,			DB_ID,	null),
	'comments' =>	array(T_ZBX_STR, O_OPT, null,			null,	'isset({save})'),
	'save' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
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
	'nodeids' => get_current_nodeid(true),
	'triggerids' => $_REQUEST['triggerid'],
	'output' => API_OUTPUT_EXTEND,
	'expandDescription' => true,
	'expandComment' => true
));
$trigger = reset($trigger);
if (!$trigger) {
	access_deny();
}

$triggerEditable = API::Trigger()->get(array(
	'triggerids' => $_REQUEST['triggerid'],
	'output' => array('triggerid'),
	'editable' => true
));
$isTriggerEditable = !empty($triggerEditable);

/*
 * Actions
 */
if (isset($_REQUEST['save'])) {
	$result = DBexecute(
		'UPDATE triggers'.
		' SET comments='.zbx_dbstr($_REQUEST['comments']).
		' WHERE triggerid='.$_REQUEST['triggerid']
	);
	show_messages($result, _('Comment updated'), _('Cannot update comment'));

	$trigger['comments'] = $_REQUEST['comments'];

	if ($result) {
		add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_TRIGGER,
			_('Trigger').' ['.$_REQUEST['triggerid'].'] ['.$trigger['description'].'] '.
			_('Comments').' ['.$_REQUEST['comments'].']');
	}
}
elseif (isset($_REQUEST['cancel'])) {
	jsRedirect('tr_status.php');
	exit();
}

/*
 * Display
 */
show_table_header(_('TRIGGER COMMENTS'));

$commentTable = new CFormTable(_('Comments').' for "'.$trigger['description'].'"');
$commentTable->addVar('triggerid', $_REQUEST['triggerid']);
$commentTable->addRow(_('Comments'), new CTextArea('comments', $trigger['comments'], array(
	'rows' => 25, 'width' => ZBX_TEXTAREA_BIG_WIDTH, 'readonly' => !$isTriggerEditable
)));

if ($isTriggerEditable) {
	$commentTable->addItemToBottomRow(new CSubmit('save', _('Save')));
}
$commentTable->addItemToBottomRow(new CButtonCancel('&triggerid='.$_REQUEST['triggerid']));
$commentTable->show();

require_once dirname(__FILE__).'/include/page_footer.php';
