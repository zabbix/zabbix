<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
 * @deprecated use CJs::encodeJson() instead
 * @see CJs::encodeJson()
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
		$is_object |= is_string($key);
		$escaped_key = $is_object ? '"'.zbx_jsvalue($key, false, false).'":' : '';
		$v = $escaped_key.zbx_jsvalue($v, $as_object, $addQuotes);
	}
	unset($v);

	return $is_object ? '{'.implode(',', $value).'}' : '['.implode(',', $value).']';
}

function encodeValues(&$value, $encodeTwice = true) {
	if (is_string($value)) {
		$value = htmlentities($value, ENT_COMPAT, 'UTF-8');
		if ($encodeTwice) {
			$value = htmlentities($value, ENT_COMPAT, 'UTF-8');
		}
	}
	elseif (is_array(($value))) {
		foreach ($value as $key => $elem) {
			encodeValues($value[$key]);
		}
	}
	elseif (is_object(($value))) {
		foreach ($value->items as $key => $item) {
			encodeValues($value->items[$key], false);
		}
	}
}

function insert_show_color_picker_javascript() {
	global $SHOW_COLOR_PICKER_SCRIPT_INSERTED;

	if ($SHOW_COLOR_PICKER_SCRIPT_INSERTED) {
		return;
	}
	$SHOW_COLOR_PICKER_SCRIPT_INSERTED = true;
	$table = [];

	$colors = [
		['FF0000','FF0080','BF00FF','4000FF','0040FF','0080FF','00BFFF','00FFFF','00FFBF','00FF00','80FF00','BFFF00','FFFF00','FFBF00','FF8000','FF4000','CC6600','666699'],
		['000000','0F0F0F','1E1E1E','2D2D2D','3C3C3C','4B4B4B','5A5A5A','696969','787878','878787','969696','A5A5A5','B4B4B4','C3C3C3','D2D2D2','E1E1E1','F0F0F0','FFFFFF'],
		['FFEBEE','FCE4EC','F3E5F5','EDE7F6','E8EAF6','E3F2FD','E1F5FE','E0F7FA','E0F2F1','E8F5E9','F1F8E9','F9FBE7','FFFDE7','FFF8E1','FFF3E0','FBE9E7','EFEBE9','ECEFF1'],
		['FFCDD2','F8BBD0','E1BEE7','D1C4E9','C5CAE9','BBDEFB','B3E5FC','B2EBF2','B2DFDB','C8E6C9','DCEDC8','F0F4C3','FFF9C4','FFECB3','FFE0B2','FFCCBC','D7CCC8','CFD8DC'],
		['EF9A9A','F48FB1','CE93D8','B39DDB','9FA8DA','90CAF9','81D4FA','80DEEA','80CBC4','A5D6A7','C5E1A5','E6EE9C','FFF59D','FFE082','FFCC80','FFAB91','BCAAA4','B0BEC5'],
		['E57373','F06292','BA68C8','9575CD','7986CB','64B5F6','4FC3F7','4DD0E1','4DB6AC','81C784','AED581','DCE775','FFF176','FFD54F','FFB74D','FF8A65','A1887F','90A4AE'],
		['EF5350','EC407A','AB47BC','7E57C2','5C6BC0','42A5F5','29B6F6','26C6DA','26A69A','66BB6A','9CCC65','D4E157','FFEE58','FFCA28','FFA726','FF7043','8D6E63','78909C'],
		['F44336','E91E63','9C27B0','673AB7','3F51B5','2196F3','03A9F4','00BCD4','009688','4CAF50','8BC34A','CDDC39','FFEB3B','FFC107','FF9800','FF5722','795548','607D8B'],
		['E53935','D81B60','8E24AA','5E35B1','3949AB','1E88E5','039BE5','00ACC1','00897B','43A047','7CB342','C0CA33','FDD835','FFB300','FB8C00','F4511E','6D4C41','546E7A'],
		['D32F2F','C2185B','7B1FA2','512DA8','303F9F','1976D2','0288D1','0097A7','00796B','388E3C','689F38','AFB42B','FBC02D','FFA000','F57C00','E64A19','5D4037','455A64'],
		['C62828','AD1457','6A1B9A','4527A0','283593','1565C0','0277BD','00838F','00695C','2E7D32','558B2F','9E9D24','F9A825','FF8F00','EF6C00','D84315','4E342E','37474F'],
		['B71C1C','880E4F','4A148C','311B92','1A237E','0D47A1','01579B','006064','004D40','1B5E20','33691E','827717','F57F17','FF6F00','E65100','BF360C','3E2723','263238'],
		['891515','660A3B','370F69','24146D','131A5E','093578','044174','00484B','003930','144618','264E16','615911','B75F11','BF5300','AC3C00','8F2809','2E1D1A','1C252A'],
		['5B0E0E','440727','250A46','180D49','0D113F','062350','002B4D','003032','002620','0D2F10','19340F','413B0B','7A3F0B','7F3700','732800','5F1B06','1F1311','13191C'],
		['2D0707','220313','120523','0C0624','06081F','031128','001526','001819','00131D','061708','0C1A07','201D05','3D1F05','3F1B00','391400','2F0D03','0F0908','090C0E'],
	];

	foreach ($colors as $palette) {
		$row = [];
		foreach ($palette as $color) {
			$row[] = (new CColorCell(null, $color))
				->setTitle('#'.$color)
				->onClick('set_color("'.$color.'");');
		}
		$table[] = (new CDiv($row))->addClass(ZBX_STYLE_COLOR_PICKER);
	}

	$cancel = (new CSimpleButton())
		->addClass(ZBX_STYLE_OVERLAY_CLOSE_BTN)
		->onClick('javascript: hide_color_picker();');

	$tmp = [$cancel, $table];
	insert_js('var color_picker = null,'."\n".
		'curr_lbl = null,'."\n".
		'curr_txt = null,'."\n".
		'color_table = '.CJs::encodeJson(unpack_object($tmp))."\n");
	zbx_add_post_js('create_color_picker();');
}

