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
require_once dirname(__FILE__).'/include/media.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Configuration of media types');
$page['file'] = 'media_types.php';
$page['scripts'] = array('class.cviewswitcher.js');
$page['hist_arg'] = array();

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'mediatypeids' =>	array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, null),
	'mediatypeid' =>	array(T_ZBX_INT, O_NO,	P_SYS,	DB_ID, 'isset({form}) && {form} == "edit"'),
	'type' =>			array(T_ZBX_INT, O_OPT,	null,	IN(implode(',', array_keys(media_type2str()))), 'isset({add}) || isset({update})'),
	'description' =>	array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY, 'isset({add}) || isset({update})', _('Name')),
	// E-mail
	'smtp_server' =>	array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.MEDIA_TYPE_EMAIL,
		_('SMTP server')
	),
	'smtp_helo' =>		array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.MEDIA_TYPE_EMAIL,
		_('SMTP helo')
	),
	'smtp_email' =>		array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.MEDIA_TYPE_EMAIL,
		_('SMTP email')
	),
	// Remedy
	'remedy_proxy' =>	array(T_ZBX_STR, O_OPT,	null,	null,
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.MEDIA_TYPE_REMEDY,
		_('Proxy')
	),
	'remedy_url' =>		array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.MEDIA_TYPE_REMEDY,
		_('Remedy Service URL')
	),
	'remedy_mapping' =>	array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.MEDIA_TYPE_REMEDY,
		_('Services mapping')
	),
	'remedy_company' =>	array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.MEDIA_TYPE_REMEDY,
		_('Company name')
	),
	'username' =>		array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.MEDIA_TYPE_REMEDY,
		_('Username')
	),
	// Jabber
	'jabber_identifier' =>	array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.MEDIA_TYPE_JABBER,
		_('Jabber identifier')
	),
	'password' =>		array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type}) && ({type} == '.MEDIA_TYPE_JABBER.
			' || {type} == '.MEDIA_TYPE_EZ_TEXTING.
			' || {type} == '.MEDIA_TYPE_REMEDY.
		')',
		_('Password')
	),
	// EZ texting
	'msg_txt_limit' =>	array(T_ZBX_STR, O_OPT,	null,	IN(array(EZ_TEXTING_LIMIT_USA, EZ_TEXTING_LIMIT_CANADA)),
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.MEDIA_TYPE_EZ_TEXTING,
		_('Message text limit')
	),
	'ez_username' =>	array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.MEDIA_TYPE_EZ_TEXTING,
		_('Username')
	),
	// script
	'exec_path' =>		array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.MEDIA_TYPE_EXEC,
		_('Script name')
	),
	// GSM modem
	'gsm_modem' =>		array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.MEDIA_TYPE_SMS,
		_('GSM modem')
	),
	'status'=>			array(T_ZBX_INT, O_OPT,	null,	IN(array(MEDIA_TYPE_STATUS_ACTIVE, MEDIA_TYPE_STATUS_DISABLED)), null),
	// actions
	'action' =>			array(T_ZBX_STR, O_OPT,	P_SYS|P_ACT,
							IN('"mediatype.massdelete","mediatype.massdisable","mediatype.massenable"'),
							null
						),
	'add' =>			array(T_ZBX_STR, O_OPT,	P_SYS|P_ACT, null, null),
	'update' =>			array(T_ZBX_STR, O_OPT,	P_SYS|P_ACT, null, null),
	'delete' =>			array(T_ZBX_STR, O_OPT,	P_SYS|P_ACT, null, null),
	'cancel' =>			array(T_ZBX_STR, O_OPT,	P_SYS|P_ACT, null, null),
	'form' =>			array(T_ZBX_STR, O_OPT,	P_SYS,	null,	null),
	'form_refresh' =>	array(T_ZBX_INT, O_OPT,	null,	null,	null),
	// sort and sortorder
	'sort' =>					array(T_ZBX_STR, O_OPT, P_SYS, IN('"description","type"'),					null),
	'sortorder' =>				array(T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null)
);
check_fields($fields);

$mediaTypeId = getRequest('mediatypeid');

/*
 * Permissions
 */
if (hasRequest('mediatypeid')) {
	$mediaTypes = API::Mediatype()->get(array(
		'mediatypeids' => $mediaTypeId,
		'output' => API_OUTPUT_EXTEND
	));
	if (empty($mediaTypes)) {
		access_deny();
	}
}
if (hasRequest('action')) {
	if (!hasRequest('mediatypeids') || !is_array(getRequest('mediatypeids'))) {
		access_deny();
	}
	else {
		$mediaTypeChk = API::Mediatype()->get(array(
			'mediatypeids' => getRequest('mediatypeids'),
			'countOutput' => true
		));
		if ($mediaTypeChk != count(getRequest('mediatypeids'))) {
			access_deny();
		}
	}
}

/*
 * Actions
 */
