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
 * Create a Zend Form automagically from The TableMap of the model.
 * The Form can be populated from the model instance and
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
 * @author      Tobias Alex Peterson, Jose Francisco D'Silva
 */
class Silva_Form extends Curry_Form
{
    protected $tableMap;
    protected $i18nTableMap = null;
    protected $locale = null;
    protected $backend = null;

    /**#@+
     * Form configuration
     */

    /**
     * whether to use content of the default locale when fields are empty?
     * @var boolean
     */
    protected $useDefaultLocaleOnEmptyValue = false;
    /**
     * whether elements be constructed for primary keys?
     * @var boolean
     */
    protected $ignorePks = true;
    /**
     * whether dropdowns be created for foreign keys?
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
    );
    /**#@-*/
    
    protected $colElmMap = null;

    /**
     * Create a Zend form.
     * Form elements are not attached to the form in the constructor.
     * Call createElements() to attach form elements to the form after setting all options.
     * @param TableMap $tableMap Model from which form elements will be created
     * @param Curry_Backend $backend: Backend instance
     * @param array $options: Options passed to Curry_Form
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

    /**
     * Populate model $instance with values from the form.
     * You need to manually save the model.
     *
     * @param BaseObject $instance
     * @todo refactor code
     */
    public function fillModel($instance)
    {
        $values = $this->getValues();
        foreach ($this->getElementColumns() as $elname => $column) {
            if ($this->getElement($elname)) {
                $val = $values[$elname];
                if ($column->getType() === PropelColumnTypes::PHP_ARRAY) {
                    $val = (array) explode(',', $val);
                }
                $instance->{"set{$column->getPhpName()}"}($val);
            }
        }

        // populate i18n
        if ($this->i18nTableMap !== null) {
            $subform = $this->getSubForm('I18n');
            $i18nValues = (array) $values['I18n'];
            foreach ($this->getI18nElementColumns() as $elname => $column) {
                if ($subform->getElement($elname)) {
                    $val = $i18nValues[$elname];
                    if ($column->getType() === PropelColumnTypes::PHP_ARRAY) {
                        $val = (array) explode(',', $val);
                    }
                    $instance->{"set{$column->getPhpName()}"}($val);
                }
            }
        }
    }

