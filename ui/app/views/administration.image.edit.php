<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


/**
 * @var CView $this
 */

$this->includeJsFile('administration.image.edit.js.php');

$widget = (new CWidget())
	->setTitle(_('Images'))
	->setTitleSubmenu(getAdministrationGeneralSubmenu());

$form = (new CForm('post', (new CUrl('zabbix.php'))
		->setArgument('action', ($data['imageid'] == 0) ? 'image.create' : 'image.update')
		->getUrl(), 'multipart/form-data')
	)
		->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
		->addVar('imagetype', $data['imagetype']);

if ($data['imageid'] != 0) {
	$form->addVar('imageid', $data['imageid']);
}

$form_list = (new CFormList('imageFormList'))
	->addRow(
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		(new CTextBox('name', $data['name'], false, 64))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('autofocus', 'autofocus')
			->setAriaRequired()
	)
	->addRow(
		(new CLabel(_('Upload'), 'image'))->setAsteriskMark($data['imageid'] == 0),
		(new CFile('image'))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
	);

if ($data['imageid'] != 0) {
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

$tab_view = (new CTabView())
	->addTab('imageTab', ($data['imagetype'] == IMAGE_TYPE_ICON) ? _('Icon') : _('Background'), $form_list);

if ($data['imageid'] != 0) {
	$tab_view->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			(new CRedirectButton(_('Delete'), (new CUrl('zabbix.php'))
					->setArgument('action', 'image.delete')
					->setArgument('imageid', $data['imageid'])
					->setArgument('imagetype', $data['imagetype'])
					->setArgumentSID(),
				_('Delete selected image?')
			))->setId('delete'),
			(new CRedirectButton(_('Cancel'), (new CUrl('zabbix.php'))
				->setArgument('action', 'image.list')
				->setArgument('imagetype', $data['imagetype'])
			))->setId('cancel')
		]
	));
}
else {
	$tab_view->setFooter(makeFormFooter(
		new CSubmit(null, _('Add')),
		[
			(new CRedirectButton(_('Cancel'), (new CUrl('zabbix.php'))
				->setArgument('action', 'image.list')
				->setArgument('imagetype', $data['imagetype'])
			))->setId('cancel')
		]
	));
}

$widget->addItem($form->addItem($tab_view))->show();
