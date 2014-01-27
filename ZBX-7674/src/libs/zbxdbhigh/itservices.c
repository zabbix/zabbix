/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"

#include "db.h"
#include "log.h"
#include "mutexs.h"

#define LOCK_SERVICES	zbx_mutex_lock(&services_lock)
#define UNLOCK_SERVICES	zbx_mutex_unlock(&services_lock)

static ZBX_MUTEX	services_lock;

#define ZBX_UPDATES_CLEAN	0
#define ZBX_UPDATES_DESTROY	1

/* service update queue items */
typedef struct
{
	/* the update source id */
	zbx_uint64_t	sourceid;
	/* the new status */
	int		status;
	/* timestmap */
	int		clock;
}
zbx_status_update_t;

/* IT service node */
typedef struct zbx_itservice_t
{
	/* service id */
	zbx_uint64_t		serviceid;
	/* trigger id for leaf nodes */
	zbx_uint64_t		triggerid;
	/* the initial service status */
	int			old_status;
	/* the new service status */
	int			status;
	/* the service status calculation algorithm for branch nodes, see SERVICE_ALGORITHM_* defines */
	int			algorithm;
	/* the parent (hard linked) node */
	struct zbx_itservice_t	*parent;
	/* the soft linked parent nodes */
	zbx_vector_ptr_t	links;
	/* the child nodes (hard or soft linked) */
	zbx_vector_ptr_t	children;
}
zbx_itservice_t;

/* the service update queue */
static zbx_vector_ptr_t	itservice_updates;

/******************************************************************************
 *                                                                            *
 * Function: service_create                                                   *
 *                                                                            *
 * Purpose: create a new IT service node                                      *
 *                                                                            *
 * Parameters: serviceid   - [IN] the service id                              *
 *             algo        - [IN] the service status calculation mode for     *
 *                                branch nodes                                *
 *             triggerid   - [IN] the source trigger id for leaf nodes        *
 *             status      - [IN] the initial service status                  *
 *                                                                            *
 * Return value: the created service node                                     *
 *                                                                            *
 ******************************************************************************/
static zbx_itservice_t	*service_create(zbx_uint64_t serviceid, int algo, zbx_uint64_t triggerid, int status)
{
	zbx_itservice_t	*service;

	service = zbx_malloc(NULL, sizeof(zbx_itservice_t));
	memset(service, 0, sizeof(zbx_itservice_t));

	service->serviceid = serviceid;
	service->triggerid = triggerid;
	service->old_status = status;
	service->status = status;
	service->algorithm = algo;

	zbx_vector_ptr_create(&service->children);
	zbx_vector_ptr_create(&service->links);

	return service;
}

/******************************************************************************
 *                                                                            *
 * Function: service_free                                                     *
 *                                                                            *
 * Purpose: frees a service and its child nodes                               *
 *                                                                            *
 * Parameters: service   - [IN] the service to free                           *
 *                                                                            *
 * Comments: Only hard linked or soft linked nodes without parents are freed. *
 *                                                                            *
 ******************************************************************************/
static void	service_free(zbx_itservice_t* service)
{
	int	i;

	for (i = 0; i < service->children.values_num; i++)
	{
		zbx_itservice_t	*child = (zbx_itservice_t *)service->children.values[i];
		/* free only hard linked children or soft linked children without parents */
		if (NULL == child->parent || service == child->parent)
			service_free(service->children.values[i]);
	}

	zbx_vector_ptr_destroy(&service->children);
	zbx_vector_ptr_destroy(&service->links);
	zbx_free(service);
}

/******************************************************************************
 *                                                                            *
 * Function: service_find_by_serviceid                                        *
 *                                                                            *
 * Purpose: locates service node by its serviceid                             *
 *                                                                            *
 * Parameters: root       - [IN] the root node                                *
 *             serviceid  - [IN] an id of the service to find                 *
 *                                                                            *
 * Return value: The located node or NULL if the service tree does not        *
 *               contain a node with the specified serviceid.                 *
 *                                                                            *
 ******************************************************************************/
