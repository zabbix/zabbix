<?php

function get_script_by_scriptid($scriptid){
	$sql = 'SELECT * FROM scripts WHERE scriptid='.zbx_dbstr($scriptid);

	$rows = false;
	if($res = DBSelect($sql)){
		$rows = DBfetch($res);
	}
return $rows;
}

function add_script($name,$command,$usrgrpid,$groupid,$access){
	$scriptid = get_dbid('scripts','scriptid');
	$sql = 'INSERT INTO scripts (scriptid,name,command,usrgrpid,groupid,host_access) '.
				' VALUES ('.$scriptid.','.zbx_dbstr($name).','.zbx_dbstr($command).','.zbx_dbstr($usrgrpid).','.zbx_dbstr($groupid).','.zbx_dbstr($access).')';
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
				' ,usrgrpid='.zbx_dbstr($usrgrpid).
				' ,groupid='.zbx_dbstr($groupid).
				' ,host_access='.zbx_dbstr($access).
			' WHERE scriptid='.zbx_dbstr($scriptid);

	$result = DBexecute($sql);
return $result;
}

function script_make_command($scriptid,$hostid){
	$host_db = DBfetch(DBselect('SELECT dns,useip,ip FROM hosts WHERE hostid='.zbx_dbstr($hostid)));
	$script_db = DBfetch(DBselect('SELECT command FROM scripts WHERE scriptid='.zbx_dbstr($scriptid)));

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

?>
