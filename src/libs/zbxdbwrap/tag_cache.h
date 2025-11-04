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

#include "zbxalgo.h"

int	zbx_db_delete_host_template_cache(zbx_uint64_t hostid, zbx_vector_uint64_t *del_templateids);
int	zbx_db_copy_host_template_cache(zbx_uint64_t hostid, zbx_vector_uint64_t *lnk_templateids);
void	zbx_db_save_item_template_cache(zbx_uint64_t hostid, zbx_vector_uint64_t *new_itemids);
