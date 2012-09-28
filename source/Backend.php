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
 * Base class for Curry_Backend modules using the event driven interface.
 *
 * @category    Curry
 * @package     Silva
 * @author      Jose Francisco D'Silva
 * @version
 *
 */
abstract class Silva_Backend extends Curry_Backend
{
    /**#@+
     * @category Url query string parameter constant
     */
    const URL_QUERY_MODULE = 'module';
    const URL_QUERY_VIEW = 'view';
    const URL_QUERY_LOCALE = 'locale';
    /**#@-*/

    /**
     * Map of viewnames and corresponding view objects
     * @var array
     */
    protected $viewMap = array();

    /**
     * Map of model class v/s view objects
     * @var array
     */
    protected $tableViewMap = array();

    /**
     * Default options
     * @var array
     */
    protected static $defaultOptions = array(
        // whether to automagically show breadcrumbs in default view handlers
        'showBreadcrumbs' => true,
    );

    /**
     * Effective options
     * @var array
     */
    protected $options = array();

    /**
     * Zend_Session_Namespace object for this module
     * @var Zend_Session_Namespace|null
     */
    private $_session = null;


    public function __construct(array $options = array())
    {
        parent::__construct();
        $this->options = self::$defaultOptions;
        $this->options = Curry_Array::extend($this->options, $options);
        $this->embedCSS();
        $this->embedJS();
    }

    /**
     * Default module group.
     */
    public static function getGroup()
    {
        return "Project";
    }

    /**
     * Set the view collection.
     * @param array $views An indexed array of Silva_View objects
     */
    public function setViews(array $views)
    {
        foreach ($views as $index => $view) {
            if ($index == 0) {
                $viewname = 'Main';
                $view->setViewname($viewname);
            } else {
                $viewname = $view->getViewname();
            }
            
            $this->setView($viewname, $view);
        }
    }
    
    /**
     * Set an individual view.
     * @param string $viewname
     * @param Silva_View $view
     */
    public function setView($viewname, $view)
    {
        $this->viewMap[$viewname] = $view;
        if ($view->hasTable()) {
            $this->tableViewMap[$view->getTablename()] = $view;
        }
    }

    /**
     * Return the View object corresponding to the tablename or viewname.
     * @param string $modelOrView: Model classname (aka Tablename) or view name
     * @return Silva_View|null
     */
    public function getView($modelOrView)
    {
        if ($modelOrView == 'Main') {
            return $this->getFirstView();
        }

        // is argument a view name?
        if ( in_array($modelOrView, array_keys($this->viewMap)) ) {
            return $this->viewMap[$modelOrView];
        }

        // is argument a tablename?
        if ( in_array($modelOrView, array_keys($this->tableViewMap)) ) {
            return $this->tableViewMap[$modelOrView];
        }

        // not found
        return null;
    }

    /**
     * Return the view object that was the first to be registered.
     * This view object corresponds to the 'Main' view.
     * (this method will also reset the internal array pointer)
     * @return Silva_View
     */
    public function getFirstView()
    {
        return reset($this->viewMap);
    }
    
    /**
     * Return the active view object.
     * @return Silva_View
     */
    public function getActiveView()
    {
        return $this->getViewByName($this->getActiveViewname());
    }

    /**
     * Return the View object from the collection for $tablename
     * @param string $tablename
     * @return Silva_View|null;
     */
    public function getViewByTable($tablename)
    {
        return $this->tableViewMap[$tablename];
    }

    /**
     * Return the View object from the collection by the view name.
     * The view name pattern must have the following format: 'Main' + ModelClass + 's'
     * @param string $viewname
     * @return Silva_View|null
     */
    public function getViewByName($viewname)
    {
        return $this->viewMap[$viewname];
    }

    /**
     * Add breadcrumbs to the current view
     * @param string $viewname
     * @param Silva_View|null $view
     */
    public function showBreadcrumbs($viewname = null, $view = null)
    {
        if (! $this->options['showBreadcrumbs']) {
            return;
        }
        
        if ($viewname && $view instanceof Silva_View) {
            $this->setView($viewname, $view);
            $this->pushView($viewname);
        }
        
        $viewStack = $this->getViewStack();
        foreach ($viewStack as $viewname => $url) {
            $sv = $this->getViewByName($viewname);
            $this->addTrace($sv->getBreadcrumbText(), $url);
        }
    }

