<?php

namespace Shengfai\LaravelAdmin\Config\Model;

use Shengfai\LaravelAdmin\Config\Config as ConfigBase;
use Shengfai\LaravelAdmin\Config\ConfigInterface;
use Shengfai\LaravelAdmin\Fields\Field as Field;
use Shengfai\LaravelAdmin\Fields\Factory as FieldFactory;
use Shengfai\LaravelAdmin\Actions\Factory as ActionFactory;

/**
 * The Model Config class helps retrieve a model's configuration and provides a reliable pointer for these items.
 */
class Config extends ConfigBase implements ConfigInterface
{
    /**
     * The config type.
     *
     * @var string
     */
    protected $type = 'model';

    /**
     * The default configuration options.
     *
     * @var array
     */
    protected $defaults = [
        'filters'            => [],
        'query_filter'       => null,
        'permission'         => true,
        'action_permissions' => [
            'create' => true,
            'delete' => true,
            'update' => true,
            'view'   => true,
        ],
        'actions'        => [],
        'batch_actions'  => [],
        'global_actions' => [],
        'sort'           => [],
        'form_width'     => 285,
        'link'           => null,
        'rules'          => false,
        'messages'       => false,
    ];

    /**
     * An instance of the Eloquent model object for this model.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $model;

    /**
     * The rules array.
     *
     * @var array
     */
    protected $rules = [
        'title'              => 'required|string',
        'single'             => 'required|string',
        'model'              => 'required|string|eloquent',
        'columns'            => 'required|array|not_empty',
        'edit_fields'        => 'array',
        'filters'            => 'array',
        'query_filter'       => 'callable',
        'permission'         => 'callable',
        'action_permissions' => 'array',
        'actions'            => 'array',
        'batch_actions'      => 'array',
        'global_actions'     => 'array',
        'sort'               => 'array',
        'form_width'         => 'integer',
        'link'               => 'callable',
        'rules'              => 'array',
        'messages'           => 'array',
    ];

    /**
     * Fetches the data model for a config.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function getDataModel()
    {
        if (!$this->model) {
            $name        = $this->getOption('model');
            $this->model = new $name();
        }

        return $this->model;
    }

    /**
     * Sets the data model for a config.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     */
    public function setDataModel($model)
    {
        $this->model = $model;
    }

    /**
     * Gets a model given an id.
     *
     * @param id    $id
     * @param array $fields
     * @param array $columns
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function getModel($id = 0, array $fields = [])
    {
        //if we're getting an existing model, we'll want to first get the edit fields without the relationships loaded
        $originalModel = $model = $this->getDataModel();

        //get the model by id
        $model = $model->find($id);
        $model = $model ? $model : $originalModel;

        //if the model exists, load up the existing related items
        if ($model->exists) {
            $this->setExtraModelValues($fields, $model);
        }

        return $model;
    }

    /**
     * Fills a model with the data it needs before being sent back to the user.
     *
     * @param array                               $fields
     * @param \Illuminate\Database\Eloquent\Model $model
     */
    public function setExtraModelValues(array $fields, &$model)
    {
        //make sure the relationships are loaded
        foreach ($fields as $name => $field) {
            if ($field->getOption('relationship')) {
                $this->setModelRelationship($model, $field);
            }

            //if this is a setter field, unset it
            if ($field->getOption('setter')) {
                $model->__unset($name);
            }
        }
    }

    /**
     * Fills a model with the necessary relationship values for a field.
     *
     * @param \Illuminate\Database\Eloquent\Model    $model
     * @param \Shengfai\LaravelAdmin\Fields\Field $field
     */
    public function setModelRelationship(&$model, Field $field)
    {
        //if this is a belongsToMany, we want to sort our initial values
        $relatedItems   = $this->getModelRelatedItems($model, $field);
        $name           = $field->getOption('field_name');
        $multipleValues = $field->getOption('multiple_values');
        $nameField      = $field->getOption('name_field');
        $autocomplete   = $field->getOption('autocomplete');
        $options        = $field->getOption('options');

        //get all existing values for this relationship
        if ($relatedItems) {
            //the array that holds all the ids of the currently-related items
            $relationsArray = [];

            //the id-indexed array that holds all of the select option data for a relation.
            //this holds the currently-related items and all of the available options
            $autocompleteArray = [];

            //iterate over the items
            foreach ($relatedItems as $item) {
                $keyName = $item->getKeyName();

                //if this is a mutliple-value type (i.e. HasMany, BelongsToMany), make sure this is an array
                if ($multipleValues) {
                    $relationsArray[] = $item->{$keyName};
                } else {
                    $model->setAttribute($name, $item->{$keyName});
                }

                //if this is an autocomplete field, we'll need to provide an array of arrays with 'id' and 'text' indexes
                if ($autocomplete) {
                    $autocompleteArray[$item->{$keyName}] = ['id' => $item->{$keyName}, 'text' => $item->{$nameField}];
                }
            }

            //if this is a BTM, set the relations array to the property that matches the relationship name
            if ($multipleValues) {
                $model->{$name} = $relationsArray;
            }

            //set the options attribute
            $model->setAttribute($name . '_options', $options);

            //unset the relationships so we only get back what we need
            $model->relationships = [];

            //set the autocomplete array
            if ($autocomplete) {
                $model->setAttribute($name . '_autocomplete', $autocompleteArray);
            }
        }
        //if there are no values, then just set an empty array
        else {
            $model->{$name} = [];
        }
    }

