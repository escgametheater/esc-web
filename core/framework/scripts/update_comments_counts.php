<?php

$sqli = DB::inst(SQLN_SITE);

$r = $sqli->query_read('
    SELECT object_id as news_id, COUNT(1) as comments_count
    FROM '.SQL_NEWS_COMMENTS.'
    GROUP BY object_id
');

while ($row = $r->fetch_assoc()) {
    echo 'Updating: '.safe_get($row, 'news_id').' with '.safe_get($row, 'comments_count').'<br />';
    $sqli->query_write('
        UPDATE news SET comments_count = '.$row['comments_count'].'
        WHERE id = '.$row['news_id'].'
    ');
}

$r->close();

echo 'End';
