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
 * Define a grid view whose master table has relationships that are self-referential
 *
 * @category    Curry
 * @package     Silva
 * @author      Jose Francisco D'Silva
 * @version
 *
 */
class Silva_View_SelfReferential extends Silva_View_Grid
{
    public function __construct($tableMap, Curry_Backend $backend, array $options = array())
    {
        $this->extendOptions(array('manualBreadcrumbs' => true));
        // catRelMap must be set as a url param if the root-level of the SR view is a foreign relation of catRelMap
        $catRelationMap = isset($_GET['relMap']) ? $_GET['relMap'] : $_GET['catRelMap'];
        parent::__construct($tableMap, $catRelationMap, $backend, $options);
    }
    
    protected function getAddEditUrl()
    {
        $editUrl = parent::getAddEditUrl();
        $editUrl->add(array(
        		'relMap' => $_GET['relMap'],
            'level' => $_GET['level'],
        ));
        
        return $editUrl;
    }
    
    public function setBreadcrumbText(array $text)
    {
        $this->breadcrumbText = $text;
        return $this;
    }
    
    public function getBreadcrumbText($key = 0)
    {
        return is_array($this->breadcrumbText) ? $this->breadcrumbText[$key] : parent::getBreadcrumbText();
    }
    
} //Silva_View_SelfReferential
