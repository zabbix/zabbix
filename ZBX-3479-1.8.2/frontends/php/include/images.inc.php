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
	function get_default_image($image=false){
		if($image){
			$image = imagecreate(50, 50);
			$color = imagecolorallocate($image, 250, 50, 50);
			imagefill($image, 0, 0, $color);
		}
		else{
			$sql = 'SELECT i.imageid '.
				' FROM images i '.
				' WHERE '.dbin_node('i.imageid', false);
			$result = DBselect($sql,1);
			if($image = DBfetch($result)) return $image;
			else{
				$image = array();
				$image['imageid'] = 0;
			}
		}

	return $image;
	}

	function get_image_by_imageid($imageid){

		$sql = 'SELECT * FROM images WHERE imageid='.$imageid;
		$result = DBselect($sql);
		if($row = DBfetch($result)){
			$row['image'] = zbx_unescape_image($row['image']);
		}

	return $row;
	}

	function zbx_unescape_image($image){
		global $DB;

		$result = ($image)?$image:0;
		if($DB['TYPE'] == "POSTGRESQL"){
			$result = pg_unescape_bytea($image);
		}
		else if($DB['TYPE'] == "SQLITE3"){
			$result = pack('H*', $image);
		}

	return $result;
	}

	function add_image($name, $imagetype, $file){
		if(!is_null($file)){
			if($file['error'] != 0 || $file['size']==0){
				error('Incorrect Image');
			}
			else if($file['size'] < 1024*1024){
				global $DB;

				$imageid = get_dbid('images','imageid');

				$image = fread(fopen($file['tmp_name'],'r'),filesize($file['tmp_name']));
    
				if($DB['TYPE'] == 'POSTGRESQL'){
					$image = pg_escape_bytea($image);
					$sql = 'INSERT INTO images (imageid, name, imagetype, image) '.
									' VALUES ('.$imageid.','.zbx_dbstr($name).','.$imagetype.",'".$image."')";
					return	DBexecute($sql);
				}
				else if($DB['TYPE'] == 'ORACLE'){
					DBstart();
					$lobimage = OCINewDescriptor($DB['DB'], OCI_D_LOB);

					$stid = OCIParse($DB['DB'], 'insert into images (imageid,name,imagetype,image)'.
						" values ($imageid,".zbx_dbstr($name).','.$imagetype.",EMPTY_BLOB())".
						' return image into :image');
					if(!$stid){
						$e = ocierror($stid);
						error(S_PARSE_SQL_ERROR.' ['.$e['message'].']'.SPACE.S_IN_SMALL.SPACE.'['.$e['sqltext'].']');
						return false;
					}

					OCIBindByName($stid, ':image', $lobimage, -1, OCI_B_BLOB);

					if(!OCIExecute($stid, OCI_DEFAULT)){
						$e = ocierror($stid);
						error(S_EXECUTE_SQL_ERROR.SPACE.'['.$e['message'].']'.SPACE.S_IN_SMALL.SPACE.'['.$e['sqltext'].']');
						return false;
					}

					$result = DBend($lobimage->save($image));
					if(!$result){
						error(S_COULD_NOT_SAVE_IMAGE);
					return false;
					}

					$lobimage->free();
					OCIFreeStatement($stid);

				return $stid;
				}
				else if($DB['TYPE'] == 'SQLITE3'){
					$image = bin2hex($image);
				}

				return	DBexecute('INSERT INTO images (imageid, name, imagetype, image) '.
									' VALUES ('.$imageid.','.zbx_dbstr($name).','.$imagetype.','.zbx_dbstr($image).')');
			}
			else{
				error(S_IMAGE_SIZE_MUST_BE_LESS_THAN_MB);
			}
		}
		else{
			error(S_SELECT_IMAGE_TO_DOWNLOAD);
		}
		return false;
	}

	function update_image($imageid,$name,$imagetype,$file){
		if(is_null($file)){
// only update parameters
			return	DBexecute('UPDATE images '.
							' SET name='.zbx_dbstr($name).',imagetype='.zbx_dbstr($imagetype).
							' WHERE imageid='.$imageid);
		}
		else{
			global $DB;

			if($file['error'] != 0 || $file['size']==0){
				error(S_INCORRECT_IMAGE);
				return FALSE;
			}

			if($file['size']<1024*1024){
				$image=fread(fopen($file['tmp_name'],'r'),filesize($file['tmp_name']));

				if($DB['TYPE'] == 'ORACLE'){
					$result = DBexecute('UPDATE images '.
									' SET name='.zbx_dbstr($name).',imagetype='.zbx_dbstr($imagetype).
									' WHERE imageid='.$imageid);

					if(!$result) return $result;


					if(!$stid = DBselect('SELECT image FROM images WHERE imageid='.$imageid.' FOR UPDATE')){
					return false;
					}

					$row = DBfetch($stid);
					$lobimage = $row['image'];

					$lobimage->save($image);
					$lobimage->free();

				return $stid;
				}
				else if($DB['TYPE'] == 'POSTGRESQL'){
					$image = pg_escape_bytea($image);
					$sql='UPDATE images '.
						' SET name='.zbx_dbstr($name).',imagetype='.zbx_dbstr($imagetype).",image='".$image."'".
						' WHERE imageid='.$imageid;
				return	DBexecute($sql);
				}
				else if($DB['TYPE'] == 'SQLITE3'){
					$image = bin2hex($image);
				}

				$sql='UPDATE images SET name='.zbx_dbstr($name).',imagetype='.zbx_dbstr($imagetype).',image='.zbx_dbstr($image).
					' WHERE imageid='.$imageid;

				return	DBexecute($sql);
			}
			else{
				error(S_IMAGE_SIZE_MUST_BE_LESS_THAN_MB);
				return FALSE;
			}
		}
	}

	function delete_image($imageid){
		if(!checkImagesToDelete($imageid)) return false;
		$result = DBexecute('DELETE FROM images WHERE imageid='.$imageid);

	return $result;
	}

	function checkImagesToDelete($imageids){
		zbx_value2array($imageids);

		$saveToDelete = true;
		$sql = 'SELECT DISTINCT sm.* '.
				' FROM sysmaps_elements se, sysmaps sm '.
				' WHERE sm.sysmapid=se.sysmapid '.
					' AND ('.
						DBCondition('se.iconid_off',$imageids).
						' OR '.DBCondition('se.iconid_on',$imageids).
						' OR '.DBCondition('se.iconid_unknown',$imageids).
						' OR '.DBCondition('se.iconid_disabled',$imageids).
						' OR '.DBCondition('se.iconid_maintenance',$imageids).
					')';
		$db_sysmaps = DBselect($sql);
		while($sysmap = DBfetch($db_sysmaps)){
			$saveToDelete = false;
//			error('Image is used as icon in ZABBIX map "'.$sysmap['name'].'" on node "'.get_node_name_by_elid($sysmap['sysmapid'],true).'"');
			error('Image is used as icon in ZABBIX map "'.get_node_name_by_elid($sysmap['sysmapid'],true,':').$sysmap['name'].'"');
		}

		$sql = 'SELECT DISTINCT sm.* '.
				' FROM sysmaps sm '.
				' WHERE '.DBCondition('sm.backgroundid',$imageids);
		$db_sysmaps = DBselect($sql);
		while($sysmap = DBfetch($db_sysmaps)){
			$saveToDelete = false;
			error('Image is used as background in ZABBIX map "'.get_node_name_by_elid($sysmap['sysmapid'],true,':').$sysmap['name'].'"');
		}

	return $saveToDelete;
	}
?>
