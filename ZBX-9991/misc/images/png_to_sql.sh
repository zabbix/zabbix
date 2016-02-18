#!/bin/bash

# A script to generate SQL from PNG images
# depends on hexdump

DB2_LENGTH_LIMIT=32000

# Converts hexadecimal string to DB2 BLOB format.
#
# This function splits the string into 32000 byte chunks because
# of DB2 limitation "For a hexadecimal constant (X, GX, or UX),
# the number of hexadecimal digits must not exceed 32704"
# Though it still did not work with 32704 length limit, so it was
# lowered to 32000.
db2_blob()
{
	image_data=$1
	if [ ${#image_data} -le $DB2_LENGTH_LIMIT ]; then
		echo "blob(x'$image_data')"
		return;
	fi
	image_chunk=${image_data:0:$DB2_LENGTH_LIMIT}
	image_data=${image_data:$DB2_LENGTH_LIMIT}
	echo "$(db2_blob $image_chunk) || $(db2_blob $image_data)"
}


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
	image_data=$(hexdump -ve '"" 1/1 "%02X"' "$imagefile")
	# ----- MySQL
	echo "INSERT INTO images (imageid,imagetype,name,image) VALUES ($imagesdone,1,'$imagename',0x$image_data);" >> "$imagefile_mysql"
	# ----- PostgreSQL
	echo "INSERT INTO images (imageid,imagetype,name,image) VALUES ($imagesdone,1,'$imagename',decode('$image_data','hex'));" >> "$imagefile_pgsql"
	# ----- Oracle
	echo -e "\tLOAD_IMAGE($imagesdone,1,'$imagename','$imagefile');" >> "$imagefile_oracle"
	# ----- SQLite
	echo "INSERT INTO images (imageid,imagetype,name,image) VALUES ($imagesdone,1,'$imagename','$image_data');" >> "$imagefile_sqlite3"
	# ----- IBM DB2
	echo "INSERT INTO images (imageid,imagetype,name,image) VALUES ($imagesdone,1,'$imagename',$(db2_blob $image_data));" >> "$imagefile_ibm_db2"

	echo -ne "\b\b\b\b$[$imagesdone*100/$imagecount]% "
done
cat images_oracle_end.txt >> "$imagefile_oracle"
echo
