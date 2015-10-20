[ -z "$1" ] && echo "Usage: ./export_data.sh <DB name>
The script generates data file out of existing MySQL database." && exit 1

dbname=$1
basedir=`dirname "$0"`
schema=$basedir/../src/schema.tmpl

echo "--
-- Zabbix
-- Copyright (C) 2001-2015 Zabbix SIA
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

for table in `grep TABLE "$schema" | grep ZBX_DATA | awk -F'|' '{print $2}'`; do
	if [ "0" == `echo "select count(*) from $table" | mysql -uroot $dbname | tail -1` ]; then
		continue
	fi
	echo "TABLE |$table"
	fields=""
	sortorder=""
	# get list of all fields
	for i in `seq 1 1000`; do
		line=`grep -v ZBX_NODATA "$schema" | grep -A $i "TABLE|$table|" | tail -1 | grep FIELD`
		[ -z "$line" ] && break
		field=`echo $line | awk -F'|' '{print $2}'`
		fields="$fields,replace(replace(replace($field,'|','&pipe;'),'\r\n','&eol;'),'\n','&eol;') as $field"
		# figure out references to itself for correct sort order
		reftable=`echo $line | cut -f8 -d'|' | sed -e 's/ //'`
		if [ "$table" = "$reftable" ]; then
			pri_field=`echo $line | cut -f2 -d'|' | sed -e 's/ //'`
			ref_field=`echo $line | cut -f9 -d'|' | sed -e 's/ //'`
			# this strange sort order works fine with MySQL
			sortorder="order by $pri_field<$ref_field,$ref_field"
		fi
	done
	# remove first comma
	fields=`echo $fields | cut -c2-`
	echo "select $fields from $table $sortorder" | mysql -t -uroot $dbname | grep -v '^+' | sed -e 's/ | /|/g' -e '1,1s/^| /FIELDS|/g' -e '2,$s/^| /ROW   |/g' -e 's/ |$/|/g'
	echo ""
done
