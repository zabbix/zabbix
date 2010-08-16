<?php
/*
** Zabbix
** Copyright (C) 2000-2010 SIA Zabbix
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

function getSounds(){
	$fileList = array();

	$dir = scandir('./audio');
	foreach($dir as $fnum => $file){
		if(!preg_match('/^([\w\d_]+)\.(wav|ogg)$/i', $file)) continue;

		list($filename, $type) = explode('.', $file);
		$fileList[$filename] = $file;
	}
	
return $fileList;
}

function getLatestCloseTime(){

}

function getMessageSettings(){
	global $USER_DETAILS;

	$defSeverities = array(
		TRIGGER_SEVERITY_NOT_CLASSIFIED => 1,
		TRIGGER_SEVERITY_INFORMATION => 1,
		TRIGGER_SEVERITY_WARNING => 1,
		TRIGGER_SEVERITY_AVERAGE => 1,
		TRIGGER_SEVERITY_HIGH => 1,
		TRIGGER_SEVERITY_DISASTER => 1,
	);

	$messages = array(
		'enabled' => 0,
		'timeout' => 60,
		'last.clock' => 0,
		'triggers.recovery' => 1,
		'triggers.severities' => null,
		'sounds.mute' => 0,
		'sounds.repeat' => 1,
		'sounds.recovery' => 'alarm_ok.wav',
		'sounds.'.TRIGGER_SEVERITY_NOT_CLASSIFIED => 'no_sound.wav',
		'sounds.'.TRIGGER_SEVERITY_INFORMATION => 'alarm_information.wav',
		'sounds.'.TRIGGER_SEVERITY_WARNING => 'alarm_warning.wav',
		'sounds.'.TRIGGER_SEVERITY_AVERAGE => 'alarm_average.wav',
		'sounds.'.TRIGGER_SEVERITY_HIGH => 'alarm_high.wav',
		'sounds.'.TRIGGER_SEVERITY_DISASTER => 'alarm_disaster.wav'
	);

	$sql = 'SELECT idx, source, value_str '.
			' FROM profiles '.
			' WHERE userid='.$USER_DETAILS['userid'].
				' AND '.DBcondition('idx',array('web.messages'), false, true);
	$db_profiles = DBselect($sql);
	while($profile = DBfetch($db_profiles)){
		$messages[$profile['source']] = $profile['value_str'];
	}

	if(is_null($messages['triggers.severities']))
		$messages['triggers.severities'] = $defSeverities;
	else
		$messages['triggers.severities'] = unserialize($messages['triggers.severities']);

return $messages;
}

function updateMessageSettings($messages){
	global $USER_DETAILS;

	if(!isset($messages['enabled'])) $messages['enabled'] = 0;
	if(isset($messages['triggers.severities']))
		$messages['triggers.severities'] = serialize($messages['triggers.severities']);

	$sql = 'SELECT profileid, idx, source, value_str '.
			' FROM profiles '.
			' WHERE userid='.$USER_DETAILS['userid'].
				' AND '.DBcondition('idx',array('web.messages'), false, true);
	$db_profiles = DBselect($sql);
	while($profile = DBfetch($db_profiles)){
		$profile['value'] = $profile['value_str'];
		$dbMessages[$profile['source']] = $profile;
	}

	$inserts = array();
	$updates = array();

	foreach($messages as $key => $value){
		$values = array(
			'userid' => $USER_DETAILS['userid'],
			'idx' => 'web.messages',
			'source' => $key,
			'value_str' =>  $value,
			'type' => PROFILE_TYPE_STR
		);

		if(!isset($dbMessages[$key])){
			$inserts[] = $values;
		}
		else if($dbMessages[$key]['value'] != $value){
			$updates[] = array(
				'values' => $values,
				'where' => array('profileid='.$dbMessages[$key]['profileid'])
			);
		}
	}

	try{
		DB::insert('profiles', $inserts);
		DB::update('profiles', $updates);
	}
	catch(APIException $e){
		$errors = $e->getErrors();
		$error = reset($errors);

		error($error);
	}

return $messages;
}
?>