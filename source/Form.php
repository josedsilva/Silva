<?php
/**
 * This file is part of Silva.
 *
 * Silva is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Silva is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Silva.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
/**
 * Create a Zend Form automagically from the TableMap of the model.
 * The form can be populated from the model instance and
 * a model instance can be populated from the form values.
 *
 * Supports i18n behavior
 * Can use custom Curry_Form elements when creating form
 * Can ignore foreign keys while creating form elements or automagically create and populate dropdowns with fk values
 * Can ignore columns for unwanted propel behaviors when creating form elements
 *
 * @category    Curry
 * @package     Silva
 * @version     2.0.0
 * @author      Jose Francisco D'Silva
 */
class Silva_Form extends Curry_Form
{
    /**#@+
     * Form element type
     */
    
    /**
     * Use Curry_Form_MultiForm for ARRAY column type
     */
    const CURRY_MULTIFORM = "Curry_Form_MultiForm";
    /**#@-*/
    
    protected $tableMap;
    protected $i18nTableMap = null;
    protected $locale = null;
    protected $backend = null;

    /**#@+
     * Form configuration
     */

    /**
     * whether content of the default locale be used when corresponding field is empty?
     * @var boolean
     */
    protected $useDefaultLocaleOnEmptyValue = false;
    /**
     * whether form elements be created for primary keys?
     * @var boolean
     */
    protected $ignorePks = true;
    /**
     * whether form elements be created for foreign keys?
     * @var boolean
     */
    protected $ignoreFks = true;
    /**
     * What columns to ignore when creating the form?
     * Columns created by these behaviors are ignored.
     * @var array
     */
    protected $ignoredColumns = array(
    	'sortable' => array('rank_column'),
    	'timestampable' => array('create_column', 'update_column'),
    	// buggy (as of propel 1.6.4):
    	// propel does not permit adding a behavior of the same name multiple times in a table.
    	// FIXME please update when issue is fixed.
    	'aggregate_column' => array('name'),
      'sluggable' => array('slug_column'),
    );
    /**#@-*/
    
    protected $colElmMap = null;
    
    protected $arrayColumnGlue = ',';

    /**
     * Create a Zend form.
     * Form elements are not attached to the form in the constructor.
     * Call createElements() to attach form elements to the form after setting all options.
     * @param TableMap $tableMap: Model from which to create form elements.
     * @param Curry_Backend $backend: Backend instance.
     * @param array $options: Options passed to Curry_Form.
     */
    public function __construct(TableMap $tableMap, Curry_Backend $backend, $options = null)
    {
        $this->addPrefixPaths(array(array('prefix' => 'Silva_Form', 'path' => 'Silva/Form/')));
        parent::__construct($options);

        $this->tableMap = $tableMap;
        $this->backend = $backend;
        $this->initI18nBehavior();
    }

    /**
     * Initialize I18n behavior (if relevant)
     */
    protected function initI18nBehavior()
    {
        $this->i18nTableMap = Silva_Propel::getI18nTableMap($this->tableMap);
    }
    
    protected function populateModel($instance, $form, $columns)
    {
        $values = $form->getValues(true);
        foreach ($columns as $elname => $column) {
            if (!$form->getElement($elname) && !$form->getSubForm("{$elname}_form")) {
                continue;
            }
            
            $val = $values[$elname];
            if ($column->getType() === PropelColumnTypes::PHP_ARRAY) {
                $val = $values["{$elname}_form"];
                if ($val === null) {
                    $val = array();
                } elseif (is_string($val)) {
                    $val = (array) explode($this->arrayColumnGlue, $val);
                } elseif ($this->isMultiForm($column)) {
                    // convert multiform defaults to array
                    $defaults = $values["{$elname}_form"];
                    $val = array();
                    foreach ($defaults as $item) {
                        $val[] = $item[$elname];
                    }
                }
            }
            
            $instance->{"set{$column->getPhpName()}"}($val);
        }
    }

