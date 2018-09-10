/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#ifndef ZABBIX_ZBXMODULES_H
#define ZABBIX_ZBXMODULES_H

typedef struct
{
	void	*lib;
	char	*name;
}
zbx_module_t;

typedef struct
{
	zbx_module_t	*module;
	void		(*history_float_cb)(const ZBX_HISTORY_FLOAT *, int);
}
zbx_history_float_cb_t;

typedef struct
{
	zbx_module_t	*module;
	void		(*history_integer_cb)(const ZBX_HISTORY_INTEGER *, int);
}
zbx_history_integer_cb_t;

typedef struct
{
	zbx_module_t	*module;
	void		(*history_string_cb)(const ZBX_HISTORY_STRING *, int);
}
zbx_history_string_cb_t;

typedef struct
{
	zbx_module_t	*module;
	void		(*history_text_cb)(const ZBX_HISTORY_TEXT *, int);
}
zbx_history_text_cb_t;

typedef struct
{
	zbx_module_t	*module;
	void		(*history_log_cb)(const ZBX_HISTORY_LOG *, int);
}
zbx_history_log_cb_t;

extern zbx_history_float_cb_t	*history_float_cbs;
extern zbx_history_integer_cb_t	*history_integer_cbs;
extern zbx_history_string_cb_t	*history_string_cbs;
extern zbx_history_text_cb_t	*history_text_cbs;
extern zbx_history_log_cb_t	*history_log_cbs;

int	zbx_load_modules(const char *path, char **file_names, int timeout, int verbose);
void	zbx_unload_modules(void);

#endif
