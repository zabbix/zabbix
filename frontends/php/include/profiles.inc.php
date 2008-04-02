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
/********** USER PROFILE ***********/

//---------- GET USER VALUE -------------
function	get_profile($idx,$default_value=null,$type=PROFILE_TYPE_UNKNOWN){
	global $USER_DETAILS;

	$result = $default_value;

	if($USER_DETAILS["alias"]!=ZBX_GUEST_USER){
		$db_profiles = DBselect('SELECT * FROM profiles WHERE userid='.$USER_DETAILS["userid"].' AND idx='.zbx_dbstr($idx).' ORDER BY profileid ASC');

		if($profile=DBfetch($db_profiles)){
		
			if(PROFILE_TYPE_UNKNOWN == $type) $type = $profile["valuetype"];
	
			if(PROFILE_TYPE_ARRAY == $type){
				$result[] = $profile['value'];
				while($profile=DBfetch($db_profiles)){
					$result[] = $profile['value'];
				}
			}
			else{
				switch($type){
					case PROFILE_TYPE_INT:		
						$result = intval($profile["value"]);
						break;
					case PROFILE_TYPE_STR:
					default:
						$result = strval($profile["value"]);
				}
			}
		}
	}

return $result;
}

//----------- ADD/EDIT USERPROFILE -------------
function	update_profile($idx,$value,$type=PROFILE_TYPE_UNKNOWN){
	global $USER_DETAILS;

	if($USER_DETAILS["alias"]==ZBX_GUEST_USER){
		return false;
	}
	
	if($type==PROFILE_TYPE_UNKNOWN && is_array($value))	$type = PROFILE_TYPE_ARRAY;
	if($type==PROFILE_TYPE_ARRAY && !is_array($value))	$value = array($value);

	if(PROFILE_TYPE_ARRAY == $type){
		DBstart();
		$sql='DELETE FROM profiles WHERE userid='.$USER_DETAILS["userid"].' and idx='.zbx_dbstr($idx);
		DBExecute($sql);
				
		$result = insert_profile($idx,$value,$type);
		DBend($result);
	}
	else{
		$row = DBfetch(DBselect('SELECT value FROM profiles WHERE userid='.$USER_DETAILS["userid"].' AND idx='.zbx_dbstr($idx)));

		if(!$row){
			insert_profile($idx,$value,$type);
		}
		else{
			$sql='UPDATE profiles SET value='.zbx_dbstr($value).',valuetype='.$type.
				' WHERE userid='.$USER_DETAILS["userid"].
					' AND idx='.zbx_dbstr($idx);
			DBexecute($sql);
		}
	}
	
return true;
}

// Author: Aly
function insert_profile($idx,$value,$type=PROFILE_TYPE_UNKNOWN){
	global $USER_DETAILS;
	
	$result = true;
	if(is_array($value)){
		foreach($value as $key => $val){
			$result&=insert_profile($idx,$val,$type);		// recursion!!!
		}
	}
	else{
		$profileid = get_dbid('profiles', 'profileid');
		$sql='INSERT INTO profiles (profileid,userid,idx,value,valuetype)'.
				' VALUES ('.$profileid.','.$USER_DETAILS["userid"].','.zbx_dbstr($idx).','.zbx_dbstr($value).','.$type.')';
		$result = DBexecute($sql);
	}
return $result;
}

/***********************************/

/************ HISTORY **************/
// Author: Aly
function get_user_history(){
	$history=array();
	$delimiter = new CSpan('&raquo;','delimiter');
	for($i = 0; $i < ZBX_HISTORY_COUNT; $i++){
		if($rows = get_profile('web.history.'.$i,false)){
			if($i>0){
				array_push($history,$delimiter);
			}
			$url = new CLink($rows[0],$rows[1],'history');
			array_push($history,array(SPACE,$url,SPACE));
		}
	}
return $history;
}

// Author: Aly
function add_user_history($page){

	$title = explode('[',$page['title']);
	$title = $title[0];

	if(!(isset($page['hist_arg']) && is_array($page['hist_arg']))){
		return FALSE;
	}
	
	$url = '';
	foreach($page['hist_arg'] as $key => $arg){
		if(isset($_REQUEST[$arg]) && !empty($_REQUEST[$arg])){
			$url.=((empty($url))?('?'):('&')).$arg.'='.$_REQUEST[$arg];
		}
	}
	$url = $page['file'].$url;

	$curr = 0;
	$profile = array();
	for($i = 0; $i < ZBX_HISTORY_COUNT; $i++){
		$history = get_profile('web.history.'.$i,false);
		if($history = get_profile('web.history.'.$i,false)){
			if($history[0] != $title){
				$profile[$curr] = $history;
				$curr++;
			}
		}
	}
			
	$history = array($title,$url);
	if($curr < ZBX_HISTORY_COUNT){
		for($i = 0; $i < $curr; $i++){
			update_profile('web.history.'.$i,$profile[$i],PROFILE_TYPE_ARRAY);
		}
		$result = update_profile('web.history.'.$curr,$history,PROFILE_TYPE_ARRAY);
	} else {
		for($i = 1; $i < ZBX_HISTORY_COUNT; $i++){
			update_profile('web.history.'.($i-1),$profile[$i],PROFILE_TYPE_ARRAY);
		}
		$result = update_profile('web.history.'.(ZBX_HISTORY_COUNT-1),$history,PROFILE_TYPE_ARRAY);
	}

return $result;
}
/********* END USER HISTORY **********/

/********** USER FAVORITES ***********/
// Author: Aly
function add2favorites($favobj,$favid,$resource=null){
	$favrsrc = $favobj.'_rsrc';

	$favorites = get_profile($favobj,array());
	$fav_rsrc =  get_profile($favrsrc,array());

	$favorites[] = $favid;
	$fav_rsrc[] =  (is_null($resource))?0:$resource;

	$result = update_profile($favobj,$favorites);
	$result &= update_profile($favrsrc,$fav_rsrc);
	
return $result;
}

// Author: Aly
function rm4favorites($favobj,$favid,$favcnt=null,$resource=null){
	$favrsrc = $favobj.'_rsrc';

	$favorites = get_profile($favobj,array());
	$fav_rsrc =  get_profile($favrsrc,array());

	$resource = (is_null($resource))?0:$resource;
	$favcnt = (is_null($favcnt))?0:$favcnt;	

	foreach($favorites as $key => $value){
		if(($favid == $value) && ($fav_rsrc[$key] == $resource)){
			if($favcnt < 1){
				unset($favorites[$key]);
				unset($fav_rsrc[$key]);
				if($favcnt > ZBX_FAVORITES_ALL) break;
			}
		}
		$favcnt--;
	}

	$result = update_profile($favobj,$favorites);
	$result &= update_profile($favrsrc,$fav_rsrc);
return $result;
}

// Author: Aly
function get4favorites($favobj){
	$favrsrc = $favobj.'_rsrc';
	
	$fav = array();
	$fav['id'] = get_profile($favobj,array());
	$fav['resource'] =  get_profile($favrsrc,array());
	
return $fav;
}


// Author: Aly
function infavorites($favobj,$favid,$resource=null){

	$fav = get4favorites($favobj);
	if(!empty($fav)){
		foreach($fav['id'] as $id => $resourceid){
			if($favid == $resourceid){
				if(is_null($resource) || ($fav['resource'][$id] == $resource))
					return true;
			}
		}
	}
return false;
}
/********** END USER FAVORITES ***********/
?>