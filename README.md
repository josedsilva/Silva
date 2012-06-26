Silva
=====

Library for rapid development of Bombayworks Curry CMS backend modules.
___
Category: Curry CMS   
Organization: [Bombayworks](http://bombayworks.se)   
Author: Jose Francisco D'Silva
___

##Quick Installation:
Copy the files from the ***source/*** folder to the ***cms/include/Silva/*** folder of your Curry CMS installation.

##Quick Start:
```php   
<?php
class Project_Backend_Products extends Silva_Backend
{
  public function __construct()
  {
    parent::__construct(array(
      'showBreadcrumbs' => true,
    ));

    $this->setViews(array(
      $this->getProductCatView(),
      $this->getProductView(),
    ));
  }

  protected function getProductCatView()
  {
    $sv = new Silva_View_Grid('ProductCat', null, $this, array(
      'autoBuildForm' => true,
      'getFormCallback' => false,
      'saveCallback' => false,
    ));
    $sv->setBreadcrumbText('Categories');
    return $sv;
  }

  protected function getProductCatGrid(Silva_View $sv)
  {
    $sv->setDescription("Manage product categories.");

    $grid = $sv->getGrid(array(
      Silva_View::BUTTON_AEDS,
      array(
        'caption' => 'Products',
        'url' => url('', array('module', 'view' => 'MainProducts')),
      ),
    ));
    
    return $grid;
  }

  protected function getProductView()
  {
    $sv = new Silva_View_Grid('Product', 'ProductCat', $this, array(
      'autoBuildForm' => true,
      'getFormCallback' => false,
      'saveCallback' => false,
    ));
    
    return $sv;
  }

}
```
