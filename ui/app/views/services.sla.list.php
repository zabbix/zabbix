<?php declare(strict_types = 1);
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @var CView $this
 * @var array $data
 */

$this->addJsFile('layout.mode.js');
$this->addJsFile('class.tagfilteritem.js');

$this->includeJsFile('services.sla.list.js.php');

$this->enableLayoutModes();
$web_layout_mode = $this->getLayoutMode();

$filter = null;

if ($web_layout_mode == ZBX_LAYOUT_NORMAL) {
	$filter = (new CFilter())
		->addVar('action', 'sla.list')
		->setResetUrl($data['reset_curl'])
		->setProfile('web.sla.filter')
		->setActiveTab($data['active_tab']);

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
						->addValue(_('Any'), CSlaHelper::SLA_STATUS_ANY)
						->addValue(_('Enabled'), CSlaHelper::SLA_STATUS_ENABLED)
						->addValue(_('Disabled'), CSlaHelper::SLA_STATUS_DISABLED)
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

(new CWidget())
	->setTitle(_('SLA'))
	->setWebLayoutMode($web_layout_mode)
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem($filter)
	->addItem(new CPartial('services.sla.list', $data))
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'mode_switch_url' => $data['edit_mode_url'],
		'refresh_url' => $data['refresh_url'],
		'refresh_interval' => $data['refresh_interval']
	]).');
'))
	->setOnDocumentReady()
	->show();
