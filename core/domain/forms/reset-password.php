<?php

class ResetPasswordForm extends PostForm
{
    protected $db;

    public function __construct($fields, Request $request, $data = NULL)
    {
        $this->db = $request->db;
        parent::__construct($fields, $request, $data);
    }

    /**
     * @return bool
     */
    public function is_valid()
    {
        $is_valid = parent::is_valid();

        if ($is_valid) { // don't trigger the error if it's not a post
            $email_check = UsersManager::objects($this->db)
                ->filter(Q::Eq(DBField::EMAIL_ADDRESS, $this->getCleanedValue(DBField::EMAIL_ADDRESS)))
                ->exists();

            if (!$email_check) {
                $is_valid = false;
                $this->set_error($this->translations->lookup(T::ACCOUNT_AUTH_RESET_PASSWORD_INVALID_EMAIL), DBField::EMAIL_ADDRESS);
            }
        }

        return $is_valid;
    }
}
