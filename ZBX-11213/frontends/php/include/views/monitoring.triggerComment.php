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


require_once dirname(__FILE__).'/js/monitoring.triggerComment.js.php';

$commentWidget = new CWidget('triggerComment');
$commentWidget->addPageHeader(_('TRIGGER COMMENTS'));

// create form
$commentForm = new CForm();
$commentForm->setName('commentForm');
$commentForm->addVar('triggerid', $this->data['triggerid']);

// create form list
$commentFormList = new CFormList('commentFormList');

$commentTextArea = new CTextArea('comments', CMacrosResolverHelper::resolveTriggerDescription($this->data['trigger']), array(
	'rows' => 25, 'width' => ZBX_TEXTAREA_BIG_WIDTH, 'readonly' => $this->data['isCommentExist']
));
$commentTextArea->attr('autofocus', 'autofocus');
$commentFormList->addRow(_('Comments'), $commentTextArea);

// append tabs to form
$commentTab = new CTabView();
$commentTab->addTab('commentTab', _s('Comments for "%s".', $this->data['trigger']['description']), $commentFormList);
$commentForm->addItem($commentTab);

// append buttons to form
$saveButton = new CSubmit('save', _('Save'));
$saveButton->setEnabled(!$this->data['isCommentExist']);

if ($this->data['isCommentExist']) {
	$editButton = new CButton('edit', _('Edit'));
	$editButton->setEnabled($this->data['isTriggerEditable']);
}
else {
	$editButton = null;
}

$commentForm->addItem(makeFormFooter(
	$saveButton,
	array($editButton, new CButtonCancel('&triggerid='.$this->data['triggerid']))
));

$commentWidget->addItem($commentForm);

return $commentWidget;
