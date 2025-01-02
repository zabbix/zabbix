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

$this->includeJsFile('mediatype.list.js.php');

$html_page = (new CHtmlPage())
	->setTitle(_('Media types'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ALERTS_MEDIATYPE_LIST))
	->setControls((new CTag('nav', true,
		(new CList())
			->addItem((new CSimpleButton(_('Create media type')))->setId('js-create'))
			->addItem(
				(new CSimpleButton(_('Import')))
					->onClick(
						'return PopUp("popup.import", {
							rules_preset: "mediatype", '.
							CSRF_TOKEN_NAME.': "'.CCsrfTokenHelper::get('import').
						'"},{
							dialogueid: "popup_import",
							dialogue_class: "modal-popup-generic"
						});'
					)
			)
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem((new CFilter())
		->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'mediatype.list'))
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
					new CLabel(_('Status')),
					new CFormField(
						(new CRadioButtonList('filter_status', (int) $data['filter']['status']))
							->addValue(_('Any'), -1)
							->addValue(_('Enabled'), MEDIA_TYPE_STATUS_ACTIVE)
							->addValue(_('Disabled'), MEDIA_TYPE_STATUS_DISABLED)
							->setModern()
					)
				]),
			(new CFormGrid())
				->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
				->addItem([
					new CLabel([_('Display actions'), makeHelpIcon([
						_('Filter actions by the scope of media type usage:'),
						(new CList([
							_('All').' - '._('display all actions'),
							[_('All available'), ' - ', make_decoration(
								_('display only actions where All available media types are used in action operation'),
								_('All available')
							)],
							_('Specific').' - '.
								_('display only actions where specific media type is used in action operation')
						]))->addClass(ZBX_STYLE_LIST_DASHED)
					])]),
					new CFormField(
						(new CRadioButtonList('filter_actions', (int) $data['filter']['actions']))
							->addValue(_('All'), ZBX_MEDIA_TYPE_ACTIONS_ALL)
							->addValue(_('All available'), ZBX_MEDIA_TYPE_ACTIONS_AVAILABLE)
							->addValue(_('Specific'), ZBX_MEDIA_TYPE_ACTIONS_SPECIFIC)
							->setModern()
					)
				])
		])
		->addVar('action', 'mediatype.list')
	);

// create form
$media_type_form = (new CForm())->setName('media-types-form');

// create table
$url = (new CUrl('zabbix.php'))
	->setArgument('action', 'mediatype.list')
	->getUrl();

$media_type_table = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_media_types'))
				->onClick("checkAll('".$media_type_form->getName()."', 'all_media_types', 'mediatypeids');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Type'), 'type', $data['sort'], $data['sortorder'], $url),
		_('Status'),
		(new CColHeader(_('Used in actions')))->setColSpan(2),
		_('Details'),
		_('Action')
	])
	->setPageNavigation($data['paging']);

$csrf_token = CCsrfTokenHelper::get('mediatype');

