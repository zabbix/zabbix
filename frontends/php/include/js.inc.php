<?php

/* function:
 *     zbx_jsvalue
 *
 * description:
 *	convert PHP variable to string version
 *      of JavaScrip style 
 *
 * author: Eugene Grigorjev
 */
function zbx_jsvalue($value)
{
	if(!is_array($value)) 
	{
		if(is_object($value)) return unpack_object($value);
		if(is_string($value)) return '\''.str_replace('\'','\\\'',			/*  '	=> \'	*/
							str_replace("\n", '\n', 		/*  LF	=> \n	*/
								str_replace("\\", "\\\\", 	/*  \	=> \\	*/
									str_replace("\r", '', 	/*  CR	=> remove */
										($value))))).'\'';
		if(is_null($value)) return 'null';
		return strval($value);
	}

	if(count($value) == 0) return '[]';

	foreach($value as $id => $v)
	{
		if(!isset($is_object) && is_string($id)) $is_object = true;

		$value[$id] = (isset($is_object) ? '\''.$id.'\' : ' : '').zbx_jsvalue($v);
	}

	if(isset($is_object))
		return '{'.implode(',',$value).'}';
	else
		return '['.implode(',',$value).']';
}

/* function:
 *     zbx_add_post_js
 *
 * description:
 *	add JavaScript for calling after page loaging.
 *
 * author: Eugene Grigorjev
 */
function zbx_add_post_js($script)
{
	global $ZBX_PAGE_POST_JS;

	$ZBX_PAGE_POST_JS[] = $script;
}


function	get_js_sizeable_graph($dom_graph_id,$url){

return new CScript('
	<script language="JavaScript" type="text/javascript">
	<!--
		A_SBOX["'.$dom_graph_id.'"] = new Object;
		A_SBOX["'.$dom_graph_id.'"].shiftT = 17;
		A_SBOX["'.$dom_graph_id.'"].shiftL = 10;

		var ZBX_G_WIDTH;
		if(window.innerWidth) ZBX_G_WIDTH=window.innerWidth; 
		else ZBX_G_WIDTH=document.body.clientWidth;
		
		ZBX_G_WIDTH-= 80;

		insert_sizeable_graph('.zbx_jsvalue($dom_graph_id).','.zbx_jsvalue($url).');
	-->
	</script>');
}


function	get_dynamic_chart($dom_graph_id,$img_src,$width=0){
	if(is_int($width) && $width > 0) $img_src.= url_param($width, false, 'width');
	$result = new CScript('
		<script language="JavaScript" type="text/javascript">
		<!--
		var width = "'.((!(is_int($width) && $width > 0)) ? $width : '').'";
		var img_src = "'.$img_src.'";
		
		A_SBOX["'.$dom_graph_id.'"] = new Object;
		A_SBOX["'.$dom_graph_id.'"].shiftT = 17;
		A_SBOX["'.$dom_graph_id.'"].shiftL = 10;

		var ZBX_G_WIDTH;
	
		if(width!=""){
			if(window.innerWidth) ZBX_G_WIDTH=window.innerWidth; 
			else ZBX_G_WIDTH=document.body.clientWidth;
			
			ZBX_G_WIDTH-= 80;
	
			ZBX_G_WIDTH+= parseInt(width);
			width = "&width=" + ZBX_G_WIDTH;
		}
		else{
			ZBX_G_WIDTH = '.$width.';
		}
		
		document.write(\'<img src="\'+img_src + width +\'" alt="chart" id="'.$dom_graph_id.'" />\');
		-->
		</script>');
return $result;
}

function insert_showhint_javascript(){
	global $page;
	if(defined('SHOW_HINT_SCRIPT_INSERTTED') || (PAGE_TYPE_HTML != $page['type'])) return;	
	define('SHOW_HINT_SCRIPT_INSERTTED', 1);

	echo '<script type="text/javascript" src="js/showhint.js"></script>';	
}

function redirect($url,$timeout=null){
	zbx_flush_post_cookies();

	echo '<script language="JavaScript" type="text/javascript">';
	if( is_numeric($timeout) ) { 
		echo 'setTimeout(\'window.location="'.$url.'"\','.($timeout*1000).')';
	} 
	else {
		echo 'window.location = "'.$url.'";';
	}
	echo '</script>';
}

function simple_js_redirect($url,$timeout=null){
	echo '<script language="JavaScript" type="text/javascript">';
	if( is_numeric($timeout) ) { 
		echo 'setTimeout(\'window.location="'.$url.'"\','.($timeout*1000).')';
	} 
	else {
		echo 'window.location = "'.$url.'";';
	}
	echo '</script>';
}

function	play_sound($filename){

	echo '<script language="javascript" type="text/javascript">
	
	if (IE){
		document.writeln(\'<bgsound src="'.$filename.'" loop="0" />\');
	}
	else{
		document.writeln(\'<embed src="'.$filename.'" autostart="true" width="0" height="0" loop="0" />\');
		document.writeln(\'<noembed><bgsound src="'.$filename.'" loop="0" /></noembed>\');
	}
	</script>';
}


function	SetFocus($frm_name, $fld_name){
	echo '<script language="javascript" type="text/javascript">
	<!--
		document.forms["'.$frm_name.'"].elements["'.$fld_name.'"].focus();
	//-->
	</script>';
}

function	Alert($msg){
	echo '<script language="javascript" type="text/javascript">
	<!--
		alert("'.$msg.'");
	//-->
	</script>';
}

function insert_js_function($fnct_name){
	switch($fnct_name){
		case 'add_item_variable':
			echo '<script type="text/javascript">
					<!--
					function add_item_variable(s_formname,x_value){
						if(add_variable(null, "itemid[]", x_value, s_formname, window.opener.document)){
							var o_form;
					
							if( !(o_form = window.opener.document.forms[s_formname]) )
								 throw "Missed form with name ["+s_formname+"].";
					
							var element = o_form.elements["itemid"];
							if(element) element.name = "itemid[]";
					
							o_form.submit();
						}
					
						close_window();
							return true;
					}
					-->
				 </script>';
			break;
		default:
			break;
	}
}

function insert_js($script){
print('<script type="text/javascript">'."\n".$script."\n".'</script>');
}
?>