    /**
     * Fills a model with the necessary relationship values.
     *
     * @param \Illuminate\Database\Eloquent\Model    $model
     * @param \Shengfai\LaravelAdmin\Fields\Field $field
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getModelRelatedItems($model, Field $field)
    {
        $name = $field->getOption('field_name');

        if ($field->getOption('multiple_values')) {
            //if a sort_field is provided, use it, otherwise sort by the name field
            if ($sortField = $field->getOption('sort_field')) {
                return $model->{$name}()->orderBy($sortField)->get();
            } else {
                return $model->{$name}()->get();
            }
        } else {
            return $model->{$name}()->get();
        }
    }

    /**
     * Updates a model with the latest permissions, links, and fields.
     *
     * @param \Illuminate\Database\Eloquent\Model       $model
     * @param \Shengfai\LaravelAdmin\Fields\Factory  $fieldFactory
     * @param \Shengfai\LaravelAdmin\Actions\Factory $actionFactory
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function updateModel($model, FieldFactory $fieldFactory, ActionFactory $actionFactory)
    {
        //set the data model to the active model
        $this->setDataModel($model->find($model->getKey()));

        //include the item link if one was supplied
        if ($link = $this->getModelLink()) {
            $model->setAttribute('admin_item_link', $link);
        }

        //set up the model with the edit fields new data
        $model->setAttribute('administrator_edit_fields', $fieldFactory->getEditFieldsArrays(true));

        //set up the new actions data
        $model->setAttribute('administrator_actions', $actionFactory->getActionsOptions(true));
        $model->setAttribute('administrator_action_permissions', $actionFactory->getActionPermissions(true));

        return $model;
    }

    /**
     * Saves the model.
     *
     * @param \Illuminate\Http\Request $input
     * @param array                    $fields
     * @param array                    $actionPermissions
     * @param int                      $id
     *
     * @return mixed //string if error, true if success
     */
    public function save(\Illuminate\Http\Request $input, array $fields, array $actionPermissions = null, $id = 0)
    {
        $model = $this->getDataModel()->find($id);

        //fetch the proper model so we don't have to deal with any extra attributes
        if (!$model) {
            $model = $this->getDataModel();
        }

        //make sure the user has the proper permissions
        if ($model->exists) {
            if (!$actionPermissions['update']) {
                return 'You do not have permission to save this item';
            }
        } elseif (!$actionPermissions['update'] || !$actionPermissions['create']) {
            return 'You do not have permission to create this item';
        }

        //fill the model with our input
        $this->fillModel($model, $input, $fields);

        //validate the model
        $data            = $model->exists ? $model->getDirty() : $model->getAttributes();
        $validation_data = array_merge($data, $this->getRelationshipInputs($input, $fields));
        $rules           = $this->getModelValidationRules();
        $rules           = $model->exists ? array_intersect_key($rules, $validation_data) : $rules;
        $messages        = $this->getModelValidationMessages();
        $validation      = $this->validateData($validation_data, $rules, $messages);

        //if a string was kicked back, it's an error, so return it
        if (is_string($validation)) {
            return $validation;
        }

        //save the model
        $model->save();

        //save the relationships
        $this->saveRelationships($input, $model, $fields);

        //set/update the data model
        $this->setDataModel($model);

        return true;
    }

