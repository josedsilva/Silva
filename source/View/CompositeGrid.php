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
 * Define a composite grid view
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
    protected static $defaultOptions = array();
    
    public function __construct($tableMap, $compositeTableMap, Curry_Backend $backend, array $options = array())
    {
        $this->extendOptions(self::$defaultOptions);
        parent::__construct($tableMap, null, $backend, $options);
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
