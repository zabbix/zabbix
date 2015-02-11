<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
 * @param bool  $asObject  return string containing javascript object
 * @param bool  $addQuotes whether quotes should be added at the beginning and at the end of string
 *
 * @return string
 */
function zbx_jsvalue($value, $asObject = false, $addQuotes = true) {
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
		return $asObject ? '{}' : '[]';
	}

	foreach ($value as $id => $v) {
		if ((!isset($is_object) && is_string($id)) || $asObject) {
			$is_object = true;
		}
		$value[$id] = (isset($is_object) ? '"'.str_replace('\'', '\\\'', $id).'":' : '').zbx_jsvalue($v, $asObject, $addQuotes);
	}

	if (isset($is_object)) {
		return '{'.implode(',', $value).'}';
	}
	else {
		return '['.implode(',', $value).']';
	}
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

// add JavaScript for calling after page loading
function zbx_add_post_js($script) {
	global $ZBX_PAGE_POST_JS;

	if ($ZBX_PAGE_POST_JS === null) {
		$ZBX_PAGE_POST_JS = array();
	}

	if (!in_array($script, $ZBX_PAGE_POST_JS)) {
		$ZBX_PAGE_POST_JS[] = $script;
	}
}

function insert_show_color_picker_javascript() {
	global $SHOW_COLOR_PICKER_SCRIPT_INSERTED;

	if ($SHOW_COLOR_PICKER_SCRIPT_INSERTED) {
		return;
	}
	$SHOW_COLOR_PICKER_SCRIPT_INSERTED = true;
	$table = new CTable();

	// gray colors
	$row = array();
	foreach (array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B', 'C', 'D', 'E', 'F') as $c) {
		$color = $c.$c.$c.$c.$c.$c;
		$row[] = new CColorCell(null, $color, 'set_color("'.$color.'");');
	}
	$table->addRow($row);

	// other colors
	$colors = array(
		array('r' => 0, 'g' => 0, 'b' => 1),
		array('r' => 0, 'g' => 1, 'b' => 0),
		array('r' => 1, 'g' => 0, 'b' => 0),
		array('r' => 0, 'g' => 1, 'b' => 1),
		array('r' => 1, 'g' => 0, 'b' => 1),
		array('r' => 1, 'g' => 1, 'b' => 0)
	);

	$brigs = array(
		array(0 => '0', 1 => '3'),
		array(0 => '0', 1 => '4'),
		array(0 => '0', 1 => '5'),
		array(0 => '0', 1 => '6'),
		array(0 => '0', 1 => '7'),
		array(0 => '0', 1 => '8'),
		array(0 => '0', 1 => '9'),
		array(0 => '0', 1 => 'A'),
		array(0 => '0', 1 => 'B'),
		array(0 => '0', 1 => 'C'),
		array(0 => '0', 1 => 'D'),
		array(0 => '0', 1 => 'E'),
		array(0 => '3', 1 => 'F'),
		array(0 => '6', 1 => 'F'),
		array(0 => '9', 1 => 'F'),
		array(0 => 'C', 1 => 'F')
	);

	foreach ($colors as $c) {
		$row = array();
		foreach ($brigs as $br) {
			$r = $br[$c['r']];
			$g = $br[$c['g']];
			$b = $br[$c['b']];

			$color = $r.$r.$g.$g.$b.$b;
			$row[] = new CColorCell(null, $color, 'set_color("'.$color.'");');
		}
		$table->addRow($row);
	}

	$cancel = new CSpan(_('Cancel'), 'link');
	$cancel->setAttribute('onclick', 'javascript: hide_color_picker();');

	$tmp = array($table, $cancel);
	$script = '
		var color_picker = null;
		var curr_lbl = null;
		var curr_txt = null;
		var color_table = '.zbx_jsvalue(unpack_object($tmp))."\n";
	insert_js($script);
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

function insert_js_function($fnct_name) {
	switch ($fnct_name) {
		case 'add_item_variable':
			insert_js('
				function add_item_variable(s_formname, x_value) {
					if (add_variable(null, "itemid[" + x_value + "]", x_value, s_formname, window.opener.document)) {
						var o_form;
						if (!(o_form = window.opener.document.forms[s_formname])) {
							throw "Missing form with name [" + s_formname + "].";
						}
						var element = o_form.elements["itemid"];
						if (element) {
							element.name = "itemid[" + element.value + "]";
						}
						o_form.submit();
					}
					close_window();
					return true;
				}');
			break;
		case 'add_media':
			insert_js('
				function add_media(formname, media, mediatypeid, sendto, period, active, severity) {
					var form = window.opener.document.forms[formname];
					var media_name = (media > -1) ? "user_medias[" + media + "]" : "new_media";
					if (!form) {
						close_window();
						return false;
					}
					window.opener.create_var(form, media_name + "[mediatypeid]", mediatypeid);
					window.opener.create_var(form, media_name + "[sendto]", sendto);
					window.opener.create_var(form, media_name + "[period]", period);
					window.opener.create_var(form, media_name + "[active]", active);
					window.opener.create_var(form, media_name + "[severity]", severity);

					form.submit();
					close_window();
					return true;
				}');
			break;
		case 'add_bitem':
			insert_js('
				function add_bitem(formname, caption, itemid, color, calc_fnc, axisside) {
					var form = window.opener.document.forms[formname];
					if (!form) {
						close_window();
						return false;
					}
					window.opener.create_var(form, "new_graph_item[caption]", caption);
					window.opener.create_var(form, "new_graph_item[itemid]", itemid);
					window.opener.create_var(form, "new_graph_item[color]", color);
					window.opener.create_var(form, "new_graph_item[calc_fnc]", calc_fnc);
					window.opener.create_var(form, "new_graph_item[axisside]", axisside);

					form.submit();
					close_window();
					return true;
				}');
			break;
		case 'update_bitem':
			insert_js('
				function update_bitem(formname, list_name, gid, caption, itemid, color, calc_fnc, axisside) {
					var form = window.opener.document.forms[formname];
					if (!form) {
						close_window();
						return false;
					}
					window.opener.create_var(form, list_name + "[" + gid + "][caption]", caption);
					window.opener.create_var(form, list_name + "[" + gid + "][itemid]", itemid);
					window.opener.create_var(form, list_name + "[" + gid + "][color]", color);
					window.opener.create_var(form, list_name + "[" + gid + "][calc_fnc]", calc_fnc);
					window.opener.create_var(form, list_name + "[" + gid + "][axisside]", axisside);

					form.submit();
					close_window();
					return true;
				}');
			break;
		case 'add_period':
			insert_js('
				function add_period(formname, caption, since, till, color) {
					var form = window.opener.document.forms[formname];
					if (!form) {
						close_window();
						return false;
					}
					window.opener.create_var(form, "new_period[caption]", caption);
					window.opener.create_var(form, "new_period[report_timesince]", since);
					window.opener.create_var(form, "new_period[report_timetill]", till);
					window.opener.create_var(form, "new_period[color]", color);

					form.submit();
					close_window();
					return true;
				}');
			break;
		case 'update_period':
			insert_js('
				function update_period(pid, formname, caption, since, till, color) {
					var form = window.opener.document.forms[formname];
					if (!form) {
						close_window();
						return false;
					}
					window.opener.create_var(form, "periods[" + pid + "][caption]", caption);
					window.opener.create_var(form, "periods[" + pid + "][report_timesince]", since);
					window.opener.create_var(form, "periods[" + pid + "][report_timetill]", till);
					window.opener.create_var(form, "periods[" + pid + "][color]", color);

					form.submit();
					close_window();
					return true;
				}');
			break;
		case 'addSelectedValues':
			insert_js('
				function addSelectedValues(form, object, parentId) {
					form = $(form);
					if (is_null(form)) {
						return close_window()
					};
					var parent = window.opener;
					if (!parent) {
						return close_window();
					}

					if (typeof parentId === "undefined") {
						var parentId = null;
					}

					var data = { object: object, values: [], parentId: parentId };
					var chkBoxes = form.getInputs("checkbox");
					for (var i = 0; i < chkBoxes.length; i++) {
						if (chkBoxes[i].checked && (chkBoxes[i].name.indexOf("all_") < 0)) {
							var value = {};
							if (isset(chkBoxes[i].value, popupReference)) {
								value = popupReference[chkBoxes[i].value];
							}
							else {
								value[object] = chkBoxes[i].value;
							}
							data["values"].push(value);
						}
					}
					close_window();

					parent.jQuery(parent.document).trigger("add.popup", data);
				}');
			break;
		case 'addValue':
			insert_js('
				function addValue(object, singleValue, parentId) {
					var parent = window.opener;
					if (!parent) {
						return close_window();
					}
					var value = {};
					if (isset(singleValue, popupReference)) {
						value = popupReference[singleValue];
					}
					else {
						value[object] = singleValue;
					}

					if (typeof parentId === "undefined") {
						var parentId = null;
					}
					var data = { object: object, values: [value], parentId: parentId };

					close_window();

					parent.jQuery(parent.document).trigger("add.popup", data);
				}');
			break;
		case 'addValues':
			insert_js('
				function addValues(frame, values, submitParent) {
					var parentDocument = window.opener.document;
					if (!parentDocument) {
						return close_window();
					}
					var parentDocumentForms = $(parentDocument.body).select("form[name=" + frame + "]");
					var submitParent = submitParent || false;
					var frmStorage = null;

					for (var key in values) {
						if (is_null(values[key])) {
							continue;
						}

						if (parentDocumentForms.length > 0) {
							frmStorage = jQuery(parentDocumentForms[0]).find("#" + key).get(0);
						}
						if (typeof frmStorage === "undefined" || is_null(frmStorage)) {
							frmStorage = parentDocument.getElementById(key);
						}

						if (jQuery(frmStorage).is("span")) {
							jQuery(frmStorage).html(values[key]);
						}
						else {
							frmStorage.value = values[key];
						}
					}
					if (!is_null(frmStorage) && submitParent) {
						frmStorage.form.submit();
					}
					close_window();
				}');
			break;
		case 'check_all':
			insert_js('
				function check_all(objname, value) {
					$(objname).getInputs("checkbox").each(function(e) {
						e.checked = value;
					});
				}');
			break;
		default:
			insert_js('throw("JS function not found ['.$fnct_name.']");');
			break;
	}
};

function insert_js($script, $jQueryDocumentReady = false) {
	echo get_js($script, $jQueryDocumentReady);
}

function get_js($script, $jQueryDocumentReady = false) {
	return $jQueryDocumentReady
		? '<script type="text/javascript">'."\n".'jQuery(document).ready(function() { '.$script.' });'."\n".'</script>'
		: '<script type="text/javascript">'."\n".$script."\n".'</script>';
}

function insertPagePostJs() {
	global $ZBX_PAGE_POST_JS;

	if ($ZBX_PAGE_POST_JS) {
		$js = '';

		foreach ($ZBX_PAGE_POST_JS as $script) {
			$js .= $script."\n";
		}

		if ($js) {
			echo get_js($js, true);
		}
	}
}
