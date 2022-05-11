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
 * @var CPartial $this
 */

if ($data['readonly'] && !$data['macros']) {
	$table = new CObject(_('No macros found.'));
}
else {
	$link = null;
	$is_hostprototype = array_key_exists('parent_hostid', $data);
	$inherited_width = $is_hostprototype ? ZBX_TEXTAREA_MACRO_INHERITED_WIDTH : ZBX_TEXTAREA_MACRO_VALUE_WIDTH;
	$table = (new CTable())
		->setId('tbl_macros')
		->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_CONTAINER)
		->addClass('inherited-macros-table');

	if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
		$link = (new CLink(_('configure'), (new CUrl('zabbix.php'))
				->setArgument('action', 'macros.edit')
				->getUrl()
			))
			->setTarget('_blank');
		$link = [' (', $link, ')'];
	}

	$table->setColumns([
		(new CTableColumn(_('Macro')))->addClass('table-col-macro'),
		(new CTableColumn(_('Effective value')))->addClass('table-col-value'),
		(new CTableColumn($data['readonly'] ? null : ''))->addClass('table-col-action'),
		$is_hostprototype ? (new CTableColumn())->addClass('table-col-arrow') : null,
		$is_hostprototype ? (new CTableColumn())->addClass('table-col-parent-value') : null,
		(new CTableColumn())->addClass('table-col-arrow'),
		(new CTableColumn(_('Template value')))->addClass('table-col-template-value'),
		(new CTableColumn())->addClass('table-col-arrow'),
		(new CTableColumn([_('Global value'), $link]))->addClass('table-col-global-value')
	]);

	foreach ($data['macros'] as $i => $macro) {
		$readonly = ($data['readonly'] || !($macro['inherited_type'] & ZBX_PROPERTY_OWN));
		$macro_cell = [
			(new CTextAreaFlexible('macros['.$i.'][macro]', $macro['macro']))
				->setReadonly($data['readonly'] || $macro['inherited_type'] & ZBX_PROPERTY_INHERITED)
				->addClass('macro')
				->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
				->setAttribute('placeholder', '{$MACRO}'),
			new CVar('macros['.$i.'][inherited_type]', $macro['inherited_type'])
		];

		if (!$data['readonly']) {
			if (array_key_exists('hostmacroid', $macro)) {
				$macro_cell[] = new CVar('macros['.$i.'][hostmacroid]', $macro['hostmacroid']);
			}

			if ($macro['inherited_type'] & ZBX_PROPERTY_INHERITED) {
				$inherited_macro = $macro[$macro['inherited_level']];
				$macro_cell[] = new CVar('macros['.$i.'][inherited][value]', $inherited_macro['value']);
				$macro_cell[] = new CVar('macros['.$i.'][inherited][description]', $inherited_macro['description']);
				$macro_cell[] = new CVar('macros['.$i.'][inherited][macro_type]', $inherited_macro['type']);
			}
		}

		$macro_value = (new CMacroValue($macro['type'], 'macros['.$i.']', null, false))->setReadonly($readonly);

		if ($macro['type'] == ZBX_MACRO_TYPE_SECRET) {
			$macro_value->addRevertButton();
			$macro_value->setRevertButtonVisibility(array_key_exists('value', $macro)
				&& array_key_exists('hostmacroid', $macro)
			);
			$macro_value->setReadonly($readonly || ($macro['inherited_type'] & ZBX_PROPERTY_BOTH));
		}

		if (array_key_exists('value', $macro)) {
			$macro_value->setAttribute('value', $macro['value']);
		}

		$row = [
			(new CCol($macro_cell))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
			(new CCol($macro_value))
				->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT)
				->addClass(ZBX_STYLE_NOWRAP)
		];

		if (!$data['readonly']) {
			if (($macro['inherited_type'] & ZBX_PROPERTY_BOTH) == ZBX_PROPERTY_BOTH) {
				$row[] = (new CCol(
					(new CButton('macros['.$i.'][change]', _('Remove')))
						->addClass(ZBX_STYLE_BTN_LINK)
						->addClass('element-table-change')
				))->addClass(ZBX_STYLE_NOWRAP);
			}
			elseif ($macro['inherited_type'] & ZBX_PROPERTY_INHERITED) {
				$row[] = (new CCol(
					(new CButton('macros['.$i.'][change]', _x('Change', 'verb')))
						->addClass(ZBX_STYLE_BTN_LINK)
						->addClass('element-table-change')
				))->addClass(ZBX_STYLE_NOWRAP);
			}
			else {
				$row[] = (new CCol(
					(new CButton('macros['.$i.'][remove]', _('Remove')))
						->addClass(ZBX_STYLE_BTN_LINK)
						->addClass('element-table-remove')
				))->addClass(ZBX_STYLE_NOWRAP);
			}
		}

		// Parent host macro value.
		if ($is_hostprototype) {
			$row[] = array_key_exists('parent_host', $macro) ? '&lArr;' : '';
			$row[] = (new CDiv(array_key_exists('parent_host', $macro) ? '"'.$macro['parent_host']['value'].'"' : null))
				->setAdaptiveWidth($inherited_width)
				->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS);
		}

		// Template macro value.
		$template_macro = null;

		if (array_key_exists('template', $macro)) {
			if ($macro['template']['rights'] == PERM_READ_WRITE) {
				$link = (new CLink(CHtml::encode($macro['template']['name']),
					'templates.php?form=update&templateid='.$macro['template']['templateid'])
				)
					->addClass('unknown')
					->setTarget('_blank');
			}
			else {
				$link = new CSpan(CHtml::encode($macro['template']['name']));
			}

			$template_macro = [$link, NAME_DELIMITER, '"'.$macro['template']['value'].'"'];
		}

		$row[] = array_key_exists('template', $macro) ? '&lArr;' : '';
		$row[] = (new CDiv(array_key_exists('template', $macro) ? $template_macro : null))
			->setAdaptiveWidth($inherited_width)
			->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS);

		// Global macro value.
		$row[] = array_key_exists('global', $macro) ? '&lArr;' : '';
		$row[] = (new CDiv(array_key_exists('global', $macro) ? '"'.$macro['global']['value'].'"' : null))
			->setAdaptiveWidth($inherited_width)
			->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS);

		$table
			->addRow($row, 'form_row')
			->addRow((new CRow([
				(new CCol(
					(new CTextAreaFlexible('macros['.$i.'][description]', $macro['description']))
						->setMaxlength(DB::getFieldLength('hostmacro', 'description'))
						->setAdaptiveWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
						->setAttribute('placeholder', _('description'))
						->setReadonly($readonly || (
							($macro['type'] == ZBX_MACRO_TYPE_SECRET) && ($macro['inherited_type'] & ZBX_PROPERTY_BOTH)
						))
				))
					->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT)
					->setColSpan(count($row))
			]))->addClass('form_row'));
	}

	// buttons
	if (!$data['readonly']) {
		$table->setFooter(new CCol(
			(new CButton('macro_add', _('Add')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-add')
		));
	}
}

$table->show();

// Initializing input secret and macro value init script separately.
(new CScriptTag("jQuery('.input-secret').inputSecret();"))->show();
(new CScriptTag("jQuery('.macro-input-group').macroValue();"))->show();
