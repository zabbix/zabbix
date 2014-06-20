#!/bin/bash

# A script to convert SVG into multiple sizes of PNG and compress them with pngcrush

# todo :
# figure out how to auto-scale rack images

# depends on inkscape, pngcrush, awk

pngcrushbin=pngcrush

outputdir="${1:-png_modern}"
sourcedir="svg"
elementdir="$sourcedir/elements"

pngcrushlog=pngcrush.log.txt
pngcrushoutput=pngcrushoutput.txt
inkscapelog=inkscape.log.txt

crushpng() {
	$pngcrushbin -brute -reduce -e .2.png "$1" >> "$pngcrushoutput" || exit 1
	echo "$1 : $(echo "$(stat -c %s "${1%png}2.png")/$(stat -c %s "${1}")*100" | bc -l)" >> "$pngcrushlog"
	mv "${1%png}2.png" "$1"
}

svgtopng() {
	# parameters : svg, size, reported size, dimension
	# 3rd parameter allows to override "reported" size; if missing, actual size is used
	# 4th parameter allows to override width/height detection
	pngoutfile="$outputdir/$(basename ${1%.svg})_(${3:-$2}).png"
	[[ "$(stat -c "%Y" "$pngoutfile" 2>/dev/null)" -lt "$svgfilemod" ]] && {
		# if png file modification time is older than svg file modification time
		echo -n " to $2..."
		# we have to query image dimensions first, because export dimensions are used "as-is", resulting in a square rackmountable server, for example
		# inkscape option --query-all could be used, but it's not fully clear which layer is supposed to be "whole image"
		# crudely dropping decimal part, bash fails on it
		[[ "$4" ]] && {
			dimension=$4
		} || {
			[[ "$(inkscape --without-gui --query-width $1 | cut -d. -f1)" -gt "$(inkscape --without-gui --query-height $1 | cut -d. -f1)" ]] && {
				dimension=width
			} || {
				dimension=height
			}
		}
		inkscape --without-gui --export-$dimension=$2 $1 --export-png="$pngoutfile" >> "$inkscapelog" || exit 1
		echo -n " compress..."
		crushpng "$pngoutfile"
	} || {
		echo -n " skip $2..."
	}
}

mkdir -p "$outputdir"

> "$pngcrushoutput"
> "$pngcrushlog"
> "$inkscapelog"

svgelementcount=$(ls $elementdir | wc -l)

for svgfile in $elementdir/*.svg; do
	echo -n "Converting $svgfile"
	((elementfilesdone++))
	svgfilemod=$(stat -c "%Y" "$svgfile")
	for size in 24 48 64 96 128; do
		# rackmountable device icons don't make much sense below size 64
		[[ "$svgfile" =~ Rackmountable_.* || "$svgfile" =~ Zabbix_server_.* || "$svgfile" =~ UPS_rackmountable.* ]] && [ "$size" -lt "64" ] && continue
		svgtopng "$svgfile" "$size"
	done
	echo "[$[$elementfilesdone*100/$svgelementcount]%]"
done

# rack images for now are converted with a fixed ratio

rackimages=([64]=68 [96]=101 [128]=134)

echo -n "Converting Rack_42.svg"
for rackimagesize in "${!rackimages[@]}"; do
	svgtopng "$sourcedir/equipment_rack/Rack_42.svg" "${rackimages[$rackimagesize]}" "$rackimagesize" "width"
done
echo

rackwithdoorimages=([64]=100 [96]=149 [128]=199)

echo -n "Converting Rack_42_with_door.svg"
for rackwithdoorimagesize in "${!rackwithdoorimages[@]}"; do
	svgtopng "$sourcedir/equipment_rack/Rack_42_with_door.svg" "${rackwithdoorimages[$rackwithdoorimagesize]}" "$rackwithdoorimagesize" "width"
done
echo

[[ -s "$pngcrushlog" ]] && {
	echo "Biggest gain from pngcrush:"
	sort -n -r -t : -k 2 "$pngcrushlog" | tail -n 1
	awk 'BEGIN {FS=":"}; {sum+=$2} END { print "Average gain:",sum/NR}' "$pngcrushlog"
}
