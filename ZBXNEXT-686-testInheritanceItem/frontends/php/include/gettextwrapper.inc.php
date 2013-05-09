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

/**
 * In case gettext functions do not exist, just replacing them with our own,
 * so user can see at least english translation
 */
if (!function_exists('_')) {
	/**
	 * Stub gettext function in case gettext is not available.
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	function _($string) {
		return $string;
	}
}

if (!function_exists('ngettext')) {
	/**
	 * Stub gettext function in case gettext is not available. Do not use directly, use _n() instead.
	 *
	 * @see _n
	 *
	 * @param string $string1
	 * @param string $string2
	 * @param string $n
	 *
	 * @return string
	 */
	function ngettext($string1, $string2, $n) {
		return $n == 1 ? $string1 : $string2;
	}
}

if (!function_exists('pgettext')) {
	/**
	 * Stub gettext function in case gettext is not available. Do not use directly, use _x() instead.
	 *
	 * @see _x
	 *
	 * @param string $context
	 * @param string $msgid
	 *
	 * @return string
	 */
	function pgettext($context, $msgid) {
		$contextString = $context."\004".$msgid;
		$translation = _($contextString);
		return ($translation == $contextString) ? $msgid : $translation;
	}
}

if (!function_exists('npgettext')) {
	/**
	 * Stub gettext function in case gettext is not available. Do not use directly, use _xn() instead.
	 *
	 * @see _xn
	 *
	 * @param string $context
	 * @param string $msgid
	 * @param string $msgid_plural
	 * @param string $num
	 *
	 * @return string
	 */
	function npgettext($context, $msgid, $msgid_plural, $num) {
		$contextString = $context."\004".$msgid;
		$contextStringp = $context."\004".$msgid_plural;
		$translation = ngettext($contextString, $contextStringp, $num);
		if ($translation == $contextString || $translation == $contextStringp) {
			return $msgid;
		}
		else {
			return $translation;
		}
	}
}

/**
 * Translates the string and substitutes the placeholders with the given parameters.
 *
 * Placeholders must be defined as %1$s, %2$s etc.
 *
 * @param string $string
 * @param string $param 		parameter to be replace the first placeholder
 * @param string $param,... 	unlimited number of optional parameters
 *
 * @return string
 */
function _s($string) {
	$arguments = array_slice(func_get_args(), 1);
	return vsprintf(_($string), $arguments);
}

/**
 * Translates the string in the correct form with respect to the given numeric parameter.
 *
 * Supports unlimited parameters; placeholders must be defined as %1$s, %2$s etc.
 *
 * Examples:
 * _n('%1$s item on host %2$s', '%1$s items on host %2$s', 1, 'Zabbix server') // 1 item on host Zabbix server
 * _n('%1$s item on host %2$s', '%1$s items on host %2$s', 2, 'Zabbix server') // 2 items on host Zabbix server
 *
 * @param string $string1		singular string
 * @param string $string2		plural string
 * @param string $param 		numeric parameter that will be used to choose the correct form, also gets replaced as
 * 								the first parameter
 * @param string $param,... 	unlimited number of optional parameters
 *
 * @return string
 */
function _n($string1, $string2, $value) {
	$arguments = array_slice(func_get_args(), 2);
	return vsprintf(ngettext($string1, $string2, $value), $arguments);
}

/**
 * Translates the string with respect to the given context and replaces placeholders with supplied arguments.
 * If no translation is found, the original string will be used. Unlimited number of parameters supplied.
 *
 * Parameter placeholders must be defined as %1$s, %2$s etc.
 *
 * Example: _x('Message for arg1 "%1$s" and arg2 "%2$s"', 'context', 'arg1Value', 'arg2Value');
 * returns: 'Message for arg1 "arg1Value" and arg2 "arg2Value"'
 *
 * @param string $message		string to translate
 * @param string $context		context of the string
 * @param string $param 		parameter to be replace the first placeholder
 * @param string $param,... 	unlimited number of optional parameters
 *
 * @return string
 */
function _x($message, $context) {
	$arguments = array_slice(func_get_args(), 2);

	if ($context == '') {
		return vsprintf($message, $arguments);
	}
	else {
		return vsprintf(pgettext($context, $message), $arguments);
	}
}

/**
 * Translates the string with respect to the given context and plural forms, also replaces placeholders with supplied arguments.
 * If no translation is found, the original string will be used. Unlimited number of parameters supplied.
 *
 * Parameter placeholders must be defined as %1$s, %2$s etc.
 *
 * Example: _xn('%1$s message for arg1 "%2$s"', '%1$s messages for arg1 "%2$s"', 3, 'context', 'arg1Value');
 * returns: '3 messages for arg1 "arg1Value"'
 *
 * @param string $message			string to translate
 * @param string $message_plural	string to translate for plural form
 * @param int    $num				number to determine usage of plural form, also is used as first replace argument
 * @param string $context			context of the string
 * @param string $param 			parameter to be replace the first placeholder
 * @param string $param,... 		unlimited number of optional parameters
 *
 * @return string
 */
function _xn($message, $message_plural, $num, $context) {
	$arguments = array_slice(func_get_args(), 4);
	array_unshift($arguments, $num);

	if ($context == '') {
		return vsprintf(ngettext($message, $message_plural, $num), $arguments);
	}
	else {
		return vsprintf(npgettext($context, $message, $message_plural, $num), $arguments);
	}
}

