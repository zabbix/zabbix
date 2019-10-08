<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
	->setTitle(_('image.edit :data-demo: '.$data['demo']))
	->setControls((new CTag('nav', true,
		(new CForm())
			->cleanItems()
			->addItem((new CList())
				->addItem(makeAdministrationGeneralMenu((new CUrl('zabbix.php'))
					->setArgument('action', 'image.edit')
					->getUrl()
				))
			)
		))
			->setAttribute('aria-label', _('Content controls'))
	);

$form = (new CForm('post', null, 'multipart/form-data'))
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('form', $data['form']);
if (isset($data['imageid'])) {
	$smage_form->addVar('imageid', $data['imageid']);
}
$form->addVar('imagetype', $data['imagetype']);

// append form list
$form_list = (new CFormList('image-form-list'))
	->addRow(
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		(new CTextBox('name', $data['imagename'], false, 64))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('autofocus', 'autofocus')
			->setAriaRequired()
	)
	->addRow(
		(new CLabel(_('Upload'), 'image'))->setAsteriskMark(),
		(new CFile('image'))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
	);

if (isset($data['imageid'])) {
	if ($data['imagetype'] == IMAGE_TYPE_BACKGROUND) {
		$form_list->addRow(_('Image'), new CLink(
			(new CImg('imgstore.php?iconid='.$data['imageid'], 'no image'))->addStyle('max-width:100%;'),
			'image.php?imageid='.$data['imageid']
		));
	}
	else {
		$form_list->addRow(_('Image'),
			(new CImg('imgstore.php?iconid='.$data['imageid'], 'no image', null))->addStyle('max-width:100%;')
		);
	}
}

// append tab
$imageTab = (new CTabView())
	->addTab('imageTab', ($data['imagetype'] == IMAGE_TYPE_ICON) ? _('Icon') : _('Background'), $form_list);

// append buttons
if (isset($data['imageid'])) {
	$imageTab->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			new CButtonDelete(_('Delete selected image?'), url_param('form').url_param('imageid').
				url_param($data['imagetype'], false, 'imagetype')
			),
			new CButtonCancel(url_param($data['imagetype'], false, 'imagetype'))
		]
	));
}
else {
	$imageTab->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel(url_param($data['imagetype'], false, 'imagetype'))]
	));
}

$form->addItem($imageTab);

$widget->addItem($form)->show();
