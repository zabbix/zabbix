<?php

function get_script_by_scriptid($scriptid){
	$sql = 'SELECT * FROM scripts WHERE scriptid='.$scriptid;
	
	$rows = false;
	if($res = DBSelect($sql)){
		$rows = DBfetch($res);
	}
return $rows;
}

function add_script($name,$command,$access){
	$scriptid = get_dbid('scripts','scriptid');
	$sql = 'INSERT INTO scripts (scriptid,name,command,host_access) '.
				" VALUES ('$scriptid','$name',".zbx_dbstr($command).",$access)";
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

function update_script($scriptid,$name,$command,$access){

	$sql = 'UPDATE scripts SET '.
				' name='.zbx_dbstr($name).
				' ,command='.zbx_dbstr($command).
				' ,host_access='.$access.
			' WHERE scriptid='.$scriptid;
			
	$result = DBexecute($sql);
return $result;
}

function script_make_command($scriptid,$hostid)
{
	$host_db = DBfetch(DBselect("select dns,useip,ip from hosts where hostid=$hostid"));
	$script_db = DBfetch(DBselect("select command from scripts where scriptid=$scriptid"));
	if($host_db && $script_db)
	{
		$command = $script_db["command"];
		$command = str_replace("{HOST.DNS}", $host_db["dns"],$command);
		$command = str_replace("{IPADDRESS}", $host_db["ip"],$command);
		$command = ($host_db["useip"]==0)?
				str_replace("{HOST.CONN}", $host_db["dns"],$command):
				str_replace("{HOST.CONN}", $host_db["ip"],$command);
	}
	else
	{
		$command = FALSE;
	}
	return $command;
}

function execute_script($scriptid,$hostid){
	$res = array();
	$res["flag"]=1;

	$command = script_make_command($scriptid,$hostid);
	$nodeid = id2nodeid($hostid);

	$socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

	if(!$socket)
	{
		$res["flag"] = 1;
	}
	if($res)
	{
		global $ZBX_SERVER, $ZBX_SERVER_PORT;
		$res = @socket_connect($socket, $ZBX_SERVER, $ZBX_SERVER_PORT);
	}
	if($res)
	{
		$send = "Command\255$nodeid\255$command\n";
		@socket_write($socket,$send);
	}
	if($res)
	{
		$res = @socket_read($socket,65535);
	}
	if($res)
	{
		list($flag,$msg)=split("\255",$res);
		$message["flag"]=$flag;
		$message["message"]=$msg;
	}
	if($res)
	{
		@socket_close($socket);
	}
	else
	{
		$message["flag"]=-1;
		$message["message"] = S_CONNECT_TO_SERVER_ERROR.' ['.$ZBX_SERVER.':'.$ZBX_SERVER_PORT.'] ['.socket_strerror(socket_last_error()).']';
	}
return $message;
}


function get_accessible_scripts_by_hosts($hosts){
	global $USER_DETAILS;
	
	if(!is_array($hosts)){
		$hosts = array('0' => hosts);
	}
	
	$hosts_read_only  = explode(',',get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,null,null,get_current_nodeid()));
	$hosts_read_write = explode(',',get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE,null,null,get_current_nodeid()));

// initialize array 
	foreach($hosts as $id => $hostid){
		$scripts_by_host[$hostid] = array();
	}
//-----
	
	$sql = 'SELECT * FROM scripts '.
			' WHERE '.DBin_node('scriptid').
			' ORDER BY scriptid ASC';
	
	$res=DBselect($sql);
	
	while($script = DBfetch($res)){
		foreach($hosts as $id => $hostid){
			if($script['host_access'] == SCRIPT_HOST_ACCESS_WRITE){
				if(in_array($hostid,$hosts_read_write)){
					$scripts_by_host[$hostid][] = $script;
				}
			}
			else{
				if(in_array($hostid,$hosts_read_only)){
					$scripts_by_host[$hostid][] = $script;
				}
			}
		}
	}

return $scripts_by_host;
}
?>
