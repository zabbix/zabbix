/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#ifndef ZABBIX_SIGHANDLER_H
#define ZABBIX_SIGHANDLER_H

#include "sysinc.h"

void	zbx_set_common_signal_handlers(void);
void	zbx_set_child_signal_handler(void);
void	zbx_unset_child_signal_handler(void);
void	zbx_set_metric_thread_signal_handler(void);
void	zbx_block_signals(sigset_t *orig_mask);
void	zbx_unblock_signals(const sigset_t *orig_mask);

void	zbx_set_exit_on_terminate(void);
void	zbx_unset_exit_on_terminate(void);

#endif
