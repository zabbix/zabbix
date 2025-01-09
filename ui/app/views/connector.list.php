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

$this->addJsFile('class.form.fieldset.collapsible.js');
$this->includeJsFile('connector.list.js.php');

$filter = (new CFilter())
	->addVar('action', 'connector.list')
	->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'connector.list'))
	->setProfile('web.connector.filter')
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
				new CLabel(_('Status')),
				new CFormField(
					(new CRadioButtonList('filter_status', (int) $data['filter']['status']))
						->addValue(_('Any'), -1)
						->addValue(_('Enabled'), ZBX_CONNECTOR_STATUS_ENABLED)
						->addValue(_('Disabled'), ZBX_CONNECTOR_STATUS_DISABLED)
						->setModern()
				)
			])
	]);

$form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('connector')))->removeId())
	->setId('connector-list')
	->setName('connector_list');

$view_url = (new CUrl('zabbix.php'))
	->setArgument('action', 'connector.list')
	->getUrl();

$connector_list = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_connectors'))->onClick("checkAll('connector_list', 'all_connectors', 'connectorids');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $view_url),
		make_sorting_header(_('Data type'), 'data_type', $data['sort'], $data['sortorder'], $view_url),
		new CColHeader(_('Status'))
	])
	->setPageNavigation($data['paging']);

foreach ($data['connectors'] as $connectorid => $connector) {
	$status_tag = $connector['status'] == ZBX_CONNECTOR_STATUS_ENABLED
		? (new CLink(_('Enabled')))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass(ZBX_STYLE_GREEN)
			->addClass('js-disable-connector')
			->setAttribute('data-connectorid', $connectorid)
		: (new CLink(_('Disabled')))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass(ZBX_STYLE_RED)
			->addClass('js-enable-connector')
			->setAttribute('data-connectorid', $connectorid);

	$connector_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'popup')
		->setArgument('popup', 'connector.edit')
		->setArgument('connectorid', $connectorid)
		->getUrl();

	$row = [
		new CCheckBox('connectorids['.$connectorid.']', $connectorid),
		(new CCol(
			(new CLink($connector['name'], $connector_url))
				->setAttribute('data-connectorid', $connectorid)
				->setAttribute('data-action', 'connector.edit')
		))->addClass(ZBX_STYLE_WORDBREAK),
		$connector['data_type'] == ZBX_CONNECTOR_DATA_TYPE_ITEM_VALUES ? _('Item values') : _('Events'),
		$status_tag
	];

	$connector_list->addRow($row);
}

$form->addItem([
	$connector_list,
	new CActionButtonList('action', 'connectorids', [
		'connector.massenable' => [
			'content' => (new CSimpleButton(_('Enable')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massenable-connector')
				->addClass('js-no-chkbxrange')
		],
		'connector.massdisable' => [
			'content' => (new CSimpleButton(_('Disable')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massdisable-connector')
				->addClass('js-no-chkbxrange')
		],
		'connector.massdelete' => [
			'content' => (new CSimpleButton(_('Delete')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massdelete-connector')
				->addClass('js-no-chkbxrange')
		]
	], 'connector')
]);

(new CHtmlPage())
	->setTitle(_('Connectors'))
	->setTitleSubmenu(getAdministrationGeneralSubmenu())
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_CONNECTOR_LIST))
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(
					(new CSimpleButton(_('Create connector')))->addClass('js-create-connector')
				)
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem($filter)
	->addItem($form)
	->show();

(new CScriptTag('view.init();'))
	->setOnDocumentReady()
	->show();
