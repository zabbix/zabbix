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
$table = (new CTable())
	->setId($data['table_id'])
	->setColumns([
		(new CTableColumn(_('Name')))
			->addStyle('width: '.ZBX_TEXTAREA_MAPPING_VALUE_WIDTH.'px;')
			->addClass('table-col-handle'),
		(new CTableColumn(_('Value')))
			->addStyle('width: '.ZBX_TEXTAREA_MAPPING_NEWVALUE_WIDTH.'px;')
			->addClass('table-col-handle'),
		(new CTableColumn(_('Action')))
			->addClass('table-col-handle')
	]);

if (array_key_exists('with_label', $data) && $data['with_label'] === true) {
	$table->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR);
}
else {
	$table->addClass(ZBX_STYLE_VALUEMAP_LIST_TABLE);
}

$buttons = [
	(new CButton('valuemap_add', _('Add')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->addClass('element-table-add')
		->setEnabled(!$data['readonly'])
];

if ($data['form'] === 'massupdate') {
	$buttons[] = (new CButtonLink(_('Add from template')))
		->addClass('js-element-table-addfrom')
		->setAttribute('data-context', 'template');
	$buttons[] = (new CButtonLink(_('Add from host')))
		->addClass('js-element-table-addfrom')
		->setAttribute('data-context', 'host');
}

$table->addItem((new CTag('tfoot', true))->addItem([new CCol($buttons)]));

$table->show();

$this->includeJsFile('configuration.valuemap.js.php', [
	'valuemaps' => $data['valuemaps'],
	'table_id' => $data['table_id']
]);
