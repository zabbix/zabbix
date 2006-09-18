for db in mysql postgresql oracle; do
	echo Generating $db schema
	fmt=`cat $db.fmt`
	cp schema.sql schema.tmp

	for i in $fmt; do
		macro=`echo "$i"|cut -f1 -d"="`
		replace=`echo "$i"|cut -f2 -d">"`
	
		sed "s/$macro/$replace/g" schema.tmp >schema.tmp2
		mv schema.tmp2 schema.tmp
	done

	mv schema.tmp $db.sql
done
echo Done
