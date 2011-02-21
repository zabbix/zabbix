<?php
/*
** ZABBIX
** Copyright (C) 2000-2008 SIA Zabbix
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
	$mbstrings_fnc_exist =
		function_exists('mb_strlen') &&
		function_exists('mb_strtoupper') &&
		function_exists('mb_strpos') &&
		function_exists('mb_substr'); //&&
		// function_exists('mb_stristr') &&
		// function_exists('mb_strstr');

return $mbstrings_fnc_exist;
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
		'en_gb'=>  S_ENGLISH_GB,
		'cn_zh'=>  S_CHINESE_CN,
		'cs_cz'=>  S_CZECH_CZ,
		'nl_nl'=>  S_DUTCH_NL,
		'fr_fr'=>  S_FRENCH_FR,
		'de_de'=>  S_GERMAN_DE,
		'hu_hu'=>  S_HUNGARIAN_HU,
		'it_it'=>  S_ITALIAN_IT,
		'ko_kr'=>  S_KOREAN_KO,
		'ja_jp'=>  S_JAPANESE_JP,
		'lv_lv'=>  S_LATVIAN_LV,
		'pl_pl'=>  S_POLISH_PL,
		'pt_br'=>  S_PORTUGUESE_PT,
		'ru_ru'=>  S_RUSSIAN_RU,
		'sk_sk'=>  S_SLOVAK_SK,
		'sp_sp'=>  S_SPANISH_SP,
		'sv_se'=>  S_SWEDISH_SE,
		'ua_ua'=>  S_UKRAINIAN_UA,
	);
}
?>
