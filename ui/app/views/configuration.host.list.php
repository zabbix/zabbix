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

$this->includeJsFile('configuration.host.list.js.php');

if ($data['uncheck']) {
	uncheckTableRows('hosts');
}

$html_page = (new CHtmlPage())
	->setTitle(_('Hosts'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_HOST_LIST))
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(
					(new CSimpleButton(_('Host Wizard')))->addClass('js-host-wizard')
				)
				->addItem(
					(new CSimpleButton(_('Create host')))->addClass('js-create-host')
				)
				->addItem(
					(new CButton('form', _('Import')))
						->onClick(
							'return PopUp("popup.import", {
								rules_preset: "host", '.
								CSRF_TOKEN_NAME.': "'.CCsrfTokenHelper::get('import').
							'"}, {
								dialogueid: "popup_import",
								dialogue_class: "modal-popup-generic"
							});'
						)
						->removeId()
				)
		))->setAttribute('aria-label', _('Content controls'))
	);

$action_url = (new CUrl('zabbix.php'))->setArgument('action', $data['action']);

$filter = (new CFilter())
	->setResetUrl($action_url)
	->setProfile($data['profileIdx'])
	->setActiveTab($data['active_tab'])
	->addVar('action', $data['action'], 'filter_action')
	->addFilterTab(_('Filter'), [
		(new CFormGrid())
			->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
			->addItem([
				new CLabel(_('Host groups'), 'filter_groups__ms'),
				new CFormField(
					(new CMultiSelect([
						'name' => 'filter_groups[]',
						'object_name' => 'hostGroup',
						'data' => $data['filter']['groups'],
						'popup' => [
							'parameters' => [
								'srctbl' => 'host_groups',
								'srcfld1' => 'groupid',
								'dstfrm' => CFilter::FORM_NAME,
								'dstfld1' => 'filter_groups_',
								'with_hosts' => true,
								'editable' => true,
								'enrich_parent_groups' => true
							]
						]
					]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				)
			])
			->addItem([
				new CLabel(_('Templates'), 'filter_templates__ms'),
				new CFormField(
					(new CMultiSelect([
						'name' => 'filter_templates[]',
						'object_name' => 'templates',
						'data' => $data['filter']['templates'],
						'popup' => [
							'parameters' => [
								'srctbl' => 'templates',
								'srcfld1' => 'hostid',
								'srcfld2' => 'host',
								'dstfrm' => CFilter::FORM_NAME,
								'dstfld1' => 'filter_templates_'
							]
						]
					]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				)
			])
			->addItem([
				new CLabel(_('Name'), 'filter_host'),
				new CFormField(
					(new CTextBox('filter_host', $data['filter']['host']))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				)
			])
			->addItem([
				new CLabel(_('DNS'), 'filter_dns'),
				new CFormField(
					(new CTextBox('filter_dns', $data['filter']['dns']))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				)
			])
			->addItem([
				new CLabel(_('IP'), 'filter_ip'),
				new CFormField(
					(new CTextBox('filter_ip', $data['filter']['ip']))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				)
			])
			->addItem([
				new CLabel(_('Port'), 'filter_port'),
				new CFormField(
					(new CTextBox('filter_port', $data['filter']['port']))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				)
			]),
		(new CFormGrid())
			->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
			->addItem([
				new CLabel(_('Status'), 'filter_status'),
				new CFormField(
					(new CRadioButtonList('filter_status', (int) $data['filter']['status']))
						->addValue(_('Any'), -1)
						->addValue(_('Enabled'), HOST_STATUS_MONITORED)
						->addValue(_('Disabled'), HOST_STATUS_NOT_MONITORED)
						->setModern()
				)
			])
			->addItem([
				new CLabel(_('Monitored by'), 'filter_monitored_by'),
				new CFormField(
					(new CRadioButtonList('filter_monitored_by', (int) $data['filter']['monitored_by']))
						->addValue(_('Any'), ZBX_MONITORED_BY_ANY)
						->addValue(_('Server'), ZBX_MONITORED_BY_SERVER)
						->addValue(_('Proxy'), ZBX_MONITORED_BY_PROXY)
						->addValue(_('Proxy group'), ZBX_MONITORED_BY_PROXY_GROUP)
						->setModern()
				)
			])
			->addItem([
				(new CLabel(_('Proxies'), 'filter_proxyids__ms'))->addClass('js-filter-proxyids'),
				(new CFormField(
					(new CMultiSelect([
						'name' => 'filter_proxyids[]',
						'object_name' => 'proxies',
						'data' => $data['proxies_ms'],
						'popup' => [
							'parameters' => [
								'srctbl' => 'proxies',
								'srcfld1' => 'proxyid',
								'srcfld2' => 'name',
								'dstfrm' => CFilter::FORM_NAME,
								'dstfld1' => 'filter_proxyids_'
							]
						]
					]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				))->addClass('js-filter-proxyids')
			])
			->addItem([
				(new CLabel(_('Proxy groups'), 'filter_proxy_groupids__ms'))->addClass('js-filter-proxy-groupids'),
				(new CFormField(
					(new CMultiSelect([
						'name' => 'filter_proxy_groupids[]',
						'object_name' => 'proxy_groups',
						'data' => $data['proxy_groups_ms'],
						'popup' => [
							'parameters' => [
								'srctbl' => 'proxy_groups',
								'srcfld1' => 'proxy_groupid',
								'srcfld2' => 'name',
								'dstfrm' => CFilter::FORM_NAME,
								'dstfld1' => 'filter_proxy_groupids_'
							]
						]
					]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				))->addClass('js-filter-proxy-groupids')
			])
			->addItem([
				new CLabel(_('Tags')),
				new CFormField(
					CTagFilterFieldHelper::getTagFilterField([
						'evaltype' => $data['filter']['evaltype'],
						'tags' => $data['filter']['tags']
					])
				)
			])
	]);

$html_page->addItem($filter);

$current_time = time();
$csrf_token = CCsrfTokenHelper::get('host');

$form = (new CForm())->setName('hosts');
$form->addItem([
	(new CDataTable())->setId('hosts'),
	(new CActionButtonList('action', 'hostids', [
		'host.enable' => [
			'content' => (new CSimpleButton(_('Enable')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massenable-host')
				->addClass('js-no-chkbxrange')
		],
		'host.disable' => [
			'content' => (new CSimpleButton(_('Disable')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massdisable-host')
				->addClass('js-no-chkbxrange')
		],
		'host.export' => [
			'content' => new CButtonExport('export.hosts', $action_url
				->setArgument('page', ($data['page'] == 1) ? null : $data['page'])
				->getUrl()
			)
		],
		'popup.massupdate.host' => [
			'content' => (new CSimpleButton(_('Mass update')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massupdate-host')
				->addClass('js-no-chkbxrange')
		],
		'host.massdelete' => [
			'content' => (new CSimpleButton(_('Delete')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massdelete-host')
				->addClass('js-no-chkbxrange')
		]
	], 'hosts'))->setAddSelectedCountElement(false)
]);

$html_page
	->addItem($form)
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'applied_filter_groupids' => array_keys($data['filter']['groups']),
		'csrf_token' => $csrf_token,
		'default_sort_field' => $data['default_sort_field'],
		'default_sort_order' => $data['default_sort_order'],
		'filter' => $data['filter'],
		'page' => $data['page'],
		'sort_field' => $data['sort_field'],
		'sort_order' => $data['sort_order'],
		'storage_idx' => $data['storage_idx'],
		'user_configs' => $data['user_configs']
	]).');
'))
	->setOnDocumentReady()
	->show();
