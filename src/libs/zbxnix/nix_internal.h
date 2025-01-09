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
#ifndef ZABBIX_NIX_INTERNAL_H
#define ZABBIX_NIX_INTERNAL_H

#include "zbxnix.h"

zbx_get_progname_f			nix_get_progname_cb(void);
zbx_get_process_info_by_thread_f	nix_get_process_info_by_thread_func_cb(void);

#endif /* ZABBIX_NIX_INTERNAL_H */
