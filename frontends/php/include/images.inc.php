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
	function	get_image_by_imageid($imageid){
		/*global $DB;

		$st = sqlite3_query($DB['DB'], 'select * from images where imageid='.$imageid);
		info(implode(',',sqlite3_fetch_array($st)));
		info(sqlite3_column_type($st,3));
		info(SQLITE3_INTEGER.','.SQLITE3_FLOAT.','.SQLITE3_TEXT.','.SQLITE3_BLOB.','.SQLITE3_NULL);
		return 0;*/
		global $DB;
		
		$result = DBselect('select * from images where imageid='.$imageid);
		$row = DBfetch($result);
		if($row){
			if($DB['TYPE'] == "ORACLE"){
				if(!isset($row['image']))
					return 0;

				$row['image'] = $row['image']->load();
			}
			else if($DB['TYPE'] == "POSTGRESQL"){
				$row['image'] = pg_unescape_bytea($row['image']);
			}
			else if($DB['TYPE'] == "SQLITE3"){
				$row['image'] = pack('H*', $row['image']);
			}
			return	$row;
		}
		else{
			return 0;
		}
	}

	function	add_image($name,$imagetype,$file){
		if(!is_null($file)){
			if($file["error"] != 0 || $file["size"]==0){
				error("Incorrect Image");
			}
			else if($file["size"]<1024*1024){
				global $DB;

				$imageid = get_dbid("images","imageid");

				$image = fread(fopen($file["tmp_name"],"r"),filesize($file["tmp_name"]));
				if($DB['TYPE'] == "ORACLE"){
					DBstart();
					$lobimage = OCINewDescriptor($DB['DB'], OCI_D_LOB);

					$stid = OCIParse($DB['DB'], "insert into images (imageid,name,imagetype,image)".
						" values ($imageid,".zbx_dbstr($name).",".$imagetype.",EMPTY_BLOB())".
						" return image into :image");
					if(!$stid){
						$e = ocierror($stid);
						error("Parse SQL error [".$e["message"]."] in [".$e["sqltext"]."]");
						return false;
					}

					OCIBindByName($stid, ':image', $lobimage, -1, OCI_B_BLOB);

					if(!OCIExecute($stid, OCI_DEFAULT)){
						$e = ocierror($stid);
						error("Execute SQL error [".$e["message"]."] in [".$e["sqltext"]."]");
						return false;
					}
					
					$result = DBend($lobimage->save($image));
					if(!$result){
						error("Couldn't save image!\n");
					return false;
					}

					$lobimage->free();
					OCIFreeStatement($stid);

				return $stid;
				}
				else if($DB['TYPE'] == "POSTGRESQL"){
					$image = pg_escape_bytea($image);
				}
				else if($DB['TYPE'] == "SQLITE3"){
					$image = bin2hex($image);
				}

				return	DBexecute("insert into images (imageid,name,imagetype,image)".
						" values ($imageid,".zbx_dbstr($name).",".$imagetype.",".zbx_dbstr($image).")");
			}
			else{
				error("Image size must be less than 1Mb");
			}
		}
		else{
			error("Select image to download");
		}
		return false;
	}

	function	update_image($imageid,$name,$imagetype,$file){
		if(is_null($file))
		{ /* only update parameters */
			return	DBexecute("update images set name=".zbx_dbstr($name).",imagetype=".zbx_dbstr($imagetype).
				" where imageid=$imageid");
		}
		else{
			global $DB;

			if($file["error"] != 0 || $file["size"]==0){
				error("Incorrect Image");
				return FALSE;
			}
			if($file["size"]<1024*1024){
				$image=fread(fopen($file["tmp_name"],"r"),filesize($file["tmp_name"]));

				if($DB['TYPE'] == "ORACLE"){

					$result = DBexecute('UPDATE images SET name='.zbx_dbstr($name).',imagetype='.zbx_dbstr($imagetype).
									' WHERE imageid='.$imageid);

					if(!$result) return $result;
					
					DBstart();
					if(!$stid = DBselect('SELECT image FROM images WHERE imageid='.$imageid.' FOR UPDATE')){
						DBend();
					return false;
					}

					$row = DBfetch($stid);
					$lobimage = $row['image'];

					DBend($lobimage->save($image));
					$lobimage->free();

				return $stid;
				}
				else if($DB['TYPE'] == "POSTGRESQL"){
					$image = pg_escape_bytea($image);
					$sql='UPDATE images SET name='.zbx_dbstr($name).',imagetype='.zbx_dbstr($imagetype).",image='".$image."'".
						' WHERE imageid='.$imageid;
				return	DBexecute($sql);
				}
				else if($DB['TYPE'] == "SQLITE3"){
					$image = bin2hex($image);
				}

				$sql='UPDATE images SET name='.zbx_dbstr($name).',imagetype='.zbx_dbstr($imagetype).',image='.zbx_dbstr($image).
					' WHERE imageid='.$imageid;
					
				return	DBexecute($sql);
			}
			else{
				error("Image size must be less than 1Mb");
				return FALSE;
			}
		}
	}

	function	delete_image($imageid)
	{
		return	DBexecute("delete from images where imageid=$imageid");
	}

?>
