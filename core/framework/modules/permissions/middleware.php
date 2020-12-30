<?php
/**
 * Permissions Middleware
 *
 * @package permissions
 */
class PermissionsMiddleware extends Middleware
{
    /**
     * Authentification information
     */
    public function process_request(Request $request)
    {
        if (!$request->user->permissions instanceof Permissions)
            $request->user->permissions = new Permissions($request->user, $request);
    }
}
