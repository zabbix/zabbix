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
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
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
		$user_type = $USER_DETAILS['type'];

		$sort_columns = array('imageid', 'name'); // allowed columns for sorting
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND); // allowed output options for [ select_* ] params

		$sql_parts = array(
			'select' => array('images' => 'i.imageid'),
			'from' => array('images i'),
			'where' => array(),
			'order' => array(),
			'limit' => null);

		$def_options = array(
			'nodeids'				=> null,
			'imageids'				=> null,
			'sysmapids'				=> null,
// Filter
			'filter'				=> '',
			'pattern'				=> '',

// OutPut
			'output'				=> API_OUTPUT_REFER,
			'select_image'			=> null,
			'editable'				=> null,
			'count'					=> null,
			'preservekeys'			=> null,

			'sortfield'				=> '',
			'sortorder'				=> '',
			'limit'					=> null
		);

		$options = zbx_array_merge($def_options, $options);


// editable + PERMISSION CHECK
		if(!is_null($options['editable']) && ($user_type < USER_TYPE_ZABBIX_ADMIN)){
			return $result;
		}

// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid(false);

// imageids
		if(!is_null($options['imageids'])){
			zbx_value2array($options['imageids']);

			$sql_parts['where']['imageid'] = DBcondition('i.imageid', $options['imageids']);
		}

// sysmapids
		if(!is_null($options['sysmapids'])){
			zbx_value2array($options['sysmapids']);
			$sql_parts['select']['sm'] = 'sm.sysmapid';

			$sql_parts['from']['sm'] = 'sysmaps sm';
			$sql_parts['from']['se'] = 'sysmaps_elements se';

			$sql_parts['where']['sm'] = DBcondition('sm.sysmapid', $options['sysmapids']);
			$sql_parts['where']['smse'] = 'sm.sysmapid=se.sysmapid ';
			$sql_parts['where']['se'] = '('.
								'se.iconid_off=i.imageid'.
								' OR se.iconid_on=i.imageid'.
								' OR se.iconid_unknown=i.imageid'.
								' OR se.iconid_disabled=i.imageid'.
								' OR se.iconid_maintenance=i.imageid'.
							')'.
							' OR sm.backgroundid=i.imageid';
		}

// output
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['images'] = 'i.imageid, i.imagetype, i.name';
		}

		if(!is_null($options['select_image'])){
			if($options['output'] == API_OUTPUT_EXTEND) $sql_parts['select']['images'] = 'i.*';
			else $sql_parts['select']['images'] = 'i.imageid, i.image';
		}

// count
		if(!is_null($options['count'])){
			$options['sortfield'] = '';

			$sql_parts['select'] = array('count(i.imageid) as rowscount');
		}

// pattern
		if(!zbx_empty($options['pattern'])){
			$sql_parts['where']['name'] = ' UPPER(i.name) LIKE '.zbx_dbstr('%'.zbx_strtoupper($options['pattern']).'%');
		}

