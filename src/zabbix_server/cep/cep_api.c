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

#include "cep_api.h"
#include "cep.h"
#include "zbxcep.h"
#include "zbxalgo.h"

ZBX_VECTOR_IMPL(cep_event_update, zbx_cep_event_update_t)

/* guards access to CEP cache which can be accessed only by acquiring with */
/* cep_cache_acquire() and releasing afterwards with cep_cache_release()   */
typedef struct
{
	zbx_cep_t	*cep;
	pthread_mutex_t	lock;
}
zbx_cep_guard_t;

/* channel were event updates for IT service manager are posted */
static zbx_channel_t	event_update_channel;

/* CEP cache guard instance */
static zbx_cep_guard_t	*cache_guard;

/******************************************************************************
 *                                                                            *
 * Purpose: create and initialize a CEP cache guard                           *
 *                                                                            *
 * Parameters: error - [OUT] error message if initialization fails            *
 *                                                                            *
 * Return value: pointer to the created guard, or NULL on failure             *
 *                                                                            *
 ******************************************************************************/
static zbx_cep_guard_t	*cep_guard_create(char **error)
{
	zbx_cep_guard_t	*guard;
	int			err;

	guard = (zbx_cep_guard_t *)zbx_malloc(NULL, sizeof(zbx_cep_guard_t));

	if (0 != (err = pthread_mutex_init(&guard->lock, NULL)))
	{
		*error = zbx_dsprintf(NULL, "cannot initialize CEP cache mutex: %s", zbx_strerror(err));
		zbx_free(guard);

		return NULL;
	}

	guard->cep = cep_create();

	return guard;
}

/******************************************************************************
 *                                                                            *
 * Purpose: destroy a CEP cache guard and free all associated resources       *
 *                                                                            *
 ******************************************************************************/
static void	cep_guard_destroy(zbx_cep_guard_t *guard)
{
	cep_destroy(guard->cep);
	pthread_mutex_destroy(&guard->lock);

	zbx_free(guard);
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize the CEP API and its associated resources               *
 *                                                                            *
 * Parameters: error - [OUT] error message if initialization fails            *
 *                                                                            *
 * Return value: SUCCEED on success, FAIL otherwise                           *
 *                                                                            *
 ******************************************************************************/
int	cep_api_init(char **error)
{
	if (NULL == (cache_guard = cep_guard_create(error)))
		return FAIL;

	zbx_chan_init(&event_update_channel, sizeof(zbx_cep_event_update_t), 10);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: destroy the CEP API and free all associated resources             *
 *                                                                            *
 ******************************************************************************/
void	cep_api_destroy(void)
{
	zbx_chan_destroy(&event_update_channel);
	cep_guard_destroy(cache_guard);
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
	pthread_mutex_lock(&cache_guard->lock);
	*cep = cache_guard->cep;
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
	*cep = NULL;
	pthread_mutex_unlock(&cache_guard->lock);
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

		zbx_chan_send_batch(&event_update_channel, updates + i, send_num);
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

		zbx_chan_send_batch(&event_update_channel, updates, send_num);
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
	return zbx_chan_recv_batch(&event_update_channel, updates, updates_num);
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
void	zbx_cep_get_events(zbx_vector_cep_event_handle_t *handles)
{
	zbx_cep_t	*cep;

	cep_cache_acquire(&cep);
	cep_get_events(cep, handles);
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
	if (1 != cep_event_handle_release(h))
		return;

	zbx_cep_t	*cep;
	zbx_cep_event_t	*event;

	cep_cache_acquire(&cep);
	event = cep_event_handle_remove(cep, h);
	cep_cache_release(&cep);

	zbx_cep_event_release(event);
}


