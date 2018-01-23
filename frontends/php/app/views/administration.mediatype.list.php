<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


if ($data['uncheck']) {
	uncheckTableRows('mediatype');
}

$widget = (new CWidget())
	->setTitle(_('Media types'))
	->setControls((new CForm())
		->cleanItems()
		->addItem((new CList())->addItem(new CRedirectButton(_('Create media type'), 'zabbix.php?action=mediatype.edit')))
	)
	->addItem((new CFilter('web.media_types.filter.state'))
		->addVar('action', 'mediatype.list')
		->addColumn((new CFormList())->addRow(_('Name'),
			(new CTextBox('filter_name', $data['filter']['name']))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
				->setAttribute('autofocus', 'autofocus')
		))
		->addColumn((new CFormList())->addRow(_('Status'),
			(new CRadioButtonList('filter_status', (int) $data['filter']['status']))
				->addValue(_('Any'), -1)
				->addValue(_('Enabled'), MEDIA_TYPE_STATUS_ACTIVE)
				->addValue(_('Disabled'), MEDIA_TYPE_STATUS_DISABLED)
				->setModern(true)
		))
	);

// create form
$mediaTypeForm = (new CForm())->setName('mediaTypesForm');

// create table
$mediaTypeTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_media_types'))
				->onClick("checkAll('".$mediaTypeForm->getName()."', 'all_media_types', 'mediatypeids');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'description', $data['sort'], $data['sortorder']),
		make_sorting_header(_('Type'), 'type', $data['sort'], $data['sortorder']),
		_('Status'),
		_('Used in actions'),
		_('Details')
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

		case MEDIA_TYPE_JABBER:
			$details = _('Jabber identifier').NAME_DELIMITER.'"'.$mediaType['username'].'"';
			break;

		case MEDIA_TYPE_EZ_TEXTING:
			$details = _('Username').NAME_DELIMITER.'"'.$mediaType['username'].'"';
			break;

		default:
			$details = '';
			break;
	}

	// action list
	$actionLinks = [];
	if (!empty($mediaType['listOfActions'])) {
		foreach ($mediaType['listOfActions'] as $action) {
			$actionLinks[] = new CLink($action['name'], 'actionconf.php?form=update&actionid='.$action['actionid']);
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
		? (new CActionLink(_('Enabled'), $statusLink))
			->addClass(ZBX_STYLE_GREEN)
			->addSID()
		: (new CActionLink(_('Disabled'), $statusLink))
			->addClass(ZBX_STYLE_RED)
			->addSID();

	$name = new CLink($mediaType['description'], '?action=mediatype.edit&mediatypeid='.$mediaType['mediatypeid']);

	// append row
	$mediaTypeTable->addRow([
		new CCheckBox('mediatypeids['.$mediaType['mediatypeid'].']', $mediaType['mediatypeid']),
		(new CCol($name))->addClass(ZBX_STYLE_NOWRAP),
		media_type2str($mediaType['typeid']),
		$status,
		$actionColumn,
		$details
	]);
}

// append table to form
$mediaTypeForm->addItem([
	$mediaTypeTable,
	$data['paging'],
	new CActionButtonList('action', 'mediatypeids', [
		'mediatype.enable' => ['name' => _('Enable'), 'confirm' => _('Enable selected media types?')],
		'mediatype.disable' => ['name' => _('Disable'), 'confirm' => _('Disable selected media types?')],
		'mediatype.delete' => ['name' => _('Delete'), 'confirm' => _('Delete selected media types?')]
	], 'mediatype')
]);

// append form to widget
$widget->addItem($mediaTypeForm)->show();
