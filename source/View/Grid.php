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
 * Define a grid view showing a collection of tuples
 *
 * @category    Curry CMS
 * @package     Silva
 * @author      Jose Francisco D'Silva
 * @version
 *
 */
class Silva_View_Grid extends Silva_View_BaseModel
{

    /**
     * The grid object
     * @var Silva_Grid|null
     */
    protected $grid = null;
    
    protected $filterForm = null;
    
    /**
     * Map of filter form elements versus filter collection
     * @var array
     */
    protected $autoFilterMap = null;
    /**
     * auto filter data map
     * @var array
     */
    protected $autoFilterData = null;

    /**
     * Default options
     * @var array
     */
    private $defaultOptions = array(
        // Csv button options
        'ImportCsvButton' => array(
            'caption' => 'Import',
            'bclass' => 'page_excel',
            'buttonOptions' => array(),
        ),
        'ExportCsvButton' => array(
            'mode' => null,
            'caption' => 'Export',
            'bclass' => 'page_excel',
            'buttonOptions' => array(),
        ),
        'DeleteButton' => array(
            'mode' => null,
            'buttonOptions' => array(),
        ),
        'EditButton' => array(
            'mode' => null,
            'dialogOptions' => array(),
            'buttonOptions' => array(),
        ),
        'AddButton' => array(
            'mode' => null,
            'dialogOptions' => array(),
            'buttonOptions' => array(),
        ),
        'ToggleSelectButton' => array(
            'caption' => 'Toggle select',
            'bclass' => 'tick',
            'buttonOptions' => array(),
        ),
        // flexigrid-specific options. @see Curry_Flexigrid.
        'gridOptions' => array(),
        // whether to tidy the grid in this view?
        'tidyGrid' => false,
    );

    public function __construct($tableMap, $catRelationMap = null, Curry_Backend $backend, array $options = array())
    {
    	Curry_Array::extend($this->defaultOptions, $options);
        parent::__construct($tableMap, $catRelationMap, $backend, $this->defaultOptions);
    }

    /**
     * Return the Silva_Grid object for the grid view
     * @param array|null $buttons Array of buttons to place on the grid.
     * @param array|null $options Array of Grid options
     * @param Model_Criteria|null $query Custom query
     * @param integer|null $id @see Curry_Flexigrid
     * @param string|null $title Title of the flexigrid
     */
    public function &getGrid($buttons = null, $options = null, $query = null, $id = null, $title = null)
    {
        // do not recreate the grid if already defined
        if ( $this->isGridDefined() && (func_num_args() === 0) ) {
            return $this->grid;
        }

        // setting $buttons to null generates default buttons (Add-Edit-Delete)
        if ($buttons === null) {
            $buttons = array(self::BUTTON_AED);
        }

        if ($options !== null) {
            $this->extendOptions($options);
        }

        // check whether this model has i18n behavior and initialize the locale.
        if ($this->hasI18nBehavior()) {
            $i18nProperties = $this->getI18nBehavior();
            $this->locale = isset($_GET[Silva_Backend::URL_QUERY_LOCALE]) ? $_GET[Silva_Backend::URL_QUERY_LOCALE] : $i18nProperties['default_locale'];
        }

        // define a simple filter query if not already defined.
        // NOTE: user-defined query overrides this query.
        if ($query === null) {
            $query = $this->getDefaultFilterQuery();
        }

        $url = url('', $_GET)->add(array('json' => 1));
        $this->grid = new Silva_Grid($this->tableMap, $url, (array) $this->options['gridOptions'], $query, $id, $title);

        // check whether this model has composite primary keys
        if ($this->tableHasCompositePk()) {
            // Flexigrid does not support composite primary keys, therefore we need to serialize it.
            $pk = $this->getPkName();
            $pkDisplay = $this->getPkPhpName();
            $this->grid->addColumn($pk, $pkDisplay, array('hide' => true, 'escape' => false));
            $this->grid->setColumnCallback($pk, create_function('$o', 'return serialize($o->getPrimaryKey());'));
            $this->grid->setPrimaryKey($pk);
        }

        // Buttons must be affixed to the flexigrid only after the flexigrid setup is completed.
        $this->addButtons($buttons);
        
        if ($this->options['tidyGrid']) {
            $this->tidyGrid();
            // execute the method once only
            $this->options['tidyGrid'] = false;
        }
        
        return $this->grid;
    }

