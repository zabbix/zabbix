<?php declare(strict_types = 0);
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * Graph prototype widget form view.
 *
 * @var CView $this
 * @var array $data
 */

if (array_key_exists('itemid', $data['fields'])) {
	$field_itemid = (new CWidgetFieldMultiSelectItemPrototypeView($data['fields']['itemid'],
		$data['captions']['ms']['item_prototypes']['itemid'])
	)->setPopupParameter('numeric', true);

	if (!$data['fields']['itemid']->isTemplateDashboard()) {
		$field_itemid->setPopupParameter('with_simple_graph_item_prototypes', true);
	}
}
else {
	$field_itemid = null;
}

(new CWidgetFormView($data))
	->addField(
		new CWidgetFieldRadioButtonListView($data['fields']['source_type'])
	)
	->addField($field_itemid)
	->addField(array_key_exists('graphid', $data['fields'])
		? new CWidgetFieldMultiSelectGraphPrototypeView($data['fields']['graphid'],
			$data['captions']['ms']['graph_prototypes']['graphid']
		)
		: null
	)
	->addField(
		new CWidgetFieldCheckBoxView($data['fields']['show_legend'])
	)
	->addField(array_key_exists('dynamic', $data['fields'])
		? new CWidgetFieldCheckBoxView($data['fields']['dynamic'])
		: null
	)
	->addField(
		new CWidgetFieldIntegerBoxView($data['fields']['columns'])
	)
	->addField(
		new CWidgetFieldIntegerBoxView($data['fields']['rows'])
	)
	->show();
