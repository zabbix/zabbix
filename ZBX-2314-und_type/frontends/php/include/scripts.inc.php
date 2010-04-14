<?php

function get_script_by_scriptid($scriptid){
	$sql = 'SELECT * FROM scripts WHERE scriptid='.$scriptid;

	$rows = false;
	if($res = DBSelect($sql)){
		$rows = DBfetch($res);
	}
return $rows;
}

function add_script($name,$command,$usrgrpid,$groupid,$access){
	$scriptid = get_dbid('scripts','scriptid');
	$sql = 'INSERT INTO scripts (scriptid,name,command,usrgrpid,groupid,host_access) '.
				" VALUES ($scriptid,".zbx_dbstr($name).','.zbx_dbstr($command).",$usrgrpid,$groupid,$access)";
	$result = DBexecute($sql);
	if($result){
		$result = $scriptid;
	}
return $result;
}

function delete_script($scriptids){
	zbx_value2array($scriptids);

	$sql = 'DELETE FROM scripts WHERE '.DBcondition('scriptid',$scriptids);
	$result = DBexecute($sql);

return $result;
}

function update_script($scriptid,$name,$command,$usrgrpid,$groupid,$access){

	$sql = 'UPDATE scripts SET '.
				' name='.zbx_dbstr($name).
				' ,command='.zbx_dbstr($command).
				' ,usrgrpid='.$usrgrpid.
				' ,groupid='.$groupid.
				' ,host_access='.$access.
			' WHERE scriptid='.$scriptid;

	$result = DBexecute($sql);
return $result;
}

function script_make_command($scriptid,$hostid){
	$host_db = DBfetch(DBselect('SELECT dns,useip,ip FROM hosts WHERE hostid='.$hostid));
	$script_db = DBfetch(DBselect('SELECT command FROM scripts WHERE scriptid='.$scriptid));

	if($host_db && $script_db){
		$command = $script_db['command'];
		$command = str_replace("{HOST.DNS}", $host_db['dns'],$command);
		$command = str_replace("{IPADDRESS}", $host_db['ip'],$command);
		$command = ($host_db['useip']==0)?
				str_replace("{HOST.CONN}", $host_db['dns'],$command):
				str_replace("{HOST.CONN}", $host_db['ip'],$command);
	}
	else{
		$command = FALSE;
	}
	return $command;
}

function execute_script($scriptid,$hostid){
	global $ZBX_SERVER, $ZBX_SERVER_PORT, $ZBX_MESSAGES;
	
	if(!$socket = fsockopen($ZBX_SERVER, $ZBX_SERVER_PORT, $errorCode, $errorMsg, ZBX_SCRIPT_TIMEOUT)){
		array_pop($ZBX_MESSAGES);
		error(S_SCRIPT_ERROR_DESCRIPTION.': '.$errorMsg);
		show_messages(false, '', S_SCRIPT_ERROR);
		return false;
	}

	$json = new CJSON();

	$array = Array(
					'request' => 'command',
					'nodeid' => id2nodeid($hostid),
					'scriptid' => $scriptid,
					'hostid' => $hostid
					);

	$dataToSend = $json->encode($array, false);

	if(!defined('ZBX_SCRIPT_TIMEOUT')) define('ZBX_SCRIPT_TIMEOUT', 60);
	stream_set_timeout($socket, ZBX_SCRIPT_TIMEOUT);

	if(fwrite($socket, $dataToSend, zbx_strlen($dataToSend)) === false) {
		error(S_SCRIPT_ERROR_DESCRIPTION.': '.S_SCRIPT_SEND_ERROR);
		show_messages(false, '', S_SCRIPT_ERROR);
		return false;
	}

	if(!defined('ZBX_SCRIPT_BYTES_LIMIT')) define('ZBX_SCRIPT_BYTES_LIMIT', 1073741824);
	$response = '';
	$pbl = ZBX_SCRIPT_BYTES_LIMIT > 8192 ? 8192 : ZBX_SCRIPT_BYTES_LIMIT; // PHP read bytes limit
	$now = time();
	for($i = 0; !feof($socket) && (time()-$now) < ZBX_SCRIPT_TIMEOUT && $i*$pbl < ZBX_SCRIPT_BYTES_LIMIT; $i++) {
		if(($out = fread($socket, $pbl)) !== false) {
			$response .= $out;
		}else{
			error(S_SCRIPT_ERROR_DESCRIPTION.': '.S_SCRIPT_READ_ERROR);
			show_messages(false, '', S_SCRIPT_ERROR);
			return false;
		}
	}
	
	if(!feof($socket)) {
		if(time()-$now >= ZBX_SCRIPT_TIMEOUT) {
			error(S_SCRIPT_ERROR_DESCRIPTION.': '.S_SCRIPT_TIMEOUT_ERROR);
			show_messages(false, '', S_SCRIPT_ERROR);
			return false;
		}else if($i*$pbl >= ZBX_SCRIPT_BYTES_LIMIT) {
			error(S_SCRIPT_ERROR_DESCRIPTION.': '.S_SCRIPT_BYTES_LIMIT_ERROR);
			show_messages(false, '', S_SCRIPT_ERROR);
			return false;
		}else {
			error(S_SCRIPT_ERROR_DESCRIPTION.': '.S_SCRIPT_UNKNOWN_ERROR);
			show_messages(false, '', S_SCRIPT_ERROR);
			return false;
		}
	}
	
	if(zbx_strlen($response) > 0){
		$json = new CJSON();
		$rcv = $json->decode($response, true);
	}else{
		error(S_SCRIPT_ERROR_DESCRIPTION.': '.S_SCRIPT_ERROR_EMPTY_RESPONSE);
		show_messages(false, '', S_SCRIPT_ERROR);
		return false;
	}

	fclose($socket);
	return $rcv;
}
?>
