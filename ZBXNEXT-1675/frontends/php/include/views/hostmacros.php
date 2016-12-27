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


if (!$data['readonly']) {
	require_once dirname(__FILE__).'/js/hostmacros.js.php';
}

// form list
$macros_form_list = new CFormList('macrosFormList');

if ($data['readonly'] && !$data['macros']) {
	$table = _('No macros found.');
}
else {
	$table = (new CTable())->setId('tbl_macros');

	$actions_col = $data['readonly'] ? null : '';
	if ($data['show_inherited_macros']) {
		if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
			$link = (new CLink(_('configure'), 'adm.macros.php'))
				->setAttribute('target', '_blank');
			$link = [' (', $link, ')'];
		}
		else {
			$link = null;
		}
		$table->setHeader([
			_('Macro'), '', _('Effective value'), $actions_col, '', _('Template value'), '', [_('Global value'), $link]
		]);
	}
	else {
		$table->setHeader([_('Macro'), '', _('Value'), $actions_col]);
	}

	// fields
	foreach ($data['macros'] as $i => $macro) {
		$macro_input = (new CTextBox('macros['.$i.'][macro]', $macro['macro'], false, 255))
			->addClass('macro')
			->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
			->setReadOnly(
				$data['readonly'] || ($data['show_inherited_macros'] && ($macro['type'] & MACRO_TYPE_INHERITED))
			)
			->setAttribute('placeholder', '{$MACRO}');

		$macro_cell = [$macro_input];
		if (!$data['readonly']) {
			if (array_key_exists('hostmacroid', $macro)) {
				$macro_cell[] = new CVar('macros['.$i.'][hostmacroid]', $macro['hostmacroid']);
			}

			if ($data['show_inherited_macros'] && ($macro['type'] & MACRO_TYPE_INHERITED)) {
				$macro_cell[] = new CVar('macros['.$i.'][inherited][value]',
					array_key_exists('template', $macro) ? $macro['template']['value'] : $macro['global']['value']
				);
			}
		}

		if ($data['show_inherited_macros']) {
			$macro_cell[] = new CVar('macros['.$i.'][type]', $macro['type']);
		}

		$value_input = (new CTextBox('macros['.$i.'][value]', $macro['value'], false, 255))
			->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
			->setReadOnly(
				$data['readonly'] || ($data['show_inherited_macros'] && !($macro['type'] & MACRO_TYPE_HOSTMACRO))
			)
			->setAttribute('placeholder', _('value'));

		$row = [$macro_cell, '&rArr;', $value_input];

		if (!$data['readonly']) {
			if ($data['show_inherited_macros']) {
				if (($macro['type'] & MACRO_TYPE_BOTH) == MACRO_TYPE_BOTH) {
					$row[] = (new CCol(
						(new CButton('macros['.$i.'][change]', _('Remove')))
							->addClass(ZBX_STYLE_BTN_LINK)
							->addClass('element-table-change')
					))->addClass(ZBX_STYLE_NOWRAP);
				}
				elseif ($macro['type'] & MACRO_TYPE_INHERITED) {
					$row[] = (new CCol(
						(new CButton('macros['.$i.'][change]', _('Change')))
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
			else {
				$row[] = (new CCol(
					(new CButton('macros['.$i.'][remove]', _('Remove')))
						->addClass(ZBX_STYLE_BTN_LINK)
						->addClass('element-table-remove')
				))->addClass(ZBX_STYLE_NOWRAP);
			}
		}

		if ($data['show_inherited_macros']) {
			if (array_key_exists('template', $macro)) {
				$link = (new CLink(CHtml::encode($macro['template']['name']),
					'templates.php?form=update&templateid='.$macro['template']['templateid'])
					)
					->addClass('unknown')
					->setAttribute('target', '_blank');
				$row[] = '&lArr;';
				$row[] = (new CDiv([$link, NAME_DELIMITER, '"'.$macro['template']['value'].'"']))
					->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)
					->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH);
			}
			else {
				array_push($row, '',
					(new CDiv())
						->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)
						->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
				);
			}

			if (array_key_exists('global', $macro)) {
				$row[] = '&lArr;';
				$row[] = (new CDiv('"'.$macro['global']['value'].'"'))
					->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)
					->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH);
			}
			else {
				array_push($row, '',
					(new CDiv())
						->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)
						->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
				);
			}
		}

		$table->addRow($row, 'form_row');
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

$macros_form_list
	->addRow(null,
		(new CRadioButtonList('show_inherited_macros', (int) $data['show_inherited_macros']))
			->addValue($data['is_template'] ? _('Template macros') : _('Host macros'), 0, null, 'this.form.submit()')
			->addValue($data['is_template'] ? _('Inherited and template macros') : _('Inherited and host macros'), 1,
				null, 'this.form.submit()'
			)
			->setModern(true)
	)
	->addRow(null, $table);

return $macros_form_list;
