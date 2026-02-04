/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#ifndef ZABBIX_TYPES_EXT_H
#define ZABBIX_TYPES_EXT_H

/* type declarations using post c99 features */

#include "zbxsysinc.h"

#if defined(HAVE_STDATOMIC_H)
typedef _Atomic uint64_t zbx_atomic_uint64_t;
typedef _Atomic uint32_t zbx_atomic_uint32_t;
#else
typedef uint64_t zbx_atomic_uint64_t;
typedef uint32_t zbx_atomic_uint64_t;
#endif

#endif
