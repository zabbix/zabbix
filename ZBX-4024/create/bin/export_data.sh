[ -z "$1" ] && echo "Usage: ./export_data.sh <DB name>" && exit

dbname=$1

cat > data.tmpl.new <<EOL
--
-- Zabbix
-- Copyright (C) 2000-2011 Zabbix SIA
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 2 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program; if not, write to the Free Software
-- Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
--

EOL

for table in `grep TABLE schema.tmpl|grep ZBX_DATA|awk -F'|' '{print $2}'`; do
	echo "TABLE |$table" >> data.tmpl.new
	fields=""
	# get list of all fields
	for i in `seq 1 1000`; do
		line=`grep -v ZBX_NODATA schema.tmpl|grep -A $i "TABLE|$table|"|tail -1|grep FIELD`
		[ -z "$line" ] && break
		field=`echo $line|awk -F'|' '{print $2}'`
		fields="$fields,$field"
	done
	# remove first comma
	fields=`echo $fields|cut -c2-`
	echo "select $fields from $table" | mysql -t -uroot $dbname | grep -v '^+' | sed -e 's/ | /|/g' -e '1,1s/^| /FIELDS|/g' -e '2,$s/^| /ROW   |/g' -e 's/ |$/|/g' >> data.tmpl.new
	echo "" >> data.tmpl.new
done