    /**
     * Prepare a model for saving given a post input array.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param \Illuminate\Http\Request            $input
     * @param array                               $fields
     */
    public function fillModel(&$model, \Illuminate\Http\Request $input, array $fields)
    {
        //run through the edit fields to see if we need to unset relationships or uneditable fields
        foreach ($fields as $name => $field) {
            if (!$field->getOption('external') && $field->getOption('editable')) {
                $field->fillModel($model, $input->get($name, null));
            }
            //if this is an "external" field (i.e. it's not a column on this model's table) or uneditable, unset it
            elseif ($name !== $model->getKeyName()) {
                $model->__unset($name);
            }
        }

        //loop through the fields again to unset any setter fields
        foreach ($fields as $name => $field) {
            $type = $field->getOption('type');

            if (($field->getOption('setter') && $type !== 'password') || ($type === 'password' && empty($model->{$name}))) {
                $model->__unset($name);
            }
        }
    }

    /**
     * Gets the validation rules for this model.
     *
     * @return array
     */
    public function getModelValidationRules()
    {
        $optionsRules = $this->getOption('rules');

        //if the 'rules' option was provided for this model, it takes precedent
        if (is_array($optionsRules)) {
            return $optionsRules;
        }

        //otherwise look for the rules as a static property on the model
        return $rules = $this->getModelStaticValidationRules() ?: [];
    }

    /**
     * Gets the validation messages for this model.
     *
     * @return array
     */
    public function getModelValidationMessages()
    {
        $optionsMessages = $this->getOption('messages');

        //if the 'rules' option was provided for this model, it takes precedent
        if (is_array($optionsMessages)) {
            return $optionsMessages;
        }

        //otherwise look for the messages as a static property on the model
        return $rules = $this->getModelStaticValidationMessages() ?: [];
    }

    /**
     * Gets the static rules propery for a model if one exists.
     *
     * @return mixed
     */
    public function getModelStaticValidationRules()
    {
        $model = $this->getDataModel();

        return isset($model::$rules) && is_array($model::$rules) ? $model::$rules : false;
    }

    /**
     * Gets the static messages propery for a model if one exists.
     *
     * @return mixed
     */
    public function getModelStaticValidationMessages()
    {
        $model = $this->getDataModel();

        return isset($model::$messages) && is_array($model::$messages) ? $model::$messages : false;
    }

    /**
     * Gets the relationship inputs.
     *
     * @param \Illuminate\Http\Request $request
     * @param array                    $fields
     *
     * @return array
     */
    protected function getRelationshipInputs(\Illuminate\Http\Request $request, array $fields)
    {
        $inputs = [];

        //run through the edit fields to find the relationships
        foreach ($fields as $name => $field) {
            if ($field->getOption('external')) {
                $inputs[$name] = $this->formatRelationshipInput($request->get($name, null), $field);
            }
        }

        return $inputs;
    }

    /**
     * Gets the formatted value of a relationship input.
     *
     * @param string                                 $value
     * @param \Shengfai\LaravelAdmin\Fields\Field $field
     *
     * @return mixed array | string
     */
    protected function formatRelationshipInput($value, Field $field)
    {
        $value = trim($value);

        if ($field->getOption('multiple_values')) {
            $value = $value ? explode(',', $value) : [];
        }

        return $value;
    }

    /**
     * After a model has been saved, this is called to save the relationships.
     *
     * @param \Illuminate\Http\Request            $input
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param array                               $fields
     */
    public function saveRelationships(\Illuminate\Http\Request $input, &$model, array $fields)
    {
        //run through the edit fields to see if we need to set relationships
        foreach ($fields as $name => $field) {
            if ($field->getOption('external')) {
                $field->fillModel($model, $input->get($name, null));
            }
        }
    }

    /**
     * Gets a model's link if one was provided, substituting for field names with this format: (:field_name).
     *
     * @return mixed
     */
    public function getModelLink()
    {
        $linkCallback = $this->getOption('link');

        if ($linkCallback && is_callable($linkCallback)) {
            return $linkCallback($this->getDataModel());
        } else {
            return false;
        }
    }

    /**
     * Runs a user-supplied query filter if one is supplied.
     *
     * @param \Illuminate\Database\Query\Builder $query
     */
    public function runQueryFilter(\Illuminate\Database\Query\Builder &$query)
    {
        if ($filter = $this->getOption('query_filter')) {
            $filter($query);
        }
    }

    /**
     * Fetches the data model for a config given a post input array.
     *
     * @param \Illuminate\Http\Request $input
     * @param array                    $fields
     * @param int                      $id
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function getFilledDataModel(\Illuminate\Http\Request $input, array $fields, $id = 0)
    {
        $model = $this->getDataModel();

        if ($id) {
            $model = $model->find($id);
        }

        $this->fillModel($model, $input, $fields);

        return $model;
    }
}
