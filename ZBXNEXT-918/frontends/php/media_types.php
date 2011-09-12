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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
require_once('include/config.inc.php');
require_once('include/media.inc.php');
require_once('include/forms.inc.php');

$page['title'] = 'S_MEDIA_TYPES';
$page['file'] = 'media_types.php';
$page['hist_arg'] = array();

include_once('include/page_header.php');

?>
<?php
	$fields = array(
		// VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
		// media form
		'mediatypeids' =>	array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'mediatypeid' =>	array(T_ZBX_INT, O_NO,	P_SYS,	DB_ID, '(isset({form})&&({form}=="edit"))'),
		'type' =>			array(T_ZBX_INT, O_OPT, NULL,	IN(implode(',', array_keys(media_type2str()))), '(isset({save}))'),
		'description' =>	array(T_ZBX_STR, O_OPT, NULL,	NOT_EMPTY, '(isset({save}))'),
		'smtp_server' =>	array(T_ZBX_STR, O_OPT, NULL,	NOT_EMPTY, 'isset({type})&&({type}=='.MEDIA_TYPE_EMAIL.')&&isset({save})'),
		'smtp_helo' =>		array(T_ZBX_STR, O_OPT, NULL,	NOT_EMPTY, 'isset({type})&&({type}=='.MEDIA_TYPE_EMAIL.')&&isset({save})'),
		'smtp_email' =>		array(T_ZBX_STR, O_OPT, NULL,	NOT_EMPTY, 'isset({type})&&({type}=='.MEDIA_TYPE_EMAIL.')&&isset({save})'),
		'exec_path' =>		array(T_ZBX_STR, O_OPT, NULL,	NOT_EMPTY, 'isset({type})&&({type}=='.MEDIA_TYPE_EXEC.')&&isset({save})'),
		'gsm_modem' =>		array(T_ZBX_STR, O_OPT, NULL,	NOT_EMPTY, 'isset({type})&&({type}=='.MEDIA_TYPE_SMS.')&&isset({save})'),
		'username' =>		array(T_ZBX_STR, O_OPT, NULL,	NOT_EMPTY, 'isset({type})&&({type}=='.MEDIA_TYPE_JABBER.'||{type}=='.MEDIA_TYPE_EZ_TEXTING.')&&isset({save})'),
		'password' =>		array(T_ZBX_STR, O_OPT, NULL,	NULL, NULL),
		// actions
		'save' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, NULL, NULL),
		'delete' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, NULL, NULL),
		'cancel' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, NULL, NULL),
		// form
		'form' =>			array(T_ZBX_STR, O_OPT, P_SYS, NULL, NULL),
		'form_refresh' =>	array(T_ZBX_INT, O_OPT, NULL, NULL, NULL)
	);

	check_fields($fields);
	validate_sort_and_sortorder('description',ZBX_SORT_UP);
?>
<?php
	$mediatypeid = get_request('mediatypeid');

	/*
	 * Save
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
			'passwd' => get_request('password')
		);

		if (is_null($mediatype['passwd'])) {
			unset($mediatype['passwd']);
		}

		if (!empty($mediatypeid)) {
			$action = AUDIT_ACTION_UPDATE;
			$mediatype['mediatypeid'] = $mediatypeid;
			$result = API::Mediatype()->update($mediatype);
			show_messages($result, _('Media type updated'), _('Media type was not updated'));
		}
		else {
			$action = AUDIT_ACTION_ADD;
			$result = API::Mediatype()->create($mediatype);
			show_messages($result, _('Added new media type'), _('New media type was not added'));
		}

		if ($result) {
			add_audit($action, AUDIT_RESOURCE_MEDIA_TYPE, 'Media type ['.$mediatype['description'].']');
			unset($_REQUEST['form']);
		}
	}
	/*
	 * Delete
	 */
	elseif (!empty($_REQUEST['delete'])) {
		$deleteids = !empty($_REQUEST['mediatypeids']) ? $_REQUEST['mediatypeids'] : $mediatypeid;
		if (!empty($deleteids)) {
			$result = API::Mediatype()->delete($deleteids);
			if ($result) {
				unset($_REQUEST['form']);
			}
			show_messages($result, _('Media type deleted'), _('Media type was not deleted'));
		}
	}

	/*
	 * Display
	 */
	$data['form'] = get_request('form');
	if (!empty($data['form'])) {
		$data['mediatypeid'] = $mediatypeid;
		$data['form_refresh'] = get_request('form_refresh', 0);

		if (!empty($data['mediatypeid']) && empty($_REQUEST['form_refresh'])) {
			$options = array(
				'mediatypeids' => $data['mediatypeid'],
				'output' => API_OUTPUT_EXTEND
			);
			$mediatypes = API::Mediatype()->get($options);
			$mediatype = reset($mediatypes);

			$data['type'] = $mediatype['type'];
			$data['description'] = $mediatype['description'];
			$data['smtp_server'] = $mediatype['smtp_server'];
			$data['smtp_helo'] = $mediatype['smtp_helo'];
			$data['smtp_email'] = $mediatype['smtp_email'];
			$data['exec_path'] = $mediatype['exec_path'];
			$data['gsm_modem'] = $mediatype['gsm_modem'];
			$data['username'] = $mediatype['username'];
			$data['password'] = $mediatype['passwd'];
		}
		else {
			$data['type'] = get_request('type', 0);
			$data['description'] = get_request('description', '');
			$data['smtp_server'] = get_request('smtp_server', 'localhost');
			$data['smtp_helo'] = get_request('smtp_helo', 'localhost');
			$data['smtp_email'] = get_request('smtp_email', 'zabbix@localhost');
			$data['exec_path'] = get_request('exec_path', '');
			$data['gsm_modem'] = get_request('gsm_modem', '/dev/ttyS0');
			$data['username'] = get_request('username', ($data['type'] == MEDIA_TYPE_EZ_TEXTING) ? 'username' : 'user@server');
		}

		// render view
		$mediaTypeView = new CView('configuration.mediatypes.edit', $data);
		$mediaTypeView->render();
		$mediaTypeView->show();
	}
	else {
		$sortfield = getPageSortField('description');
		$sortorder = getPageSortOrder();

		// get media types
		$options = array(
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => 1,
			'editable' => true,
			'sortfield' => $sortfield,
			'sortorder' => $sortorder,
			'limit' => ($config['search_limit'] + 1)
		);
		$data['mediatypes'] = API::Mediatype()->get($options);

		// check if media types are used by existing actions
		$options = array(
			'mediatypeids' => zbx_objectValues($data['mediatypes'], 'mediatypeid'),
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => 1
		);
		$actions = API::Action()->get($options);
		foreach ($actions as $actionid => $action) {
			if (empty($data['mediatypes'][$action['mediatypeid']]['listOfActions'])) {
				$data['mediatypes'][$action['mediatypeid']]['listOfActions'] = array();
			}
			$data['mediatypes'][$action['mediatypeid']]['listOfActions'][] = array('actionid' => $actionid, 'name' => $action['name']);
		}
		foreach ($data['mediatypes'] as $number => $mediatype) {
			$data['mediatypes'][$number]['usedInActions'] = !isset($mediatype['listOfActions']);
		}
		order_result($data['mediatypes'], $sortfield, $sortorder);
		$data['paging'] = getPagingLine($data['mediatypes']);

		// render view
		$mediaTypeView = new CView('configuration.mediatypes.list', $data);
		$mediaTypeView->render();
		$mediaTypeView->show();
	}

include_once('include/page_footer.php');
?>
