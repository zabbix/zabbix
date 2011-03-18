#!/bin/bash

# A script to convert SVG into multiple sizes of PNG, compress them with pngcrush and create SQL to import in the database
# MySQL only for now

# todo :
# add support for other databases;
# integrate in make dist;
# report some pngcrush stats
# progress reporting
# figure out how to auto-scale rack images

outputdir=output_png
pngcrushlog=pngcrush.log.txt
pngcrushbin=pngcrush
elementdir=elements
pngcrushoutput=pngcrushoutput.txt
sqlfile=images_mysql.sql

mkdir -p "$outputdir"

svgelementcount=$(ls $elementdir | wc -l)

for svgfile in $elementdir/*.svg; do
	echo -n "Converting $svgfile"
	((elementfilesdone++))
	for size in 24 48 64 96 128; do
		pngoutfile="$outputdir/$(basename ${svgfile%.svg}) ($size).png"
		[[ "$(stat -c "%Y" "$pngoutfile" 2>/dev/null)" -lt "$(stat -c "%Y" "$svgfile")" ]] && {
			# if png file modification time is older than svg file modification time
			echo -n " to $size..."
			# we have to query image dimensions first, because export dimensions are used "as-is", resulting in a aquare rackmountable server, for example
			# inkscape option --query-all could be used, but it's not fully clear which layer is supposed to be "whole image"
			# crudely dropping decimal part, bash fails on it
			[[ "$(inkscape --without-gui --query-width $svgfile | cut -d. -f1)" -gt "$(inkscape --without-gui --query-height $svgfile | cut -d. -f1)" ]] && {
				dimension=width
			} || {
				dimension=height
			}
			inkscape --without-gui --export-$dimension=$size $svgfile --export-png="$pngoutfile" >> inkscape.log.txt|| exit 1
			echo -n " compress..."
			$pngcrushbin -brute -reduce -e .2.png "$pngoutfile" >> $pngcrushoutput || exit 1
			echo "$pngoutfile : $(echo "$(stat -c %s "${pngoutfile%png}2.png")/$(stat -c %s "${pngoutfile}")*100" | bc -l)" >> $pngcrushlog
			mv "${pngoutfile%png}2.png" "$pngoutfile"
		} || {
			echo -n " skip $size..."
		}
	done
	echo "[$[$elementfilesdone*100/$svgelementcount]%]"
done

echo "Generating SQL file"

for imagefile in $outputdir/*.png; do
	((imagecount++))
	echo "INSERT INTO images (imageid,imagetype,name,image) VALUES ($imagecount,1,'${imagefile%.png}','$(hexdump -ve '"" 1/1 "%02X"' "$imagefile")';" >> $sqlfile
done
