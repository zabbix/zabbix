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
 * @var CPartial $this
 */

$scripts = [];

if ($data['readonly'] && !$data['macros']) {
	$table = new CObject(_('No macros found.'));
}
else {
	$table = (new CTable())
		->setId('tbl_macros')
		->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_CONTAINER);

	$actions_col = $data['readonly'] ? null : '';

	if ($data['show_inherited_macros']) {
		if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
			$link = (new CLink(_('configure'), (new CUrl('zabbix.php'))
					->setArgument('action', 'macros.edit')
					->getUrl()
				))
				->setAttribute('target', '_blank');
			$link = [' (', $link, ')'];
		}
		else {
			$link = null;
		}
		$table->setHeader([
			_('Macro'), _('Effective value'), $actions_col, '', _('Template value'), '', [_('Global value'), $link]
		]);
	}
	else {
		$table->setHeader([_('Macro'), _('Value'), _('Description'), $actions_col]);
	}

	// fields
	foreach ($data['macros'] as $i => $macro) {
		$readonly = ($data['readonly']
			|| ($data['show_inherited_macros'] && !($macro['inherited_type'] & ZBX_PROPERTY_OWN)));

		$macro_input = (new CTextAreaFlexible('macros['.$i.'][macro]', $macro['macro'], ['readonly' => $readonly]))
			->addClass('macro')
			->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
			->setAttribute('placeholder', '{$MACRO}');

		$macro_cell = [$macro_input];
		if (!$data['readonly']) {
			if (array_key_exists('hostmacroid', $macro)) {
				$macro_cell[] = new CVar('macros['.$i.'][hostmacroid]', $macro['hostmacroid']);
			}

			if ($data['show_inherited_macros'] && ($macro['inherited_type'] & ZBX_PROPERTY_INHERITED)) {
				if (array_key_exists('template', $macro)) {
					$macro_cell[] = new CVar('macros['.$i.'][inherited][value]', $macro['template']['value']);
					$macro_cell[] = new CVar('macros['.$i.'][inherited][description]',
						$macro['template']['description']
					);
					$macro_cell[] = new CVar('macros['.$i.'][inherited][macro_type]', $macro['template']['type']);
				}
				else {
					$macro_cell[] = new CVar('macros['.$i.'][inherited][value]', $macro['global']['value']);
					$macro_cell[] = new CVar('macros['.$i.'][inherited][description]',
						$macro['global']['description']
					);
					$macro_cell[] = new CVar('macros['.$i.'][inherited][macro_type]', $macro['global']['type']);
				}
			}
		}

		if ($data['show_inherited_macros']) {
			$macro_cell[] = new CVar('macros['.$i.'][inherited_type]', $macro['inherited_type']);
		}

		// macro value input group.
		$value_input_group = (new CDiv())
			->addClass(ZBX_STYLE_INPUT_GROUP)
			->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH);

		$value_input = ($macro['type'] == ZBX_MACRO_TYPE_TEXT)
			? (new CTextareaFlexible('macros['.$i.'][value]', CMacrosResolverGeneral::getMacroValue($macro), ['add_post_js' => false]))
				->setAttribute('placeholder', _('value'))
			: new CInputSecret('macros['.$i.'][value]', ZBX_MACRO_SECRET_MASK, _('value'), [
				'disabled' => $readonly,
				'add_post_js' => false
			]);

		// We need load this js code in the end.
		$scripts[] = $value_input->getPostJS();

		if ($macro['type'] == ZBX_MACRO_TYPE_TEXT && $readonly) {
			$value_input->setAttribute('readonly', 'readonly');
		}

		$dropdown_options = [
			'title' => _('Change type'),
			'active_class' => ($macro['type'] == ZBX_MACRO_TYPE_TEXT) ? ZBX_STYLE_ICON_TEXT : ZBX_STYLE_ICON_SECRET_TEXT,
			'disabled' => $readonly,
			'items' => [
				['label' => _('Text'), 'value' => ZBX_MACRO_TYPE_TEXT, 'class' => ZBX_STYLE_ICON_TEXT],
				['label' => _('Secret text'), 'value' => ZBX_MACRO_TYPE_SECRET, 'class' => ZBX_STYLE_ICON_SECRET_TEXT]
			]
		];

		$value_input_group->addItem([
			$value_input,
			($macro['type'] == ZBX_MACRO_TYPE_SECRET)
				? (new CButton(null))
					->setAttribute('title', _('Revert changes'))
					->addClass(ZBX_STYLE_BTN_ALT.' '.ZBX_STYLE_BTN_UNDO)
				: null,
			new CButtonDropdown('macros['.$i.'][type]', $macro['type'], $dropdown_options)
		]);

		$row = [
			(new CCol($macro_cell))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
			(new CCol($value_input_group))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT)
		];

		if (!$data['show_inherited_macros']) {
			$row[] = (new CCol(
				(new CTextAreaFlexible('macros['.$i.'][description]', $macro['description']))
					->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
					->setMaxlength(DB::getFieldLength('hostmacro', 'description'))
					->setReadonly($data['readonly'])
					->setAttribute('placeholder', _('description'))
			))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT);
		}

		if (!$data['readonly']) {
			if ($data['show_inherited_macros']) {
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
				if ($macro['template']['rights'] == PERM_READ_WRITE) {
					$link = (new CLink(CHtml::encode($macro['template']['name']),
						'templates.php?form=update&templateid='.$macro['template']['templateid'])
					)
						->addClass('unknown')
						->setAttribute('target', '_blank');
				}
				else {
					$link = new CSpan(CHtml::encode($macro['template']['name']));
				}

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

		if ($data['show_inherited_macros']) {
			$table->addRow((new CRow([
				(new CCol(
					(new CTextAreaFlexible('macros['.$i.'][description]', $macro['description']))
						->setMaxlength(DB::getFieldLength('hostmacro', 'description'))
						->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
						->setAttribute('placeholder', _('description'))
						->setReadonly($data['readonly'] || !($macro['inherited_type'] & ZBX_PROPERTY_OWN))
				))
					->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT)
					->setColSpan(8)
			]))->addClass('form_row'));
		}
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
insert_js(implode("\n", $scripts));