static zbx_itservice_t	*service_find_by_serviceid(zbx_itservice_t *root, zbx_uint64_t serviceid)
{
	int		i;
	zbx_itservice_t	*service;

	if (root->serviceid == serviceid)
		return root;

	for (i = 0; i < root->children.values_num; i++)
	{
		zbx_itservice_t	*child = (zbx_itservice_t *)root->children.values[i];

		/* iterate only over hard linked nodes or soft linked nodes without parents */
		if (NULL != child->parent && root != child->parent)
			continue;

		if (NULL != (service = service_find_by_serviceid(root->children.values[i], serviceid)))
			return service;
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: updates_append                                                   *
 *                                                                            *
 * Purpose: adds an update to the queue                                       *
 *                                                                            *
 * Parameters: updates   - [OUT] the update queue                             *
 *             sourceid  - [IN] the update source id                          *
 *             status    - [IN] the update status                             *
 *             clock     - [IN] the update timestamp                          *
 *                                                                            *
 ******************************************************************************/
static void	updates_append(zbx_vector_ptr_t *updates, zbx_uint64_t sourceid, int status, int clock)
{
	zbx_status_update_t	*update;

	update = zbx_malloc(NULL, sizeof(zbx_status_update_t));

	update->sourceid = sourceid;
	update->status = status;
	update->clock = clock;

	zbx_vector_ptr_append(updates, update);
}

/******************************************************************************
 *                                                                            *
 * Function: service_add                                                      *
 *                                                                            *
 * Purpose: adds a node to the service node tree                              *
 *                                                                            *
 * Parameters: root     - [IN] the root node                                  *
 *             service  - [IN] the node to add                                *
 *                                                                            *
 * Comments: This function recursively iterates through the parent nodes      *
 *           until the root node and add also the missing nodes to the tree.  *
 *                                                                            *
 ******************************************************************************/
static void	service_add(zbx_itservice_t *root, zbx_itservice_t *service)
{
	DB_RESULT	result;
	DB_RESULT	result2;
	DB_ROW		row;
	zbx_itservice_t	*parent, *sibling;
	zbx_uint64_t	serviceid;
	int		top_node = 1;

	result = DBselect("select s.serviceid,s.algorithm,s.status,sl.soft from services s,services_links sl"
			" where sl.servicedownid=" ZBX_FS_UI64
			" and s.serviceid=sl.serviceupid"
			" order by sl.soft", service->serviceid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(serviceid, row[0]);

		/* find/load the parent service */
		if (NULL == (parent = service_find_by_serviceid(root, serviceid)))
		{
			/* first try to build the tree upwards to the root node to ensure    */
			/* that the tree fragment we are building is linked to the root node */
			/* when we start processing sibling nodes                            */
			parent = service_create(serviceid, atoi(row[1]), atoi(row[3]), atoi(row[2]));
			service_add(root, parent);
		}

		if (0 == atoi(row[3]))
		{
			top_node = 0;
			service->parent = parent;
		}
		else
			zbx_vector_ptr_append(&service->links, parent);

		zbx_vector_ptr_append(&parent->children, service);

		/* load the sibling services */
		result2 = DBselect("select s.serviceid,s.algorithm,s.status,s.triggerid,sl.soft"
				" from services s,services_links sl"
					" where sl.serviceupid=" ZBX_FS_UI64
					" and s.serviceid=sl.servicedownid", serviceid);

		while (NULL != (row = DBfetch(result2)))
		{
			ZBX_STR2UINT64(serviceid, row[0]);

			if (serviceid == service->serviceid)
				continue;

			if (NULL == (sibling = service_find_by_serviceid(root, serviceid)))
			{
				zbx_uint64_t	triggerid = 0;

				if (SUCCEED != DBis_null(row[3]))
					ZBX_STR2UINT64(triggerid, row[3]);

				sibling = service_create(serviceid, atoi(row[1]), triggerid, atoi(row[2]));

				if (0 == atoi(row[4]))
					sibling->parent = parent;
				else
					zbx_vector_ptr_append(&sibling->links, parent);

				zbx_vector_ptr_append(&parent->children, sibling);
			}
		}

		DBfree_result(result2);
	}

	if (1 == top_node)
	{
		/* top node, link to the root node */
		zbx_vector_ptr_append(&root->children, service);
		service->parent = root;
	}

	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: service_load                                                     *
 *                                                                            *
 * Purpose: loads all services the specified trigger can affect into service  *
 *          node tree                                                         *
 *                                                                            *
 * Parameters: root       - [IN] the root node                                *
 *             triggerid  - [IN] the source trigger id                        *
 *                                                                            *
 ******************************************************************************/
static void	service_load(zbx_itservice_t *root, zbx_uint64_t triggerid)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	serviceid;
	zbx_itservice_t	*service;

	result = DBselect("select serviceid,status,algorithm from services where triggerid=" ZBX_FS_UI64, triggerid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(serviceid, row[0]);

		if (NULL == (service = service_find_by_serviceid(root, serviceid)))
		{
			service = service_create(serviceid, atoi(row[2]), triggerid, atoi(row[1]));
			service_add(root, service);
		}
	}

	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: service_update_status                                            *
 *                                                                            *
 * Purpose: updates service and its parents statuses                          *
 *                                                                            *
 * Parameters: service    - [IN] the service to update                        *
 *             clock      - [IN] the update timestamp                         *
 *             alarms     - [OUT] the alarms update queue                     *
 *                                                                            *
 * Comments: This function recalculates service status according to the       *
 *           algorithm and status of the children services. If the status     *
 *           has been changed, an alarm is generated and parent services      *
 *           (up until the root service) are updated too.                     *
 *                                                                            *
 ******************************************************************************/
static void	service_update_status(zbx_itservice_t *service, int clock, zbx_vector_ptr_t *alarms)
{
	int	status, i;

	/* parent node can be NULL only for:                                  */
	/*   1) root node                                                     */
	/*   2) soft linked nodes which are static during this service update */
	/* In both cases we don't need to calculate new status value          */
	if (NULL == service->parent)
		return;

	switch (service->algorithm)
	{
		case SERVICE_ALGORITHM_MIN:
			status = TRIGGER_SEVERITY_COUNT;
			for (i = 0; i < service->children.values_num; i++)
			{
				zbx_itservice_t	*child = (zbx_itservice_t *)service->children.values[i];

				if (child->status < status)
					status = child->status;
			}
			break;
		case SERVICE_ALGORITHM_MAX:
			status = 0;
			for (i = 0; i < service->children.values_num; i++)
			{
				zbx_itservice_t	*child = (zbx_itservice_t *)service->children.values[i];

				if (child->status > status)
					status = child->status;
			}
			break;
		case SERVICE_ALGORITHM_NONE:
			goto out;
		default:
			zabbix_log(LOG_LEVEL_ERR, "unknown calculation algorithm of service status [%d]",
					service->algorithm);
			goto out;
	}
	if (service->status != status)
	{
		service->status = status;

		updates_append(alarms, service->serviceid, status, clock);

		/* update status of hard linked parent */
		service_update_status(service->parent, clock, alarms);

		/* and soft linked upper nodes  */
		for (i = 0; i < service->links.values_num; i++)
			service_update_status(service->links.values[i], clock, alarms);
	}

out:;
}

/******************************************************************************
 *                                                                            *
 * Function: service_get_services_by_triggerid                                *
 *                                                                            *
 * Purpose: get a list of services directly based on the specified trigger    *
 *                                                                            *
 * Parameters: service    - [IN] the service to check                         *
 *             triggerid  - [IN] the trigger id the service is based on       *
 *             services   - [OUT] a list of services                          *
 *                                                                            *
 ******************************************************************************/
static void	service_get_services_by_triggerid(zbx_itservice_t *service, zbx_uint64_t triggerid,
		zbx_vector_ptr_t *services)
{
	int	i;

	if (service->triggerid == triggerid)
		zbx_vector_ptr_append(services, service);

	for (i = 0; i < service->children.values_num; i++)
	{
		zbx_itservice_t	*child = (zbx_itservice_t *)service->children.values[i];

		/* iterate only over hard linked nodes or soft linked nodes without parents */
		if (NULL != child->parent && service != child->parent)
			continue;

		service_get_services_by_triggerid(child, triggerid, services);
	}
}

static void	service_check_status_change(zbx_itservice_t *service, zbx_vector_ptr_t *updates)
{
	int	i;

	if (service->old_status != service->status)
		updates_append(updates, service->serviceid, service->status, 0);

	for (i = 0; i < service->children.values_num; i++)
	{
		zbx_itservice_t	*child = (zbx_itservice_t *)service->children.values[i];

		/* iterate only over hard linked nodes or soft linked nodes without parents */
		if (NULL != child->parent && service != child->parent)
			continue;

		service_check_status_change(child, updates);
	}
}

static int	compare_updates(zbx_status_update_t **update1, zbx_status_update_t **update2)
{
	if ((*update1)->sourceid < (*update2)->sourceid)
		return -1;

	if ((*update1)->sourceid > (*update2)->sourceid)
		return 1;

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: service_write_changes                                            *
 *                                                                            *
 * Purpose: writes service status changes and generated service alarms into   *
 *          database                                                          *
 *          DBflush_service_updates() function                                *
 *                                                                            *
 * Parameters: root    - [IN] the root service                                *
 *             alarms  - [IN] the service alarms update queue                 *
 *                                                                            *
 * Return value: SUCCEED - the data was written successfully                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	service_write_changes(zbx_itservice_t *root, zbx_vector_ptr_t *alarms)
{
	int			i, ret = FAIL;
	zbx_vector_ptr_t	updates;
	char			*sql = NULL;
	const char		*ins_service_alarms =	"insert into service_alarms"
							" (servicealarmid,serviceid,value,clock) values ";
	size_t			sql_offset = 0, sql_alloc = 256;
	zbx_uint64_t		alarmid;

	sql = zbx_malloc(NULL, sql_alloc);

	zbx_vector_ptr_create(&updates);

	service_check_status_change(root, &updates);
	zbx_vector_ptr_sort(&updates, (zbx_compare_func_t)compare_updates);
	zbx_vector_ptr_uniq(&updates, (zbx_compare_func_t)compare_updates);

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	for (i = 0; i < updates.values_num; i++)
	{
		zbx_status_update_t	*update = updates.values[i];

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update services set status=%d"
					" where serviceid=" ZBX_FS_UI64 ";\n",
				update->status, update->sourceid);

		if (SUCCEED != DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset))
			goto out;
	}

	alarmid = DBget_maxid_num("service_alarms", alarms->values_num);

	for (i = 0; i < alarms->values_num; i++)
	{
		zbx_status_update_t	*update = alarms->values[i];

#ifdef HAVE_MULTIROW_INSERT
		if (16 > sql_offset || 0 == i)
#endif
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ins_service_alarms);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"(" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,%d)" ZBX_ROW_DL,
				alarmid++, update->sourceid, update->status, update->clock);

		if (SUCCEED != DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset))
			goto out;
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset)
	{
#ifdef HAVE_MULTIROW_INSERT
		if (0 < alarms->values_num)
		{
			sql_offset--;
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
		}
#endif
		if (ZBX_DB_OK > DBexecute("%s", sql))
			goto out;
	}

	ret = SUCCEED;

