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
	))->addClass(ZBX_STYLE_NOWRAP);

	if ($show_inherited_tags) {
		$template_list = [];

		if (array_key_exists('parent_templates', $tag)) {
			CArrayHelper::sort($tag['parent_templates'], ['name']);

			foreach ($tag['parent_templates'] as $templateid => $template) {
				if ($template['permission'] == PERM_READ_WRITE) {
					$template_list[] = (new CLink($template['name'],
						(new CUrl('templates.php'))
							->setArgument('form', 'update')
							->setArgument('templateid', $templateid)
					))->setAttribute('target', '_blank');
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

if ($data['source'] === 'trigger' || $data['source'] === 'trigger_prototype') {
	$tags_form_list->addRow(null,
		(new CRadioButtonList('show_inherited_tags', (int) $data['show_inherited_tags']))
			->addValue(_('Trigger tags'), 0, null, 'this.form.submit()')
			->addValue(_('Inherited and trigger tags'), 1, null, 'this.form.submit()')
			->setModern(true)
	);
}

$tags_form_list->addRow(null, $table);

return $tags_form_list;
