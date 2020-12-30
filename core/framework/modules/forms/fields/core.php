<?php
/**
 * Form Fields
 *
 */

use \libphonenumber\PhoneNumberUtil;

class BooleanField extends FormField
{
    public function __construct($name, $verbose_name, $is_required = false, $help_text = '', $template_file = '', $isReadOnly = false)
    {
        parent::__construct($name, $verbose_name, $is_required, $template_file, $isReadOnly);
        $this->verbose_name = $verbose_name;
        $this->help_text  = $help_text;
    }

    public function get_cleaned_value($form)
    {
        $value = array_get($form->data, $this->name, '');

        return  $value == 'on' || $value == 1;
    }

    protected function _is_valid(Form $form)
    {
        $value = array_get($form->data, $this->name, '');
        return $value == "1" || $value == 'on' || $value == '';
    }

    protected function _render($form, $class_names = null)
    {
        $t = new Template();
        $t->assign([
            'data' => $form->data,
            'field' => $this,
            'class_names' => $class_names
        ]);
        if (empty($this->template_file))
            $this->template_file = 'forms/boolean_field.twig';

        return $t->render_template($this->template_file);
    }
}
class JSONBooleanField extends FormField
{
    public function __construct($name, $verbose_name, $is_required = false, $help_text = '', $template_file = '')
    {
        parent::__construct($name, $verbose_name, $is_required);
        $this->verbose_name = $verbose_name;
        $this->help_text  = $help_text;
        $this->template_file = $template_file;
    }

    public function get_cleaned_value($form)
    {
        return array_get($form->data, $this->name, false);
    }

    protected function _is_valid(Form $form)
    {
        $value = array_get($form->data, $this->name, null);
        if (!is_bool($value) && $this->is_required) {
            $this->error_msg = "{$this->name} is required boolean value";
            return false;
        } else {
            return true;
        }
    }

    protected function _render($form, $class_names = null)
    {
        $t = new Template();
        $t->assign([
            'data' => $form->data,
            'field' => $this,
            'class_names' => $class_names
        ]);
        if (empty($this->template_file))
            $this->template_file = 'forms/boolean_field.twig';

        return $t->render_template($this->template_file);
    }
}

class CharField extends FormField
{
    protected $max_length;
    protected $width;
    public $type = 'text';
    public $default_placeholder = "Write something...";

    public function __construct($name, $verbose_name, $max_length = 0, $is_required = true, $help_text = '', $placeholder = null, $template_file = null, $isReadOnly = false)
    {
        parent::__construct($name, $verbose_name, $is_required, $template_file, $isReadOnly);
        $this->max_length = $max_length;
        $this->help_text  = $help_text;
        $this->placeholder = $placeholder;
    }

    protected function _is_valid(Form $form)
    {
        $is_valid = true;
        if ($this->is_required && !isset($form->data[$this->name])) {
            $is_valid = false;
            $this->error_msg = 'This field is required';
        }
        if ($this->max_length > 0
                && strlen($form->data[$this->name]) > $this->max_length) {
            $is_valid = false;
            $this->error_msg = 'Max length exceeded';
        }
        return $this->is_valid = $is_valid;
    }

    protected function _render($form, $class_names = null)
    {
        $t = new Template();
        $t->assign([
            'data' => $form->data,
            'field' => $this,
            'class_names' => $class_names
        ]);
        if (!$this->template_file)
            $this->template_file = 'forms/char_field.twig';
        return $t->render_template($this->template_file);
    }
}

class HexaDecimalColorField extends CharField
{

    public $type = 'color';

    /**
     * HexaDecimalColorField constructor.
     * @param $name
     * @param $verbose_name
     * @param bool $is_required
     * @param string $help_text
     * @param null $placeholder
     * @param null $template_file
     */
    public function __construct($name, $verbose_name, $is_required = true, $help_text = '', $placeholder = null, $template_file = null)
    {
        parent::__construct($name, $verbose_name, 7, $is_required, $help_text, $placeholder, $template_file);
    }

    /**
     * @param PostForm $form
     * @return bool
     */
    protected function _is_valid(Form $form)
    {
        $isValid = parent::_is_valid($form);

        $value = $form->data[$this->name];

        if ($isValid) {
            if (!preg_match('/#([a-fA-F0-9]{3}){1,2}\b/', $value)) {
                $this->error_msg = 'This is not a valid hexadecimal color (Ex: #111 or #111111)';
                return false;
            }
        }

        return $isValid;
    }
}

class ESCCustomAssetField extends CharField
{
    /** @var GameModActiveCustomAssetEntity */
    public $customAsset;

    public $properties = [];

    /**
     * ESCCustomAssetField constructor.
     * @param GameModActiveCustomAssetEntity $gameModActiveCustomAsset
     * @param $name
     * @param $verbose_name
     * @param bool $is_required
     * @param string $help_text
     * @param null $placeholder
     * @param null $template_file
     */
    public function __construct(GameModActiveCustomAssetEntity $gameModActiveCustomAsset, $properties = [], $name, $verbose_name, $is_required = true, $help_text = '', $placeholder = null, $template_file = "forms/custom-fields/game_mod_custom_asset_field.twig")
    {
        parent::__construct($name, $verbose_name, 0, $is_required, $help_text, $placeholder, $template_file);

        $this->customAsset = $gameModActiveCustomAsset;
        $this->properties = $properties;

        /** @var CustomGameModBuildAssetEntity $gameAsset */
        if ($gameAsset = $gameModActiveCustomAsset->field(VField::GAME_ASSET)) {

            $this->properties[VField::URL] = $gameAsset->getUrl();
            $this->properties['file_meta'] = [
                DBField::CREATE_TIME => $gameAsset->getCreateTime(),
                DBField::FILENAME => $gameAsset->getFileName(),
                DBField::FILE_SIZE => $gameAsset->getFileSize(),
                DBField::MD5 => $gameAsset->getMd5(),

            ];
        } else {
            $this->properties[VField::URL] = null;
            $this->properties['file_meta'] = [];
        }
    }
}

class ESCCustomImageAssetField extends ESCCustomAssetField
{
    public function __construct(GameModActiveCustomAssetEntity $gameModActiveCustomAsset, $properties = [], $name, $verbose_name, bool $is_required = true, $help_text = '', $placeholder = null, $template_file = "forms/custom-fields/game_mod_custom_image_asset_field.twig")
    {
        parent::__construct($gameModActiveCustomAsset, $properties, $name, $verbose_name, $is_required, $help_text, $placeholder, $template_file);
    }
}

