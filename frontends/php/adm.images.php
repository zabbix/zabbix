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
require_once dirname(__FILE__).'/include/images.inc.php';

$page['title'] = _('Configuration of images');
$page['file'] = 'adm.images.php';

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'imageid' =>	[T_ZBX_INT, O_NO,	P_SYS,		DB_ID,		'isset({form}) && {form} == "update"'],
	'name' =>		[T_ZBX_STR, O_NO,	null,		NOT_EMPTY,	'isset({add}) || isset({update})'],
	'imagetype' =>	[T_ZBX_INT, O_OPT, null,		IN('1,2'),	'isset({add}) || isset({update})'],
	// actions
	'add' =>		[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,		null],
	'update' =>		[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,		null],
	'delete' =>		[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,		null],
	'form' =>		[T_ZBX_STR, O_OPT, P_SYS,		null,		null]
];
check_fields($fields);

/*
 * Permissions
 */
if (hasRequest('imageid')) {
	$dbImage = DBfetch(DBselect('SELECT i.imagetype,i.name FROM images i WHERE i.imageid='.zbx_dbstr(getRequest('imageid'))));
	if (empty($dbImage)) {
		access_deny();
	}
}

/*
 * Actions
 */
if (hasRequest('add') || hasRequest('update')) {
	if (hasRequest('update')) {
		$msgOk = _('Image updated');
		$msgFail = _('Cannot update image');
	}
	else {
		$msgOk = _('Image added');
		$msgFail = _('Cannot add image');
	}

	try {
		DBstart();

		if (isset($_FILES['image'])) {
			$file = new CUploadFile($_FILES['image']);

			$image = null;
			if ($file->wasUploaded()) {
				$file->validateImageSize();
				$image = base64_encode($file->getContent());
			}
		}

		if (hasRequest('update')) {
			$result = API::Image()->update([
				'imageid' => getRequest('imageid'),
				'name' => getRequest('name'),
				'image' => $image
			]);

			$audit_action = 'Image ['.getRequest('name').'] updated';
		}
		else {
			$result = API::Image()->create([
				'name' => $_REQUEST['name'],
				'imagetype' => $_REQUEST['imagetype'],
				'image' => $image
			]);

			$audit_action = 'Image ['.$_REQUEST['name'].'] added';
		}

		if ($result) {
			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_IMAGE, $audit_action);
			unset($_REQUEST['form']);
		}

		$result = DBend($result);
		show_messages($result, $msgOk, $msgFail);
	}
	catch (Exception $e) {
		DBend(false);
		error($e->getMessage());
		show_error_message($msgFail);
	}
}
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['imageid'])) {
	DBstart();

	$image = get_image_by_imageid($_REQUEST['imageid']);
	$result = API::Image()->delete([getRequest('imageid')]);

	if ($result) {
		add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_IMAGE, 'Image ['.$image['name'].'] deleted');
		unset($_REQUEST['form'], $image, $_REQUEST['imageid']);
	}

	$result = DBend($result);
	show_messages($result, _('Image deleted'), _('Cannot delete image'));
}

/*
 * Display
 */
$data = [
	'form' => getRequest('form')
];

if (!empty($data['form'])) {
	if (isset($_REQUEST['imageid'])) {
		$data['imageid'] = $_REQUEST['imageid'];
		$data['imagename'] = $dbImage['name'];
		$data['imagetype'] = $dbImage['imagetype'];
	}
	else {
		$data['imageid'] = null;
		$data['imagename'] = getRequest('name', '');
		$data['imagetype'] = getRequest('imagetype', IMAGE_TYPE_ICON);
	}

	$view = new CView('administration.general.image.edit', $data);
}
else {
	$data['imagetype'] = getRequest('imagetype', IMAGE_TYPE_ICON);

	$data['images'] = API::Image()->get([
		'filter' => ['imagetype' => $data['imagetype']],
		'output' => ['imageid', 'imagetype', 'name']
	]);
	order_result($data['images'], 'name');

	$view = new CView('administration.general.image.list', $data);
}

$view->render();
$view->show();

require_once dirname(__FILE__).'/include/page_footer.php';