    /**
     * Populate model $instance with values from the form.
     * You need to manually save the model.
     *
     * @param BaseObject $instance
     */
    public function fillModel($instance)
    {
        $this->populateModel($instance, $this, $this->getElementColumns());
        if ($this->i18nTableMap !== null) {
            $i18nForm = $this->getSubForm('I18n');
            $this->populateModel($instance, $i18nForm, $this->getI18nElementColumns());
        }
    }

    protected function populateElements($instance, $form, $columns, $locale = null)
    {
        if ($locale !== null) {
            $i18nProperties = Silva_Propel::getBehavior("i18n", $this->tableMap);
        }
        
        foreach ($columns as $elname => $column) {
            if (!$form->getElement($elname) && !$form->getSubForm("{$elname}_form")) {
                continue;
            }
            $columnPhpName = $column->getPhpName();
            $value = $instance->{"get{$columnPhpName}"}();
            if ( ($locale !== null) && $this->useDefaultLocaleOnEmptyValue && empty($value) && ($locale != $i18nProperties['default_locale'])) {
                // NOTE: getTranslation() will change the locale of $instance
                $value = $instance->getTranslation($i18nProperties['default_locale'])->{"get{$columnPhpName}"}();
                // reset the locale of $instance, just in case getTranslation() changed it
                $instance->setLocale($this->locale);
            }
            
            if ($column->getType() === PropelColumnTypes::PHP_ARRAY) {
                $ue = $this->getUserdefinedElement($column);
                if ($ue === null) {
                    // using default element, i.e. textarea
                    $value = implode($this->arrayColumnGlue, (array) $value);
                } elseif ($this->isMultiForm($column)) {
                    // populate multiform
                    $defaults = $this->getMultiFormDefaults($column, $value);
                    $fieldName = "{$elname}_form";
                    $mf = $form->getSubForm($fieldName);
                    $mf->setDefaults($defaults);
                    continue;
                }
            }
            
            $form->getElement($elname)
                ->setValue($value);
        }
    }
    
    /**
     * Populate form elements with values from the model $instance.
     * @param BaseObject $instance: Model instance whose column values will be used to populate corresponding form fields
     */
    public function fillForm($instance)
    {
        $this->populateElements($instance, $this, $this->getElementColumns());
        // populate i18n fields
        if ($this->i18nTableMap !== null) {
            $i18nForm = $this->getSubForm('I18n');
            $this->locale = $instance->getLocale();
            $i18nForm->addAttribs(array(
                'legend' => 'Locale: ' . Silva_View::getLanguageString($this->locale)
            ));
            $this->populateElements($instance, $i18nForm, $this->getI18nElementColumns(), $this->locale);
        }
    }
    
    /**
     * Return a user-defined element for $column, else return null
     * @param $column
     * @return null|string
     */
    protected function getUserdefinedElement(ColumnMap $column)
    {
        $v = (array) $this->colElmMap[$column->getPhpName()];
        return array_shift($v);
    }
    
    /**
     * Determine whether ARRAY $column is a Curry_Form_MultiForm.
     * @param ColumnMap $column
     */
    protected function isMultiForm(ColumnMap $column)
    {
        return ($this->getUserdefinedElement($column) === self::CURRY_MULTIFORM);
    }
    
    protected function getMultiFormDefaults(ColumnMap $column, array $values)
    {
        $defaults = array();
        $fieldname = strtolower($column->getName());
        foreach ($values as $value) {
            $defaults[] = array(
                $fieldname => $value,
            );
        }
        
        return $defaults;
    }

    /**
     * Whether to create form fields from primary keys.
     * @param boolean $value
     */
    public function setIgnorePks($value)
    {
        $this->ignorePks = (boolean) $value;
    }

    public function getIgnorePks()
    {
        return $this->ignorePks;
    }

    /**
     * Whether to create form fields from foreign keys.
     * @param boolean $value
     */
    public function setIgnoreFks($value)
    {
        $this->ignoreFks = (boolean) $value;
    }

