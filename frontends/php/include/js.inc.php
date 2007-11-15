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

function	insert_sizeable_graph($url){

	echo '<script language="JavaScript" type="text/javascript">
		  <!--
				insert_sizeable_graph('.zbx_jsvalue($url).');
		  -->
		  </script>';
}


function	get_dynamic_chart($img_src,$width=0){
	if(is_int($width) && $width > 0) $img_src.= url_param($width, false, 'width');
	$result = '
		<script language="JavaScript" type="text/javascript">
		<!--
		var width = "'.((!(is_int($width) && $width > 0)) ? $width : '').'";
		var img_src = "'.$img_src.'";
		
		if(width!=""){
			var scr_width = 0;
			if(document.body.clientWidth)
				scr_width = document.body.clientWidth;
			else 
				scr_width = document.width;
		
			width = "&width=" + (scr_width - 100 + parseInt(width));
		}
		
		document.write(\'<img alt="chart" src="\'+img_src + width +\'" />\');
		-->
		</script>';
return $result;
}

function insert_showhint_javascript(){
	if(defined('SHOW_HINT_SCRIPT_INSERTTED')) return;
	define('SHOW_HINT_SCRIPT_INSERTTED', 1);
	
	echo '<script type"text/javascript" src="js/showhint.js"></script>';	
}

function Redirect($url,$timeout=null){
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
?>
