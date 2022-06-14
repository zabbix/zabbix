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

$this->includeJsFile('administration.iconmap.edit.js.php');

$widget = (new CWidget())
	->setTitle(_('Icon mapping'))
	->setTitleSubmenu(getAdministrationGeneralSubmenu());

$form_list = new CFormList();

$name = (new CTextBox('iconmap[name]', $data['iconmap']['name']))
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	->setAttribute('maxlength', 64)
	->setAriaRequired()
	->setAttribute('autofocus', 'autofocus');

$form_list->addRow((new CLabel(_('Name'), 'iconmap[name]'))->setAsteriskMark(), $name);

$form = (new CForm())
	->setId('iconmap')
	->setAction((new CUrl('zabbix.php'))
		->setArgument('action', ($data['iconmapid'] != 0) ? 'iconmap.update' : 'iconmap.create')
		->getUrl()
	)
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('form', 1);

if ($data['iconmapid'] != 0) {
	$form->addVar('iconmapid', $data['iconmapid']);
}

$table = (new CTable())
	->setAttribute('style', 'width: 100%;')
	->setId('iconMapTable')
	->setHeader(['', '', _('Inventory field'), _('Expression'), _('Icon'), '', _('Action')]);

$i = 0;
foreach ($data['iconmap']['mappings'] as $mapping) {
	$table->addRow(
		(new CRow([
			(new CCol(
				(new CDiv())->addClass(ZBX_STYLE_DRAG_ICON)
			))->addClass(ZBX_STYLE_TD_DRAG_ICON),
			(new CSpan(($i + 1).':'))->addClass('rowNum'),
			(new CSelect('iconmap[mappings]['.$i.'][inventory_link]'))
				->setValue($mapping['inventory_link'])
				->addOptions(CSelect::createOptionsFromArray($data['inventory_list'])),
			(new CTextBox('iconmap[mappings]['.$i.'][expression]', $mapping['expression']))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAriaRequired()
				->setAttribute('maxlength', 64),
			(new CSelect('iconmap[mappings]['.$i.'][iconid]'))
				->setValue($mapping['iconid'])
				->addOptions(CSelect::createOptionsFromArray($data['icon_list']))
				->addClass('js-mapping-icon'),
			(new CCol(
				(new CImg('imgstore.php?iconid='.$mapping['iconid'].'&width='.ZBX_ICON_PREVIEW_WIDTH.
					'&height='.ZBX_ICON_PREVIEW_HEIGHT, _('Preview'), null, null
				))
					->addClass('preview')
					->addClass(ZBX_STYLE_CURSOR_POINTER)
					->setAttribute('data-image-full', 'imgstore.php?iconid='.$mapping['iconid'])
			))->setAttribute('style', 'vertical-align: middle;'),
			(new CCol(
				(new CButton('remove', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('remove_mapping')
					->removeId()
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->addClass('sortable')
			->setId('iconmapidRow_'.$i)
	);

	$i++;
}

$table
	->addRow((new CRow([
		(new CCol(
			(new CButton('addMapping', _('Add')))->addClass(ZBX_STYLE_BTN_LINK)
		))->setColSpan(7)
	]))->setId('iconMapListFooter'))
	->addRow([
		(new CCol(_('Default')))->setColSpan(4),
		(new CSelect('iconmap[default_iconid]'))
			->setValue($data['iconmap']['default_iconid'])
			->addOptions(CSelect::createOptionsFromArray($data['icon_list']))
			->addClass('js-mapping-icon'),
		(new CCol(
			(new CImg('imgstore.php?iconid='.$data['iconmap']['default_iconid'].
				'&width='.ZBX_ICON_PREVIEW_WIDTH.'&height='.ZBX_ICON_PREVIEW_HEIGHT, _('Preview'), null, null
			))
				->addClass(ZBX_STYLE_CURSOR_POINTER)
				->addClass('preview')
				->setAttribute('data-image-full', 'imgstore.php?iconid='.$data['iconmap']['default_iconid'])
		))->setAttribute('style', 'vertical-align: middle;')
	]);

$form_list->addRow(
	(new CLabel(_('Mappings'), 'iconmap_list'))->setAsteriskMark(),
	(new CDiv($table))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		->setId('iconmap_list')
);

$tab = new CTabView();
$tab->addTab('iconmap_edit', _('Icon map'), $form_list);

if ($data['iconmapid'] != 0) {
	$tab->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			(new CSimpleButton(_('Clone')))->setId('clone'),
			(new CRedirectButton(_('Delete'), (new CUrl('zabbix.php'))
					->setArgument('action', 'iconmap.delete')
					->setArgument('iconmapid', $data['iconmapid'])
					->setArgumentSID(),
				_('Delete icon map?')
			))->setId('delete'),
			(new CRedirectButton(_('Cancel'), (new CUrl('zabbix.php'))
				->setArgument('action', 'iconmap.list')
			))->setId('cancel')
		]
	));
}
else {
	$tab->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[
			(new CRedirectButton(_('Cancel'), (new CUrl('zabbix.php'))
				->setArgument('action', 'iconmap.list')
			))->setId('cancel')
		]
	));
}

$form->addItem($tab);

$widget->addItem($form)->show();
