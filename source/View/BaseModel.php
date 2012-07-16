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
 * Abstract class for a Curry_Backend view associated with a model.
 *
 * @category	Curry
 * @package		Silva
 * @author		Jose Francisco D'Silva
 * @version
 *
 */
abstract class Silva_View_BaseModel extends Silva_View
{
    protected $tableMap = null;
    protected $tableHasCompositePk = null;
    protected $catRelationMap = null;
    protected $catTableHasCompositePks = null;
    /**
     * The current locale if this table has the i18n behavior
     * @var string|null
     */
    protected $locale = null;
    protected $i18nTableMap = null;
    protected $hasI18nBehavior = null;

    protected static $defaultOptions = array(
        // whether to automagically appended a submit button to the form if not present.
        'appendSubmitButton' => true,
        // whether to automagically build form from the TableMap?
        'autoBuildForm' => false,
        // whether get[TablePhpName]Form() callback must be made when "autoBuildForm" is true?
        'getFormCallback' => true,
        // whether save[TablePhpName]() callback must be made when "autoBuildForm" is true?
        'saveCallback' => true,
        // whether empty value is substituted by the default locale's content when "autoBuildForm" is edited?
        'useDefaultLocaleOnEmptyValue' => false,
        // whether to use custom Curry_Form_Elements?
        'useCustomFormElements' => false,
        // whether to ignore foreign keys when auto building forms?
        'ignoreForeignKeys' => false,
        // whether to ignore primary keys when auto building forms?
        'ignorePrimaryKeys' => true,
    );

    /**
     * Instantiate a view object.
     *
     * @param TableMap|string $tableMap	The TableMap object or the table name whose tuples to show in the view.
     * @param RelationMap|string $catRelationMap	The RelationMap that relates this table to the category or the RelationMap name.
     * @param Curry_Backend $backend
     */
    public function __construct($tableMap, $catRelationMap = null, Curry_Backend $backend)
    {
        parent::__construct($backend);
        $this->tableMap = is_string($tableMap) ? PropelQuery::from($tableMap)->getTableMap() : $tableMap;
        $this->catRelationMap = is_string($catRelationMap) ? $this->tableMap->getRelation($catRelationMap) : $catRelationMap;
        $this->extendOptions(self::$defaultOptions);
        // initialise I18n behavior
        if ($this->hasI18nBehavior()) {
            $i18nProperties = $this->getI18nBehavior();
            $this->locale = isset($_GET[Silva_Backend::URL_QUERY_LOCALE]) ? $_GET[Silva_Backend::URL_QUERY_LOCALE] : $i18nProperties['default_locale'];
        }
    }

    // override
    protected function extendOptions(array $options)
    {
        parent::extendOptions($options);
        if (! $this->options['autoBuildForm']) {
            $this->options['getFormCallback'] = true;
            $this->options['saveCallback'] = true;
            $this->options['useCustomFormElements'] = false;
            $this->options['useDefaultLocaleOnEmptyValue'] = false;
        }
    }

    /**
     * Return the PhpName of this table
     * @return string
     */
    public function getTablename()
    {
        return $this->tableMap->getPhpName();
    }

    /**
     * Return the classname belonging to this tableMap.
     * @return string
     */
    public function getModelClass()
    {
        return $this->tableMap->getClassname();
    }

    /**
     * Return the PhpName of the I18n table
     * @return string|null
     */
    public function getI18nTablename()
    {
        return $this->hasI18nBehavior() ? $this->getI18nTableMap()->getPhpName() : null;
    }

    /**
     * Whether the table has composite primary keys
     * @return boolean
     */
    public function tableHasCompositePk()
    {
        if ($this->tableHasCompositePk === null) {
            $this->tableHasCompositePk = (boolean) (count($this->tableMap->getPrimaryKeys()) > 1);
        }
        return $this->tableHasCompositePk;
    }

    /**
     * Return the schema name of the Pk for this table.
     * If this table has a composite key, the Pk name is returned as "table_name_id".
     *
     * @return string
     */
    protected function getPkName()
    {
        $cmPks = $this->tableMap->getPrimaryKeys();
        $isCompositeKey = (boolean) (count($cmPks) > 1);
        if ($isCompositeKey) {
            $pkName = strtolower($this->tableMap->getName()) . '_id';
        } else {
            reset($cmPks);
            $cm = current($cmPks);
            $pkName = strtolower($cm->getName());
        }
        return $pkName;
    }

    /**
     * Return the PhpName of the Pk for this table.
     * If this table has a composite key, the Pk name is returned as "TableNameId".
     *
     * @return string
     */
    protected function getPkPhpName()
    {
        $cmPks = $this->tableMap->getPrimaryKeys();
        $pkPhpName = (count($cmPks) > 1) ? (strtolower($this->getTablename()) . 'Id') : strtolower($cmPks->getPhpName());
        return $pkPhpName;
    }

