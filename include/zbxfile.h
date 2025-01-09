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

#ifndef ZABBIX_ZBXFILE_H
#define ZABBIX_ZBXFILE_H

#include "zbxcommon.h"

typedef struct
{
	zbx_fs_time_t	modification_time;	/* time of last modification */
	zbx_fs_time_t	access_time;		/* time of last access */
	zbx_fs_time_t	change_time;		/* time of last status change */
}
zbx_file_time_t;

int	zbx_get_file_time(const char *path, int sym, zbx_file_time_t *time);
void	zbx_find_cr_lf_szbyte(const char *encoding, const char **cr, const char **lf, size_t *szbyte);
char	*zbx_find_buf_newline(char *p, char **p_next, const char *p_end, const char *cr, const char *lf,
		size_t szbyte);

#define ZBX_READ_ERR		-1
#define ZBX_READ_WRONG_ENCODING	-2
int	zbx_read_text_line_from_file(int fd, char *buf, size_t count, const char *encoding);
int	zbx_is_regular_file(const char *path);
char	*zbx_fgets(char *buffer, int size, FILE *fp);
int	zbx_write_all(int fd, const char *buf, size_t n);

#if defined(_WINDOWS) || defined(__MINGW32__)
#	define zbx_open(pathname, flags)	__zbx_open(pathname, flags | O_BINARY)
#	define ZBX_PATH_SEPARATOR	'\\'
int	__zbx_open(const char *pathname, int flags);
#else
#	define zbx_open(pathname, flags)	open(pathname, flags)
#	define ZBX_PATH_SEPARATOR	'/'
#endif

ssize_t	zbx_buf_readln(int fd, char *buf, size_t bufsz, const char *encoding, char **value, void **saveptr);

#endif /* ZABBIX_ZBXFILE_H */