    protected function getDefaultFilterQuery()
    {
        if ($this->catRelationMap !== null) {
            // define a simple filter for items in this category.
            $query = PropelQuery::from($this->getTablename())
                ->filterBy($this->getCategoryLocalReferencePhpName(), $_GET[$this->getCategoryForeignReferenceName()]);
        }

        if ($this->locale) {
            if (! $query) {
                // $query may not be defined if this model does not have a category.
                $query = PropelQuery::from($this->getTablename());
            }

            $query->joinWithI18n($this->locale);
            // Add i18n columns to the flexigrid
            $i18nProperties = $this->getI18nBehavior();
            $i18nColumns = array_map("trim", explode(',', $i18nProperties['i18n_columns']));
            foreach (Silva_Propel::getI18nColumns($this->tableMap) as $column) {
                if (in_array(strtolower($column->getName()), $i18nColumns)) {
                    $query->withColumn("{$this->getI18nTablename()}.{$column->getPhpName()}", "I18n{$column->getPhpName()}");
                }
            }
        }
        
        // setup auto filters
        if ($this->autoFilterMap !== null) {
            if (! $query) {
                // $query may not be defined if this model does not have a category.
                $query = PropelQuery::from($this->getTablename());
            }
            
            $this->autoFilterData = array();
            foreach ($this->autoFilterMap as $element => $val) {
                $this->autoFilterData[$element] = isset($_GET[$element]) ? $_GET[$element] : 0;
                if ($this->autoFilterData[$element]) {
                    $query->filterBy($this->tableMap->getColumn($element)->getPhpName(), $this->autoFilterData[$element]);
                }
            }
        }

        return $query;
    }

    /**
     * Affix buttons to the Grid
     * @param array $buttons
     */
    protected function addButtons(array $buttons)
    {
        foreach ($buttons as $button) {
            switch ($button) {
                case self::BUTTON_SEPARATOR:
                    $this->grid->addSeparator();
                    break;
                case self::BUTTON_ADD:
                    $this->addAddButton();
                    break;
                case self::BUTTON_EDIT:
                    $this->addEditButton();
                    break;
                case self::BUTTON_DELETE:
                    $this->addDeleteButton($this->options['DeleteButton']['mode']);
                    break;
                case self::BUTTON_AED:
                    $this->addAddButton();
                    $this->addEditButton();
                    $this->addDeleteButton($this->options['DeleteButton']['mode']);
                    break;
                case self::BUTTON_AEDS:
                    $this->addAddButton();
                    $this->addEditButton();
                    $this->addDeleteButton($this->options['DeleteButton']['mode']);
                    $this->grid->addSeparator();
                    break;
                case self::BUTTON_EXPORT_CSV:
                    $this->addExportCsvButton();
                    break;
                case self::BUTTON_IMPORT_CSV:
                    $this->addImportCsvButton();
                    break;
                case self::BUTTON_TOGGLE_SELECT:
                    $this->addToggleSelectButton();
                    break;
                default:
                    $this->addUserDefinedButton($button);
                    break;
            }
        }
    }

    /**
     * Return the url for the Add/Edit button.
     * @return Curry_URL
     */
    protected function getAddEditUrl()
    {
        $editUrl = url('', array(
            Silva_Backend::URL_QUERY_MODULE,
            Silva_Backend::URL_QUERY_VIEW => $this->getTableAlias(),
        ));

        if ($this->catRelationMap !== null) {
            $editUrl->add(array(
                $this->getCategoryLocalReferenceName() => $_GET[$this->getCategoryForeignReferenceName()]
            ));
        }

        if ($this->locale) {
            $editUrl->add(array(Silva_Backend::URL_QUERY_LOCALE => $this->locale));
        }
        
        // merge user-specific url params
        if (isset($this->options['urlParams'])) {
            $editUrl->add((array) $this->options['urlParams']);
        }
        
        return $editUrl;
    }

    protected function addAddButton($mode = null)
    {
        $this->grid->addAddButton($this->getAddEditUrl(), $this->options['AddButton']['dialogOptions'], $this->options['AddButton']['buttonOptions']);
    }

    protected function addEditButton($mode = null)
    {
        $this->grid->addEditButton($this->getAddEditUrl(), $this->options['EditButton']['dialogOptions'], $this->options['EditButton']['buttonOptions']);
    }

