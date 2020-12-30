<?php
/**
 * Form class
 *
 */

class Forms {

    /**
     * Custom Request Get/Post/Put Forms (includes FormField to DB Mappers)
     */

    const ENTITY_IMAGE_UPLOADS = 'entity-image-uploads';
    const PAGE_ENTITY_FORMS = 'release-page-forms';
    const RESET_PASSWORD = 'reset-password';
    const REGISTRATION = 'registration';
}

class Form
{
    /**
     * Fields list
     *
     * @access private
     * @var FormField[]
     */
    protected $fields = [];

    protected $rendered_fields = [];

    /** @var  string $templateFile */
    protected $templateFile;

    /** @var array $viewData */
    public $viewData = [];

    public $isPost = false;

    /**
     * Form data
     *
     * @access public
     * @var array
     */
    public $data = [];

    /** @var string|null $request_id */
    public $request_id;

    /**
     * Form cleaned data
     *
     * @access public
     * @var array
     */
    public $cleaned_data = [];

    /**
     * Form uploaded files
     *
     * @access public
     * @var array
     */
    public $files = [];

    /**
     * User id
     *
     * @access public
     * @var array
     */
    public $user_id = null;

    /**
     * Form is bound if it's associated to some data
     * Triggers validation
     *
     * @access private
     * @var boolean
     */
    protected $is_bound = false;

    /**
     * Errors from set_error() are stored here
     *
     * @access public
     * @var array
     */
    public $errors = [];

    /** @var i18n $translations */
    public $translations;

    public $user;

    /**
     * Form constructor.
     * @param FormField[]|FormField $fields
     * @param null $data
     * @param null $files
     * @param null $user_id
     */
    public function __construct($fields, i18n $translations, $data = null, $files = null, $user_id = null, Request $request = null)
    {
        $this->add_fields($fields);
        $this->translations = $translations;

        if ($request)
            $this->isPost = $request->is_post();

        if ($request) {
            $this->request_id = $request->requestId;
            $this->viewData = [
                TemplateVars::WWW_URL => $request->getWwwUrl(),
                TemplateVars::DEVELOP_URL => $request->getDevelopUrl(),
                TemplateVars::PLAY_URL => $request->getPlayUrl()
            ];
        }

        if (is_array($data) > 0 || ($data instanceof DBDataEntity)) {
            $this->data = $data;
            $this->is_bound = true;
        }

        if (is_array($files) && $files)
        $this->files = $files;

        $this->user_id = $user_id;

    }

    /**
     * Render form in tempaltes
     */
    public function __toString()
    {
        return $this->render();
    }

    /**
     * @param $name
     * @return FormField
     * @throws Exception
     */
    public function get_field($name)
    {
        if (isset($this->fields[$name]))
            return $this->fields[$name];
        else
            return null;
    }

    /**
     * @param $name
     * @return bool
     */
    public function has_field($name)
    {
        return isset($this->fields[$name]);
    }

