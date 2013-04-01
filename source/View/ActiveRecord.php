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
 * Define a view showing the active record
 *
 * @category    Curry
 * @package     Silva
 * @author      Jose Francisco D'Silva
 * @version
 *
 */
class Silva_View_ActiveRecord extends Silva_View_BaseModel
{
    protected $activeRecord = null;
    private $defaultOptions = array();

    public function __construct(BaseObject $activeRecord, Curry_Backend $backend, array $options = array())
    {
        $this->activeRecord = $activeRecord;
        $tableMap = PropelQuery::from(get_class($activeRecord))->getTableMap();
        Curry_Array::extend($this->defaultOptions, $options);
        parent::__construct($tableMap, null, $backend, $this->defaultOptions);
    }

    public function editModel()
    {
        return parent::editModel(false);
    }

    // override
    protected function getActiveRecord()
    {
        return $this->activeRecord;
    }

    // override: return the form to embed in the backend view.
    protected function saveActiveRecord($activeRecord, $form)
    {
        parent::saveActiveRecord($activeRecord, $form);
        return Curry_Application::returnPartial($form);
    }

    public function render()
    {
        $this->showLocaleForm();
        $this->showDescription();
        $this->addMainContent($this->editModel());
    }

} //Silva_View_ActiveRecord