if (hasRequest('add') || hasRequest('update')) {
	$mediaType = array(
		'type' => getRequest('type'),
		'description' => getRequest('description'),
		'gsm_modem' => getRequest('gsm_modem'),
		'status' => getRequest('status', MEDIA_TYPE_STATUS_DISABLED)
	);

	switch ($mediaType['type']) {
		case MEDIA_TYPE_REMEDY:
			$mediaType['smtp_server'] = getRequest('remedy_url');
			$mediaType['smtp_helo'] = getRequest('remedy_proxy');
			$mediaType['smtp_email'] = getRequest('remedy_mapping');
			$mediaType['exec_path'] = getRequest('remedy_company');
			$mediaType['username'] = getRequest('username');
			$mediaType['passwd'] = getRequest('password');
			break;

		case MEDIA_TYPE_JABBER:
			$mediaType['smtp_server'] = '';
			$mediaType['smtp_helo'] = '';
			$mediaType['smtp_email'] = '';
			$mediaType['exec_path'] = '';
			$mediaType['username'] = getRequest('jabber_identifier');
			$mediaType['passwd'] = getRequest('password');
			break;

		case MEDIA_TYPE_EZ_TEXTING:
			$mediaType['smtp_server'] = '';
			$mediaType['smtp_helo'] = '';
			$mediaType['smtp_email'] = '';
			$mediaType['exec_path'] = getRequest('msg_txt_limit');
			$mediaType['username'] = getRequest('ez_username');
			$mediaType['passwd'] = getRequest('password');
			break;

		default:
			$mediaType['smtp_server'] = getRequest('smtp_server');
			$mediaType['smtp_helo'] = getRequest('smtp_helo');
			$mediaType['smtp_email'] = getRequest('smtp_email');
			$mediaType['exec_path'] = getRequest('exec_path');
			$mediaType['username'] = getRequest('username');
			$mediaType['passwd'] = '';
	}

	DBstart();

	if ($mediaTypeId) {
		$mediaType['mediatypeid'] = $mediaTypeId;
		$result = API::Mediatype()->update($mediaType);

		$messageSuccess = _('Media type updated');
		$messageFailed = _('Cannot update media type');
		$auditAction = AUDIT_ACTION_UPDATE;

	}
	else {
		$result = API::Mediatype()->create($mediaType);

		$messageSuccess = _('Media type added');
		$messageFailed = _('Cannot add media type');
		$auditAction = AUDIT_ACTION_ADD;
	}

	if ($result) {
		add_audit($auditAction, AUDIT_RESOURCE_MEDIA_TYPE, 'Media type ['.$mediaType['description'].']');
		unset($_REQUEST['form']);
	}

	$result = DBend($result);

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, $messageSuccess, $messageFailed);
}
elseif (isset($_REQUEST['delete']) && !empty($mediaTypeId)) {
	$result = API::Mediatype()->delete(array(getRequest('mediatypeid')));

	if ($result) {
		unset($_REQUEST['form']);
		uncheckTableRows();
	}
	show_messages($result, _('Media type deleted'), _('Cannot delete media type'));
}
elseif (hasRequest('action') && str_in_array(getRequest('action'), array('mediatype.massenable', 'mediatype.massdisable')) && hasRequest('mediatypeids')) {
	$mediaTypeIds = getRequest('mediatypeids');
	$enable = (getRequest('action') == 'mediatype.massenable');
	$status = $enable ? MEDIA_TYPE_STATUS_ACTIVE : MEDIA_TYPE_STATUS_DISABLED;
	$update = array();

	foreach ($mediaTypeIds as $mediaTypeId) {
		$update[] = array(
			'mediatypeid' => $mediaTypeId,
			'status' => $status
		);
	}
	$result = API::Mediatype()->update($update);

	if ($result) {
		uncheckTableRows();
	}

	$updated = count($update);

	$messageSuccess = $enable
		? _n('Media type enabled', 'Media types enabled', $updated)
		: _n('Media type disabled', 'Media types disabled', $updated);
	$messageFailed = $enable
		? _n('Cannot enable media type', 'Cannot enable media types', $updated)
		: _n('Cannot disable media type', 'Cannot disable media types', $updated);

	show_messages($result, $messageSuccess, $messageFailed);
}
elseif (hasRequest('action') && getRequest('action') == 'mediatype.massdelete' && hasRequest('mediatypeids')) {
	$result = API::Mediatype()->delete(getRequest('mediatypeids'));

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, _('Media type deleted'), _('Cannot delete media type'));
}

/*
 * Display
 */