class DateTimeField extends CharField
{
    public $type = 'datetime-local';

    /**
     * DateTimeField constructor.
     * @param $name
     * @param $verbose_name
     * @param bool $is_required
     * @param string $help_text
     * @param null $placeholder
     * @param string $type
     * @param null $template_file
     */
    public function __construct($name, $verbose_name, $is_required = true, $help_text = '', $placeholder = null, $type = 'text', $template_file = null)
    {
        $this->type = $type;

        parent::__construct($name, $verbose_name, 0, $is_required, $help_text, $placeholder, $template_file);
    }

    /**
     * @param $form
     * @return bool
     */
    protected function _is_valid(Form $form)
    {
        $is_valid = parent::_is_valid($form);

        if ($is_valid) {
            $value = $form->data[$this->name];

            if (!$this->is_required && !$value) {

            } else {
                $is_valid = is_valid_date_time($value);
                if (!$is_valid) {
                    $this->error_msg = "DateTime is invalid, expecting format: YYYY-MM-DD HH:MM:SS";
                    return false;
                }

            }
        }

        return $is_valid;
    }

}

class DateField extends CharField
{
    public $type = 'date';

    /**
     * DateTimeField constructor.
     * @param $name
     * @param $verbose_name
     * @param bool $is_required
     * @param string $help_text
     * @param null $placeholder
     * @param string $type
     * @param null $template_file
     */
    public function __construct($name, $verbose_name, $is_required = true, $help_text = '', $placeholder = null, $type = 'date', $template_file = null)
    {
        $this->type = $type;

        parent::__construct($name, $verbose_name, 0, $is_required, $help_text, $placeholder, $template_file);
    }

    /**
     * @param $form
     * @return bool
     */
    protected function _is_valid(Form $form)
    {
        $is_valid = parent::_is_valid($form);

        if ($is_valid) {
            $value = $form->data[$this->name];

            if (!$this->is_required && !$value) {

            } else {
                $is_valid = is_valid_date($value);
                if (!$is_valid) {
                    $this->error_msg = "Date is invalid, expecting format: MM/DD/YYYY";
                    return false;
                }

            }
        }

        return $is_valid;
    }

}

class TimeField extends CharField
{
    public $type = 'time';

    /**
     * DateTimeField constructor.
     * @param $name
     * @param $verbose_name
     * @param bool $is_required
     * @param string $help_text
     * @param null $placeholder
     * @param string $type
     * @param null $template_file
     */
    public function __construct($name, $verbose_name, $is_required = true, $help_text = '', $placeholder = null, $type = 'time', $template_file = null)
    {
        $this->type = $type;

        parent::__construct($name, $verbose_name, 0, $is_required, $help_text, $placeholder, $template_file);
    }

    /**
     * @param $form
     * @return bool
     */
    protected function _is_valid(Form $form)
    {
        $is_valid = parent::_is_valid($form);

        if ($is_valid) {
            $value = $form->data[$this->name];

            if (!$this->is_required && !$value) {

            } else {
                $is_valid = is_valid_time($value, "H:i");
                if (!$is_valid) {
                    $this->error_msg = "Time is invalid, expecting format: HH:MM";
                    return false;
                }

            }
        }

        return $is_valid;
    }

}


class CoinAwardsFormField extends FormField
{
    /** @var CoinAwardEntity[] */
    protected $awards = [];

    protected $delimiter;

    protected $fieldCount = 4;

    /**
     * CoinAwardsFormField constructor.
     * @param $name
     * @param null $verbose_name
     * @param bool $is_required
     * @param null $template_file
     */
    public function __construct($name, $verbose_name = null, $is_required = true, $delimiter = ';')
    {
        parent::__construct($name, $verbose_name, $is_required, null);

        $this->delimiter = $delimiter;
    }

    protected function _render($form, $class_names = null)
    {
        $t = new Template();
        $t->assign([
            'data' => $form->data,
            'field' => $this,
            'class_names' => $class_names
        ]);
        if (!$this->template_file)
            $this->template_file = 'forms/char_field.twig';
        return $t->render_template($this->template_file);
    }

    /**
     * @param Form $form
     * @return bool
     */
    public function is_valid(Form $form)
    {
        return $this->_is_valid($form);
    }

    /**
     * @param Form $form
     * @return bool
     */
    public function _is_valid(Form $form)
    {
        $isValid = true;

        if ($isValid) {

            $values = array_key_exists($this->name, $form->data) ? $form->data[$this->name] : null;

            if (is_array($values)) {

                foreach ($values as $value) {

                    if (!is_string($value)) {
                        $isValid = false;
                        $this->error_msg = 'Array value incorrect type (not string)';
                        break;
                    }

                    $awardContext = explode($this->delimiter, $value);

                    if (count($awardContext) == $this->fieldCount) {

                        $sessionHash = $awardContext[0];
                        $pointValue = $awardContext[1];
                        $name = (string) trim($awardContext[2]);
                        $girpId = $awardContext[3];

                        if (!is_string($sessionHash)) {
                            $isValid = false;
                            $this->error_msg = "Award string session hash incorrect type for \"{$value}\"";
                            break;
                        }
                        if (strlen($sessionHash) !== 40) {
                            $isValid = false;
                            $this->error_msg = "Session hash string malformed for \"{$value}\"";
                            break;
                        }

                        if (!Validators::int($pointValue) || !Validators::float($pointValue)) {
                            $isValid = false;
                            $this->error_msg = "Incorrect point value type for \"{$value}\"";
                            break;
                        }

                        if (!is_string($name) || empty($name) && $name !== "0") {
                            $isValid = false;
                            $this->error_msg = "Name requires a value of type string with at least one character for \"{$name}\".";
                            break;
                        }


                        if ($girpId) {
                            if (!Validators::int($girpId)) {
                                $isValid = false;
                                $this->error_msg = "GirpId needs to be an integer in \"{$value}\"";
                                break;
                            }
                        } else {
                            $girpId = null;
                        }

                        if ($isValid) {
                            $award = [
                                DBField::SESSION_HASH => $sessionHash,
                                DBField::VALUE => $pointValue,
                                DBField::NAME => $name,
                                DBField::GAME_INSTANCE_ROUND_PLAYER_ID => $girpId
                            ];

                            $this->awards[] = new CoinAwardEntity($award);
                        }

                    } else {

                        if (strpos($value, $this->delimiter) === false) {
                            $isValid = false;
                            $this->error_msg = "Delimiter character '{$this->delimiter}' not found in string.";
                            break;
                        }

                        if (count($awardContext) != $this->fieldCount) {
                            $isValid = false;
                            $this->error_msg = 'Award string provided incorrect parameter count ( "'.$value.'" '.count($awardContext).').';
                            break;
                        }
                    }
                }

            } else {
                $isValid = false;
                $this->error_msg = 'Given data is not of type array.';
            }

        }

        $this->is_valid = $isValid;

        return $isValid;
    }

