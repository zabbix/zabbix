#!/bin/bash

# This script can be used to create data.sql from a database.
# It creates sql file that is suitable for importing on top of schema.sql.
# INSERT statements refer to fields by name to avoid field ordering problems,
#  and only fields whose content differs from the default are included in the
#  resulting file.
#
# Only accepted parameter currently is database name. If omitted, 'zabbix' is
#  used. Resulting SQL is stored in FINALFILE file.
#
# This script must be run as a user that has write access to files created by
#  MySQL server user.
#
# Only tables listed are considered for data retrieval, and there is support for
#  additional finetuning:
#
# 1. Blacklist
#    Blacklist support allows to specify which fields should never be dumped from
#     the specified tables. This is mostly used to exclude runtime data like last
#     value, status or error messages. Blacklist entries are specified in
#     BLACKLISTFILE file. Each line contains space separated:
#         table field1 field2 field3...
#
#    Adding a hash mark (#) in front of the line will indicate that it is a
#     comment.
#
# 2. Filter
#    Filter allows to dump only specific entries from some tables. This is
#     currently used to dump only interesting entries from the 'profiles' table.
#    Filter entries are specified in FILTERFILE file. Each line contains space
#     separated:
#         table field value1 value2 value3...
#
#    From table 'table' only those entries will be dumped where 'field' is
#     one of values.
#    Adding a hash mark (#) in front of the line will indicate that it is a
#     comment.
#
# 3. Pre-dump hook
#    Pre-dump hook allows to execute a query right before dumping as specific
#     table.
#    Pre-dump hook entries are specified in PREDUMPHOOKFILE file. Each line
#     contains space separated:
#         table query
#
#    There is a possible race condition between executing the pre-dump hook and
#     dumping the table. This feature should not be used for critical changes at
#     this time.
#    Adding a hash mark (#) in front of the line will indicate that it is a
#     comment.

# Do not make configuration changes to the database while running this script.

DB=${1:-zabbix}
MYSQLLINE="mysql -N $DB"
TMPFILE=/tmp/data_sql_tmp_dump.sql
FINALFILE=final_data.sql
BLACKLISTFILE=data_sql_blacklist.txt
FILTERFILE=data_sql_filter.txt
PREDUMPHOOKFILE=data_sql_predumphooks.txt
>"$FINALFILE"

fail() {
	echo $1
	exit 1
}

[[ -f "$BLACKLISTFILE" ]] || echo "warning - blacklist file $BLACKLISTFILE not found"

#UTILS="mysql strings awk cut sed"

[[ "$2" ]] && {
	TABLES="$2"
} || {
TABLES="actions \
applications \
conditions \
config \
dchecks \
drules \
expressions \
functions \
globalmacro \
graph_theme \
graphs \
graphs_items \
groups \
help_items \
hostmacro \
hosts \
hosts_groups \
hosts_profiles \
hosts_profiles_ext \
hosts_templates \
httpstep \
httpstepitem \
httptest \
httptestitem \
items \
items_applications \
maintenances \
maintenances_groups \
maintenances_hosts \
maintenances_windows \
mappings \
media \
media_type \
opconditions \
operations \
opmediatypes \
profiles \
regexps \
rights \
screens \
screens_items \
scripts \
services \
services_links \
services_times \
slides \
slideshows \
sysmaps \
sysmaps_elements \
sysmaps_link_triggers \
sysmaps_links \
timeperiods \
trigger_depends \
triggers \
users \
users_groups \
usrgrp \
valuemaps"
# nodes \
}

for TABLE in $TABLES; do
	while read line; do
		FIELD=$(echo $line | cut -d" " -f1)
		DEFAULT=$(echo $line | cut -d" " -f2-)
		[[ $(echo "select $FIELD from $TABLE;" | $MYSQLLINE | sed "/^$DEFAULT$/ d") ]] && {
			# at least one entry differs from the default
			DIFFERENT_FIELDS="$DIFFERENT_FIELDS,$FIELD"
		}
	done < <(echo "show columns from $TABLE;" | $MYSQLLINE -t | awk '{FS="|"}; {print $2,$6}' | strings | awk '{print $1,$2}')
	[[ "$DIFFERENT_FIELDS" ]] && {
		# --- blacklist file must have table name, followed by blacklisted fields - all space separated
		BLACKLIST=$(grep -v ^# "$BLACKLISTFILE" 2>/dev/null | grep $TABLE 2>/dev/null)
		[[ "$BLACKLIST" ]] && {
			BLACKFIELDS=$(echo $BLACKLIST | cut -d" " -f2-)
			for BLACKFIELD in $BLACKFIELDS; do
				DIFFERENT_FIELDS=$(echo "$DIFFERENT_FIELDS" | sed "s/,${BLACKFIELD}\($\|,\)/\1/")
			done
		}
		# --- filter file must have table name, followed by field to filter on, then followed by values to match - all space separated
		FILTER=$(grep -v ^# "$FILTERFILE" 2>/dev/null | grep $TABLE 2>/dev/null)
		[[ "$FILTER" ]] && {
			FILTERFIELD=$(echo $FILTER | cut -d" " -f2)
			FILTERVALUES=$(echo $FILTER | cut -d" " -f3-)
			for FILTERVALUE in $FILTERVALUES; do
				FINALFILTER="$FINALFILTER or $FILTERFIELD='$FILTERVALUE'"
			done
			FINALFILTER="where ${FINALFILTER# or }"
		}
		echo "
--
-- Dumping data for table \`$TABLE\`
--
" >> "$FINALFILE"

		# pre-dump hook to update possibly out of order values in current database
		# it's still possible that server would change them right after fixing & before dump,
		#  but that's a smaller chance
		HOOKLINE=$(grep ^$TABLE $PREDUMPHOOKFILE)
		[[ "$HOOKLINE" ]] && {
			HOOK=$(echo "$HOOKLINE" | cut -d" " -f2-)
			echo "$HOOK" | $MYSQLLINE
			unset HOOKLINE
		}

		echo "select ${DIFFERENT_FIELDS#,} from $TABLE $FINALFILTER into outfile '$TMPFILE' \
FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY \"'\";" | $MYSQLLINE || fail "select failed"

		# "into outfile" does not replace newlines with \r\n, thus we just replace them with sed
		sed -i 's/\r/\\r/g' "$TMPFILE"
		while grep '\\$' "$TMPFILE" > /dev/null; do
			sed -i '/\\$/ {N; s/\\\n/\\n/g}' "$TMPFILE"
		done

		# $TMPFILE is created by mysql user - this script thus has to be run either as that user, or root
		sed -i "s/\(.*\)/insert into $TABLE (${DIFFERENT_FIELDS#,}) values (\1);/" "$TMPFILE"
		cat "$TMPFILE" >> "$FINALFILE"
		rm "$TMPFILE" || fail "can't remove $TMPFILE"
	}
	unset DIFFERENT_FIELDS FILTER BLACKLIST FINALFILTER
done
