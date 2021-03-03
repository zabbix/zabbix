/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

#ifndef SCREENS_CONVERTER_H
#define SCREENS_CONVERTER_H

int	DBpatch_delete_screen(uint64_t screenid);
int	DBpatch_convert_slideshow(uint64_t slideshowid, char *name, int delay, uint64_t userid, int private);
int	DBpatch_delay_routine(const char *screen_delay, int *dashboard_delay);
int	DBpatch_convert_screen(uint64_t screenid, char *name, uint32_t templateid, uint64_t userid, int private);

#endif
