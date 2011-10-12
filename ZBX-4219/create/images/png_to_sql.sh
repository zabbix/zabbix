#!/bin/bash

# A script to generate SQL from PNG images

# todo :
# add support for other databases;
# integrate in make dist;
# generate an sql of old images as well
# generate importable xml out of a bunch of png images

# depends on hexdump

outputdir=output_png

imagefile_mysql=images_mysql.sql
imagefile_pgsql=images_postgresql.sql
imagefile_sqlite3=images_sqlite3.sql
imagefile_oracle=images_oracle.sql
imagefile_db2=images_ibm_db2.sql

for imagefile in $imagefile_mysql $imagefile_pgsql $imagefile_sqlite3 $imagefile_oracle $imagefile_db2; do
	[[ -s $imagefile ]] && {
		echo "Non-empty $imagefile already exists, stopping"
		exit 1
	}
done

echo "Generating SQL files"

imagecount=$(ls $outputdir/*.png | wc -l)
for imagefile in $outputdir/*.png; do
	((imagesdone++))
	imagename=$(basename ${imagefile%.png})
	# ----- MySQL
	echo "INSERT INTO images (imageid,imagetype,name,image) VALUES ($imagecount,1,'$imagename',0x$(hexdump -ve '"" 1/1 "%02X"' "$imagefile"));" >> $imagefile_mysql
	# ----- PostgreSQL
	echo "INSERT INTO images (imageid,imagetype,name,image) VALUES ($imagecount,1,'$imagename',decode('$(hexdump -ve '"" 1/1 "%02X"' "$imagefile")','hex'));" >> $imagefile_pgsql
	# ----- SQLite
	echo "INSERT INTO images (imageid,imagetype,name,image) VALUES ($imagecount,1,'$imagename','$(hexdump -ve '"" 1/1 "%02X"' "$imagefile")');" >> $imagefile_sqlite3
	# ----- DB2
	echo "INSERT INTO images (imageid,imagetype,name,image) VALUES ($imagecount,1,'$imagename',blob(x'$(hexdump -ve '"" 1/1 "%02X"' "$imagefile")'));" >> $imagefile_db2

	echo -n "$[$imagesdone*100/$imagecount]% "
done
echo
