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
 * Abstract class for a Curry_Backend view.
 *
 * @category	Curry
 * @package		Silva
 * @author		Jose Francisco D'Silva
 * @version
 *
 */
abstract class Silva_View
{
    /**#@+
     * @category Button
     */

    /** Show button separator */
    const BUTTON_SEPARATOR = "button_separator";
    /** Show Add button */
    const BUTTON_ADD = "button_add";
    /** Show Edit button */
    const BUTTON_EDIT = "button_edit";
    /** Show Delete button */
    const BUTTON_DELETE = "button_delete";
    /** Show Add, Edit and Delete buttons.
     * Short-hand for BUTTON_ADD, BUTTON_EDIT and BUTTON_DELETE constants.
     */
    const BUTTON_AED = "button_aed";
    /** shorthand for BUTTON_AED, BUTTON_SEPARATOR */
    const BUTTON_AEDS = "button_aeds";
    /** Show Select records button */
    const BUTTON_TOGGLE_SELECT = "button_toggle_select";
    /** Show the Export to CSV button */
    const BUTTON_EXPORT_CSV = "button_export_csv";
    /** Show Import CSV button */
    const BUTTON_IMPORT_CSV = "button_import_csv";
    /**#@-*/

    /** The default button icon class */
    const BUTTON_BCLASS_DEFAULT = "icon_brick";

    /**#@+
     * @category Button type
     */

    /** Link button. Default button type if type is not specified */
    const BUTTON_TYPE_LINK = "button_type_link";

    /** Dialog button. Clicking this button results in a thickbox popup showing up */
    const BUTTON_TYPE_DIALOG = "button_type_dialog";

    /** Raw button. @see Curry_Flexigrid::addButton */
    const BUTTON_TYPE_RAW = "button_type_raw";

    /** Command button */
    const BUTTON_TYPE_COMMAND = "button_type_command";

    /** Action button */
    const BUTTON_TYPE_ACTION = "button_type_action";
    /**#@-*/

    /**#@+
     * @category Button mode
     */

    /** Allow a button to return status and show it in an alert box */
    const BUTTON_MODE_STATUS = "button_mode_status";
    /**#@-*/

    const JS_SUBMIT_FORM = 'this.form.submit();';

    /**#@+
     * @category Content type
     */
    const CONTENT_TEXT = "content_text";
    const CONTENT_HTML = "content_html";
    /**#@-*/

    const DEFAULT_CONTENT_TYPE = self::CONTENT_TEXT;

    /**
     * Breadcrumb text
     * @var string|null
     */
    protected $breadcrumbText = null;

    /**
     * View description
     * @var string|null
     */
    protected $description = null;

    /**
     * Content type of view description
     * @see Silva_Backend: Content type
     */
    protected $descContentType = null;

    /**
     * Curry_Backend instance
     * @var Curry_Backend
     */
    protected $backend = null;

    protected $viewname = null;

    protected static $defaultOptions = array();
    /**
     * Effective options
     * @var array
     */
    protected $options = array();

    protected $filterForm = null;

    /**
     * Instantiate the view
     * @param Curry_Backend $backend
     */
    public function __construct(Curry_Backend $backend)
    {
        $this->extendOptions(self::$defaultOptions);
        $this->backend = $backend;
    }

    /**
     * Helper method to extend View options.
     * @param array $extenderOptions
     */
    protected function extendOptions(array $extenderOptions)
    {
        $this->options = Curry_Array::extend($this->options, $extenderOptions);
    }

    /**
     * Render the view.
     */
    abstract public function render();

    /**
     * Return the descriptive locale name from the Curry CMS translation table.
     * @example $locale=="sv_SE", return: "Svenska"
     *
     * @param string $locale
     * @return string
     */
    public static function getLanguageString($locale)
    {
    	return LanguageQuery::create()
    		->findPk($locale)
    		->getName();
    }

    /**
    * Return a filter form without form elements.
    * @param array $formElements
    * @param string $class
    * @param string $title
    * @return Curry_Form
    */
    public static function getFilterForm(array $formElements, $class = 'filters', $title = 'Filters')
    {
        $form = self::getPartialForm(array(), 'get', $class);
        $form->setAttrib('title', $title);
        $form->setElements($formElements);

        foreach ($_GET as $k => $v) {
            if (! $form->getElement($k)) {
                $form->addElement('hidden', $k, array('value' => $v));
            }
        }

        return $form;
    }

    public function setFilterForm(Curry_Form $filterForm)
    {
        $this->filterForm = $filterForm;
    }

    protected function showFilterForm()
    {
        if ($this->filterForm) {
            $this->addMainContent($this->filterForm);
        }
    }