    /**
     * @param $form
     * @return CoinAwardEntity[]
     */
    public function get_cleaned_value($form)
    {
        return $this->awards;
    }
}

class SlugField extends CharField
{
    protected $trim = false;
    protected $value = null;

    /**
     * SlugField constructor.
     * @param $name
     * @param $verbose_name
     * @param int $max_length
     * @param bool $is_required
     * @param string $help_text
     * @param null $placeholder
     * @param null $template_file
     * @param bool $trim
     */
    public function __construct($name, $verbose_name, $max_length = 0, $is_required = true, $help_text = '', $placeholder = null, $template_file = null, $trim = false)
    {
        $this->trim = $trim;
        parent::__construct($name, $verbose_name, $max_length, $is_required, $help_text, $placeholder, $template_file);
    }

    /**
     * @param Form $form
     * @return bool
     */
    protected function _is_valid(Form $form)
    {
        $isValid = parent::_is_valid($form);

        if ($isValid) {
            $value = $form->data[$this->name];

            if ($this->trim)
                $value = trim($value);

            $this->value = $value;

            if (!$value && $this->is_required) {
                $isValid = false;
                $this->error_msg = 'This field is required';
            }

            if ($isValid && $value && !preg_match("/^[0-9a-zA-Z-]+$/", $value)) {
                $this->error_msg = 'Your slug must contain only letters, numbers and dashes';
                $isValid = false;
            }
        }
        return $isValid;
    }

    /**
     * @param $form
     * @return mixed|null
     */
    public function get_cleaned_value($form)
    {
        return $this->value;
    }
}

class BuildVersionField extends CharField
{
    protected function _is_valid(Form $form)
    {
        $isValid = parent::_is_valid($form);

        if ($isValid) {
            $value = $form->data[$this->name];
            if (!preg_match("/^([0-9]+)\.([0-9]+)\.([0-9]+)$/", $value)) {
                $this->error_msg = 'The build version must match format: x.y.z (e.g: 0.3.57)';
                return false;
            }
        }
        return $isValid;
    }
}
class GameControllerBuildVersionField extends CharField
{
    protected function _is_valid(Form $form)
    {
        $isValid = parent::_is_valid($form);

        if ($isValid) {
            $value = $form->data[$this->name];

            if (!preg_match("/^([0-9]+)\.([0-9]+)\.([0-9]+)\.([0-9]+)$/", $value)) {
                $this->error_msg = 'The build version must match format: x.y.z.s (e.g: 0.3.57.1)';
                return false;
            }
        }
        return $isValid;
    }
}

class ExtendedSlugField extends CharField
{
    protected function _is_valid(Form $form)
    {
        $value = $form->data[$this->name];
        if (!preg_match("/^[a-zA-Z0-9-_]+$/", $value)) {
            $this->error_msg = 'Your username must contain only letters, numbers, underscores and dashes';
            return false;
        }
        return true;
    }
}

class IntegerField extends CharField
{
    protected $min;
    protected $max;

    public function __construct($name, $verbose_name, $is_required = true, $help_text = '', $min = null, $max = null, $templateFile = 'forms/char_field.twig')
    {
        parent::__construct($name, $verbose_name, 0, $is_required, $help_text, null, $templateFile);
        $this->help_text  = $help_text;
        $this->min = $min;
        $this->max = $max;

    }

    protected function _is_valid(Form $form)
    {
        $value = array_key_exists($this->name, $form->data) ? $form->data[$this->name] : null;

        if ($this->is_required) {
            if (!$value) {
                if ($value !== "0" && $value !== 0) {
                    $this->error_msg = 'This field is required.';
                    return $this->is_valid = false;
                }
            }
        }

        if (!is_null($value)) {
            if (!preg_match("/^-?[0-9]+$/", $value)) {
                $this->error_msg = 'This field must only contain whole numbers.';
                return $this->is_valid = false;
            }

            if (!Validators::int($value) && $value !== "") {
                $this->error_msg = 'This field must be an integer';
                return $this->is_valid = false;
            }

            if (is_numeric($this->min) && $this->min > $value) {
                $this->error_msg = 'Minimum value for this field is: '.$this->min;
                return $this->is_valid = false;
            }

            if (is_numeric($this->max) && $this->max < $value) {
                $this->error_msg = 'Maximum value for this field is: '.$this->max;
                return $this->is_valid = false;
            }
        }

        return $this->is_valid = true;
    }
}

class ArrayField extends FormField
{
    protected $values = [];

    public function is_valid(Form $form)
    {
        return $this->_is_valid($form);
    }

    /**
     * @param Form $form
     * @return bool
     */
    public function _is_valid(Form $form)
    {
        $isValid = true;

        // Check if form sent the data, if not, set to null.
        if (!array_key_exists($this->name, $form->data))
            $keyValues = [];
        else
            $keyValues = $form->data[$this->name];

        if (!is_array($keyValues)) {
            $this->set_error("{$this->name} is not of type array");
            $isValid = false;
        }

        if ($isValid) {
            // If this field is not required and has no value, the field is valid.
            if (!$this->is_required && !$keyValues) {
                return true;
            } elseif ($this->is_required && !$keyValues) {
                $this->set_error("{$this->name} field is required");
                $isValid = false;
            }

            if ($isValid) {
                $this->values = $keyValues;
            }
        }

        $this->is_valid = $isValid;

        return $this->is_valid;
    }

    public function _render($form, $class_name = null)
    {
        // TODO: Implement _render() method.
    }

    /**
     * @param $form
     * @return array|mixed
     */
    public function get_cleaned_value($form)
    {
        return $this->values;
    }
}

