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

$data += ['readonly' => false];

if (!$data['readonly']) {
	$this->includeJsFile('configuration.tags.tab.js.php');
}

$on_change = null;
$show_inherited_tags_id = 'show_inherited_tags';

switch ($data['source']) {
	case 'template':
		$btn_labels = [_('Template tags'), _('Inherited and template tags')];
		$show_inherited_tags_id = 'template_show_inherited_tags';
		break;

	case 'host':
		$show_inherited_tags_id = 'host_show_inherited_tags';
		// break; is not missing here

	case 'host_prototype':
		$btn_labels = [_('Host tags'), _('Inherited and host tags')];
		break;

	case 'item':
	case 'item_prototype':
		$btn_labels = [_('Item tags'), _('Inherited and item tags')];
		break;

	case 'trigger':
	case 'trigger_prototype':
		$btn_labels = [_('Trigger tags'), _('Inherited and trigger tags')];
		break;

	case 'httptest':
		$btn_labels = [_('Scenario tags'), _('Inherited and scenario tags')];
		$on_change = 'this.form.submit()';
		break;
}

$form_grid = (new CFormGrid())->addItem(
	new CFormField(
		(new CRadioButtonList($show_inherited_tags_id, (int) $data['show_inherited_tags']))
			->addValue($btn_labels[0], 0, null, $on_change)
			->addValue($btn_labels[1], 1, null, $on_change)
			->setModern()
	)
);

$table = new CPartial('tags.list.html', $data);

$form_grid->addItem([
	new CLabel(_('Tags')),
	new CFormField((new CDiv($table))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR))
]);

$form_grid->show();
