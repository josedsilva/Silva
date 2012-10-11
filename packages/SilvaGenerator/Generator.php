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
 * Generate code for a Backend module using Silva APIs 
 * 
 * @category   Silva
 * @package    Silva Module Generator
 * @author     Jose Francisco D'Silva
 * @version    
 */
final class Common_Backend_SilvaGenerator_Generator extends Curry_Backend
{
    protected $consoleBuffer = '';
    protected $views = array();
    
    const OPT_GRIDINIT_LEAFVIEWS = 'generate_gridinit_handler_for_leaf_view';
    const OPT_FORMELMSINIT = 'generate_formelminit_handler';
    const OPT_DOCCOMMENTS = 'generate_doc_comments';
    const OPT_TIDYGRID = 'tidy_grid';
    protected $options = array(
        self::OPT_GRIDINIT_LEAFVIEWS => false,
        self::OPT_FORMELMSINIT => true,
        self::OPT_DOCCOMMENTS => true,
        self::OPT_TIDYGRID => false, // whether to tidy the grid?
    );
    
    public static function getName() {
        return 'Silva Module Generator';
    }
    
    public static function getGroup() {
    	return 'Content';
    }
    
    public function showMain() {
        if (! $this->isSilvaInstalled()) {
            $html =<<<HTML
<p class="message notice">Automagically generate code for a backend module.</p>
<p class="message error">Silva was not found with your project installation.</p>
<pre>
You must install Silva before you can generate code for a backend module.
@see <a href="https://github.com/josedsilva/Silva/" target="_blank">https://github.com/josedsilva/Silva/</a>
</pre>
HTML;

        } else {
            $form = $this->getForm();
            if (isPost() && $form->isValid($_POST)) {
                $values = $form->getValues(true);
                if ($form->generate->isChecked()) {
                    $this->handleForm($values);
                    return;
                }
            }
            
            $html =<<<HTML
<p class="message notice">Automagically generate code for a backend module.</p>
$form
HTML;

        }
        
        $this->addMainContent($html);
    }
    
    private function isSilvaInstalled() {
        // detect the main Silva classes
        return class_exists('Silva_Backend') && class_exists('Silva_View_Grid');
    }
    
    private function getForm() {
        $form = new Curry_Form(array(
            'action' => url('', $_GET),
            'method' => 'post',
            'elements' => array(
                'module_class' => array('text', array(
                    'label' => 'Module class *',
                    'value' => 'Project_Backend_',
                    'required' => true,
                )),
                'root_table' => array('text', array(
                    'label' => 'Root table *',
                    'placeholder' => 'Enter PhpName of the root model',
                    'required' => true,
                )),
                'module_desc' => array('textarea', array(
                    'label' => 'Module description',
                    'placeholder' => 'What is your backend module supposed to do?',
                    'rows' => 5,
                )),
                'module_author' => array('text', array(
                    'label' => 'Author',
                    'placeholder' => 'Enter your name',
                )),
            ),
        ));
        
        $subform = new Curry_Form_SubForm(array(
            'legend' => 'Options',
            'class' => 'advanced',
            'elements' => array(
                'gridinit_for_leafview' => array('checkbox', array(
                    'label' => 'Generate onGridInit event handler for leaf views',
                    'value' => $this->options[self::OPT_GRIDINIT_LEAFVIEWS],
                )),
                'formelmsinit' => array('checkbox', array(
                    'label' => 'Generate onFormElementsInit event handler',
                    'value' => $this->options[self::OPT_FORMELMSINIT],
                    'description' => 'Allow Curry_Form_Elements',
                )),
                'gen_doc_comments' => array('checkbox', array(
                    'label' => 'Generate documentation comments',
                    'value' => $this->options[self::OPT_DOCCOMMENTS],
                )),
                'tidy_grid' => array('checkbox', array(
                    'label' => 'Tidy grid?',
                    'value' => $this->options[self::OPT_TIDYGRID],
                )),
                'apply' => array('submit', array('label' => 'Apply')),
            ),
        ));
        
        $form->addSubForm($subform, 'options');
        $form->addElement('submit', 'generate', array('label' => 'Generate code'));
        return $form;
    }
    