class KeyValueArrayField extends FormField 
{
    /** @var int  */
    protected $maxKeyLength = 32;
    /** @var int  */
    protected $maxValueLength = 254;

    /**
     * KeyValueArrayField constructor.
     * @param $name
     * @param $verbose_name
     * @param bool $is_required
     * @param int $maxKeyLength
     * @param int $maxValueLength
     * @param null $template_file
     */
    public function __construct($name, $verbose_name, bool $is_required = true, $maxKeyLength = 32, $maxValueLength = 254, $template_file = null)
    {
        $this->maxKeyLength = $maxKeyLength;
        $this->maxValueLength = $maxValueLength;
        
        parent::__construct($name, $verbose_name, $is_required, $template_file);
    }

    /**
     * @param $form
     * @return bool
     */
    protected function _is_valid(Form $form)
    {
        $isValid = true;

        // Check if form sent the data, if not, set to null.
        if (!array_key_exists($this->name, $form->data))
            $keyValues = null;
        else
            $keyValues = $form->data[$this->name];

        // If this field is not required and has no value, the field is valid.
        if (!$this->is_required && !$keyValues) {
            return true;
        } elseif ($this->is_required && !$keyValues) {
            $this->set_error("{$this->name} field is required");
            $isValid = false;
        }

        if ($isValid && $keyValues) {

            if (!is_array($keyValues)) {
                $this->set_error("Returned input is not an array/dictionary");
                $isValid = false;

            }

            if ($isValid) {
                foreach ($keyValues as $key => $value) {

                    if (is_array($value)) {
                        $this->set_error("Key: {$key} has incorrect value type: array");
                        $isValid = false;
                        break;
                    }

                    if (is_null($value)) {
                        $this->set_error("Key: {$key} has incorrect value type: null");
                        $isValid = false;
                        break;
                    }

                    if (!is_string($key)) {
                        $this->set_error("Key: {$key} is not a string");
                        $isValid = false;
                        break;
                    }

                    if (strlen($key) > $this->maxKeyLength) {
                        $this->set_error("Key: {$key} exceeds max key length: {$this->maxKeyLength}");
                        $isValid = false;
                        break;
                    }

                    if (strlen($value) > $this->maxValueLength) {
                        $this->set_error("Key: {$key} exceeds max value length: {$this->maxValueLength}");
                        $isValid = false;
                        break;
                    }
                }
            }

        }

        return $isValid;
    }

    protected function _render($form, $class_name = null)
    {
        // TODO: Implement _render() method.
    }

}

class MatchingSlugField extends SlugField {

    /** @var int */
    protected $matching_field_value;

    public function __construct($name, $verbose_name, $matching_value, $is_required = true, $max_length = 100, $help_text = '', $placeholder = null, $template_file = null)
    {
        $this->matching_field_value = $matching_value;
        parent::__construct($name, $verbose_name, $max_length, $is_required, $help_text, $placeholder, $template_file);
    }
}

class MatchingIntegerField extends IntegerField {

    /** @var int */
    protected $matching_field_value;

    public function __construct($name, $verbose_name, $matching_value, $is_required = true)
    {
        $this->matching_field_value = $matching_value;
        parent::__construct($name, $verbose_name, $is_required);
    }

    /**
     * @param $form
     * @return bool
     */
    protected function _is_valid(Form $form)
    {
        $is_valid = parent::_is_valid($form);

        if ($is_valid) {
            $field_value = $this->get_cleaned_value($form);

            if ($field_value != $this->matching_field_value) {
                $is_valid = false;
                $this->error_msg = "Field value ({$field_value}) must match validation value ({$this->matching_field_value})";
            }
        }

        return $is_valid;
    }
}

class FloatField extends CharField
{
    protected $minAmount;
    protected $maxAmount;
    public function __construct($name, $verbose_name, $is_required = true, $width = 300, $help_text = '', $minAmount = null, $maxAmount = null, $template_file = 'forms/char_field.twig')
    {
        parent::__construct($name, $verbose_name, 0, $is_required);
        $this->style = "width: ${width}px";
        $this->help_text  = $help_text;
        $this->template_file = $template_file;

        if ($minAmount)
            $this->minAmount = $minAmount;

        if ($maxAmount)
            $this->maxAmount = $maxAmount;

    }

    protected function _is_valid(Form $form)
    {
        $data = $form->data;
        $value = floatval($data[$this->name]);
        if (!Validators::float($value) && $value != "") {
            $this->error_msg = $form->translations['This field must be an integer or a float value'];
            return false;
        }

        if (!is_null($this->minAmount) && $value < $this->minAmount) {
            $this->error_msg = $form->translations['The minimum value is:']. " {$this->minAmount}";
            return false;
        }

        if (!is_null($this->maxAmount) && $value > $this->maxAmount) {
            $this->error_msg = $form->translations['The maximum value is:']. " {$this->maxAmount}";
            return false;
        }

        return true;
    }
}

class RangeField extends IntegerField
{
    protected $min;
    protected $max;

    public function __construct($name, $verbose_name, $min, $max, $is_required = true, $help_text = '')
    {
        parent::__construct($name, $verbose_name, 0, $is_required);
        $this->min = $min;
        $this->max = $max;
        $this->help_text = $help_text;
    }

    protected function _is_valid(Form $form)
    {
        if (!parent::_is_valid($form))
            return false;

        $value = (int)$form->data[$this->name];
        if ($form->data[$this->name] != "" &&
            ($this->min > $value || $this->max < $value)) {
            $this->error_msg = 'Out of range';
            return false;
        }
        return true;
    }
}

class FloatRangeField extends FloatField
{
    protected $min;
    protected $max;

    public function __construct($name, $verbose_name, $min, $max, $is_required = true, $help_text = '')
    {
        parent::__construct($name, $verbose_name, 0, $is_required);
        $this->min = $min;
        $this->max = $max;
        $this->help_text  = $help_text;
    }

    protected function _is_valid(Form $form)
    {
        if (!parent::_is_valid($form))
            return false;

        $value = (float)$form->data[$this->name];
        if ($form->data[$this->name] != ""
            && ($this->min > $value || $this->max < $value)) {
            $this->error_msg = 'Out of range';
            return false;
        }
        return true;
    }
}

