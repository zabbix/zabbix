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

if (!$data['readonly']) {
	$this->includeJsFile('configuration.tags.tab.js.php');
}

$show_inherited_tags = array_key_exists('show_inherited_tags', $data) && $data['show_inherited_tags'];
$with_automatic = array_key_exists('with_automatic', $data) && $data['with_automatic'];
$parent_template_header = null;

if ($show_inherited_tags && $data['context'] === 'host') {
	$parent_template_header = in_array($data['source'], ['trigger', 'trigger_prototype'])
		? _('Parent templates')
		: _('Parent template');
}

// form list
$tags_form_list = new CFormList('tagsFormList');

$table = (new CTable())
	->addClass('tags-table')
	->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_CONTAINER)
	->setHeader([
		_('Name'),
		_('Value'),
		'',
		$parent_template_header
	]);

$allowed_ui_conf_templates = CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);

// fields
foreach ($data['tags'] as $i => $tag) {
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

	if ($show_inherited_tags && $data['context'] === 'host') {
		$template_list = [];

		if (array_key_exists('parent_object', $tag)) {
			foreach ($tag['parent_object']['template_names'] as $templateid => $template_name) {
				if (array_key_exists('templateids', $tag) && !in_array($templateid, $tag['templateids'])) {
					continue;
				}

				if ($template_list) {
					$template_list[] = ', ';
				}

				if ($tag['parent_object']['editable']) {
					$template_list[] = (new CLink($template_name,
						(new CUrl('templates.php'))
							->setArgument('form', 'update')
							->setArgument('templateid', $templateid)
					))->setTarget('_blank');
				}
				else {
					$template_list[] = (new CSpan($template_name))->addClass(ZBX_STYLE_GREY);
				}
			}
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