// filter
		if(!is_null($options['filter'])){
			zbx_value2array($options['filter']);

			if(isset($options['filter']['imageid']))
				$sql_parts['where']['imageid'] = 'i.imageid='.$options['filter']['imageid'];

			if(isset($options['filter']['name']))
				$sql_parts['where']['name'] = 'i.name='.zbx_dbstr($options['filter']['name']);

			if(isset($options['filter']['imagetype']))
				$sql_parts['where']['imagetype'] = 'i.imagetype='.$options['filter']['imagetype'];
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
		if(!empty($sql_parts['select']))	$sql_select.= implode(',',$sql_parts['select']);
		if(!empty($sql_parts['from']))		$sql_from.= implode(',',$sql_parts['from']);
		if(!empty($sql_parts['where']))		$sql_where.= ' AND '.implode(' AND ',$sql_parts['where']);
		if(!empty($sql_parts['order']))		$sql_order.= ' ORDER BY '.implode(',',$sql_parts['order']);
		$sql_limit = $sql_parts['limit'];

		$sql = 'SELECT DISTINCT '.$sql_select.
				' FROM '.$sql_from.
				' WHERE '.DBin_node('i.imageid', $nodeids).
					$sql_where.
				$sql_order;
		$res = DBselect($sql, $sql_limit);
		while($image = DBfetch($res)){
			if($options['count'])
				$result = $image;
			else{
				$imageids[$image['imageid']] = $image['imageid'];

				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$image['imageid']] = array('imageid' => $image['imageid']);
				}
				else{
					if(!isset($result[$image['imageid']]))
						$result[$image['imageid']] = array();

// sysmapds
					if(isset($image['sysmapid']) && is_null($options['select_sysmaps'])){
						if(!isset($result[$image['imageid']]['sysmaps']))
							$result[$image['imageid']]['sysmaps'] = array();

						$result[$image['imageid']]['sysmaps'][] = array('sysmapid' => $image['sysmapid']);
					}


					$result[$image['imageid']] += $image;
				}
			}
		}

		if(!is_null($options['count'])){
			if(is_null($options['preservekeys'])) $result = zbx_cleanHashes($result);
			return $result;
		}


// Adding Objects
//---------------

// removing keys (hash -> array)
		if(is_null($options['preservekeys'])){
			$result = zbx_cleanHashes($result);
		}

	return $result;
	}

