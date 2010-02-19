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
		$str_type[MEDIA_TYPE_EMAIL]	= S_EMAIL;
		$str_type[MEDIA_TYPE_EXEC]	= S_SCRIPT;
		$str_type[MEDIA_TYPE_SMS]	= S_SMS;
		$str_type[MEDIA_TYPE_JABBER]	= S_JABBER;

		if(isset($str_type[$type]))
			return $str_type[$type];

		return S_UNKNOWN;
	}

	function media_severity2str($severity){
		$mapping = array(
			0 => array('letter' => 'N', 'style' => (($severity & 1)  ? 'enabled' : NULL)),
			1 => array('letter' => 'I', 'style' => (($severity & 2)  ? 'enabled' : NULL)),
			2 => array('letter' => 'W', 'style' => (($severity & 4)  ? 'enabled' : NULL)),
			3 => array('letter' => 'A', 'style' => (($severity & 8)  ? 'enabled' : NULL)),
			4 => array('letter' => 'H', 'style' => (($severity & 16) ? 'enabled' : NULL)),
			5 => array('letter' => 'D', 'style' => (($severity & 32) ? 'enabled' : NULL))
		);

		foreach($mapping as $id => $map){
			$result[$id] = new CSpan($map['letter'], $map['style']);
			$result[$id]->SetHint(get_severity_description($id)." (".(isset($map['style']) ? "on" : "off").")");
		}

	return $result;
	}

	function get_media_by_mediaid($mediaid){

		$sql="select * from media where mediaid=$mediaid";
		$result=DBselect($sql);
		$row=DBfetch($result);
		if($row)
		{
			return	$row;
		}
		else
		{
			error(S_NO_MEDIA_WITH.SPACE."mediaid=[$mediaid]");
		}
		return	$result;
	}

// Delete Media definition by mediatypeid
	function delete_media_by_mediatypeid($mediatypeid){
		$sql="delete from media where mediatypeid=$mediatypeid";
		return	DBexecute($sql);
	}

// Delete alerts by mediatypeid
	function delete_alerts_by_mediatypeid($mediatypeid){
		$sql="delete from alerts where mediatypeid=$mediatypeid";
		return	DBexecute($sql);
	}

	function get_mediatype_by_mediatypeid($mediatypeid){
		$sql="select * from media_type where mediatypeid=$mediatypeid";
		$result=DBselect($sql);
		$row=DBfetch($result);
		if($row){
			return	$row;
		}
		else{
			error(S_NO_MEDIA_TYPE_WITH.SPACE."mediatypeid=[$mediatypeid]");
		}
	return $item;
	}

// Delete media type
	function delete_mediatype($mediatypeid){
		delete_media_by_mediatypeid($mediatypeid);
		delete_alerts_by_mediatypeid($mediatypeid);
		$mediatype = get_mediatype_by_mediatypeid($mediatypeid);

		$sql='DELETE FROM media_type WHERE mediatypeid='.$mediatypeid;

		if($ret = DBexecute($sql)){
			add_audit_ext(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_MEDIA_TYPE, $mediatypeid, $mediatype['description'], NULL, NULL, NULL);
		}
	return $ret;
	}

// Update media type
	function update_mediatype($mediatypeid,$type,$description,$smtp_server,$smtp_helo,$smtp_email,$exec_path,$gsm_modem,$username,$password){
		$ret = 0;

		$sql='SELECT * '.
			' FROM media_type '.
			' WHERE description='.zbx_dbstr($description).
				' AND mediatypeid<>'.$mediatypeid.
				' AND '.DBin_node('mediatypeid');
		$result=DBselect($sql);
		if(DBfetch($result)){
			error(S_AN_ACTION_TYPE_WITH_DESCRIPTION.SPACE."'".$description."'".SPACE.S_ALREADY_EXISTS_SMALL.'.');
		}
		else{
			$mediatype_old = get_mediatype_by_mediatypeid($mediatypeid);

			$sql='UPDATE media_type SET '.
					'type='.$type.','.
					'description='.zbx_dbstr($description).','.
					'smtp_server='.zbx_dbstr($smtp_server).','.
					'smtp_helo='.zbx_dbstr($smtp_helo).','.
					'smtp_email='.zbx_dbstr($smtp_email).','.
					'exec_path='.zbx_dbstr($exec_path).','.
					'gsm_modem='.zbx_dbstr($gsm_modem).','.
					'username='.zbx_dbstr($username).','.
					'passwd='.zbx_dbstr($password).
				' WHERE mediatypeid='.$mediatypeid;
			$ret = DBexecute($sql);
			if($ret){
				$mediatype_new = get_mediatype_by_mediatypeid($mediatypeid);
				add_audit_ext(AUDIT_ACTION_UPDATE,
								AUDIT_RESOURCE_MEDIA_TYPE,
								$mediatypeid,
								$mediatype_old['description'],
								'media_type',
								$mediatype_old,
								$mediatype_new);
			}
		}
	return $ret;
	}

// Add Media type

	function add_mediatype($type,$description,$smtp_server,$smtp_helo,$smtp_email,$exec_path,$gsm_modem,$username,$password){
		$ret = 0;

		if($description==""){
			error(S_INCORRECT_DESCRIPTION);
			return 0;
		}

		$sql='SELECT * '.
				' FROM media_type '.
				' WHERE description='.zbx_dbstr($description).
					' AND '.DBin_node('mediatypeid');
		$result=DBselect($sql);
		if(DBfetch($result)){
			error(S_AN_ACTION_TYPE_WITH_DESCRIPTION.SPACE."'".$description."'".SPACE.S_ALREADY_EXISTS_SMALL.'.');
		}
		else{
			$mediatypeid=get_dbid("media_type","mediatypeid");
			$sql='INSERT INTO media_type (mediatypeid,type,description,smtp_server,smtp_helo,smtp_email,exec_path,gsm_modem,username,passwd) '.
				" VALUES ($mediatypeid,$type,".zbx_dbstr($description).",".zbx_dbstr($smtp_server).",".
							zbx_dbstr($smtp_helo).",".zbx_dbstr($smtp_email).",".zbx_dbstr($exec_path).",".
							zbx_dbstr($gsm_modem).",".zbx_dbstr($username).",".zbx_dbstr($password).")";
			$ret = DBexecute($sql);
			if($ret){
				$ret = $mediatypeid;
				add_audit_ext(AUDIT_ACTION_ADD, AUDIT_RESOURCE_MEDIA_TYPE, $mediatypeid, $description, NULL, NULL, NULL);
			}
		}
		return $ret;
	}

// Add Media definition

	function add_media( $userid, $mediatypeid, $sendto, $severity, $active, $period){
		if(!validate_period($period)){
			error(S_INCORRECT_TIME_PERIOD);
			return NULL;
		}
/*
		$c=count($severity);
		$s=0;
		for($i=0;$i<$c;$i++){
			$s=$s|pow(2,(int)$severity[$i]);
		}
//*/
		$s = $severity;

		$mediaid=get_dbid("media","mediaid");

		$sql='INSERT INTO media (mediaid,userid,mediatypeid,sendto,active,severity,period) '.
				" VALUES ($mediaid,$userid,".$mediatypeid.','.zbx_dbstr($sendto).','.$active.','.$s.','.zbx_dbstr($period).')';

		if($ret = DBexecute($sql)){
			$ret = $mediaid;
		}

	return	$ret;
	}

	# Update Media definition

	function	update_media($mediaid, $userid, $mediatypeid, $sendto, $severity, $active, $period)
	{
		if( !validate_period($period) )
		{
			error(S_INCORRECT_TIME_PERIOD);
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
