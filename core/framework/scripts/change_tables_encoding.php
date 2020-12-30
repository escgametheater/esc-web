<?php
/**
 * Changes the encoding (collation) for all the tables and all the fields
 *
 * @version 1
 * @package scripts
 */

require_once "../../init.php";

$sqli = DB::inst(SQLN_SITE);
$db = 'negumiv2';

$result = $sqli->query_write('SHOW TABLES');
if (!$result)
    echo '<span style="color: red;">Get SHOW TABLE - SQL Error: <br />' . "</span>\n";

while ($tables = $result->fetch_assoc()) {
    # Loop through all tables in this database
    $table = $tables["Tables_in_$db"];

    $result2 = $sqli->query_write("ALTER TABLE $table COLLATE utf8_general_ci");
    if (!$result2) {
        echo '<span style="color: red;">UTF SET - SQL Error: <br />'."</span>\n";
        break;
    }

    echo "$table changed to UTF-8 successfully.<br />\n";

    # Now loop through all the fields within this table
    $result2 = $sqli->query_write("SHOW COLUMNS FROM ".$table);
    if (!$result2) {
        echo '<span style="color: red;">Get Table Columns query_write - SQL Error: <br />' . "</span>\n";
        break;
    }

    while ($column = $result2->fetch_assoc()) {
        $field_name = $column['Field'];
        $field_type = $column['Type'];

        echo "Converting $field_name<br />\n";

        # Change text based fields
        $field_types = ['char', 'text', 'enum', 'set'];

        foreach ($field_types as $type) {
            if (strpos($field_type, $type) !== false) {
                $sqli->query_write("
                    ALTER TABLE $table
                    CHANGE `$field_name` `$field_name` $field_type
                    CHARACTER SET utf8 COLLATE utf8_bin");
                echo "---- $field_name changed to UTF-8 successfully.<br />\n";
            }
        }
    }
    echo "<hr />\n";
}