    /**
     * Return the category table's Php name
     * @return string|null
     */
    public function getCategoryTablename()
    {
        return ($this->catRelationMap !== null) ? $this->catRelationMap->getForeignTable()->getPhpName() : null;
    }

    /**
     * Whether the category table has composite primary keys
     * @return boolean|null
     */
    public function categoryTableHasCompositePks()
    {
        if ( ($this->catRelationMap !== null) && ($this->catTableHasCompositePks === null) ) {
            $this->catTableHasCompositePks = (boolean) (count($this->catRelationMap->getForeignTable()->getPrimaryKeys()) > 1);
        }
        return $this->catTableHasCompositePks;
    }

    /**
     * Return a ColumnMap object of the category's local reference column.
     * @return ColumnMap
     */
    public function getCategoryLocalReference()
    {
        $localRefs = $this->catRelationMap->getLocalColumns();
        $localRef = $localRefs[0];
        return $localRef;
    }

    /**
     * Return the schema name of the category's local reference.
     * @return string
     */
    public function getCategoryLocalReferenceName()
    {
        return strtolower($this->getCategoryLocalReference()->getName());
    }

    /**
     * Return the PhpName of the category's local reference.
     * @return string
     */
    public function getCategoryLocalReferencePhpName()
    {
        return $this->getCategoryLocalReference()->getPhpName();
    }

    public function getCategoryForeignReference()
    {
        $foreignRefs = $this->catRelationMap->getForeignColumns();
        $foreignRef = $foreignRefs[0];
        return $foreignRef;
    }

    public function getCategoryForeignReferenceName()
    {
        return strtolower($this->getCategoryForeignReference()->getName());
    }

    public function getCategoryForeignReferencePhpName()
    {
        return $this->getCategoryForeignReference()->getPhpName();
    }

    public function getI18nBehavior()
    {
        return $this->hasI18nBehavior() ? Silva_Propel::getBehavior('i18n', $this->tableMap) : null;
    }

    /**
     * Whether this table has I18n behavior
     * @return boolean
     */
    public function hasI18nBehavior()
    {
        if ($this->hasI18nBehavior === null) {
            $this->hasI18nBehavior = Silva_Propel::hasBehavior('i18n', $this->tableMap);
        }

        return $this->hasI18nBehavior;
    }

    /**
     * Return the I18n TableMap for this table if it has I18n behavior
     * @return TableMap|null
     */
    public function getI18nTableMap()
    {
        if ( $this->hasI18nBehavior() && ($this->i18nTableMap === null) ) {
            $this->i18nTableMap = Silva_Propel::getI18nTableMap($this->tableMap);
        }
        return $this->i18nTableMap;
    }

    // override
    public function getBreadcrumbText()
    {
        if ($this->breadcrumbText === null) {
            $this->breadcrumbText = "{$this->getTablename()}s";
        }
        return $this->breadcrumbText;
    }

    /**
     * Enable View to edit the model.
     * You will have to define 2 callback methods in your backend class, viz.
     * 1. public function get[TablePhpName/Class]Form([TableClass] $instance, Curry_Form|Silva_Form $form);
     * -> A partial Curry_Form or Silva_Form is passed to the method. The action and encoding has been set.
     * If Curry_Form, add elements to $form and return $form.
     * If Silva_Form, manipulate pre-added elements and return $form.
     * 2. public function save[TableClass]([TableClass] $instance, array values);
     * -> The method that saves $form values to the model.
     *
     * @param boolean $returnPartial: @see Curry_Application::returnPartial
     * @return string|Curry_Form|Silva_Form
     *
     */
    public function editModel($returnPartial = true)
    {
        $activeRecord = $this->getActiveRecord();
        if ($activeRecord->isNew()) {
            // associate with category
            if ($this->catRelationMap !== null) {
                // Respective ajax url query params must be resolved to local references by respective Views.
                // @see Silva_View_Grid::getAddEditUrl()
                $activeRecord->{"set{$this->getCategoryLocalReferencePhpName()}"}($_GET[$this->getCategoryLocalReferenceName()]);
            }
        }

        // localization
        if ($this->hasI18nBehavior()) {
            $i18nProperties = $this->getI18nBehavior();
            $this->locale = isset($_GET[Silva_Backend::URL_QUERY_LOCALE]) ? $_GET[Silva_Backend::URL_QUERY_LOCALE] : $i18nProperties['default_locale'];
            $activeRecord->setLocale($this->locale);
        }

        $form = $this->getActiveRecordForm($activeRecord);
        // append a save/update button as necessary
        if ( $this->options['appendSubmitButton'] && !$form->getElement('save') ) {
            $form->addElement('submit', 'save', array('label' => $activeRecord->isNew() ? 'Save' : 'Update'));
        }

        $ret = $form;
        if ( isPost() && $form->isValid($_POST) ) {
            $ret = $this->saveActiveRecord($activeRecord, $form);
        }

        if ($returnPartial) {
            $this->backend->returnPartial($ret);
        }

        return $ret;
    }

