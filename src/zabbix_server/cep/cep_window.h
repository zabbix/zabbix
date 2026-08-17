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

#ifndef ZABBIX_CEP_WINDOW_H
#define ZABBIX_CEP_WINDOW_H

#include "cep_event.h"
#include "zbxmw.h"
#include "zbxcacheconfig.h"
#include "zbxtypes_ext.h"

typedef enum
{
	CEP_LOCATION_UNKNOWN,
	CEP_LOCATION_QUEUE,
	CEP_LOCATION_REMOVED
}
zbx_cep_location_t;

#define CEP_WINDOW_FLAGS_NONE			0x0000
#define CEP_WINDOW_FLAGS_SYMPTOM_TAG_SET	0x0001

typedef struct zbx_cep_window_ref zbx_cep_window_ref_t;

/* the ordering reflects the syncing priority */
typedef enum
{
	CEP_WINDOW_SYNC_DESTROY,	/* remove window and skip rest of updates */
	CEP_WINDOW_SYNC_CREATE,		/* window must be created before events can be added/removed */
	CEP_WINDOW_SYNC_EVENT_REMOVE,	/* event removal will swallow adding of the same event */
	CEP_WINDOW_SYNC_EVENT_ADD
}
zbx_cep_window_sync_type_t;

typedef struct
{
	zbx_cep_window_sync_type_t	type;
	zbx_uint64_t			eventid;
	zbx_uint64_t			index;
}
zbx_cep_window_sync_entry_t;

ZBX_VECTOR_LITE_DECL(cep_window_sync_entry, zbx_cep_window_sync_entry_t)

typedef struct
{
	zbx_uint64_t		ruleid;
	zbx_uint64_t		windowid;

	int			type;
	int			duration;
	int			capacity;

	/* window location and access_num are read/written only within window pool lock */
	zbx_cep_location_t	location;

	int			access_num;	/* number of workers adding events,                      */
						/* window cannot be removed while events are being added */

	time_t			time_created;
	zbx_queue_ptr_t		hevents;
	unsigned char		flags;


	zbx_uint64_t		next_index;

	char			*js_script;
	char			*js_code;
	int			js_codelen;

	zbx_vector_cep_window_sync_entry_t	sync;

	zbx_atomic_uint64_t	nextcheck;
	zbx_atomic_uint32_t	refcount;
	zbx_cep_window_ref_t	*ref;
	pthread_mutex_t		lock;
}
zbx_cep_window_t;

ZBX_PTR_VECTOR_LITE_DECL(cep_window_ptr, zbx_cep_window_t *)

struct zbx_cep_window_ref
{
	zbx_uint64_t	ruleid;
	unsigned char	group_by;
	zbx_uint64_t	hostid;
	zbx_uint64_t	hostgroupid;
	char		*tag;
	char		*tag_value;

	zbx_cep_window_t	*window;
};

zbx_cep_window_t	*cep_window_addref(zbx_cep_window_t *window);
void	cep_window_release(zbx_cep_window_t *window);
void	cep_window_sync_detach(zbx_cep_window_t *window, zbx_vector_cep_window_sync_entry_t *sync);
void	cep_window_lock(zbx_cep_window_t *window);
void	cep_window_unlock(zbx_cep_window_t *window);

zbx_cep_window_t	*cep_get_window_or_create(zbx_hashset_t *windows, const zbx_cep_rule_t *rule,
		zbx_cep_event_context_t *ctx);

void	cep_window_sliding_process_event(const zbx_cep_rule_t *rule, zbx_cep_event_context_t *ctx,
		zbx_vector_mw_task_ptr_t *tasks);
void	cep_window_sliding_process(zbx_cep_window_t *window, time_t now, zbx_vector_mw_task_ptr_t *tasks);

void	cep_window_causal_process_event(const zbx_cep_rule_t *rule, zbx_cep_event_context_t *ctx,
		zbx_vector_mw_task_ptr_t *tasks);
void	cep_window_causal_process(zbx_cep_window_t *window, time_t now, zbx_vector_mw_task_ptr_t *tasks);

void	cep_window_js_process_event(const zbx_cep_rule_t *rule, zbx_cep_event_context_t *ctx,
	zbx_vector_mw_task_ptr_t *tasks);
void	cep_window_js_process(zbx_cep_window_t *window, time_t now, zbx_vector_mw_task_ptr_t *tasks);

void	cep_window_process(zbx_cep_window_t *window, time_t now, zbx_vector_mw_task_ptr_t *tasks);

/*
 * window pool
 */

typedef struct
{
	int	windows_num;
	int	alarms_num;
	int	ticks_num;
}
zbx_cep_window_pool_stats_t;

typedef struct zbx_cep_window_pool zbx_cep_window_pool_t;

zbx_cep_window_pool_t	*cep_window_pool_create(void);
void	cep_window_pool_destroy(void *a);

zbx_cep_window_t	*cep_window_pool_get_or_create_window(zbx_cep_window_pool_t *pool, const zbx_cep_rule_t *rule,
		zbx_cep_event_context_t *ctx, char **error);
void	cep_window_pool_remove_window(zbx_cep_window_pool_t *pool, zbx_cep_window_t *window);
int	cep_window_pool_next_batch(zbx_cep_window_pool_t *pool, time_t now,
		zbx_vector_cep_window_ptr_t *windows);
void	cep_window_pool_enqueue(zbx_cep_window_pool_t *pool, zbx_cep_window_t *window);
void	cep_window_pool_load(zbx_cep_window_pool_t *pool, zbx_dbconn_pool_t *dbpool);

void	cep_window_pool_get_stats(zbx_cep_window_pool_t *pool, zbx_cep_window_pool_stats_t *stats);

void	cep_window_pool_dump(zbx_cep_window_pool_t *pool);

void	cep_remove_windows_by_rule(zbx_uint64_t ruleid, zbx_vector_mw_task_ptr_t *tasks);

#endif

