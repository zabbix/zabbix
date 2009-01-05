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

function delete_script($scriptid){
	$sql = 'DELETE FROM scripts WHERE scriptid='.$scriptid;
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
	$res = 1;

	$command = script_make_command($scriptid,$hostid);
	$nodeid = id2nodeid($hostid);

	$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

	if(!$socket){
		$res = 0;
	}
	
	if($res){
		global $ZBX_SERVER, $ZBX_SERVER_PORT;
		$res = socket_connect($socket, $ZBX_SERVER, $ZBX_SERVER_PORT);
	}
	
	if($res){
		$send = "Command\255$nodeid\255$hostid\255$command\n";
		socket_write($socket,$send);
	}
	
	if($res){
		$res = socket_read($socket,65535);
	}
	
	if($res){
		list($flag,$msg)=split("\255",$res);
		$message["flag"]=$flag;
		$message["message"]=$msg;
	}
	else{
		$message["flag"]=-1;
		$message["message"] = S_CONNECT_TO_SERVER_ERROR.' ['.$ZBX_SERVER.':'.$ZBX_SERVER_PORT.'] ['.socket_strerror(socket_last_error()).']';
	}
	
	if($socket){
		socket_close($socket);
	}
return $message;
}


function get_accessible_scripts_by_hosts($hosts){
	global $USER_DETAILS;
	
	if(!is_array($hosts)){
		$hosts = array('0' => hosts);
	}

// Selecting usrgroups by user	
	$sql = 'SELECT ug.usrgrpid '.
			' FROM users_groups ug '.
			' WHERE ug.userid='.$USER_DETAILS['userid'];
			
	$user_groups = DBfetch(DBselect($sql));
	$user_groups[] = 0;	// to ALL user groups
// --


// Selecting groups by Hosts	
	$sql = 'SELECT hg.hostid,hg.groupid '.
			' FROM hosts_groups hg '.
			' WHERE '.DBcondition('hg.hostid',$hosts);
			
	$hg_res = DBselect($sql);
	while($hg_rows = DBfetch($hg_res)){
		$hosts_groups[$hg_rows['groupid']][$hg_rows['hostid']] = $hg_rows['hostid'];
		$hg_groups[$hg_rows['groupid']] = $hg_rows['groupid'];
	}
	$hg_groups[] = 0;	// to ALL host groups
// --

	$hosts_read_only  = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY);
	$hosts_read_write = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE);

	$hosts_read_only = array_intersect($hosts,$hosts_read_only);
	$hosts_read_write = array_intersect($hosts,$hosts_read_write);
	
	$scripts_by_host = array();
// initialize array 
	foreach($hosts as $id => $hostid){
		$scripts_by_host[$hostid] = array();
	}
//-----
	
	$sql = 'SELECT s.* '.
			' FROM scripts s '.
			' WHERE '.DBin_node('s.scriptid').
				' AND '.DBcondition('s.groupid',$hg_groups).
				' AND '.DBcondition('s.usrgrpid',$user_groups).
			' ORDER BY scriptid ASC';

	$res=DBselect($sql);
	while($script = DBfetch($res)){
		$add_to_hosts = array();
		if(PERM_READ_WRITE == $script['host_access']){
			if($script['groupid'] > 0)
				$add_to_hosts = array_intersect($hosts_read_write, $hosts_groups[$script['groupid']]);
			else 
				$add_to_hosts = $hosts_read_write;
		}
		else if(PERM_READ_ONLY == $script['host_access']){
			if($script['groupid'] > 0)
				$add_to_hosts = array_intersect($hosts_read_only, $hosts_groups[$script['groupid']]);
			else 
				$add_to_hosts = $hosts_read_only;
		}
		
		foreach($add_to_hosts as $id => $hostid){
			$scripts_by_host[$hostid][] = $script;
		}
	}
//SDI($scripts_by_host);
return $scripts_by_host;
}
?>