    public function getIgnoreFks()
    {
        return $this->ignoreFks;
    }

    public function setLocale($locale)
    {
        $this->locale = $locale;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function getTablename()
    {
        return $this->tableMap->getPhpName();
    }

    public function setUseDefaultLocaleOnEmptyValue($value)
    {
        $this->useDefaultLocaleOnEmptyValue = (boolean) $value;
    }

    public function getUseDefaultLocaleOnEmptyValue()
    {
        return $this->useDefaultLocaleOnEmptyValue;
    }
    
    public function getArrayColumnGlue()
    {
        return $this->arrayColumnGlue;
    }
    
    public function setArrayColumnGlue($glue)
    {
        $this->arrayColumnGlue = $glue;
    }

    /**
     * Add behaviors and columns to the ignored list.
     * These columns will be ignored when form elements are created.
     * NOTE: behavior and column names are specified in "lowercase characters separated by underscores"
     *  
     * @param array $behaviorsOrColumns
     * @example $form->ignoreColumns(array("sluggable" => array('slug_column'), "user_comment"));
     */
    public function ignoreColumns(array $behaviorsOrColumns)
    {
        Silva_Array::extend($this->ignoredColumns, $behaviorsOrColumns);
    }

    /**
     * Remove a column or behavior from the ignored columns set.
     * The column will appear as a form element.
     * 
     * @param string $behaviorOrColumn
     * @example $form->removeIgnoredColumn("timestampable"); // show, as form fields, columns created by the "timestampable" behavior
     * @example $form->removeIgnoredColumn("user_comment"); // show, as form field, the "user_comment" column
     */
    public function removeIgnoredColumn($behaviorOrColumn)
    {
        if (array_key_exists($behaviorOrColumn, $this->ignoredColumns)) {
            unset($this->ignoredColumns[$behaviorOrColumn]);
        } elseif ( ($key = array_search($behaviorOrColumn, $this->ignoredColumns)) !== false) {
            unset($this->ignoredColumns[$key]);
        }
    }

    /**
     * Create and return a Curry_Form_Element from the ColumnMap.
     * The element is not attached to this form, only returned.
     *
     * @param ColumnMap $column
     * @return Curry_Form_Element|Curry_Form
     */
    public function createElementFromColumn(ColumnMap $column)
    {
        // create default element options
        $defaultOptions = array(
            'label' => ucfirst(strtolower(str_replace("_", " ", $column->getName()))),
            'required' => $column->isNotNull(),
        );
        $element = null;
        $fieldName = strtolower($column->getName());
        // tinyMCE fix
        if ($fieldName == 'content') {
            // FIXME strtolower($this->tableMap->getName()) is not required since $this->tableMap->getName() returns a lowercase-underscore-name
            $fieldName = strtolower($this->tableMap->getName()) . '_content';
        }
        
        if ($this->colElmMap !== null) {
            if ($column->getType()===PropelColumnTypes::PHP_ARRAY && $this->isMultiForm($column)) {
                return $this->getMultiForm($column, $fieldName, $defaultOptions);
            }
            $elm = $this->colElmMap[$column->getPhpName()];
            if ($elm !== null) {
                if (is_string($elm)) {
                    // user-defined Curry_Form_Element
                    $element = $this->createElement($elm, $fieldName);
                } elseif (is_array($elm)) {
                    // could be either:
                    // 1. Curry_Form_Element with array of user-defined options
                    // 2. array of user-defined options
                    reset($elm);
                    if (is_int(key($elm))) {
                        // case 1.
                        return $this->createElement(array_shift($elm), $fieldName, Silva_Array::extend($defaultOptions, array_pop($elm)));
                    } else {
                        // case 2.
                        $defaultOptions = Silva_Array::extend($defaultOptions, $elm);
                    }
                }
            }
        }
        
        if ($element === null) {
            if ($column->isForeignKey()) {
                $element = $this->createElement('select', $fieldName, array(
                    'multiOptions' => self::getMultiOptionsForFk($column, $this->locale),
                ));
            } else {
                switch ($column->getType()) {
                    case PropelColumnTypes::PHP_ARRAY:
                    case PropelColumnTypes::LONGVARCHAR:
                        $element = $this->createElement('textarea', $fieldName, array(
                            'rows' => 10,
                        ));
                        break;
                    case PropelColumnTypes::DATE:
                        $element = $this->createElement('date', $fieldName);
                        break;
                    case PropelColumnTypes::TIMESTAMP:
                        $element = $this->createElement('dateTime', $fieldName);
                        $element->setDescription('Choose date from the DatePicker and time should be entered in HH:MM:SS format.');
                        break;
                    case PropelColumnTypes::BOOLEAN:
                        $element = $this->createElement('checkbox', $fieldName);
                        break;
                    case PropelColumnTypes::ENUM:
                        $element = $this->createElement('select', $fieldName, array(
                            'multiOptions' => array_combine(
                                $column->getValueSet(),
                                array_map("ucfirst", $column->getValueSet())
                            ),
                        ));
                        break;
                    case PropelColumnTypes::DOUBLE:
                    case PropelColumnTypes::FLOAT:
                    case PropelColumnTypes::INTEGER:
                    case PropelColumnTypes::VARCHAR:
                    default:
                        $element = $this->createElement('text', $fieldName);
                        break;
                }
            }
        }
        
        $element->setOptions($defaultOptions);
        return $element;
    }

    /**
     * Return an array of "primaryString"s or Json encoded strings of the foreign object.
     * @param ColumnMap $foreignColumn
     * @param string $locale
     * @param boolean $showNullOption Whether to include "[-- Select %FOREIGN_TABLENAME% --]" in options?
     */
    public static function getMultiOptionsForFk(ColumnMap $foreignColumn, $locale = null, $showNullOption = true)
    {
        $foreignTableMap = $foreignColumn->getRelation()->getForeignTable();
        $foreignTablename = $foreignTableMap->getPhpName();
        $q = PropelQuery::from($foreignTablename);
        if ( ($locale !== null) && Silva_Propel::hasBehavior('i18n', $foreignTableMap) ) {
            $q->joinWithI18n($locale);
        }
        
        $objs = $q->find();
        $list = array();
        if ($showNullOption) {
            $list[0] = "[-- Select {$foreignTablename} --]";
        }
        
        foreach ($objs as $obj) {
            $list[$obj->getPrimaryKey()] = method_exists($obj, '__toString') ? $obj->__toString() : Zend_Json::prettyPrint(Zend_Json::encode($obj->toArray()), array('indent' => ' '));
        }
        
        return $list;
    }

    /**
     * Return an array containing the actual column names to ignore.
     * @return array
     */
    protected function getIgnoredColumns()
    {
        $columns = array();
        if (! empty($this->ignoredColumns)) {
            $behaviors = $this->tableMap->getBehaviors();
            foreach ($this->ignoredColumns as $behavior => $igcols) {
                if (is_int($behavior)) {
                    // this is a column_name
                    $columns[] = $igcols;
                } elseif (array_key_exists($behavior, $behaviors)) {
                    // this is a behavior
                    foreach ($igcols as $igcol) {
                        $fields = $behaviors[$behavior][$igcol];
                        // aggregate_column behavior separates fields by comma
                        // (the fix by jose.dsilva@bombayworks.se)
                        // FIXME please update when propelorm.org has a better solution
                        $fields = array_map("trim", explode(',', $fields));
                        $columns = array_merge($columns, $fields);
                    }
                }
            }
        }

        return $columns;
    }
    
    /**
     * Return the map of fieldNames versus ColumnMaps
     * @param ColumnMap[] $columns
     * @param $skipColumnFunc
     */
    protected function getElementColumnMap($columns, $skipColumnFunc)
    {
        $cols = array();
        $ignoredColumns = $this->getIgnoredColumns();
        foreach ($columns as $column) {
            $fieldName = strtolower($column->getName());
            // whether to ignore this column?
            if ($skipColumnFunc($column, $this->ignorePks, $this->ignoreFks) || in_array($fieldName, $ignoredColumns)) {
                continue;
            }
            // tinyMCE fix
            if ($fieldName == 'content') {
                // FIXME strtolower($this->tableMap->getName()) is not required since $this->tableMap->getName() returns a lowercase-underscore-name
                $fieldName = strtolower($this->tableMap->getName()) . '_content';
            }
            $cols[$fieldName] = $column;
        }
        return $cols;
    }

    /**
     * Return a map of elementName-ColumnMap objects for this table.
     * @return array (Array[form_field_name] = ColumnMap)
     */
    protected function getElementColumns()
    {
        $skipColFunc = create_function('$column,$igPks,$igFks', 'return (($column->isPrimaryKey() && $igPks) || ($column->isForeignKey() && $igFks));');
        return $this->getElementColumnMap($this->tableMap->getColumns(), $skipColFunc);
    }

    protected function getI18nElementColumns()
    {
        $skipColFunc = create_function('$column,$igPks,$igFks', 'return ($column->isPrimaryKey() || ($column->getName() == "LOCALE"));');
        return $this->getElementColumnMap($this->i18nTableMap->getColumns(), $skipColFunc);
    }
    
    protected function preCreateElements()
    {
        if (method_exists($this->backend, Silva_Event::EVENT_ON_FORM_ELEMENTS_INIT)) {
            $this->colElmMap = call_user_func_array(array($this->backend, Silva_Event::EVENT_ON_FORM_ELEMENTS_INIT), array($this));
        }
    }
    
    protected function addElementsToForm($form, $columns)
    {
        foreach ($columns as $column) {
            $element = $this->createElementFromColumn($column);
            if ($element instanceof  Zend_Form_Element) {
                $form->addElement($element);
            } elseif ($element instanceof Zend_Form) {
                $form->addSubForm($element, strtolower($column->getName()) . '_form');
            }
        }
    }

    /**
     * Construct form elements.
     * If the model has i18n behavior, a subform with the i18n elements will be added to this form.
     */
    public function createElements()
    {
        $this->preCreateElements();
        $this->addElementsToForm($this, $this->getElementColumns());
        if ($this->i18nTableMap !== null) {
            $i18nForm = new Curry_Form_SubForm();
            $this->addElementsToForm($i18nForm, $this->getI18nElementColumns());
            $this->addSubForm($i18nForm, 'I18n');
        }
    }

    protected function getMultiForm(ColumnMap $column, $fieldName, $options)
    {
        $element = 'text';
        $legend = null;
        $v = (array) $this->colElmMap[$column->getPhpName()];
        array_shift($v); //remove topmost item
        if (! empty($v)) {
            $t = array_shift($v);
            if (is_string($t)) {
                // user-defined element specified
                $element = $t;
                // extract user-defined options
                $v = array_pop($v);
            } elseif (is_array($t)) {
                $v = (array) $t;
            }
            
            if (! empty($v) && is_array($v)) {
                // is there a legend option?
                if (array_key_exists('legend', $v)) {
                    $legend = $v['legend'];
                    unset($v['legend']);
                }
                Silva_Array::extend($options, $v);
            }
        }
        
        // create dynamic form
        $dynaForm = new Curry_Form_Dynamic();
        $dynaForm->addElement($element, $fieldName, $options);
        
        // create multiform
        $multiForm = new Curry_Form_MultiForm(array(
            'legend' => $legend ? $legend : $column->getPhpName(),
            'cloneTarget' => $dynaForm,
            'defaults' => array(),
        ));
        
        return $multiForm;
    }

} // Silva_Form
