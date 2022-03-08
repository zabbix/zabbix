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

#include "selfmon.h"

#include "common.h"

extern int	CONFIG_REPORTMANAGER_FORKS;
extern int	CONFIG_REPORTWRITER_FORKS;

/******************************************************************************
 *                                                                            *
 * Purpose: Returns number of processes depending on process type             *
 *                                                                            *
 * Parameters: proc_type - [IN] process type; ZBX_PROCESS_TYPE_*              *
 *                                                                            *
 * Return value: number of processes                                          *
 *                                                                            *
 ******************************************************************************/
int	get_component_process_type_forks(unsigned char proc_type)
{
	switch (proc_type)
	{
		case ZBX_PROCESS_TYPE_REPORTMANAGER:
			return CONFIG_REPORTMANAGER_FORKS;
		case ZBX_PROCESS_TYPE_REPORTWRITER:
			return CONFIG_REPORTWRITER_FORKS;
	}

	return 0;
}