class TextField extends CharField
{
    public function __construct(
        $name,
        $verbose_name,
        $max_length,
        $is_required = true,
        $help_text = '',
        $placeholder = '',
        $template_file = '',
        $isReadOnly = false
        )
    {
        parent::__construct($name, $verbose_name, $max_length, $is_required, $help_text, $placeholder, $template_file, $isReadOnly);
        $this->help_text  = $help_text;
    }

    protected function _render($form, $class_names = null)
    {
        $t = new Template();
        $t->assign([
            'data' => $form->data,
            'field' => $this,
            'class_names' => $class_names
        ]);
        if (!$this->template_file)
            $this->template_file = 'forms/text_field.twig';
        return $t->render_template($this->template_file);
    }
}

class SecureTextField extends TextField
{
    public function get_cleaned_value($form) {
        $cleanedValue = parent::get_cleaned_value($form);
        if($cleanedValue) {
            return parse_post($cleanedValue);
        }
        return $cleanedValue;
    }
}


class JsonDataField extends TextField {

    /**
     * Validates that the content can be JSON Decoded
     *
     * @param Form $form
     * @return bool
     */
    public function _is_valid(Form $form)
    {
        $this->is_valid = parent::_is_valid($form);

        $field_data = $this->get_cleaned_value($form);


        $decoded_field_data = json_decode($field_data, true);

        if ($decoded_field_data === null) {
            $this->is_valid = false;
            $this->error_msg = 'This field must contain a valid JSON data array';
        }

        return $this->is_valid;
    }

    public function get_cleaned_value($form)
    {
        return json_encode(parent::get_cleaned_value($form));
    }
}

class HiddenField extends CharField
{
    protected function _render($form, $class_names = null)
    {
        $t = new Template();
        $t->assign([
            'data' => $form->data,
            'field' => $this,
            'class_names' => $class_names
        ]);
        return $t->render_template('forms/hidden_field.twig');
    }
}

class RadioField extends FormField
{
    public $options;

    protected $template = 'forms/radio_field.twig';

    /**
     * @param $name
     * @param $verbose_name
     * @param array $options
     * @param bool|true $is_required
     * @param bool|false $inline
     * @param string $help_text
     */
    public function __construct($name, $verbose_name, $options, $is_required = true, $inline = false, $help_text = '', $template = null)
    {
        parent::__construct($name, $verbose_name, $is_required);
        $this->inline    = $inline;
        $this->options   = $options;
        $this->help_text = $help_text;

        if ($template)
            $this->template = $template;
    }

    protected function _is_valid(Form $form)
    {
        $value = $form->data[$this->name];
        foreach ($this->options as $option) {
            if ($this->_is_valid_option_value($option, $value))
                return true;
        }
        if ($this->is_required || $value != '') {
            $this->error_msg = 'Not a valid choice';
            return false;
        }
        return true;
    }

    protected function _render($form, $class_names = null)
    {
        $t = new Template();
        $t->assign([
            'data' => $form->data,
            'field' => $this,
            'class_names' => $class_names
        ]);

        return $t->render_template($this->template);
    }

    public function get_cleaned_value($form)
    {
        $value = array_get($form->data, $this->name, '');
        return $value != '' ? $value : null;
    }
}

/**
 * Class SelectField
 */
class SelectField extends FormField
{
    /** @var Array|BaseDataEntity[] */
    public $options;

    public $template_file = 'forms/select_field.twig';

    /**
     * @param $name
     * @param $verbose_name
     * @param array $options
     * @param bool|true $is_required
     * @param bool|false $inline
     * @param string $help_text
     */
    public function __construct($name, $verbose_name, $options, $is_required = true, $help_text = '', $template_file = 'forms/select_field.twig')
    {
        parent::__construct($name, $verbose_name, $is_required);
        $this->options   = $options;
        $this->help_text = $help_text;
        if ($template_file)
            $this->template_file = $template_file;
    }

    protected function _is_valid(Form $form)
    {
        $value = $form->data[$this->name];
        $isValid = false;
        foreach ($this->options as $option) {
            if ($this->_is_valid_option_value($option, $value))
                return true;
        }

        if (!$this->is_required && !$this->options) {
            return true;
        }

        if (!$isValid && $this->is_required) {
            $this->error_msg = "This field is required.";
            return false;
        }

        if ($this->is_required && $value != '') {
            $this->error_msg = 'Not a valid choice';
            return false;
        }

        return true;
    }

    protected function _render($form, $class_names = null)
    {
        $t = new Template();
        $t->assign([
            'data' => $form->data,
            'field' => $this,
            'form' => $form,
            'class_names' => $class_names,
            'i18n' => $form->translations
        ]);
        return $t->render_template($this->template_file);
    }

    public function get_cleaned_value($form)
    {
        $value = array_get($form->data, $this->name, '');
        return $value != '' ? $value : null;
    }
}


class MultipleBooleanField extends FormField
{
    public $options;

    /**
     * @param $name
     * @param $verbose_name
     * @param array|DBDataEntity[] $options
     * @param bool|true $is_required
     * @param string $help_text
     */
    public function __construct($name, $verbose_name, $options, $is_required = true, $help_text = '', $template = '')
    {
        parent::__construct($name, $verbose_name, $is_required, $template);
        $this->id_name = str_replace('[]', '', $name);
        $this->options = $options;
        $this->help_text  = $help_text;
    }

    protected function _is_valid(Form $form) {}

    public function is_valid(Form $form)
    {
        $data = $form->data;
        if (!array_key_exists($this->name, $data))
            return true;

        $array = $data[$this->name];
        foreach ($array as $value) {
            $is_valid = false;
            foreach ($this->options as $option) {
                if ($this->_is_valid_option_value($option, $value))
                    $is_valid = true;
            }
            if (!$is_valid) {
                $this->error_msg = 'Not a valid choice: '.htmlentities($value);
                return false;
            }
        }
        return true;
    }

    protected function _render($form, $class_names = null)
    {
        $t = new Template();
        $t->assign([
            'data' => $form->data,
            'field' => $this,
            'class_names' => $class_names
        ]);

        $template = $this->template_file ? $this->template_file : 'forms/multiple_boolean_field.twig';

        return $t->render_template($template);
    }
}

class MultipleSelectField extends FormField
{
    public $options;
    public $load_options_deferred = false;

