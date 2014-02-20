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

/* status update queue items */
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
typedef struct
{
	/* service id */
	zbx_uint64_t		serviceid;
	/* trigger id of leaf nodes */
	zbx_uint64_t		triggerid;
	/* the initial service status */
	int			old_status;

	/* the calculated service status */
	int			status;
	/* the service status calculation algorithm, see SERVICE_ALGORITHM_* defines */
	int			algorithm;
	/* the parent nodes */
	zbx_vector_ptr_t	parents;
	/* the child nodes */
	zbx_vector_ptr_t	children;
}
zbx_itservice_t;

/* index of services by triggerid */
typedef struct
{
	zbx_uint64_t		triggerid;
	zbx_vector_ptr_t	services;
}
zbx_itservice_index_t;

/* the service update queue */
static zbx_vector_ptr_t	itservice_updates;

/* a set of IT services used during update session                          */
/*                                                                          */
/* All services are stored into hashset accessed by serviceid. The services */
/* also are indexed by triggerid.                                           */
/* The following types of services are loaded during update session:        */
/*  1) services directly linked to the triggers with values changed         */
/*     during update session.                                               */
/*  2) direct or indirect parent services of (1)                            */
/*  3) services required to calculate status of (2) and not already loaded  */
/*     as (1) or (2).                                                       */
/*                                                                          */
/* In this schema:                                                          */
/*   (1) can't have children services                                       */
/*   (2) will have children services                                        */
/*   (1) and (2) will have parent services unless it's the root service     */
/*   (3) will have neither children or parent services                      */
/*                                                                          */
typedef struct
{
	/* loaded services */
	zbx_hashset_t		services;
	/* service index by triggerid */
	zbx_hashset_t		index;
}
zbx_itservices_set_t;

/******************************************************************************
 *                                                                            *
 * Function: init_itservice_set                                               *
 *                                                                            *
 * Purpose: initializes IT services data set to store services during update  *
 *          session                                                           *
 *                                                                            *
 * Parameters: set   - [IN] the data set to initialize                        *
 *                                                                            *
 ******************************************************************************/
