#mysqldump -uroot trunk --extended-insert=FALSE --complete-insert=TRUE --quote-names=FALSE
DBNAME=trunk
echo "truncate table alerts"|mysql -uroot $DBNAME
echo "truncate table auditlog"|mysql -uroot $DBNAME
echo "truncate table events"|mysql -uroot $DBNAME
echo "truncate table history"|mysql -uroot $DBNAME
echo "truncate table history_uint"|mysql -uroot $DBNAME
echo "truncate table history_log"|mysql -uroot $DBNAME
echo "truncate table history_text"|mysql -uroot $DBNAME
echo "truncate table profiles"|mysql -uroot $DBNAME
echo "truncate table trends"|mysql -uroot $DBNAME
echo "truncate table dhosts"|mysql -uroot $DBNAME
echo "truncate table dservices"|mysql -uroot $DBNAME
mysqldump -uroot $DBNAME --quote-names=FALSE