    /**
     * Default "Main" view handler.
     * Show the first registered view.
     *
     * NOTE: You may need to override this method in the subclass.
     */
    public function showMain()
    {
        $this->defaultViewHandler();
    }

    // extend method
    public function preShow()
    {
        if (empty($this->viewMap)) {
            throw new Silva_Exception("View collection not set. Please ensure whether " . __CLASS__ . "::setViews is called in the constructor.");
        }

        parent::preShow();
    }

    public function postShow()
    {
        parent::postShow();
    }

    /**
     * Curry_Backend view handler.
     * Backend view is rendered according to the following rules:
     * 1. If the view handler is defined in the subclass, execute that method.
     * 2. Attempt to execute a default View handler (each functionality like edit, export, import has a default view handler)
     * 3. If no suitable view handler is found then throw an exception.
     */
    public function show()
    {
        try {
            $this->preShow();
            $viewname = $this->getActiveViewname();
            $viewHandler = "show{$viewname}";
            if ($this->options['showBreadcrumbs']) {
                $this->pushView($viewname);
            }

            if (method_exists($this, $viewHandler)) {
                // user-defined handler has higher precedence
                call_user_func(array($this, $viewHandler));
            } elseif (in_array($viewname, array_keys($this->viewMap))) {
                // execute view handler
                $this->defaultViewHandler();
            } elseif (in_array($viewname, array_keys($this->tableViewMap))) {
                // editModel view handler should have the same name as the model to edit
                $this->defaultEditModelViewHandler($viewname);
            } else {
                // other view handlers defined by the system.
                if ( ($tablename = $this->isExportCsvView()) ) {
                    $this->defaultExportCsvViewHandler($tablename);
                } elseif ( ($tablename = $this->isImportCsvView()) ) {
                    $this->defaultImportCsvViewHandler($tablename);
                } else {
                    throw new Silva_Exception("View not registered or view handler show{$viewname} not defined.");
                }
            }
            $this->postShow();
        } catch (Exception $e) {
            if (! headers_sent()) {
                header("HTTP/1.0 500 Internal server error: " . str_replace("\n", "  ", $e->getMessage()));
            }
            Curry_Core::log($e, Zend_Log::ERR);
            $this->addMessage($e->getMessage(), self::MSG_ERROR);
            $this->addMainContent("<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>");
        }
        return $this->render();
    }

    protected function isExportCsvView()
    {
        $view = substr($_GET[self::URL_QUERY_VIEW], -9); // -1 * strlen('ExportCsv')
        if ($view == 'ExportCsv') {
            $tablename = substr($_GET[self::URL_QUERY_VIEW], 0, -9);
            if (in_array($tablename, array_keys($this->tableViewMap))) {
                return $tablename;
            }
        }
        return false;
    }

    protected function isImportCsvView()
    {
        $view = substr($_GET[self::URL_QUERY_VIEW], -9); // -1 * strlen('ImportCsv')
        if ($view == 'ImportCsv') {
            $tablename = substr($_GET[self::URL_QUERY_VIEW], 0, -9);
            if ( in_array($tablename, array_keys($this->tableViewMap)) ) {
                return $tablename;
            }
        }
        return false;
    }

    /**
     * The default view handler.
     * The view name pattern should have the following format: 'Main' + model-associated-with-view + 's'
     * @throws Silva_Exception
     */
    protected function defaultViewHandler()
    {
        $viewname = $this->getActiveViewname();
        $sv = $this->getViewByName($viewname);
        if ($sv === null) {
            throw new Silva_Exception("show{$viewname} is undefined or view not registered.");
        }

        $this->handleGrid($sv);
        
        if (! $sv->getOption('manualBreadcrumbs')) {
            $this->showBreadcrumbs();
        }
        
        $sv->render();
    }

    /**
     * Grid manipulation callback handler.
     * Prototype: Silva_Grid on%TABLENAME%GridInit(Silva_View &$vw);
     *
     * @param Silva_View $sv
     * @return Silva_Grid|null
     */
    private function handleGrid(Silva_View &$sv)
    {
        if (! $sv->hasTable()) {
            return;
        }

        $gridHandler = str_replace('%TABLENAME%', $sv->getTablename(), Silva_Event::EVENT_ON_GRID_INIT);
        if (method_exists($this, $gridHandler)) {
            $grid = call_user_func_array(array($this, $gridHandler), array(&$sv));
            return $grid;
        }
    }

