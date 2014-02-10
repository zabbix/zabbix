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


function media_type2str($type = null) {
	$mediaTypes = array(
		MEDIA_TYPE_EMAIL => _('Email'),
		MEDIA_TYPE_EXEC => _('Script'),
		MEDIA_TYPE_SMS => _('SMS'),
		MEDIA_TYPE_JABBER => _('Jabber'),
		MEDIA_TYPE_EZ_TEXTING => _('Ez Texting'),
		MEDIA_TYPE_REMEDY => _('Remedy Service')
	);

	if ($type === null) {
		natsort($mediaTypes);

		return $mediaTypes;
	}
	elseif (isset($mediaTypes[$type])) {
		return $mediaTypes[$type];
	}
	else {
		return _('Unknown');
	}
}
