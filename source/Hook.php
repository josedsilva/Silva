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
 * Static class declares hook patterns and functions
 *
 * @category    Curry CMS
 * @package     Silva
 * @author      Jose Francisco D'Silva
 * @version
 *
 */
class Silva_Hook {
    
    /**#@+
     * @category Silva_View hook
     */
    
    /** Hook function is called before the grid is initialized.
     *  Define this hook to customize the grid.
     */
    const HOOK_ON_GRID_INIT = 'on%TABLENAME%GridInit';
    /** Hook function is called on an ajax json request.
     *  Define this hook to manipulate JSON data to and from the grid.
     */
    const HOOK_ON_JSON = 'on%TABLENAME%Json';
    /** Hook function is called when the form is about to be displayed. */
    const HOOK_ON_SHOW_FORM = 'on%TABLENAME%ShowForm';
    /** Hook function is called when form data is validated and is about to be saved. */
    const HOOK_ON_SAVE = 'on%TABLENAME%Save';
    /** Hook function is called when CSV file is uploaded and requires user to parse it. */
    const HOOK_ON_IMPORT_CSV = 'on%TABLENAME%ImportCsv';
    /** Hook function is called when user has to supply data to the Csv export handler. */
    const HOOK_ON_EXPORT_CSV = 'on%TABLENAME%ExportCsv';
    /** Hook function is called just before an HTML view is rendered */
    const HOOK_ON_VIEW_RENDER = 'on%VIEWNAME%Render';
    /**#@-*/
    
    /**#@+
     * @category Silva_Form hook
     */
    /** Hook function is called before form elements are created. */
    const HOOK_ON_FORM_ELEMENTS_INIT = 'onFormElementsInit';
    /**#@-*/
    
    /**
     * Return the formatted hook pattern.
     * @param string $hookConstant      A Silva hook constant
     * @param mixed $search             Placeholders or substitution parameters
     * @param mixed $replace            Placeholder values
     * @return string
     */
    public static function getHookPattern($hookConstant, $search, $replace)
    {
        return str_replace($search, $replace, $hookConstant);
    }
    
    /**
     * Hook execution helper
     * 
     * @param callable $hook         The hook function to execute
     * @param ...
     * @throws Silva_Exception
     * @return mixed|null            Return null if the hook function returns nothing else whatever the hook function returns.
     */
    public static function execHook($hook)
    {
        $argc = func_num_args();
        if ($argc < 1) {
            throw new Silva_Exception('Insufficient arguments');
        }
        
        $argv = func_get_args();
        $funcArgs = array_splice($argv, 1);
        return (empty($funcArgs) ? call_user_func($hook) : call_user_func_array($hook, $funcArgs));
        
    }
    
} // Silva_Hook
