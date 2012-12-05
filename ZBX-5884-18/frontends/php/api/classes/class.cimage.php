<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
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
/**
 * File containing Cimage class for API.
 * @package API
 */
/**
 * Class containing methods for operations with images
 *
 */
class CImage extends CZBXAPI{
/**
 * Get images data
 *
 * @param array $options
 * @param array $options['itemids']
 * @param array $options['hostids']
 * @param array $options['groupids']
 * @param array $options['triggerids']
 * @param array $options['imageids']
 * @param boolean $options['status']
 * @param boolean $options['editable']
 * @param boolean $options['count']
 * @param string $options['pattern']
 * @param int $options['limit']
 * @param string $options['order']
 * @return array|boolean image data as array or false if error
 */
	public static function get($options = array()){
		global $USER_DETAILS;

		$result = array();

		$sort_columns = array('imageid', 'name'); // allowed columns for sorting
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND); // allowed output options for [ select_* ] params

		$sql_parts = array(
			'select' => array('images' => 'i.imageid'),
			'from' => array('images' => 'images i'),
			'where' => array(),
			'order' => array(),
			'limit' => null);

		$def_options = array(
			'nodeids'				=> null,
			'imageids'				=> null,
			'sysmapids'				=> null,
// filter
			'filter'				=> null,
			'search'				=> null,
			'startSearch'			=> null,
			'excludeSearch'			=> null,
			'searchWildcardsEnabled'=> null,

// OutPut
			'output'				=> API_OUTPUT_REFER,
			'select_image'			=> null,
			'editable'				=> null,
			'countOutput'			=> null,
			'preservekeys'			=> null,

			'sortfield'				=> '',
			'sortorder'				=> '',
			'limit'					=> null
		);

		$options = zbx_array_merge($def_options, $options);


// editable + PERMISSION CHECK
		if(!is_null($options['editable']) && ($USER_DETAILS['type'] < USER_TYPE_ZABBIX_ADMIN)){
			return $result;
		}

// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

// imageids
		if(!is_null($options['imageids'])){
			zbx_value2array($options['imageids']);

			$sql_parts['where']['imageid'] = DBcondition('i.imageid', $options['imageids']);
		}

// sysmapids
		if(!is_null($options['sysmapids'])){
			zbx_value2array($options['sysmapids']);

			$sql_parts['select']['sm'] = 'sm.sysmapid';

			$sql_parts['from']['sysmaps'] = 'sysmaps sm';
			$sql_parts['from']['sysmaps_elements'] = 'sysmaps_elements se';

			$sql_parts['where']['sm'] = DBcondition('sm.sysmapid', $options['sysmapids']);
			$sql_parts['where']['smse'] = 'sm.sysmapid=se.sysmapid ';
			$sql_parts['where']['se'] = '('.
				'se.iconid_off=i.imageid'.
				' OR se.iconid_on=i.imageid'.
				' OR se.iconid_unknown=i.imageid'.
				' OR se.iconid_disabled=i.imageid'.
				' OR se.iconid_maintenance=i.imageid'.
				' OR sm.backgroundid=i.imageid)';
		}

// output
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['images'] = 'i.imageid, i.imagetype, i.name';
		}

// count
		if(!is_null($options['countOutput'])){
			$options['sortfield'] = '';
			$sql_parts['select'] = array('count(i.imageid) as rowscount');
		}


// filter
		if(is_array($options['filter'])){
			zbx_db_filter('images i', $options, $sql_parts);
		}

// search
		if(is_array($options['search'])){
			zbx_db_search('images i', $options, $sql_parts);
		}

// order
// restrict not allowed columns for sorting
		$options['sortfield'] = str_in_array($options['sortfield'], $sort_columns) ? $options['sortfield'] : '';
		if(!zbx_empty($options['sortfield'])){
			$sortorder = ($options['sortorder'] == ZBX_SORT_DOWN)?ZBX_SORT_DOWN:ZBX_SORT_UP;

			$sql_parts['order'][] = 'i.'.$options['sortfield'].' '.$sortorder;

			if(!str_in_array('i.'.$options['sortfield'], $sql_parts['select']) && !str_in_array('i.*', $sql_parts['select'])){
				$sql_parts['select'][] = 'i.'.$options['sortfield'];
			}
		}

// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}
//----------

		$imageids = array();

		$sql_parts['select'] = array_unique($sql_parts['select']);
		$sql_parts['from'] = array_unique($sql_parts['from']);
		$sql_parts['where'] = array_unique($sql_parts['where']);
		$sql_parts['order'] = array_unique($sql_parts['order']);

		$sql_select = '';
		$sql_from = '';
		$sql_where = '';
		$sql_order = '';
		if(!empty($sql_parts['select'])) $sql_select.= implode(',',$sql_parts['select']);
		if(!empty($sql_parts['from'])) $sql_from.= implode(',',$sql_parts['from']);
		if(!empty($sql_parts['where'])) $sql_where.= ' AND '.implode(' AND ',$sql_parts['where']);
		if(!empty($sql_parts['order'])) $sql_order.= ' ORDER BY '.implode(',',$sql_parts['order']);

		$sql = 'SELECT '.zbx_db_distinct($sql_parts).' '.$sql_select.
				' FROM '.$sql_from.
				' WHERE '.DBin_node('i.imageid', $nodeids).
					$sql_where.
				$sql_order;
		$res = DBselect($sql, $sql_parts['limit']);
		while($image = DBfetch($res)){
			if($options['countOutput']){
				return $image['rowscount'];
			}
			else{
				$imageids[$image['imageid']] = $image['imageid'];

				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$image['imageid']] = array('imageid' => $image['imageid']);
				}
				else{
					if(!isset($result[$image['imageid']]))
						$result[$image['imageid']] = array();

// sysmapds
					if(isset($image['sysmapid'])){
						if(!isset($result[$image['imageid']]['sysmaps']))
							$result[$image['imageid']]['sysmaps'] = array();

						$result[$image['imageid']]['sysmaps'][] = array('sysmapid' => $image['sysmapid']);
					}

					$result[$image['imageid']] += $image;
				}
			}
		}

// adding objects
		if(!is_null($options['select_image'])){
			$db_img = DBselect('SELECT imageid, image FROM images WHERE '.DBCondition('imageid', $imageids));
			while($img = DBfetch($db_img)){

				$result[$img['imageid']]['image'] = base64_encode(zbx_unescape_image($img['image']));
			}
		}

		if(is_null($options['preservekeys'])){
			$result = zbx_cleanHashes($result);
		}

	return $result;
	}

/**
 * Get images
 *
 * @param array $image
 * @param array $image['name']
 * @param array $image['hostid']
 * @return array|boolean
 */
	public static function getObjects($imageData){
		$options = array(
			'filter' => $imageData,
			'output' => API_OUTPUT_EXTEND
		);

		if(isset($imageData['node']))
			$options['nodeids'] = getNodeIdByNodeName($imageData['node']);
		else if(isset($imageData['nodeids']))
			$options['nodeids'] = $imageData['nodeids'];
		else
			$options['nodeids'] = get_current_nodeid(true);


		$result = self::get($options);

	return $result;
	}

/**
 * Check image existence
 *
 * @param array $images
 * @param array $images['name']
 * @return boolean
 */
	public static function exists($object){
		$keyFields = array(array('imageid', 'name'), 'imagetype');

		$options = array(
			'filter' => zbx_array_mintersect($keyFields, $object),
			'output' => API_OUTPUT_SHORTEN,
			'nopermissions' => 1,
			'limit' => 1
		);

		if(isset($object['node']))
			$options['nodeids'] = getNodeIdByNodeName($object['node']);
		else if(isset($object['nodeids']))
			$options['nodeids'] = $object['nodeids'];

		$objs = self::get($options);

	return !empty($objs);
	}

