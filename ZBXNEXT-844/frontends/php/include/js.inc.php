<?php

/**
 * Convert PHP variable to string version of JavaScript style
 * @author Eugene Grigorjev
 * @param mixed $value
 * @param bool $asObject return string containing javascript object
 * @param bool $addQuotes whether quotes should be added at the beginning and at the end of string
 * @return string
 */
function zbx_jsvalue($value, $asObject=false, $addQuotes=true){
	if(!is_array($value)) {
		if(is_object($value)){
			return unpack_object($value);
		}
		elseif(is_string($value)){
			$escaped = str_replace("\r", '', $value); // removing caret returns
			$escaped = str_replace("\\", "\\\\", $escaped); // escaping slashes: \ => \\
			$escaped = str_replace('"', '\"', $escaped); // escaping quotes: " => \"
			$escaped = str_replace("\n", '\n', $escaped); // changing LF to '\n' string
			$escaped = str_replace('\'', '\\\'', $escaped); // escaping single quotes: ' => \'
			$escaped = str_replace('/', '\/', $escaped); // escaping forward slash: / => \/
			if($addQuotes){
				$escaped = "'".$escaped."'";
			}
			return $escaped;
		}
		elseif(is_null($value)){
			return 'null';
		}
		else{
			return strval($value);
		}
	}
	elseif(count($value) == 0){
		return $asObject ? '{}' : '[]';
	}

	foreach($value as $id => $v){
		if((!isset($is_object) && is_string($id)) || $asObject) $is_object = true;
		$value[$id] = (isset($is_object) ? '\''.$id.'\' : ' : '').zbx_jsvalue($v, $asObject, $addQuotes);
	}

	if(isset($is_object)){
		return '{'.implode(',',$value).'}';
	}
	else{
		return '['.implode(',',$value).']';
	}
}

/* function:
 *     zbx_add_post_js
 *
 * description:
 *	add JavaScript for calling after page loading.
 *
 * author: Eugene Grigorjev
 */
function zbx_add_post_js($script){
	global $ZBX_PAGE_POST_JS;

	$ZBX_PAGE_POST_JS[] = $script;
}

function zbx_addJSLocale($to_translate){
	global $JS_TRANSLATE;

	zbx_value2array($to_translate);
	if(empty($JS_TRANSLATE)) $JS_TRANSLATE = array();

	foreach($to_translate as $tnum => $string){
		if(defined($string)) $JS_TRANSLATE[$string] = constant($string);
		else $JS_TRANSLATE[$string] = 'UNTRANSLATED['.$string.']';
	}
}

function inseret_javascript_for_editable_combobox(){
	if(defined('EDITABLE_COMBOBOX_SCRIPT_INSERTTED')) return;
	define('EDITABLE_COMBOBOX_SCRIPT_INSERTTED', 1);

	$js = 'function CEditableComboBoxInit(obj){
		var opt = obj.options;

		if(obj.value) obj.oldValue = obj.value;

		for (var i = 0; i < opt.length; i++)
			if (-1 == opt.item(i).value)
				return;

		opt = document.createElement("option");
		opt.value = -1;
		opt.text = "('.S_OTHER_SMALL.' ...)";

		if(!obj.options.add)
			obj.insertBefore(opt, obj.options.item(0));
		else
			obj.options.add(opt, 0);

		return;
	}

	function CEditableComboBoxOnChange(obj,size){
		if(-1 != obj.value){
			obj.oldValue = obj.value;
		}
		else{
			var new_obj = document.createElement("input");
			new_obj.type = "text";
			new_obj.name = obj.name;
			if(size && size > 0){
				new_obj.size = size;
			}
			new_obj.className = obj.className;
			if(obj.oldValue) new_obj.value = obj.oldValue;
			obj.parentNode.replaceChild(new_obj, obj);
			new_obj.focus();
			new_obj.select();
		}
	}';

	insert_js($js);
}

