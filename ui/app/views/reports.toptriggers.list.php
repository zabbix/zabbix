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
$this->addJsFile('class.tagfilteritem.js');
$this->addJsFile('items.js');
$this->addJsFile('multilineinput.js');

$this->includeJsFile('reports.toptriggers.list.js.php');

$filter = (new CFilter())
	->addVar('action', 'toptriggers.list')
	->setResetUrl(
		(new CUrl('zabbix.php'))->setArgument('action', 'toptriggers.list')
	)
	->setProfile($data['filter']['timeline']['profileIdx'])
	->setActiveTab($data['filter']['active_tab'])
	->addTimeSelector($data['filter']['timeline']['from'], $data['filter']['timeline']['to'], true,
		'web.toptriggers.filter', ZBX_DATE_TIME
	)
	->addFilterTab(_('Filter'), [
		(new CFormGrid())
			->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
			->addItem([
				new CLabel(_('Host groups'), 'filter_groupids__ms'),
				new CFormField(
					(new CMultiSelect([
						'name' => 'filter_groupids[]',
						'object_name' => 'hostGroup',
						'data' => $data['filter']['groups'],
						'popup' => [
							'parameters' => [
								'srctbl' => 'host_groups',
								'srcfld1' => 'groupid',
								'dstfrm' => 'zbx_filter',
								'dstfld1' => 'filter_groupids_',
								'with_hosts' => true,
								'enrich_parent_groups' => true
							]
						]
					]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				)
			])
			->addItem([
				new CLabel(_('Hosts'), 'filter_hostids__ms'),
				new CFormField(
					(new CMultiSelect([
						'name' => 'filter_hostids[]',
						'object_name' => 'hosts',
						'data' => $data['filter']['hosts'],
						'popup' => [
							'filter_preselect' => [
								'id' => 'filter_groupids_',
								'submit_as' => 'groupid'
							],
							'parameters' => [
								'srctbl' => 'hosts',
								'srcfld1' => 'hostid',
								'dstfrm' => 'zbx_filter',
								'dstfld1' => 'filter_hostids_'
							]
						]
					]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				)
			])
			->addItem([
				new CLabel(_('Problem'), 'filter_problem'),
				new CFormField(
					(new CTextBox('filter_problem', $data['filter']['problem']))
						->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				)
			])
			->addItem([
				new CLabel(_('Severity')),
				new CFormField(
					(new CCheckBoxList('filter_severities'))
						->setOptions(CSeverityHelper::getSeverities())
						->setChecked($data['filter']['severities'])
						->setColumns(3)
						->setVertical()
				)
			]),
		(new CFormGrid())
			->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
			->addItem([
				new CLabel(_('Problem tags')),
				new CFormField([
					CTagFilterFieldHelper::getTagFilterField([
						'evaltype' => $data['filter']['evaltype'],
						'tags' => $data['filter']['tags'] ?: [
							['tag' => '', 'operator' => TAG_OPERATOR_LIKE, 'value' => '']
						]
					])
				])
			])
	]);

$table = (new CTableInfo())->setHeader([_('Host'), _('Trigger'), _('Severity'), _('Number of problems')]);

foreach ($data['triggers'] as $triggerid => $trigger) {
	$hosts = [];

	foreach ($trigger['hosts'] as $host) {
		$hosts[] = (new CLinkAction($host['name']))
			->addClass(ZBX_STYLE_WORDBREAK)
			->addClass($host['status'] == HOST_STATUS_NOT_MONITORED ? ZBX_STYLE_RED : null)
			->setMenuPopup(CMenuPopupHelper::getHost($host['hostid']));
		$hosts[] = ', ';
	}

	array_pop($hosts);

	$table->addRow([
		(new CCol($hosts))->addClass(ZBX_STYLE_WORDBREAK),
		(new CCol(
			(new CLinkAction($trigger['description']))->setMenuPopup(
				CMenuPopupHelper::getTrigger([
					'triggerid' => $trigger['triggerid'],
					'backurl' => (new CUrl('zabbix.php'))
						->setArgument('action', 'toptriggers.list')
						->getUrl()
				])
			)
		))->addClass(ZBX_STYLE_WORDBREAK),
		CSeverityHelper::makeSeverityCell((int) $trigger['priority']),
		$trigger['problem_count']
	]);
}

(new CHtmlPage())
	->setTitle(_('Top 100 triggers'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::REPORTS_TOPTRIGGERS))
	->addItem($filter)
	->addItem($table)
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'timeline' => $data['filter']['timeline']
	]).');
'))
	->setOnDocumentReady()
	->show();
