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
 * Returns a list of all used locales.
 *
 * Each locale has the following properties:
 * - name       - the full name of the locale
 * - display    - whether to display the locale in the frontend
 *
 * @return array    an array of locales with locale codes as keys and arrays as values
 */
function getLocales(): array {
	return [
		'en_GB' => ['name' => _('English (en_GB)'),	'display' => true],
		'en_US' => ['name' => _('English (en_US)'),	'display' => true],
		'bg_BG' => ['name' => _('Bulgarian (bg_BG)'),	'display' => false],
		'ca_ES' => ['name' => _('Catalan (ca_ES)'),	'display' => false],
		'zh_CN' => ['name' => _('Chinese (zh_CN)'),	'display' => true],
		'zh_TW' => ['name' => _('Chinese (zh_TW)'),	'display' => false],
		'cs_CZ' => ['name' => _('Czech (cs_CZ)'),	'display' => true],
		'nl_NL' => ['name' => _('Dutch (nl_NL)'),	'display' => false],
		'fi_FI' => ['name' => _('Finnish (fi_FI)'),	'display' => false],
		'fr_FR' => ['name' => _('French (fr_FR)'),	'display' => true],
		'ka_GE' => ['name' => _('Georgian (ka_GE)'),	'display' => false],
		'de_DE' => ['name' => _('German (de_DE)'),	'display' => true],
		'el_GR' => ['name' => _('Greek (el_GR)'),	'display' => false],
		'he_IL' => ['name' => _('Hebrew (he_IL)'),	'display' => true],
		'hu_HU' => ['name' => _('Hungarian (hu_HU)'),	'display' => false],
		'id_ID' => ['name' => _('Indonesian (id_ID)'),	'display' => false],
		'it_IT' => ['name' => _('Italian (it_IT)'),	'display' => true],
		'ko_KR' => ['name' => _('Korean (ko_KR)'),	'display' => true],
		'ja_JP' => ['name' => _('Japanese (ja_JP)'),	'display' => true],
		'lv_LV' => ['name' => _('Latvian (lv_LV)'),	'display' => false],
		'lt_LT' => ['name' => _('Lithuanian (lt_LT)'),	'display' => false],
		'nb_NO' => ['name' => _('Norwegian (nb_NO)'),	'display' => true],
		'fa_IR' => ['name' => _('Persian (fa_IR)'),	'display' => false],
		'pl_PL' => ['name' => _('Polish (pl_PL)'),	'display' => true],
		'pt_BR' => ['name' => _('Portuguese (pt_BR)'),	'display' => true],
		'pt_PT' => ['name' => _('Portuguese (pt_PT)'),	'display' => true],
		'ro_RO' => ['name' => _('Romanian (ro_RO)'),	'display' => false],
		'ru_RU' => ['name' => _('Russian (ru_RU)'),	'display' => true],
		'sk_SK' => ['name' => _('Slovak (sk_SK)'),	'display' => true],
		'es_ES' => ['name' => _('Spanish (es_ES)'),	'display' => false],
		'sv_SE' => ['name' => _('Swedish (sv_SE)'),	'display' => false],
		'tr_TR' => ['name' => _('Turkish (tr_TR)'),	'display' => true],
		'uk_UA' => ['name' => _('Ukrainian (uk_UA)'),	'display' => true],
		'vi_VN' => ['name' => _('Vietnamese (vi_VN)'),	'display' => true]
	];
}

/**
 * Get support URL for specified language.
 *
 * @param string|null $language  ISO639-1 code or null for English support URL.
 *
 * @return string
 */
function getSupportUrl(string $language = null): string {
	$urls = [
		'cs' => 'https://www.zabbix.com/cz/support',
		'fr' => 'https://www.zabbix.com/fr/support',
		'ja' => 'https://www.zabbix.com/jp/support',
		'pt' => 'https://www.zabbix.com/br/support',
		'ru' => 'https://www.zabbix.com/ru/support',
		'zh' => 'https://www.zabbix.com/cn/support'
	];

	return array_key_exists($language, $urls) ? $urls[$language] : 'https://www.zabbix.com/support';
}

/**
 * Get integrations URL for specified language.
 *
 * @param string|null $language  ISO639-1 code or null for English integrations URL.
 *
 * @return string
 */
