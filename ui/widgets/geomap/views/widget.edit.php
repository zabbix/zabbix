<?php declare(strict_types = 0);
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
 * Geomap widget form view.
 *
 * @var CView $this
 * @var array $data
 */

$form = new CWidgetFormView($data);

$groupids = array_key_exists('groupids', $data['fields'])
	? new CWidgetFieldMultiSelectGroupView($data['fields']['groupids'])
	: null;

$clustering_mode_field = new CWidgetFieldRadioButtonListView($data['fields']['clustering_mode']);
$clustering_zoom_level_field = (new CWidgetFieldIntegerBoxView($data['fields']['clustering_zoom_level']));

$form->registerField($clustering_mode_field);
$form->registerField($clustering_zoom_level_field);

$form
	->addField($groupids)
	->addField(array_key_exists('hostids', $data['fields'])
		? (new CWidgetFieldMultiSelectHostView($data['fields']['hostids']))
			->setFilterPreselect([
				'id' => $groupids->getId(),
				'accept' => CMultiSelect::FILTER_PRESELECT_ACCEPT_ID,
				'submit_as' => 'groupid'
			])
		: null
	)
	->addField(array_key_exists('evaltype', $data['fields'])
		? new CWidgetFieldRadioButtonListView($data['fields']['evaltype'])
		: null
	)
	->addField(array_key_exists('tags', $data['fields'])
		? new CWidgetFieldTagsView($data['fields']['tags'])
		: null
	)
	->addField(
		(new CWidgetFieldLatLngView($data['fields']['default_view']))->setPlaceholder('40.6892494,-74.0466891')
	)
	->addItem([
		new CLabel(_('Clustering')),
		(new CDiv())
			->addItem($clustering_mode_field->getView())
			->addItem($clustering_zoom_level_field->getView()->addClass('js-zoom-level-field'))
			->addClass('fields-group-clustering')
	])
	->includeJsFile('widget.edit.js.php')
	->initFormJs('widget_form.init();')
	->show();
