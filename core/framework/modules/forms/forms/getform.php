<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 9/20/16
 * Time: 3:03 AM
 */

class GetForm extends Form
{
    public function __construct($fields, Request $request, $data = null, $files = null, $user_id = null)
    {
        if ($request->get->hasParams())
            $data = $request->get->params();

        parent::__construct($fields, $$request->translations, $data, $files, $user_id);
    }
}