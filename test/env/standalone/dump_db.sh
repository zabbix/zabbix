#mysqldump -uroot trunk --extended-insert=FALSE --complete-insert=TRUE --quote-names=FALSE
DBNAME=trunk
echo "truncate table events"|mysql -uroot $DBNAME
mysqldump -uroot $DBNAME --quote-names=FALSE
