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
 * In case gettext functions do not exist, just replacing them with our own,
 * so user can see at least English translation.
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
		return ($n == 1) ? $string1 : $string2;
	}
}

/**
 * Translates the string with respect to the given context.
 *
 * @see _x
 *
 * @param string $context
 * @param string $msgId
 *
 * @return string
 */
function pgettext($context, $msgId) {
	$contextString = $context."\004".$msgId;
	$translation = _($contextString);

	return ($translation == $contextString) ? $msgId : $translation;
}

/**
 * Translates the string with respect to the given context and plural forms.
 *
 * @see _xn
 *
 * @param string $context
 * @param string $msgId
 * @param string $msgIdPlural
 * @param string $num
 *
 * @return string
 */
function npgettext($context, $msgId, $msgIdPlural, $num) {
	$contextString = $context."\004".$msgId;
	$contextStringp = $context."\004".$msgIdPlural;
	return ngettext($contextString, $contextStringp, $num);
}

/**
 * Translates the string and substitutes the placeholders with the given parameters.
 * Placeholders must be defined as %1$s, %2$s etc.
 *
 * @param string $string         String optionally containing placeholders to substitute.
 * @param string $param,...      Unlimited number of optional parameters to replace sequential placeholders.
 *
 * @return string
 */
function _s($string) {
	$arguments = array_slice(func_get_args(), 1);

	return _params(_($string), $arguments);
}

/**
 * Translates the string in the correct form with respect to the given numeric parameter. According to gettext
 * standards the numeric parameter must be passed last.
 * Supports unlimited parameters; placeholders must be defined as %1$s, %2$s etc.
 *
 * Examples:
 * _n('%2$s item on host %1$s', '%2$s items on host %1$s', 'Zabbix server', 1) // 1 item on host Zabbix server
 * _n('%2$s item on host %1$s', '%2$s items on host %1$s', 'Zabbix server', 2) // 2 items on host Zabbix server
 *
 * @param string $string1		singular string
 * @param string $string2		plural string
 * @param string $param			parameter to replace the first placeholder
 * @param string $param,...		unlimited number of optional parameters
 *
 * @return string
 */
function _n($string1, $string2) {
	$arguments = array_slice(func_get_args(), 2);

	return _params(ngettext($string1, $string2, end($arguments)), $arguments);
}

/**
 * Translates the string with respect to the given context.
 * If no translation is found, the original string will be used.
 *
 * Example: _x('Message', 'context');
 * returns: 'Message'
 *
 * @param string $message		string to translate
 * @param string $context		context of the string
 *
 * @return string
 */
function _x($message, $context) {
	return ($context == '')
		? _($message)
		: pgettext($context, $message);
}

/**
 * Translates the string with respect to the given context and replaces placeholders with supplied arguments.
 * If no translation is found, the original string will be used. Unlimited number of parameters supplied.
 * Parameter placeholders must be defined as %1$s, %2$s etc.
 *
 * Example: _xs('Message for arg1 "%1$s" and arg2 "%2$s"', 'context', 'arg1Value', 'arg2Value');
 * returns: 'Message for arg1 "arg1Value" and arg2 "arg2Value"'
 *
 * @param string $message       String to translate.
 * @param string $context       Context of the string.
 * @param string $param,...     Unlimited number of optional parameters to replace sequential placeholders.
 *
 * @return string
 */
function _xs($message, $context) {
	$arguments = array_slice(func_get_args(), 2);

	return ($context == '')
		? _params($message, $arguments)
		: _params(pgettext($context, $message), $arguments);
}

/**
 * Translates the string with respect to the given context and plural forms, also replaces placeholders with supplied
 * arguments. If no translation is found, the original string will be used. Unlimited number of parameters supplied.
 *
 * Parameter placeholders must be defined as %1$s, %2$s etc.
 *
 * Example: _xn('%1$s message for arg1 "%2$s"', '%1$s messages for arg1 "%2$s"', 3, 'context', 'arg1Value');
 * returns: '3 messages for arg1 "arg1Value"'
 *
 * @param string $message           String to translate.
 * @param string $messagePlural     String to translate for plural form.
 * @param int    $num               Number to determine usage of plural form, also is used as first replace argument.
 * @param string $context           Context of the string.
 * @param string $param,...         Unlimited number of optional parameters to replace sequential placeholders.
 *
 * @return string
 */
function _xn($message, $messagePlural, $num, $context) {
	$arguments = array_slice(func_get_args(), 4);
	array_unshift($arguments, $num);

	return _params(pgettext($context, ngettext($message, $messagePlural, $num)), $arguments);
}

/**
 * Returns a formatted string.
 *
 * @param string $format		receives already stranlated string with format
 * @param array  $arguments		arguments to replace according to given format
 *
 * @return string
 */
function _params($format, array $arguments) {
	return vsprintf($format, $arguments);
}

/**
 * Initialize locale environment and gettext translations depending on language selected by user.
 *
 * Note: should be called before including file includes/translateDefines.inc.php.
 *
 * @param string $language    Locale language prefix like en_US, ru_RU etc.
 * @param string $error       Message on failure.
 *
 * @return bool    Whether locale could be switched; always true for en_GB.
 */
function setupLocale(string $language, ?string &$error = ''): bool {
	$numeric_locales = [
		'C', 'POSIX', 'en', 'en_US', 'en_US.UTF-8', 'English_United States.1252', 'en_GB', 'en_GB.UTF-8'
	];
	$locale_variants = zbx_locale_variants($language);
	$locale_set = false;
	$error = '';

	ini_set('default_charset', 'UTF-8');
	ini_set('mbstring.detect_order', 'UTF-8, ISO-8859-1, JIS, SJIS');

	// Since LC_MESSAGES may be unavailable on some systems, try to set all of the locales and then make adjustments.
	foreach ($locale_variants as $locale) {
		putenv('LC_ALL='.$locale);
		putenv('LANG='.$locale);
		putenv('LANGUAGE='.$locale);

		if (setlocale(LC_ALL, $locale)) {
			$locale_set = true;
			break;
		}
	}

	// Force PHP to always use a point instead of a comma for decimal numbers.
	setlocale(LC_NUMERIC, $numeric_locales);

	if (function_exists('bindtextdomain')) {
		bindtextdomain('frontend', 'locale');
		bind_textdomain_codeset('frontend', 'UTF-8');
		textdomain('frontend');
	}

	if (!$locale_set && strtolower($language) !== 'en_gb') {
		setlocale(LC_ALL, $numeric_locales);

		$language = htmlspecialchars($language, ENT_QUOTES, 'UTF-8');
		$locale_variants = array_map(function ($locale) {
			return htmlspecialchars($locale, ENT_QUOTES, 'UTF-8');
		}, $locale_variants);

		$error = 'Locale for language "'.$language.'" is not found on the web server. Tried to set: '.
			implode(', ', $locale_variants).'. Unable to translate Zabbix interface.';
	}

	return ($error === '');
}
