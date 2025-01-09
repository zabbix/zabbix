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

#ifndef ZABBIX_SEND_BUFFER_H
#define ZABBIX_SEND_BUFFER_H

#include "zbxcommon.h"
#include "zbxalgo.h"
#include "zbxjson.h"

/* sending a huge amount of values in a single connection is likely to */
/* take long and hit timeout, so we limit values to 250 per connection */
#define VALUES_MAX	250

#define ZBX_SEND_GROUP_NONE	0
#define ZBX_SEND_GROUP_HOST	1

#define ZBX_SEND_BATCHED	0
#define ZBX_SEND_IMMEDIATE	1

typedef struct
{
	int	group_mode;
	int	with_clock;
	int	with_ns;
	char	*host;

	/* temporary buffers */
	char	*key;
	char	*value;
	size_t	kv_alloc;

	zbx_hashset_t	batches;
}
zbx_send_buffer_t;

const char	*get_string(const char *p, char *buf, size_t bufsize);

void	sb_init(zbx_send_buffer_t *buf, int group_mode, const char *host, int with_clock, int with_ns);
void	sb_destroy(zbx_send_buffer_t *buf);
int	sb_parse_line(zbx_send_buffer_t *buf, const char *line, size_t line_alloc, int immediate, struct zbx_json **out,
		char **error);
struct zbx_json	*sb_pop(zbx_send_buffer_t *buf);

#endif
