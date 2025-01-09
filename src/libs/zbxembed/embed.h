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

#ifndef ZABBIX_EMBED_H
#define ZABBIX_EMBED_H

#include "zbxembed.h"

#include "zbxtime.h"
#include "zbxalgo.h"

#include "duktape.h"

#define ZBX_ES_LOG_MEMORY_LIMIT	(ZBX_MEBIBYTE * 8)
#define ZBX_ES_LOG_MSG_LIMIT	8000

/* this macro can be used in time intensive C functions to check for script timeout execution */
#define ZBX_ES_CHECK_TIMEOUT(ctx, env)									\
	do												\
	{												\
		zbx_uint64_t	elapsed_ms;								\
		elapsed_ms = zbx_get_duration_ms(&env->start_time);					\
		if (elapsed_ms >= (zbx_uint64_t)env->timeout * 1000)					\
			return duk_error(ctx, DUK_RET_TYPE_ERROR, "script execution timeout occurred");	\
	}												\
	while (0)

struct zbx_es_env
{
	duk_context	*ctx;
	size_t		total_alloc;
	size_t		max_total_alloc;
	zbx_timespec_t	start_time;

	char		*error;
	int		rt_error_num;
	int		fatal_error;
	int		timeout;
	struct zbx_json	*json;

	jmp_buf		loc;

	int		http_req_objects;

	int		logged_msgs;

	const char	*config_source_ip;

	char		*browser_endpoint;

	int		browser_objects;
	int		constructor_chain;

	void		*json_parse;
	void		*json_stringify;

	zbx_hashset_t	objmap;
};

zbx_es_env_t	*zbx_es_get_env(duk_context *ctx);

int	es_duktape_string_decode(const char *duk_str, char **out_str);
void	es_push_result_string(duk_context *ctx, char *str, size_t size);

duk_ret_t	es_super(duk_context *ctx, const char *base, int args);
int	es_is_chained_constructor_call(duk_context *ctx);

typedef enum
{
	ES_OBJ_HTTPREQUEST,
	ES_OBJ_BROWSER,
	ES_OBJ_ELEMENT,
	ES_OBJ_ALERT
}
zbx_es_obj_type_t;

void	es_obj_attach_data(zbx_es_env_t *env, void *objptr, void *data, zbx_es_obj_type_t type);
void	*es_obj_get_data(zbx_es_env_t *env, const void *objptr, zbx_es_obj_type_t type);
void	*es_obj_detach_data(zbx_es_env_t *env, void *objptr, zbx_es_obj_type_t type);

#endif /* ZABBIX_EMBED_H */