    /**
     * @param $name
     * @param $verbose_name
     * @param array|DBDataEntity[] $options
     * @param bool|true $is_required
     * @param string $help_text
     */
    public function __construct($name, $verbose_name, $options, $is_required = true, $load_options_deferred = false, $help_text = '', $template = '')
    {
        parent::__construct($name, $verbose_name, $is_required, $template);
        $this->load_options_deferred = $load_options_deferred;
        $this->options = $options;
        $this->help_text  = $help_text;
    }

    protected function _is_valid(Form $form) {}

    public function is_valid(Form $form)
    {
        $data = $form->data;
        if (!array_key_exists($this->name, $data))
            return true;

        if ($this->is_required && !is_array($data[$this->name])) {
            $this->error_msg = 'Array Data not found';
            return false;
        }

        if (!$this->load_options_deferred) {
            $array = $data[$this->name];
            foreach ($array as $value) {
                $is_valid = false;
                foreach ($this->options as $option) {
                    if ($this->_is_valid_option_value($option, $value))
                        $is_valid = true;
                }
                if (!$is_valid) {
                    $this->error_msg = 'Not a valid choice: '.htmlentities($value);
                    return false;
                }
            }
        } else {
            if (method_exists($this, 'validate_deferred_options')) {
                $method = 'validate_deferred_options';
                return $this->$method();
            }
        }
        return true;
    }

    protected function _render($form, $class_names = null)
    {
        $t = new Template();
        $t->assign([
            'data' => $form->data,
            'field' => $this,
            'class_names' => $class_names
        ]);

        $template = $this->template_file ? $this->template_file : 'forms/select_multiple_field.twig';

        return $t->render_template($template);
    }
}

class MultipleSelectUiField extends MultipleSelectField
{
    public $options;
    public $load_options_deferred = false;
    public $placeholder;

    /**
     * @param $name
     * @param $verbose_name
     * @param array|DBDataEntity[] $options
     * @param bool|true $is_required
     * @param string $help_text
     */
    public function __construct($name, $verbose_name, $options, $is_required = true, $load_options_deferred = false, $help_text = '', $placeholder = '', $template = '')
    {
        parent::__construct($name, $verbose_name, $options, $is_required, $template);
        $this->load_options_deferred = $load_options_deferred;
        $this->options = $options;
        $this->placeholder = $placeholder;
        $this->help_text  = $help_text;
    }

    protected function _is_valid(Form $form) {}

    public function is_valid(Form $form)
    {
        $data = $form->data;
        if (!array_key_exists($this->name, $data))
            return true;

        if ($this->is_required && empty($data[$this->name])) {
            $this->error_msg = 'At least one choice is required.';
            return false;
        }

        if (!$this->load_options_deferred) {
            $stringValue = $data[$this->name];
            if ($stringValue) {
                $array = explode(',', $stringValue);
                if (!$array)
                    $array = [];
            } else {
                $array = [];
            }

            foreach ($array as $value) {
                $is_valid = false;
                foreach ($this->options as $option) {
                    if ($this->_is_valid_option_value($option, $value))
                        $is_valid = true;
                }
                if (!$is_valid) {
                    $this->error_msg = 'Not a valid choice: '.htmlentities($value);
                    return false;
                }
            }
        } else {
            if (method_exists($this, 'validate_deferred_options')) {
                $method = 'validate_deferred_options';
                return $this->$method();
            }
        }
        return true;
    }

    /**
     * @param $form
     * @return array
     */
    public function get_cleaned_value($form)
    {
        $stringValue = $form->data[$this->name];
        if ($stringValue) {
            $array = explode(',', $stringValue);
            if (!$array)
                $array = [];
        } else {
            $array = [];
        }
        return $array;
    }

    protected function _render($form, $class_names = null)
    {
        $t = new Template();

        if (isset($form->data[$this->name]))
            $defaults = $form->data[$this->name];
        else
            $defaults = [];

        $t->assign([
            'data' => $form->data,
            'field' => $this,
            'defaults' => $form->data[$this->name],
            'defaults_string' => join(',', $defaults),
            'class_names' => $class_names
        ]);

        $template = $this->template_file ? $this->template_file : 'forms/custom-fields/select_multiple_ui.twig';

        return $t->render_template($template);
    }
}

class FileField extends FormField
{
    /**
     * Accepted extensions of the uploaded file
     */
    protected $extensions;

    /**
     * Id used for saving the file if none specified in the form data
     */
    protected $upload_id;

    /**
     * JS code to control the field
     */
    protected $custom_js;

    public function __construct($name, $verbose_name, $is_required = true, $extensions = null, $template_file = 'forms/file_field.twig')
    {
        parent::__construct($name, $verbose_name, $is_required, $template_file);
        $this->extensions = $extensions;
        $this->upload_id = md5(TIME_NOW.'-'.rand());
    }

    public function is_valid(Form $form)
    {
        $this->is_valid = $this->_is_valid($form);

        return $this->is_valid;
    }

    protected function _is_valid(Form $form)
    {
        $data = $form->data;
        $files = $form->files;
        $user_id = $form->user_id;
        $key = 'upload_id_' . $this->name;

        if (!array_key_exists($key, $data)) {
            $uploadId = $this->upload_id;
//            $this->error_msg = 'Missing upload id';
//            return !$this->is_required;
        } else {
            $uploadId = $data[$key];
        }


        if (array_key_exists($this->name, $form->files)
            && array_key_exists('tmp_name', $form->files[$this->name])
            && array_key_exists('name', $form->files[$this->name])
            && $form->files[$this->name]['tmp_name'] != '') {

            try {
                UploadsHelper::check_upload_errors($form->files, $this->name);


                UploadsHelper::finish_upload($form->files[$this->name]['tmp_name'], $uploadId, $form->files[$this->name]['name']);
                $source_extension = FilesToolkit::get_file_extension($form->files[$this->name]['name']);

                if ($this->extensions && !in_array(strtolower($source_extension), $this->extensions)) {
                    $this->error_msg = 'Invalid extension';
                    return false;
                }

                return true;
            } catch(UploadException $e) {
                $this->error_msg = $e->getMessage();
                return false;
            }
        } else {
            if (!$this->is_required) {
                return true;
            }
            if (UploadsHelper::check_file_id($uploadId)) {
                try {
                    UploadsHelper::check_upload_errors($form->files, $this->name);
                    UploadsHelper::finish_upload($form->files[$this->name]['tmp_name'], $uploadId, $form->files[$this->name]['name']);
                    $source_extension = FilesToolkit::get_file_extension($form->files[$this->name]['name']);

                    if ($this->extensions && !in_array($source_extension, $this->extensions)) {
                        $this->error_msg = 'Invalid extension';
                        return false;
                    }

                    return true;
                } catch(UploadException $e) {
                    $this->error_msg = $e->getMessage();
                    return false;
                }
            } else {
                $this->error_msg = 'This file field is required';
                return false;
            }
        }
    }

