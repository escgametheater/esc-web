<?php
/**
 * DB module middleware
 *
 * @package db
 */
class DBMiddleware extends Middleware
{
    public function process_request(Request $request)
    {
        $request->db = $default_db = new DB($request->config);

        $request->managers = new ManagerLocator($request);

        // -- CC Commented out to ensure correct main DB for now.
        // $request->db = $default_db = DB::inst(SQLN_SITE);

        if ($request->managers)
            $request->managers->setDefaultDB($default_db);
    }
}
