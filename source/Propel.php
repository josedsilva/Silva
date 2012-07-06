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
 * Helper class for reusable Propel methods
 *
 * @category	Curry
 * @package		Silva
 * @author		Jose Francisco D'Silva
 * @version
 *
 */
class Silva_Propel extends Curry_Propel {

    /**
     * Return true if $tableMap has the specified behavior; false otherwise
     * @param string $behavior
     * @param TableMap $tableMap
     * @return boolean
     */
    public static function hasBehavior($behavior, TableMap $tableMap)
    {
        return in_array(strtolower($behavior), array_keys($tableMap->getBehaviors()));
    }

    /**
     * Return the specified behavior properties
     * @param string $behavior
     * @param TableMap $tableMap
     * @return array
     */
    public static function getBehavior($behavior, TableMap $tableMap)
    {
    	$behaviors = $tableMap->getBehaviors();
    	return (array) $behaviors[strtolower($behavior)];
    }

    /**
     * Whether $tableMap has i18n behavior
     * @param TableMap $tableMap
     * @return boolean
     */
    public static function hasI18nBehavior(TableMap $tableMap) {
        return self::hasBehavior('i18n', $tableMap);
    }

    /**
     * Return the i18n TableMap if $tableMap has I18n behavior
     * @param TableMap $tableMap
     * return TableMap|null
     */
    public static function getI18nTableMap(TableMap $tableMap)
    {
        if (self::hasI18nBehavior($tableMap)) {
            $i18nTablename = "{$tableMap->getPhpName()}I18n";
            return PropelQuery::from($i18nTablename)->getTableMap();
        }

        return null;
    }

    /**
     * Return the I18n ColumnMap objects for the specified $tableMap
     * @param TableMap $tableMap
     * @return array|null A ColumnMap[] of I18n columns.
     */
    public static function getI18nColumns(TableMap $tableMap)
    {
        $i18nTableMap = self::getI18nTableMap($tableMap);
        if ($i18nTableMap !== null) {
            return $i18nTableMap->getColumns();
        }

        return null;
    }

    /**
     * Whether $column is an I18n column
     * @param string $column
     */
    public static function hasI18nColumn($column, TableMap $tableMap) {
        $i18nTableMap = self::getI18nTableMap($tableMap);
        if ($i18nTableMap !== null) {
            return $i18nTableMap->hasColumn($column);
        }

        return false;
    }

    /**
     * Return the ColumnMap for the i18n column
     * @param string $column
     * @return ColumnMap|null
     */
    public static function getI18nColumn($column, TableMap $tableMap) {
        $i18nTableMap = self::getI18nTableMap($tableMap);
        if ($i18nTableMap !== null) {
            return $i18nTableMap->getColumn($column);
        }

        return null;
    }

} // Silva_Propel