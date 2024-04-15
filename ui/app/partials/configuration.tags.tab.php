<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

$header_columns = [
	(new CTableColumn(_('Name')))
		->addStyle('width: '.ZBX_TEXTAREA_TAG_WIDTH.'px;')
		->addClass('table-col-handle'),
	(new CTableColumn(_('Value')))
		->addStyle('width: '.ZBX_TEXTAREA_TAG_WIDTH.'px;')
		->addClass('table-col-handle'),
	(new CTableColumn(_('Action')))->addClass('table-col-handle')
];

if ($show_inherited_tags) {
	$header_columns[] = (new CTableColumn(_('Parent templates')))->addClass('table-col-handle');
}

$table = (new CTable())
	->addClass('tags-table')
	->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_CONTAINER)
	->setColumns($header_columns);

// fields
$options = [
	'show_inherited_tags' => $show_inherited_tags
];

foreach ($data['tags'] as $index => $tag) {
	if (!array_key_exists('type', $tag)) {
		$tag['type'] = ZBX_PROPERTY_OWN;
	}

	$options['readonly'] = $show_inherited_tags && $tag['type'] == ZBX_PROPERTY_INHERITED;

	$table->addItem(renderTagTableRow($index, $tag, $options));
}

// buttons
$table->setFooter(
	(new CCol(
		(new CButton('tag_add', _('Add')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-add')
			->setEnabled(!$data['readonly'])
	))->setColSpan(count($header_columns))
);

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
