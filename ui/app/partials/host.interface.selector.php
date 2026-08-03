<?php
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
 * @var CPartial $this
 * @var array    $data
 */

$interface = array_key_exists($data['interfaceid'], $data['interfaces'])
	? $data['interfaces'][$data['interfaceid']]
	: [];

if ($data['discovered']) {
	(new CInput('hidden', 'interfaceid', $data['interfaceid']))->show();

	$required = $interface && $interface['type'] != INTERFACE_TYPE_OPT;
	$select_interface = new CTextBox('interface', $interface ? getHostInterface($interface) : _('None'), true);
	$label_for = $select_interface->getId();
}
else {
	$required = true;
	$select_interface = getInterfaceSelect($data['interfaces'])
		->setId('interface-select')
		->setValue($data['interfaceid'])
		->addClass(ZBX_STYLE_ZSELECT_HOST_INTERFACE)
		->setFocusableElementId('interfaceid')
		->setAriaRequired();
	$label_for = $select_interface->getFocusableElementId();
}

(new CLabel(_('Host interface'), $label_for))
	->setAsteriskMark($required)
	->setId('js-item-interface-label')
	->show();

(new CFormField($select_interface))
	->setId('js-item-interface-field')
	->show();
