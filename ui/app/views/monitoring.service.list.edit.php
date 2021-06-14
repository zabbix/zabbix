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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @var CView $this
 */

$this->addJsFile('layout.mode.js');
$this->addJsFile('class.tagfilteritem.js');
$this->addJsFile('class.calendar.js');
$this->addJsFile('multiselect.js');
$this->addJsFile('textareaflexible.js');
$this->addJsFile('class.tab-indicators.js');

$this->includeJsFile('monitoring.service.list.js.php');

$this->enableLayoutModes();
$web_layout_mode = $this->getLayoutMode();

$filter = ($web_layout_mode == ZBX_LAYOUT_NORMAL)
	? (new CFilter($data['view_curl']))
		->setProfile('web.service.filter')
		->setActiveTab($data['active_tab'])
		->addFilterTab(_('Filter'), [
			(new CFormGrid())
				->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
				->addItem([
					new CLabel(_('Name'), 'filter_select'),
					new CFormField(
						(new CTextBox('filter_select', $data['filter']['name']))
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
		])
	: null;

$form = (new CForm())->setName('service_form');

$table = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_services'))->onClick("checkAll('".$form->getName()."', 'all_services', 'serviceids');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		(new CColHeader(_('Name')))->addStyle('width: 25%'),
		(new CColHeader(_('Status')))->addStyle('width: 14%'),
		(new CColHeader(_('Root cause')))->addStyle('width: 24%'),
		(new CColHeader(_('SLA')))->addStyle('width: 14%'),
		(new CColHeader(_('Tags')))->addClass(ZBX_STYLE_COLUMN_TAGS_3),
		(new CColHeader())
	]);

foreach ($data['services'] as $serviceid => $service) {
	$dependencies_count = count($service['dependencies']);

	$table->addRow(new CRow([
		new CCheckBox('serviceids['.$serviceid.']', $serviceid),
		$dependencies_count > 0
			? [
				(new CLink($service['name'], (new CUrl('zabbix.php'))
					->setArgument('action', 'service.list.edit')
					->setArgument('serviceid', $serviceid)
				))->setAttribute('data-serviceid', $serviceid),
				CViewHelper::showNum($dependencies_count)
			]
			: $service['name'],
		in_array($service['status'], [TRIGGER_SEVERITY_INFORMATION, TRIGGER_SEVERITY_NOT_CLASSIFIED])
			? (new CCol(_('OK')))->addClass(ZBX_STYLE_GREEN)
			: (new CCol(getSeverityName($service['status'])))->addClass(getSeverityStyle($service['status'])),
		'',
		sprintf('%.4f', $service['goodsla']),
		array_key_exists($serviceid, $data['tags']) ? $data['tags'][$serviceid] : 'tags',
		(new CCol([
			(new CButton(null))
				->addClass(ZBX_STYLE_BTN_ADD)
				->addClass('js-service-add-child')
				->setAttribute('data-serviceid', $serviceid),
			(new CButton(null))
				->addClass(ZBX_STYLE_BTN_EDIT)
				->addClass('js-service-edit')
				->setAttribute('data-serviceid', $serviceid),
			(new CButton(null))
				->addClass(ZBX_STYLE_BTN_REMOVE)
				->addClass('js-service-remove')
				->setAttribute('data-serviceid', $serviceid)
		]))->addClass(ZBX_STYLE_LIST_TABLE_ACTIONS)
	]));
}

(new CWidget())
	->setTitle(_('Services'))
	->setWebLayoutMode($web_layout_mode)
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(
					(new CSimpleButton(_('Create service')))
						->addClass('js-service-create')
						->setAttribute('data-serviceid', $data['serviceid'])
				)
				->addItem(
					(new CRadioButtonList('list_mode', ZBX_LIST_MODE_EDIT))
						->addValue(_('View'), ZBX_LIST_MODE_VIEW)
						->addValue(_('Edit'), ZBX_LIST_MODE_EDIT)
						->setModern(true)
				)
				->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem([
		$filter,
		$form->addItem([
			$table,
			$data['paging'],
			new CActionButtonList('action', 'serviceids', [
				'popup.massupdate.service' => [
					'content' => (new CButton('', _('Mass update')))
						->onClick("return openMassupdatePopup(this, 'popup.massupdate.service');")
						->addClass(ZBX_STYLE_BTN_ALT)
						->removeAttribute('id')
				],
				'service.delete' => ['name' => _('Delete'), 'confirm' => _('Delete selected services?')]
			])
		])
	])
	->show();

(new CScriptTag('
	initializeView(
		'.json_encode($data['serviceid']).',
		null
	);
'))
	->setOnDocumentReady()
	->show();
