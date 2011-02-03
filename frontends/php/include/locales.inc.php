<?php
/*
** ZABBIX
** Copyright (C) 2000-2011 SIA Zabbix
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
function init_mbstrings(){
	$res = true;

	$res &= mbstrings_available();

	ini_set('mbstring.internal_encoding', 'UTF-8');
	$res &= (ini_get('mbstring.internal_encoding') == 'UTF-8');

	ini_set('mbstring.detect_order', 'UTF-8, ISO-8859-1, JIS, SJIS');
	$res &= (ini_get('mbstring.detect_order') == 'UTF-8, ISO-8859-1, JIS, SJIS');

	if($res) define('ZBX_MBSTRINGS_ENABLED', true);
return $res;
}

function mbstrings_available(){
	return (function_exists('mb_strlen')
			&& function_exists('mb_strtoupper')
			&& function_exists('mb_strpos')
			&& function_exists('mb_substr'));
}

// Translate global array $TRANSLATION into constants
function process_locales(){
	global $TRANSLATION;
//SDI(count($TRANSLATION).' : '.$TRANSLATION['S_HTML_CHARSET']);
	if(isset($TRANSLATION) && is_array($TRANSLATION)){
		foreach($TRANSLATION as $const=>$label){
			if(!defined($const)) define($const,$label);
		}
	}
	unset($GLOBALS['TRANSLATION']);
}

function set_zbx_locales(){
	global $ZBX_LOCALES;
	$ZBX_LOCALES = array(
		'en_GB'=>  S_ENGLISH_GB,
		'zh_CN'=>  S_CHINESE_CN,
		'cs_CZ'=>  S_CZECH_CZ,
		'nl_NL'=>  S_DUTCH_NL,
		'fr_FR'=>  S_FRENCH_FR,
		'de_DE'=>  S_GERMAN_DE,
		'hu_HU'=>  S_HUNGARIAN_HU,
		'it_IT'=>  S_ITALIAN_IT,
		'ko_KR'=>  S_KOREAN_KR,
		'ja_JP'=>  S_JAPANESE_JP,
		'lv_LV'=>  S_LATVIAN_LV,
		'pl_PL'=>  S_POLISH_PL,
		'pt_BR'=>  S_PORTUGUESE_PT,
		'ru_RU'=>  S_RUSSIAN_RU,
		'sk_SK'=>  _('Slovak (SK)'),
		'es_ES'=>  S_SPANISH_ES,
		'sv_SE'=>  S_SWEDISH_SE,
		'uk_UA'=>  S_UKRAINIAN_UA,
	);
}


/**
 * Return an array of locale name variants based of language.
 *
 * @param string $language in format 'ru_RU', 'en_EN' and so on
 * @return array a list of possible locale names
 */
function zbx_locale_variants($language){
	if(stristr($_SERVER['SERVER_SOFTWARE'], 'win32') !== false)
		$result = zbx_locale_variants_win($language);
	else
		$result = zbx_locale_variants_unix($language);
return $result;
}

function zbx_locale_variants_unix($language){
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
		'.tcvn',
	);

	$result = array();
	foreach($postfixes as $postfix){
		$result[] = $language.$postfix;
	}
return $result;
}

function zbx_locale_variants_win($language){
// windows locales are written like language[_country[.charset]]
	$winLanguageName = array(
		'en_gb'=>  'english',
		'zh_cn'=>  'chinese',
		'cs_cz'=>  'czech',
		'nl_nl'=>  'dutch',
		'fr_fr'=>  'french',
		'de_de'=>  'german',
		'hu_hu'=>  'hungarian',
		'it_it'=>  'italian',
		'ko_kr'=>  'korean',
		'ja_jp'=>  'japanese',
		'lv_lv'=>  'latvian',
		'pl_pl'=>  'polish',
		'pt_br'=>  'portuguese',
		'ru_ru'=>  'russian',
		'es_es'=>  'spanish',
		'sv_se'=>  'swedish',
		'uk_ua'=>  'ukrainian'
	);

	$winLang = $winLanguageName[strtolower($language)];

	$result = array($winLang);

return $result;
}
?>