/**
 * Add images
 *
 * @param array $images ['name' => string, 'image' => string, 'imagetype' => int]
 * @return array
 */
	public static function create($images){
		global $DB, $USER_DETAILS;

		$images = zbx_toArray($images);
		$imageids = array();

		try{
			self::BeginTransaction(__METHOD__);

			if($USER_DETAILS['type'] < USER_TYPE_ZABBIX_ADMIN){
				self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSIONS);
			}

			foreach($images as $snum => $image){

				$image_db_fields = array(
					'name' => null,
					'image' => null,
					'imagetype' => 1
				);

				if(!check_db_fields($image_db_fields, $image)){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Wrong fields for image [ '.$image['name'].' ]');
				}

				if(self::exists(array('name' => $image['name']))){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_IMAGE.' [ '.$image['name'].' ] '.S_ALREADY_EXISTS_SMALL);
				}

// Decode BASE64
				$image['image'] = base64_decode($image['image']);
				if(strlen($image['image']) > ZBX_MAX_IMAGE_SIZE){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_IMAGE_SIZE_MUST_BE_LESS_THAN_MB);
				}

				$imageid = get_dbid('images','imageid');
				$values = array(
					'imageid' => $imageid,
					'name' => zbx_dbstr($image['name']),
					'imagetype' => $image['imagetype'],
				);

				if($DB['TYPE'] == 'ORACLE'){
					$values['image'] = 'EMPTY_BLOB()';

					$lob = oci_new_descriptor($DB['DB'], OCI_D_LOB);

					$sql = 'INSERT INTO images ('.implode(' ,', array_keys($values)).') VALUES ('.implode(',', $values).')'.
						' returning image into :imgdata';
					$stmt = oci_parse($DB['DB'], $sql);
					if(!$stmt){
						$e = oci_error($DB['DB']);
						self::exception(ZBX_API_ERROR_PARAMETERS, S_PARSE_SQL_ERROR.' ['.$e['message'].'] '.S_IN_SMALL.' ['.$e['sqltext'].']');
					}

					oci_bind_by_name($stmt, ':imgdata', $lob, -1, OCI_B_BLOB);
					if(!oci_execute($stmt)){
						$e = oci_error($stmt);
						self::exception(ZBX_API_ERROR_PARAMETERS, S_EXECUTE_SQL_ERROR.' ['.$e['message'].'] '.S_IN_SMALL.' ['.$e['sqltext'].']');
					}
					oci_free_statement($stmt);

				}
				else if($DB['TYPE'] == 'IBM_DB2'){
					$stmt = db2_prepare($DB['DB'], 'INSERT INTO images ('.implode(' ,', array_keys($values)).',image)'.
						' VALUES ('.implode(',', $values).', ?)');

					if(!$stmt){
						self::exception(ZBX_API_ERROR_PARAMETERS, db2_conn_errormsg($DB['DB']));
					}

					$variable = $image['image'];
					if(!db2_bind_param($stmt, 1, "variable", DB2_PARAM_IN, DB2_BINARY)){
						self::exception(ZBX_API_ERROR_PARAMETERS, db2_conn_errormsg($DB['DB']));
					}
					if(!db2_execute($stmt)){
						self::exception(ZBX_API_ERROR_PARAMETERS, db2_conn_errormsg($DB['DB']));
					}
				}
				else if(($DB['TYPE'] == 'SQLITE3') || $DB['TYPE'] == 'MYSQL' || $DB['TYPE'] == 'POSTGRESQL'){
					if($DB['TYPE'] == 'SQLITE3')
						$values['image'] = zbx_dbstr(bin2hex($image['image']));
					else if($DB['TYPE'] == 'POSTGRESQL')
						$values['image'] = "'".pg_escape_bytea($image['image'])."'";
					else if($DB['TYPE'] == 'MYSQL')
						$values['image'] = zbx_dbstr($image['image']);

					$sql = 'INSERT INTO images ('.implode(', ', array_keys($values)).') VALUES ('.implode(', ', $values).')';
					if(!DBexecute($sql)){
						self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
					}
				}

				$imageids[] = $imageid;
			}

			self::EndTransaction(true, __METHOD__);

			return array('imageids' => $imageids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

/**
 * Update images
 *
 * @param array $images
 * @return array (updated images)
 */
	public static function update($images){
		global $DB, $USER_DETAILS;

		try{
			$images = zbx_toArray($images);

			self::BeginTransaction(__METHOD__);

			if($USER_DETAILS['type'] < USER_TYPE_ZABBIX_ADMIN){
				self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSIONS);
			}

			foreach($images as $num => $image){
				if(!isset($image['imageid']))
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Wrong fields for image.');

				$options = array(
					'filter' => array('name' => $image['name']),
					'output' => API_OUTPUT_SHORTEN,
					'nopermissions' => 1
				);
				$image_exists = self::get($options);
				$image_exists = reset($image_exists);

				if(!empty($image_exists) && ($image_exists['imageid'] != $image['imageid'])){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_IMAGE.' [ '.$image['name'].' ] '.S_ALREADY_EXISTS_SMALL);
				}

				$values = array();
				if(isset($image['name'])) $values['name'] = zbx_dbstr($image['name']);
				if(isset($image['imagetype'])) $values['imagetype'] = $image['imagetype'];

				if(isset($image['image'])){
// Decode BASE64
					$image['image'] = base64_decode($image['image']);

					if($DB['TYPE'] == 'POSTGRESQL'){
						$values['image'] = "'".pg_escape_bytea($image['image'])."'";
					}
					else if($DB['TYPE'] == 'SQLITE3'){
						$values['image'] = zbx_dbstr(bin2hex($image['image']));
					}
					else if($DB['TYPE'] == 'MYSQL'){
						$values['image'] = zbx_dbstr($image['image']);
					}
					else if($DB['TYPE'] == 'ORACLE'){
						$sql = 'SELECT image FROM images WHERE imageid = '.$image['imageid'].' FOR UPDATE';

						if(!$stmt = oci_parse($DB['DB'], $sql)){
							$e = oci_error($DB['DB']);
							self::exception(ZBX_API_ERROR_PARAMETERS, 'SQL error ['.$e['message'].'] in ['.$e['sqltext'].']');
						}

						if(!oci_execute($stmt, OCI_DEFAULT)){
							$e = oci_error($stmt);
							self::exception(ZBX_API_ERROR_PARAMETERS, 'SQL error ['.$e['message'].'] in ['.$e['sqltext'].']');
						}

						if(FALSE === ($row = oci_fetch_assoc($stmt))){
							self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
						}

						$row['IMAGE']->truncate();
						$row['IMAGE']->save($image['image']);
						$row['IMAGE']->free();
					}
					else if($DB['TYPE'] == 'IBM_DB2'){
						$stmt = db2_prepare($DB['DB'], 'UPDATE images SET image=? WHERE imageid='.$image['imageid']);

						if(!$stmt){
							self::exception(ZBX_API_ERROR_PARAMETERS, db2_conn_errormsg($DB['DB']));
						}

						$variable = $image['image'];
						if(!db2_bind_param($stmt, 1, "variable", DB2_PARAM_IN, DB2_BINARY)){
							self::exception(ZBX_API_ERROR_PARAMETERS, db2_conn_errormsg($DB['DB']));
						}
						if(!db2_execute($stmt)){
							self::exception(ZBX_API_ERROR_PARAMETERS, db2_conn_errormsg($DB['DB']));
						}
					}
				}

				$sql_upd = array();
				foreach($values as $field => $value){
					$sql_upd[] = $field.'='.$value;
				}
				$sql = 'UPDATE images SET '.implode(', ', $sql_upd).' WHERE imageid='.$image['imageid'];
				$result = DBexecute($sql);

				if(!$result){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_COULD_NOT_SAVE_IMAGE);
				}
			}

			self::EndTransaction(true, __METHOD__);
			return array('imageids' => zbx_objectValues($images, 'imageid'));
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);

			$error = $e->getErrors();
			$error = reset($error);

			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

