#!/bin/bash

# A script to generate SQL from PNG images
# depends on hexdump

scriptdir="$(dirname $0)"
pngdir="${1:-png_modern}"
sqlbasedir="$scriptdir/../../database"
imagefile="images.sql"

imagefile_mysql="$sqlbasedir/mysql/$imagefile"
imagefile_pgsql="$sqlbasedir/postgresql/$imagefile"
imagefile_sqlite3="$sqlbasedir/sqlite3/$imagefile"
imagefile_oracle="$sqlbasedir/oracle/$imagefile"
imagefile_ibm_db2="$sqlbasedir/ibm_db2/$imagefile"

for imagefile in "$imagefile_mysql" "$imagefile_pgsql" "$imagefile_sqlite3" "$imagefile_oracle" "$imagefile_ibm_db2"; do
	[[ -s "$imagefile" ]] && {
		echo "Non-empty $imagefile already exists, stopping"
		exit 1
	}
done

echo "Generating SQL files"

cat images_oracle_start.txt > "$imagefile_oracle"

imagecount=$(ls $pngdir/*.png | wc -l)

# TODO: this loop won't work with directory names, containing spaces
# using 'find' here seems to be a bit excessive for now
for imagefile in $pngdir/*.png; do
	((imagesdone++))
	imagename="$(basename "${imagefile%.png}")"
	# ----- MySQL
	echo "INSERT INTO images (imageid,imagetype,name,image) VALUES ($imagesdone,1,'$imagename',0x$(hexdump -ve '"" 1/1 "%02X"' "$imagefile"));" >> "$imagefile_mysql"
	# ----- PostgreSQL
	echo "INSERT INTO images (imageid,imagetype,name,image) VALUES ($imagesdone,1,'$imagename',decode('$(hexdump -ve '"" 1/1 "%02X"' "$imagefile")','hex'));" >> "$imagefile_pgsql"
	# ----- Oracle
	echo -e "\tLOAD_IMAGE($imagesdone,1,'$imagename','$imagefile');" >> "$imagefile_oracle"
	# ----- SQLite
	echo "INSERT INTO images (imageid,imagetype,name,image) VALUES ($imagesdone,1,'$imagename','$(hexdump -ve '"" 1/1 "%02X"' "$imagefile")');" >> "$imagefile_sqlite3"
	# ----- IBM DB2
	echo "INSERT INTO images (imageid,imagetype,name,image) VALUES ($imagesdone,1,'$imagename',blob(x'$(hexdump -ve '"" 1/1 "%02X"' "$imagefile")'));" >> "$imagefile_ibm_db2"

	echo -n "$[$imagesdone*100/$imagecount]% "
done
cat images_oracle_end.txt >> "$imagefile_oracle"
echo
