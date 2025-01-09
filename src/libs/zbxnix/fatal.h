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

#ifndef ZABBIX_FATAL_H
#define ZABBIX_FATAL_H

#define ZBX_FATAL_LOG_PC_REG_SF		0x0001
#define ZBX_FATAL_LOG_BACKTRACE		0x0002
#define ZBX_FATAL_LOG_MEM_MAP		0x0004
#define ZBX_FATAL_LOG_FULL_INFO		(ZBX_FATAL_LOG_PC_REG_SF | ZBX_FATAL_LOG_BACKTRACE | ZBX_FATAL_LOG_MEM_MAP)

const char	*get_signal_name(int sig);
void		zbx_log_fatal_info(void *context, unsigned int flags);

#endif /* ZABBIX_FATAL_H */
