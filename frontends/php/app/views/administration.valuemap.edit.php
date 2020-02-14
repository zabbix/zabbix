<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

$this->includeJsFile('administration.valuemap.edit.js.php');

$widget = (new CWidget())
	->setTitle(_('Value mapping'))
	->setTitleSubmenu(getAdministrationGeneralSubmenu());

$form = (new CForm())
	->setId('valuemap')
	->setAction((new CUrl('zabbix.php'))
		->setArgument('action', ($data['valuemapid'] == 0) ? 'valuemap.create' : 'valuemap.update')
		->getUrl()
	)
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE);

if ($data['valuemapid'] != 0) {
	$form->addVar('valuemapid', $data['valuemapid']);
}

$form_list = (new CFormList())
	->addRow(
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		(new CTextBox('name', $data['name'], false, 64))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
	);

$table = (new CTable())
	->setId('mappings_table')
	->setHeader([_('Value'), '', _('Mapped to'), _('Action')])
	->setAttribute('style', 'width: 100%;');

foreach ($data['mappings'] as $i => $mapping) {
	$table->addRow([
		(new CTextBox('mappings['.$i.'][value]', $mapping['value'], false, 64))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
		'&rArr;',
		(new CTextBox('mappings['.$i.'][newvalue]', $mapping['newvalue'], false, 64))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired(),
		(new CButton('mappings['.$i.'][remove]', _('Remove')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-remove')
		],
		'form_row'
	);
}

$table->addRow([
	(new CCol(
		(new CButton('mapping_add', _('Add')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-add')
	))->setColSpan(4)
]);

$form_list->addRow(
	(new CLabel(_('Mappings'), $table->getId()))->setAsteriskMark(),
	(new CDiv($table))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
);

// append form list to tab
$tab_view = (new CTabView())->addTab('valuemap_tab', _('Value mapping'), $form_list);

// append buttons
$cancel_button = (new CRedirectButton(_('Cancel'), (new CUrl('zabbix.php'))
	->setArgument('action', 'valuemap.list')
	->setArgument('page', CPagerHelper::loadPage('valuemap.list', null))
))->setId('cancel');

if ($data['valuemapid'] != 0) {
	if ($data['valuemap_count'] == 0) {
		$confirm_message = _('Delete selected value mapping?');
	}
	else {
		$confirm_message = _n(
			'Delete selected value mapping? It is used for %d item!',
			'Delete selected value mapping? It is used for %d items!',
			$data['valuemap_count']
		);
	}

	$tab_view->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			(new CSimpleButton(_('Clone')))->setId('clone'),
			(new CRedirectButton(_('Delete'), (new CUrl('zabbix.php'))
					->setArgument('action', 'valuemap.delete')
					->setArgument('valuemapids', (array) $data['valuemapid']),
				$confirm_message
			))->setId('delete'),
			$cancel_button
		]
	));
}
else {
	$tab_view->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[
			$cancel_button
		]
	));
}

$form->addItem($tab_view);

$widget
	->addItem($form)
	->show();
