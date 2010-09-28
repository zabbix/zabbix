<?php
/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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

	function	media_type2str($type)
	{
		$str_type[ALERT_TYPE_EMAIL]	= S_EMAIL;
		$str_type[ALERT_TYPE_EXEC]	= S_SCRIPT;
		$str_type[ALERT_TYPE_SMS]	= S_SMS;
		$str_type[ALERT_TYPE_JABBER]	= S_JABBER;
		
		if(isset($str_type[$type]))
			return $str_type[$type];

		return S_UNKNOWN;
	}

	function	media_severity2str($severity)
	{

		insert_showhint_javascript();
		$mapping = array(
			0 => array('letter' => 'N', 'style' => (($severity & 1)  ? 'enabled' : NULL)),
			1 => array('letter' => 'I', 'style' => (($severity & 2)  ? 'enabled' : NULL)),
			2 => array('letter' => 'W', 'style' => (($severity & 4)  ? 'enabled' : NULL)),
			3 => array('letter' => 'A', 'style' => (($severity & 8)  ? 'enabled' : NULL)),
			4 => array('letter' => 'H', 'style' => (($severity & 16) ? 'enabled' : NULL)),
			5 => array('letter' => 'D', 'style' => (($severity & 32) ? 'enabled' : NULL))
		);

		foreach($mapping as $id => $map)
		{
			$result[$id] = new CSpan($map['letter'], $map['style']);
			$result[$id]->SetHint(get_severity_description($id)." (".(isset($map['style']) ? "on" : "off").")");
		}

		return unpack_object($result);
	}

	function	get_media_by_mediaid($mediaid)
	{
		$sql="select * from media where mediaid=$mediaid"; 
		$result=DBselect($sql);
		$row=DBfetch($result);
		if($row)
		{
			return	$row;
		}
		else
		{
			error("No media with mediaid=[$mediaid]");
		}
		return	$result;
	}

	# Delete Media definition by mediatypeid

	function	delete_media_by_mediatypeid($mediatypeid)
	{
		$sql="delete from media where mediatypeid=$mediatypeid";
		return	DBexecute($sql);
	}

	# Delete alrtes by mediatypeid

	function	delete_alerts_by_mediatypeid($mediatypeid)
	{
		$sql="delete from alerts where mediatypeid=$mediatypeid";
		return	DBexecute($sql);
	}

	function	get_mediatype_by_mediatypeid($mediatypeid)
	{
		$sql="select * from media_type where mediatypeid=$mediatypeid";
		$result=DBselect($sql);
		$row=DBfetch($result);
		if($row)
		{
			return	$row;
		}
		else
		{
			error("No media type with with mediatypeid=[$mediatypeid]");
		}
		return	$item;
	}

	# Delete media type

	function	delete_mediatype($mediatypeid)
	{

		delete_media_by_mediatypeid($mediatypeid);
		delete_alerts_by_mediatypeid($mediatypeid);
		$sql="delete from media_type where mediatypeid=$mediatypeid";
		return	DBexecute($sql);
	}

	# Update media type

	function	update_mediatype($mediatypeid,$type,$description,$smtp_server,$smtp_helo,$smtp_email,$exec_path,$gsm_modem,$username,$password)
	{
		$ret = 0;

		$sql="select * from media_type where description=".zbx_dbstr($description)." and mediatypeid!=$mediatypeid";
		$result=DBexecute($sql);
		if(DBfetch($result))
		{
			error("An action type with description '$description' already exists.");
		}
		else
		{
			$sql="update media_type set type=$type,description=".zbx_dbstr($description).",smtp_server=".zbx_dbstr($smtp_server).",smtp_helo=".zbx_dbstr($smtp_helo).",smtp_email=".zbx_dbstr($smtp_email).",exec_path=".zbx_dbstr($exec_path).",gsm_modem=".zbx_dbstr($gsm_modem).",username=".zbx_dbstr($username).",passwd=".zbx_dbstr($password)." where mediatypeid=$mediatypeid";
			$ret =	DBexecute($sql);
		}
		return $ret;
	}

	# Add Media type

	function	add_mediatype($type,$description,$smtp_server,$smtp_helo,$smtp_email,$exec_path,$gsm_modem,$username,$password)
	{
		$ret = 0;

		if($description==""){
			error(S_INCORRECT_DESCRIPTION);
			return 0;
		}

		$sql="select * from media_type where description=".zbx_dbstr($description);
		$result=DBexecute($sql);
		if(DBfetch($result))
		{
			error("An action type with description '$description' already exists.");
		}
		else
		{
			$mediatypeid=get_dbid("media_type","mediatypeid");
			$sql="insert into media_type (mediatypeid,type,description,smtp_server,smtp_helo,smtp_email,exec_path,gsm_modem,username,passwd) values ($mediatypeid,$type,".zbx_dbstr($description).",".zbx_dbstr($smtp_server).",".zbx_dbstr($smtp_helo).",".zbx_dbstr($smtp_email).",".zbx_dbstr($exec_path).",".zbx_dbstr($gsm_modem).",".zbx_dbstr($username).",".zbx_dbstr($password).")";
			$ret = DBexecute($sql);
			if($ret)	$ret = $mediatypeid;
		}
		return $ret;
	}
	
	# Add Media definition

	function	add_media( $userid, $mediatypeid, $sendto, $severity, $active, $period)
	{
		if( !validate_period($period) )
		{
			error("Icorrect time period");
			return NULL;
		}

		$c=count($severity);
		$s=0;
		for($i=0;$i<$c;$i++)
		{
			$s=$s|pow(2,(int)$severity[$i]);
		}
		$mediaid=get_dbid("media","mediaid");
		$sql="insert into media (mediaid,userid,mediatypeid,sendto,active,severity,period) values ($mediaid,$userid,".zbx_dbstr($mediatypeid).",".zbx_dbstr($sendto).",$active,$s,".zbx_dbstr($period).")";
		$ret = DBexecute($sql);
		if($ret)	$ret = $mediaid;
		return	$ret;
	}

	# Update Media definition

	function	update_media($mediaid, $userid, $mediatypeid, $sendto, $severity, $active, $period)
	{
		if( !validate_period($period) )
		{
			error("Icorrect time period");
			return NULL;
		}

		$c=count($severity);
		$s=0;
		for($i=0;$i<$c;$i++)
		{
			$s=$s|pow(2,(int)$severity[$i]);
		}
		$sql="update media set userid=$userid, mediatypeid=$mediatypeid, sendto=".zbx_dbstr($sendto).", active=$active,severity=$s,period=".zbx_dbstr($period)." where mediaid=$mediaid";
		return	DBexecute($sql);
	}

	# Delete Media definition

	function	delete_media($mediaid)
	{
		return	DBexecute("delete from media where mediaid=$mediaid");
	}

	# Activate Media

	function	activate_media($mediaid)
	{
		return	DBexecute("update media set active=0 where mediaid=$mediaid");
	}

	# Disactivate Media

	function	disactivate_media($mediaid)
	{
		return	DBexecute("update media set active=1 where mediaid=$mediaid");
	}

?>