    /**
     * Return the locale selector
     * @param string $locale
     * @return Curry_Form
     */
    public static function getLocaleForm($locale)
    {
    	return self::getFilterForm(array(
    	    Silva_Backend::URL_QUERY_LOCALE => array('select', array(
            'label' => 'Language',
            'multiOptions' => LanguageQuery::create()
            	->find()
            	->toKeyValue('PrimaryKey', 'Name'),
            'value' => $locale,
            'onchange' => 'this.form.submit();',
    		  )),
    	), 'locale-selector', 'Locale selector');
    }

    /**
     * Return the HTML snippet to embed a progress-bar widget.
     * @param string $url
     * @return string
     */
    public static function getProgressbarHtml($url)
    {
        $js =<<<JS
$(function() {
	$("#progressbar").progressbar({ value: 0 });

	var sendmail = function(data, textStatus) {
		$("#progressbar").progressbar('value', data.status);

		$('#mail-status').html('Sending emails, ' + data.eta + ' seconds remaining...<br />Sent: ' + data.sent + '<br/>Failed: ' + data.failed);

		if(data.url) {
			$.get(data.url, null, sendmail, 'json');
		} else {
			if(data.status == 100) {
				$('#mail-status').html('All done!<br />Sent: ' + data.sent + '<br/>Failed: ' + data.failed);
			} else {
				$('#mail-status').append('<br />Error');
			}
		}
	};
	$.get("$url", null, sendmail, 'json');

});
JS;

	    $html =<<<HTML
<p><div id="progressbar"></div></p>
<br/>
<p id="mail-status">
	Please wait, preparing...
</p>
HTML;

	    Curry_Admin::getInstance()->getHtmlHead()->addInlineScript($js);
	    return $html;
    }

    public function setViewname($viewname)
    {
        $this->viewname = $viewname;
    }

    public function getViewname()
    {
        return $this->viewname;
    }

    public function setBreadcrumbText($text)
    {
        $this->breadcrumbText = $text;
    }

    public function getBreadcrumbText()
    {
        if ($this->breadcrumbText === null) {
            $this->breadcrumbText = $this->viewname;
        }

        return $this->breadcrumbText;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($text, $contentType = self::DEFAULT_CONTENT_TYPE)
    {
        $this->description = $text;
        $this->descContentType = $contentType;
    }

    protected function showDescription()
    {
        if (! $this->description) {
            return;
        }

        if ($this->descContentType === self::CONTENT_TEXT) {
            $this->addMessage($this->description, "");
        } elseif ($this->descContentType === self::CONTENT_HTML) {
            $this->addMainContent($this->description);
        }
    }

    /**
     * Wrapper for Curry_Backend::addMessage
     * @param string $text
     * @param $class @see Curry_Backend::MSG_*
     * @param boolean $escape
     */
    protected function addMessage($text, $class = Curry_Backend::MSG_NOTICE, $escape = true)
    {
        $this->backend->addMessage($text, $class, $escape);
    }

    /**
     * Wrapper for Curry_Backend::addMainContent
     * @param mixed $content
     */
    protected function addMainContent($content)
    {
        $this->backend->addMainContent($content);
    }

    /**
     * Whether this view is associated with a table/model.
     * @return boolean
     */
    abstract public function hasTable();

    /**
     * Return a partially constructed Curry_Form.
     *
     * @param array $actionQuery An array of url query parameters for the form's 'action' attribute.
     * @param string $method 'get' or 'post'
     * @param string $class A css class for the form (Determine's the type of form in Curry, i.e. dialog or normal)
     * @return Curry_Form
     */
    protected static function getPartialForm(array $actionQuery = array(), $method = 'post', $class = 'dialog-form')
    {
        $form = new Curry_Form(array(
            'action' => url('', $_GET)->add($actionQuery),
            'method' => $method,
            'class' => $class,
        ));

        return $form;
    }

    /**
     * Return the CSV import form.
     * (this form is designed to show in a dialog box)
     * @return Curry_Form
     */
    protected static function getImportCsvForm()
    {
        return new Curry_Form(array(
            'action' => url('', $_GET),
            'method' => 'post',
            'enctype' => 'application/x-www-form-urlencoded',
            'class' => 'dialog-form',
            'elements' => array(
                'csvfile' => array('filebrowser', array(
                    'label' => 'CSV file',
                    'description' => 'A file with comma separated values (name,email).',
                    //'destination' => Curry_Core::$config->curry->projectPath . '/data/temp/',
                    'required' => true,
                )),
                'delimiter' => array('text', array(
                    'label' => 'Delimiter',
                    'description' => 'Delimiter used for separating fields. The default is comma (,) but in some cases semicolon (;) is used.',
                    'value' => ',',
                    'required' => true,
                )),
                'enclosure' => array('text', array(
                    'label' => 'Enclosure',
                    'description' => 'Character used for string enclosure.',
                    'value' => '"',
                    'required' => true,
                )),
                'escape' => array('text', array(
                    'label' => 'Escape',
                    'description' => 'Character used for escaping.',
                    'value' => '\\',
                    'required' => true,
                )),
                'import' => array('submit', array('label' => 'Import')),
            ),
        ));
    }


} // Silva_View