    protected function defaultEditModelViewHandler($tablename)
    {
        $sv = $this->getViewByTable($tablename);
        $sv->editModel();
    }

    protected function defaultExportCsvViewHandler($tablename)
    {
        $sv = $this->getViewByTable($tablename);
        $sv->exportCsv();
    }

    protected function defaultImportCsvViewHandler($tablename)
    {
        $sv = $this->getViewByTable($tablename);
        $sv->importCsv();
    }

    /**
     * Prepare to send email and initiate transfer
     *
     * @param array $queryParams: merged array of $form->getValues() and additional query params to set in the GET ajax request
     * @param string $view: name of the method that handles the mail sending (and returns JSON). Method must be defined in the subclassed Backend.
     * @param integer $start_index
     * @param integer|null $end_index
     */
    public function sendMail(array $queryParams, $view = 'MailBatchJson', $start_index = 0, $end_index = null)
    {
        $url = url('', array_merge(array(
            self::URL_QUERY_MODULE,
            self::URL_QUERY_VIEW => $view,
            'start_index' => $start_index,
            'end_index' => $end_index
        ), (array) $queryParams));

        $this->initMailSession();
        $htmlHead = Curry_Admin::getInstance()->getHtmlHead();
        $htmlHead->addStylesheet('shared/libs/jquery-ui-1.8.17/css/curry/jquery-ui-1.8.17.custom.css');
        $htmlHead->addScript('shared/libs/jquery-ui-1.8.17/js/jquery-ui-1.8.17.custom.min.js');
        $html = Silva_View::getProgressbarHtml($url);
        $this->addMainContent($html);
    }

    /**
     * Return the Zend session for this class.
     * @return Zend_Session_Namespace
     */
    protected function getSessionNamespace()
    {
        return new Zend_Session_Namespace(__CLASS__);
    }

    /**
     * Initialize the email session
     */
    protected function initMailSession()
    {
        $session = $this->getSessionNamespace();
        $session->sent = 0;
        $session->failed = 0;
        $session->startTime = microtime(true);
    }

    /**
     * The ajax batch mail handler
     * @param PropelModelPager $pager
     * @param string $cbObjectHandler: The callback method that handles an individual $pager object
     * @return array: The widget status and other information that must be returned as Json by the calling method.
     */
    public function mailBatchJson(PropelModelPager $pager, $cbObjectHandler)
    {
        $session = $this->getSessionNamespace();
        $sent = (int) $session->sent;
        $failed = (int) $session->failed;
        $startTime = $session->startTime;
        $page = $pager->getPage();
        $batchSize = $pager->getMaxPerPage();
        $index = ($page - 1) * $batchSize;
        foreach ($pager as $object) {
            try {
                if ( ($index >= $_GET['start_index']) && (empty($_GET['end_index']) || ($index < $_GET['end_index'])) ) {
                    list ($sendMailStatus, $logMessage) = call_user_func(array($this, $cbObjectHandler), $object);
                    if ($sendMailStatus) {
                        ++ $sent;
                    } else {
                        ++ $failed;
                    }
                    if (!empty($logMessage)) {
                        Curry_Core::log($logMessage, $sendMailStatus ? null : Zend_Log::ERR);
                    }
                }
            } catch (Exception $e) {
                Curry_Core::log(__METHOD__ . ': ' . $e->getMessage(), Zend_Log::ERR);
                ++ $failed;
            }
            ++ $index;
        }

        // update the mail session
        $session->sent = $sent;
        $session->failed = $failed;
        // update progress-bar widget status
        $totalSent = $sent + $failed;
        $elapsedTime = microtime(true) - $startTime;
        $mailsPerSecond = $totalSent / $elapsedTime;
        $remainingMails = $pager->getNbResults() - $totalSent;
        $eta = ($mailsPerSecond > 0) ? ($remainingMails / $mailsPerSecond) : 1;
        $json = array(
        	'status' => $pager->getLastPage() ? round($pager->getPage() * 100 / $pager->getLastPage()) : 100,
        	'eta' => round($eta),
        	'sent' => $sent,
        	'failed' => $failed,
        );
        // do we have more pages?
        if ($pager->getLastPage() && !$pager->isLastPage()) {
            $json['url'] = (string) url('', $_GET)->add(array('page' => $pager->getNextPage()));
        }
        return $json;
    }

