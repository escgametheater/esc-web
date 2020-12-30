engine = 'InnoDB'
host = 'dev'
db = 'mh_forum'
user = 'root'
passwd = '8IqgzEYM'
skip_tables = ('vb_post')

import MySQLdb

db = MySQLdb.connect(user=user, passwd=passwd, db=db, host=host)

c = db.cursor()
c.execute("show tables")

row = c.fetchone()
while row:
    table = row[0]
    print 'Converting Table: %s' % table
    if table in skip_tables:
        print 'Skipping'
        row = c.fetchone()
        continue
    e = db.cursor()
    e.execute('ALTER TABLE `%s` ENGINE = %s' % (MySQLdb.escape_string(table), engine))
    row = c.fetchone()
    print 'Done'
c.close()