out:
	zbx_free(sql);

	for (i = 0; i < updates.values_num; i++)
		zbx_free(updates.values[i]);

	zbx_vector_ptr_destroy(&updates);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: service_flush_updates                                            *
 *                                                                            *
 * Purpose: processes the service update queue                                *
 *                                                                            *
 * Return value: SUCCEED - the data was written successfully                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: The following steps are taken to process the queue:              *
 *           1) Load all services either directly referenced (with triggerid) *
 *              by update queue or dependent on those services (directly or   *
 *              indirectly) or required to calculate status of any loaded     *
 *              services.                                                     *
 *           2) Apply updates to the loaded service tree. Queue service       *
 *              alarms whenever service status changes.                       *
 *           3) Write the final service status changes and the generated      *
 *              service alarm queue into database.                            *
 *                                                                            *
 ******************************************************************************/
int	service_flush_updates(zbx_vector_ptr_t *service_updates)
{
	int			iupdate, iservice, i, ret = FAIL;
	zbx_itservice_t		*root;
	zbx_status_update_t	*update;
	zbx_vector_ptr_t	services, alarms;

	if (NULL == service_updates->values)
		return SUCCEED;

	root = service_create(0, 0, 0, 0);
	zbx_vector_ptr_create(&services);
	zbx_vector_ptr_create(&alarms);

	/* load all services affected by the trigger status change and      */
	/* the services that are required for resulting status calculations */
	for (iupdate = 0; iupdate < service_updates->values_num; iupdate++)
	{
		update = service_updates->values[iupdate];

		service_load(root, update->sourceid);
	}

	/* apply status updates */
	for (iupdate = 0; iupdate < service_updates->values_num; iupdate++)
	{
		update = service_updates->values[iupdate];

		service_get_services_by_triggerid(root, update->sourceid, &services);

		/* change the status of services based on the update */
		for (iservice = 0; iservice < services.values_num; iservice++)
		{
			zbx_itservice_t	*service = (zbx_itservice_t*)services.values[iservice];

			if (SERVICE_ALGORITHM_NONE == service->algorithm || service->status == update->status)
				continue;

			updates_append(&alarms, service->serviceid, update->status, update->clock);
			service->status = update->status;
		}

		/* recalculate status of the parent services */
		for (iservice = 0; iservice < services.values_num; iservice++)
		{
			zbx_itservice_t	*service = (zbx_itservice_t*)services.values[iservice];

			service_update_status(service->parent, update->clock, &alarms);
		}

		services.values_num = 0;
	}

	ret = service_write_changes(root, &alarms);

	for (i = 0; i < alarms.values_num; i++)
		zbx_free(alarms.values[i]);

	zbx_vector_ptr_destroy(&alarms);

	zbx_vector_ptr_destroy(&services);
	service_free(root);

	return ret;
}

