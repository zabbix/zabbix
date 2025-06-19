<?php declare(strict_types = 0);
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

(new CTemplateTag('valuemap-rename-row-tmpl'))
	->addItem(
		(new CRow([
			(new CTextBox('valuemap_rename[#{rowNum}][from]', '', false, DB::getFieldLength('valuemap', 'name')))
				->addStyle('width: 100%;'),
			(new CTextBox('valuemap_rename[#{rowNum}][to]', '', false, DB::getFieldLength('valuemap', 'name')))
				->addStyle('width: 100%;'),
			(new CCol(
				(new CButtonLink(_('Remove')))->addClass('element-table-remove'))
			)->addClass(ZBX_STYLE_TOP)
		]))->addClass('form_row')
	)
	->show();

(new CTemplateTag('macro-row-tmpl'))
	->addItem(
		(new CRow([
			(new CCol(
				(new CTextAreaFlexible('macros[#{rowNum}][macro]', '', ['add_post_js' => false]))
					->addClass('macro')
					->setAdaptiveWidth(ZBX_TEXTAREA_MACRO_WIDTH)
					->setAttribute('placeholder', '{$MACRO}')
					->disableSpellcheck()
			))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
			(new CCol(
				new CMacroValue(ZBX_MACRO_TYPE_TEXT, 'macros[#{rowNum}]', '', false)
			))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
			(new CCol(
				(new CTextAreaFlexible('macros[#{rowNum}][description]', '', ['add_post_js' => false]))
					->setMaxlength(DB::getFieldLength('globalmacro', 'description'))
					->setAdaptiveWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
					->setAttribute('placeholder', _('description'))
			))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
			(new CCol(
				(new CButton('macros[#{rowNum}][remove]', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
			))
				->addClass(ZBX_STYLE_NOWRAP)
				->addClass(ZBX_STYLE_TOP)
		]))->addClass('form_row')
	)
	->show();

(new CTemplateTag('tag-row-tmpl'))
	->addItem(renderTagTableRow('#{rowNum}', ['tag' => '', 'value' => ''], ['add_post_js' => false]))
	->show();

(new CTemplateTag('custom-intervals-tmpl'))
	->addItem(
		(new CRow([
			(new CRadioButtonList('delay_flex[#{rowNum}][type]', 0))
				->setModern()
				->addValue(_('Flexible'), 0)
				->addValue(_('Scheduling'), 1),
			[
				(new CTextBox('delay_flex[#{rowNum}][delay]'))
					->setAdaptiveWidth(100)
					->setAttribute('placeholder', ZBX_ITEM_FLEXIBLE_DELAY_DEFAULT),
				(new CTextBox('delay_flex[#{rowNum}][schedule]'))
					->addClass(ZBX_STYLE_DISPLAY_NONE)
					->setAdaptiveWidth(100)
					->setAttribute('placeholder', ZBX_ITEM_SCHEDULING_DEFAULT)
			],
			(new CTextBox('delay_flex[#{rowNum}][period]'))
				->setAdaptiveWidth(110)
				->setAttribute('placeholder', ZBX_DEFAULT_INTERVAL),
			(new CButton('delay_flex[#{rowNum}][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		]))->addClass('form_row')
	)
	->show();

(new CTemplateTag('dependency-row-tmpl'))
	->addItem(
		(new CRow([
			[
				new CVar('dependencies[]', '#{triggerid}', 'dependencies_#{triggerid}'),
				(new CLink('#{name}', '#{trigger_url}'))
					->addClass('js-edit-dependency')
						->setAttribute('data-triggerid', '#{triggerid}')
						->setAttribute('data-context', '#{context}')
						->setAttribute('data-parent_discoveryid', '#{parent_discoveryid}')
						->setAttribute('data-prototype', '#{prototype}')
			],

			(new CCol(
				(new CButtonLink(_('Remove')))
					->setName('remove')
					->onClick("javascript: removeDependency('#{triggerid}');")
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->setId('dependency_#{triggerid}')
			->setAttribute('data-triggerid', '#{triggerid}')
	)
	->show();
