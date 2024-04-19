/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
#ifndef ZABBIX_NIX_INTERNAL_H
#define ZABBIX_NIX_INTERNAL_H

#include "zbxnix.h"

zbx_get_progname_f			nix_get_progname_cb(void);
zbx_get_process_info_by_thread_f	nix_get_process_info_by_thread_func_cb(void);

#endif /* ZABBIX_NIX_INTERNAL_H */
