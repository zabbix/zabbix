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

#ifndef ZABBIX_HISTORY_H
#define ZABBIX_HISTORY_H

#include "zbxhistory.h"
#include "zbxtypes.h"

#define HISTORY_PROVIDER_SQL		"sql"	/* default provider */
#define HISTORY_PROVIDER_ELASTIC	"elastic"
#define HISTORY_PROVIDER_CLICKHOUSE	"clickhouse"

#define HISTORY_PROVIDER_OPTION_NAME			"name"
#define HISTORY_PROVIDER_OPTION_PATH			"path"
#define HISTORY_PROVIDER_OPTION_LOG_SLOW_QUERIES	"log_slow_queries"
#define HISTORY_PROVIDER_OPTION_URL			"url"
#define HISTORY_PROVIDER_OPTION_USERNAME		"username"
#define HISTORY_PROVIDER_OPTION_PASSWORD		"password"
#define HISTORY_PROVIDER_OPTION_DB			"db"
#define HISTORY_PROVIDER_OPTION_TYPES			"types"
#define HISTORY_PROVIDER_OPTION_DATE_INDEX		"date_index"

typedef struct
{
	zbx_history_provider_write_t		write;
	zbx_history_provider_flush_t		flush;
	zbx_history_provider_fetch_t		fetch;
	zbx_history_provider_close_t		close;
	zbx_history_provider_get_info_t		get_info;
}
zbx_history_provider_impl_t;

typedef struct
{
	char				*name;
	void				*data;
	zbx_history_provider_impl_t	impl;
	zbx_uint64_t			traits;
}
zbx_history_provider_t;

zbx_uint64_t	history_make_flush_error(int ret, unsigned char value_type);

const char	*history_value_type_desc(unsigned char value_type);

#endif
