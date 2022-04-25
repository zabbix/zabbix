<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

if ($data['uncheck']) {
	uncheckTableRows('mediatype');
}

$widget = (new CWidget())
	->setTitle(_('Media types'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_MEDIATYPE_LIST))
	->setControls((new CTag('nav', true,
		(new CList())
			->addItem(new CRedirectButton(_('Create media type'), 'zabbix.php?action=mediatype.edit'))
			->addItem(
				(new CButton('', _('Import')))
					->onClick(
						'return PopUp("popup.import", {rules_preset: "mediatype"},
							{dialogue_class: "modal-popup-generic"}
						);'
					)
					->removeId()
			)
		))
			->setAttribute('aria-label', _('Content controls'))
	)
	->addItem((new CFilter())
		->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'mediatype.list'))
		->setProfile($data['profileIdx'])
		->setActiveTab($data['active_tab'])
		->addFilterTab(_('Filter'), [
			(new CFormList())->addRow(_('Name'),
				(new CTextBox('filter_name', $data['filter']['name']))
					->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
					->setAttribute('autofocus', 'autofocus')
			),
			(new CFormList())->addRow(_('Status'),
				(new CRadioButtonList('filter_status', (int) $data['filter']['status']))
					->addValue(_('Any'), -1)
					->addValue(_('Enabled'), MEDIA_TYPE_STATUS_ACTIVE)
					->addValue(_('Disabled'), MEDIA_TYPE_STATUS_DISABLED)
					->setModern(true)
			)
		])
		->addVar('action', 'mediatype.list')
	);

// create form
$mediaTypeForm = (new CForm())->setName('mediaTypesForm');

// create table
$url = (new CUrl('zabbix.php'))
	->setArgument('action', 'mediatype.list')
	->getUrl();

$mediaTypeTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_media_types'))
				->onClick("checkAll('".$mediaTypeForm->getName()."', 'all_media_types', 'mediatypeids');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Type'), 'type', $data['sort'], $data['sortorder'], $url),
		_('Status'),
		_('Used in actions'),
		_('Details'),
		_('Action')
	]);

foreach ($data['mediatypes'] as $mediaType) {
	switch ($mediaType['typeid']) {
		case MEDIA_TYPE_EMAIL:
			$details =
				_('SMTP server').NAME_DELIMITER.'"'.$mediaType['smtp_server'].'", '.
				_('SMTP helo').NAME_DELIMITER.'"'.$mediaType['smtp_helo'].'", '.
				_('SMTP email').NAME_DELIMITER.'"'.$mediaType['smtp_email'].'"';
			break;

		case MEDIA_TYPE_EXEC:
			$details = _('Script name').NAME_DELIMITER.'"'.$mediaType['exec_path'].'"';
			break;

		case MEDIA_TYPE_SMS:
			$details = _('GSM modem').NAME_DELIMITER.'"'.$mediaType['gsm_modem'].'"';
			break;

		default:
			$details = '';
			break;
	}

	// action list
	$actionLinks = [];
	if (!empty($mediaType['listOfActions'])) {
		foreach ($mediaType['listOfActions'] as $action) {
			$actionLinks[] = new CLink($action['name'],
				(new CUrl('actionconf.php'))
					->setArgument('eventsource', $action['eventsource'])
					->setArgument('form', 'update')
					->setArgument('actionid', $action['actionid'])
					->getUrl()
			);
			$actionLinks[] = ', ';
		}
		array_pop($actionLinks);
	}
	else {
		$actionLinks = '';
	}
	$actionColumn = new CCol($actionLinks);
	$actionColumn->setAttribute('style', 'white-space: normal;');

	$statusLink = 'zabbix.php'.
		'?action='.($mediaType['status'] == MEDIA_TYPE_STATUS_DISABLED
			? 'mediatype.enable'
			: 'mediatype.disable'
		).
		'&mediatypeids[]='.$mediaType['mediatypeid'];

	$status = (MEDIA_TYPE_STATUS_ACTIVE == $mediaType['status'])
		? (new CLink(_('Enabled'), $statusLink))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass(ZBX_STYLE_GREEN)
			->addSID()
		: (new CLink(_('Disabled'), $statusLink))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass(ZBX_STYLE_RED)
			->addSID();

	$test_link = (new CButton('mediatypetest_edit', _('Test')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->removeId()
		->setEnabled(MEDIA_TYPE_STATUS_ACTIVE == $mediaType['status'])
		->setAttribute('data-mediatypeid', $mediaType['mediatypeid'])
		->onClick('
			PopUp("popup.mediatypetest.edit", {mediatypeid: this.dataset.mediatypeid}, {
				dialogueid: "mediatypetest_edit",
				dialogue_class: "modal-popup-medium"
			});
		');

	$name = new CLink($mediaType['name'], '?action=mediatype.edit&mediatypeid='.$mediaType['mediatypeid']);

	// append row
	$mediaTypeTable->addRow([
		new CCheckBox('mediatypeids['.$mediaType['mediatypeid'].']', $mediaType['mediatypeid']),
		(new CCol($name))->addClass(ZBX_STYLE_NOWRAP),
		media_type2str($mediaType['typeid']),
		$status,
		$actionColumn,
		$details,
		$test_link
	]);
}

// append table to form
$mediaTypeForm->addItem([
	$mediaTypeTable,
	$data['paging'],
	new CActionButtonList('action', 'mediatypeids', [
		'mediatype.enable' => ['name' => _('Enable'), 'confirm' => _('Enable selected media types?')],
		'mediatype.disable' => ['name' => _('Disable'), 'confirm' => _('Disable selected media types?')],
		'mediatype.export' => [
			'content' => new CButtonExport('export.mediatypes',
				(new CUrl('zabbix.php'))
					->setArgument('action', 'mediatype.list')
					->setArgument('page', ($data['page'] == 1) ? null : $data['page'])
					->getUrl()
			)
		],
		'mediatype.delete' => ['name' => _('Delete'), 'confirm' => _('Delete selected media types?')]
	], 'mediatype')
]);

// append form to widget
$widget->addItem($mediaTypeForm)->show();
