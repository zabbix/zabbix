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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php

function init_mbstrings() {
	$res = true;
	$res &= mbstrings_available();
	ini_set('mbstring.internal_encoding', 'UTF-8');
	$res &= (ini_get('mbstring.internal_encoding') == 'UTF-8');
	ini_set('mbstring.detect_order', 'UTF-8, ISO-8859-1, JIS, SJIS');
	$res &= (ini_get('mbstring.detect_order') == 'UTF-8, ISO-8859-1, JIS, SJIS');
	if ($res) {
		define('ZBX_MBSTRINGS_ENABLED', true);
	}
	return $res;
}

function mbstrings_available() {
	return function_exists('mb_strlen') && function_exists('mb_strtoupper') && function_exists('mb_strpos') && function_exists('mb_substr');
}

function set_zbx_locales() {
	global $ZBX_LOCALES;

	$ZBX_LOCALES = array(
		'en_GB' => _('English (en_GB)'),
		'zh_CN' => _('Chinese (zh_CN)'),
		'cs_CZ' => _('Czech (cs_CZ)'),
		'nl_NL' => _('Dutch (nl_NL)'),
		'fr_FR' => _('French (fr_FR)'),
		'de_DE' => _('German (de_DE)'),
		'el_GR' => _('Greek (el_GR)'),
		'hu_HU' => _('Hungarian (hu_HU)'),
		'it_IT' => _('Italian (it_IT)'),
		'ko_KR' => _('Korean (ko_KR)'),
		'ja_JP' => _('Japanese (ja_JP)'),
		'lv_LV' => _('Latvian (lv_LV)'),
		'pl_PL' => _('Polish (pl_PL)'),
		'pt_BR' => _('Portuguese (pt_BR)'),
		'ru_RU' => _('Russian (ru_RU)'),
		'sk_SK' => _('Slovak (sk_SK)'),
		'es_ES' => _('Spanish (es_ES)'),
		'sv_SE' => _('Swedish (sv_SE)'),
//		'tr_TR' => _('Turkish (tr_TR)'),
		'uk_UA' => _('Ukrainian (uk_UA)')
	);
}

/**
 * Return an array of locale name variants based of language.
 *
 * @param string $language in format 'ru_RU', 'en_EN' and so on
 * @return array a list of possible locale names
 */
function zbx_locale_variants($language) {
	if (stristr($_SERVER['SERVER_SOFTWARE'], 'win32') !== false) {
		return zbx_locale_variants_win($language);
	}
	else {
		return zbx_locale_variants_unix($language);
	}
}

function zbx_locale_variants_unix($language) {
	$postfixes = array(
		'',
		'.utf8',
		'.UTF-8',
		'.iso885915',
		'.ISO8859-1',
		'.ISO8859-2',
		'.ISO8859-4',
		'.ISO8859-5',
		'.ISO8859-15',
		'.ISO8859-13',
		'.CP1131',
		'.CP1251',
		'.CP1251',
		'.CP949',
		'.KOI8-U',
		'.US-ASCII',
		'.eucKR',
		'.eucJP',
		'.SJIS',
		'.GB18030',
		'.GB2312',
		'.GBK',
		'.eucCN',
		'.Big5HKSCS',
		'.Big5',
		'.armscii8',
		'.cp1251',
		'.eucjp',
		'.euckr',
		'.euctw',
		'.gb18030',
		'.gbk',
		'.koi8r',
		'.tcvn'
	);
	$result = array();
	foreach ($postfixes as $postfix) {
		$result[] = $language.$postfix;
	}
	return $result;
}

function zbx_locale_variants_win($language) {
	// windows locales are written like language[_country[.charset]]
	// for a list of supported languages see http://msdn.microsoft.com/en-us/library/39cwe7zf(vs.71).aspx
	$winLanguageName = array(
		'en_gb' => 'english',
		'zh_cn' => 'chinese',
		'cs_cz' => 'czech',
		'nl_nl' => 'dutch',
		'fr_fr' => 'french',
		'de_de' => 'german',
		'hu_hu' => 'hungarian',
		'it_it' => 'italian',
		'ko_kr' => 'korean',
		'ja_jp' => 'japanese',
		'lv_lv' => 'latvian',
		'pl_pl' => 'polish',
		'pt_br' => 'portuguese',
		'ru_ru' => 'russian',
		'sk_sk' => 'slovak',
		'es_es' => 'spanish',
		'sv_se' => 'swedish',
		'uk_ua' => 'ukrainian'
	);
	return array($winLanguageName[strtolower($language)]);
}
?>
