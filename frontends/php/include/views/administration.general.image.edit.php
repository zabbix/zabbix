<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

$widget = (new CWidget())
	->setTitle(_('Images'))
	->setControls((new CForm())
		->cleanItems()
		->addItem((new CList())->addItem(makeAdministrationGeneralMenu('adm.images.php')))
	);

$imageForm = (new CForm('post', null, 'multipart/form-data'))
	->addVar('form', $this->data['form']);
if (isset($this->data['imageid'])) {
	$imageForm->addVar('imageid', $this->data['imageid']);
}
$imageForm->addVar('imagetype', $this->data['imagetype']);

// append form list
$imageFormList = (new CFormList('imageFormList'))
	->addRow(_('Name'),
		(new CTextBox('name', $this->data['imagename'], false, 64))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('autofocus', 'autofocus')
	)
	->addRow(_('Upload'), (new CFile('image'))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH));

if (isset($this->data['imageid'])) {
	if ($this->data['imagetype'] == IMAGE_TYPE_BACKGROUND) {
		$imageFormList->addRow(_('Image'), new CLink(new CImg('imgstore.php?width=200&height=200&iconid='.$this->data['imageid'], 'no image'), 'image.php?imageid='.$this->data['imageid']));
	}
	else {
		$imageFormList->addRow(_('Image'), new CImg('imgstore.php?iconid='.$this->data['imageid'], 'no image', null));
	}
}

// append tab
$imageTab = (new CTabView())
	->addTab('imageTab', ($this->data['imagetype'] == IMAGE_TYPE_ICON) ? _('Icon') : _('Background'), $imageFormList);

// append buttons
if (isset($this->data['imageid'])) {
	$imageTab->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			new CButtonDelete(_('Delete selected image?'), url_param('form').url_param('imageid')),
			new CButtonCancel()
		]
	));
}
else {
	$imageTab->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel()]
	));
}

$imageForm->addItem($imageTab);

$widget->addItem($imageForm);

return $widget;
