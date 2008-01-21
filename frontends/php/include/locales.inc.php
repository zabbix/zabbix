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
	# Translate global array $TRANSLATION into constants
	function	process_locales(){
		global $TRANSLATION;
		
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
			"en_gb"=>  S_ENGLISH_GB,
			"cn_zh"=>  S_CHINESE_CN,
			"nl_nl"=>  S_DUTCH_NL,
			"fr_fr"=>  S_FRENCH_FR,
			"de_de"=>  S_GERMAN_DE,
			"hu_hu"=>  S_HUNGARY_HU,
			"it_it"=>  S_ITALIAN_IT,
			"ja_jp"=>  S_JAPANESE_JP,
			"lv_lv"=>  S_LATVIAN_LV,
			"pt_br"=>  S_PORTUGUESE_PT,
			"ru_ru"=>  S_RUSSIAN_RU,
			"sp_sp"=>  S_SPANISH_SP,
			"sv_se"=>  S_SWEDISH_SE,
		);
	}
?>