    protected function addDeleteButton($showStatus = false)
    {
        if ($showStatus) {
            $this->grid->addDeleteStatusButton($this->options['DeleteButton']['buttonOptions']);
        } else {
            $this->grid->addDeleteButton($this->options['DeleteButton']['buttonOptions']);
        }
    }

    protected function addExportCsvButton()
    {
        $exportCsvUrl = url('', array(
            Silva_Backend::URL_QUERY_MODULE,
            Silva_Backend::URL_QUERY_VIEW => "{$this->getTableAlias()}ExportCsv",
            'nostck' => 1, // do not push this view onto the stack
        ));

        if ($this->catRelationMap !== null) {
            // FIXME verify whether to use $_GET[local cat. rf. or foreign cat. rf. ?]
            $exportCsvUrl->add(array(
                $this->getCategoryLocalReferenceName() => $_GET[$this->getCategoryForeignReferenceName()]
            ));
        }
        
        if ($this->locale) {
            $exportCsvUrl->add(array(Silva_Backend::URL_QUERY_LOCALE => $this->locale));
        }
        
        // merge user-specific url params
        if (isset($this->options['urlParams'])) {
            $exportCsvUrl->add((array) $this->options['urlParams']);
        }
        
        $csvButton = $this->options['ExportCsvButton'];
        if ($csvButton['mode'] === self::BUTTON_MODE_SYSTEM) {
            $exportCsvUrl->add(array('mode' => self::BUTTON_MODE_SYSTEM));
            $this->grid->addExportExcelButton($exportCsvUrl);
        } else {
            $bclass = (strpos($csvButton['bclass'], 'icon_') === false) ? 'icon_' . $csvButton['bclass'] : $csvButton['bclass'];
            $this->grid->addLinkButton($csvButton['caption'], $bclass, $exportCsvUrl, -1, (array) $csvButton['buttonOptions']);
        }
    }

    protected function addImportCsvButton()
    {
        $importCsvUrl = url('', array(
            Silva_Backend::URL_QUERY_MODULE,
            Silva_Backend::URL_QUERY_VIEW => "{$this->getTableAlias()}ImportCsv",
        ));

        if ($this->locale) {
            $importCsvUrl->add(array(Silva_Backend::URL_QUERY_LOCALE => $this->locale));
        }

        if ($this->catRelationMap !== null) {
            // FIXME verify whether $_GET[local or foreign rf. ?]
            $catLocalRefName = $this->getCategoryLocalReferenceName();
            $importCsvUrl->add(array($catLocalRefName => $_GET[$catLocalRefName]));
        }
        
        // merge user-specific url params
        if (isset($this->options['urlParams'])) {
            $importCsvUrl->add((array) $this->options['urlParams']);
        }
        
        $csvButton = $this->options['ImportCsvButton'];
        $bclass = (strpos($csvButton['bclass'], 'icon_') === false) ? 'icon_' . $csvButton['bclass'] : $csvButton['bclass'];
        // NOTE: should not use a DialogButton because dialog-form cannot upload a file.
        // Use Curry_Form::filebrowser instead of file
        //$this->grid->addLinkButton($csvButton['caption'], $bclass, $importCsvUrl, -1, (array) $csvButton['buttonOptions']);
        $this->grid->addDialogButton($csvButton['caption'], $bclass, "dialog_importcsv", "Import CSV for {$this->getTableAlias()}s", $importCsvUrl, array(), -1, true, (array) $csvButton['buttonOptions']);
    }

    protected function addToggleSelectButton()
    {
        $toggleSelectButton = $this->options['ToggleSelectButton'];
        $bclass = (strpos($toggleSelectButton['bclass'], 'icon_') === false) ? 'icon_' . $toggleSelectButton['bclass'] : $toggleSelectButton['bclass'];
        $this->grid->addToggleSelectButton($toggleSelectButton['caption'], array_merge($toggleSelectButton, array(
            'bclass' => $bclass,
        )));
    }

