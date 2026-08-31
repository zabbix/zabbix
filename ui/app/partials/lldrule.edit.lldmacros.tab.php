<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
 * @var CPartial $this
 * @var array    $data
 */


$readonly = $data['readonly'];

$formgrid = (new CFormGrid());

$formgrid
	->addItem([
		(new CLabel(_('LLD macros'))),
		(new CFormField(
			(new CDiv([
				(new CTable())
					->setId('lld_macro_paths')
					->setAttribute('data-field-type', 'set')
					->setAttribute('data-field-name', 'lld_macro_paths')
					->setHeader([_('LLD macro'), _('JSONPath'), ''])
					->addClass('js-lld-macro-paths')
					->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_CONTAINER)
					->setFooter(
						(new CCol(
							(new CButtonLink(_('Add')))
								->addClass('element-table-add')
								->setEnabled(!$readonly)
						))->setColSpan(3)
					),
				(new CTemplateTag('', [
					(new CRow([
						(new CCol(
							(new CTextAreaFlexible('lld_macro_paths[#{rowNum}][lld_macro]', '#{lld_macro}'))
								->setReadonly($readonly)
								->setMaxlength(DB::getFieldLength('lld_macro_path', 'lld_macro'))
								->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
								->setAttribute('placeholder', '{#MACRO}')
								->addClass('js-macro')
								->disableSpellcheck()
								->setErrorContainer('lld-macro-paths-#{rowNum}-error-container')
						))
							->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
						(new CCol(
							(new CTextAreaFlexible('lld_macro_paths[#{rowNum}][path]', '#{path}'))
								->setReadonly($readonly)
								->setAttribute('placeholder', _('$.path.to.node'))
								->setMaxlength(DB::getFieldLength('lld_macro_path', 'path'))
								->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
								->setAttribute('spellcheck', false)
								->setErrorContainer('lld-macro-paths-#{rowNum}-error-container')
						))
							->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
						(new CCol(
							(new CButtonLink(_('Remove')))
								->addClass('element-table-remove')
								->setEnabled(!$readonly)
						))->addClass(ZBX_STYLE_NOWRAP)
					]))->addClass('form_row'),
					(new CRow())
						->addClass('error-container-row')
						->addItem((new CCol())->setId('lld-macro-paths-#{rowNum}-error-container')->setColSpan(3))
				]))->addClass('js-lld-macro-paths-template')
			]))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH .'px;')
		))
	]);

$formgrid->show();
