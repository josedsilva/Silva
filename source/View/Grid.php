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
 * @category	Curry
 * @package		Silva
 * @author		Jose Francisco D'Silva
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

	/**
	 * Default options
	 * @var array
	 */
	protected static $defaultOptions = array(
		// Csv button options
		'ImportCsvButton' => array(
			'caption' => 'Import',
			'bclass' => 'page_excel',
			'buttonOptions' => array(),
		),
		'ExportCsvButton' => array(
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
	);

	public function __construct($tableMap, $catRelationMap = null, Curry_Backend $backend, array $options = array())
	{
	    //trace(__METHOD__);
	    //trace("Grid default options");
	    //trace(self::$defaultOptions);
	    parent::__construct($tableMap, $catRelationMap, $backend);
	    $this->extendOptions(self::$defaultOptions);
	    //trace('Effective default options:');
	    //trace($this->options);
	    $this->extendOptions($options);
	    //trace('Effective options:');
	    //trace($this->options);
	    //trace($this->i18nTableMap);
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
	    trace(__METHOD__);
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
	    //trace("Has i18n behavior: " . $this->hasI18nBehavior());
	    if ($this->hasI18nBehavior()) {
	        $i18nProperties = $this->getI18nBehavior();
	        //trace($i18nProperties);
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

	    return $this->grid;
	}

	protected function getDefaultFilterQuery()
	{
	    //trace(__METHOD__);
	    if ($this->catRelationMap !== null) {
	        // define a simple filter for items in this category.
	        $query = PropelQuery::from($this->getTablename())
	            ->filterBy($this->getCategoryLocalReferencePhpName(), $_GET[$this->getCategoryForeignReferenceName()]);
	    }

	    //trace("Locale: " . $this->locale);
	    if ($this->locale) {
	        //trace("query:");
	        //trace($query);
        if (! $query) {
            // $query may not be defined if this model does not have a category.
            $query = PropelQuery::from($this->getTablename());
        }

	    	$query->joinWithI18n($this->locale);
	    	// Add i18n columns to the flexigrid
	    	$i18nProperties = $this->getI18nBehavior();
	    	$i18nColumns = array_map("trim", explode(',', $i18nProperties['i18n_columns']));
	    	//trace("i18nColumns:");
	    	//trace($i18nColumns);
	    	//trace($i18nProperties['i18n_columns']);
	    	foreach (self::getI18nColumns($this->tableMap) as $column) {
	    	    if ( in_array(strtolower($column->getName()), $i18nColumns) ) {
	    	        $query->withColumn("{$this->getI18nTablename()}.{$column->getPhpName()}", "I18n{$column->getPhpName()}");
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
	    if (empty($buttons)) {
	        return;
	    }

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
	private function getAddEditUrl()
	{
	    $editUrl = url('', array(
	        Silva_Backend::URL_QUERY_MODULE,
	        Silva_Backend::URL_QUERY_VIEW => $this->getTablename(),
	    ));

	    if ($this->catRelationMap !== null) {
	        $editUrl->add(array(
	            $this->getCategoryLocalReferenceName() => $_GET[$this->getCategoryForeignReferenceName()]
	        ));
	    }

	    if ($this->locale) {
	        $editUrl->add(array(Silva_Backend::URL_QUERY_LOCALE => $this->locale));
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
	        Silva_Backend::URL_QUERY_VIEW => "{$this->getTablename()}ExportCsv",
	    ));

	    if ($this->catRelationMap !== null) {
	        // FIXME verify whether $_GET[local or foreign rf. ?]
	        $catLocalRefName = $this->getCategoryLocalReferenceName();
	        $exportCsvUrl->add(array($catLocalRefName => $_GET[$catLocalRefName]));
	    }

	    $csvButton = $this->options['ExportCsvButton'];
	    $bclass = (strpos($csvButton['bclass'], 'icon_') === false) ? 'icon_' . $csvButton['bclass'] : $csvButton['bclass'];
	    $this->grid->addLinkButton($csvButton['caption'], $bclass, $exportCsvUrl, -1, (array) $csvButton['buttonOptions']);
	}

	protected function addImportCsvButton()
	{
	    $importCsvUrl = url('', array(
	        Silva_Backend::URL_QUERY_MODULE,
	        Silva_Backend::URL_QUERY_VIEW => "{$this->getTablename()}ImportCsv",
	    ));

	    if ($this->locale) {
	        $importCsvUrl->add(array(Silva_Backend::URL_QUERY_LOCALE => $this->locale));
	    }

	    if ($this->catRelationMap !== null) {
	        // FIXME verify whether $_GET[local or foreign rf. ?]
	        $catLocalRefName = $this->getCategoryLocalReferenceName();
	        $importCsvUrl->add(array($catLocalRefName => $_GET[$catLocalRefName]));
	    }

	    $csvButton = $this->options['ImportCsvButton'];
	    $bclass = (strpos($csvButton['bclass'], 'icon_') === false) ? 'icon_' . $csvButton['bclass'] : $csvButton['bclass'];
	    // NOTE: should not use a DialogButton because dialog-form cannot upload a file.
	    // Use Curry_Form::filebrowser instead of file
	    //$this->grid->addLinkButton($csvButton['caption'], $bclass, $importCsvUrl, -1, (array) $csvButton['buttonOptions']);
	    $this->grid->addDialogButton($csvButton['caption'], $bclass, "dialog_importcsv", "Import CSV for {$this->getTablename()}s", $importCsvUrl, array(), -1, true, (array) $csvButton['buttonOptions']);
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
	        $jsonHook = "show{$this->getTablename()}Json";
	        if (method_exists($this->backend, $jsonHook)) {
	            call_user_func(array($this->backend, $jsonHook));
	        } else {
	           if ($this->tableHasCompositePk()) {
	               $this->deleteTuplesHavingCompositeKey();
	           }
	        }

	        $this->backend->returnJson($this->grid->getJSON());
	    }

	    $this->showLocaleForm();
	    $this->showFilterForm();
	    $this->showDescription();
	    $this->addMainContent($this->grid->getHtml());
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
		$cbFunc = "export{$this->getTablename()}Csv";
		if (! method_exists($this->backend, $cbFunc)) {
		    throw new Silva_Exception("Callback ($cbFunc) not defined in " . get_class($this->backend));
		}

		$filename = $this->getTablename() . 's-' . date('Y-m-d-H-i-s') . '.csv';
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
		    $cbFunc = "import{$this->getTablename()}Csv";
		    if (! method_exists($this->backend, $cbFunc)) {
		        throw new Silva_Exception("Callback ($cbFunc) not defined in " . get_class($this->backend));
		    }

		    $filepath = Curry_Core::$config->curry->wwwPath . '/' . $values['csvfile'];
		    $ret = call_user_func(array($this->backend, $cbFunc), $filepath, $values['delimiter'], $values['enclosure'], $values['escape']);
		    unlink($filepath); // delete csv file after processing is completed
		    $this->backend->returnPartial($ret);
		}

		$this->backend->returnPartial($form);
	}

} //Silva_View_Grid