static void	init_itservice_set(zbx_itservices_set_t *set)
{
	zbx_hashset_create(&set->services, 512, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_hashset_create(&set->index, 128, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Function: clean_itservice_set                                              *
 *                                                                            *
 * Purpose: cleans IT services data set by releasing allocated memory         *
 *                                                                            *
 * Parameters: set   - [IN] the data set to clean                             *
 *                                                                            *
 ******************************************************************************/
static void	clean_itservice_set(zbx_itservices_set_t *set)
{
	zbx_hashset_iter_t	iter;
	zbx_itservice_t		*service;
	zbx_itservice_index_t	*index;

	zbx_hashset_iter_reset(&set->index, &iter);

	while (NULL != (index = zbx_hashset_iter_next(&iter)))
		zbx_vector_ptr_destroy(&index->services);

	zbx_hashset_destroy(&set->index);

	zbx_hashset_iter_reset(&set->services, &iter);

	while (NULL != (service = zbx_hashset_iter_next(&iter)))
	{
		zbx_vector_ptr_destroy(&service->children);
		zbx_vector_ptr_destroy(&service->parents);
	}

	zbx_hashset_destroy(&set->services);
}

/******************************************************************************
 *                                                                            *
 * Function: create_service                                                   *
 *                                                                            *
 * Purpose: creates a new IT service node                                     *
 *                                                                            *
 * Parameters: set         - [IN] the IT services data set                    *
 *             serviceid   - [IN] the service id                              *
 *             algorithm   - [IN] the service status calculation mode         *
 *             triggerid   - [IN] the source trigger id for leaf nodes        *
 *             status      - [IN] the initial service status                  *
 *                                                                            *
 * Return value: the created service node                                     *
 *                                                                            *
 ******************************************************************************/
static zbx_itservice_t	*create_service(zbx_itservices_set_t *set, zbx_uint64_t serviceid, zbx_uint64_t triggerid,
		int status, int algorithm)
{
	zbx_itservice_t		service = {serviceid, triggerid, status, status, algorithm}, *pservice;
	zbx_itservice_index_t	*pindex;

	zbx_vector_ptr_create(&service.children);
	zbx_vector_ptr_create(&service.parents);

	pservice = zbx_hashset_insert(&set->services, &service, sizeof(service));

	if (0 != triggerid)
	{
		if (NULL == (pindex = zbx_hashset_search(&set->index, &triggerid)))
		{
			zbx_itservice_index_t	index = {triggerid};

			zbx_vector_ptr_create(&index.services);

			pindex = zbx_hashset_insert(&set->index, &index, sizeof(index));
		}

		zbx_vector_ptr_append(&pindex->services, pservice);
	}

	return pservice;
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
 * Function: load_service_parents                                             *
 *                                                                            *
 * Purpose: recursively loads parent nodes of the specified service until the *
 *          root node                                                         *
 *                                                                            *
 * Parameters: set      - [IN] the IT services data set                       *
 *             service  - [IN] the service                                    *
 *                                                                            *
 ******************************************************************************/
static void	load_service_parents(zbx_itservices_set_t *set, zbx_itservice_t *service)
{
	const char	*__function_name = "load_service_parents";

	DB_RESULT	result;
	DB_RESULT	result2;
	DB_ROW		row;
	zbx_itservice_t	*parent, *sibling;
	zbx_uint64_t	parentid, siblingid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select s.serviceid,s.status,s.algorithm"
			" from services s,services_links sl"
			" where s.serviceid=sl.serviceupid"
				" and sl.servicedownid=" ZBX_FS_UI64, service->serviceid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(parentid, row[0]);

		/* find/load the parent service */
		if (NULL == (parent = zbx_hashset_search(&set->services, &parentid)))
			parent = create_service(set, parentid, 0, atoi(row[1]), atoi(row[2]));

		/* for newly created or indirectly linked parent services we must load */
		/* their parents until we reach the root service                       */
		if (0 == parent->parents.values_num && 0 == parent->children.values_num)
			load_service_parents(set, parent);

		/* link the service as a parent's child */
		if (FAIL == zbx_vector_ptr_search(&parent->children, service, ZBX_DEFAULT_PTR_COMPARE_FUNC))
			zbx_vector_ptr_append(&parent->children, service);

		if (FAIL == zbx_vector_ptr_search(&service->parents, parent, ZBX_DEFAULT_PTR_COMPARE_FUNC))
			zbx_vector_ptr_append(&service->parents, parent);

		/* load the sibling services */
		result2 = DBselect(
				"select s.serviceid,s.triggerid,s.status,s.algorithm"
				" from services s,services_links sl"
				" where s.serviceid=sl.servicedownid"
					" and sl.serviceupid=" ZBX_FS_UI64, parentid);

		while (NULL != (row = DBfetch(result2)))
		{
			ZBX_STR2UINT64(siblingid, row[0]);

			if (siblingid == service->serviceid)
				continue;

			if (NULL == (sibling = zbx_hashset_search(&set->services, &siblingid)))
			{
				zbx_uint64_t	triggerid;

				ZBX_DBROW2UINT64(triggerid, row[1]);

				sibling = create_service(set, siblingid, triggerid, atoi(row[2]), atoi(row[3]));
			}

			if (FAIL == zbx_vector_ptr_search(&parent->children, sibling, ZBX_DEFAULT_PTR_COMPARE_FUNC))
				zbx_vector_ptr_append(&parent->children, sibling);
		}

		DBfree_result(result2);
	}

	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: load_services_by_triggerids                                      *
 *                                                                            *
 * Purpose: loads services that might be affected by the specified triggerid  *
 *          or are required to calculate status of loaded services            *
 *                                                                            *
 * Parameters: set        - [IN] the IT services data set                     *
 *             triggerids - [IN] the sorted list of trigger ids               *
 *                                                                            *
 ******************************************************************************/
static void	load_services_by_triggerids(zbx_itservices_set_t *set, const zbx_vector_uint64_t *triggerids)
{
	const char	*__function_name = "load_services_by_triggerids";

	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	serviceid, triggerid;
	zbx_itservice_t	*service;
	char		*sql = NULL;
	size_t		sql_alloc = 256, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	sql = zbx_malloc(sql, sql_alloc);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select serviceid,triggerid,status,algorithm"
			" from services"
			" where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "triggerid", triggerids->values, triggerids->values_num);

	result = DBselect("%s", sql);

	zbx_free(sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(serviceid, row[0]);
		ZBX_STR2UINT64(triggerid, row[1]);

		if (NULL == (service = zbx_hashset_search(&set->services, &serviceid)))
			service = create_service(set, serviceid, triggerid, atoi(row[2]), atoi(row[3]));

		/* Even if the service already exists it might be loaded only to calculate value */
		/* of parent service (indirectly linked). In this case we also must load its     */
		/* parent services.                                                              */
		if (0 == service->parents.values_num)
			load_service_parents(set, service);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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

		/* update parent services */
		for (i = 0; i < service->parents.values_num; i++)
			service_update_status(service->parents.values[i], clock, alarms);
	}

out:;
}

/******************************************************************************
 *                                                                            *
 * Function: compare_updates                                                  *
 *                                                                            *
 * Purpose: used to sort service updates by service id                        *
 *                                                                            *
 ******************************************************************************/
static int	compare_updates(const zbx_status_update_t **update1, const zbx_status_update_t **update2)
{
	if ((*update1)->sourceid < (*update2)->sourceid)
		return -1;

	if ((*update1)->sourceid > (*update2)->sourceid)
		return 1;

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: write_service_changes_and_alarms                                 *
 *                                                                            *
 * Purpose: writes service status changes and generated service alarms into   *
 *          database                                                          *
 *                                                                            *
 * Parameters: set     - [IN] the IT services data set                        *
 *             alarms  - [IN] the service alarms update queue                 *
 *                                                                            *
 * Return value: SUCCEED - the data was written successfully                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	write_service_changes_and_alarms(zbx_itservices_set_t *set, zbx_vector_ptr_t *alarms)
{
	int			i, ret = FAIL;
	zbx_vector_ptr_t	updates;
	char			*sql = NULL;
	const char		*ins_service_alarms =	"insert into service_alarms"
							" (servicealarmid,serviceid,value,clock) values ";
	size_t			sql_offset = 0, sql_alloc = 256;
	zbx_uint64_t		alarmid;
	zbx_hashset_iter_t	iter;
	zbx_itservice_t		*service;

	sql = zbx_malloc(NULL, sql_alloc);

	/* get a list of service status updates that must be written to database */
	zbx_vector_ptr_create(&updates);
	zbx_hashset_iter_reset(&set->services, &iter);

	while (NULL != (service = zbx_hashset_iter_next(&iter)))
	{
		if (service->old_status != service->status)
			updates_append(&updates, service->serviceid, service->status, 0);
	}

	zbx_vector_ptr_sort(&updates, (zbx_compare_func_t)compare_updates);
	zbx_vector_ptr_uniq(&updates, (zbx_compare_func_t)compare_updates);

	/* write service status changes into database */
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

	/* write generated service alarms into database */
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
 * Function: flush_service_updates                                            *
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
 *           2) Apply updates to the loaded service tree. Queue new service   *
 *              alarms whenever service status changes.                       *
 *           3) Write the final service status changes and the generated      *
 *              service alarm queue into database.                            *
 *                                                                            *
 ******************************************************************************/
static int	flush_service_updates(zbx_vector_ptr_t *service_updates)
{
	const char		*__function_name = "flush_service_updates";

	int			iupdate, iservice, i, ret = FAIL;
	zbx_status_update_t	*update;
	zbx_itservices_set_t	set;
	zbx_vector_ptr_t	alarms;
	zbx_itservice_index_t	*index;
	zbx_vector_uint64_t	triggerids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	init_itservice_set(&set);

	zbx_vector_ptr_create(&alarms);
	zbx_vector_uint64_create(&triggerids);

	for (i = 0; i < service_updates->values_num; i++)
	{
		update = (zbx_status_update_t *)service_updates->values[i];

		zbx_vector_uint64_append(&triggerids, update->sourceid);
	}

	zbx_vector_uint64_sort(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	/* load all services affected by the trigger status change and      */
	/* the services that are required for resulting status calculations */
	load_services_by_triggerids(&set, &triggerids);

	zbx_vector_uint64_destroy(&triggerids);

	/* apply status updates */
	for (iupdate = 0; iupdate < service_updates->values_num; iupdate++)
	{
		update = service_updates->values[iupdate];

		if (NULL != (index = zbx_hashset_search(&set.index, update)))
		{
			/* change the status of services based on the update */
			for (iservice = 0; iservice < index->services.values_num; iservice++)
			{
				zbx_itservice_t	*service = (zbx_itservice_t*)index->services.values[iservice];

				if (SERVICE_ALGORITHM_NONE == service->algorithm || service->status == update->status)
					continue;

				updates_append(&alarms, service->serviceid, update->status, update->clock);
				service->status = update->status;
			}

			/* recalculate status of the parent services */
			for (iservice = 0; iservice < index->services.values_num; iservice++)
			{
				zbx_itservice_t	*service = (zbx_itservice_t*)index->services.values[iservice];

				/* update parent services */
				for (i = 0; i < service->parents.values_num; i++)
					service_update_status(service->parents.values[i], update->clock, &alarms);
			}
		}
	}

	ret = write_service_changes_and_alarms(&set, &alarms);

	for (i = 0; i < alarms.values_num; i++)
		zbx_free(alarms.values[i]);

	zbx_vector_ptr_destroy(&alarms);

	clean_itservice_set(&set);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/*
 * Public API
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

	if (NULL == itservice_updates.values || 0 == itservice_updates.values_num)
		return SUCCEED;

	LOCK_SERVICES;

	ret = flush_service_updates(&itservice_updates);

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

	if (FAIL == flush_service_updates(&updates))
		goto out;

	sql = zbx_malloc(sql, sql_alloc);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"update services set triggerid=null,showsla=0 where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "triggerid", triggerids, triggerids_num);

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		ret = SUCCEED;

	zbx_free(sql);
out:
	for (i = 0; i < updates.values_num; i++)
		zbx_free(updates.values[i]);

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



