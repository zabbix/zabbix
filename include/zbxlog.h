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

#ifndef ZABBIX_ZBXLOG_H
#define ZABBIX_ZBXLOG_H

#include "zbxcommon.h"

#define ZBX_LOG_TYPE_UNDEFINED	0
#define ZBX_LOG_TYPE_SYSTEM	1
#define ZBX_LOG_TYPE_FILE	2
#define ZBX_LOG_TYPE_CONSOLE	3

#define ZBX_OPTION_LOGTYPE_SYSTEM	"system"
#define ZBX_OPTION_LOGTYPE_FILE		"file"
#define ZBX_OPTION_LOGTYPE_CONSOLE	"console"

#define ZBX_LOG_ENTRY_INTERVAL_DELAY	60	/* seconds */

void	__zbx_update_env(double time_now);

#ifdef _WINDOWS
#define zbx_update_env(info, time_now)			\
							\
do							\
{							\
	__zbx_update_env(time_now);			\
	ZBX_UNUSED(info);				\
}							\
while (0)
#else
#define zbx_update_env(info, time_now)			\
							\
do							\
{							\
	__zbx_update_env(time_now);			\
	zbx_prof_update(info, time_now);		\
}							\
while (0)
#endif

typedef struct
{
	char	*log_file_name;
	char	*log_type_str;
	int	log_type;
	int	log_file_size;
} zbx_config_log_t;

int	zbx_open_log(const zbx_config_log_t *log_file_cfg, int level, const char *syslog_app_name,
		const char *event_source, char **error);
void	zbx_log_impl(int level, const char *fmt, va_list args);
void	zbx_close_log(void);

char	*zbx_strerror_from_system(zbx_syserror_t error);

#ifdef _WINDOWS
char		*zbx_strerror_from_module(zbx_syserror_t error, const wchar_t *module);
#endif

int		zbx_redirect_stdio(const char *filename);

void		zbx_handle_log(void);

int		zbx_get_log_type(const char *logtype);
int		zbx_validate_log_parameters(ZBX_TASK_EX *task, const zbx_config_log_t *log_file_cfg);

void	zbx_strlog_alloc(int level, char **out, size_t *out_alloc, size_t *out_offset, const char *format,
		...) __zbx_attr_format_printf(5, 6);

#endif
