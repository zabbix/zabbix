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
 * @var CPartial $this
 * @var array    $data
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
		!$data['readonly'] ? (new CTableColumn())->addClass('table-col-action') : null,
		$is_hostprototype ? (new CTableColumn())->addClass('table-col-arrow') : null,
		$is_hostprototype ? (new CTableColumn(_('Parent host value')))->addClass('table-col-parent-value') : null,
		(new CTableColumn())->addClass('table-col-arrow'),
		(new CTableColumn(_('Template value')))->addClass('table-col-template-value'),
		(new CTableColumn())->addClass('table-col-arrow'),
		(new CTableColumn([_('Global value'), $link]))->addClass('table-col-global-value')
	]);

	foreach ($data['macros'] as $i => $macro) {
		$macro_value = (new CMacroValue($macro['type'], 'macros['.$i.']', null, false))
			->setReadonly($data['readonly']
				|| !($macro['discovery_state'] & CControllerHostMacrosList::DISCOVERY_STATE_CONVERTING)
				|| !($macro['inherited_type'] & ZBX_PROPERTY_OWN)
			);

		$macro_cell = [
			(new CTextAreaFlexible('macros['.$i.'][macro]', $macro['macro']))
				->setReadonly($data['readonly']
					|| $macro['discovery_state'] != CControllerHostMacrosList::DISCOVERY_STATE_MANUAL
					|| $macro['inherited_type'] & ZBX_PROPERTY_INHERITED
				)
				->addClass('macro')
				->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
				->setAttribute('placeholder', '{$MACRO}')
				->disableSpellcheck()
		];

		$macro_cell[] = new CVar('macros['.$i.'][discovery_state]', $macro['discovery_state']);

		if (array_key_exists('hostmacroid', $macro)) {
			$macro_cell[] = new CVar('macros['.$i.'][hostmacroid]', $macro['hostmacroid']);
		}

		$macro_cell[] = new CVar('macros['.$i.'][inherited_type]', $macro['inherited_type']);

		if ($macro['inherited_type'] & ZBX_PROPERTY_INHERITED) {
			$inherited_macro = $macro[$macro['inherited_level']];
			$macro_cell[] = new CVar('macros['.$i.'][inherited][value]', $inherited_macro['value']);
			$macro_cell[] = new CVar('macros['.$i.'][inherited][description]', $inherited_macro['description']);
			$macro_cell[] = new CVar('macros['.$i.'][inherited][macro_type]', $inherited_macro['type']);
		}

		if ($macro['discovery_state'] != CControllerHostMacrosList::DISCOVERY_STATE_MANUAL) {
			$macro_cell[] = new CVar('macros['.$i.'][original_value]', $macro['original']['value']);
			$macro_cell[] = new CVar('macros['.$i.'][original_description]', $macro['original']['description']);
			$macro_cell[] = new CVar('macros['.$i.'][original_macro_type]', $macro['original']['type']);
		}

		if (array_key_exists('allow_revert', $macro)) {
			$macro_value->setAttribute('placeholder', 'value');
			$macro_value->addRevertButton();
			$macro_value->setRevertButtonVisibility($macro['type'] != ZBX_MACRO_TYPE_SECRET
				|| array_key_exists('value', $macro)
			);

			$macro_cell[] = new CVar('macros['.$i.'][allow_revert]', '1');
		}

		if (array_key_exists('value', $macro)) {
			$macro_value->setAttribute('value', $macro['value']);
		}

		if (!$data['readonly']) {
			// buttons
			$action_buttons = [];
			if ($macro['inherited_type'] & ZBX_PROPERTY_OWN) {
				if ($macro['discovery_state'] == CControllerHostMacrosList::DISCOVERY_STATE_CONVERTING) {
					$action_buttons[] = (new CButton('macros['.$i.'][change_state]', _('Revert')))
						->addClass(ZBX_STYLE_BTN_LINK)
						->addClass('element-table-set-manual');
				}
				elseif ($macro['discovery_state'] == CControllerHostMacrosList::DISCOVERY_STATE_AUTOMATIC) {
					$action_buttons[] = (new CButton('macros['.$i.'][change_state]', _x('Change', 'verb')))
						->addClass(ZBX_STYLE_BTN_LINK)
						->addClass('element-table-set-manual');
				}

				if (($macro['inherited_type'] & ZBX_PROPERTY_BOTH) == ZBX_PROPERTY_BOTH) {
					$action_buttons[] = (new CButton('macros['.$i.'][change_inheritance]', _x('Remove', 'verb')))
						->addClass(ZBX_STYLE_BTN_LINK)
						->addClass('element-table-change');
				}
				else {
					$action_buttons[] = (new CButton('macros['.$i.'][remove]', _x('Remove', 'verb')))
						->addClass(ZBX_STYLE_BTN_LINK)
						->addClass('element-table-remove');
				}
			}
			elseif ($macro['inherited_type'] & ZBX_PROPERTY_INHERITED) {
				$action_buttons[] = (new CButton('macros['.$i.'][change_inheritance]', _x('Change', 'verb')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-change');
			}
		}

		$row = [
			(new CCol($macro_cell))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
			(new CCol($macro_value))
				->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT)
				->addClass(ZBX_STYLE_NOWRAP),
			!$data['readonly'] ? (new CCol(new CHorList($action_buttons)))->addClass(ZBX_STYLE_NOWRAP) : null
		];

		// Parent host macro value.
		if ($is_hostprototype) {
			$row[] = array_key_exists('parent_host', $macro) ? LARR() : '';
			$row[] = (new CDiv(array_key_exists('parent_host', $macro) ? '"'.$macro['parent_host']['value'].'"' : null))
				->setAdaptiveWidth($inherited_width)
				->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS);
		}

		// Template macro value.
		$template_macro = null;

		if (array_key_exists('template', $macro)) {
			if ($macro['template']['rights'] == PERM_READ_WRITE) {
				$template_url = (new CUrl('zabbix.php'))
					->setArgument('action', 'popup')
					->setArgument('popup', 'template.edit')
					->setArgument('templateid', $macro['template']['templateid'])
					->getUrl();

				$link = new CLink($macro['template']['name'], $template_url);
			}
			else {
				$link = new CSpan($macro['template']['name']);
			}

			$template_macro = [$link, NAME_DELIMITER, '"'.$macro['template']['value'].'"'];
		}

		$row[] = array_key_exists('template', $macro) ? LARR() : '';
		$row[] = (new CDiv(array_key_exists('template', $macro) ? $template_macro : null))
			->setAdaptiveWidth($inherited_width)
			->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS);

		// Global macro value.
		$row[] = array_key_exists('global', $macro) ? LARR() : '';
		$row[] = (new CDiv(array_key_exists('global', $macro) ? '"'.$macro['global']['value'].'"' : null))
			->setAdaptiveWidth($inherited_width)
			->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS);

		$description_readonly = ($data['readonly']
			|| $macro['discovery_state'] == CControllerHostMacrosList::DISCOVERY_STATE_AUTOMATIC)
			|| !($macro['inherited_type'] & ZBX_PROPERTY_OWN);

		$table
			->addRow($row, 'form_row')
			->addRow((new CRow([
				(new CCol([
					(new CTextAreaFlexible('macros['.$i.'][description]', $macro['description']))
						->setMaxlength(DB::getFieldLength('hostmacro', 'description'))
						->setAdaptiveWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
						->setAttribute('placeholder', _('description'))
						->setReadonly($description_readonly),
					($macro['discovery_state'] != CControllerHostMacrosList::DISCOVERY_STATE_MANUAL)
						? (new CSpan(_('(created by host discovery)')))->addClass(ZBX_STYLE_GREY)
						: null
				]))
					->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT)
					->addClass(CControllerHostMacrosList::MACRO_TEXTAREA_PARENT)
					->setColSpan(count($row))
			]))->addClass('form_row'));
	}

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
