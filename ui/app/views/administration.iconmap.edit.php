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
 * @var array $data
 */

$this->includeJsFile('administration.iconmap.edit.js.php');

$html_page = (new CHtmlPage())
	->setTitle(_('Icon mapping'))
	->setTitleSubmenu(getAdministrationGeneralSubmenu())
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_ICONMAP_EDIT));

$csrf_token = CCsrfTokenHelper::get('iconmap');

$form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, $csrf_token))->removeId())
	->setId('iconmap')
	->setAction((new CUrl('zabbix.php'))->getUrl())
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID);

if ($data['iconmapid'] != 0) {
	$form->addVar('iconmapid', $data['iconmapid']);
}

$options_inventory = CSelect::createOptionsFromArray($data['inventory_list']);
$options_icon = CSelect::createOptionsFromArray($data['icon_list']);

$table = (new CTable())
	->setAttribute('data-field-name', 'mappings')
	->setAttribute('data-field-type', 'set')
	->setId('icon-mapping-table')
	->addClass(ZBX_STYLE_LIST_NUMBERED)
	->setHeader(['', '', _('Inventory field'), _('Expression'), _('Icon'), '', '']);

foreach ($data['iconmap']['mappings'] as $mapping) {
	$mapping += ['options_inventory' => $options_inventory, 'options_icon' => $options_icon];
	$table->addRow(getMappingEntryView(...$mapping));
	$table->addRow(getMappingEntryErrorView($mapping['sortorder']));
}

$table->addRow((new CRow((new CCol((new CButton('add', _('Add')))->addClass(ZBX_STYLE_BTN_LINK)))->setColSpan(7)))
	->setId('iconmap-list-footer')
);

$form_grid = (new CFormGrid())
	->addItem((new CLabel(_('Name'), 'name'))->setAsteriskMark())
	->addItem(new CFormField((new CTextBox('name', $data['iconmap']['name']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAttribute('maxlength', DB::getFieldLength('icon_map', 'name'))
		->setAriaRequired()
		->setAttribute('autofocus', 'autofocus')
	))
	->addItem((new CLabel(_('Mappings')))->setAsteriskMark())
	->addItem((new CFormField())
		->addItem((new CDiv($table))
			->addStyle('min-width:'.ZBX_TEXTAREA_BIG_WIDTH.'px')
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		)
		->addItem((new CTemplateTag('icon-mapping-template'))
			->addItem(getMappingEntryView(...[
				'sortorder' => '#{sortorder}',
				'expression' => '#{expression}',
				'iconid' => '#{iconid}',
				'inventory_link' => '#{inventory_link}',
				'options_inventory' => $options_inventory,
				'options_icon' => $options_icon
			]))
			->addItem(getMappingEntryErrorView('#{sortorder}'))
		)
	)
	->addItem((new CLabel(_('Default icon'), 'default-mapping-icon'))->setAsteriskMark())
	->addItem((new CFormField())
		->addItem((new CSelect('default_iconid'))
			->setId('default-mapping-icon')
			->addOptions($options_icon)
			->setValue($data['iconmap']['default_iconid'])
		)
		->addItem((new CImg('imgstore.php?iconid='.$data['iconmap']['default_iconid'].'&width='.ZBX_ICON_PREVIEW_WIDTH.
			'&height='.ZBX_ICON_PREVIEW_HEIGHT, _('Preview'))
		)
			->setId('default-mapping-icon-preview')
			->addStyle('vertical-align: middle;margin-left: 10px;')
			->setAttribute('data-image-full', 'imgstore.php?iconid='.$data['iconmap']['default_iconid'])
			->addClass(ZBX_STYLE_CURSOR_POINTER)
			->addClass('preview')
		)
	);

$form
	->addItem((new CTabView())
		->addTab('iconmap-edit', _('Icon map'), $form_grid)
		->setFooter($data['iconmapid'] != 0
			? makeFormFooter(new CSubmit('update', _('Update')), [
				(new CSimpleButton(_('Clone')))->setId('clone'),
				(new CSimpleButton(_('Delete')))
					->setAttribute('data-redirect-url', (new CUrl('zabbix.php'))
						->setArgument('action', 'iconmap.delete')
						->setArgument('iconmapid', $data['iconmapid'])
						->setArgument(CSRF_TOKEN_NAME, $csrf_token)
					)
					->setId('delete'),
				(new CRedirectButton(_('Cancel'), (new CUrl('zabbix.php'))
					->setArgument('action', 'iconmap.list')
				))->setId('cancel')
			])
			: makeFormFooter(new CSubmit('add', _('Add')), [
				(new CRedirectButton(_('Cancel'), (new CUrl('zabbix.php'))
					->setArgument('action', 'iconmap.list')
				))->setId('cancel')
			])
		)
	)
	->addItem(new CScriptTag('iconmap_edit.init('.json_encode([
		'rules' => $data['js_validation_rules'],
		'default_imageid' => $data['default_imageid']
	]).');'));

$html_page->addItem($form)->show();

function getMappingEntryView(string $sortorder, string $expression, string $iconid, string $inventory_link,
		array $options_inventory, array $options_icon): CRow {

	return (new CRow())
		->addItem((new CCol())
			->addItem((new CDiv())->addClass(ZBX_STYLE_DRAG_ICON)->addStyle('margin-top:4px;'))
			->addItem((new CInput('hidden', "mappings[$sortorder][sortorder]", $sortorder))
				->setAttribute('data-field-type', 'hidden')
				->removeId()
			)
		)
		->addItem((new CCol())
			->addItem((new CSpan(':'))->addClass(ZBX_STYLE_LIST_NUMBERED_ITEM))
		)
		->addItem((new CCol())
			->addItem((new CSelect("mappings[$sortorder][inventory_link]"))
				->setErrorContainer('mapping-'.$sortorder.'-error-container')
				->setErrorLabel(_('Inventory field'))
				->addOptions($options_inventory)
				->setValue($inventory_link)
			)
		)
		->addItem((new CCol())
			->addItem((new CTextBox("mappings[$sortorder][expression]", $expression, false, 64))
				->setErrorContainer('mapping-'.$sortorder.'-error-container')
				->setErrorLabel(_('Expression'))
				->setAriaRequired()
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			)
		)
		->addItem((new CCol())
			->addItem((new CSelect("mappings[$sortorder][iconid]"))
				->setErrorContainer('mapping-'.$sortorder.'-error-container')
				->setErrorLabel(_('Icon'))
				->addClass('js-mapping-icon')
				->addOptions($options_icon)
				->setValue($iconid)
			)
		)
		->addItem((new CCol())
			->addItem((new CImg('imgstore.php?iconid='.$iconid.'&width='.ZBX_ICON_PREVIEW_WIDTH.
				'&height='.ZBX_ICON_PREVIEW_HEIGHT, _('Preview'))
			)
				->setAttribute('data-image-full', 'imgstore.php?iconid='.$iconid)
				->addStyle('vertical-align: middle')
				->addClass(ZBX_STYLE_CURSOR_POINTER)
				->addClass('preview')
		))
		->addItem((new CCol())
			->addItem((new CButton('remove', _('Remove')))
				->addClass(ZBX_STYLE_NOWRAP)
				->addClass(ZBX_STYLE_BTN_LINK)
				->removeId()
			)
	);
}

function getMappingEntryErrorView(string $sortorder): CRow {
	return (new CRow())
		->addClass('error-container-row')
		->addItem((new CCol())->setId('mapping-'.$sortorder.'-error-container')->setColSpan(7));
}
