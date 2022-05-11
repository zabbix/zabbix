<?php
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
 * Convert PHP variable to string version of JavaScript style
 *
 * @deprecated  Use json_encode() instead.
 *
 * @param mixed $value
 * @param bool  $as_object return string containing javascript object
 * @param bool  $addQuotes whether quotes should be added at the beginning and at the end of string
 *
 * @return string
 */
function zbx_jsvalue($value, $as_object = false, $addQuotes = true) {
	if (!is_array($value)) {
		if (is_object($value)) {
			return unpack_object($value);
		}
		elseif (is_string($value)) {
			$escaped = str_replace("\r", '', $value); // removing caret returns
			$escaped = str_replace("\\", "\\\\", $escaped); // escaping slashes: \ => \\
			$escaped = str_replace('"', '\"', $escaped); // escaping quotes: " => \"
			$escaped = str_replace("\n", '\n', $escaped); // changing LF to '\n' string
			$escaped = str_replace('\'', '\\\'', $escaped); // escaping single quotes: ' => \'
			$escaped = str_replace('/', '\/', $escaped); // escaping forward slash: / => \/
			if ($addQuotes) {
				$escaped = "'".$escaped."'";
			}
			return $escaped;
		}
		elseif (is_null($value)) {
			return 'null';
		}
		elseif (is_bool($value)) {
			return ($value) ? 'true' : 'false';
		}
		else {
			return strval($value);
		}
	}
	elseif (count($value) == 0) {
		return $as_object ? '{}' : '[]';
	}

	$is_object = $as_object;

	foreach ($value as $key => &$v) {
		if (is_string($key)) {
			$is_object = true;
		}
		$escaped_key = $is_object ? '"'.zbx_jsvalue($key, false, false).'":' : '';
		$v = $escaped_key.zbx_jsvalue($v, $as_object, $addQuotes);
	}
	unset($v);

	return $is_object ? '{'.implode(',', $value).'}' : '['.implode(',', $value).']';
}

function insert_js($script, $jQueryDocumentReady = false) {
	echo get_js($script, $jQueryDocumentReady);
}

function get_js($script, $jQueryDocumentReady = false) {
	return $jQueryDocumentReady
		? '<script type="text/javascript">'."\n".'jQuery(document).ready(function() { '.$script.' });'."\n".'</script>'
		: '<script type="text/javascript">'."\n".$script."\n".'</script>';
}

// add JavaScript for calling after page loading
function zbx_add_post_js($script) {
	global $ZBX_PAGE_POST_JS;

	if ($ZBX_PAGE_POST_JS === null) {
		$ZBX_PAGE_POST_JS = [];
	}

	if (!in_array($script, $ZBX_PAGE_POST_JS)) {
		$ZBX_PAGE_POST_JS[] = $script;
	}
}

function insertPagePostJs($jQueryDocumentReady = false) {
	global $ZBX_PAGE_POST_JS;

	if ($ZBX_PAGE_POST_JS) {
		echo get_js(implode("\n", $ZBX_PAGE_POST_JS), $jQueryDocumentReady);
	}
}

function getPagePostJs() {
	global $ZBX_PAGE_POST_JS;

	if ($ZBX_PAGE_POST_JS) {
		return implode("\n", $ZBX_PAGE_POST_JS);
	}

	return '';
}