    private function &getViewStackSession()
    {
        if ($this->_session !== null) {
            return $this->_session;
        }

        // create a separate view stack for each backend module
        // to avoid erratic manipulations of the stack when
        // modules are opened in new browser tabs.
        $module = $_GET[self::URL_QUERY_MODULE];
        $this->_session = new Zend_Session_Namespace($module);
        return $this->_session;
    }

    private function getViewStack()
    {
        $session = $this->getViewStackSession();
        $viewStack = isset($session->viewStack) ? $session->viewStack : array();
        return $viewStack;
    }

    private function saveViewStack($viewStack)
    {
        $session = &$this->getViewStackSession();
        $session->viewStack = $viewStack;
    }

    /**
     * Push the view onto the view stack.
     * The view stack stores a list of views traversed (Main at 0, nth-view at 'n-1').
     * @param string $viewname: the value of the 'view' url query param.
     */
    protected function pushView($viewname)
    {
        trace(__METHOD__);
        $viewStack = $this->getViewStack();
        if (! empty($viewStack)) {
            trace($viewStack);
            //$offset = Silva_Array::array_key_index($viewname, $this->viewMap);
            $offset = Silva_Array::array_key_index($viewname, $viewStack);
            if ($offset !== false) {
                array_splice($viewStack, $offset);
            }
        }
        $viewStack[$viewname] = url('', $_GET)->remove(array('json'))->getUrl();
        $this->saveViewStack($viewStack);
    }

    /**
     * Push the current/active view onto the view stack.
     */
    protected function pushActiveView()
    {
        $this->pushView($this->getActiveViewname());
    }

    /**
     * Return the current/active view name.
     * @return string
     */
    protected function getActiveViewname()
    {
        $viewname = (isset($_GET[self::URL_QUERY_VIEW]) && !empty($_GET[self::URL_QUERY_VIEW])) ? $_GET[self::URL_QUERY_VIEW] : 'Main';
        return $viewname;
    }

    /**
     * Inject CSS styles into the <head> of the backend template
     */
    private function embedCSS()
    {
        $styles = <<<CSS
<style type="text/css" media="screen,projection">
/*<![CDATA[*/
.dlg-status .ui-dialog-content {
	padding: 0.5em 1em !important;
}
.dlg-large-image .ui-dialog-content {
  text-align: center;
}
form.locale-selector, form.filters {
	box-shadow: 0 1px 2px lightgray;
	border-radius: 15px;
	overflow: hidden;
}
form.locale-selector #locale-element {
	border-radius: 6px;
	box-shadow: 0px 1px 4px lightgray;
}
div.flexigrid {
	box-shadow: 0 2px 2px lightgray;
}
/*]]>*/
</style>
CSS;
        self::appendTemplateHead($styles);
    }

    private function embedJS()
    {
        $js = <<<JS
(function($){
// Info dialog
$.util.infoDialog = function(dialogTitle, text, onClose){
 var \$dlg = \$('<div title="'+dialogTitle+'"></div>');
 \$dlg.html(text);
 \$dlg.dialog({
  dialogClass: 'dlg-status info',
  width: 300,
  minHeight: 150,
  modal: true,
  position: 'center',
  buttons: {
    Ok: function(){
      \$(this).dialog("close");
	  }
  },
  close: function(event, ui){
    if(onClose && typeof(onClose)==='function'){
      onClose();
    }
  }
 });
 return \$dlg;
};

// Large image viewer
$(document).delegate('a.silva-large-image', 'click', function(){
  var dialogTitle = 'Image preview';
  var \$dlg = \$('<div title="'+dialogTitle+'"></div>');
  var flexId = \$(this).data('flexid');
  \$dlg.html('<img src="'+\$(this).attr('href')+'" />');
  \$dlg.dialog({
    dialogClass: 'dlg-large-image',
    modal: true,
    position: 'center',
    resizable: true,
    buttons: {
      Ok: function(){
        \$(this).dialog("close");
	    }
	  },
	  close: function(event, ui){
	   \$('#'+flexId).flexReload();
	  }
   });
  return false;
});

})(jQuery);
JS;
        Curry_Admin::getInstance()->getHtmlHead()->addInlineScript($js);
    }

    /**
     * Inject html code into the backend template's &lt;head&gt; section.
     * @param string $html
     * @return Curry_HtmlHead
     */
    public static function appendTemplateHead($html)
    {
        $htmlHead = Curry_Admin::getInstance()->getHtmlHead();
        $htmlHead->addRaw($html);
        return $htmlHead;
    }

} // Silva_Backend
