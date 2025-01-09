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

$show_inherited_tags = array_key_exists('show_inherited_tags', $data) && $data['show_inherited_tags'];
$with_automatic = array_key_exists('with_automatic', $data) && $data['with_automatic'];
$data['readonly'] = array_key_exists('readonly', $data) ? $data['readonly'] : false;

if (!$data['readonly']) {
	$this->includeJsFile('configuration.tags.tab.js.php');
}

// form list
$form_grid = (new CFormGrid())->setId('tagsFormList');

if (in_array($data['source'], ['trigger', 'trigger_prototype', 'item', 'httptest'])) {
	switch ($data['source']) {
		case 'trigger':
		case 'trigger_prototype':
			$btn_labels = [_('Trigger tags'), _('Inherited and trigger tags')];
			$on_change = '';
			break;

		case 'httptest':
			$btn_labels = [_('Scenario tags'), _('Inherited and scenario tags')];
			$on_change = 'this.form.submit()';
			break;

		case 'item':
			$btn_labels = [_('Item tags'), _('Inherited and item tags')];
			$on_change = null;
			break;
	}

	$form_grid->addItem(
		new CFormField(
			(new CRadioButtonList('show_inherited_tags', (int) $data['show_inherited_tags']))
				->addValue($btn_labels[0], 0, null, $on_change)
				->addValue($btn_labels[1], 1, null, $on_change)
				->setModern()
		)
	);
}

$table = new CPartial('tags.list.html', $data);

$form_grid->addItem([
	new CLabel('Tags'),
	new CFormField((new CDiv($table))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR))
]);

$form_grid->show();
