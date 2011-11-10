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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php

/**
 * In case gettext functions do not exist, just replacing them with our own,
 * so user can see atleast english translation
 */


if(!function_exists('_')){
	function _($string){
		return $string;
	}
}

if(!function_exists('gettext')){
	function gettext($string){
		return $string;
	}
}

if(!function_exists('ngettext')){
	function ngettext($string1, $string2, $n){
		return $n == 1 ? $string1 : $string2;
	}
}

if (!function_exists('dcgettext')) {
	function dcgettext($domain, $string, $localeType) {
		return $string;
	}
}

function _s($string){
	$arguments = array_slice(func_get_args(), 1);
	return vsprintf(_($string), $arguments);
}

function _n($string1, $string2, $value){
	$arguments = array_slice(func_get_args(), 2);
	return vsprintf(ngettext($string1, $string2, $value), $arguments);
}

/**
 * Translates the string with respect to the given context.
 *
 * If no translation is found, the original string will be returned.
 *
 * @param string $msgid     String to translate
 * @param string $context   Context of the string
 * @param string $domain    Locale domain
 * @return string
 */
function _x($msgid, $context, $domain = 'frontend') {
	$contextString = $context."\004".$msgid;
	$translation = dcgettext($domain, $contextString, LC_MESSAGES);
	return ($translation == $contextString) ? $msgid : $translation;
}
?>
