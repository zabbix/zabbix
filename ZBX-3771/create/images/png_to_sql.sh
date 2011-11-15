#!/bin/bash

# A script to generate SQL from PNG images
# depends on hexdump

outputdir=${1:-png}

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

cat images_oracle_start.txt > $imagefile_oracle

imagecount=$(ls $outputdir/*.png | wc -l)

for imagefile in $outputdir/*.png; do
	((imagesdone++))
	imagename=$(basename ${imagefile%.png})
	# ----- MySQL
	echo "INSERT INTO images (imageid,imagetype,name,image) VALUES ($imagesdone,1,'$imagename',0x$(hexdump -ve '"" 1/1 "%02X"' "$imagefile"));" >> $imagefile_mysql
	# ----- PostgreSQL
	echo "INSERT INTO images (imageid,imagetype,name,image) VALUES ($imagesdone,1,'$imagename',decode('$(hexdump -ve '"" 1/1 "%02X"' "$imagefile")','hex'));" >> $imagefile_pgsql
	# ----- Oracle
	echo -e "\tLOAD_IMAGE($imagesdone,1,'$imagename','$imagefile');" >> $imagefile_oracle
	# ----- SQLite
	echo "INSERT INTO images (imageid,imagetype,name,image) VALUES ($imagesdone,1,'$imagename','$(hexdump -ve '"" 1/1 "%02X"' "$imagefile")');" >> $imagefile_sqlite3
	# ----- DB2
	echo "INSERT INTO images (imageid,imagetype,name,image) VALUES ($imagesdone,1,'$imagename',blob(x'$(hexdump -ve '"" 1/1 "%02X"' "$imagefile")'));" >> $imagefile_db2

	echo -n "$[$imagesdone*100/$imagecount]% "
done
cat images_oracle_end.txt >> $imagefile_oracle
echo
