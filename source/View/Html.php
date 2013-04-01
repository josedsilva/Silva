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
 * Define an ordinary html view
 *
 * @category    Curry
 * @package     Silva
 * @author      Jose Francisco D'Silva
 * @version
 *
 */
class Silva_View_Html extends Silva_View
{
	/**
	 * The html content
	 * @var string
	 */
	protected $content;
	private $defaultOptions = array();
	
	public function __construct($viewname, Curry_Backend $backend, array $options = array())
	{
		Curry_Array::extend($this->defaultOptions, $options);
		parent::__construct($backend, $this->defaultOptions);
		
		$this->setViewname($viewname);
	}
	
	public function hasTable()
	{
		return false;
	}
	
	public function render()
	{
		$this->showDescription();
		$this->addMainContent($this->content);
	}
	
	public function setContent($content)
	{
		$this->content = $content;
		return $this;
	}
	
} // Silva_View_Html
