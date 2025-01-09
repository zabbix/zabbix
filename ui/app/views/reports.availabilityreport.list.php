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

$this->addJsFile('gtlc.js');
$this->addJsFile('class.calendar.js');

$this->includeJsFile('reports.availabilityreport.list.js.php');

$html_page = (new CHtmlPage())
	->setTitle(_('Availability report'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::REPORTS_AVAILABILITYREPORT_LIST))
	->setControls(
		(new CTag('nav', true,
			(new CList())->addItem(
				(new CForm('get'))
					->setAttribute('aria-label', _('Main filter'))
					->addVar('action', 'availabilityreport.list')
					->addItem([
						(new CLabel(_('Mode'), 'mode'))->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						(new CSelect('mode'))
							->setId('mode')
							->setValue($data['mode'])
							->addOptions([
								new CSelectOption(AVAILABILITY_REPORT_BY_HOST, _('By host')),
								new CSelectOption(AVAILABILITY_REPORT_BY_TEMPLATE, _('By trigger template'))
							])
							->addClass(ZBX_STYLE_HEADER_Z_SELECT)
					])
			)
		))->setAttribute('aria-label', _('Content controls'))
	);

$filter = (new CFilter())
	->addVar('action', 'availabilityreport.list')
	->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'availabilityreport.list'))
	->setProfile($data['profileIdx'])
	->setActiveTab($data['active_tab'])
	->addTimeSelector($data['timeline']['from'], $data['timeline']['to'], true, $data['timeline']['profileIdx'],
		ZBX_DATE_TIME
	);

if ($data['mode'] == AVAILABILITY_REPORT_BY_TEMPLATE) {
	$filter->addFilterTab(_('Filter'), [
		(new CFormGrid())
			->addItem([
				new CLabel(_('Template group'),'filter_template_groups__ms'),
				new CFormField(
					(new CMultiSelect([
						'name' => 'filter_template_groups[]',
						'object_name' => 'templateGroup',
						'multiple' => false,
						'data' => $data['filter']['template_groups'],
						'popup' => [
							'parameters' => [
								'srctbl' => 'template_groups',
								'srcfld1' => 'groupid',
								'dstfrm' => 'zbx_filter',
								'dstfld1' => 'filter_template_groups_',
								'with_triggers' => true,
								'enrich_parent_groups' => true
							]
						]
					]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				)
			])
			->addItem([
				new CLabel(_('Template'),'filter_templates__ms'),
				new CFormField(
					(new CMultiSelect([
						'name' => 'filter_templates[]',
						'object_name' => 'templates',
						'multiple' => false,
						'data' => $data['filter']['templates'],
						'popup' => [
							'filter_preselect' => [
								'id' => 'filter_template_groups_',
								'submit_as' => 'templategroupid'
							],
							'parameters' => [
								'srctbl' => 'templates',
								'srcfld1' => 'hostid',
								'srcfld2' => 'host',
								'dstfrm' => 'zbx_filter',
								'dstfld1' => 'filter_templates_',
								'editable' => true,
								'with_triggers' => true
							]
						]
					]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				)
			])
			->addItem([
				new CLabel(_('Template trigger'),'filter_triggers__ms'),
				new CFormField(
					(new CMultiSelect([
						'name' => 'filter_triggers[]',
						'object_name' => 'triggers',
						'multiple' => false,
						'data' => $data['filter']['triggers'],
						'popup' => [
							'filter_preselect' => [
								'id' => 'filter_templates_',
								'submit_as' => 'templateid'
							],
							'parameters' => [
								'srctbl' => 'template_triggers',
								'srcfld1' => 'triggerid',
								'dstfrm' => 'zbx_filter',
								'dstfld1' => 'filter_triggers_',
								'with_monitored_triggers' => true
							]
						]
					]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				)
			])
			->addItem([
				new CLabel(_('Host group'), 'filter_host_groups__ms'),
				new CFormField(
					(new CMultiSelect([
						'name' => 'filter_host_groups[]',
						'object_name' => 'hostGroup',
						'multiple' => false,
						'data' => $data['filter']['host_groups'],
						'popup' => [
							'parameters' => [
								'srctbl' => 'host_groups',
								'srcfld1' => 'groupid',
								'dstfrm' => 'zbx_filter',
								'dstfld1' => 'filter_host_groups_',
								'with_monitored_triggers' => true,
								'enrich_parent_groups' => true
							]
						]
					]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				)
			])
	]);
}
else {
	$filter->addFilterTab(_('Filter'), [
		(new CFormGrid())
			->addItem([
				new CLabel(_('Host groups'), 'filter_host_groups__ms'),
				new CFormField(
					(new CMultiSelect([
						'name' => 'filter_host_groups[]',
						'object_name' => 'hostGroup',
						'data' => $data['filter']['host_groups'],
						'popup' => [
							'parameters' => [
								'srctbl' => 'host_groups',
								'srcfld1' => 'groupid',
								'dstfrm' => 'zbx_filter',
								'dstfld1' => 'filter_host_groups_',
								'with_monitored_triggers' => true,
								'enrich_parent_groups' => true
							]
						]
					]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				)
			])
			->addItem([
				new CLabel(_('Hosts'), 'filter_hosts__ms'),
				new CFormField(
					(new CMultiSelect([
						'name' => 'filter_hosts[]',
						'object_name' => 'hosts',
						'data' => $data['filter']['hosts'],
						'popup' => [
							'filter_preselect' => [
								'id' => 'filter_host_groups_',
								'submit_as' => 'groupid'
							],
							'parameters' => [
								'srctbl' => 'hosts',
								'srcfld1' => 'hostid',
								'dstfrm' => 'zbx_filter',
								'dstfld1' => 'filter_hosts_',
								'with_monitored_triggers' => true,
								'real_hosts' => 1
							]
						]
					]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				)
			])
	]);
}

$table = (new CTableInfo())
	->setHeader([_('Host'), _('Name'), _('Problems'), _('Ok'), _('Graph')])
	->setPageNavigation($data['paging']);

foreach ($data['triggers'] as $trigger) {
	$availability = calculateAvailability($trigger['triggerid'], $data['timeline']['from_ts'],
		$data['timeline']['to_ts']
	);

	$table->addRow([
		(new CCol($trigger['host_name']))->addClass(ZBX_STYLE_WORDBREAK),
		$data['can_monitor_problems']
			? (new CCol(
			new CLink($trigger['description'],
				(new CUrl('zabbix.php'))
					->setArgument('action', 'problem.view')
					->setArgument('filter_set', '1')
					->setArgument('triggerids', [$trigger['triggerid']])
			)
		))->addClass(ZBX_STYLE_WORDBREAK)
			: (new CCol($trigger['description']))->addClass(ZBX_STYLE_WORDBREAK),
		$availability['true'] < 0.00005
			? ''
			: (new CSpan(sprintf('%.4f%%', $availability['true'])))->addClass(ZBX_STYLE_RED),
		$availability['false'] < 0.00005
			? ''
			: (new CSpan(sprintf('%.4f%%', $availability['false'])))->addClass(ZBX_STYLE_GREEN),
		new CLink(_('Show'), (new CUrl('zabbix.php'))
			->setArgument('action', 'availabilityreport.trigger')
			->setArgument('triggerid', [$trigger['triggerid']])
		)
	]);
}

$html_page
	->addItem($filter)
	->addItem($table)
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'timeline' => $data['timeline']
	]).');
'))
	->setOnDocumentReady()
	->show();