    /**
     * @return FormField[]
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @return array
     */
    public function getUpdatedFieldData()
    {
        $updated_fields = [];
        // Extract Field Values From Form
        foreach ($this->getFields() as $field) {
            $updated_fields[$field->get_name()] = $field->get_cleaned_value($this);
        }
        return $updated_fields;
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @param FormField[]|FormField $fields
     */
    public function add_fields($fields)
    {
        if ($fields instanceof FormField)
            $this->fields[$fields->get_name()] = $fields;
        elseif (is_array($fields)) {
            foreach($fields as $field) {
                if ($field instanceof FormField)
                    $this->fields[$field->get_name()] = $field;
                else
                    elog('Field is not an instance of FormField');
            }
        }
    }

    /**
     * Check if input data is valid
     */
    public function is_valid()
    {
        if (!$this->is_bound)
            return false;

        $is_valid = true;

        foreach ($this->fields as $field) {
            if (!$field->is_valid($this)) {
                $is_valid = false;
            } else {
                $this->cleaned_data[$field->get_name()] = $field->get_cleaned_value($this);
            }
        }
        return $is_valid;
    }

    /**
     * Mark the form as invalid and set an error
     *
     * @param string $msg error description
     */
    public function set_error($msg, $field = null)
    {
        if ($field) {
            if (isset($this->fields[$field]))
                $this->fields[$field]->set_error($msg);
            else
                $this->errors[$field] = $msg;
        }
        else
            $this->errors[] = $msg;
    }

    /**
     * @param array $viewData
     * @return $this
     */
    public function assignViewData(array $viewData)
    {
        $this->viewData = array_merge($this->viewData, $viewData);
        return $this;
    }

    /**
     * @param $templateFile
     * @return $this
     */
    public function setTemplateFile($templateFile)
    {
        $this->templateFile = $templateFile;
        return $this;
    }

    /**
     * Render the form as array
     */
    public function render_as_array()
    {
        $elements = [];
        $elements['errors'] = $this->render_errors();
        foreach($this->fields as $field)
            $elements[$field->get_name()] = $field->render($this);
        return $elements;
    }

    /**
     * @return string
     */
    public function render_errors()
    {
        $errorsHtml = '';
        foreach ($this->errors as $error)
            $errorsHtml .= "<div class=\"formerror\">".$error."</div>\n";
        return $errorsHtml;
    }

    /**
     * Render the form as html
     */
    public function render()
    {
        if ($this->templateFile) {

            $templateVars = [
                TemplateVars::I18N => $this->translations,
                TemplateVars::REQUEST_USER => $this->user,
                TemplateVars::FORM => $this,
            ];

            $template = new Template(array_merge($templateVars, $this->viewData));

            $html = '';

            foreach ($this->errors as $msg)
                $html .= "<div class=\"formerror\">".$msg."</div>\n";


            $html .= $template->render_template($this->templateFile);

            return $html;
        }
        elseif (!$this->rendered_fields) {
            $html = '';
            foreach ($this->errors as $msg)
                $html .= "<div class=\"formerror\">".$msg."</div>\n";
            foreach($this->fields as $field)
                $html .= "".$field->render($this)."\n";
            return $html;
        }
        else
            return $this->render_unrendered_fields();
    }

    /**
     * @return array
     */
    public function renderJson($results = [], $model = [])
    {
        $data = [
            'meta' => [
                'request_id' => $this->request_id,
                'user_id' => $this->user_id,
                'client_request_id' => null,
            ],
            'payload' => [
                'fields' => $this->renderFieldsAsJson(),
                'model' => $model,
                'results' => $results
            ],
            'form' => [
                'errors' => $this->errors
            ]
        ];

        return $data;
    }

    /**
     * @return array
     */
    public function renderFieldsAsJson()
    {
        $fields = [];

        foreach (array_keys($this->fields) as $fieldName) {

            $field = $this->fields[$fieldName];
            $jsonField = [
                'label' => $field->verbose_name,
                'name' => $fieldName,
                'field_group' => $field->fieldGroup,
                'help_text' => $field->help_text,
                'placeholder' => $field->placeholder,
                'is_required' => $field->is_required,
                'type' => $field->getType(),
                'is_valid' => $this->isPost ? $field->is_valid : null,
                'error' => $field->getErrorMsg(),
                'value' => $this->cleaned_data[$fieldName] ??  $this->data[$fieldName] ?? null,
                'options' => isset($field->options) ? [] : null,
                'properties' => isset($field->properties) ? $field->properties : []
            ];

            if (isset($field->options)) {
                foreach ($field->options as $option) {
                    if ($option instanceof DBManagerEntity)
                        $optionValues = $option->getOption();
                    else
                        $optionValues = $option;

                    $jsonField['options'][] = $optionValues;
                }
            }
            $fields[] = $jsonField;
        }

        return $fields;
    }

    /**
     * @return string
     */
    protected function render_unrendered_fields()
    {
        $html = '';
        foreach ($this->errors as $msg)
            $html .= "<div class=\"formerror\">".$msg."</div>\n";

        foreach ($this->fields as $field)
            if (!in_array($field->get_name(), $this->rendered_fields))
                $html .= "".$field->render($this)."\n";

        return $html;
    }

    /**
     * Render a single field
     */
    public function render_field($name, $class_names = null)
    {
        foreach($this->fields as $field)
            if ($field->get_name() == $name)
                return $field->render($this, $class_names);
        throw new Exception("Field ${name} not found to render");
    }

    /**
     * @param $field
     * @param $identifier
     * @return string
     * @throws Exception
     */
    public function renderDynamicField($field, $identifier, $class_names = null)
    {
        return $this->render_field(FormField::createDynamicFieldName($field, $identifier), $class_names);
    }


    /**
     * @param $key
     * @param null $default
     * @return null
     */
    public function has_cleaned_value($key)
    {
        return array_key_exists($key, $this->cleaned_data);
    }

    /**
     * @param $key
     * @param bool|true $handle_error
     * @return null|string|int
     */
    public function getCleanedValue($key, $default = null, $handle_error = true)
    {
        if ($handle_error) {

            $value = $this->has_cleaned_value($key) ? $this->cleaned_data[$key] : $default;

            if (!$value)
                $value = $default;

            return $value;
        }
        else
            return $this->cleaned_data[$key];
    }

    /**
     * @param $key
     * @param $value
     */
    public function set_value($key, $value)
    {
        $this->cleaned_data[$key] = $value;
    }

    /**
     * @return array
     */
    public function getAllCleanedData()
    {
        return $this->cleaned_data;
    }

    /**
     * Fallback for rendering fields by name magically
     *
     * @param $name
     * @param $arguments
     * @return string
     * @throws Exception
     */
    public function __call($name, $arguments)
    {
        if (!method_exists($this, $name)) {
            if (!$field = $this->get_field($name))
                return "<p>Field '{$name}' not found</p>";
            else {
                if (!in_array($name, $this->rendered_fields)) {
                    $this->rendered_fields[] = $name;
                    return $field->render($this, join(" ", $arguments));
                } else {
                    return "<p>Field named {$name} is already rendered in the template.</p>";
                }
            }
        } else {
            $method = new ReflectionMethod(get_class($this), $name);
            return $method->invokeArgs($this, $arguments);
        }
    }

}
