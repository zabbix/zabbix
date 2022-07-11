<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * @var CView $this
 * @var array $data
 */

/**
 * Data overview widget form view.
 */
$fields = $data['dialogue']['fields'];

$form = CWidgetHelper::createForm();

$rf_rate_field = ($data['templateid'] === null) ? $fields['rf_rate'] : null;

$form_list = CWidgetHelper::createFormList($data['dialogue']['name'], $data['dialogue']['type'],
	$data['dialogue']['view_mode'], $data['known_widget_types'], $rf_rate_field
);

$scripts = [$this->readJsFile('../../../include/classes/widgets/views/js/widget.tophosts.form.view.js.php')];

// Host groups.
$field_groupids = CWidgetHelper::getGroup($fields['groupids'], $data['captions']['ms']['groups']['groupids'],
	$form->getName()
);
$form_list->addRow(CWidgetHelper::getMultiselectLabel($fields['groupids']), $field_groupids);
$scripts[] = $field_groupids->getPostJS();

// Hosts.
$field_hostids = CWidgetHelper::getHost($fields['hostids'],
	$data['captions']['ms']['hosts']['hostids'],
	$form->getName()
);
$form_list->addRow(CWidgetHelper::getMultiselectLabel($fields['hostids']), $field_hostids);
$scripts[] = $field_hostids->getPostJS();

// Tags.
$form_list->addRow(CWidgetHelper::getLabel($fields['evaltype']), CWidgetHelper::getRadioButtonList($fields['evaltype']));

// Tags filter list.
$form_list->addRow(CWidgetHelper::getLabel($fields['tags']), CWidgetHelper::getTags($fields['tags']));
$scripts[] = $fields['tags']->getJavascript();
$jq_templates['tag-row-tmpl'] = CWidgetHelper::getTagsTemplate($fields['tags']);

// Columns definition table.
$form_list->addRow(CWidgetHelper::getLabel($fields['columns']), CWidgetHelper::getWidgetColumns($fields['columns']));

// Order.
$form_list->addRow(CWidgetHelper::getLabel($fields['order']), CWidgetHelper::getRadioButtonList($fields['order']));

// Order column.
$column = CWidgetHelper::getSelect($fields['column']);

if (!$fields['column']->getValues()) {
	$column = (new CDiv(_('Add item column')))->addClass(
		($fields['column']->getFlags() & CWidgetField::FLAG_DISABLED)
			? ZBX_STYLE_DISABLED
			: null
	);
}

$form_list->addRow(CWidgetHelper::getLabel($fields['column']), $column);

// Hosts count.
$form_list->addRow(CWidgetHelper::getLabel($fields['count']), CWidgetHelper::getIntegerBox($fields['count']));

$form->addItem($form_list);
$form->addItem(
	(new CScriptTag('
		widget_tophosts_form.init('.json_encode([
			'form_id' => $form->getId()
		]).');
	'))->setOnDocumentReady()
);

return [
	'form' => $form,
	'scripts' => $scripts,
	'jq_templates' => $jq_templates
];
