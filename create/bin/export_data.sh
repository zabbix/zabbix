#!/bin/bash

if [ -z "$1" ] || [ -z "$2" ] || [ -z "$3" ] || [ -z "$4" ] || [ -z "$5" ] || [ -z "$6" ]; then
	echo "Usage:
	./export_data.sh -hhost -Pport -uroot -p<password> <DB name> ZBX_DATA > ../src/data.tmpl
	./export_data.sh -hhost -Pport -uroot -p<password> <DB name> ZBX_TEMPLATE > ../src/templates.tmpl
	./export_data.sh -hhost -Pport -uroot -p<password> <DB name> ZBX_DASHBOARD > ../src/dashboards.tmpl
	The script generates data file out of existing MySQL database." && exit 1
fi
mysql_cmd="mysql $1 $2 $3 $4 $5"
dbflag=$6
basedir=`dirname "$0"`
schema=$basedir/../src/schema.tmpl

echo "--
-- Zabbix
-- Copyright (C) 2001-2022 Zabbix SIA
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 2 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program; if not, write to the Free Software
-- Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
--
"

IFS=$'\n'
for tbl_line in `grep "^TABLE.*${dbflag}" "${schema}"`; do
	tbl_line=${tbl_line#*|}
	table=${tbl_line%%|*}
	tbl_line=${tbl_line#*|}
	primary_key=${tbl_line%%|*}

	total_count=`echo "select count(*) from ${table}" | eval ${mysql_cmd} | tail -1`

	fields=''
	delim=''

	refs=()
	depth=()

	for fld_line in `sed -n "/^TABLE|${table}|/,/^$/ p" "${schema}" | grep "^FIELD" | sed 's/[ \t]//g'`; do
		fld_line=${fld_line#*|}		# FIELD
		field=${fld_line%%|*}
		fld_line=${fld_line#*|}		# <field_name>
		field_type=${fld_line%%|*}
		fld_line=${fld_line#*|}		# <field_type>
		default_val=${fld_line%%|*}
		fld_line=${fld_line#*|}		# <default>
		fld_line=${fld_line#*|}		# <not_null>
		flags=${fld_line%%|*}
		fld_line=${fld_line#*|}		# <flags>
		fld_line=${fld_line#*|}		# <index #>
		ref_table=${fld_line%%|*}
		fld_line=${fld_line#*|}		# <ref_table>
		ref_field=${fld_line%%|*}

		if [[ "$flags" =~ ZBX_NODATA ]]; then
			if [[ "$field_type" =~ ^t_(shorttext|text|longtext)$ ]]; then
				[[ -n "$default_val" ]] || default_val="''"

				fields="${fields}${delim} $default_val as ${field}"
			else
				continue
			fi
		else
			fields="${fields}${delim}replace(replace(replace(${field},'|','&pipe;'),'\r\n','&eol;'),'\n','&bsn;') as ${field}"
		fi
		delim=','

		if [[ ${ref_table} == ${table} ]]; then
			refs+=("${field}:${ref_field}")
			depth+=(0)
		fi
	done

	while true; do
		where=' '

		if [[ ${#refs[@]} -ne 0 ]]; then
			delim='where '

			for i in ${!refs[@]}; do
				field="${refs[$i]%:*}"
				ref_field="${refs[$i]#*:}"

				condition="${field} is null"
				for (( d = 0; d < ${depth[$i]}; d++ )); do
					condition="${field} in (select ${ref_field} from ${table} where ${condition})"
				done

				where="${where}${delim}${condition}"
				delim=' and '
			done
			where="${where} "
		fi

		count=`echo "select count(*) from ${table}${where}" | eval ${mysql_cmd} | tail -1`
		(( total_count -= count ))

		if [[ ${count} -eq 0 ]]; then
			if [[ ${#refs[@]} -ne 0 ]]; then
				inc=0

				for (( i = ${#depth[@]} - 1; i >= 0; i-- )); do
					if [[ $inc -ne 0 ]]; then
						(( depth[$i]++ ))
						break
					fi

					if [[ $i -eq 0 ]]; then
						break 2
					fi

					if [[ ${depth[$i]} -ne 0 ]]; then
						depth[$i]=0
						inc=1
					fi
				done

				continue
			fi

			break
		fi

		echo "TABLE |$table"
		echo "select ${fields} from ${table}${where}order by ${table}.${primary_key}" | eval "${mysql_cmd} -t" | grep -v '^+' | sed -e 's/ | /|/g' -e '1,1s/^| /FIELDS|/g' -e '2,$s/^| /ROW   |/g' -e 's/ |$/|/g'
		echo ""

		if [[ ${#refs[@]} -ne 0 ]]; then
			(( depth[${#depth[@]} - 1]++ ))
		else
			break
		fi
	done

	if [[ ${total_count} -ne 0 ]]; then
		echo "The total number of records in table \"${table}\" is not equal to the fetched records." >&2
		exit 1
	fi
done