    protected function _render($form, $class_names = null)
    {
        /** @var PostForm $form */

        $upload_id = array_get($form->data, 'upload_id_'.$this->name, $this->upload_id);
        $t = new Template();
        $t->assign([
            'data' => $form->data,
            'field' => $this,
            'upload_id' => $upload_id,
            'class_names' => $class_names,
            'extensions' => '',
            'settings' => $form->getSettings()
        ]);

        if ($this->extensions) {
            $formattedExtensions = [];
            foreach ($this->extensions as $ext) {
                $formattedExtensions[] = ".{$ext}";
            }
            $t['extensions'] = join (',', $formattedExtensions);
        }

        $info = UploadsHelper::get_file_info($upload_id);
        $filepath = UploadsHelper::path_from_file_id($upload_id);
        if ($info && file_exists($filepath)) {
            $t->assign([
                'filepath' => UploadsHelper::path_from_file_id($upload_id),
                'filesize' => filesize($filepath),
                'filename' => array_get($info, 'filename', 'Untitled'),
            ]);
        } else {
            $t->assign([
                'filepath' => '',
            ]);
        }

        return $t->render_template($this->template_file);
    }

    public function get_cleaned_value($form)
    {
        $upload_id = array_get($form->data, 'upload_id_'.$this->name, $this->upload_id);
        return ['upload_id_'.$this->name => $upload_id];
    }
}

class CroppedImageFileField extends FileField {

    public function __construct($name, $verbose_name, $is_required, $template_file = 'forms/custom-fields/cropped_image_file_field.twig')
    {
        $extensions = ['png', 'gif', 'jpg', 'jpeg'];
        parent::__construct($name, $verbose_name, $is_required, $extensions, $template_file);
    }
}

class URLField extends FormField
{
    protected $max_length;
    protected $width = 300;
    protected $type = 'url';

    public function __construct($name, $verbose_name, $max_length = 255,
            $is_required = true, $help_text = '', $allowed_schemes = ['http', 'https', 'ftp', 'irc'])
    {
        parent::__construct($name, $verbose_name, $is_required);
        $this->max_length      = $max_length;
        $this->help_text       = $help_text;
        $this->allowed_schemes = $allowed_schemes;
    }

    protected function _is_valid(Form $form)
    {
        $data = $form->data;
        $is_valid = true;
        $value = $data[$this->name];

        if (!$this->is_required) {
            if ($value === "" || $value === null) {
                $this->is_valid = true;
                return $this->is_valid;
            }
        }


        if ($this->max_length > 0 && strlen($value) > $this->max_length) {
            $is_valid = false;
            $this->error_msg = 'Max length exceeded';
        }

        $scheme = parse_url($data[$this->name], PHP_URL_SCHEME);
        if (!in_array($scheme, $this->allowed_schemes)) {
            $is_valid = false;
            $this->error_msg = 'Invalid URL';
        }

        $this->is_valid = $is_valid;

        return $this->is_valid;
    }

    protected function _render($form, $class_names = null)
    {
        $t = new Template();
        $t->assign([
            'data' => $form->data,
            'field' => $this,
            'class_names' => $class_names
        ]);
        return $t->render_template('forms/url_field.twig');
    }
}

class EmailField extends CharField
{
    public $type = 'email';
    public $autoComplete = true;

    public function __construct($name, $verbose_name, $is_required = true, $help_text = '', $placeholder = null, $template_file = null, $autoComplete = true)
    {
        parent::__construct($name, $verbose_name, 255, $is_required, $help_text, $placeholder, $template_file);
        $this->help_text  = $help_text;
        $this->placeholder = $placeholder;
        $this->autoComplete = $autoComplete;

    }

    protected function _is_valid(Form $form)
    {
        $isValid = parent::_is_valid($form);

        $data = array_get($form->data, $this->name, '');

        if ($isValid) {
            if ($this->is_required && $data && !Validators::email($data)) {
                $this->error_msg = 'Invalid Format';
                return false;
            }

        }
        return true;
    }

    /**
     * @param $form
     * @return mixed|string
     */
    public function get_cleaned_value($form)
    {
        return strtolower(parent::get_cleaned_value($form));
    }
}

class EmailArrayField extends CharField {

    public $type = 'email';

    protected $emailAddresses = [];

    /**
     * EmailArrayField constructor.
     * @param $name
     * @param $verbose_name
     * @param bool $is_required
     * @param string $help_text
     * @param null $placeholder
     * @param null $template_file
     */
    public function __construct($name, $verbose_name, $is_required = true, $help_text = '', $placeholder = null, $template_file = null)
    {
        parent::__construct($name, $verbose_name, 255, $is_required, $help_text, $placeholder, $template_file);
        $this->help_text  = $help_text;
        $this->placeholder = $placeholder;

    }

    /**
     * @param Form $form
     * @return bool
     */
    protected function _is_valid(Form $form)
    {
        $isValid = false;
        $emailAddresses = array_get($form->data, $this->name, []);

        if (!$emailAddresses && $this->is_required) {
            $this->error_msg = "{$this->name} field is required.";
            $isValid = false;
        }

        if ($isValid) {

            foreach ($emailAddresses as $emailAddress) {
                if (!Validators::email($emailAddress)) {
                    $this->error_msg = "Invalid Format: {$emailAddress}";
                    return false;
                } else {
                    $this->emailAddresses[] = strtolower($emailAddress);
                }
            }

        }
        return true;
    }

    /**
     * @param $form
     * @return array
     */
    public function get_cleaned_value($form)
    {
        return $this->emailAddresses;
    }

}

class PhoneNumberField extends CharField
{
    public $type = 'tel';

    /** @var string $phoneNumber */
    protected $phoneNumber;

    /** @var string */
    protected $countryId;

