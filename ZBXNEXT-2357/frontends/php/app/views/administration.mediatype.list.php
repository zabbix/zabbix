<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
	uncheckTableRows();
}

$mediaTypeWidget = (new CWidget())->setTitle(_('Media types'));

// create new media type button
$createForm = new CForm('get');

$controls = new CList();
$controls->addItem(new CRedirectButton(_('Create media type'), 'zabbix.php?action=mediatype.edit'));
$createForm->addItem($controls);
$mediaTypeWidget->setControls($createForm);

// create form
$mediaTypeForm = new CForm();
$mediaTypeForm->setName('mediaTypesForm');

// create table
$mediaTypeTable = new CTableInfo();
$mediaTypeTable->setHeader([
	(new CColHeader(
		new CCheckBox('all_media_types', null,
			"checkAll('".$mediaTypeForm->getName()."', 'all_media_types', 'mediatypeids');"
		)))->
		addClass('cell-width'),
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
		? new CLink(_('Enabled'), $statusLink, ZBX_STYLE_LINK_ACTION.' '.ZBX_STYLE_GREEN)
		: new CLink(_('Disabled'), $statusLink, ZBX_STYLE_LINK_ACTION.' '.ZBX_STYLE_RED);

	$name = new CLink($mediaType['description'], '?action=mediatype.edit&mediatypeid='.$mediaType['mediatypeid']);

	// append row
	$mediaTypeTable->addRow([
		new CCheckBox('mediatypeids['.$mediaType['mediatypeid'].']', null, null, $mediaType['mediatypeid']),
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
	])
]);

// append form to widget
$mediaTypeWidget->addItem($mediaTypeForm)->show();