/*
 * Public API
 *
 */

/******************************************************************************
 *                                                                            *
 * Function: DBqueue_service_update                                           *
 *                                                                            *
 * Purpose: queues a service update to be executed later with                 *
 *          DBflush_service_updates() function                                *
 *                                                                            *
 * Parameters: triggerid  - [IN] the source trigger id that initiated the     *
 *                               update                                       *
 *             status     - [IN] the source trigger status                    *
 *             clock      - [IN] the update timestamp                         *
 *                                                                            *
 ******************************************************************************/
void	DBqueue_itservice_update(zbx_uint64_t triggerid, int status, int clock)
{
	if (NULL == itservice_updates.values)
		zbx_vector_ptr_create(&itservice_updates);

	updates_append(&itservice_updates, triggerid, status, clock);
}

/******************************************************************************
 *                                                                            *
 * Function: DBflush_service_updates                                          *
 *                                                                            *
 * Purpose: processes the service update queue                                *
 *                                                                            *
 * Return value: SUCCEED - the data was written successfully                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	DBflush_itservice_updates()
{
	int	i, ret = FAIL;

	if (NULL == itservice_updates.values)
		return SUCCEED;

	LOCK_SERVICES;

	ret = service_flush_updates(&itservice_updates);

	for (i = 0; i < itservice_updates.values_num; i++)
		zbx_free(itservice_updates.values[i]);

	itservice_updates.values_num = 0;

	UNLOCK_SERVICES;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DBremove_itservice_triggers                                      *
 *                                                                            *
 * Purpose: removes specified trigger ids from dependent services and reset   *
 *          the status of those services to the default value (0)             *
 *                                                                            *
 * Parameters: triggerids     - [IN] an array of trigger ids to remove        *
 *             triggerids_num - [IN] the number of items in triggerids array  *
 *                                                                            *
 * Return value: SUCCEED - the data was written successfully                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	DBremove_triggers_from_itservices(zbx_uint64_t *triggerids, int triggerids_num)
{
	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset = 0;
	zbx_vector_ptr_t	updates;
	int			i, ret = FAIL;

	if (0 == triggerids_num)
		return SUCCEED;

	LOCK_SERVICES;

	zbx_vector_ptr_create(&updates);

	for (i = 0; i < triggerids_num; i++)
		updates_append(&updates, triggerids[i], 0, 0);

	if (FAIL == service_flush_updates(&updates))
		goto out;

	sql = zbx_malloc(sql, sql_alloc);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"update services set triggerid=null,showsla=0 where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "triggerid", triggerids, triggerids_num);

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		ret = SUCCEED;

	zbx_free(sql);

	for (i = 0; i < updates.values_num; i++)
		zbx_free(updates.values[i]);

out:
	zbx_vector_ptr_destroy(&updates);

	UNLOCK_SERVICES;

	return ret;
}

void	zbx_create_services_lock()
{
	if (ZBX_MUTEX_ERROR == zbx_mutex_create_force(&services_lock, ZBX_MUTEX_SERVICES))
	{
		zbx_error("cannot create mutex for IT services");
		exit(FAIL);
	}
}

void	zbx_destroy_services_lock()
{
	zbx_mutex_destroy(&services_lock);
}



