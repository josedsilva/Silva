/**
 * {{ SG.ModuleDescription | default('Enter module description here...') | raw }}
 * 
 * @category   Curry
 * @name       {{ SG.ModuleClass }}
 * @author     {{ SG.ModuleAuthor | default('Enter author name here...') | raw }}
 *
 * @see        https://github.com/josedsilva/Silva/wiki/Documentation
 */
 class {{ SG.ModuleClass }} extends Silva_Backend
{
    public function __construct() {
        parent::__construct();
        
        $this->setViews(array(
            {% for sgView in SG.Views %}
            new Silva_View_Grid('{{ sgView.TableName }}', {% if sgView.CatRelationName %}'{{ sgView.CatRelationName }}'{% else %}null{% endif %}, $this{% if sgView.ViewOptions is not empty %}, array(
            	{% for k,v in sgView.ViewOptions %}
            	'{{ k }}' => {{ v }},
            	{% endfor %}
            ){% endif %}),
            {% endfor %}
        ));
    }
    
    {% if SG.Options[constant(SG.Self ~ '::OPT_FORMELMSINIT')] %}
    {% if SG.Options[constant(SG.Self ~ '::OPT_DOCCOMMENTS')] %}
    /**
     * Customize Silva_Form fields.
     * 
     * @param Silva_Form $form    Reference to Silva_Form in the current context.
     * @return array|null
     *
     * @see https://github.com/josedsilva/Silva/wiki/Advanced-Features
     */
    {% endif %}
    public function onFormElementsInit(Silva_Form &$form) {
        $ret = null;
        switch($form->getTablename()) {
            //case 'TablePhpName':
                //$ret = array(
                    // fieldPhpName => 'CurryFormElement',
                    // fieldPhpName => array('CurryFormElement', array('label' => 'Some text', ...)),
                    // fieldPhpName => array('label' => 'Some text', ...),
                //);
                //break;
        }
        
        return $ret;
    }
    {% endif %}
    
    {% for sgView in SG.Views %}
    {% if sgView.HasChildViews or SG.Options[constant(SG.Self ~ '::OPT_GRIDINIT_LEAFVIEWS')]  %}
    {% if SG.Options[constant(SG.Self ~ '::OPT_DOCCOMMENTS')] %}
    /**
     * Customize the view associated with model {{ sgView.TableName }}
     * @param Silva_View $vw  The view object.
     */
    {% endif %}
    public function on{{ sgView.TableName }}GridInit(Silva_View $vw) {
    		$vw->setDescription("Manage {{ sgView.TableName ~ 's'}}{% if sgView.CatRelationName %} for {$vw->getActiveCategoryObject()}{% endif %}");
    		{% if sgView.HasChildViews %}
        $buttons = array(
        {% for sgButton in sgView.Buttons %}
            {% if sgButton.AEDS %}
                {{ sgButton.AEDS }},
            {% else %}
                array(
                    'caption' => '{{ sgButton.RelationName }}s',
                    'url' => url('', array('module', 'view' => 'Main{{ sgButton.RelationName }}s')),
                ),
            {% endif %}
        {% endfor %}
        );
        $grid = $vw->getGrid($buttons);
        {% else %}
        $grid = $vw->getGrid();
        {% endif %}
        
        {% if SG.Options[constant(SG.Self ~ '::OPT_TIDYGRID')] %}
        $vw->tidyGrid();
        {% endif %}
    }
    
    {% endif %}
    {% endfor %}
    
} // {{ SG.ModuleClass }}
