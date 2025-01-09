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

#ifndef ZABBIX_EXPORT_H
#define ZABBIX_EXPORT_H

#include "zbxtypes.h"

#define ZBX_FLAG_EXPTYPE_EVENTS		1
#define ZBX_FLAG_EXPTYPE_HISTORY	2
#define ZBX_FLAG_EXPTYPE_TRENDS		4

typedef struct
{
	char	*name;
	FILE	*file;
	int	missing;
}
zbx_export_file_t;

typedef zbx_export_file_t	*(*zbx_get_export_file_f)(void);

typedef struct
{
	char		*dir;
	char		*type;
	zbx_uint64_t	file_size;
} zbx_config_export_t;

int	zbx_init_library_export(zbx_config_export_t *zbx_config_export, char **error);
void	zbx_deinit_library_export(void);

int	zbx_validate_export_type(char *export_type, uint32_t *export_mask);
int	zbx_is_export_enabled(uint32_t flags);
int	zbx_has_export_dir(void);
void	zbx_export_deinit(zbx_export_file_t *file);

zbx_export_file_t	*zbx_problems_export_init(zbx_get_export_file_f get_export_file_cb, const char *process_name,
		int process_num);
void	zbx_problems_export_write(const char *buf, size_t count);
void	zbx_problems_export_flush(void);

zbx_export_file_t	*zbx_history_export_init(zbx_get_export_file_f get_export_file_cb, const char *process_name,
		int process_num);
void	zbx_history_export_write(const char *buf, size_t count);
void	zbx_history_export_flush(void);

zbx_export_file_t	*zbx_trends_export_init(zbx_get_export_file_f get_export_file_cb, const char *process_name,
		int process_num);
void	zbx_trends_export_write(const char *buf, size_t count);
void	zbx_trends_export_flush(void);

#endif