    public function __construct($name, $verbose_name, string $countryId = 'US', bool $is_required = true, string $help_text = '', $placeholder = null, $template_file = null)
    {
        parent::__construct($name, $verbose_name, 15, $is_required, $help_text, $placeholder, $template_file);

        $this->countryId = strtoupper($countryId);
    }

    /**
     * @param Form $form
     * @return bool
     */
    protected function _is_valid(Form $form)
    {
        $isValid = parent::_is_valid($form);

        if ($isValid) {

            $phoneNumber = $form->data[$this->name];

            if (!$phoneNumber && $this->is_required) {
                $isValid = false;
                $this->error_msg = "This field is required.";
            }

            if ($isValid) {
                $phoneUtil = PhoneNumberUtil::getInstance();

                try {

                    $numberProto = $phoneUtil->parse($phoneNumber, $this->countryId);
                    $isValid = $phoneUtil->isValidNumber($numberProto);
                    if (!$isValid) {
                        $this->error_msg = $form->translations['Invalid Phone Number'];
                        $isValid = false;
                    } else {
                        $this->phoneNumber = $phoneUtil->format($numberProto, \libphonenumber\PhoneNumberFormat::E164);
                    }

                } catch (\libphonenumber\NumberParseException $e) {
                    $isValid = false;
                    $this->error_msg = $e->getMessage();
                }
            }
        }

        return $this->is_valid = $isValid;
    }

    /**
     * @param $form
     * @return mixed|string
     */
    public function get_cleaned_value($form)
    {
        return $this->phoneNumber;
    }

}

class PhoneNumberArrayField extends CharField
{
    public $type = 'phone';

    /** @var string */
    protected $countryId;
    /** @var PhoneNumberUtil  */
    protected $phoneUtils;

    /** @var array */
    protected $phoneNumbers = [];

    public function __construct($name, $verbose_name, string $countryId = 'US', bool $is_required = true, string $help_text = '', $placeholder = null, $template_file = null)
    {
        parent::__construct($name, $verbose_name, 15, $is_required, $help_text, $placeholder, $template_file);

        $this->countryId = strtoupper($countryId);
        $this->phoneUtils = PhoneNumberUtil::getInstance();
    }

    /**
     * @param Form $form
     * @return bool
     */
    protected function _is_valid(Form $form)
    {
        $isValid = true;

        // If they key exists, set the value for further validation. It it doesn't and the field is required then
        // the field is not valid.
        if (array_key_exists($this->name, $form->data)) {
            $phoneNumbers = $form->data[$this->name];
        } else {
            if ($this->is_required) {
                $this->error_msg = "{$this->name} field of type array is missing.";
                $isValid = false;
            }
        }

        // If the field is valid and is not null, it must be an array.
        if ($isValid && !is_null($phoneNumbers) && !is_array($phoneNumbers)) {
            $isValid = false;
            $this->error_msg = "{$this->name} must be an array.";
        }

        // Process and validate the values data array
        /** @var array $phoneNumbers */
        if ($isValid && $phoneNumbers) {

            foreach ($phoneNumbers as $phoneNumber) {

                if ($isValid) {
                    try {
                        $numberProto = $this->phoneUtils->parse($phoneNumber, $this->countryId);
                        $isValid = $this->phoneUtils->isValidNumber($numberProto);
                        if (!$isValid) {
                            $this->error_msg = "Invalid Phone Number: {$phoneNumber}";
                            break;
                        }
                        $this->phoneNumbers[] = $this->phoneUtils->format($numberProto, \libphonenumber\PhoneNumberFormat::E164);
                    } catch (\libphonenumber\NumberParseException $e) {
                        $isValid = false;
                        $this->error_msg = $e->getMessage();
                        break;
                    }
                }
            }
        }
        return $this->is_valid = $isValid;
    }

    /**
     * @param Form $form
     * @return array
     */
    public function get_cleaned_value($form)
    {
        return array_unique($this->phoneNumbers);
    }
}


class PasswordField extends CharField
{
    public $type = 'password';

    public function __construct($name, $verbose_name, $is_required = true, $help_text = '', $placeholder = null, $template_file = 'forms/password_field.twig')
    {
        parent::__construct($name, $verbose_name, 0, $is_required, $help_text, $placeholder, $template_file);
        $this->help_text = $help_text;
        $this->placeholder = $placeholder;
    }
}


class PasswordConfirmField extends PasswordField
{
    public $password_field;

    public function __construct($name, $verbose_name, $password_field, $is_required = true, $help_text = '')
    {
        $this->password_field = $password_field;
        parent::__construct($name, $verbose_name, 0, $is_required);
        $this->help_text  = $help_text;
    }

    protected function _is_valid(Form $form)
    {
        if ($form->cleaned_data[$this->password_field.'_hash'] != $form->cleaned_data[$this->name.'_hash']) {
            $this->error_msg = 'Password does not match.';
            return false;
        }
        return parent::_is_valid($form);
    }
}


class CaptchaField extends FormField
{
    protected $config;
    protected $domain;
    protected $user_ip;

    public function __construct($config, $domain, $user_ip)
    {
        $this->config  = $config;
        $this->domain  = $domain;
        $this->user_ip = $user_ip;
        parent::__construct('recaptcha', 'Recaptcha', true);
    }

    public function is_valid(Form $form)
    {
        return $this->_is_valid($form);
    }

    protected function _is_valid(Form $form)
    {
        $user_response = $form->data['g-recaptcha-response'];

        // Discard spam submissions
        if (!$user_response) {
            $this->error_msg = 'Invalid Captcha';
            return false;
        }

        $params = http_build_query([
             'secret' => $this->config['recaptcha']['private_keys'][$this->domain],
             'remoteip' => $this->user_ip,
             'response' => $user_response,
        ]);

        $curl = curl_init("https://www.google.com/recaptcha/api/siteverify");
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $json = curl_exec($curl);

        if (curl_errno($curl) != 0) {
            $this->error_msg = 'Error checking your answer with recaptcha servers';
            return false;
        }

        $response = json_decode($json, true);
        if ($response['success'] === true)
            return true;

        $this->error_msg = 'Invalid Answer';
        return false;
    }

    protected function _render($form, $class_names = null)
    {
        $t = new Template();
        $t->assign([
            'data' => $form->data,
            'field' => $this,
            'public_key' => $this->config['recaptcha']['public_keys'][$this->domain],
            'class_names' => $class_names
        ]);
        return $t->render_template('forms/captcha_field.twig');
    }
}