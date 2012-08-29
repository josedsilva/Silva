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
 * Static class declares event constants
 *
 * @category    Curry
 * @package     Silva
 * @author      Jose Francisco D'Silva
 * @version
 *
 */
class Silva_Event {
    
    /**#@+
     * @category Silva_View event
     */
    /** Event triggered when the grid is initialized */
    const EVENT_ON_GRID_INIT = 'on%TABLENAME%GridInit';
    /** Event triggered on an ajax json request */
    const EVENT_ON_JSON = 'on%TABLENAME%Json';
    /** Event triggered when the Add/Edit form is about to be displayed */
    const EVENT_ON_SHOW_FORM = 'on%TABLENAME%ShowForm';
    /** Event triggered when form data is validated and is about to be saved */
    const EVENT_ON_SAVE = 'on%TABLENAME%Save';
    /** Event triggered when CSV file is uploaded and requires user to parse it */
    const EVENT_ON_IMPORT_CSV = 'on%TABLENAME%ImportCsv';
    /** Event triggered when user has to supply data to the Csv export handler */
    const EVENT_ON_EXPORT_CSV = 'on%TABLENAME%ExportCsv';
    /**#@-*/
    
    /**#@+
     * @category Silva_Form event
     */
    /** Before form elements are created */
    const EVENT_ON_FORM_ELEMENTS_INIT = 'onFormElementsInit';
    /**#@-*/
    
    /**
     * Return the name of the event handler
     * @param string $event   Silva_Event::EVENT_XXXXX
     * @param mixed $params   Placeholders or substitution parameters
     * @param mixed $values   
     */
    public static function getEvent($event, $params, $values)
    {
        return str_replace($params, $values, $event);
    }
    
} // Silva_Event
