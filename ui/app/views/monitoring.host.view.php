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
 * @var CView $this
 * @var array $data
 */

$this->addJsFile('layout.mode.js');

$this->includeJsFile('monitoring.host.view.js.php');

$this->enableLayoutModes();
$web_layout_mode = $this->getLayoutMode();
$nav_items = new CList();

if ($data['can_create_hosts']) {
	$nav_items->addItem(
		(new CSimpleButton(_('Create host')))
			->addClass('js-create-host')
	);
}

$nav_items->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]));

$html_page = (new CHtmlPage())
	->setTitle(_('Hosts'))
	->setWebLayoutMode($web_layout_mode)
	->setDocUrl(CDocHelper::getUrl(CDocHelper::MONITORING_HOST_VIEW))
	->setControls((new CTag('nav', true, $nav_items))
		->setAttribute('aria-label', _('Content controls'))
	);

$filter = (new CTabFilter())
	->setId('monitoring_hosts_filter')
	->setOptions($data['tabfilter_options'])
	->addTemplate(new CPartial($data['filter_view'], $data['filter_defaults']));

if ($web_layout_mode == ZBX_LAYOUT_KIOSKMODE) {
	$filter->setAttribute('hidden', '');
}

foreach ($data['filter_tabs'] as $tab) {
	$tab['tab_view'] = $data['filter_view'];
	$filter->addTemplatedTab($tab['filter_name'], $tab);
}

// Set javascript options for tab filter initialization in monitoring.host.view.js.php file.
$data['filter_options'] = $filter->options;

$html_page
	->addItem($filter)
	->addItem(
		(new CForm())
			->setName('host_view')
			->addItem((new CDiv())->setId('hosts'))
	)
	->show();

(new CTemplateTag('problems'))
	->addItem([
		new CFormField(
			(new CCheckBox('show_suppressed'))
				->setLabel(_('Show suppressed problems'))
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
					['name' => SHOW_TAGS_3, 'value' => SHOW_TAGS_3]
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
					['name' => _('None'), 'value' => TAG_NAME_NONE]
				])
				->setModern(),
		),
		(new CLabel(_('Tag display priority'), 'tag_display_priority'))
			->addClass('form-label'),
		new CFormField(new CTextBox('tag_display_priority'))
	])
	->show();

(new CTemplateTag('tagvalue'))
	->addItem([
		(new CLabel(_('Tag name'), 'tag_name'))
			->addClass('form-label'),
		new CFormField(new CTextBox('tag_name'))
	])
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'layout_mode' => $web_layout_mode,
		'filter_options' => $data['filter_options'],
		'refresh_interval' => $data['refresh_interval'],
		'applied_filter_groupids' => $data['filter_groupids'],
		'filter_defaults' => $data['filter_defaults'],
		'filter' => $data['filter'],
		'page' => $data['tabfilter_options']['page'],
		'sort_field' => $data['sort_field'],
		'sort_order' => $data['sort_order'],
		'storage_idx' => $data['storage_idx'],
		'user_configs' => $data['user_configs']
	]).');
'))
	->setOnDocumentReady()
	->show();