/**
 * Delete images
 *
 * @param array $imageids
 * @return boolean
 */
	public static function delete($imageids){
		global $USER_DETAILS;

		$imageids = zbx_toArray($imageids);
		try{
			self::BeginTransaction(__METHOD__);

			if(empty($imageids)) self::exception(ZBX_API_ERROR_PARAMETERS, 'Empty parameters');

			if($USER_DETAILS['type'] < USER_TYPE_ZABBIX_ADMIN){
				self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSIONS);
			}

			$sql = 'SELECT DISTINCT sm.sysmapid, sm.name '.
				' FROM sysmaps_elements se, sysmaps sm '.
				' WHERE sm.sysmapid=se.sysmapid '.
					' AND ('.
						DBCondition('se.iconid_off',$imageids).
						' OR '.DBCondition('se.iconid_on',$imageids).
						' OR '.DBCondition('se.iconid_unknown',$imageids).
						' OR '.DBCondition('se.iconid_disabled',$imageids).
						' OR '.DBCondition('se.iconid_maintenance',$imageids).
						' OR '.DBCondition('sm.backgroundid',$imageids).
					')';
			$db_sysmaps = DBselect($sql);

			$errors = array();
			while($sysmap = DBfetch($db_sysmaps)){
				$errors[] = S_IMAGE_IS_USED_IN_ZABBIX_MAP.' "'.get_node_name_by_elid($sysmap['sysmapid'],true,':').$sysmap['name'].'"';
			}
			if(!empty($errors)) self::exception(ZBX_API_ERROR_PARAMETERS, $errors);


			$sql = 'DELETE FROM images WHERE '.DBcondition('imageid', $imageids);
			if(!DBexecute($sql)){
				self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
			}

			self::EndTransaction(true, __METHOD__);

			return array('imageids' => $imageids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);

			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}
}
?>
