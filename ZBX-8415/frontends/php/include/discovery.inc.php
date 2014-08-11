<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


require_once dirname(__FILE__).'/perm.inc.php';

/**
 * Returns true if the user has the permissions to network discovery.
 *
 * @return bool
 */
function check_right_on_discovery() {
	return (CWebUser::getType() >= USER_TYPE_ZABBIX_ADMIN);
}

function svc_default_port($type_int) {
	$typePort = array(
		SVC_SSH =>		'22',
		SVC_LDAP =>		'389',
		SVC_SMTP =>		'25',
		SVC_FTP =>		'21',
		SVC_HTTP =>		'80',
		SVC_POP =>		'110',
		SVC_NNTP =>		'119',
		SVC_IMAP =>		'143',
		SVC_AGENT =>	'10050',
		SVC_SNMPv1 =>	'161',
		SVC_SNMPv2c =>	'161',
		SVC_SNMPv3 =>	'161',
		SVC_HTTPS =>	'443',
		SVC_TELNET =>	'23'
	);

	return isset($typePort[$type_int]) ? $typePort[$type_int] : 0;
}

function discovery_check_type2str($type = null) {
	$discovery_types = array(
		SVC_SSH => _('SSH'),
		SVC_LDAP => _('LDAP'),
		SVC_SMTP => _('SMTP'),
		SVC_FTP => _('FTP'),
		SVC_HTTP => _('HTTP'),
		SVC_POP => _('POP'),
		SVC_NNTP => _('NNTP'),
		SVC_IMAP => _('IMAP'),
		SVC_TCP => _('TCP'),
		SVC_AGENT => _('Zabbix agent'),
		SVC_SNMPv1 => _('SNMPv1 agent'),
		SVC_SNMPv2c => _('SNMPv2 agent'),
		SVC_SNMPv3 => _('SNMPv3 agent'),
		SVC_ICMPPING => _('ICMP ping'),
		SVC_TELNET => _('Telnet'),
		SVC_HTTPS => _('HTTPS')
	);

	if (is_null($type)) {
		order_result($discovery_types);
		return $discovery_types;
	}
	elseif (isset($discovery_types[$type])) {
		return $discovery_types[$type];
	}
	else {
		return false;
	}
}

function discovery_check2str($type, $key, $port) {
	$externalParam = '';

	if ($key !== '') {
		switch ($type) {
			case SVC_SNMPv1:
			case SVC_SNMPv2c:
			case SVC_SNMPv3:
			case SVC_AGENT:
				$externalParam = ' "'.$key.'"';
				break;
		}
	}

	$result = discovery_check_type2str($type);

	if ($port && (svc_default_port($type) !== $port || $type === SVC_TCP)) {
		$result .= ' ('.$port.')';
	}

	return $result.$externalParam;
}

function discovery_port2str($type_int, $port) {
	$port_def = svc_default_port($type_int);

	if ($port != $port_def) {
		return ' ('.$port.')';
	}

	return '';
}

function discovery_status2str($status = null) {
	$discoveryStatus = array(
		DRULE_STATUS_ACTIVE => _('Enabled'),
		DRULE_STATUS_DISABLED => _('Disabled')
	);

	if (is_null($status)) {
		return $discoveryStatus;
	}
	elseif (isset($discoveryStatus[$status])) {
		return $discoveryStatus[$status];
	}
	else {
		return _('Unknown');
	}
}

function discovery_status2style($status) {
	switch ($status) {
		case DRULE_STATUS_ACTIVE:
			$status = 'off';
			break;
		case DRULE_STATUS_DISABLED:
			$status = 'on';
			break;
		default:
			$status = 'unknown';
			break;
	}

	return $status;
}

function discovery_object_status2str($status = null) {
	$discoveryStatus = array(
		DOBJECT_STATUS_UP => _x('Up', 'discovery status'),
		DOBJECT_STATUS_DOWN => _x('Down', 'discovery status'),
		DOBJECT_STATUS_DISCOVER => _('Discovered'),
		DOBJECT_STATUS_LOST => _('Lost')
	);

	if (is_null($status)) {
		order_result($discoveryStatus);

		return $discoveryStatus;
	}
	elseif (isset($discoveryStatus[$status])) {
		return $discoveryStatus[$status];
	}
	else {
		return _('Unknown');
	}
}

function get_discovery_rule_by_druleid($druleid) {
	return DBfetch(DBselect('SELECT d.* FROM drules d WHERE d.druleid='.zbx_dbstr($druleid)));
}
