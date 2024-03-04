<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

$this->includeJsFile('administration.proxygroup.list.js.php');

$filter = (new CFilter())
	->addVar('action', 'proxygroup.list')
	->setResetUrl(
		(new CUrl('zabbix.php'))->setArgument('action', 'proxygroup.list')
	)
	->setProfile($data['profileIdx'])
	->setActiveTab($data['active_tab'])
	->addFilterTab(_('Filter'), [
		(new CFormGrid())
			->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
			->addItem([
				new CLabel(_('Name'), 'filter_name'),
				new CFormField(
					(new CTextBox('filter_name', $data['filter']['name']))
						->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
						->setAttribute('autofocus', 'autofocus')
				)
			]),
		(new CFormGrid())
			->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
			->addItem([
				new CLabel(_('State')),
				new CFormField(
					(new CRadioButtonList('filter_state', (int) $data['filter']['state']))
						->addValue(_('Any'), -1)
						->addValue(_('Online'), ZBX_PROXYGROUP_STATE_ONLINE)
						->addValue(_('Degrading'), ZBX_PROXYGROUP_STATE_DEGRADING)
						->addValue(_('Offline'), ZBX_PROXYGROUP_STATE_OFFLINE)
						->addValue(_('Recovering'), ZBX_PROXYGROUP_STATE_RECOVERING)
						->setModern()
				)
			])
	]);

$form = (new CForm())
	->addItem((new CVar(CCsrfTokenHelper::CSRF_TOKEN_NAME, CCsrfTokenHelper::get('proxygroup')))->removeId())
	->setId('proxy-group-list')
	->setName('proxy_group_list');

$view_url = (new CUrl('zabbix.php'))
	->setArgument('action', 'proxygroup.list')
	->getUrl();

$header = [
	(new CColHeader(
		(new CCheckBox('all_proxy_groups'))
			->onClick("checkAll('proxy_group_list', 'all_proxy_groups', 'proxy_groupids');")
	))->addClass(ZBX_STYLE_CELL_WIDTH),
	make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $view_url),
	new CColHeader(_('State')),
	new CColHeader(_('Failover period')),
	new CColHeader(_('Online proxies')),
	new CColHeader(_('Minimum proxies')),
	(new CColHeader(_('Proxies')))->setColSpan(2)
];

$proxy_group_list = (new CTableInfo())->setHeader($header);

foreach ($data['proxy_groups'] as $proxy_groupid => $proxy_group) {
	$state = '';
	$proxies = [];
	$proxy_count_total = '';

	if ($proxy_group['proxies']) {
		switch ($proxy_group['state']) {
			case ZBX_PROXYGROUP_STATE_UNKNOWN:
				$state = (new CSpan(_('Unknown')))->addClass(ZBX_STYLE_STATUS_GREY);
				break;

			case ZBX_PROXYGROUP_STATE_OFFLINE:
				$state = (new CSpan(_('Offline')))->addClass(ZBX_STYLE_STATUS_RED);
				break;

			case ZBX_PROXYGROUP_STATE_RECOVERING:
				$state = (new CSpan(_('Recovering')))->addClass(ZBX_STYLE_STATUS_YELLOW);
				break;

			case ZBX_PROXYGROUP_STATE_ONLINE:
				$state = (new CSpan(_('Online')))->addClass(ZBX_STYLE_STATUS_GREEN);
				break;

			case ZBX_PROXYGROUP_STATE_DEGRADING:
				$state = (new CSpan(_('Degrading')))->addClass(ZBX_STYLE_STATUS_YELLOW);
				break;
		}

		foreach ($proxy_group['proxies'] as $proxy) {
			$proxies[] = $data['user']['can_edit_proxies']
				? (new CLink($proxy['name']))
					->addClass('js-edit-proxy')
					->setAttribute('data-proxyid', $proxy['proxyid'])
				: $proxy['name'];
			$proxies[] = ', ';
		}

		array_pop($proxies);

		if ($proxy_group['proxy_count_total'] > count($proxy_group['proxies'])) {
			$proxies[] = [' ', HELLIP()];
		}

		$proxy_count_total = (new CSpan($proxy_group['proxy_count_total']))->addClass(ZBX_STYLE_ENTITY_COUNT);
	}

	$proxy_group_list->addRow([
		new CCheckBox('proxy_groupids['.$proxy_groupid.']', $proxy_groupid),
		(new CCol(
			(new CLink($proxy_group['name']))
				->addClass('js-edit-proxy-group')
				->setAttribute('data-proxy_groupid', $proxy_groupid)
		))->addClass(ZBX_STYLE_WORDBREAK),
		$state,
		$proxy_group['failover_delay'],
		$proxy_group['proxy_count_online'],
		$proxy_group['min_online'],
		(new CCol($proxy_count_total))->addClass(ZBX_STYLE_CELL_WIDTH),
		$proxies
	]);
}

$form
	->addItem([$proxy_group_list, $data['paging']])
	->addItem(
		new CActionButtonList('action', 'proxy_groupids', [
			'proxygroup.massdelete' => [
				'content' => (new CSimpleButton(_('Delete')))
					->addClass(ZBX_STYLE_BTN_ALT)
					->addClass('js-massdelete-proxy-group')
					->addClass('js-no-chkbxrange')
			]
		], 'proxygroup')
	);

(new CHtmlPage())
	->setTitle(_('Proxy groups'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_PROXY_GROUP_LIST))
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(
					(new CSimpleButton(_('Create proxy group')))->addClass('js-create-proxy-group')
				)
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem($filter)
	->addItem($form)
	->show();

(new CScriptTag('view.init();'))
	->setOnDocumentReady()
	->show();
