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
 * @var CView $this
 * @var array $data
 */

$this->addJsFile('layout.mode.js');
$this->addJsFile('class.tagfilteritem.js');

$this->includeJsFile('service.list.js.php');

$this->enableLayoutModes();
$web_layout_mode = $this->getLayoutMode();

$breadcrumbs = [];
$filter = null;

if ($web_layout_mode == ZBX_LAYOUT_NORMAL) {
	if (count($data['breadcrumbs']) > 1) {
		while ($path_item = array_shift($data['breadcrumbs'])) {
			$breadcrumbs[] = (new CSpan())
				->addItem(array_key_exists('curl', $path_item)
					? new CLink($path_item['name'], $path_item['curl'])
					: $path_item['name']
				)
				->addClass(!$data['breadcrumbs'] ? ZBX_STYLE_SELECTED : null);
		}
	}

	$filter = (new CFilter())
		->addVar('action', 'service.list')
		->addVar('serviceid', $data['service'] !== null ? $data['service']['serviceid'] : null)
		->setResetUrl($data['reset_curl'])
		->setProfile('web.service.filter')
		->setActiveTab($data['active_tab']);

	if ($data['service'] !== null && !$data['is_filtered']) {
		$filter
			->addTab(
				(new CLink(_('Info'), '#tab_info'))
					->addClass(ZBX_STYLE_BTN)
					->addClass(ZBX_ICON_CIRCLE_INFO),
				(new CDiv())
					->setId('tab_info')
					->addClass(ZBX_STYLE_FILTER_CONTAINER)
					->addItem(new CPartial('service.info', $data + ['is_editable' => false]))
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
			]),
		(new CFormGrid())
			->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
			->addItem([
				new CLabel(_('Tags')),
				new CFormField(
					CTagFilterFieldHelper::getTagFilterField([
						'evaltype' => $data['filter']['evaltype'],
						'tags' => $data['filter']['tags'] ?: [
							['tag' => '', 'value' => '', 'operator' => TAG_OPERATOR_LIKE]
						]
					])
				)
			])
	]);
}

(new CHtmlPage())
	->setTitle(_('Services'))
	->setWebLayoutMode($web_layout_mode)
	->setDocUrl(CDocHelper::getUrl(CDocHelper::SERVICES_SERVICE_LIST))
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(
					$data['can_edit']
						? (new CRadioButtonList('list_mode', ZBX_LIST_MODE_VIEW))
							->addValue(_('View'), ZBX_LIST_MODE_VIEW)
							->addValue(_('Edit'), ZBX_LIST_MODE_EDIT)
							->disableAutocomplete()
							->setModern(true)
						: null
				)
				->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))
		))->setAttribute('aria-label', _('Content controls'))
	)
	->setNavigation(
		$breadcrumbs ? new CList([new CBreadcrumbs($breadcrumbs)]) : null
	)
	->addItem($filter)
	->addItem(new CPartial('service.list', array_intersect_key($data, array_flip(['can_monitor_problems', 'path',
		'is_filtered', 'max_in_table', 'service', 'services', 'events', 'tags', 'paging'
	]))))
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'serviceid' => $data['service'] !== null ? $data['service']['serviceid'] : null,
		'mode_switch_url' => $data['edit_mode_url'],
		'refresh_url' => $data['refresh_url'],
		'refresh_interval' => $data['refresh_interval']
	]).');
'))
	->setOnDocumentReady()
	->show();