if (!empty($_REQUEST['form'])) {
	$data = array(
		'form' => getRequest('form'),
		'form_refresh' => getRequest('form_refresh', 0),
		'mediatypeid' => $mediaTypeId
	);

	if (isset($data['mediatypeid']) && empty($_REQUEST['form_refresh'])) {
		$mediaType = reset($mediaTypes);

		$data['type'] = $mediaType['type'];
		$data['description'] = $mediaType['description'];
		$data['smtp_server'] = ($data['type'] == MEDIA_TYPE_EMAIL) ? $mediaType['smtp_server'] : 'localhost';
		$data['smtp_helo'] = ($data['type'] == MEDIA_TYPE_EMAIL) ? $mediaType['smtp_helo'] : 'localhost';
		$data['smtp_email'] = ($data['type'] == MEDIA_TYPE_EMAIL) ? $mediaType['smtp_email'] : 'zabbix@localhost';
		$data['remedy_url'] = ($data['type'] == MEDIA_TYPE_REMEDY) ? $mediaType['smtp_server'] : 'localhost';
		$data['remedy_proxy'] = ($data['type'] == MEDIA_TYPE_REMEDY) ? $mediaType['smtp_helo'] : '';
		$data['remedy_company'] = ($data['type'] == MEDIA_TYPE_REMEDY) ? $mediaType['exec_path'] : '';
		$data['remedy_mapping'] = ($data['type'] == MEDIA_TYPE_REMEDY) ? $mediaType['smtp_email'] : '';
		$data['exec_path'] = ($data['type'] == MEDIA_TYPE_EZ_TEXTING) ? '' : $mediaType['exec_path'];
		$data['msg_txt_limit'] = $mediaType['exec_path'];
		$data['gsm_modem'] = $mediaType['gsm_modem'] ? $mediaType['gsm_modem'] : '/dev/ttyS0';
		$data['jabber_identifier'] = $mediaType['username'] ? $mediaType['username'] : 'user@server';
		$data['ez_username'] = $mediaType['username'] ? $mediaType['username'] : 'username';
		$data['username'] = $mediaType['username'];
		$data['password'] = $mediaType['passwd'];
		$data['status'] = $mediaType['status'];
	}
	else {
		$data['type'] = getRequest('type', MEDIA_TYPE_EMAIL);
		$data['description'] = getRequest('description', '');
		$data['smtp_server'] = getRequest('smtp_server', 'localhost');
		$data['smtp_helo'] = getRequest('smtp_helo', 'localhost');
		$data['smtp_email'] = getRequest('smtp_email', 'zabbix@localhost');
		$data['remedy_url'] = getRequest('remedy_url', 'localhost');
		$data['remedy_proxy'] = getRequest('remedy_proxy', '');
		$data['remedy_company'] = getRequest('remedy_company', '');
		$data['remedy_mapping'] = getRequest('remedy_mapping', '');
		$data['exec_path'] = getRequest('exec_path', '');
		$data['msg_txt_limit'] = getRequest('msg_txt_limit', '');
		$data['gsm_modem'] = getRequest('gsm_modem', '/dev/ttyS0');
		$data['jabber_identifier'] = getRequest('jabber_identifier', 'user@server');
		$data['ez_username'] = getRequest('ez_username', 'username');
		$data['username'] = getRequest('username', '');
		$data['password'] = getRequest('password', '');
		$data['status'] = getRequest('status', MEDIA_TYPE_STATUS_ACTIVE);
	}

	// render view
	$mediaTypeView = new CView('administration.mediatypes.edit', $data);
	$mediaTypeView->render();
	$mediaTypeView->show();
}
else {
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'description'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	$data = array(
		'sort' => $sortField,
		'sortorder' => $sortOrder
	);

	// get media types
	$data['mediatypes'] = API::Mediatype()->get(array(
		'output' => API_OUTPUT_EXTEND,
		'preservekeys' => true,
		'editable' => true,
		'limit' => $config['search_limit'] + 1
	));

	if ($data['mediatypes']) {
		// get media types used in actions
		$actions = API::Action()->get(array(
			'mediatypeids' => zbx_objectValues($data['mediatypes'], 'mediatypeid'),
			'output' => array('actionid', 'name'),
			'selectOperations' => array('operationtype', 'opmessage'),
			'preservekeys' => true
		));

		foreach ($data['mediatypes'] as $key => $mediaType) {
			$data['mediatypes'][$key]['typeid'] = $data['mediatypes'][$key]['type'];
			$data['mediatypes'][$key]['type'] = media_type2str($data['mediatypes'][$key]['type']);
			$data['mediatypes'][$key]['listOfActions'] = array();

			if ($actions) {
				foreach ($actions as $actionId => $action) {
					foreach ($action['operations'] as $operation) {
						if ($operation['operationtype'] == OPERATION_TYPE_MESSAGE
								&& $operation['opmessage']['mediatypeid'] == $mediaType['mediatypeid']) {

							$data['mediatypes'][$key]['listOfActions'][$actionId] = array(
								'actionid' => $actionId,
								'name' => $action['name']
							);
						}
					}
				}

				order_result($data['mediatypes'][$key]['listOfActions'], 'name');
			}
		}

		order_result($data['mediatypes'], $sortField, $sortOrder);

		$data['paging'] = getPagingLine($data['mediatypes']);
	}
	else {
		$arr = array();
		$data['paging'] = getPagingLine($arr);
	}

	// render view
	$mediaTypeView = new CView('administration.mediatypes.list', $data);
	$mediaTypeView->render();
	$mediaTypeView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
