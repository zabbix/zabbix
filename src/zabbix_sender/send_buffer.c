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

#include "send_buffer.h"

#include "zbxalgo.h"
#include "zbxjson.h"
#include "zbxnum.h"

typedef struct
{
	char		*host;
	int		values_num;
	struct zbx_json	*json;
}
zbx_send_batch_t;

static zbx_hash_t	send_batch_hash(const void *d)
{
	const zbx_send_batch_t	*b = (const zbx_send_batch_t *)d;

	return ZBX_DEFAULT_STRING_HASH_ALGO(b->host, strlen(b->host), ZBX_DEFAULT_HASH_SEED);
}

static int	send_batch_compare(const void *d1, const void *d2)
{
	const zbx_send_batch_t	*b1 = (const zbx_send_batch_t *)d1;
	const zbx_send_batch_t	*b2 = (const zbx_send_batch_t *)d2;

	return strcmp(b1->host, b2->host);
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize send buffer                                            *
 *                                                                            *
 ******************************************************************************/
void	sb_init(zbx_send_buffer_t *buf, int group_mode, const char *host, int with_clock, int with_ns)
{
	buf->group_mode = group_mode;
	buf->with_clock = with_clock;
	buf->with_ns = with_ns;
	buf->host = (NULL == host ? NULL : zbx_strdup(NULL, host));
	buf->key = NULL;
	buf->value = NULL;
	buf->kv_alloc = 0;

	zbx_hashset_create(&buf->batches, 100, send_batch_hash, send_batch_compare);
}

/******************************************************************************
 *                                                                            *
 * Purpose: destroy send buffer                                               *
 *                                                                            *
 ******************************************************************************/
void	sb_destroy(zbx_send_buffer_t *buf)
{
	zbx_hashset_iter_t	iter;
	zbx_send_batch_t	*batch;

	zbx_hashset_iter_reset(&buf->batches, &iter);
	while (NULL != (batch = (zbx_send_batch_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_free(batch->host);

		if (NULL != batch->json)
		{
			zbx_json_free(batch->json);
			zbx_free(batch->json);
		}
	}

	zbx_hashset_destroy(&buf->batches);

	zbx_free(buf->key);
	zbx_free(buf->value);
	zbx_free(buf->host);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add parsed value to the send buffer                               *
 *                                                                            *
 * Parameters: buf   - [IN/OUT] send buffer                                   *
 *             host  - [IN] host name                                         *
 *             key   - [IN] item key                                          *
 *             value - [IN] value                                             *
 *             clock - [IN] value timestamp seconds (can be 0)                *
 *             ns    - [IN] value timestamp nanoseconds (can be 0)            *
 *                                                                            *
 * Return value: The batch the value was added to.                            *
 *                                                                            *
 * Comments: Depending on sending mode it will group batches by hosts or have *
 *           one batch for all values.                                        *
 *                                                                            *
 ******************************************************************************/
static zbx_send_batch_t	*sb_add_value(zbx_send_buffer_t *buf, const char *host, const char *key, const char *value,
		int clock, int ns)
{
	zbx_send_batch_t	batch_local, *batch;

	if (ZBX_SEND_GROUP_NONE == buf->group_mode)
		batch_local.host = "";
	else
		batch_local.host = (char *)(uintptr_t)host;

	if (NULL == (batch = (zbx_send_batch_t *)zbx_hashset_search(&buf->batches, &batch_local)))
	{
		batch_local.host = zbx_strdup(NULL, batch_local.host);

		batch = (zbx_send_batch_t *)zbx_hashset_insert(&buf->batches, &batch_local, sizeof(batch_local));
		batch->values_num = 0;
		batch->json = NULL;
	}

	if (0 == batch->values_num)
	{
		batch->json = (struct zbx_json *)zbx_malloc(NULL, sizeof(struct zbx_json));

		zbx_json_init(batch->json, ZBX_JSON_STAT_BUF_LEN);
		zbx_json_addstring(batch->json, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_SENDER_DATA,
				ZBX_JSON_TYPE_STRING);
		zbx_json_addarray(batch->json, ZBX_PROTO_TAG_DATA);
	}

	zbx_json_addobject(batch->json, NULL);
	zbx_json_addstring(batch->json, ZBX_PROTO_TAG_HOST, host, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(batch->json, ZBX_PROTO_TAG_KEY, key, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(batch->json, ZBX_PROTO_TAG_VALUE, value, ZBX_JSON_TYPE_STRING);

	if (1 == buf->with_clock)
	{
		zbx_json_addint64(batch->json, ZBX_PROTO_TAG_CLOCK, clock);

		if (1 == buf->with_ns)
			zbx_json_addint64(batch->json, ZBX_PROTO_TAG_NS, ns);
	}

	zbx_json_close(batch->json);

	batch->values_num++;

	return batch;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse input line and cache parsed data                            *
 *                                                                            *
 * Parameters: buf        - [IN/OUT] send buffer                              *
 *             line       - [IN] input line                                   *
 *             line_alloc - [IN] number of bytes allocated for line buffer    *
 *             send_mode  - [IN] ZBX_SEND_BATCHED - cache the parsed data to  *
 *                                                  send in batches           *
 *                               ZBX_SEND_IMMEDIATE - prepare the batch with  *
 *                                                    added value for sending *
 *             out        - [OUT] data to send (NULL - nothing to send)       *
 *             error      - [OUT] error message                               *
 *                                                                            *
 * Return value: SUCCEED - line was parsed successfully                       *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: line format: <hostname> <key> [<timestamp>] [<ns>] <value>       *
 *                                                                            *
 ******************************************************************************/
int	sb_parse_line(zbx_send_buffer_t *buf, const char *line, size_t line_alloc, int send_mode, struct zbx_json **out,
		char **error)
{
	const char	*p = line;
	char		hostname[MAX_STRING_LEN], tmp[32];
	int		clock = 0, ns = 0;

	if ('\0' == *p || NULL == (p = get_string(p, hostname, sizeof(hostname))) || '\0' == *hostname)
	{
		*error = zbx_strdup(NULL, "'Hostname' required");
		return FAIL;
	}

	if (0 == strcmp(hostname, "-"))
	{
		if (NULL == buf->host)
		{
			*error = zbx_strdup(NULL, "'-' encountered as 'Hostname', "
					"but no default hostname was specified");
			return FAIL;
		}
		else
			zbx_strlcpy(hostname, buf->host, sizeof(hostname));
	}

	if (buf->kv_alloc != line_alloc)
	{
		buf->kv_alloc = line_alloc;
		buf->key = (char *)zbx_realloc(buf->key, buf->kv_alloc);
		buf->value = (char *)zbx_realloc(buf->value, buf->kv_alloc);
	}

	if ('\0' == *p || NULL == (p = get_string(p, buf->key, buf->kv_alloc)) || '\0' == *buf->key)
	{
		*error = zbx_strdup(NULL, "'Key' required");
		return FAIL;
	}

	if (1 == buf->with_clock)
	{
		if ('\0' == *p || NULL == (p = get_string(p, tmp, sizeof(tmp))) || '\0' == *tmp)
		{
			*error = zbx_strdup(NULL, "'Timestamp' required");
			return FAIL;
		}

		if (FAIL == zbx_is_uint31(tmp, &clock))
		{
			*error = zbx_strdup(NULL, "invalid 'Timestamp' value detected");
			return FAIL;
		}

		if (1 == buf->with_ns)
		{
			if ('\0' == *p || NULL == (p = get_string(p, tmp, sizeof(tmp))) || '\0' == *tmp)
			{
				*error = zbx_strdup(NULL, "'Nanoseconds' required");
				return FAIL;

			}

			if (FAIL == zbx_is_uint_n_range(tmp, sizeof(tmp), &ns, sizeof(ns), 0LL, 999999999LL))
			{
				*error = zbx_strdup(NULL, "invalid 'Nanoseconds' value detected");
				return FAIL;
			}
		}
	}

	if ('\0' != *p && '"' != *p)
	{
		zbx_strlcpy(buf->value, p, buf->kv_alloc);
	}
	else if ('\0' == *p || NULL == (p = get_string(p, buf->value, buf->kv_alloc)))
	{
		*error = zbx_strdup(NULL, "'Key value' required");
		return FAIL;
	}
	else if ('\0' != *p)
	{
		*error = zbx_strdup(NULL, "too many parameters");
		return FAIL;
	}

	zbx_send_batch_t	*batch;

	batch = sb_add_value(buf, hostname, buf->key, buf->value, clock, ns);

	if (ZBX_SEND_IMMEDIATE == send_mode || VALUES_MAX <= batch->values_num)
	{
		zbx_json_close(batch->json);
		*out = batch->json;

		batch->json = NULL;
		batch->values_num = 0;
	}
	else
		*out = NULL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: pop batch with cached values from buffer                          *
 *                                                                            *
 * Return value: SUCCEED - batch with cached data or NULL if the buffer is    *
 *                         empty                                              *
 ******************************************************************************/
struct zbx_json	*sb_pop(zbx_send_buffer_t *buf)
{
	zbx_hashset_iter_t	iter;
	zbx_send_batch_t	*batch;

	zbx_hashset_iter_reset(&buf->batches, &iter);
	while (NULL != (batch = (zbx_send_batch_t *)zbx_hashset_iter_next(&iter)))
	{
		struct zbx_json	*out = batch->json;
		int		values_num = batch->values_num;

		zbx_free(batch->host);
		zbx_hashset_iter_remove(&iter);

		if (0 != values_num)
		{
			zbx_json_close(out);

			return out;
		}
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get current string from the quoted or unquoted string list,       *
 *          delimited by blanks                                               *
 *                                                                            *
 * Parameters:                                                                *
 *      p       - [IN] parameter list, delimited by blanks (' ' or '\t')      *
 *      buf     - [OUT] output buffer                                         *
 *      bufsize - [IN] output buffer size                                     *
 *                                                                            *
 * Return value: pointer to the next string                                   *
 *                                                                            *
 ******************************************************************************/
const char	*get_string(const char *p, char *buf, size_t bufsize)
{
/* 0 - init, 1 - inside quoted param, 2 - inside unquoted param */
	int	state;
	size_t	buf_i = 0;

	bufsize--;	/* '\0' */

	for (state = 0; '\0' != *p; p++)
	{
		switch (state)
		{
			/* init state */
			case 0:
				if (' ' == *p || '\t' == *p)
				{
					/* skipping the leading spaces */
				}
				else if ('"' == *p)
				{
					state = 1;
				}
				else
				{
					state = 2;
					p--;
				}
				break;
			/* quoted */
			case 1:
				if ('"' == *p)
				{
					if (' ' != p[1] && '\t' != p[1] && '\0' != p[1])
						return NULL;	/* incorrect syntax */

					while (' ' == p[1] || '\t' == p[1])
						p++;

					buf[buf_i] = '\0';
					return ++p;
				}
				else if ('\\' == *p && ('"' == p[1] || '\\' == p[1]))
				{
					p++;
					if (buf_i < bufsize)
						buf[buf_i++] = *p;
				}
				else if ('\\' == *p && 'n' == p[1])
				{
					p++;
					if (buf_i < bufsize)
						buf[buf_i++] = '\n';
				}
				else if (buf_i < bufsize)
				{
					buf[buf_i++] = *p;
				}
				break;
			/* unquoted */
			case 2:
				if (' ' == *p || '\t' == *p)
				{
					while (' ' == *p || '\t' == *p)
						p++;

					buf[buf_i] = '\0';
					return p;
				}
				else if (buf_i < bufsize)
				{
					buf[buf_i++] = *p;
				}
				break;
		}
	}

	/* missing terminating '"' character */
	if (1 == state)
		return NULL;

	buf[buf_i] = '\0';

	return p;
}