    /**
     * Populate form elements with values from the model $instance.
     * @param BaseObject $instance Model instance whose column values will be used to populate corresponding form fields
     */
    public function fillForm($instance) {
        foreach ($this->getElementColumns() as $elname => $column) {
            if (! $this->getElement($elname)) {
                continue;
            }

            $val = $instance->{"get{$column->getPhpName()}"}();
            if ($column->getType() === PropelColumnTypes::PHP_ARRAY) {
                $val = implode(',', (array) $val);
            }

            $this->getElement($elname)
                ->setValue($val);
        }

        // populate i18n fields
        if ($this->i18nTableMap !== null) {
            $subform = $this->getSubForm('I18n');
            $this->locale = $instance->getLocale();
            $subform->addAttribs(array(
                'legend' => 'Locale: ' . Silva_View::getLanguageString($this->locale)
            ));

            $i18nProperties = Silva_Propel::getBehavior("i18n", $this->tableMap);
            foreach ($this->getI18nElementColumns() as $elname => $column) {
                if (! $subform->getElement($elname)) {
                    continue;
                }

                $value = $instance->{"get{$column->getPhpName()}"}();
                if ( empty($value) && $this->useDefaultLocaleOnEmptyValue && ($this->locale != $i18nProperties['default_locale']) ) {
                    // NOTE: getTranslation() will change the locale of $instance
                    $value = $instance->getTranslation($i18nProperties['default_locale'])->{"get{$column->getPhpName()}"}();
                }

                $subform->getElement($elname)
                    ->setValue($value);
            }

            // reset the locale of $instance, just in case getTranslation() changed it
            $instance->setLocale($this->locale);
        }
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

    /**
     * Add behaviors and columns to the ignored list.
     * These columns will be ignored when form elements are created.
     * NOTE: behavior and column names are specified in "lowercase characters separated by underscores"
     *  
     * @param array $behaviorsOrColumns
     * @example $form->ignoreColumns("sluggable");
     * @example $form->ignoreColumns("user_comment");
     */
    public function ignoreColumns(array $behaviorsOrColumns)
    {
        Curry_Array::extend($this->ignoredColumns, $behaviorsOrColumns);
    }

    /**
     * Remove a column or behavior from the ignored columns set.
     * The column will appear as a form element.
     * 
     * @param string $behaviorOrColumn
     * @example $form->removeIgnoreColumn("timestampable"); // show, as form fields, columns created by the "timestampable" behavior
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
     * @return Curry_Form_Element
     */
    public function createElementFromColumn(ColumnMap $column)
    {
        // create default element options
        $defaultOptions = array(
            'label' => ucfirst(strtolower(str_replace("_", " ", $column->getName()))),
            'required' => $column->isNotNull(),
        );
        
        $element = null;
        
        if (is_array($this->colElmMap) && (($elm = $this->colElmMap[$column->getPhpName()]) !== null) ) {
            if (is_string($elm)) {
                // user-defined Curry_Form_Element
                $element = $this->createElement($elm, strtolower($column->getName()));
            } elseif (is_array($elm)) {
                // could be either:
                // 1. Curry_Form_Element with array of user-defined options
                // 2. array of user-defined options
                reset($elm);
                if (is_int(key($elm))) {
                    // case 1.
                    return $this->createElement(array_shift($elm), strtolower($column->getName()), Curry_Array::extend($defaultOptions, array_pop($elm)));
                } else {
                    // case 2.
                    $defaultOptions = Curry_Array::extend($defaultOptions, $elm);
                }
            }
        }
        
        // create select dropdown for foreign-key column.
        if (($element === null) && $column->isForeignKey()) {
            $element = $this->createElement('select', strtolower($column->getName()), array(
                'multiOptions' => self::getMultiOptionsForFk($column, $this->locale)
            ));
        } elseif ($element === null) {
            switch ($column->getType()) {
                case PropelColumnTypes::PHP_ARRAY:
                case PropelColumnTypes::LONGVARCHAR:
                    $element = $this->createElement('textarea', strtolower($column->getName()), array(
                        'rows' => 10,
                    ));
                    break;
                case PropelColumnTypes::DATE:
                    $element = $this->createElement('date', strtolower($column->getName()));
                    break;
                case PropelColumnTypes::TIMESTAMP:
                    $element = $this->createElement('dateTime', strtolower($column->getName()));
                    $element->setDescription('Choose date from the DatePicker and time should be entered in HH:MM:SS format.');
                    break;
                case PropelColumnTypes::BOOLEAN:
                    $element = $this->createElement('checkbox', strtolower($column->getName()));
                    break;
                case PropelColumnTypes::ENUM:
                    $element = $this->createElement('select', strtolower($column->getName()), array(
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
                    $element = $this->createElement('text', strtolower($column->getName()));
                    break;
            }
        }
        
        $element->setOptions($defaultOptions);
        return $element;
    }

    /**
     * Return an array of "primaryString"s or Json encoded strings of the foreign object.
     * @param ColumnMap $foreignColumn
     */
    protected static function getMultiOptionsForFk(ColumnMap $foreignColumn, $locale = null)
    {
        $foreignTableMap = $foreignColumn->getRelation()->getForeignTable();
        $foreignTablename = $foreignTableMap->getPhpName();
        $q = PropelQuery::from($foreignTablename);
        if (($locale !== null) && Silva_Propel::hasBehavior('i18n', $foreignTableMap)) {
            $q->joinWithI18n($locale);
        }
        $objs = $q->find();
        // FIXME null translates to 0 (zero). Should we keep -1 instead?
        $list = array(null => "[-- Select {$foreignTablename} --]");
        foreach ($objs as $obj) {
            $list[$obj->getPrimaryKey()] = method_exists($obj, '__toString') ? $obj->__toString() : Zend_Json::prettyPrint(Zend_Json::encode($obj->toArray()), array('indent' => ' '));
        }
        
        return $list;
    }
        /*public function createElementFromRelation(RelationMap $relation)
	{
		switch ($relation->getType())
		{
			case RelationMap::ONE_TO_MANY:
			case RelationMap::MANY_TO_MANY:
				$element = $this->createElement('multiSelect', strtolower($relation->getName()));
				$element->setLabel(ucfirst(str_replace("_", " ", $relation->getName())) + 's');
				break;
			case RelationMap::MANY_TO_ONE:
			case RelationMap::ONE_TO_ONE:
				$element = $this->createElement('multiSelect', strtolower($relation->getName()));
				$element->setLabel(ucfirst(str_replace("_", " ", $relation->getName())));
				break;
		}

		$element->setMultioptions($this->getMultiOptsFromRelation($relation));
		return $element;
	}*/
    /*public function createMultiOptsFromRelation(RelationMap $relation)
	{
		$otherTable = $relation->getRightTable();
		$objs = PropelQuery::from($otherTable->getPhpName())->find();
		$opts = array();
		foreach ($objs as $obj)
		{
			if ( method_exists($obj, '__toString') )
			{
				$opts[$obj->getPrimaryKey()] = $obj->__toString();
			}
			else
			{
				$opts[$obj->getPrimaryKey()] = $obj->getPrimaryKey();
			}
		}

		return $opts;
	}*/

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
     * Return an array of ColumnMap objects for this table
     * that will be used in constructing form elements.
     *
     * @return array (Array[form_field_name] = ColumnMap)
     */
    protected function getElementColumns()
    {
        $columns = array();
        $ignoredColumns = $this->getIgnoredColumns();
        foreach ($this->tableMap->getColumns() as $column) {
            // whether to skip primary and foreign keys?
            if ( ($column->isPrimaryKey() && $this->ignorePks) || ($column->isForeignKey() && $this->ignoreFks) ) {
                continue;
            }

            $fieldName = strtolower($column->getName());
            // whether to ignore this column?
            if (in_array($fieldName, $ignoredColumns)) {
                continue;
            }

            $columns[$fieldName] = $column;
        }

        return $columns;
    }

    protected function getI18nElementColumns()
    {
        $columns = array();
        foreach ($this->i18nTableMap->getColumns() as $column) {
            // skip Pk and locale columns
            if ( $column->isPrimaryKey() || ($column->getName() == 'LOCALE') ) {
                continue;
            }

            $columns[strtolower($column->getName())] = $column;
        }

        return $columns;
    }
    
    protected function preCreateElements()
    {
        if (method_exists($this->backend, Silva_Event::EVENT_ON_FORM_ELEMENTS_INIT)) {
            $this->colElmMap = call_user_func_array(array($this->backend, Silva_Event::EVENT_ON_FORM_ELEMENTS_INIT), array(&$this));
        }
    }

    /**
     * Construct form elements.
     * If the model has i18n behavior, a subform with the i18n elements will be added to this form.
     */
    public function createElements()
    {
        $this->preCreateElements();

        foreach ($this->getElementColumns() as $column) {
            if ( ($column->isPrimaryKey && $this->ignorePks) || ($column->isForeignKey() && $this->ignoreFks) ) {
                continue;
            }

            $this->addElement($this->createElementFromColumn($column));
        }

        if ($this->i18nTableMap !== null) {
            $subform = $this->getLocaleSubForm();
            $this->addSubForm($subform, 'I18n');
        }
    }

    protected function getLocaleSubForm()
    {
        $subform = new Curry_Form_SubForm();
        foreach ($this->getI18nElementColumns() as $column) {
            $subform->addElement($this->createElementFromColumn($column));
        }

        return $subform;
    }

} // Silva_Form
