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
	function	get_image_by_imageid($imageid)
	{
		$result = DBselect('select * from images where imageid='.$imageid);
		$row = DBfetch($result);
		if($row)
		{
			global $DB_TYPE;

			if($DB_TYPE == "ORACLE")
			{
				if(!isset($row['image']))
					return 0;

				$row['image'] = $row['image']->load();
			}
			else if($DB_TYPE == "POSTGRESQL")
			{
				$row['image'] = pg_unescape_bytea($row['image']);
			}
			return	$row;
		}
		else
		{
			return 0;
		}
	}

	function	add_image($name,$imagetype,$file)
	{
		if(!is_null($file))
		{
			if($file["error"] != 0 || $file["size"]==0)
			{
				error("Incorrect Image");
			}
			elseif($file["size"]<1024*1024)
			{
				global $DB_TYPE;
				global $DB;

				$imageid = get_dbid("images","imageid");

				$image = fread(fopen($file["tmp_name"],"r"),filesize($file["tmp_name"]));
				if($DB_TYPE == "ORACLE")
				{
					$lobimage = OCINewDescriptor($DB, OCI_D_LOB);

					$stid = OCIParse($DB, "insert into images (imageid,name,imagetype,image)".
						" values ($imageid,".zbx_dbstr($name).",".$imagetype.",EMPTY_BLOB())".
						" return image into :image");
					if(!$stid)
					{
						$e = ocierror($stid);
						error("Parse SQL error [".$e["message"]."] in [".$e["sqltext"]."]");
						return false;
					}

					OCIBindByName($stid, ':image', $lobimage, -1, OCI_B_BLOB);

					if(!OCIExecute($stid, OCI_DEFAULT))
					{
						$e = ocierror($stid);
						error("Execute SQL error [".$e["message"]."] in [".$e["sqltext"]."]");
						return false;
					}

					if ($lobimage->save($image)) {
						OCICommit($DB);
					}
					else {
						OCIRollback($DB);
						error("Couldn't save image!\n");
						return false;
					}

					$lobimage->free();
					OCIFreeStatement($stid);

					return $stid;
				}
				else if($DB_TYPE == "POSTGRESQL")
				{
					$image = pg_escape_bytea($image);
				}
				else if($DB_TYPE == "MYSQL")
				{
					//$image = zbx_dbstr($image);
				}
				else
				{
					$image = '';
				}

				return	DBexecute("insert into images (imageid,name,imagetype,image)".
						" values ($imageid,".zbx_dbstr($name).",".$imagetype.",".zbx_dbstr($image).")");
			}
			else
			{
				error("Image size must be less than 1Mb");
			}
		}
		else
		{
			error("Select image to download");
		}
		return false;
	}

	function	update_image($imageid,$name,$imagetype,$file)
	{
		if(is_null($file))
		{ /* only update parameters */
			return	DBexecute("update images set name=".zbx_dbstr($name).",imagetype=".zbx_dbstr($imagetype).
				" where imageid=$imageid");
		}
		else
		{
			global $DB_TYPE;
			global $DB;

			if($file["error"] != 0 || $file["size"]==0)
			{
				error("Incorrect Image");
				return FALSE;
			}
			if($file["size"]<1024*1024)
			{
				$image=fread(fopen($file["tmp_name"],"r"),filesize($file["tmp_name"]));

				if($DB_TYPE == "ORACLE")
				{

					$result = DBexecute("update images set name=".zbx_dbstr($name).
						",imagetype=".zbx_dbstr($imagetype).
						" where imageid=$imageid");

					if(!$result) return $result;

					$stid = OCIParse($DB, "select image from images where imageid=".$imageid." for update");

					$result = OCIExecute($stid, OCI_DEFAULT);
					if(!$result){
						$e = ocierror($stid);
						error("Execute SQL error [".$e["message"]."] in [".$e["sqltext"]."]");
						OCIRollback($DB);
						return false;
					}

					$row = DBfetch($stid);

					$lobimage = $row['image'];

					if (!$lobimage->save($image)) {
						OCIRollback($DB);
					} else {
						OCICommit($DB);
					}

					$lobimage->free();

					return $stid;
				}
				else if($DB_TYPE == "POSTGRESQL")
				{
					$image = pg_escape_bytea($image);
					$sql="update images set name=".zbx_dbstr($name).",imagetype=".zbx_dbstr($imagetype).
						",image='".$image."' where imageid=$imageid";
					return	DBexecute($sql);
				}

				$sql="update images set name=".zbx_dbstr($name).",imagetype=".zbx_dbstr($imagetype).
					",image=".zbx_dbstr($image)." where imageid=$imageid";
				return	DBexecute($sql);
			}
			else
			{
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