    private function handleForm(array $values) {
        try {
            // populate options
            $this->options[self::OPT_GRIDINIT_LEAFVIEWS] = $values['options']['gridinit_for_leafview'];
            $this->options[self::OPT_FORMELMSINIT] = $values['options']['formelmsinit'];
            $this->options[self::OPT_DOCCOMMENTS] = $values['options']['gen_doc_comments'];
            $this->options[self::OPT_TIDYGRID] = $values['options']['tidy_grid'];
            
            $rootTableMap = PropelQuery::from($values['root_table'])->getTableMap();
            $this->generateCode($values, $rootTableMap);
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Return an array of 1-n and n-n RelationMaps for $tableMap
     * @param TableMap $tableMap
     * @param boolean $ignoreI18nRelations: Whether to ignore I18n relation maps?
     * @return RelationMap[]
     */
    public static function getManyForeignRelations(TableMap $tableMap, $ignoreI18nRelations = true) {
        $ret = array();
        foreach ($tableMap->getRelations() as $rm) {
            if ($rm->getType() === RelationMap::ONE_TO_MANY || $rm->getType() === RelationMap::MANY_TO_MANY) {
                if ($ignoreI18nRelations && strrpos($rm->getName(), 'I18n', -1) === false) {
                    $ret[$rm->getName()] = $rm;
                }
            }
        }
        
        return $ret;
    }
    
    /**
     * Get all possible Silva_View_Grid views for module whose root tableMap is $tableMap
     * @param TableMap $tableMap
     * @param TableMap|null $catTableMap
     * @param integer $level: Recursive depth
     */
    private function constructModuleViews(TableMap $tableMap, $catTableMap = null, $level = 1) {
        $catRelMap = null;
        if ($catTableMap !== null) {
            $catRelMap = $this->getCategoryRelationMap($tableMap, $catTableMap);
        }
        
        $vw = new Silva_View_Grid($tableMap, $catRelMap, $this);
        $whyIgnored = '';
        if ($this->ignoreView($vw, $whyIgnored)) {
            $catRel = $catRelMap ? $catRelMap->getName() : 'null';
            $s = "Ignored view with table <span style=\"color: cyan\">{$vw->getTablename()}</span> and category relation <span style=\"color: cyan\">$catRel</span> (reason: $whyIgnored)";
            $this->consoleBuffer .= '<span style="color: white">' . $s . '</span>' . "\n";
            return;
        }
        
        $this->views[] = $vw;
        
        // buffer status
        $catRel = $catRelMap ? $catRelMap->getName() : 'null';
        $s = "Built <span style=\"color: cyan\">Grid View</span> with table <span style=\"color: cyan\">{$vw->getTablename()}</span> and category relation <span style=\"color: cyan\">$catRel</span>";
        $this->consoleBuffer .= '<span style="color: lightgreen">' . $s . '</span>' . "\n";
        
        // attempt to construct related views for this table
        $relMaps = self::getManyForeignRelations($tableMap);
        foreach ($relMaps as $relName => $relMap) {
            $localTm = $relMap->getLocalTable();
            $this->constructModuleViews($localTm, $tableMap, ($level+1));
        }
    }
    
    /**
     * Whether to ignore a view?
     * @param Silva_View $view
     */
    private function ignoreView(Silva_View $view, &$whyIgnored) {
        return $this->viewExists($view, $whyIgnored) || $this->hasSelfReferentialRelationship($view, $whyIgnored);
    }
    
    private function viewExists(Silva_View $view, &$status) {
        foreach ($this->views as $vw) {
            if ($vw->getTablename() == $view->getTablename() /*&& $vw->getCategoryRelationMap() == $view->getCategoryRelationMap()*/) {
                $status = 'Conflicting master table <span style="color: cyan">'. $vw->getTablename() . '</span> found in stack. Last find discarded.';
                return true;
            }
        }
        
        return false;
    }
    
    private function hasSelfReferentialRelationship(Silva_View $view, &$status) {
        $tableMap = $view->getTableMap();
        $catRelMap = $view->getCategoryRelationMap();
        if ($catRelMap === null) {
            return false;
        }
        
        if (($catRelMap->getForeignTable() == $tableMap)) {
            $status = "Self referential relationship found. Infinite recursion avoided.";
            return true;
        }
    }
    
    /**
     * Return the RelationMap from $tableMap whose foreign tablemap matches $catTableMap 
     * @param TableMap $tableMap
     * @param TableMap $catTableMap
     */
    private function getCategoryRelationMap(TableMap $tableMap, TableMap $catTableMap) {
        foreach ($tableMap->getRelations() as $rm) {
            if (strrpos($rm->getName(), 'I18n', -1) !== false) {
                continue;
            }
            
            if ($rm->getType() === RelationMap::MANY_TO_ONE || $rm->getType() === RelationMap::MANY_TO_MANY) {
                if ($rm->getForeignTable()->getPhpName() == $catTableMap->getPhpName()) {
                    return $rm;
                }
            }
        }
        
        return null;
    }

    private function generateCode(array $formValues, TableMap $rootTableMap) {
        $moduleRelativePath = implode('/', explode('_', $formValues['module_class'])) . '.php';
        $modulePath = Curry_Core::$config->curry->projectPath . '/include/' . $moduleRelativePath;
        
        if (file_exists($modulePath)) {
            throw new Exception('Module file already exists in path: ' . $moduleRelativePath);
        }
        
        $views = array();
        $this->constructModuleViews($rootTableMap);
        foreach ($this->views as $vw) {
            $relMaps = self::getManyForeignRelations($vw->getTableMap());
            $buttons = array();
            $buttons[0] = array('AEDS' => 'Silva_View::BUTTON_AED');
            if (count($relMaps)) {
                $buttons[0] = array('AEDS' => 'Silva_View::BUTTON_AEDS');
                foreach ($relMaps as $n => $rm) {
                    $buttons[] = array(
                        'RelationName' => $n,
                    );
                }
            }
            
            $viewOptions = array();
            if ($vw->tableHasCompositePk()) {
                $viewOptions['ignorePrimaryKeys'] = 'false';
            }
            
            $views[] = array(
                'TableName' => $vw->getTablename(),
                'CatRelationName' => $vw->getCategoryRelationMap() ? $vw->getCategoryRelationMap()->getName() : null,
                'HasChildViews' => (boolean) count($relMaps),
                'Buttons' => $buttons,
                'ViewOptions' => $viewOptions,
            );
        }
        
        $tplData = array(
            'Self' => __CLASS__,
        		'Options' => $this->options,
            'ModuleClass' => $formValues['module_class'],
            'ModuleDescription' => $formValues['module_desc'],
            'ModuleAuthor' => $formValues['module_author'],
            'Views' => $views,
        );
        
        $tplPath = __DIR__ . '/Templates';
        $cachePath = Curry_Core::$config->curry->projectPath . '/data/cache/templates';
        $loader = new Twig_Loader_Filesystem($tplPath);
        $twig = new Twig_Environment($loader, array(
            'cache' => $cachePath,
            'auto_reload' => true,
        ));
        $tpl = $twig->loadTemplate('Module.tpl');
        $code = $tpl->render(array(
            'SG' => $tplData,
        ));
        
        file_put_contents($modulePath, '<?php'."\n" . $code);
        
        $moduleUrl = url('', array('module' => $formValues['module_class']))->getUrl();
        $html =<<<HTML
<pre class="console">
{$this->consoleBuffer}
</pre>
<br />
<p class="message success">Code generated!</p>
<p class="message warning">Generated code is a module stub. Manual programming maybe required to build the finished product.</p>
<pre>
Backend module: <a href="$moduleUrl">{$formValues['module_class']}</a>
Module path: {$moduleRelativePath}
</pre>
HTML;

        $this->addMainContent($html);
    }
    
} // Common_Backend_SilvaGenerator_Generator