    protected function addUserDefinedButton($button)
    {
        if ($this->locale) {
            $button['url']->add(array(Silva_Backend::URL_QUERY_LOCALE => $this->locale));
        }

        $buttonType = isset($button['buttonType']) ? $button['buttonType'] : self::BUTTON_TYPE_LINK;
        $bclass = isset($button['bclass']) ? (strpos($button['bclass'], 'icon_') === false ? "icon_{$button['bclass']}" : $button['bclass']) : self::BUTTON_BCLASS_DEFAULT;

        switch ($buttonType) {
            case self::BUTTON_TYPE_LINK:
                $this->grid->addLinkButton(
                    $button['caption'],
                    $bclass,
                    $button['url'],
                    (isset($button['forcePrimaryKey']) ? $button['forcePrimaryKey'] : 1),
                    (isset($button['buttonOptions']) ? $button['buttonOptions'] : array())
                );
                break;
            case self::BUTTON_TYPE_DIALOG:
                $this->grid->addDialogButton(
                    $button['caption'],
                    $bclass,
                    (isset($button['dialogId']) ? $button['dialogId'] : uniqid('dialog_')),
                    (isset($button['dialogTitle']) ? $button['dialogTitle'] : $button['caption']),
                    $button['url'],
                    (isset($button['dialogOptions']) ? $button['dialogOptions'] : array()),
                    (isset($button['forcePrimaryKey']) ? $button['forcePrimaryKey'] : 1),
                    (isset($button['reloadOnClose']) ? $button['reloadOnClose'] : true),
                    (isset($button['buttonOptions']) ? $button['buttonOptions'] : array())
                );
                break;
            case self::BUTTON_TYPE_RAW: // raw button
                $this->grid->addButton(
                    $button['caption'],
                    array_merge(
                        array('bclass' => $bclass),
                        (isset($button['buttonOptions']) ? $button['buttonOptions'] : array())
                    )
                );
                break;
            case self::BUTTON_TYPE_ACTION: // action button
                $this->grid->addActionButton(
                    $button['caption'],
                    $bclass,
                    $button['url'],
                    (isset($button['buttonOptions']) ? $button['buttonOptions'] : array())
                );
                break;
            case self::BUTTON_TYPE_COMMAND: //command button
                $this->grid->addCommandButton(
                    $button['caption'],
                    $bclass,
                    $button['cmd'],
                    (isset($button['forcePrimaryKey']) ? $button['forcePrimaryKey'] : 1),
                    (isset($button['buttonOptions']) ? $button['buttonOptions'] : array())
                );
                break;
        }
    }

    public function render()
    {
        if (! $this->isGridDefined()) {
            $grid = $this->getGrid();
        }
        
        if (isset($_GET['json'])) {
            $jsonHook = Silva_Hook::getHookPattern(Silva_Hook::HOOK_ON_JSON, '%TABLENAME%', $this->getTableAlias());
            if (method_exists($this->backend, $jsonHook)) {
//                 call_user_func(array($this->backend, $jsonHook));
                Silva_Hook::execHook(array($this->backend, $jsonHook));
            } else {
               if ($this->tableHasCompositePk()) {
                   $this->deleteTuplesHavingCompositeKey();
               }
            }

            Curry_Application::returnJson($this->grid->getJSON());
        }

        $this->showLocaleForm();
        $this->showFilterForm();
        $this->showDescription();
        $this->addMainContent($this->grid->getHtml());
    }
    
    /**
     * Hide unnecessary fields in this grid.
     * The grid shall be defined before the method is called.
     */
    protected function tidyGrid()
    {
        if (! $this->isGridDefined() || ! $this->options['tidyGrid']) {
            return;
        }
        
        // hide foreign-key fields
        foreach ($this->tableMap->getRelations() as $relMap) {
            // ignore I18n relations
            if (strrpos($relMap->getName(), 'I18n') !== false) {
                trace_notice(__METHOD__.': Skipped I18n relation: '.$relMap->getName());
                continue;
            }
            // ignore ONE_TO_MANY relations
            if ($relMap->getType() === RelationMap::ONE_TO_MANY) {
                trace_notice(__METHOD__.': Skipped ONE_TO_MANY relation: '.$relMap->getName());
                continue;
            }
            
            $colMaps = $relMap->getLocalColumns();
            if (is_array($colMaps) && !empty($colMaps)) {
                $cm = array_shift($colMaps);
                $this->grid->setColumnOption(strtolower($cm->getName()), 'hide', true);
                if ( (null !== $this->catRelationMap) && ($this->getCategoryLocalReferencePhpName() == $cm->getPhpName()) ) {
                    trace_notice(__METHOD__.': Skipped category column: '.$this->getCategoryLocalReferenceName());
                    continue;
                }
                
                $newCol = strtolower($relMap->getForeignTable()->getName());
                $this->grid->addColumn($newCol, $relMap->getName(), array('sortable' => false));
                $fn = 'return $o->get'.$relMap->getName().'();';
                $this->grid->setColumnCallback($newCol, create_function('$o', $fn));
            }
        }
        
        // hide slug column
        $sluggable = Silva_Propel::getBehavior('sluggable', $this->tableMap);
        if (! empty($sluggable)) {
            trace_notice(__METHOD__.': Slug column hidden');
            $this->grid->setColumnOption($sluggable['slug_column'], 'hide', true);
        }
    }

