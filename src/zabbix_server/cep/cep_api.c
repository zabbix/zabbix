/*
** Copyright (C) 2001-2026 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "cep.h"
#include "cep_api.h"
#include "zbx_cep.h"
#include "zbxalgo.h"
#include "zbxcommon.h"

ZBX_VECTOR_IMPL(cep_event_update, zbx_cep_event_update_t)

/* guards access to CEP cache which can be accessed only by acquiring with */
/* cep_cache_acquire() and releasing afterwards with cep_cache_release()   */
typedef struct
{
	void			*ptr;
	zbx_mem_free_func_t	destroy;
	pthread_mutex_t		lock;
}
zbx_cep_guard_t;

typedef struct
{
	zbx_cep_guard_t		*cache_guard;
	zbx_cep_guard_t		*window_pool_guard;

	const char		*config_source_ip;

	zbx_channel_t		*update_channel;
	zbx_atomic_uint32_t	refcount;
}
zbx_cep_api_t;

static zbx_cep_api_t	*cep_api = NULL;

/******************************************************************************
 *                                                                            *
 * Purpose: create and initialize a guard for a managed resource              *
 *                                                                            *
 * Parameters: ptr     - [IN] pointer to the resource to be guarded           *
 *             destroy - [IN] destructor function for the resource            *
 *             error   - [OUT] error message                                  *
 *                                                                            *
 * Return value: pointer to the created guard, or NULL on error               *
 *                                                                            *
 ******************************************************************************/
static zbx_cep_guard_t	*cep_guard_create(void *ptr, zbx_mem_free_func_t destroy, char **error)
{
	zbx_cep_guard_t	*guard;
	int		err;

	guard = (zbx_cep_guard_t *)zbx_malloc(NULL, sizeof(zbx_cep_guard_t));

	if (0 != (err = pthread_mutex_init(&guard->lock, NULL)))
	{
		*error = zbx_dsprintf(NULL, "cannot initialize CEP cache mutex: %s", zbx_strerror(err));
		zbx_free(guard);

		return NULL;
	}

	guard->ptr = ptr;
	guard->destroy = destroy;

	return guard;
}

/******************************************************************************
 *                                                                            *
 * Purpose: destroy a guard and free all associated resources                 *
 *                                                                            *
 ******************************************************************************/
static void	cep_guard_destroy(zbx_cep_guard_t *guard)
{
	guard->destroy(guard->ptr);
	pthread_mutex_destroy(&guard->lock);

	zbx_free(guard);
}

/******************************************************************************
 *                                                                            *
 * Purpose: acquire the guard lock and expose the protected pointer           *
 *                                                                            *
 * Parameters: guard - [INT]                                                  *
*              ptr   - [OUT] set to the protected pointer                     *
 *                                                                            *
 * Comments: Must be paired with a call to cep_guard_release().               *
 *                                                                            *
 ******************************************************************************/
static void	cep_guard_acquire(zbx_cep_guard_t *guard, void **ptr)
{
	pthread_mutex_lock(&guard->lock);
	*ptr = guard->ptr;
}

/******************************************************************************
 *                                                                            *
 * Purpose: release the guard lock and nullify the pointer                    *
 *                                                                            *
 * Parameters: guard - [INT]                                                  *
 *              ptr - [IN/OUT] pointer to nullify before releasing            *
 *                                                                            *
 * Comments: Must be paired with a prior call to cep_guard_acquire().         *
 *                                                                            *
 ******************************************************************************/