function getIntegrationsUrl(string $language = null): string {
	$urls = [
		'cs' => 'https://www.zabbix.com/cz/integrations',
		'fr' => 'https://www.zabbix.com/fr/integrations',
		'ja' => 'https://www.zabbix.com/jp/integrations',
		'pt' => 'https://www.zabbix.com/br/integrations',
		'ru' => 'https://www.zabbix.com/ru/integrations',
		'zh' => 'https://www.zabbix.com/cn/integrations'
	];

	return array_key_exists($language, $urls) ? $urls[$language] : 'https://www.zabbix.com/integrations';
}

/**
 * Returns an array of locale name variants based on language.
 *
 * @param string $language Language in format 'ru_RU', 'en_EN' and so on.
 *
 * @return array A list of possible locale names.
 */
function zbx_locale_variants($language) {
	if (stripos(PHP_OS, 'WIN') === 0) {
		return zbx_locale_variants_win($language);
	}

	return zbx_locale_variants_unix($language);
}

function zbx_locale_variants_unix($language) {
	$postfixes = [
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
	];

	$result = [];
	foreach ($postfixes as $postfix) {
		$result[] = $language.$postfix;
	}

	return $result;
}

function zbx_locale_variants_win($language) {
	// Windows locales are written like language[_country[.charset]].
	// For a list of supported languages see:
	// http://msdn.microsoft.com/en-us/library/39cwe7zf(vs.71).aspx
	// http://docs.moodle.org/dev/Table_of_locales#Table
	// This array should contain all array keys from getLocales() function, but in lowercase.
	$win_language_names = [
		'en_gb' => ['English_United Kingdom.1252', 'english-uk'],
		'en_us' => ['English_United States.1252', 'english-usa'],
		'bg_bg' => ['Bulgarian_Bulgaria.1251'],
		'ca_es' => ['Catalan_Spain.1252'],
		'zh_cn' => ['Chinese (Simplified)_People\'s Republic of China.936', 'chinese'],
		'zh_tw' => ['Chinese_Taiwan.950', 'chinese'],
		'cs_cz' => ['Czech_Czech Republic.1250', 'czech'],
		'nl_nl' => ['Dutch_Netherlands.1252', 'dutch'],
		'fi_fi' => ['Finnish_Finland.1252', 'finnish'],
		'fr_fr' => ['French_France.1252', 'french'],
		'ka_ge' => ['Georgian_Georgia.65001', 'georgian'],
		'de_de' => ['German_Germany.1252', 'german'],
		'el_gr' => ['Greek_Greece.1253', 'greek'],
		'he_il' => ['Hebrew_Israel.1255', 'hebrew'],
		'hu_hu' => ['Hungarian_Hungary.1250', 'hungarian'],
		'id_id' => ['Indonesian_indonesia.1252', 'indonesian'],
		'it_it' => ['Italian_Italy.1252', 'italian'],
		'ko_kr' => ['Korean_Korea.949', 'korean'],
		'ja_jp' => ['Japanese_Japan.932', 'japanese'],
		'lv_lv' => ['Latvian_Latvia.1257', 'latvian'],
		'lt_lt' => ['Lithuanian_Lithuania.1257', 'lithuanian'],
		'nb_no' => ['Norwegian_Norway.1252', 'norwegian'],
		'fa_ir' => ['Farsi_Iran.1256', 'farsi'],
		'pl_pl' => ['Polish_Poland.1250', 'polish'],
		'pt_br' => ['Portuguese_Brazil.1252', 'portuguese-brazil'],
		'pt_pt' => ['Portuguese_Portugal.1252', 'portuguese'],
		'ro_ro' => ['Romanian_Romania.1250', 'romanian'],
		'ru_ru' => ['Russian_Russia.1251', 'russian'],
		'sk_sk' => ['Slovak_Slovakia.1250', 'slovak'],
		'es_es' => ['Spanish_Spain.1252', 'spanish'],
		'sv_se' => ['Swedish_Sweden.1252', 'swedish'],
		'tr_tr' => ['Turkish_Turkey.1254', 'turkish'],
		'uk_ua' => ['Ukrainian_Ukraine.1251', 'ukrainian'],
		'vi_vn' => ['Vietnamese_Viet Nam.1258', 'vietnamese']
	];

	return $win_language_names[strtolower($language)];
}
