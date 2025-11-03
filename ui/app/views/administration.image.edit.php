<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * @var CView $this
 */

$data['form_name'] = 'image-form';

$this->includeJsFile('administration.image.edit.js.php');

$html_page = (new CHtmlPage())
	->setTitle(_('Images'))
	->setTitleSubmenu(getAdministrationGeneralSubmenu())
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_IMAGE_EDIT));

$form = (new CForm())
	->setId($data['form_name'])
	->setName($data['form_name'])
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('image')))->removeId())
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
	->addVar('imagetype', $data['imagetype'])
	->addVar('imageid', $data['imageid']);

$form_list = (new CFormList('imageFormList'))
	->addRow(
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		(new CTextBox('name', $data['name'], false, 64))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('autofocus', 'autofocus')
			->setAriaRequired()
	)
	->addRow(
		(new CLabel(_('Upload'), 'image'))->setAsteriskMark($data['imageid'] == null),
		(new CFile('image'))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('accept', 'image/*')
	);

if ($data['imageid'] != null) {
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

if ($data['imageid'] != null) {
	$tab_view->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			(new CSimpleButton(_('Delete')))
				->setId('delete'),
			(new CRedirectButton(_('Cancel'), (new CUrl('zabbix.php'))
				->setArgument('action', 'image.list')
				->setArgument('imagetype', $data['imagetype'])
			))->setId('cancel')
		]
	));
}
else {
	$tab_view->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[
			(new CRedirectButton(_('Cancel'), (new CUrl('zabbix.php'))
				->setArgument('action', 'image.list')
				->setArgument('imagetype', $data['imagetype'])
			))->setId('cancel')
		]
	));
}

$html_page->addItem($form->addItem($tab_view))->show();

(new CScriptTag('
	view.init('.json_encode([
		'rules' => $data['js_validation_rules']
	]).');
'))
	->show();
