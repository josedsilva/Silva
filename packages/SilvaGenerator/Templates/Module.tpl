/**
 * {{ SG.ModuleDescription | default('Enter module description here...') | raw }}
 * 
 * @category   Curry
 * @name       {{ SG.ModuleClass }}
 * @author     {{ SG.ModuleAuthor | default('Enter author name here...') | raw }}
 */
 class {{ SG.ModuleClass }} extends Silva_Backend
{
    public function __construct() {
        parent::__construct();
        
        $this->setViews(array(
            {% for sgView in SG.Views %}
            new Silva_View_Grid('{{ sgView.TableName }}', {% if sgView.CatRelationName %}'{{ sgView.CatRelationName }}'{% else %}null{% endif %}, $this),
            {% endfor %}
        ));
    }
    
    {% if SG.Options[constant(SG.Self ~ '::OPT_FORMELMSINIT')] %}
    /**
     * Customize Silva_Form fields.
     * 
     * @param Silva_Form $sf Reference to the Silva_Form object in the current context.
     * @return array|null
     * @see https://github.com/josedsilva/Silva/wiki/Advanced-Features
     */
    public function onFormElementsInit(Silva_Form &$sf) {
        $ret = null;
        switch($sf->getTablename()) {
            //case 'Enter TablePhpName...':
                //$ret = array(
                    // fieldPhpNames
                //);
                //break;
        }
        
        return $ret;
    }
    {% endif %}
    
    {% for sgView in SG.Views %}
        {% if sgView.HasChildViews or SG.Options[constant(SG.Self ~ '::OPT_GRIDINIT_LEAFVIEWS')]  %}
            /**
             * Customize grid for view associated with model {{ sgView.TableName }}
             * 
             * @param Silva_View $sv  The view object.
             * @see https://github.com/josedsilva/Silva/wiki/Documentation
             */
            protected function on{{ sgView.TableName }}GridInit(Silva_View $sv) {
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
                
                $grid = $sv->getGrid($buttons);
            }
            
        {% endif %}
    {% endfor %}
    
} // {{ SG.ModuleClass }}
