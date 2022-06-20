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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @var CView $this
 * @var array $data
 */

$this->addJsFile('layout.mode.js');
$this->addJsFile('class.tagfilteritem.js');
$this->addJsFile('class.calendar.js');

$this->includeJsFile('service.list.js.php');

$breadcrumbs = [];
$filter = null;

if (count($data['breadcrumbs']) > 1) {
	foreach ($data['breadcrumbs'] as $index => $path_item) {
		$breadcrumbs[] = (new CSpan())
			->addItem(array_key_exists('curl', $path_item)
				? new CLink($path_item['name'], $path_item['curl'])
				: $path_item['name']
			)
			->addClass($index == count($data['breadcrumbs']) - 1 ? ZBX_STYLE_SELECTED : null);
	}
}

$filter = (new CFilter())
	->addVar('action', 'service.list.edit')
	->addVar('serviceid', $data['service'] !== null ? $data['service']['serviceid'] : null)
	->setResetUrl($data['reset_curl'])
	->setProfile('web.service.filter')
	->setActiveTab($data['active_tab']);

if ($data['service'] !== null && !$data['is_filtered']) {
	$filter
		->addTab(
			(new CLink(_('Info'), '#tab_info'))->addClass(ZBX_STYLE_BTN_INFO),
			(new CDiv())
				->setId('tab_info')
				->addClass(ZBX_STYLE_FILTER_CONTAINER)
				->addItem(new CPartial('service.info', $data + ['is_editable' => true]))
		);
}

$filter->addFilterTab(_('Filter'), [
	(new CFormGrid())
		->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
		->addItem([
			new CLabel(_('Name'), 'filter_name'),
			new CFormField(
				(new CTextBox('filter_name', $data['filter']['name']))
					->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			)
		])
		->addItem([
			new CLabel(_('Status')),
			new CFormField(
				(new CRadioButtonList('filter_status', (int) $data['filter']['status']))
					->addValue(_('Any'), SERVICE_STATUS_ANY)
					->addValue(_('OK'), SERVICE_STATUS_OK)
					->addValue(_('Problem'), SERVICE_STATUS_PROBLEM)
					->setModern(true)
			)
		])
		->addItem([
			new CLabel(_('Only services without children'), 'filter_without_children'),
			new CFormField(
				(new CCheckBox('filter_without_children'))
					->setUncheckedValue(0)
					->setChecked($data['filter']['without_children'])
			)
		])
		->addItem([
			new CLabel(_('Only services without problem tags'), 'filter_without_problem_tags'),
			new CFormField(
				(new CCheckBox('filter_without_problem_tags'))
					->setUncheckedValue(0)
					->setChecked($data['filter']['without_problem_tags'])
			)
		]),
	(new CFormGrid())
		->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
		->addItem([
			new CLabel(_('Tags')),
			new CFormField([
				(new CRadioButtonList('filter_tag_source', (int) $data['filter']['tag_source']))
					->addValue(_('Any'), ZBX_SERVICE_FILTER_TAGS_ANY)
					->addValue(_('Service'), ZBX_SERVICE_FILTER_TAGS_SERVICE)
					->addValue(_('Problem'), ZBX_SERVICE_FILTER_TAGS_PROBLEM)
					->setModern(true)
					->addStyle('margin-bottom: 10px;'),
				CTagFilterFieldHelper::getTagFilterField([
					'evaltype' => $data['filter']['evaltype'],
					'tags' => $data['filter']['tags'] ?: [
						['tag' => '', 'value' => '', 'operator' => ZBX_SERVICE_PROBLEM_TAG_OPERATOR_LIKE]
					]
				])
			])
		])
]);

(new CWidget())
	->setTitle(_('Services'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::SERVICE_LIST_EDIT))
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(
					(new CSimpleButton(_('Create service')))
						->addClass('js-create-service')
						->setAttribute('data-serviceid', $data['service'] !== null
							? $data['service']['serviceid']
							: null
						)
				)
				->addItem(
					(new CRadioButtonList('list_mode', ZBX_LIST_MODE_EDIT))
						->addValue(_('View'), ZBX_LIST_MODE_VIEW)
						->addValue(_('Edit'), ZBX_LIST_MODE_EDIT)
						->disableAutocomplete()
						->setModern(true)
				)
		))->setAttribute('aria-label', _('Content controls'))
	)
	->setNavigation(
		$breadcrumbs ? new CList([new CBreadcrumbs($breadcrumbs)]) : null
	)
	->addItem($filter)
	->addItem(new CPartial('service.list.edit', array_intersect_key($data, array_flip([
		'can_monitor_problems', 'path', 'is_filtered', 'max_in_table', 'service', 'services', 'events', 'tags',
		'paging', 'back_url'
	]))))
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'serviceid' => $data['service'] !== null ? $data['service']['serviceid'] : null,
		'path' => $data['path'],
		'is_filtered' => $data['is_filtered'],
		'mode_switch_url' => $data['view_mode_url'],
		'parent_url' => $data['parent_url'],
		'refresh_url' => $data['refresh_url'],
		'refresh_interval' => $data['refresh_interval'],
		'back_url' => $data['back_url']
	]).');
'))
	->setOnDocumentReady()
	->show();