foreach ($data['mediatypes'] as $media_type) {
	switch ($media_type['typeid']) {
		case MEDIA_TYPE_EMAIL:
			if ($media_type['provider'] == CMediatypeHelper::EMAIL_PROVIDER_SMTP) {
				$details =
					_('SMTP server').NAME_DELIMITER.'"'.$media_type['smtp_server'].'", '.
					_('SMTP helo').NAME_DELIMITER.'"'.$media_type['smtp_helo'].'", '.
					_('email').NAME_DELIMITER.'"'.$media_type['smtp_email'].'"';
			}
			else {
				$details =
					_('SMTP server').NAME_DELIMITER.'"'.$media_type['smtp_server'].'", '.
					_('email').NAME_DELIMITER.'"'.$media_type['smtp_email'] . '"';
			}
			break;

		case MEDIA_TYPE_EXEC:
			$details = _('Script name').NAME_DELIMITER.'"'.$media_type['exec_path'].'"';
			break;

		case MEDIA_TYPE_SMS:
			$details = _('GSM modem').NAME_DELIMITER.'"'.$media_type['gsm_modem'].'"';
			break;

		default:
			$details = '';
	}

	// action list
	$action_count_total = '';
	$actions = [];

	if ($media_type['actions']) {
		$action_count_total = (new CSpan($media_type['action_count_total']))->addClass(ZBX_STYLE_ENTITY_COUNT);

		foreach ($media_type['actions'] as $action) {
			$action_url = (new CUrl('zabbix.php'))
				->setArgument('action', 'popup')
				->setArgument('popup', 'action.edit')
				->setArgument('actionid', $action['actionid'])
				->setArgument('eventsource', $action['eventsource'])
				->getUrl();

			$actions[] = $action['is_editable']
				? (new CLink($action['name'], $action_url))
					->addClass(ZBX_STYLE_LINK_ALT)
					->addClass(ZBX_STYLE_GREY)
				: (new CSpan($action['name']))->addClass(ZBX_STYLE_GREY);
			$actions[] = ', ';
		}

		array_pop($actions);

		if ($media_type['action_count_total'] > count($media_type['actions'])) {
			$actions[] = [', ', HELLIP()];
		}
	}

	$status = (MEDIA_TYPE_STATUS_ACTIVE == $media_type['status'])
		? (new CLink(_('Enabled')))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass(ZBX_STYLE_GREEN)
			->addClass('js-disable')
			->setAttribute('data-mediatypeid', (int) $media_type['mediatypeid'])
		: (new CLink(_('Disabled')))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass(ZBX_STYLE_RED)
			->addClass('js-enable')
			->setAttribute('data-mediatypeid', (int) $media_type['mediatypeid']);

	$test_link = (new CButton('mediatypetest_edit', _('Test')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->removeId()
		->setEnabled(MEDIA_TYPE_STATUS_ACTIVE == $media_type['status'])
		->setAttribute('data-mediatypeid', $media_type['mediatypeid'])
		->addClass('js-test-edit');

	$media_type_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'popup')
		->setArgument('popup', 'mediatype.edit')
		->setArgument('mediatypeid', $media_type['mediatypeid'])
		->getUrl();

	$name = new CLink($media_type['name'], $media_type_url);

	// append row
	$media_type_table->addRow([
		new CCheckBox('mediatypeids['.$media_type['mediatypeid'].']', $media_type['mediatypeid']),
		(new CCol($name))->addClass(ZBX_STYLE_WORDBREAK),
		CMediatypeHelper::getMediaTypes($media_type['typeid']),
		$status,
		(new CCol($action_count_total))->addClass(ZBX_STYLE_CELL_WIDTH),
		(new CCol($actions))->addClass(ZBX_STYLE_WORDBREAK),
		(new CCol($details))->addClass(ZBX_STYLE_WORDBREAK),
		$test_link
	]);
}

// append table to form
$media_type_form->addItem([
	$media_type_table,
	new CActionButtonList('action', 'mediatypeids', [
		'mediatype.enable' => [
			'content' => (new CSimpleButton(_('Enable')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->setId('js-massenable')
				->addClass('no-chkbxrange')
		],
		'mediatype.disable' => [
			'content' => (new CSimpleButton(_('Disable')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->setId('js-massdisable')
				->addClass('no-chkbxrange')
		],
		'mediatype.export' => [
			'content' => new CButtonExport('export.mediatypes',
				(new CUrl('zabbix.php'))
					->setArgument('action', 'mediatype.list')
					->setArgument('page', ($data['page'] == 1) ? null : $data['page'])
					->getUrl()
			)
		],
		'mediatype.delete' => [
			'content' => (new CSimpleButton(_('Delete')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->setId('js-massdelete')
				->addClass('no-chkbxrange')
		]
	], 'mediatype')
]);

// append form to widget
$html_page
	->addItem($media_type_form)
	->show();

(new CScriptTag('view.init();'))
	->setOnDocumentReady()
	->show();
