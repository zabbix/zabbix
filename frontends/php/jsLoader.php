<?php
// get language translations {{{
require_once('include/locales/en_gb.inc.php');
require_once('include/js.inc.php');
$translations = $TRANSLATION;


if(isset($_GET['lang']) && ($_GET['lang'] != 'en_gb') && preg_match('/^[a-z]{2}_[a-z]{2}$/', $_GET['lang'])){
	require_once('include/locales/'.$_GET['lang'].'.inc.php');
	$translations = array_merge($translations, $TRANSLATION);
}
// }}} get language translations


// available scripts 'scriptFileName' => 'path relative to js/'
$availableJScripts = array(
	'common.js' => '',
	'menu.js' => '',
	'prototype.js' => '',
	'builder.js' => 'scriptaculous/',
	'controls.js' => 'scriptaculous/',
	'dragdrop.js' => 'scriptaculous/',
	'effects.js' => 'scriptaculous/',
	'slider.js' => 'scriptaculous/',
	'sound.js' => 'scriptaculous/',
	'gtlc.js' => '',
	'functions.js' => '',
	'main.js' => '',
	'dom.js' => '',
	'class.bbcode.js' => '',
	'class.calendar.js' => '',
	'class.cdate.js' => '',
	'class.cdebug.js' => '',
	'class.cmap.js' => '',
	'class.cmessages.js' => '',
	'class.cookie.js' => '',
	'class.cscreen.js' => '',
	'class.csuggest.js' => '',
	'class.cswitcher.js' => '',
	'class.ctree.js' => '',
	'class.curl.js' => '',
	'class.rpc.js' => '',
	'class.pmaster.js' => '',
	'class.cviewswitcher.js' => ''
);

$tranStrings = array(
	'gtlc.js' => array('S_ALL_S', 'S_ZOOM', 'S_FIXED_SMALL','S_DYNAMIC_SMALL', 'S_NOW_SMALL', 'S_YEAR_SHORT',
		'S_MONTH_SHORT', 'S_WEEK_SHORT', 'S_DAY_SHORT', 'S_HOUR_SHORT', 'S_MINUTE_SHORT'
	),
	'functions.js' => array('DO_YOU_REPLACE_CONDITIONAL_EXPRESSION_Q', 'S_INSERT_MACRO', 'S_ADD_SERVICE',
		'S_EDIT_SERVICE', 'S_DELETE_SERVICE', 'S_DELETE_SELECTED_SERVICES_Q', 'S_CREATE_LOG_TRIGGER', 'S_DELETE',
		'S_DELETE_KEYWORD_Q', 'S_DELETE_EXPRESSION_Q','S_SIMPLE_GRAPHS', 'S_HISTORY', 'S_HISTORY_AND_SIMPLE_GRAPHS'
	),
	'main.js' => array('S_CLOSE', 'S_NO_ELEMENTS_SELECTED'),
	'class.calendar.js' => array('S_JANUARY', 'S_FEBRUARY', 'S_MARCH', 'S_APRIL', 'S_MAY', 'S_JUNE',
		'S_JULY', 'S_AUGUST', 'S_SEPTEMBER', 'S_OCTOBER', 'S_NOVEMBER', 'S_DECEMBER', 'S_MONDAY_SHORT_BIG',
		'S_TUESDAY_SHORT_BIG', 'S_WEDNESDAY_SHORT_BIG', 'S_THURSDAY_SHORT_BIG', 'S_FRIDAY_SHORT_BIG',
		'S_SATURDAY_SHORT_BIG', 'S_SUNDAY_SHORT_BIG', 'S_TIME', 'S_NOW', 'S_DONE'
	),
	'class.cmap.js' => array('S_ON','S_OFF','S_HIDDEN','S_SHOWN','S_EDIT_MAP_ELEMENT','S_ERROR',
		'S_TYPE','S_LABEL','S_GET_SELEMENTS_FAILED','S_SHOW','S_HIDE',
		'S_LABEL_LOCATION','S_HOST', 'S_MAP','S_TRIGGER','S_SELECT', 'S_HOST_GROUP','S_IMAGE','S_ICON_OK',
		'S_ICON_PROBLEM','S_ICON_UNKNOWN', 'S_ICON_MAINTENANCE','S_ICON_DISABLED','S_ICON_DEFAULT',
		'S_COORDINATE_X','S_COORDINATE_Y', 'S_URL','S_BOTTOM','S_TOP','S_LEFT','S_RIGHT','S_DEFAULT',
		'S_APPLY','S_REMOVE','S_CLOSE','S_PLEASE_SELECT_TWO_ELEMENTS','S_MAP_ELEMENTS','S_CONNECTORS',
		'S_ELEMENT','S_LINK_STATUS_INDICATOR', 'S_LINK','S_EDIT_CONNECTOR','S_TRIGGERS','S_COLOR',
		'S_ADD','S_TYPE_OK','S_COLOR_OK','S_LINK_INDICATORS', 'S_DESCRIPTION',
		'S_LINE','S_BOLD_LINE','S_DOT','S_DASHED_LINE','S_USE_ADVANCED_ICONS',
		'S_WRONG_TYPE_OF_ARGUMENTS_PASSED_TO_FUNCTION', 'S_TWO_ELEMENTS_SHOULD_BE_SELECTED',
		'S_DELETE_SELECTED_ELEMENTS_Q', 'S_PLEASE_SELECT_TWO_ELEMENTS','S_LINK','S_NO_LINKS', 'S_NEW_ELEMENT',
		'S_SELECT','S_SET_TRIGGER', 'S_DELETE_LINK_BETWEEN_SELECTED_ELEMENTS_Q'
	),
	'class.cmessages.js' => array('S_MUTE','S_UNMUTE','S_MESSAGES','S_CLEAR','S_SNOOZE','S_MOVE'
	),
	'class.cookie.js' => array('S_MAX_COOKIE_SIZE_REACHED'
	)
);

if(empty($_GET['files'])){
	$files = array(
		'prototype.js',
		'effects.js',
		'dragdrop.js',
		'common.js',
		'dom.js',
		'class.cdebug.js',
		'class.cdate.js',
		'class.cookie.js',
		'class.curl.js',
		'class.rpc.js',
		'class.bbcode.js',
		'class.csuggest.js',
		'class.cmessages.js',
		'main.js',
		'functions.js',
		'menu.js'
	);
}
else{
	$files = $_GET['files'];
}


$js = 'if(typeof(locale) == "undefined") var locale = {};'."\n";
foreach($files as $file){
	if(isset($tranStrings[$file])){
		foreach($tranStrings[$file] as $str){
			$js .= "locale['".$str."'] = ".zbx_jsvalue($translations[$str]).";";
		}
	}
}

foreach($files as $file){
	if(isset($availableJScripts[$file]))
		$js .= file_get_contents('js/'.$availableJScripts[$file].$file)."\n";
}


$jsLength = strlen($js);
$ETag = md5($jsLength);
if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $ETag){
	header('HTTP/1.1 304 Not Modified');
	header('ETag: '.$ETag);
	exit();
}

header('Content-type: text/javascript; charset=UTF-8');
// breaks if "zlib.output_compression = On"
//	header('Content-length: '.$jsLength);
header('Cache-Control: public, must-revalidate');
header('ETag: '.$ETag);

echo $js;
?>
