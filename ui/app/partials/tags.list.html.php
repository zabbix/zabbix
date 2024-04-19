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
 * @var array    $data
 */

$show_inherited_tags = array_key_exists('show_inherited_tags', $data) && $data['show_inherited_tags'];
$with_automatic = array_key_exists('with_automatic', $data) && $data['with_automatic'];
$data['readonly'] = array_key_exists('readonly', $data) ? $data['readonly'] : false;

$header_columns = [
	(new CTableColumn(_('Name')))
		->addStyle('width: '.ZBX_TEXTAREA_TAG_WIDTH.'px;')
		->addClass('table-col-handle'),
	(new CTableColumn(_('Value')))
		->addStyle('width: '.ZBX_TEXTAREA_TAG_VALUE_WIDTH.'px;')
		->addClass('table-col-handle'),
	(new CTableColumn(''))->addClass('table-col-handle')
];

if ($show_inherited_tags) {
	$header_columns[] = (new CTableColumn(_('Parent templates')))->addClass('table-col-handle');
}

$table = (new CTable())
	->addClass('tags-table')
	->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_CONTAINER)
	->setColumns($header_columns);

$options = [
	'with_automatic' => $with_automatic,
	'show_inherited_tags' => $show_inherited_tags,
	'source' => $data['source']
];

// fields
foreach (array_values($data['tags']) as $index  => $tag) {
	$tag += ['type' => ZBX_PROPERTY_OWN];

	if ($with_automatic) {
		$tag += ['automatic' => ZBX_TAG_MANUAL];
	}

	$options['readonly'] = $data['readonly']
		|| ($show_inherited_tags && $tag['type'] == ZBX_PROPERTY_INHERITED)
		|| ($with_automatic && $tag['automatic'] == ZBX_TAG_AUTOMATIC);

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

$table->show();
