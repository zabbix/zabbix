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

if (!$data['readonly']) {
	$this->includeJsFile('configuration.tags.tab.js.php');
}

$show_inherited_tags = (array_key_exists('show_inherited_tags', $data) && $data['show_inherited_tags']);

// form list
$tags_form_list = new CFormList('tagsFormList');

$table = (new CTable())
	->setId('tags-table')
	->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_CONTAINER)
	->setHeader([
		_('Name'),
		_('Value'),
		_('Action'),
		$show_inherited_tags ? _('Parent templates') : null
	]);

$allowed_ui_conf_templates = CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);

// fields
foreach ($data['tags'] as $i => $tag) {
	if (!array_key_exists('type', $tag)) {
		$tag['type'] = ZBX_PROPERTY_OWN;
	}

	$readonly = ($data['readonly'] || ($show_inherited_tags && $tag['type'] == ZBX_PROPERTY_INHERITED));

	$tag_input = (new CTextAreaFlexible('tags['.$i.'][tag]', $tag['tag'], ['readonly' => $readonly]))
		->setWidth(ZBX_TEXTAREA_TAG_WIDTH)
		->setAttribute('placeholder', _('tag'));

	$tag_cell = [$tag_input];

	if ($show_inherited_tags) {
		$tag_cell[] = new CVar('tags['.$i.'][type]', $tag['type']);
	}

	$value_input = (new CTextAreaFlexible('tags['.$i.'][value]', $tag['value'], ['readonly' => $readonly]))
		->setWidth(ZBX_TEXTAREA_TAG_VALUE_WIDTH)
		->setAttribute('placeholder', _('value'));

	$row = [
		(new CCol($tag_cell))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
		(new CCol($value_input))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT)
	];

	$row[] = (new CCol(
		($show_inherited_tags && ($tag['type'] & ZBX_PROPERTY_INHERITED))
			? (new CButton('tags['.$i.'][disable]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-disable')
				->setEnabled(!$readonly)
			: (new CButton('tags['.$i.'][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
				->setEnabled(!$readonly)
	))
		->addClass(ZBX_STYLE_NOWRAP)
		->addClass(ZBX_STYLE_TOP);

	if ($show_inherited_tags) {
		$template_list = [];

		if (array_key_exists('parent_templates', $tag)) {
			CArrayHelper::sort($tag['parent_templates'], ['name']);

			foreach ($tag['parent_templates'] as $templateid => $template) {
				if ($allowed_ui_conf_templates && $template['permission'] == PERM_READ_WRITE) {
					$template_list[] = (new CLink($template['name'],
						(new CUrl('templates.php'))
							->setArgument('form', 'update')
							->setArgument('templateid', $templateid)
					))->setTarget('_blank');
				}
				else {
					$template_list[] = (new CSpan($template['name']))->addClass(ZBX_STYLE_GREY);
				}

				$template_list[] = ', ';
			}

			array_pop($template_list);
		}

		$row[] = $template_list;
	}

	$table->addRow($row, 'form_row');
}

// buttons
$table->setFooter(new CCol(
	(new CButton('tag_add', _('Add')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->addClass('element-table-add')
		->setEnabled(!$data['readonly'])
));

if (in_array($data['source'], ['trigger', 'trigger_prototype', 'item', 'httptest'])) {
	switch ($data['source']) {
		case 'trigger':
		case 'trigger_prototype':
			$btn_labels = [_('Trigger tags'), _('Inherited and trigger tags')];
			$on_change = 'this.form.submit()';
			break;

		case 'httptest':
			$btn_labels = [_('Scenario tags'), _('Inherited and scenario tags')];
			$on_change = 'window.httpconf.$form.submit()';
			break;

		case 'item':
			$btn_labels = [_('Item tags'), _('Inherited and item tags')];
			$on_change = 'this.form.submit()';
			break;
	}

	$tags_form_list->addRow(null,
		(new CRadioButtonList('show_inherited_tags', (int) $data['show_inherited_tags']))
			->addValue($btn_labels[0], 0, null, $on_change)
			->addValue($btn_labels[1], 1, null, $on_change)
			->setModern(true)
	);
}

$tags_form_list->addRow(null, $table);

$tags_form_list->show();
