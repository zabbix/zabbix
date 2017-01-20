<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

function svc_default_port($type) {
	$types = [
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
	];

	return isset($types[$type]) ? $types[$type] : 0;
}

function discovery_check_type2str($type = null) {
	$types = [
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
	];

	if ($type === null) {
		return $types;
	}

	return isset($types[$type]) ? $types[$type] : false;
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
	$statuses = [
		DRULE_STATUS_ACTIVE => _('Enabled'),
		DRULE_STATUS_DISABLED => _('Disabled')
	];

	if (is_null($status)) {
		return $statuses;
	}

	return isset($statuses[$status]) ? $statuses[$status] : _('Unknown');
}

function discovery_status2style($status) {
	switch ($status) {
		case DRULE_STATUS_ACTIVE:
			$status = ZBX_STYLE_GREEN;
			break;
		case DRULE_STATUS_DISABLED:
			$status = ZBX_STYLE_RED;
			break;
		default:
			$status = ZBX_STYLE_GREY;
			break;
	}

	return $status;
}

function discovery_object_status2str($status = null) {
	$discoveryStatus = [
		DOBJECT_STATUS_UP => _x('Up', 'discovery status'),
		DOBJECT_STATUS_DOWN => _x('Down', 'discovery status'),
		DOBJECT_STATUS_DISCOVER => _('Discovered'),
		DOBJECT_STATUS_LOST => _('Lost')
	];

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