    /**
     * Curry Json hook too delete tuples having composite primary keys.
     * The method deletes one or more tuples selected in the flexigrid.
     */
    protected function deleteTuplesHavingCompositeKey()
    {
        if ( isset($_POST) && ($_POST['cmd'] == 'delete') ) {
            $ids = $_POST['id']; // array of serialized keys
            if (count($ids) == 1) {
                $compositePk = unserialize($ids[0]);
                PropelQuery::from($this->getTablename())
                    ->findPk($compositePk)
                    ->delete();
            } else {
                $compositePks = array();
                foreach ($ids as $id) {
                    $compositePks[] = unserialize($id);
                }
                PropelQuery::from($this->getTablename())
                    ->findPks($compositePks)
                    ->delete();
            }
            $_POST['cmd'] = '';
        }
    }

    public function isGridDefined()
    {
        return (boolean) ($this->grid !== null);
    }

    /**
     * Handle Csv export.
     * @return text/csv
     */
    public function exportCsv()
    {
        $cbFunc = Silva_Hook::getHookPattern(Silva_Hook::HOOK_ON_EXPORT_CSV, '%TABLENAME%', $this->getTableAlias());
        if (! method_exists($this->backend, $cbFunc)) {
            throw new Silva_Exception("Callback ($cbFunc) not defined in " . get_class($this->backend));
        }

        $filename = $this->getTableAlias() . 's-' . date('Y-m-d-H-i-s') . '.csv';
        $delimiter = ',';
        $enclosure = '"';
        $escape = '\\';
        $list = (array) call_user_func_array(array($this->backend, $cbFunc), array(&$filename, &$delimiter, &$enclosure, &$escape));
        self::_exportCsv($list, $filename, $delimiter, $enclosure, $escape);
    }

    private static function _exportCsv(array $list, $filename, $delimiter, $enclosure, $escape)
    {
        ob_start();
        $ostream = fopen("php://output", "w");
        foreach ($list as $row) {
            fputcsv($ostream, (array) $row, $delimiter, $enclosure);
        }
        fclose($ostream);
        $data = ob_get_clean();
        self::_sendCsvHeaders($filename);
        echo $data;
        exit;
    }

    private static function _sendCsvHeaders($filename)
    {
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=" . Curry_String::escapeQuotedString($filename));
    }

    /**
     * Show the import CSV form in a dialog box.
     * The CSV file is uploaded with the filebrowser plugin.
     * Once uploaded,a user-defined Csv handler is called.
     * The CSV handler must return "" (empty string) to close the dialog box.
     */
    public function importCsv()
    {
        $form = self::getImportCsvForm();
        if (isPost() && $form->isValid($_POST)) {
            $values = $form->getValues();
            $cbFunc = Silva_Hook::getHookPattern(Silva_Hook::HOOK_ON_IMPORT_CSV, '%TABLENAME%', $this->getTableAlias());
            if (! method_exists($this->backend, $cbFunc)) {
                throw new Silva_Exception("Callback ($cbFunc) not defined in " . get_class($this->backend));
            }

            $filepath = Curry_Core::$config->curry->wwwPath . '/' . $values['csvfile'];
            $ret = call_user_func(array($this->backend, $cbFunc), $filepath, $values['delimiter'], $values['enclosure'], $values['escape']);
            unlink($filepath); // delete csv file after processing is completed
            Curry_Application::returnPartial($ret);
        }

        Curry_Application::returnPartial($form);
    }
    
    /**
     * Setup auto filters for this view.
     * @param array|null $filterMap
     */
    public function setAutoFilters($filterMap = null)
    {
        $filterMap = ($filterMap === null) ? $this->prepareAutoFilters() : $this->formatAutoFilters($filterMap);
        $this->autoFilterMap = $filterMap;
        return $this;
    }
    
