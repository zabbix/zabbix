<?php declare(strict_types = 1);
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
 * @var CPartial $this
 */

(new CDiv())
	->addItem([
		(new CInput('hidden', 'interfaces[#{iface.interfaceid}][items]', '#{iface.items}'))->removeId(),
		(new CInput('hidden', 'interfaces[#{iface.interfaceid}][isNew]', '#{iface.isNew}'))->removeId(),
		(new CInput('hidden', 'interfaces[#{iface.interfaceid}][interfaceid]', '#{iface.interfaceid}'))->removeId(),
		(new CInput('hidden', 'interfaces[#{iface.interfaceid}][type]', '#{iface.type}'))
			->setId('interface_type_#{iface.interfaceid}'),
		(new CDiv(
			(new CSimpleButton())->addClass(ZBX_STYLE_HOST_INTERFACE_BTN_TOGGLE)
		))->addClass(ZBX_STYLE_HOST_INTERFACE_CELL),
		(new CDiv('#{iface.type_name}'))
			->addClass(ZBX_STYLE_HOST_INTERFACE_CELL.' '.ZBX_STYLE_HOST_INTERFACE_CELL_TYPE),
		(new CDiv(
			(new CTextBox('interfaces[#{iface.interfaceid}][ip]', '#{iface.ip}', false, DB::getFieldLength('interface', 'ip')))
				->addClass(ZBX_STYLE_HOST_INTERFACE_INPUT_EXPAND)
				->setWidth(ZBX_TEXTAREA_INTERFACE_IP_WIDTH)
		))->addClass(ZBX_STYLE_HOST_INTERFACE_CELL.' '.ZBX_STYLE_HOST_INTERFACE_CELL_IP),
		(new CDiv(
			(new CTextBox('interfaces[#{iface.interfaceid}][dns]', '#{iface.dns}', false, DB::getFieldLength('interface', 'dns')))
				->addClass(ZBX_STYLE_HOST_INTERFACE_INPUT_EXPAND)
				->setWidth(ZBX_TEXTAREA_INTERFACE_DNS_WIDTH)
		))->addClass(ZBX_STYLE_HOST_INTERFACE_CELL . ' ' . ZBX_STYLE_HOST_INTERFACE_CELL_DNS),
		(new CDiv(
			(new CRadioButtonList('interfaces[#{iface.interfaceid}][useip]', null))
				->addValue('IP', INTERFACE_USE_IP, 'interfaces[#{iface.interfaceid}][useip]['.INTERFACE_USE_IP.']')
				->addValue('DNS', INTERFACE_USE_DNS, 'interfaces[#{iface.interfaceid}][useip]['.INTERFACE_USE_DNS.']')
				->addClass(ZBX_STYLE_HOST_INTERFACE_CELL_USEIP.' '.ZBX_STYLE_HOST_INTERFACE_INPUT_EXPAND)
				->setModern(true)
		))->addClass(ZBX_STYLE_HOST_INTERFACE_CELL . ' ' . ZBX_STYLE_HOST_INTERFACE_CELL_USEIP),
		(new CDiv(
			(new CTextBox('interfaces[#{iface.interfaceid}][port]', '#{iface.port}', false, DB::getFieldLength('interface', 'port')))
				->setWidth(ZBX_TEXTAREA_INTERFACE_PORT_WIDTH)
				->addClass(ZBX_STYLE_HOST_INTERFACE_INPUT_EXPAND)
				->setAriaRequired()
		))->addClass(ZBX_STYLE_HOST_INTERFACE_CELL . ' ' . ZBX_STYLE_HOST_INTERFACE_CELL_PORT),
		(new CDiv([
			(new CInput('radio', 'mainInterfaces[#{iface.type}]', '#{iface.interfaceid}'))
				->addClass(ZBX_STYLE_CHECKBOX_RADIO . ' ' . ZBX_STYLE_HOST_INTERFACE_BTN_MAIN_INTERFACE)
				->setId('interface_main_#{iface.interfaceid}'),
			(new CLabel(new CSpan(), 'interface_main_#{iface.interfaceid}'))
				->addClass('checkboxLikeLabel')
				->addStyle('height: 16px; width: 16px;')
		]))->addClass(ZBX_STYLE_HOST_INTERFACE_CELL . ' ' . ZBX_STYLE_HOST_INTERFACE_CELL_DEFAULT),
		(new CDiv(
			(new CSimpleButton(_('Remove')))->addClass(ZBX_STYLE_BTN_LINK . ' ' . ZBX_STYLE_HOST_INTERFACE_BTN_REMOVE)
		))->addClass(ZBX_STYLE_HOST_INTERFACE_CELL . ' ' . ZBX_STYLE_HOST_INTERFACE_CELL_ACTION),
		(new CDiv(
			(new CFormGrid())
				->setId('snmp_details_#{iface.interfaceid}')
				->addItem([
					(new CLabel(_('SNMP version'), 'label_interfaces_#{iface.interfaceid}_details_version'))
						->setAsteriskMark(),
					new CFormField(
						(new CSelect('interfaces[#{iface.interfaceid}][details][version]'))
							->addOptions(CSelect::createOptionsFromArray([
								SNMP_V1 => _('SNMPv1'),
								SNMP_V2C => _('SNMPv2'),
								SNMP_V3 => _('SNMPv3')
							]))
							->setValue(SNMP_V2C)
							->setFocusableElementId('label_interfaces_#{iface.interfaceid}_details_version')
							->setId('interfaces_#{iface.interfaceid}_details_version')
					)
				])
				->addItem([
					(new CLabel(_('SNMP community'), 'interfaces[#{iface.interfaceid}][details][community]'))
						->setId('snmp_community_label_#{iface.interfaceid}')
						->setAsteriskMark(),
					(new CFormField(
						(new CTextBox('interfaces[#{iface.interfaceid}][details][community]', '#{iface.details.community}',
								false, DB::getFieldLength('interface_snmp', 'community')
						))
							->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
							->setAriaRequired()
					))
						->setId('snmp_community_field_#{iface.interfaceid}')
				])
				->addItem([
					(new CLabel(_('Context name'), 'interfaces[#{iface.interfaceid}][details][contextname]'))
						->setId('snmpv3_contextname_label_#{iface.interfaceid}'),
					(new CFormField(
						(new CTextBox('interfaces[#{iface.interfaceid}][details][contextname]',
								'#{iface.details.contextname}', false, DB::getFieldLength('interface_snmp', 'contextname')
						))
							->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					))
						->setId('snmpv3_contextname_field_#{iface.interfaceid}')
				])
				->addItem([
					(new CLabel(_('Security name'), 'interfaces[#{iface.interfaceid}][details][securityname]'))
						->setId('snmpv3_securityname_label_#{iface.interfaceid}'),
					(new CFormField(
						(new CTextBox('interfaces[#{iface.interfaceid}][details][securityname]',
								'#{iface.details.securityname}', false,
								DB::getFieldLength('interface_snmp', 'securityname')
						))
							->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					))
						->setId('snmpv3_securityname_field_#{iface.interfaceid}')
				])
				->addItem([
					(new CLabel(_('Security level'), 'label_interfaces_#{iface.interfaceid}_details_securitylevel'))
						->setId('snmpv3_securitylevel_label_#{iface.interfaceid}'),
					(new CFormField(
						(new CSelect('interfaces[#{iface.interfaceid}][details][securitylevel]'))
							->addOptions(CSelect::createOptionsFromArray([
								ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV => 'noAuthNoPriv',
								ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV => 'authNoPriv',
								ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV => 'authPriv'
							]))
							->setValue(ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV)
							->setFocusableElementId('label_interfaces_#{iface.interfaceid}_details_securitylevel')
							->setId('interfaces_#{iface.interfaceid}_details_securitylevel')
					))
						->setId('snmpv3_securitylevel_field_#{iface.interfaceid}')
				])
				->addItem([
					(new CLabel(_('Authentication protocol'), 'label-authprotocol-#{iface.interfaceid}'))
						->setId('snmpv3_authprotocol_label_#{iface.interfaceid}'),
					(new CFormField(
						(new CSelect('interfaces[#{iface.interfaceid}][details][authprotocol]'))
							->setFocusableElementId('label-authprotocol-#{iface.interfaceid}')
							->addOptions(CSelect::createOptionsFromArray(getSnmpV3AuthProtocols()))
							->setId('interfaces_#{iface.interfaceid}_details_authprotocol')
					))
						->setId('snmpv3_authprotocol_field_#{iface.interfaceid}')
				])
				->addItem([
					(new CLabel(_('Authentication passphrase'),
							'interfaces[#{iface.interfaceid}][details][authpassphrase]'
					))
						->setId('snmpv3_authpassphrase_label_#{iface.interfaceid}'),
					(new CFormField(
						(new CTextBox('interfaces[#{iface.interfaceid}][details][authpassphrase]',
								'#{iface.details.authpassphrase}', false,
								DB::getFieldLength('interface_snmp', 'authpassphrase')
						))
							->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
							->disableAutocomplete()
					))
						->setId('snmpv3_authpassphrase_field_#{iface.interfaceid}')
				])
				->addItem([
					(new CLabel(_('Privacy protocol'), 'label-privprotocol-#{iface.interfaceid}'))
						->setId('snmpv3_privprotocol_label_#{iface.interfaceid}'),
					(new CFormField(
						(new CSelect('interfaces[#{iface.interfaceid}][details][privprotocol]'))
							->setFocusableElementId('label-privprotocol-#{iface.interfaceid}')
							->addOptions(CSelect::createOptionsFromArray(getSnmpV3PrivProtocols()))
					))
						->setId('snmpv3_privprotocol_field_#{iface.interfaceid}')
				])
				->addItem([
					(new CLabel(_('Privacy passphrase'), 'interfaces[#{iface.interfaceid}][details][privpassphrase]'))
						->setId('snmpv3_privpassphrase_label_#{iface.interfaceid}'),
					(new CFormField(
						(new CTextBox('interfaces[#{iface.interfaceid}][details][privpassphrase]',
								'#{iface.details.privpassphrase}', false,
							DB::getFieldLength('interface_snmp', 'privpassphrase')
						))
							->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
							->disableAutocomplete()
					))
						->setId('snmpv3_privpassphrase_field_#{iface.interfaceid}')
				])
				->addItem(
					new CFormField(
						(new CCheckBox('interfaces[#{iface.interfaceid}][details][bulk]', SNMP_BULK_ENABLED))
							->setLabel(_('Use bulk requests'))
					)
				)
		))
			->addClass(ZBX_STYLE_HOST_INTERFACE_CELL . ' ' . ZBX_STYLE_HOST_INTERFACE_CELL_DETAILS . ' ' . ZBX_STYLE_LIST_ACCORDION_ITEM_BODY)
	])
	->addClass(ZBX_STYLE_HOST_INTERFACE_ROW.' '.ZBX_STYLE_LIST_ACCORDION_ITEM.' '.ZBX_STYLE_LIST_ACCORDION_ITEM_CLOSED)
	->setId('interface_row_#{iface.interfaceid}')
	->setAttribute('data-type', '#{iface.type}')
	->setAttribute('data-interfaceid', '#{iface.interfaceid}')
	->show();