/**
 * Get images
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
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
 * Check image existance
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $images Data
 * @param array $image['name']
 * @param array $image['hostid']
 * @param array $image['host']
 * @return array
 */
	public static function exists($object){
		$keyFields = array(array('hostid', 'host'), 'name');

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
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $images
 * @param _array $image['name']
 * @param _array $image['image']
 * @param _array $image['imagetype']
 * @return array (new images)
 */
	public static function create($images){
		global $DB, $USER_DETAILS;

		$images = zbx_toArray($images);
		$imageids = array();

		$result = false;
//------
		try{
			if($USER_DETAILS['usertype'] < USER_TYPE_ZABBIX_ADMIN){
				throw new APIException(ZBX_API_ERROR_PERMISSIONS, 'You do not have enough rights for operation');
			}

			$transaction = self::BeginTransaction(__METHOD__);
			foreach($images as $snum => $image){
				$image_db_fields = array(
					'name' => null,
					'image' => null,
					'imagetype' => 0
				);

				if(!check_db_fields($image_db_fields, $image)){
					$result = false;
					$error = 'Wrong fields for image [ '.$image['name'].' ]';
					break;
				}

				$imageid = get_dbid('images','imageid');

				if($DB['TYPE'] == 'POSTGRESQL'){
					$image['image'] = pg_escape_bytea($image['image']);
					$sql = 'INSERT INTO images (imageid, name, imagetype, image) '.
									' VALUES ('.$imageid.','.zbx_dbstr($image['name']).','.$image['imagetype'].",'".$image['image']."')";
					$result = (bool) DBexecute($sql);
				}
				else if($DB['TYPE'] == 'ORACLE'){
					DBstart();
					$lobimage = OCINewDescriptor($DB['DB'], OCI_D_LOB);

					$stid = OCIParse($DB['DB'], 'INSERT INTO images (imageid,name,imagetype,image)'.
						' VALUES ('.$imageid.','.zbx_dbstr($image['name']).','.$image['imagetype'].",EMPTY_BLOB())".
						' RETURN image INTO :image');

					if(!$stid){
						$e = ocierror($stid);
						throw new APIException(ZBX_API_ERROR_APPLICATION, S_PARSE_SQL_ERROR.' ['.$e['message'].']'.SPACE.S_IN_SMALL.SPACE.'['.$e['sqltext'].']');
					}

					OCIBindByName($stid, ':image', $lobimage, -1, OCI_B_BLOB);

					if(!OCIExecute($stid, OCI_DEFAULT)){
						$e = ocierror($stid);
						throw new APIException(ZBX_API_ERROR_APPLICATION, S_EXECUTE_SQL_ERROR.SPACE.'['.$e['message'].']'.SPACE.S_IN_SMALL.SPACE.'['.$e['sqltext'].']');
					}

					$result = DBend($lobimage->save($image));

					$lobimage->free();
					OCIFreeStatement($stid);
					$result = (bool) $stid;
				}

				if(($DB['TYPE'] == 'SQLITE3') || $DB['TYPE'] == 'MYSQL'){
					if($DB['TYPE'] == 'SQLITE3') $image = bin2hex($image);

					$values = array(
						'imageid' => $imageid,
						'name' => zbx_dbstr($image['name']),
						'imagetype' => $image['imagetype'],
						'image' => zbx_dbstr($image['image'])
					);

					$result = DBexecute('INSERT INTO images ('.implode(',', array_keys($values)).') '.
										' VALUES ('.implode(',', $values).')');

					if(!$result){
						throw new APIException(ZBX_API_ERROR_APPLICATION, S_COULD_NOT_SAVE_IMAGE);
					}
				}

				$imageids[] = $imageid;
			}

			$result = self::EndTransaction($result, __METHOD__);

			return array('imageids' => $imageids);
		}
		catch(APIException $e){
			if(isset($transaction)) self::EndTransaction(false, __METHOD__);

			$error = $e->getErrors();
			$error = reset($error);

			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

/**
 * Update images
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $images
 * @param _array $image['imageid']
 * @return array (updated images)
 */
	public static function update($images){
		global $DB, $USER_DETAILS;

		$images = zbx_toArray($images);
		$imageids = zbx_objectValues($images, 'imageid');

		$result = false;
//------
		try{
			if($USER_DETAILS['usertype'] < USER_TYPE_ZABBIX_ADMIN){
				throw new APIException(ZBX_API_ERROR_PERMISSIONS, 'You do not have enough rights for operation');
			}

//------
			$options = array(
				'imageids'=>$imageids,
				'output'=> API_OUTPUT_EXTEND,
				'select_image' => 1,
				'preservekeys' => 1
			);
			$upd_images = self::get($options);

			$transaction = self::BeginTransaction(__METHOD__);

			foreach($images as $num => $image){
				$image_db_fields = $upd_images[$image['imageid']];

				if(!check_db_fields($image_db_fields, $image)){
					throw new APIException(ZBX_API_ERROR_PARAMS, 'Wrong fields for host [ '.$upd_images[$image['imageid']]['name'].' ]');
				}

//				$result = update_image($image['imageid'], $image['name'],$image['command'],$image['usrgrpid'],$image['groupid'],$image['host_access']);
//				if(!$result) break;
			}

			$result = self::EndTransaction($result, __METHOD__);

			return array('imageids' => $imageids);
		}
		catch(APIException $e){
			if(isset($transaction)) self::EndTransaction(false, __METHOD__);

			$error = $e->getErrors();
			$error = reset($error);

			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

/**
 * Delete images
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $imageids
 * @param array $imageids
 * @return boolean
 */
	public static function delete($images){
		$images = zbx_toArray($images);
		$imageids = array();

		$result = false;
//------
		$options = array(
			'imageids'=>zbx_objectValues($images, 'imageid'),
			'editable'=>1,
			'extendoutput'=>1,
			'preservekeys'=>1
		);
		$del_images = self::get($options);
		foreach($images as $snum => $image){
			if(!isset($del_images[$image['imageid']])){
				self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				return false;
			}

			$imageids[] = $image['imageid'];
			//add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_image, 'image ['.$image['name'].']');
		}

		self::BeginTransaction(__METHOD__);
		if(!empty($imageids)){
			$result = delete_image($imageids);
		}
		else{
			self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, 'Empty input parameter [ imageids ]');
			$result = false;
		}

		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			return array('imageids' => $imageids);
		}
		else{
			self::setError(__METHOD__);
			return false;
		}
	}
}
?>