function insert_show_color_picker_javascript(){
	global $SHOW_COLOR_PICKER_SCRIPT_ISERTTED;

	if($SHOW_COLOR_PICKER_SCRIPT_ISERTTED) return;
	$SHOW_COLOR_PICKER_SCRIPT_ISERTTED = true;

	$table = new CTable();

// gray colors
	$row = array();
	foreach(array('0','1','2','3','4','5','6','7','8','9','A','B','C','D','E','F') as $c){
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

	$brigs  = array(
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

	foreach($colors as $c){
		$row = array();
		foreach($brigs as $br){
			$r = $br[$c['r']];
			$g = $br[$c['g']];
			$b = $br[$c['b']];

			$color = $r.$r.$g.$g.$b.$b;
			$row[] = new CColorCell(null, $color, 'set_color("'.$color.'");');
		}
		$table->addRow($row);
	}

	$cancel = new CSpan(S_CANCEL, 'link');
	$cancel->setAttribute('onclick', 'javascript:hide_color_picker();');

	$script = 'var color_picker = null;
				var curr_lbl = null;
				var curr_txt = null;'."\n";

	$tmp = array($table, $cancel);
	$script.= "var color_table = ".zbx_jsvalue(unpack_object($tmp))."\n";
	insert_js($script);

	zbx_add_post_js('create_color_picker();');
}

function insert_javascript_for_tweenbox(){
	global $page;
	if(defined('SHOW_TWINBOX_SCRIPT_INSERTTED') || (PAGE_TYPE_HTML != $page['type'])) return;
	define('SHOW_TWINBOX_SCRIPT_INSERTTED', 1);

	$js = '
		function moveListBoxSelectedItem(formname,objname,from,to,action){
			var result = true

			from = $(from);
			to = $(to);

			var j = 0;
			var i = 0;
			while(i<from.options.length){

				if((from.options[i].selected == true) && from.options[i].disabled != true) {
					//	from.options[i].selected = false;

					var temp = from.options[i].cloneNode(true);

					if(action.toLowerCase() == "add"){
						result &= create_var(formname, objname+"["+from.options[i].value+"]", from.options[i].value, false);
					}
					else if(action.toLowerCase() == "rmv"){
						result &= remove_element(objname+"["+from.options[i].value+"]","input");
					}

					while(true){
						if(to.options.length == 0){
							$(to).insert(from.options[i]);
							break;
						}

						if(from.options[i].innerHTML.toLowerCase() < to.options[j].innerHTML.toLowerCase()){
							$(to.options[j]).insert({before:from.options[i]});
							break;
						}

						if((typeof(to.options[j+1]) == "undefined") || is_null(to.options[j+1])){  // is_null 4 IE =)
							$(to.options[j]).insert({after:from.options[i]});
							break;
						}

						j++;
					}

					continue;
				}
				i++;
			}

		return result;
		}';

	insert_js($js);
	zbx_add_post_js('if(IE6 || IE7 ) $$("select option[disabled]").each(function(e){e.setStyle({color: "gray"});});');
}

function insert_javascript_for_visibilitybox(){
	if(defined('CVISIBILITYBOX_JAVASCRIPT_INSERTED')) return;
	define('CVISIBILITYBOX_JAVASCRIPT_INSERTED', 1);

	$js = 'function visibility_status_changeds(value, obj_name, replace_to){
			var obj = document.getElementsByName(obj_name);

			if(obj.length <= 0){
				obj = new Array(document.getElementById(obj_name));
			}

			if((obj.length <= 0) || is_null(obj[0])) throw "'.S_CANNOT_FIND_OBJECTS_WITH_NAME.' [" + obj_name +"]";

			for(i = obj.length-1; i>=0; i--){
				if(replace_to && replace_to != ""){
					if(obj[i].originalObject){
						var old_obj = obj[i].originalObject;
						old_obj.originalObject = obj[i];
						obj[i].parentNode.replaceChild(old_obj, obj[i]);
					}
					else if(!value){
						try{
							var new_obj = document.createElement("a");
							new_obj.setAttribute("name",obj[i].name);
							new_obj.setAttribute("id",obj[i].id);
						}
						catch(e){
							throw "'.S_CANNOT_CREATE_NEW_ELEMENT.'";
						}

						new_obj.style.textDecoration = "none";
						new_obj.innerHTML = replace_to;
						new_obj.originalObject = obj[i];
						obj[i].parentNode.replaceChild(new_obj, obj[i]);
					}
					else{
						throw "Missing originalObject for restoring";
					}
				}
				else{
					value = value?"visible":"hidden";
					obj[i].style.visibility = value;
				}
			}
		}';

	insert_js($js);
}

function play_sound($filename){
	insert_js('	if(IE){
			document.writeln(\'<bgsound src="'.$filename.'" loop="0" />\');
		}
		else{
			document.writeln(\'<embed src="'.$filename.'" autostart="true" width="0" height="0" loop="0" />\');
			document.writeln(\'<noembed><bgsound src="'.$filename.'" loop="0" /></noembed>\');
		}');
}


function setFocus($frm_name, $fld_name){
	insert_js('document.forms["'.$frm_name.'"].elements["'.$fld_name.'"].focus();');
}

function alert($msg){
	insert_js('alert("'.$msg.'");');
}

function insert_js_function($fnct_name){
	switch($fnct_name){
		case 'add_item_variable':
			insert_js('function add_item_variable(s_formname,x_value){
				if(add_variable(null, "itemid["+x_value+"]", x_value, s_formname, window.opener.document)){
					var o_form;

					if( !(o_form = window.opener.document.forms[s_formname]) )
						throw "Missing form with name ["+s_formname+"].";

					var element = o_form.elements["itemid"];
					if(element) element.name = "itemid["+element.value+"]";

					o_form.submit();
				}

				close_window();
					return true;
			}');
			break;
		case 'add_media':
			insert_js('function add_media(formname,media,mediatypeid,sendto,period,active,severity){
				var form = window.opener.document.forms[formname];
				var media_name = (media > -1)?"user_medias["+media+"]":"new_media";

				if(!form){
					close_window();
				return false;
				}

				window.opener.create_var(form,media_name+"[mediatypeid]",mediatypeid);
				window.opener.create_var(form,media_name+"[sendto]",sendto);
				window.opener.create_var(form,media_name+"[period]",period);
				window.opener.create_var(form,media_name+"[active]",active);
				window.opener.create_var(form,media_name+"[severity]",severity);

				form.submit();
				close_window();
			return true;
			}');
			break;
		case 'add_graph_item':
			insert_js('function add_graph_item(formname,itemid,color,drawtype,sortorder,yaxisside,calc_fnc,type,periods_cnt){
					var form = window.opener.document.forms[formname];

					if(!form){
						close_window();
					return false;
					}

					window.opener.create_var(form,"new_graph_item[itemid]",itemid);
					window.opener.create_var(form,"new_graph_item[color]",color);
					window.opener.create_var(form,"new_graph_item[drawtype]",drawtype);
					window.opener.create_var(form,"new_graph_item[sortorder]",sortorder);
					window.opener.create_var(form,"new_graph_item[yaxisside]",yaxisside);
					window.opener.create_var(form,"new_graph_item[calc_fnc]",calc_fnc);
					window.opener.create_var(form,"new_graph_item[type]",type);
					window.opener.create_var(form,"new_graph_item[periods_cnt]",periods_cnt);

					form.submit();
					close_window();
					return true;
				}');
			break;
		case 'update_graph_item':
			insert_js('function update_graph_item(formname,list_name,gid,itemid,color,drawtype,sortorder,yaxisside,calc_fnc,type,periods_cnt){
				var form = window.opener.document.forms[formname];

				if(!form){
					close_window();
				return false;
				}

				window.opener.create_var(form,list_name + "[" + gid + "][itemid]",itemid);
				window.opener.create_var(form,list_name + "[" + gid + "][color]",color);
				window.opener.create_var(form,list_name + "[" + gid + "][drawtype]",drawtype);
				window.opener.create_var(form,list_name + "[" + gid + "][sortorder]",sortorder);
				window.opener.create_var(form,list_name + "[" + gid + "][yaxisside]",yaxisside);
				window.opener.create_var(form,list_name + "[" + gid + "][calc_fnc]",calc_fnc);
				window.opener.create_var(form,list_name + "[" + gid + "][type]",type);
				window.opener.create_var(form,list_name + "[" + gid + "][periods_cnt]",periods_cnt);

				form.submit();
				close_window();
				return true;
			}');
			break;
		case 'add_bitem':
			insert_js('function add_bitem(formname,caption,itemid,color,calc_fnc,axisside){
					var form = window.opener.document.forms[formname];

					if(!form){
						close_window();
					return false;
					}

					window.opener.create_var(form,"new_graph_item[caption]",caption);
					window.opener.create_var(form,"new_graph_item[itemid]",itemid);
					window.opener.create_var(form,"new_graph_item[color]",color);
					window.opener.create_var(form,"new_graph_item[calc_fnc]",calc_fnc);
					window.opener.create_var(form,"new_graph_item[axisside]",axisside);
					form.submit();
					close_window();
					return true;
				}');
			break;
		case 'update_bitem':
			insert_js('function update_bitem(formname,list_name,gid,caption,itemid,color,calc_fnc,axisside){
				var form = window.opener.document.forms[formname];

				if(!form){
					close_window();
				return false;
				}

				window.opener.create_var(form,list_name + "[" + gid + "][caption]",caption);
				window.opener.create_var(form,list_name + "[" + gid + "][itemid]",itemid);
				window.opener.create_var(form,list_name + "[" + gid + "][color]",color);
				window.opener.create_var(form,list_name + "[" + gid + "][calc_fnc]",calc_fnc);
				window.opener.create_var(form,list_name + "[" + gid + "][axisside]",axisside);

				form.submit();
				close_window();
				return true;
			}');
			break;
		case 'add_period':
			insert_js('function add_period(formname,caption,since,till,color){
					var form = window.opener.document.forms[formname];

					if(!form){
						close_window();
					return false;
					}

					window.opener.create_var(form,"new_period[caption]",caption);
					window.opener.create_var(form,"new_period[report_timesince]",since);
					window.opener.create_var(form,"new_period[report_timetill]",till);
					window.opener.create_var(form,"new_period[color]",color);

					form.submit();
					close_window();
					return true;
				}');
			break;
		case 'update_period':
			insert_js('function update_period(pid, formname,caption,since,till,color){
				var form = window.opener.document.forms[formname];

				if(!form){
					close_window();
				return false;
				}

				window.opener.create_var(form,"periods["+pid+"][caption]",caption);
				window.opener.create_var(form,"periods["+pid+"][report_timesince]",since);
				window.opener.create_var(form,"periods["+pid+"][report_timetill]",till);
				window.opener.create_var(form,"periods["+pid+"][color]",color);

				form.submit();
				close_window();
				return true;
			}');
		break;
		case 'addSelectedValues':
			insert_js('
				function addSelectedValues(form, object){
					form = $(form);
					if(is_null(form)) return close_window();

					var parent = window.opener;
					if(!parent) return close_window();

					var items = { "object": object, "values": new Array() };

					var chkBoxes = form.getInputs("checkbox");
					for(var i=0; i < chkBoxes.length; i++){
						if(chkBoxes[i].checked && (chkBoxes[i].name.indexOf("all_") < 0)){
							items["values"].push(chkBoxes[i].value);
						}
					}

					parent.addPopupValues(items);
					close_window();
				}');
		break;
		case 'addValue':
			insert_js('
				function addValue(object, singleValue) {
					var parent = window.opener;
					if(!parent) return close_window();

					var items = { "object": object, "values": new Array(singleValue) };

					parent.addPopupValues(items);
					close_window();
				}');
		break;
		case 'check_all':
			insert_js('
				function check_all(objname, value) {
					$(objname).getInputs("checkbox").each(function(e){ e.checked = value });
				}');
		break;
		case 'removeSelectedItems':
			insert_js('function removeSelectedItems(formobject, name){
					formobject = $(formobject);
					if(is_null(formobject)) return false;

					for(var i=0; i < formobject.options.length; i++){
						if(!isset(i, formobject.options)) continue;

						if(formobject.options[i].selected){
							var obj = $(name+"["+formobject.options[i].value+"]");
							if(!is_null(obj)) obj.remove();
						}
					}
				}
			');
		break;
		default:
			insert_js('throw("JS function not found ['.$fnct_name.']");');
			break;
	}
};

function insert_js($script){
	print('<script type="text/javascript">// <![CDATA['."\n".$script."\n".'// ]]></script>');
}

?>
