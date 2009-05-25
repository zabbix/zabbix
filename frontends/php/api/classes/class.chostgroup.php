<?php
/**
 * File containing hostgroup class for API.
 * @package API
 */
/**
 * Class containing methods for operations with host groups
 *
 */
class CHostGroup {
	
	/**
	 * Gets all host group data from DB by groupid
	 *
	 * @static
	 * @param int $groupid 
	 * @return array|boolean host group data as array or false if error
	 */
	public static function getById($groupid){
		$sql = 'SELECT * FROM groups WHERE groupid='.$groupid;
		$group = DBfetch(DBselect($sql));
		
		return $group ? $group : false;
	}
	
	/**
	 * Get host group id by group name
	 *
	 * $group_data = array(
	 * + string 'name' => 'group name'
	 * );
	 *
	 * @static
	 * @param array $group_data
	 * @return int|boolean host group data as array or false if error
	 */
	public static function getId($group_data){
		$sql = 'SELECT groupid FROM groups WHERE name='.zbx_dbstr($group_data['name']).' AND '.DBin_node('groupid', get_current_nodeid(false));
		$groupid = DBfetch(DBselect($sql));
		
		return $groupid ? $groupid['groupid'] : false;	
	}
	
	/**
	 * Add host group //wrok
	 *
	 * Create Host group. Input parameter is array with following structure :
	 * Array('Name1', 'Name2', ...);
	 *
	 * @static
	 * @param array $groups multidimensional array with host groups data
	 * @return boolean 
	 */
	public static function add($groups){
		DBstart(false);
		
		$result = false;
		foreach($groups as $group){
			$result = db_save_group($group);
			if(!$result) break;
		}
		
		$result = DBend($result);
		return $result ? true : false;
	}
	
	/**
	 * Update host group
	 *
	 * Updates existing host groups, changing names. Input parameter is array with following structure :
	 * Array( Array('groupid' => groupid1, 'name' => name1), Array( 'groupid => groupid2, 'name' => name2), ...);
	 *
	 * @static
	 * @param array $groups multidimensional array with host groups data
	 * @return boolean
	 */
	public static function update($groups){		
		DBstart(false);
		
		$result = false;
		foreach($groups as $group){
			$result = db_save_group($group['name'], $group['groupid']);
			if(!$result) break;
		}
			
		$result = DBend($result);
		return $result;
	}
	
	/**
	 * Delete host groups
	 *
	 * @static
	 * @param array $groupids 
	 * @return boolean
	 */	
	public static function delete($groupids){
		return delete_host_group($groupids);
	}
	
	/**
	 * Add hosts to host group
	 *
	 * @static
	 * @param int $groupid 
	 * @param array $hostids 
	 * @return boolean 
	 */
	public static function addHosts($groupid, $hostids){
		return add_host_to_group($hostids, $groupid);
	}
	
	/**
	 * Add hosts to host group
	 *
	 * @static
	 * @param int $groupid 
	 * @param array $hostids 
	 * @return boolean 
	 */
	public static function removeHosts($groupid, $hostids){
		DBstart(false);
		
		foreach($hostids as $key => $hostid){
			$result = delete_host_from_group($hostid, $groupid);
			if(!$result) break;
		}
		
		$result = DBend($result);
		return $result;
	}

	/**
	 * Add groups to existing host groups
	 *
	 * @static
	 * @param string $hostid
	 * @param array $groupids
	 * @return boolean
	 */	
	public static function addGroupsToHost($hostid, $groupids){
		$result = false;
		
		DBstart(false);
		foreach($groupids as $key => $groupid) {
			$hostgroupid = get_dbid("hosts_groups","hostgroupid");
			$result = DBexecute("insert into hosts_groups (hostgroupid,hostid,groupid) values ($hostgroupid, $hostid, $groupid)");
			if(!$result)
				return $result;
		}
		$result = DBend($result);
		
		return $result;
	}

	/**
	 * Update existing host groups with new one (rewrite) //work
	 *
	 * @static
	 * @param string $hostid 
	 * @param array $groupids 
	 * @return boolean
	 */	
	public static function updateGroupsToHost($hostid, $groupids){
		return update_host_groups($hostid, $groupids);
	}

}
?>