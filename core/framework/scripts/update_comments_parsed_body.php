<?php

echo "News Comments parsed_body Updater\n";
echo "Starting\n";

// Change current work directory to project root
chdir('..');

// Does initialisation
require "init.php";

$sqli = DB::inst(SQLN_SITE);

$r = $sqli->query_read('
    SELECT id, body
    FROM '.SQL_NEWS_COMMENTS.'
    ORDER BY id ASC
');

while($row = $r->fetch_assoc()) {
    echo '<p>Updating '.$row['id'].'</p>'."\n";

    $body = cleanup_bbcode($row['body']);
    $parsed_body = parse_bb($body);

    $b = $sqli->query_write('
        UPDATE '.SQL_NEWS_COMMENTS.'
        SET
            parsed_body = "'.$sqli->escape_string($parsed_body).'"
        WHERE id = '.$row['id'].'
    ');
}

echo "Done\n";
