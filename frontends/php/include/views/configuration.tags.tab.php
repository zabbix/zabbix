<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
	require_once dirname(__FILE__).'/js/configuration.tags.tab.js.php';
}

// form list
$tags_form_list = new CFormList('tagsFormList');

$table = (new CTable())
	->setId('tbl-tags')
	->setHeader([
		_('Name'),
		_('Value'),
		_('Action'),
		$data['show_inherited_tags'] ? _('Parent templates') : null
	]);

// fields
foreach ($data['tags'] as $i => $tag) {
	$readonly = ($data['readonly'] || ($data['show_inherited_tags'] && !($tag['type'] & ZBX_PROPERTY_OWN)));

	$tag_input = (new CTextBox('tags['.$i.'][tag]', $tag['tag'], $readonly))
		->setWidth(ZBX_TEXTAREA_TAG_WIDTH)
		->setAttribute('placeholder', _('tag'));

	$tag_cell = [$tag_input];

	if ($data['show_inherited_tags']) {
		$tag_cell[] = new CVar('tags['.$i.'][type]', $tag['type']);
	}

	$value_input = (new CTextBox('tags['.$i.'][value]', $tag['value'], $readonly))
		->setWidth(ZBX_TEXTAREA_TAG_WIDTH)
		->setAttribute('placeholder', _('value'));

	$row = [$tag_cell, $value_input];

	$row[] = (new CCol(
		($data['show_inherited_tags'] && ($tag['type'] & ZBX_PROPERTY_INHERITED))
			? (new CButton('tags['.$i.'][disable]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-disable')
				->setEnabled(!$readonly)
			: (new CButton('tags['.$i.'][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
				->setEnabled(!$readonly)
	))->addClass(ZBX_STYLE_NOWRAP);

	if ($data['show_inherited_tags']) {
		$template_list = [];

		if (array_key_exists('templateids', $tag)) {
			foreach ($tag['templateids'] as $templateid) {
				$template_name = CHtml::encode($data['parent_templates'][$templateid]['name']);

				if ($data['parent_templates'][$templateid]['permission'] == PERM_READ_WRITE) {
					$template_list[] = (new CLink($template_name,
						(new CUrl('templates.php'))
							->setArgument('form', 'update')
							->setArgument('templateid', $templateid)
					))->setAttribute('target', '_blank');
				}
				else {
					$template_list[] = (new CSpan($template_name))->addClass(ZBX_STYLE_GREY);
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

$tags_form_list
	->addRow(null,
		(new CRadioButtonList('show_inherited_tags', (int) $data['show_inherited_tags']))
			->addValue($data['is_template'] ? _('Template tags') : _('Host tags'), 0, null, 'this.form.submit()')
			->addValue($data['is_template'] ? _('Inherited and template tags') : _('Inherited and host tags'), 1,
				null, 'this.form.submit()'
			)
			->setModern(true)
	)
	->addRow(null, $table);

return $tags_form_list;
