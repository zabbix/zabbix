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
		'en_US' => _('English (en_US)'),
		'bg_BG' => _('Bulgarian (bg_BG)'),
		'zh_CN' => _('Chinese (zh_CN)'),
		'zh_TW' => _('Chinese (zh_TW)'),
		'cs_CZ' => _('Czech (cs_CZ)'),
		'nl_NL' => _('Dutch (nl_NL)'),
		'fi_FI' => _('Finnish (fi_FI)'),
		'fr_FR' => _('French (fr_FR)'),
		'de_DE' => _('German (de_DE)'),
		'el_GR' => _('Greek (el_GR)'),
		'hu_HU' => _('Hungarian (hu_HU)'),
		'id_ID' => _('Indonesian (id_ID)'),
		'it_IT' => _('Italian (it_IT)'),
		'ko_KR' => _('Korean (ko_KR)'),
		'ja_JP' => _('Japanese (ja_JP)'),
		'lv_LV' => _('Latvian (lv_LV)'),
		'lt_LT' => _('Lithuanian (lt_LT)'),
		'fa_IR' => _('Persian (fa_IR)'),
		'pl_PL' => _('Polish (pl_PL)'),
		'pt_BR' => _('Portuguese (pt_BR)'),
		'pt_PT' => _('Portuguese (pt_PT)'),
		'ro_RO' => _('Romanian (ro_RO)'),
		'ru_RU' => _('Russian (ru_RU)'),
		'sk_SK' => _('Slovak (sk_SK)'),
		'es_ES' => _('Spanish (es_ES)'),
		'sv_SE' => _('Swedish (sv_SE)'),
		'tr_TR' => _('Turkish (tr_TR)'),
		'uk_UA' => _('Ukrainian (uk_UA)')
	);
}

/**
 * Return an array of locale name variants based on language.
 *
 * @param string $language in format 'ru_RU', 'en_EN' and so on
 * @return array a list of possible locale names
 */
function zbx_locale_variants($language) {
	if ((stristr($_SERVER['SERVER_SOFTWARE'], 'win32') !== false) || (stristr($_SERVER['SERVER_SOFTWARE'], 'win64') !== false)) {
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
	// for a list of supported languages see:
	// http://msdn.microsoft.com/en-us/library/39cwe7zf(vs.71).aspx
	// http://docs.moodle.org/dev/Table_of_locales#Table
	$winLanguageName = array(
		'en_gb' => array('English_United Kingdom.1252', 'english-uk'),
		'en_us' => array('English_United States.1252', 'english-usa'),
		'bg_bg' => array('Bulgarian_Bulgaria.1251'),
		'zh_cn' => array('Chinese (Simplified)_People\'s Republic of China.936', 'chinese'),
		'zh_tw' => array('Chinese_Taiwan.950', 'chinese'),
		'cs_cz' => array('Czech_Czech Republic.1250', 'czech'),
		'nl_nl' => array('Dutch_Netherlands.1252', 'dutch'),
		'fi_fi' => array('Finnish_Finland.1252', 'finnish'),
		'fr_fr' => array('French_France.1252', 'french'),
		'de_de' => array('German_Germany.1252', 'german'),
		'el_gr' => array('Greek_Greece.1253', 'greek'),
		'hu_hu' => array('Hungarian_Hungary.1250', 'hungarian'),
		'id_id' => array('Indonesian_indonesia.1252', 'indonesian'),
		'it_it' => array('Italian_Italy.1252', 'italian'),
		'ko_kr' => array('Korean_Korea.949', 'korean'),
		'ja_jp' => array('Japanese_Japan.932', 'japanese'),
		'lv_lv' => array('Latvian_Latvia.1257', 'latvian'),
		'lt_lt' => array('Lithuanian_Lithuania.1257', 'lithuanian'),
		'fa_ir' => array('Farsi_Iran.1256', 'farsi'),
		'pl_pl' => array('Polish_Poland.1250', 'polish'),
		'pt_br' => array('Portuguese_Brazil.1252', 'portuguese-brazil'),
		'pt_pt' => array('Portuguese_Portugal.1252', 'portuguese'),
		'ro_ro' => array('Romanian_Romania.1250', 'romanian'),
		'ru_ru' => array('Russian_Russia.1251', 'russian'),
		'sk_sk' => array('Slovak_Slovakia.1250', 'slovak'),
		'es_es' => array('Spanish_Spain.1252', 'spanish'),
		'sv_se' => array('Swedish_Sweden.1252', 'swedish'),
		'tr_tr' => array('Turkish_Turkey.1254', 'turkish'),
		'uk_ua' => array('Ukrainian_Ukraine.1251', 'ukrainian')
	);
	return $winLanguageName[strtolower($language)];
}
?>
