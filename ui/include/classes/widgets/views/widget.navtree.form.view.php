<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 * Map navigation tree widget form view.
 *
 * @var CView $this
 * @var array $data
 */

$fields = $data['dialogue']['fields'];

$form = CWidgetHelper::createForm();

$form_grid = CWidgetHelper::createFormGrid($data['dialogue']['name'], $data['dialogue']['type'],
	$data['dialogue']['view_mode'], $data['known_widget_types'],
	$data['templateid'] === null ? $fields['rf_rate'] : null
);

// Map widget reference.
$form->addItem(
	(new CVar($fields[CWidgetFieldReference::FIELD_NAME]->getName(),
		$fields[CWidgetFieldReference::FIELD_NAME]->getValue()
	))->removeId()
);

// Add dynamically created fields navtree.name.<N>, navtree.parent.<N>, navtree.order.<N> and navtree.sysmapid.<N>.
foreach ($fields['navtree']->getValue() as $i => $navtree_item) {
	$form->addItem((new CVar($fields['navtree']->getName().'.name.'.$i, $navtree_item['name']))->removeId());

	if ($navtree_item['order'] != 1) {
		$form->addItem((new CVar($fields['navtree']->getName().'.order.'.$i, $navtree_item['order']))->removeId());
	}
	if ($navtree_item['parent'] != 0) {
		$form->addItem((new CVar($fields['navtree']->getName().'.parent.'.$i, $navtree_item['parent']))->removeId());
	}
	if (array_key_exists('sysmapid', $navtree_item)) {
		$form->addItem(
			(new CVar($fields['navtree']->getName().'.sysmapid.'.$i, $navtree_item['sysmapid']))->removeId()
		);
	}
}

// Show unavailable maps.
$form_grid->addItem([
	CWidgetHelper::getLabel($fields['show_unavailable']),
	new CFormField(CWidgetHelper::getCheckBox($fields['show_unavailable']))
]);

$form->addItem($form_grid);

return [
	'form' => $form
];
