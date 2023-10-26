<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
 * @var array    $data
 */

$show_inherited_tags = array_key_exists('show_inherited_tags', $data) && $data['show_inherited_tags'];
$with_automatic = array_key_exists('with_automatic', $data) && $data['with_automatic'];
$data['readonly'] = array_key_exists('readonly', $data) ? $data['readonly'] : false;

$table = (new CTable())
	->addClass('tags-table')
	->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_CONTAINER)
	->setHeader([
		_('Name'),
		_('Value'),
		'',
		$show_inherited_tags ? _('Parent templates') : null
	]);

$allowed_ui_conf_templates = CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);

// fields
foreach (array_values($data['tags']) as $i => $tag) {
	$tag += ['type' => ZBX_PROPERTY_OWN];

	if ($with_automatic) {
		$tag += ['automatic' => ZBX_TAG_MANUAL];
	}

	$readonly = $data['readonly']
		|| ($show_inherited_tags && $tag['type'] == ZBX_PROPERTY_INHERITED)
		|| ($with_automatic && $tag['automatic'] == ZBX_TAG_AUTOMATIC);

	$tag_input = (new CTextAreaFlexible('tags['.$i.'][tag]', $tag['tag'], ['readonly' => $readonly]))
		->setWidth(ZBX_TEXTAREA_TAG_WIDTH)
		->setAttribute('placeholder', _('tag'));

	$tag_cell = [$tag_input];

	if ($show_inherited_tags) {
		$tag_cell[] = new CVar('tags['.$i.'][type]', $tag['type']);
	}

	if ($with_automatic) {
		$tag_cell[] = new CVar('tags['.$i.'][automatic]', $tag['automatic']);
	}

	$value_input = (new CTextAreaFlexible('tags['.$i.'][value]', $tag['value'], ['readonly' => $readonly]))
		->setWidth(ZBX_TEXTAREA_TAG_VALUE_WIDTH)
		->setAttribute('placeholder', _('value'));

	$actions = [];

	if ($with_automatic && $tag['automatic'] == ZBX_TAG_AUTOMATIC) {
		switch ($data['source']) {
			case 'host':
				$actions[] = (new CSpan(_('(created by host discovery)')))->addClass(ZBX_STYLE_GREY);
				break;
		}
	}
	elseif ($show_inherited_tags && ($tag['type'] & ZBX_PROPERTY_INHERITED) != 0) {
		$actions[] = (new CButton('tags['.$i.'][disable]', _('Remove')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-disable')
			->setEnabled(!$readonly);
	}
	else {
		$actions[] = (new CButton('tags['.$i.'][remove]', _('Remove')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-remove')
			->setEnabled(!$readonly);
	}

	$row = [
		(new CCol($tag_cell))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
		(new CCol($value_input))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
		(new CCol($actions))
			->addClass(ZBX_STYLE_NOWRAP)
			->addClass(ZBX_STYLE_TOP)
	];

	if ($show_inherited_tags) {
		$template_list = [];

		if (array_key_exists('parent_templates', $tag)) {
			CArrayHelper::sort($tag['parent_templates'], ['name']);

			foreach ($tag['parent_templates'] as $templateid => $template) {
				if ($allowed_ui_conf_templates && $template['permission'] == PERM_READ_WRITE) {
					$template_link = (new CLink($template['name']))->setAttribute('data-templateid', $templateid);

					$data['source'] !== 'httptest'
						? $template_link->addClass('js-edit-template')
						: $template_link->onClick('view.editTemplate(event, this.dataset.templateid);');

					$template_list[] = $template_link;
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

$table->show();
