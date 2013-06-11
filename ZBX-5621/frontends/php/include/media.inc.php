<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
?>
<?php
function media_type2str($type = null) {
	$media_types = array(
		MEDIA_TYPE_EMAIL => _('Email'),
		MEDIA_TYPE_EXEC => _('Script'),
		MEDIA_TYPE_SMS => _('SMS'),
		MEDIA_TYPE_JABBER => _('Jabber'),
		MEDIA_TYPE_EZ_TEXTING => _('Ez Texting'),
	);

	if (is_null($type)) {
		natsort($media_types);
		return $media_types;
	}
	elseif (isset($media_types[$type])) {
		return $media_types[$type];
	}
	else {
		return _('Unknown');
	}
}

/**
 * Return span array with trigger severities labels and in Profile (Media tab)
 *
 * @param int $severity
 *
 * @return array
 */
function media_severity2str($severity) {
	$result = array();
	$mapping = array();
	$severityStyle = null;

	for ($i = 0; $i <= 5; $i++) {
		$severityStyle = empty($severityStyle) ? 1 : $severityStyle * 2;

		$mapping[] = array(
			'letter' => zbx_substr(getSeverityCaption($i), 0, 1),
			'style' => (($severity & $severityStyle)  ? 'enabled' : NULL)
		);
	}

	foreach ($mapping as $key => $map) {
		$result[$key] = new CSpan($map['letter'], $map['style']);
		$result[$key]->setHint(getSeverityCaption($key).' ('.(isset($map['style']) ? 'on' : 'off').')');
	}

	return $result;
}
?>