static void	cep_guard_release(zbx_cep_guard_t *guard, void **ptr)
{
	*ptr = NULL;
	pthread_mutex_unlock(&guard->lock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize the CEP API and its associated resources               *
 * Purpose: free CEP API handle and its associated resources                  *
 *                                                                            *
 * Parameters: api - [IN] CEP API handle to free                              *
 *                                                                            *
 ******************************************************************************/
static void	cep_api_free(zbx_cep_api_t *api)
{
	if (NULL != api->window_pool_guard)
		cep_guard_destroy(api->window_pool_guard);

	if (NULL != api->cache_guard)
		cep_guard_destroy(api->cache_guard);

	if (NULL != api->update_channel)
	{
		zbx_chan_destroy(api->update_channel);
		zbx_free(api->update_channel);
	}

	zbx_free(api);
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize CEP API                                                *
 *                                                                            *
 * Parameters: api   - [IN/OUT] CEP API to initialize                         *
 *             error - [OUT] error message                                    *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
static int	cep_api_init(zbx_cep_api_t *api, const char *config_source_ip, char **error)
{
	if (NULL == (api->cache_guard = cep_guard_create(cep_create(), (zbx_mem_free_func_t)cep_destroy, error)))
		return FAIL;

	if (NULL == (api->window_pool_guard = cep_guard_create(cep_window_pool_create(),
			(zbx_mem_free_func_t)cep_window_pool_destroy, error)))
	{
		return FAIL;
	}

	api->update_channel = (zbx_channel_t *)zbx_malloc(NULL, sizeof(zbx_channel_t));
	zbx_chan_init(api->update_channel, sizeof(zbx_cep_event_update_t), 10);

	api->config_source_ip = config_source_ip;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create and initialize the global CEP API handle                   *
 *                                                                            *
 * Parameters: error - [OUT] error message                                    *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
int	cep_api_create(const char *config_source_ip, char **error)
{

	zbx_cep_api_t	*api = (zbx_cep_api_t *)zbx_calloc(NULL, 1, sizeof(zbx_cep_api_t));

	if (FAIL == cep_api_init(api, config_source_ip, error))
	{
		cep_api_free(api);
		return FAIL;
	}

	cep_api = api;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: acquire a reference to the CEP API                                *
 *                                                                            *
 * Comments: Called on thread entry to keep the CEP API alive for the         *
 *           duration of the thread's use of it                               *
 *                                                                            *
 ******************************************************************************/
void	zbx_cep_api_acquire(void)
{
	if (NULL == cep_api)
	{
		THIS_SHOULD_NEVER_HAPPEN_MSG("trying to open uninitialized cep api");
		exit(EXIT_FAILURE);
	}
	atomic_fetch_add(&cep_api->refcount, 1);
}

/******************************************************************************
 *                                                                            *
 * Purpose: release a reference to the CEP API handle                         *
 *                                                                            *
 * Comments: Called before thread exit.                                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_cep_api_release(void)
{
	if (1 == atomic_fetch_sub(&cep_api->refcount, 1))
		cep_api_free(cep_api);
}

/******************************************************************************
 *                                                                            *
 * Purpose: acquire the CEP cache lock and expose the protected cache         *
 *                                                                            *
 * Parameters: cep - [OUT] set to the protected CEP cache pointer             *
 *                                                                            *
 * Comments: Must be paired with a call to cep_cache_release().               *
 *                                                                            *
 ******************************************************************************/
void	cep_cache_acquire(zbx_cep_t **cep)
{
	cep_guard_acquire(cep_api->cache_guard, (void **)cep);
}

/******************************************************************************
 *                                                                            *
 * Purpose: release the CEP cache lock and nullify the cache pointer          *
 *                                                                            *
 * Parameters: cep - [IN/OUT] cache pointer to nullify before releasing       *
 *                                                                            *
 * Comments: Must be paired with a prior call to cep_cache_acquire().         *
 *                                                                            *
 ******************************************************************************/
void	cep_cache_release(zbx_cep_t **cep)
{
	cep_guard_release(cep_api->cache_guard, (void **)cep);
}

/******************************************************************************
 *                                                                            *
 * Purpose: acquire the CEP window pool and lock its guard                    *
 *                                                                            *
 * Parameters: pool - [OUT] pointer to the window pool                        *
 *                                                                            *
 * Comments: Must be paired with a call to cep_window_pool_release().         *
 *                                                                            *
 ******************************************************************************/
void	cep_window_pool_acquire(zbx_cep_window_pool_t **pool)
{
	cep_guard_acquire(cep_api->window_pool_guard, (void **)pool);
}

/******************************************************************************
 *                                                                            *
 * Purpose: release the CEP window pool and unlock its guard                  *
 *                                                                            *
 * Parameters: pool - [IN/OUT] pointer to the window pool                     *
 *                                                                            *
 * Comments: Must be paired with a prior call to cep_window_pool_acquire().   *
 *                                                                            *
 ******************************************************************************/
void	cep_window_pool_release(zbx_cep_window_pool_t **pool)
{
	cep_guard_release(cep_api->window_pool_guard, (void **)pool);
}

#define CEP_UPDATE_BATCH_SIZE  1000

/******************************************************************************
 *                                                                            *
 * Purpose: post event updates to the event update channel in batches         *
 *                                                                            *
 * Parameters: updates     - [IN] array of event updates to send              *
 *             updates_num - [IN] number of updates in the array              *
 *                                                                            *
 ******************************************************************************/
void	cep_post_event_updates(zbx_cep_event_update_t *updates, int updates_num)
{
	for (int i = 0; i < updates_num; i += CEP_UPDATE_BATCH_SIZE)
	{
		int	send_num = CEP_UPDATE_BATCH_SIZE;

		if (i + send_num > updates_num)
			send_num = updates_num - i;

		zbx_chan_send_batch(cep_api->update_channel, updates + i, send_num);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: wrap event handles with an action and post them to the event      *
 *          update channel in batches                                         *
 *                                                                            *
 * Parameters: handles     - [IN] array of event handles to post              *
 *             handles_num - [IN] number of handles in the array              *
 *             action      - [IN] action to associate with each handle        *
 *                                                                            *
 ******************************************************************************/
void	cep_post_event_handle_action(zbx_cep_event_handle_t *handles, int handles_num, zbx_cep_event_op_t action)
{
	zbx_cep_event_update_t  updates[CEP_UPDATE_BATCH_SIZE];

	for (int i = 0; i < handles_num; i += CEP_UPDATE_BATCH_SIZE)
	{
		int	send_num = CEP_UPDATE_BATCH_SIZE;

		if (i + send_num > handles_num)
			send_num = handles_num - i;

		for (int j = 0; j < send_num; j++)
		{
			updates[j].handle = handles[i + j];
			updates[j].op = action;
		}

		zbx_chan_send_batch(cep_api->update_channel, updates, send_num);
	}
}

#undef CEP_UPDATE_BATCH_SIZE

/******************************************************************************
 *                                                                            *
 * Purpose: receive a batch of pending event updates from the channel         *
 *                                                                            *
 * Parameters: updates     - [OUT] buffer to store received updates           *
 *             updates_num - [IN]  maximum number of updates to receive       *
 *                                                                            *
 * Return value: number of updates received (can be 0)                        *
 *                                                                            *
 ******************************************************************************/
int	zbx_cep_recv_event_updates(zbx_cep_event_update_t *updates, int updates_num)
{
	return zbx_chan_recv_batch(cep_api->update_channel, updates, updates_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve events corresponding to the given handles                *
 *                                                                            *
 * Parameters: handles     - [IN]  array of event handles to look up          *
 *             handles_num - [IN]  number of handles in the array             *
 *             events      - [OUT] retrieved events, one per handle           *
 *                                                                            *
 * Comments: The events array must be allocated by the caller and contain     *
 *           at least handles_num elements.                                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_cep_get_events_by_handles(zbx_cep_event_handle_t *handles, int handles_num, zbx_cep_event_t **events)
{
	zbx_cep_t	*cep;

	cep_cache_acquire(&cep);
	cep_get_events_by_handles(cep, handles, handles_num, events);
	cep_cache_release(&cep);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve events corresponding to the given updates                *
 *                                                                            *
 * Parameters: updates     - [IN]  array of event updates to look up          *
 *             updates_num - [IN]  number of updates in the array             *
 *             events      - [OUT] retrieved events, one per update           *
 *                                                                            *
 * Comments: The events array must be allocated by the caller and contain     *
 *           at least updates_num elements.                                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_cep_get_events_by_updates(zbx_cep_event_update_t *updates, int updates_num, zbx_cep_event_t **events)
{
	zbx_cep_t	*cep;

	cep_cache_acquire(&cep);
	cep_get_events_by_updates(cep, updates, updates_num, events);
	cep_cache_release(&cep);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve all events from the CEP cache                            *
 *                                                                            *
 * Parameters: handles - [OUT] vector to store handles of retrieved events    *
 *                                                                            *
 ******************************************************************************/
void	zbx_cep_get_events(unsigned char source, zbx_vector_cep_event_handle_t *handles)
{
	zbx_cep_t	*cep;

	cep_cache_acquire(&cep);
	cep_get_events(cep, source, handles);
	cep_cache_release(&cep);
}

/******************************************************************************
 *                                                                            *
 * Purpose: release an event handle and remove the event from the cache       *
 *          if it is no longer referenced                                     *
 *                                                                            *
 * Parameters: h - [IN] event handle to release                               *
 *                                                                            *
 ******************************************************************************/
void	zbx_cep_event_handle_release(zbx_cep_event_handle_t h)
{
	if (1 != cep_event_handle_unref(h))
		return;

	zbx_cep_t	*cep;
	zbx_cep_event_t	*event;

	cep_cache_acquire(&cep);
	event = cep_event_handle_remove(cep, h);
	cep_cache_release(&cep);

	zbx_cep_event_release(event);
}

/******************************************************************************
 *                                                                            *
 * Purpose: update the CEP cache events accessed counter                      *
 *                                                                            *
 * Parameters: value - [IN] number of events accessed                         *
 *                                                                            *
 * Comments: Does not lock the cache guard; the underlying update function    *
 *           is thread-safe.                                                  *
 *                                                                            *
 ******************************************************************************/
void	cep_stats_update_events_accessed(zbx_uint64_t value)
{
	cep_update_events_accessed((zbx_cep_t *)cep_api->cache_guard->ptr, value);
}

/******************************************************************************
 *                                                                            *
 * Purpose: update the CEP cache events processed counter                     *
 *                                                                            *
 * Parameters: value - [IN] number of events processed                        *
 *                                                                            *
 * Comments: Does not lock the cache guard; the underlying update function    *
 *           is thread-safe.                                                  *
 *                                                                            *
 ******************************************************************************/
void	cep_stats_update_events_processed(zbx_uint64_t value)
{
	cep_update_events_processed((zbx_cep_t *)cep_api->cache_guard->ptr, value);
}

/******************************************************************************
 *                                                                            *
 * Purpose: update the CEP cache events discarded counter                     *
 *                                                                            *
 * Parameters: value - [IN] number of events discarded                        *
 *                                                                            *
 * Comments: Does not lock the cache guard; the underlying update function    *
 *           is thread-safe.                                                  *
 *                                                                            *
 ******************************************************************************/
void	cep_stats_update_events_discarded(zbx_uint64_t value)
{
	cep_update_events_discarded((zbx_cep_t *)cep_api->cache_guard->ptr, value);
}

/******************************************************************************
 *                                                                            *
 * Purpose: collect CEP cache statistics                                      *
 *                                                                            *
 * Parameters: stats - [OUT] collected statistics                             *
 *                                                                            *
 * Comments: Does not lock the cache guard; the underlying collection         *
 *           function is thread-safe.                                         *
 *                                                                            *
 ******************************************************************************/
void	cep_stats_collect(zbx_cep_stats_t *stats)
{
	zbx_cep_t	*cep;

	cep_cache_acquire(&cep);
	cep_get_stats(cep, stats);
	cep_cache_release(&cep);
}

/*
 * server configuration parameter support
 */

const char	*cep_config_get_source_ip(void)
{
	if (NULL == cep_api)
	{
		THIS_SHOULD_NEVER_HAPPEN_MSG("CEP api has not been initialized");
		zbx_exit(EXIT_FAILURE);
	}

	return cep_api->config_source_ip;
}
