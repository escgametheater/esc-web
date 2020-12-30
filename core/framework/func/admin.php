<?php
/**
 * Admin function
 *
 */

function add_admin_log($db, $user_id, $action)
{
    FlashMessagesManager::sendFlashMessage($user_id, $action, MSG_SUCCESS);
    $sqli = $db->get_connection(SQLN_SITE);
    $r = $sqli->query_write("
        INSERT INTO ".Table::AdminLog."
        (user_id, action, post_date)
        VALUES (
            ".(int)$user_id.",
            ".$sqli->quote_value($action).",
            NOW()
        )");
    return $r;
}

function admin_log(Request $request, $action)
{
    return add_admin_log($request->db, $request->user->id, $action);
}