    protected function prepareAutoFilters()
    {
        $filters = array();
        foreach ($this->tableMap->getRelations() as $relMap) {
            // skip category relation since this view is already filtered by category
            if ($this->catRelationMap !== null && $this->catRelationMap === $relMap) {
                continue;
            }
            
            if ($relMap->getType() !== RelationMap::MANY_TO_ONE && $relMap->getType() !== RelationMap::MANY_TO_MANY) {
                continue;
            }
            
            $localRefs = $relMap->getLocalColumns();
            $localRef = $localRefs[0];
            $filters[strtolower($localRef->getName())] = array(Silva_Form::getMultiOptionsForFk($localRef, $this->locale, false), "Filter by {$relMap->getName()}", "[ All {$relMap->getName()}s ]");
        }
        
        if (empty($filters)) {
            return null;
        }
        
        return $filters;
    }
    
    protected function formatAutoFilters(array $filterMap)
    {
        $filters = array();
        foreach ($filterMap as $k => $v) {
            $multiOptions = null;
            $label = null;
            $nullOptionText = null;
            if (is_int($k)) {
                $col_name = $v;
            } else {
                $col_name = $k;
                if (is_array($v[0])) {
                    $multiOptions = $v[0];
                } else {
                    $label = $v[0];
                }
                
                if (isset($v[1])) {
                    if (is_array($v[0])) {
                        $label = $v[1];
                    } else {
                        $nullOptionText = $v[1];
                    }
                }
                
                if (isset($v[2])) {
                    $nullOptionText = $v[2];
                }
            }
            
            if ($multiOptions === null) {
                $colMap = $this->tableMap->getColumn($col_name);
                $relMap = $colMap->getRelation();
                $multiOptions = Silva_Form::getMultiOptionsForFk($colMap, $this->locale, false);
            }
            
            if ($label === null) {
                $colMap = $this->tableMap->getColumn($col_name);
                $relMap = $colMap->getRelation();
                $label = "Filter by {$relMap->getName()}";
            }
            
            $filters[$col_name] = array($multiOptions, $label, $nullOptionText);
        }
        
        return $filters;
    }
    
    /**
     * Attach a filter form to the view.
     * @param Curry_Form $filterForm
     */
    public function setFilterForm(Curry_Form $filterForm)
    {
        $this->filterForm = $filterForm;
        return $this;
    }

    protected function showFilterForm()
    {
        if ($this->autoFilterMap !== null) {
            $this->setAutoFilterForm();
        }
        
        if ($this->filterForm) {
            $this->addMainContent($this->filterForm);
        }
    }
    
    protected function setAutoFilterForm()
    {
        $elements = array();
        foreach ($this->autoFilterMap as $element => $val) {
            $elements[$element] = array('select', array(
                'label' => $val[1],
                'multiOptions' => array(0 => isset($val[2]) ? $val[2] : '[ No filter ]') + $val[0],
                'value' => $this->autoFilterData[$element],
                'onchange' => self::JS_SUBMIT_FORM,
            ));
        }
        
        return $this->setFilterForm(self::getFilterForm($elements));
    }
    
    /**
     * Return the active category model object for the view.
     * @return BaseObject|null
     */
    public function getActiveCategoryObject()
    {
        return ($this->catRelationMap !== null) ? PropelQuery::from($this->getCategoryTablename())->findPk($_GET[$this->getCategoryForeignReferenceName()]) : null;
    }
    
    protected function showDescription()
    {
        if (isset($this->options['description'])) {
            $rawText = $this->options['description'];
            $description = str_replace(array(
                '%TABLE_NAME%', '%TABLE_ALIAS%', '%BREADCRUMB_TEXT%', '%ACTIVE_CATEGORY_OBJECT%',
            ), array(
                $this->getTablename(), $this->getTableAlias(), $this->getBreadcrumbText(), $this->getActiveCategoryObject(),
            ), $rawText);
            unset($this->options['description']);
            if (isset($this->options['descContentType'])) {
                $descContentType = $this->options['descContentType'];
                unset($this->options['descContentType']);
            } else {
                $descContentType = self::DEFAULT_CONTENT_TYPE;
            }
            
            $this->setDescription($description, $descContentType);
        }
        
        parent::showDescription();
    }

} //Silva_View_Grid
