<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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

$data['readonly'] = array_key_exists('readonly', $data) ? $data['readonly'] : false;

if (!$data['readonly']) {
	$this->includeJsFile('proxy.apm.attributes.js.php', $data);
}

$header_columns = [
	new CTableColumn(_('Name')),
	new CTableColumn(_('Value')),
	new CTableColumn(_('Type')),
	new CTableColumn('')
];

$table = (new CTable())
	->addClass('attributes-table')
	->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_CONTAINER)
	->setColumns($header_columns);

$options = [
	'attribute_types' => $data['attribute_types'],
	'field_name' => $data['field_name'],
	'has_inline_validation' => $data['has_inline_validation'],
	'readonly' => $data['readonly']
];

if ($data['has_inline_validation']) {
	$table
		->setAttribute('data-field-type', 'set')
		->setAttribute('data-field-name', $data['field_name']);
}

// fields
foreach (array_values($data['attributes']) as $index => $attribute) {
	$table->addItem(renderProxyApmAttrRow($index, $attribute, $options));
}

// buttons
$table->setFooter(
	(new CCol(
		(new CButton('attribute_add', _('Add')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-add')
			->setEnabled(!$data['readonly'])
	))->setColSpan(count($header_columns))
);

$table->show();
