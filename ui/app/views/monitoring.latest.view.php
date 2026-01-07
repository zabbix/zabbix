<?php declare(strict_types = 0);
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
 * @var CView $this
 * @var array $data
 */

$this->addJsFile('layout.mode.js');

$this->includeJsFile('monitoring.latest.view.js.php');

$this->enableLayoutModes();
$web_layout_mode = $this->getLayoutMode();

if ($data['uncheck']) {
	uncheckTableRows('latest');
}

$html_page = (new CHtmlPage())
	->setTitle(_('Latest data'))
	->setWebLayoutMode($web_layout_mode)
	->setDocUrl(CDocHelper::getUrl(CDocHelper::MONITORING_LATEST_VIEW))
	->setControls(
		(new CTag('nav', true, (new CList())->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))))
			->setAttribute('aria-label', _('Content controls'))
	);


$filter = (new CTabFilter())
	->setId('monitoring_latest_filter')
	->setOptions($data['tabfilter_options'])
	->addTemplate(new CPartial($data['filter_view'], $data['filter_defaults']));

if ($data['mandatory_filter_set'] && $data['items'] || $data['subfilter_set']) {
	$filter->addSubfilter(new CPartial('monitoring.latest.subfilter',
		array_intersect_key($data, array_flip(['subfilters', 'subfilters_expanded'])))
	);
}

foreach ($data['filter_tabs'] as $tab) {
	$tab['tab_view'] = $data['filter_view'];
	$filter->addTemplatedTab($tab['filter_name'], $tab);
}

// Set javascript options for tab filter initialization in monitoring.latest.view.js.php file.
$data['filter_options'] = $filter->options;

$button_list = [
	GRAPH_TYPE_STACKED => [
		'name' => _('Display stacked graph'),
		'attributes' => ['data-required' => 'graph', 'data-required-count' => 2]
	],
	GRAPH_TYPE_NORMAL => [
		'name' => _('Display graph'),
		'attributes' => ['data-required' => 'graph']
	],
	'item.execute' => [
		'content' => (new CSimpleButton(_('Execute now')))
			->addClass(ZBX_STYLE_BTN_ALT)
			->addClass('js-massexecute-item')
			->addClass('js-no-chkbxrange')
			->setAttribute('data-required', 'execute')
	]
];

$html_page
	->addItem($filter)
	->addItem(
		new CPartial('monitoring.latest.view.html', array_intersect_key($data,
			array_flip(['filter', 'sort_field', 'sort_order', 'view_curl', 'paging', 'hosts', 'items', 'history',
				'config', 'tags', 'maintenances', 'items_rw', 'mandatory_filter_set', 'subfilter_set'
			])
		))
	)
	->show();

(new CTemplateTag('name'))
	->addItem([
		new CFormField(
			(new CCheckBox('show_item_key'))
				->setLabel(_('Show item key'))
				->setLabelPosition(CCheckBox::LABEL_POSITION_RIGHT)
		)
	])
	->show();

(new CTemplateTag('tags'))
	->addItem([
		(new CLabel(_('Number of tags'), 'number_of_tags'))
			->addClass('form-label'),
		new CFormField(
			(new CRadioButtonList('number_of_tags'))
				->setValues([
					['name' => SHOW_TAGS_1, 'value' => SHOW_TAGS_1],
					['name' => SHOW_TAGS_2, 'value' => SHOW_TAGS_2],
					['name' => SHOW_TAGS_3, 'value' => SHOW_TAGS_3],
				])
				->setModern(),
		),
		(new CLabel(_('Tag name display'), 'tag_name_display'))
			->addClass('form-label'),
		new CFormField(
			(new CRadioButtonList('tag_name_display'))
				->setValues([
					['name' => _('Full'), 'value' => TAG_NAME_FULL],
					['name' => _('Shortened'), 'value' => TAG_NAME_SHORTENED],
					['name' => _('None'), 'value' => TAG_NAME_NONE],
				])
				->setModern(),
		),
		(new CLabel(_('Tag display priority'), 'tag_display_priority'))
			->addClass('form-label'),
		new CFormField(new CTextBox('tag_display_priority')),
	])
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'filter_options' => $data['filter_options'],
		'refresh_interval' => $data['refresh_interval'],
		'filter_defaults' => $data['filter_defaults'],
		'checkbox_object' => 'itemids',
		'filter_set' => $data['mandatory_filter_set'] || $data['subfilter_set'],
		'layout_mode' => $web_layout_mode,
		'filter' => $data['filter'],
		'page' => $data['tabfilter_options']['page'],
		'sort_field' => $data['sort_field'],
		'sort_order' => $data['sort_order'],
		'storage_idx' => $data['storage_idx'],
		'user_configs' => $data['user_configs'],
	]).');
'))
	->setOnDocumentReady()
	->show();
