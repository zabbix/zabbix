<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
zbx_add_post_js('chkbxRange.pageGoName = "mediatypeids";');

$mediaTypeWidget = new CWidget();

// create new media type button
$createForm = new CForm('get');
$createForm->addItem(new CSubmit('form', _('Create media type')));
$mediaTypeWidget->addPageHeader(_('CONFIGURATION OF MEDIA TYPES'), $createForm);

// header
$numRows = new CDiv();
$numRows->setAttribute('name', 'numrows');
$mediaTypeWidget->addHeader(_('Media types'));
$mediaTypeWidget->addHeader($numRows);

// create form
$mediaTypeForm = new CForm();
$mediaTypeForm->setName('mediaTypesForm');

// create table
$mediaTypeTable = new CTableInfo(_('No media types defined'));
$mediaTypeTable->setHeader(array(
	new CCheckBox('all_media_types', null, "checkAll('".$mediaTypeForm->getName()."', 'all_media_types', 'mediatypeids');"),
	make_sorting_header(_('Description'), 'description'),
	make_sorting_header(_('Type'), 'type'),
	_('Used in actions'),
	_('Details')
));

// append data to table
foreach ($this->data['mediatypes'] as $mediatype) {
	switch ($mediatype['typeid']) {
		case MEDIA_TYPE_EMAIL:
			$details =
			_('SMTP server').': "'.$mediatype['smtp_server'].'", '.
			_('SMTP helo').': "'.$mediatype['smtp_helo'].'", '.
			_('SMTP email').': "'.$mediatype['smtp_email'].'"';
			break;
		case MEDIA_TYPE_EXEC:
			$details = _('Script name').': "'.$mediatype['exec_path'].'"';
			break;
		case MEDIA_TYPE_SMS:
			$details = _('GSM modem').': "'.$mediatype['gsm_modem'].'"';
			break;
		case MEDIA_TYPE_JABBER:
			$details = _('Jabber identifier').': "'.$mediatype['username'].'"';
			break;
		case MEDIA_TYPE_EZ_TEXTING:
			$details = _('Username').': "'.$mediatype['username'].'"';
			break;
		default:
			$details = '';
	}

	$actionLinks = array();
	if (!empty($mediatype['listOfActions'])) {
		order_result($mediatype['listOfActions'], 'name');
		foreach ($mediatype['listOfActions'] as $action) {
			$actionLinks[] = new CLink($action['name'], 'actionconf.php?form=edit&actionid='.$action['actionid']);
			$actionLinks[] = ', ';
		}
		array_pop($actionLinks);
	}
	else {
		$actionLinks = '-';
	}
	$actionColumn = new CCol($actionLinks);
	$actionColumn->setAttribute('style', 'white-space: normal;');

	// append row
	$mediaTypeTable->addRow(array(
		new CCheckBox('mediatypeids['.$mediatype['mediatypeid'].']', null, null, $mediatype['mediatypeid']),
		new CLink($mediatype['description'], '?form=edit&mediatypeid='.$mediatype['mediatypeid']),
		media_type2str($mediatype['typeid']),
		$actionColumn,
		$details
	));
}

// create go button
$goComboBox = new CComboBox('go');
$goOption = new CComboItem('delete', _('Delete selected'));
$goOption->setAttribute('confirm', _('Delete selected media types?'));
$goComboBox->addItem($goOption);

$goButton = new CSubmit('goButton', _('Go'));
$goButton->setAttribute('id', 'goButton');

// append table to form
$mediaTypeForm->addItem(array($this->data['paging'], $mediaTypeTable, $this->data['paging'], get_table_header(array($goComboBox, $goButton))));

// append form to widget
$mediaTypeWidget->addItem($mediaTypeForm);

return $mediaTypeWidget;
?>
