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

#ifndef ZABBIX_AUDIT_GRAPH_H
#define ZABBIX_AUDIT_GRAPH_H

#include "zbxalgo.h"

void	zbx_audit_graph_create_entry(int audit_context_mode, int audit_action, zbx_uint64_t graphid, const char *name,
		int flags);

void	zbx_audit_graph_update_json_add_data(int audit_context_mode, zbx_uint64_t graphid, const char *name, int width,
		int height, double yaxismin, double yaxismax, zbx_uint64_t templateid, int show_work_period,
		int show_triggers, int graphtype, int show_legend, int show_3d, double percent_left,
		double percent_right, int ymin_type, int ymax_type, zbx_uint64_t ymin_itemid, zbx_uint64_t ymax_itemid,
		int flags, int discover);

void	zbx_audit_graph_update_json_add_gitems(int audit_context_mode, zbx_uint64_t graphid, int flags,
		zbx_uint64_t gitemid, int drawtype, int sortorder, const char *color, int yaxisside, int calc_fnc,
		int type, zbx_uint64_t itemid);

#define PREPARE_AUDIT_GRAPH_UPDATE(resource, type1)								\
void	zbx_audit_graph_update_json_update_##resource(int audit_context_mode, zbx_uint64_t graphid, int flags,	\
		type1 resource##_old, type1 resource##_new);							\

PREPARE_AUDIT_GRAPH_UPDATE(name, const char*)
PREPARE_AUDIT_GRAPH_UPDATE(width, int)
PREPARE_AUDIT_GRAPH_UPDATE(height, int)
PREPARE_AUDIT_GRAPH_UPDATE(yaxismin, double)
PREPARE_AUDIT_GRAPH_UPDATE(yaxismax, double)
PREPARE_AUDIT_GRAPH_UPDATE(show_work_period, int)
PREPARE_AUDIT_GRAPH_UPDATE(show_triggers, int)
PREPARE_AUDIT_GRAPH_UPDATE(graphtype, int)
PREPARE_AUDIT_GRAPH_UPDATE(show_legend, int)
PREPARE_AUDIT_GRAPH_UPDATE(show_3d, int)
PREPARE_AUDIT_GRAPH_UPDATE(percent_left, double)
PREPARE_AUDIT_GRAPH_UPDATE(percent_right, double)
PREPARE_AUDIT_GRAPH_UPDATE(ymin_type, int)
PREPARE_AUDIT_GRAPH_UPDATE(ymax_type, int)
PREPARE_AUDIT_GRAPH_UPDATE(ymin_itemid, zbx_uint64_t)
PREPARE_AUDIT_GRAPH_UPDATE(ymax_itemid, zbx_uint64_t)
PREPARE_AUDIT_GRAPH_UPDATE(discover, int)
PREPARE_AUDIT_GRAPH_UPDATE(templateid, zbx_uint64_t)
#undef PREPARE_AUDIT_GRAPH_UPDATE

void	zbx_audit_graph_update_json_update_gitem_create_entry(int audit_context_mode, zbx_uint64_t graphid, int flags,
		zbx_uint64_t gitemid);

#define PREPARE_AUDIT_GRAPH_UPDATE(resource, type1)						\
void	zbx_audit_graph_update_json_update_gitem_update_##resource(int audit_context_mode,	\
		zbx_uint64_t graphid, int flags, zbx_uint64_t gitemid, type1 resource##_old,	\
		type1 resource##_new);
PREPARE_AUDIT_GRAPH_UPDATE(itemid, zbx_uint64_t)
PREPARE_AUDIT_GRAPH_UPDATE(drawtype, int)
PREPARE_AUDIT_GRAPH_UPDATE(sortorder, int)
PREPARE_AUDIT_GRAPH_UPDATE(color, const char*)
PREPARE_AUDIT_GRAPH_UPDATE(yaxisside, int)
PREPARE_AUDIT_GRAPH_UPDATE(calc_fnc, int)
PREPARE_AUDIT_GRAPH_UPDATE(type, int)
#undef PREPARE_AUDIT_GRAPH_UPDATE

void	zbx_audit_graph_update_json_delete_gitems(int audit_context_mode, zbx_uint64_t graphid, int flags,
		zbx_uint64_t gitemid);

void	zbx_audit_graph_delete(int audit_context_mode, zbx_vector_uint64_t *graphids);

#endif	/* ZABBIX_AUDIT_GRAPH_H */