function insert_javascript_for_visibilitybox() {
	if (defined('CVISIBILITYBOX_JAVASCRIPT_INSERTED')) {
		return null;
	}
	define('CVISIBILITYBOX_JAVASCRIPT_INSERTED', 1);

	$js = '
		function visibility_status_changeds(value, obj_name, replace_to) {
			var obj = document.getElementsByName(obj_name);
			if (obj.length <= 0) {
				obj = [document.getElementById(obj_name)];
			}
			if (obj.length <= 0 || is_null(obj[0])) {
				throw "'._('Cannot find objects with name').' [" + obj_name +"]";
			}

			for (i = obj.length - 1; i >= 0; i--) {
				if (replace_to && replace_to != "") {
					if (obj[i].originalObject) {
						var old_obj = obj[i].originalObject;
						old_obj.originalObject = obj[i];
						obj[i].parentNode.replaceChild(old_obj, obj[i]);
					}
					else if (!value) {
						try {
							var new_obj = document.createElement("a");
							new_obj.setAttribute("name", obj[i].name);
							new_obj.setAttribute("id", obj[i].id);
						}
						catch(e) {
							throw "'._('Cannot create new element').'";
						}
						new_obj.style.textDecoration = "none";
						new_obj.innerHTML = replace_to;
						new_obj.originalObject = obj[i];
						obj[i].parentNode.replaceChild(new_obj, obj[i]);
					}
					else {
						throw "Missing originalObject for restoring";
					}
				}
				else {
					value = value ? "visible" : "hidden";
					obj[i].style.visibility = value;
				}
			}
		}';
	insert_js($js);
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

function insertPagePostJs() {
	global $ZBX_PAGE_POST_JS;

	if ($ZBX_PAGE_POST_JS) {
		echo get_js(implode("\n", $ZBX_PAGE_POST_JS), true);
	}
}
