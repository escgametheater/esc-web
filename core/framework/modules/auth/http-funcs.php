<?php
/**
 * Auth funcs
 *
 * @package auth
 */

/**
 * Rights helper function
 * Checks rights and throws HttpDenied
 *
 */
function check_permission(Request $request, $right, $access_level = Rights::MODERATE)
{
    if (!$request->user->permissions->has($right, $access_level))
        throw new HttpDenied($right.'('.$access_level.') required');
}

/**
 * Throws an error if the user is not logged in
 */
function check_login(Request $request)
{
    if (!$request->user->is_authenticated)
        throw new HttpDenied('You need to be logged in to view this');
}


