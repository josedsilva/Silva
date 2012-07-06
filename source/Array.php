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
 * Helper class for reusable PHP array methods
 *
 * @category	Curry
 * @package		Silva
 * @author		Jose Francisco D'Silva
 * @version
 *
 */
class Silva_Array extends Curry_Array
{

    /**
     * Return the values of the $input array recursively.
     * @param array $input
     * @return array
     */
    public static function array_values_recursive(array $input)
    {
        $values = array();
        foreach ($input as $elm) {
            if (is_array($elm)) {
                $values = array_merge($values, self::array_values_recursive($elm));
            } else {
                $values[] = $elm;
            }
        }

        return $values;
    }

    /**
     * Return the index or the numerical position of an array key.
     * @param mixed $key
     * @param array $haystack
     * @param bool $strict
     * @return integer|false
     */
    public static function array_key_index($key, array $haystack, $strict = false)
    {
        return array_search($key, array_keys($haystack), $strict);
    }


} // Silva_Array