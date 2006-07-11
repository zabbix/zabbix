/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#ifndef ZABBIX_TYPES_H
#define ZABBIX_TYPES_H

#if defined(WIN32)

#	define zbx_uint64_t __int64
#	define ZBX_FS_UI64 "%llu"

#	define zbx_pid_t	int

#	define stat		_stat
#	define snprintf		_snprintf
#	define vsnprintf	_vsnprintf

#	define alloca		_alloca

#ifndef uint32_t
#	define uint32_t	__int32
#endif /* uint32_t */

#else /* WIN32 */

#	define zbx_uint64_t uint64_t
#	if __WORDSIZE == 64
#		define ZBX_FS_UI64 "%lu"
#	else /* __WORDSIZE == 64 */
#		define ZBX_FS_UI64 "%llu"
#	endif /* __WORDSIZE == 64 */

#	define zbx_pid_t	pid_t

#endif /* WIN32 */

#ifndef S_ISREG
#	define S_ISREG(x) (((x) & S_IFMT) == S_IFREG)
#endif

#endif
