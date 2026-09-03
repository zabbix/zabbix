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

$this->addJsFile('gtlc.js');
$this->addJsFile('layout.mode.js');

$this->includeJsFile('apm.log.list.js.php');

$this->enableLayoutModes();
$web_layout_mode = $this->getLayoutMode();

$csrf_token = CCsrfTokenHelper::get('apm');
$reset_url  = (new CUrl('zabbix.php'))->setArgument('action', $data['action']);

if (!array_key_exists('attributes', $data['filter']) || !$data['filter']['attributes']) {
	$data['filter']['attributes'] = [['key' => '', 'value' => '', 'operator' => 0]];
}

$filter = (new CFilter())
	->setId('apm_log_filter')
	->setResetUrl($reset_url)
	->setProfile($data['filter_options']['idx'])
	->setActiveTab($data['active_tab'])
	->addVar('action', $data['action'], 'filter_action')
	->addTimeSelector(
		$data['filter_options']['timeselector']['from'],
		$data['filter_options']['timeselector']['to'],
		$web_layout_mode != ZBX_LAYOUT_KIOSKMODE
	)
	->addFilterTab(_('Filter'), [
		(new CFormGrid())
			->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
			->addItem([
				(new CLabel(_('Body'), 'filter_body')),
				new CFormField(
					(new CTextBox('filter_body', $data['filter']['body']))
						->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				)
			])
			->addItem([
				(new CLabel(_('Trace ID'), 'filter_traceid')),
				new CFormField(
					(new CTextBox('filter_traceid', $data['filter']['traceid']))
						->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				)
			])
			->addItem([
				(new CLabel(_('Span ID'), 'filter_spanid')),
				new CFormField(
					(new CTextBox('filter_spanid', $data['filter']['spanid']))
						->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				)
			])
			->addItem([
				(new CLabel(_('Service name'), 'filter_service_name')),
				new CFormField(
					(new CTextBox('filter_service_name', $data['filter']['service_name']))
						->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				)
			])
			->addItem([
				(new CLabel(_('Scope name'), 'filter_scope_name')),
				new CFormField(
					(new CTextBox('filter_scope_name', $data['filter']['scope_name']))
						->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				)
			]),
		(new CFormGrid())
			->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
			->addItem([
				(new CLabel(_('Severity'), 'filter_severities')),
				new CFormField(
					(new CCheckBoxList('filter_severities'))
						->setUniqid('#{uniqid}')
						->setOptions(CApmLogHelper::getSeverities())
						->setChecked($data['filter']['severities'])
						->setColumns(3)
						->setVertical()
						->showTitles()
				)
			])
			->addItem([
				(new CLabel(_('Attributes'), 'filter_attributes')),
				new CFormField(
					CApmAttrFilterFieldHelper::getFilterField([
						'evaltype' => $data['filter']['evaltype'],
						'attributes' => $data['filter']['attributes']
					])
				)
			])
	]);

$form = (new CForm())
	->setName('logs')
	->addItem(
		(new CDataTable())->setId('datatable-logs')
	);

$html_page = (new CHtmlPage())
	->setTitle(_('Logs'))
	->setWebLayoutMode($web_layout_mode)
	->setDocUrl(CDocHelper::getUrl(CDocHelper::APM_LOG_VIEW))
	->setControls(
		(new CTag('nav', true, (new CList())->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))))
			->setAttribute('aria-label', _('Content controls'))
	)
	->addItem([$filter, $form]);

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	$html_page->addItem((new CPre())->addClass(ZBX_STYLE_DEBUG_OUTPUT_TABLE_REFRESH));
}

$html_page->show();

(new CTemplateTag('filter-attributes-row-tmpl'))
	->addItem(
		CApmAttrFilterFieldHelper::getFilterFieldRow('#{rowNum}', [
			'key' => '#{key}',
			'value' => '#{value}',
			'operator' => 0
		])
	)
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'csrf_token' => $csrf_token,
		'default_sort_field' => $data['default_sort_field'],
		'default_sort_order' => $data['default_sort_order'],
		'filter' => $data['filter'],
		'filter_options' => $data['filter_options'],
		'layout_mode' => $web_layout_mode,
		'page' => $data['page'],
		'refresh_interval' => $data['refresh_interval'],
		'sort_field' => $data['sort_field'],
		'sort_order' => $data['sort_order'],
		'storage_idx' => $data['storage_idx'],
		'user_configs' => $data['user_configs']
	]).');
'))
	->setOnDocumentReady()
	->show();
