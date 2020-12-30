<?php
/**
 * FormField class
 *
 */

abstract class BaseFormField extends BaseDataFields
{
    // Form Specific Field Names
    const STRIPE_TOKEN = 'stripeToken';
    const CHECK = 'check';
    const FILESIZE = 'filesize';
    const HAS_READONLINE = 'has_readonline';
    const VISIBLE = 'visible';
    const ARTIST_NAME = 'artist_name';
    const EMAIL = 'email';
    const USERNAME = 'username';
    const EDIT_BODY = 'edit_body';
    const PARSED_DESCRIPTION = 'parsed_description';
    const YEAR = 'year';
    const MONTH = 'month';
    const DAY = 'day';
    const TAGS = 'tags';
    const PICTURE = 'picture';
    const BANNER = 'banner';
    const COMIC_TYPE_ID = 'comic_type_id';
    const THREAD = 't';
    const TO = 'to';
    const CUSTOM_URL = 'custom_url';
    const FILE = 'file';
    const ICON = 'icon';
    const COVER = 'cover';
    const PASSWORD = 'password';
    const NEXT = 'next';
    const HASH = 'hash';
    const PASSWORD_HASH = 'password_hash';
    const REMEMBER_ME = 'remember_me';

    const UPDATE_ALL_PAGES = 'update_all_pages';

    const ALERT_ON = 'alert_on';

    const F = 'f';

    // Derived File Upload Fields
    const UPLOAD_ID_PICTURE = 'upload_id_picture';
    const UPLOAD_ID_BANNER = 'upload_id_banner';
    const UPLOAD_ID_FILE = 'upload_id_file';
    const UPLOAD_ID_COVER = 'upload_id_cover';
    const UPLOAD_ID_ICON = 'upload_id_icon';

}

abstract class FormField extends BaseFormField
{
    public $name;
    public $verbose_name;
    public $is_required;
    public $help_text;
    public $placeholder;
    public $is_valid = false;
    public $error_msg;
    public $template_file;
    public $is_readonly = false;
    public $fieldGroup = null;

    public function __construct($name, $verbose_name, $is_required = true, $template_file = null, $is_readonly = false)
    {
        $this->name = $name;
        $this->verbose_name = $verbose_name;
        $this->is_required = $is_required;
        $this->label_style = '';
        $this->style = '';
        $this->template_file = $template_file;
        $this->is_readonly = $is_readonly;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return get_class($this);
    }

    /**
     * @param $fieldGroup
     */
    public function setFieldGroup($fieldGroup)
    {
        $this->fieldGroup = $fieldGroup;
    }

    /**
     * @param $field_prefix
     * @param $field_id
     * @return string
     */
    public static function createDynamicFieldName($field_prefix, $field_id)
    {
        $field_prefix = str_replace('_', '-', $field_prefix);
        return $field_prefix.'-'.$field_id;
    }


    /**
     * Output html representing the field (internal use)
     *
     * @param Form form class
     */
    abstract protected function _render($form, $class_name = null);

    /**
     * Output html representing the field
     *
     * @param Form form class
     */
    public function render($form, $class_name = null)
    {
        $error_html = ($this->is_valid || $this->error_msg == '') ? '': "<div class=\"formerror\">".$this->error_msg."</div>";
        return $this->_render($form, $class_name).$error_html;
    }

    /**
     * Checks if the form data is valid (internal use)
     *
     * @param Form form class
     */
    abstract protected function _is_valid(Form $form);

    /**
     * Checks if the form data is valid
     *
     * @param Form form class
     */
    public function is_valid(Form $form)
    {
        $data = $form->data;

        if (!$this->is_valid) {

            if ($this->is_required && !isset($data[$this->name][0])) {

                if (!array_key_exists($this->name, $data) || !is_numeric($data[$this->name])) {
                    $this->is_valid = false;
                    $this->error_msg = "{$this->verbose_name} {$form->translations["is required"]}!";
                } else {
                    if (array_key_exists($this->name, $data) && ($data[$this->name] === "" || $data[$this->name] === null)) {
                        $this->is_valid = false;
                        $this->error_msg = "{$this->verbose_name} {$form->translations["is required"]}!";
                    } else {
                        $this->is_valid = $this->_is_valid($form);
                    }
                }
                //$form->set_error("Field {$this->name} is required", $this->name);
            } elseif (!$this->is_required && !isset($data[$this->name][0])) {
                $this->is_valid = $this->is_valid = $this->_is_valid($form);
            } else {
                $this->is_valid = $this->_is_valid($form);
            }
        }

        return $this->is_valid;
    }

    /**
     * $name accessor
     */
    public function get_name()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getVerboseName()
    {
        return $this->verbose_name;
    }

    /**
     * Returns cleaned data
     *
     * @param Form form class
     */
    public function get_cleaned_value($form)
    {
        return array_get($form->data, $this->name, null);
    }

    /**
     * Set a custom field error
     *
     * @param Form form class
     */
    public function set_error($msg)
    {
        $this->is_valid = false;
        $this->error_msg = $msg;
    }

    /**
     * @return string
     */
    public function getErrorMsg()
    {
        return $this->error_msg;
    }

    /**
     * @param $option
     * @param $value
     * @return bool
     */
    protected function _is_valid_option_value($option, $value)
    {
        if ($option instanceof DBManagerEntity && isset($option[$option->getPkField()]))
            return $option->getPk() == $value;
        elseif (isset($option[$this->name]))
            return $option[$this->name] == $value;
        elseif (isset($option['id']))
            return $option['id'] == $value;
        else
            return $option[0] == $value;
    }
}
