<?php

require "../init.php";

define('LIMIT', 1);

function query1()
{
    $sqli = DB::inst(SQLN_SITE);
    $sqli->prepare_read('
       SELECT articleid,title
       FROM '.SQL_ARTICLE_DATA.'
       LIMIT ?');
    $sqli->bind_param('i', 4);
    $sqli->execute();
    if (!$sqli->have())
        return false;

    $sqli->bind_result($nid, $title);

    $list_articles = [];
    while ($sqli->fetch()) {
        $list_articles[$nid]['data']['title'] = stripslashes($title);
        echo '<div>'.$nid.'-'.$title.'</div>';
    }
    $sqli->stmclose();
}

function query2()
{
    $sqli = DB::inst(SQLN_SITE);

    $r = $sqli->query_read('
        SELECT articleid,title
        FROM '.SQL_ARTICLE_DATA.'
        LIMIT 4');

    if (!$sqli->have())
        return false;

    $list_articles = [];
    while ($a = $sqli->fetch_assoc()) {
        $nid = $a['articleid'];
        $title = $a['title'];
        $list_articles[$nid]['data']['title'] = stripslashes($title);
        echo '<div>'.$nid.'-'.$title.'</div>';
    }
    $sqli->stmclose();
}

$website_root = "/Users/osso/Documents/Negumi/Website Root/negumi v2/";

for ($k = 0; $k < 10; $k++) {
    $start_time = microtime(true);

    for ($i = 0; $i < LIMIT; $i++)
        query1();

    $end_time = microtime(true);

    $t1 = $end_time - $start_time;

    $start_time = microtime(true);

    for ($i = 0; $i < LIMIT; $i++)
        query2();

    $end_time = microtime(true);

    $t2 = $end_time - $start_time;

    print '<br />execution time: '. round($t1 * 1000) . ' ms vs '. round($t2 * 1000) .' ms ';
}
