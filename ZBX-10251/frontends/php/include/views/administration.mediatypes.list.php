<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


$mediaTypeWidget = new CWidget();

// create new media type button
$createForm = new CForm('get');
$createForm->addItem(new CSubmit('form', _('Create media type')));
$mediaTypeWidget->addPageHeader(_('CONFIGURATION OF MEDIA TYPES'), $createForm);
$mediaTypeWidget->addHeader(_('Media types'));
$mediaTypeWidget->addHeaderRowNumber();

// create form
$mediaTypeForm = new CForm();
$mediaTypeForm->setName('mediaTypesForm');

// create table
$mediaTypeTable = new CTableInfo(_('No media types found.'));
$mediaTypeTable->setHeader(array(
	new CCheckBox('all_media_types', null, "checkAll('".$mediaTypeForm->getName()."', 'all_media_types', 'mediatypeids');"),
	$this->data['displayNodes'] ? _('Node') : null,
	make_sorting_header(_('Name'), 'description'),
	make_sorting_header(_('Type'), 'type'),
	_('Status'),
	_('Used in actions'),
	_('Details')
));

foreach ($this->data['mediatypes'] as $mediaType) {
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
	$actionLinks = array();
	if (!empty($mediaType['listOfActions'])) {
		foreach ($mediaType['listOfActions'] as $action) {
			$actionLinks[] = new CLink($action['name'], 'actionconf.php?form=update&actionid='.$action['actionid']);
			$actionLinks[] = ', ';
		}
		array_pop($actionLinks);
	}
	else {
		$actionLinks = '-';
	}
	$actionColumn = new CCol($actionLinks);
	$actionColumn->setAttribute('style', 'white-space: normal;');

	$statusLink = 'media_types.php?go='.(($mediaType['status'] == MEDIA_TYPE_STATUS_DISABLED) ? 'activate' : 'disable').
		'&mediatypeids'.SQUAREBRACKETS.'='.$mediaType['mediatypeid'];

	$status = (MEDIA_TYPE_STATUS_ACTIVE == $mediaType['status'])
		? new CLink(_('Enabled'), $statusLink, 'enabled')
		: new CLink(_('Disabled'), $statusLink, 'disabled');

	// append row
	$mediaTypeTable->addRow(array(
		new CCheckBox('mediatypeids['.$mediaType['mediatypeid'].']', null, null, $mediaType['mediatypeid']),
		$this->data['displayNodes'] ? $mediaType['nodename'] : null,
		new CLink($mediaType['description'], '?form=edit&mediatypeid='.$mediaType['mediatypeid']),
		media_type2str($mediaType['typeid']),
		$status,
		$actionColumn,
		$details
	));
}

// create go button
$goComboBox = new CComboBox('go');
$goOption = new CComboItem('activate', _('Enable selected'));
$goOption->setAttribute('confirm', _('Enable selected media types?'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('disable', _('Disable selected'));
$goOption->setAttribute('confirm', _('Disable selected media types?'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('delete', _('Delete selected'));
$goOption->setAttribute('confirm', _('Delete selected media types?'));
$goComboBox->addItem($goOption);

$goButton = new CSubmit('goButton', _('Go').' (0)');
$goButton->setAttribute('id', 'goButton');
zbx_add_post_js('chkbxRange.pageGoName = "mediatypeids";');

// append table to form
$mediaTypeForm->addItem(array($this->data['paging'], $mediaTypeTable, $this->data['paging'], get_table_header(array($goComboBox, $goButton))));

// append form to widget
$mediaTypeWidget->addItem($mediaTypeForm);

return $mediaTypeWidget;
