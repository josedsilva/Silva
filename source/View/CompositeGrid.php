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
 * Define a composite grid view.
 * A composite grid view is a view that is visited from a previous grid view having composite primary keys.
 * Since the parent view used composite primary keys, the flexigrid has passed the serialized primary key to this view.
 *
 * @category    Curry
 * @package     Silva
 * @author      Jose Francisco D'Silva
 * @version     
 *
 */
class Silva_View_CompositeGrid extends Silva_View_Grid
{
    protected $compositeTableMap = null;
    private $defaultOptions = array();
    
    /**
     * The constructor...
     * @param TableMap|string $tableMap  Table associated with this view (may or maynot have composite primary keys)
     * @param TableMap|string $compositeTableMap  The table associated with parent view (the table must have composite primary keys).
     * @param Curry_Backend $backend
     * @param array $options
     */
    public function __construct($tableMap, $compositeTableMap, Curry_Backend $backend, array $options = array())
    {
    	Curry_Array::extend($this->defaultOptions, $options);
        parent::__construct($tableMap, null, $backend, $this->defaultOptions);
        
        $this->compositeTableMap = is_string($compositeTableMap) ? PropelQuery::from($compositeTableMap)->getTableMap() : $compositeTableMap;
    }
    
    protected function getDefaultFilterQuery()
    {
        $query = parent::getDefaultFilterQuery();
        if (! $query) {
            $query = PropelQuery::from($this->getTablename());
        }
        
        $compositeKey = strtolower($this->compositeTableMap->getName()) . '_id';
        $composite_object = PropelQuery::from($this->compositeTableMap->getPhpName())->findPk(unserialize($_GET[$compositeKey]));
        foreach ($this->compositeTableMap->getPrimaryKeys() as $cmPk) {
            $ftPk = array_shift($cmPk->getRelatedTable()->getPrimaryKeys());
            $query->filterBy($ftPk->getPhpName(), $composite_object->{'get'.$ftPk->getPhpName()}());
        }
        
        return $query;
    }
    
    /**
     * Return the related object from the parent view 
     */
    public function getActiveCategoryObject()
    {
        $fk = unserialize($_GET[strtolower($this->compositeTableMap->getName()) . '_id']);
        return PropelQuery::from($this->compositeTableMap->getPhpName())->findPk($fk);
    }
    
    public function getCategoryLocalReference()
    {
        return null;
    }

    public function getCategoryLocalReferenceName()
    {
        return null;
    }

    public function getCategoryLocalReferencePhpName()
    {
        return null;
    }

    public function getCategoryForeignReference()
    {
        return null;
    }

    public function getCategoryForeignReferenceName()
    {
        return null;
    }

    public function getCategoryForeignReferencePhpName()
    {
        return null;
    }
    
    
} // Silva_View_CompositeGrid
