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
 * Reusable general purpose methods.
 *
 * @category    Curry
 * @package     Silva
 * @author      Jose Francisco D'Silva
 * @version
 *
 */
class Silva_Helpers
{
    const TEMP_PATH = 'content/temp/';

    private function __construct()
    {
        
    }
    
    /**
     * Return the temporary path.
     * If path does not exist it will be created.
     * @param boolean $fullPath Return the full path instead of the relative path
     * @return string
     */
    public static function getTempPath($fullPath = false)
    {
        $fullTempPath = Curry_Core::$config->curry->wwwPath . '/' . self::TEMP_PATH;
        if (! file_exists($fullTempPath)) {
            mkdir($fullTempPath);
        }

        return $fullPath ? $fullTempPath : self::TEMP_PATH;
    }
    
    /**
     * Return a Php getter string
     * @param string $dottedString
     */
    public static function getPhpGetterString($dottedString) {
        $a = explode('.', $dottedString);
        $lastElm = array_pop($a);
        if (! empty($a)) {
            $a = array_map(create_function('$e', 'return "get{$e}()";'), $a);
            $s = implode('->', $a);
            return $s.'->'."get{$lastElm}";
        }
        
        return "get{$lastElm}";
    }
    
    /**
     * Return a Php setter string
     * @param string $dottedString
     */
    public static function getPhpSetterString($dottedString) {
        $a = explode('.', $dottedString);
        $lastElm = array_pop($a);
        if (! empty($a)) {
            $a = array_map(create_function('$e', 'return "get{$e}()";'), $a);
            $s = implode('->', $a);
            return $s.'->'."set{$lastElm}";
        }
        
        return "set{$lastElm}";
    }
    

} //Silva_Helpers
