/*
** Copyright (C) 2001-2025 Zabbix SIA
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

#include "zbxcommon.h"
#include "zbxpreprocbase.h"

#include "zbxalgo.h"
#include "zbxtime.h"
#include "zbxvariant.h"

ZBX_VECTOR_IMPL(pp_step_history, zbx_pp_step_history_t)

struct zbx_pp_history_cache
{
	pthread_mutex_t		lock;
	zbx_pp_history_t	*history;
	unsigned int		refcount;
};

/******************************************************************************
 *                                                                            *
 * Purpose: create preprocessing history                                      *
 *                                                                            *
 * Parameters: history_num - [IN] number of steps using history               *
 *                                                                            *
 * Return value: The created preprocessing history.                           *
 *                                                                            *
 ******************************************************************************/
zbx_pp_history_t	*zbx_pp_history_create(int history_num)
{
	zbx_pp_history_t	*history = (zbx_pp_history_t *)zbx_malloc(NULL, sizeof(zbx_pp_history_t));
	history->refcount = 1;

	zbx_vector_pp_step_history_create(&history->step_history);

	if (0 != history_num)
		zbx_vector_pp_step_history_reserve(&history->step_history, (size_t)history_num);

	return history;
}

zbx_pp_history_t	*zbx_pp_history_release(zbx_pp_history_t *history)
{
	if (NULL == history || 0 != --history->refcount)
		return history;

	for (int i = 0; i < history->step_history.values_num; i++)
		zbx_variant_clear(&history->step_history.values[i].value);

	zbx_vector_pp_step_history_destroy(&history->step_history);

	zbx_free(history);

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: reserve preprocessing history                                     *
 *                                                                            *
 ******************************************************************************/
void	zbx_pp_history_reserve(zbx_pp_history_t *history, int history_num)
{
	zbx_vector_pp_step_history_reserve(&history->step_history, (size_t)history_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add value to preprocessing history                                *
 *                                                                            *
 * Parameters: history - [IN] preprocessing history                           *
 *             index   - [IN] preprocessing step index                        *
 *             value   - [IN/OUT] value to add, its resources are copied      *
 *                         over to history and the value itself is reset      *
 *             ts      - [IN] value timestamp                                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_pp_history_add(zbx_pp_history_t *history, int index, zbx_variant_t *value, zbx_timespec_t ts)
{
	zbx_pp_step_history_t	step_history;

	step_history.index = index;
	step_history.value = *value;
	step_history.ts = ts;

	zbx_variant_set_none(value);

	zbx_vector_pp_step_history_append_ptr(&history->step_history, &step_history);
}

/******************************************************************************
 *                                                                            *
 * Purpose: remove value from preprocessing history and return it             *
 *                                                                            *
 * Parameters: history - [IN] preprocessing history                           *
 *             index   - [IN] preprocessing step index                        *
 *             value   - [OUT] value. If there is no history for the          *
 *                             requested step then empty variant              *
 *                             NULL is returned                               *
 *             ts      - [OUT] value timestamp. If there is no history        *
 *                             for the requested step then 0 timestamp is     *
 *                             returned                                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_pp_history_get(const zbx_pp_history_t *history, int index, const zbx_variant_t **value, zbx_timespec_t *ts)
{
	if (NULL != history)
	{
		for (int i = 0; i < history->step_history.values_num; i++)
		{
			if (history->step_history.values[i].index == index)
			{
				*value = &history->step_history.values[i].value;
				*ts = history->step_history.values[i].ts;

				return;
			}
		}
	}

	*value = NULL;

	ts->sec = 0;
	ts->ns = 0;
}

void	zbx_pp_history_init(zbx_pp_history_t *history)
{
	zbx_vector_pp_step_history_create(&history->step_history);
}

void	zbx_pp_history_clear(zbx_pp_history_t *history)
{
	for (int i = 0; i < history->step_history.values_num; i++)
		zbx_variant_clear(&history->step_history.values[i].value);

	zbx_vector_pp_step_history_destroy(&history->step_history);
}

zbx_pp_history_cache_t	*zbx_pp_history_cache_create(void)
{
	zbx_pp_history_cache_t	*history_cache;
	int			err;

	history_cache = (zbx_pp_history_cache_t *)zbx_malloc(NULL, sizeof(zbx_pp_history_cache_t));
	memset(history_cache, 0, sizeof(zbx_pp_history_cache_t));
	history_cache->refcount = 1;

	if (0 != (err = pthread_mutex_init(&history_cache->lock, NULL)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot initialize preprocessing history cache mutex: %s",
				zbx_strerror(err));
		zbx_free(history_cache);
	}

	return history_cache;
}

zbx_pp_history_cache_t	*zbx_pp_history_cache_acquire(zbx_pp_history_cache_t *history_cache)
{
	if (NULL == history_cache)
		return NULL;

	history_cache->refcount++;

	return history_cache;
}

void	zbx_pp_history_cache_release(zbx_pp_history_cache_t *history_cache)
{
	/* history cache is created/destroyed only by manager */
	if (NULL == history_cache || 0 != --history_cache->refcount)
		return;

	pthread_mutex_lock(&history_cache->lock);
	history_cache->history = zbx_pp_history_release(history_cache->history);
	pthread_mutex_unlock(&history_cache->lock);

	pthread_mutex_destroy(&history_cache->lock);
	zbx_free(history_cache);
}

zbx_pp_history_t	*zbx_pp_history_cache_history_acquire(zbx_pp_history_cache_t *history_cache)
{
	zbx_pp_history_t	*history;

	if (NULL == history_cache)
		return NULL;

	pthread_mutex_lock(&history_cache->lock);

	history = history_cache->history;
	if (NULL != history)
		history->refcount++;

	pthread_mutex_unlock(&history_cache->lock);

	return history;
}

void	zbx_pp_history_cache_history_set_and_release(zbx_pp_history_cache_t *history_cache, zbx_pp_history_t *history_in,
		zbx_pp_history_t *history_out)
{
	if (NULL == history_cache)
		return;

	pthread_mutex_lock(&history_cache->lock);

	zbx_pp_history_release(history_in);
	zbx_pp_history_release(history_cache->history);
	history_cache->history = history_out;

	pthread_mutex_unlock(&history_cache->lock);
}
