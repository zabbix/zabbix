<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
$page['hist_arg'] = array();

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'imageid' =>	array(T_ZBX_INT, O_NO,	P_SYS,		DB_ID,		'isset({form})&&{form}=="update"'),
	'name' =>		array(T_ZBX_STR, O_NO,	null,		NOT_EMPTY,	'isset({save})'),
	'imagetype' =>	array(T_ZBX_INT, O_OPT, null,		IN('1,2'),	'isset({save})'),
	'save' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,		null),
	'delete' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,		null),
	'form' =>		array(T_ZBX_STR, O_OPT, P_SYS,		null,		null)
);
check_fields($fields);

/*
 * Permissions
 */
if (isset($_REQUEST['imageid'])) {
	$dbImage = DBfetch(DBselect('SELECT i.imagetype,i.name FROM images i WHERE i.imageid='.get_request('imageid')));
	if (empty($dbImage)) {
		access_deny();
	}
}

/*
 * Actions
 */
if (isset($_REQUEST['save'])) {
	if (isset($_REQUEST['imageid'])) {
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

		if (isset($_REQUEST['imageid'])) {
			$result = API::Image()->update(array(
				'imageid' => $_REQUEST['imageid'],
				'name' => $_REQUEST['name'],
				'imagetype' => $_REQUEST['imagetype'],
				'image' => $image
			));

			$audit_action = 'Image ['.$_REQUEST['name'].'] updated';
		}
		else {
			$result = API::Image()->create(array(
				'name' => $_REQUEST['name'],
				'imagetype' => $_REQUEST['imagetype'],
				'image' => $image
			));

			$audit_action = 'Image ['.$_REQUEST['name'].'] added';
		}

		if ($result) {
			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_IMAGE, $audit_action);
			unset($_REQUEST['form']);
		}

		DBend($result);
		show_messages($result, $msgOk, $msgFail);
	}
	catch (Exception $e) {
		DBend(false);
		error($e->getMessage());
		show_error_message($msgFail);
	}
}
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['imageid'])) {
	$image = get_image_by_imageid($_REQUEST['imageid']);
	$result = API::Image()->delete($_REQUEST['imageid']);

	if ($result) {
		add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_IMAGE, 'Image ['.$image['name'].'] deleted');
		unset($_REQUEST['form'], $image, $_REQUEST['imageid']);
	}

	show_messages($result, _('Image deleted'), _('Cannot delete image'));
}

/*
 * Display
 */
$form = new CForm();
$form->cleanItems();
$generalComboBox = new CComboBox('configDropDown', 'adm.images.php', 'redirect(this.options[this.selectedIndex].value);');
$generalComboBox->addItems(array(
	'adm.gui.php' => _('GUI'),
	'adm.housekeeper.php' => _('Housekeeper'),
	'adm.images.php' => _('Images'),
	'adm.iconmapping.php' => _('Icon mapping'),
	'adm.regexps.php' => _('Regular expressions'),
	'adm.macros.php' => _('Macros'),
	'adm.valuemapping.php' => _('Value mapping'),
	'adm.workingtime.php' => _('Working time'),
	'adm.triggerseverities.php' => _('Trigger severities'),
	'adm.triggerdisplayoptions.php' => _('Trigger displaying options'),
	'adm.other.php' => _('Other')
));
$form->addItem($generalComboBox);

if (!isset($_REQUEST['form'])) {
	$form->addItem(new CSubmit('form', _('Create image')));
}

$imageWidget = new CWidget();
$imageWidget->addPageHeader(_('CONFIGURATION OF IMAGES'), $form);

$data = array(
	'form' => get_request('form'),
	'displayNodes' => is_array(get_current_nodeid()),
	'widget' => &$imageWidget
);

if (!empty($data['form'])) {
	if (isset($_REQUEST['imageid'])) {
		$data['imageid'] = $_REQUEST['imageid'];
		$data['imagename'] = $dbImage['name'];
		$data['imagetype'] = $dbImage['imagetype'];
	}
	else {
		$data['imageid'] = null;
		$data['imagename'] = get_request('name', '');
		$data['imagetype'] = get_request('imagetype', 1);
	}

	$imageForm = new CView('administration.general.image.edit', $data);
}
else {
	$data['imagetype'] = get_request('imagetype', IMAGE_TYPE_ICON);

	$data['images'] = API::Image()->get(array(
		'filter' => array('imagetype' => $data['imagetype']),
		'output' => API_OUTPUT_EXTEND,
		'sortfield' => 'name'
	));

	// nodes
	if ($data['displayNodes']) {
		foreach ($data['images'] as &$image) {
			$image['nodename'] = get_node_name_by_elid($image['imageid'], true).NAME_DELIMITER;
		}
		unset($image);
	}

	$imageForm = new CView('administration.general.image.list', $data);
}

$imageWidget->addItem($imageForm->render());
$imageWidget->show();

require_once dirname(__FILE__).'/include/page_footer.php';