    /**
     * Return the active record or create a new one if it does not exists.
     * @return BaseObject
     */
    protected function getActiveRecord()
    {
        // edit
        if ( isset($_GET[$this->getPkName()]) ) {
            $activeRecord = PropelQuery::from($this->getTablename())->findPk($this->tableHasCompositePk() ? unserialize($_GET[$this->getPkName()]) : $_GET[$this->getPkName()]);
        }

        // create
        if (! $activeRecord) {
            $modelClass = $this->getModelClass();
            $activeRecord = new $modelClass();
        }

        return $activeRecord;
    }

    protected function getActiveRecordForm($activeRecord)
    {
        if ($this->options['autoBuildForm']) {
            $silvaForm = $this->getSilvaForm($activeRecord, array($this->getPkName() => $this->tableHasCompositePk() ? serialize($activeRecord->getPrimaryKey()) : $activeRecord->getPrimaryKey()));
            if ($this->options['getFormCallback']) {
                $cbFormHandler = "get{$this->getTablename()}Form";
                if (! method_exists($this->backend, $cbFormHandler)) {
                    throw new Silva_Exception("Callback ($cbFormHandler) not defined in " . get_class($this->backend));
                }

                $form = call_user_func(array($this->backend, $cbFormHandler), $activeRecord, $silvaForm);
            } else {
                $form = $silvaForm;
            }
        } else {
            // partial form
            $cbFormHandler = "get{$this->getTablename()}Form";
            if (! method_exists($this->backend, $cbFormHandler)) {
                throw new Silva_Exception("Callback ($cbFormHandler) not defined in " . get_class($this->backend));
            }

            // partially constructed form without form elements
            $partialForm = self::getPartialForm(array(
                $this->getPkName() => $this->tableHasCompositePk() ? serialize($activeRecord->getPrimaryKey()) : $activeRecord->getPrimaryKey()
            ));
            // get user-defined form from the backend module
            $form = call_user_func(array($this->backend, $cbFormHandler), $activeRecord, $partialForm);
        }

        return $form;
    }

    protected function saveActiveRecord($activeRecord, $form)
    {
        if ($this->options['autoBuildForm']) {
            // automagically populate known columns from form fields
            $form->fillModel($activeRecord);
            // ignore save handler?
            if (! $this->options['saveCallback']) {
                $activeRecord->save();
                return ''; //close popup
            }
        }

        $cbSaveHandler = "save{$this->getTablename()}";
        if (! method_exists($this->backend, $cbSaveHandler)) {
            throw new Silva_Exception("Callback ($cbSaveHandler) not defined in " . get_class($this->backend));
        }

        $ret = call_user_func(array($this->backend, $cbSaveHandler), $activeRecord, (array) $form->getValues());
        return (($ret === null) ? '' : $ret);
    }

    public function hasTable()
    {
        return true;
    }

    // override
    public function getViewname()
    {
        if ($this->viewname === null) {
            $this->viewname = "Main{$this->getTablename()}s";
        }
        return $this->viewname;
    }

    /**
     * Automagically build a Zend form specific to this model.
     * @param BaseObject $modelInstance
     * @param array $actionQuery
     * @return Silva_Form
     */
    protected function getSilvaForm($modelInstance, array $actionQuery = array())
    {
        $sf = new Silva_Form($this->tableMap, $this->backend);
        $sf->setAction(url('', $_GET)->add($actionQuery))->setAttrib('class', 'dialog-form');
        $sf->setUseDefaultLocaleOnEmptyValue($this->options['useDefaultLocaleOnEmptyValue']);
        $sf->setUseCustomFormElements($this->options['useCustomFormElements']);
        $sf->setIgnoreFks($this->options['ignoreForeignKeys']);
        $sf->setIgnorePks($this->options['ignorePrimaryKeys']);
        $sf->setLocale($this->locale);
        $sf->createElements();
        $sf->fillForm($modelInstance);
        return $sf;
    }

    protected function showLocaleForm()
    {
        if ($this->locale) {
            $localeForm = self::getLocaleForm($this->locale);
            $this->addMainContent($localeForm);
        }
    }

} //Silva_View_BaseModel