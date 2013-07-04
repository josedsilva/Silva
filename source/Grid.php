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
 * Wrapper class for Curry_Flexigrid_Propel
 *
 * @category    Curry CMS
 * @package     Silva
 * @author      Jose Francisco D'Silva
 * @version
 *
 */
class Silva_Grid extends Curry_Flexigrid_Propel
{
    protected $tableMap = null;
    protected static $thumbnailProcessor = null;
    protected $url = null;
    protected $defaultDialogOptions = array('width'=>600, 'minHeight'=>150/*, 'show' => 'scale', 'hide' => 'scale'*/);

    public function __construct(TableMap $tableMap, $url, $options = array(), $query = null, $id = null, $title = null)
    {
        parent::__construct($tableMap->getPhpName(), $url, $options, $query, $id, $title);
        $this->url = $url;
        $this->tableMap = $tableMap;
    }
    
    /**
     * Behaves just like Curry_Flexigrid::addLinkButton except that it supports a callback parameter
     * in $buttonOptions which can be used to selectively continue with further processing or abort operation.
     * The callback must return a json hash: {'message': 'some optional message', 'abort': true/false}
     *
     * @param $name
     * @param $bclass
     * @param $url
     * @param integer|array $forcePrimaryKey		How many rows must be selected before the operation begins execution?
     * This value can contain a range such as array('min' => min-value, 'max' => max-value)
     * @param $buttonOptions
     */
		public function addLinkButton($name, $bclass, $url, $forcePrimaryKey = 0, $buttonOptions = array())
		{
				if($forcePrimaryKey < 0) {
					$onPress = "function(com, grid) {
						window.location.href = '$url';
					}";
				} else {
					$callback = isset($buttonOptions['callback']) ? $buttonOptions['callback'] : '';
					$callbackUrl = $callback ? url('', array('module', 'view' => $callback, 'locale')) : '';
					if (is_integer($forcePrimaryKey) && $forcePrimaryKey) {
					    $fpkExpr = "items.length == {$forcePrimaryKey}";
					} elseif (is_array($forcePrimaryKey)) {
					    $fpkExpr = '';
					    if (isset($forcePrimaryKey['min'])) {
					        $fpkExpr = "items.length >= {$forcePrimaryKey['min']}";
					    }
					    
					    if (isset($forcePrimaryKey['max'])) {
					        $fpkExpr .= (isset($forcePrimaryKey['min']) ? " && " : "") . "items.length <= {$forcePrimaryKey['max']}";
					    }
					}
					
					$onPress = "function(com, grid) {
						var items = $('.trSelected', grid);
						".($forcePrimaryKey ? "if({$fpkExpr}) {" : "")."
							var ids = $.map(items, function(item) { return $.data(item, '{$this->primaryKey}'); });
							if ('{$callback}'.length) {
								$.get('{$callbackUrl}', {'id[]': ids}, function(data){
									if (data.message && data.message.length) {
										$.util.infoDialog((data.title && data.title.length) ? data.title : '{$callback} says', data.message, function(){
											if (data.abort) {
												items.removeClass('trSelected');
											} else {
												window.location.href = '$url&' + $.param({'{$this->primaryKey}': ids.length == 1 ? ids[0] : ids});
											}
										});
									} else if (data.abort) {
										alert('The operation was aborted');
										items.removeClass('trSelected');
									} else if (! data.abort) {
										window.location.href = '$url&' + $.param({'{$this->primaryKey}': ids.length == 1 ? ids[0] : ids});
									}
								});
							} else {
								window.location.href = '$url&' + $.param({'{$this->primaryKey}': ids.length == 1 ? ids[0] : ids});
							}
						".($forcePrimaryKey ? "}" : "")."
					}";
				}
				
				$this->addButton($name, array_merge($buttonOptions, array('bclass' => $bclass, 'onpress' => new Zend_Json_Expr($onPress))));
		}
		
    /**
     * Json encode data. Handles encoding and functions.
     *
     * @param mixed $value
     * @param string $encoding
     * @return string
     */
    protected static function json_encode($value, $encoding = 'utf-8')
    {
    	$value; // if I remember correctly this had to go here to prevent a crash in some php-version :)
    	// make sure we convert all strings to utf8
    	array_walk_recursive($value, create_function('&$value', '
    		if(is_string($value))
    			$value = Curry_String::toEncoding($value, "utf-8");
    		if($value instanceof Zend_Json_Expr)
    			$value = new Zend_Json_Expr(Curry_String::toEncoding((string)$value, "utf-8"));
    	'));
    	// return string in proper encoding
    	return iconv('utf-8', $encoding, Zend_Json::encode($value, false, array('enableJsonExprFinder' => true)));
    }
    
    public function addDialogButton($name, $bclass, $dialogId, $dialogTitle, $dialogUrl, array $dialogOptions = array(), $forcePrimaryKey = 0, $reloadOnClose = true, $buttonOptions = array())
    {
        $opts = array_merge($this->defaultDialogOptions, $reloadOnClose ? array('close' => new Zend_Json_Expr("function() { $('#{$this->id}').flexReload(); }")) : array(), $dialogOptions);
        $opts = self::json_encode($opts, Curry_Core::$config->curry->internalEncoding); // keep the internal encoding
        
        if($forcePrimaryKey < 0) {
        	$onPress = "function(com, grid) {
        		$.util.openDialog('$dialogId', '$dialogTitle', '$dialogUrl', $opts);
        	}";
        } else {
					$callback = isset($buttonOptions['callback']) ? $buttonOptions['callback'] : '';
					$callbackUrl = $callback ? url('', array('module', 'view' => $callback, 'locale')) : '';
					if (is_integer($forcePrimaryKey) && $forcePrimaryKey) {
					    $fpkExpr = "items.length == {$forcePrimaryKey}";
					} elseif (is_array($forcePrimaryKey)) {
					    $fpkExpr = '';
					    if (isset($forcePrimaryKey['min'])) {
					        $fpkExpr = "items.length >= {$forcePrimaryKey['min']}";
					    }
					    
					    if (isset($forcePrimaryKey['max'])) {
					        $fpkExpr .= (isset($forcePrimaryKey['min']) ? " && " : "") . "items.length <= {$forcePrimaryKey['max']}";
					    }
					}
					
        	$onPress = "function(com, grid) {
        		var items = $('.trSelected', grid);
        		".($forcePrimaryKey ? "if({$fpkExpr}) {" : "")."
        			var ids = $.map(items, function(item) { return $.data(item, '{$this->primaryKey}'); });
        			if ('{$callback}'.length) {
        				$.get('{$callbackUrl}', {'id[]': ids}, function(data){
    								if (data.message && data.message.length) {
    									$.util.infoDialog((data.title && data.title.length) ? data.title : '{$callback} says', data.message, function(){
    										if (data.abort) {
    											items.removeClass('trSelected');
    										} else {
    											$.util.openDialog('$dialogId', '$dialogTitle', '$dialogUrl&' + $.param({'{$this->primaryKey}': ids.length == 1 ? ids[0] : ids}), $opts);
    										}
    									});
    								} else if (data.abort) {
    									alert('The operation was aborted');
    									items.removeClass('trSelected');
    								} else if (! data.abort) {
    									$.util.openDialog('$dialogId', '$dialogTitle', '$dialogUrl&' + $.param({'{$this->primaryKey}': ids.length == 1 ? ids[0] : ids}), $opts);
    								}
        				});
        			} else {
        				$.util.openDialog('$dialogId', '$dialogTitle', '$dialogUrl&' + $.param({'{$this->primaryKey}': ids.length == 1 ? ids[0] : ids}), $opts);
        			}
        		".($forcePrimaryKey ? "}" : "")."
        	}";
        }
        
        $this->addButton($name, array_merge($buttonOptions, array('bclass' => $bclass, 'onpress' => new Zend_Json_Expr($onPress))));
    }
    	
    /**
     * Behaves just like Curry_Flexigrid::addDeleteButton except that
     * JSON data can be returned (with Curry_Application::returnJson) from the JSON handler.
     * returned json: {'message': 'some message to show in the info box'}
     */
    public function addDeleteStatusButton($options = array())
    {
        $this->addButton("Delete", array_merge((array) $options, array("bclass" => "icon_delete", "onpress" => new Zend_Json_Expr("function(com, grid){
        	var items = $('.trSelected', grid);
        	if(items.length && confirm('Delete ' + items.length + ' {$this->options['title']}? \\nWARNING: This cannot be undone.')) {
        		var ids = $.map(items, function(item) { return $.data(item, '{$this->primaryKey}'); });
        		$.post('{$this->options['url']}', {cmd: 'delete', 'id[]': ids}, function(data){
        			if(data.message && data.message.length) {
        				$.util.infoDialog('Delete {$this->tableMap->getPhpName()} status:', data.message, function(){
        					$('#{$this->id}').flexReload();
        				});
        			} else {
        				$('#{$this->id}').flexReload();
        			}
        		});
        	}
        }"))));
    }

    /**
     * Put a "Toggle select" button on the grid.
     * @param string $name
     * @param array $options
     */
    public function addToggleSelectButton($name = "Select all", $options = array())
    {
        $onPress = "function(){
            var \$gridTable = \$('#{$this->id}');
            \$gridTable.find('tr').each(function(){
            	if(\$(this).hasClass('trSelected')){
            		\$(this).removeClass('trSelected');
            	} else {
            		\$(this).addClass('trSelected');
            	}
            	});
            return false;
        }";

        $this->addButton($name, array_merge($options, array(
            'forcePrimaryKey' => 0,
            'onpress' => new Zend_Json_Expr($onPress),
        )));
    }

    /**
     * Disable the drag&drop feature of the flexigrid.
     * The drag&drop feature is automagically enabled when the model has a sortable behavior.
     */
    public function disableDragDrop()
    {
        if (Silva_View_BaseModel::hasBehavior('sortable', $this->tableMap)) {
            $this->setOption('onSuccess', null);
        }
    }

    /**
     * Add a search-item feature to the flexigrid.
     * @param array|null $columns
     * @example $columns = array("field1", "field2")
     * @example $columns = array("field1" => "Display1", "field2" => "Display2")
     */
    public function addSearch($columns = null)
    {
        if ($columns === null) {
            $columns = $this->getTextColumnNames();
        }

        foreach ($columns as $k => $v) {
            if (is_numeric($k)) {
                $field = $v;
                $display = ucwords(str_replace(array('_'), array(' '), $v));
            } else {
                $field = $k;
                $display = $v;
            }
            $this->addSearchItem($field, $display);
        }
    }

    /**
     * Return an array of lowercase column names having the specified PropelColumnTypes.
     * @param array $columnTypes: Propel column types, @see PropelColumnTypes
     * @return array
     */
    public function getColumnNamesForTypes(array $columnTypes)
    {
        $columnNames = array();
        foreach ($this->tableMap->getColumns() as $column) {
            if (! in_array($column->getType(), $columnTypes)) {
                continue;
            }

            $columnNames[] = strtolower($column->getName());
        }

        return $columnNames;
    }

    /**
     * Return an array of lowercase column names for VARCHAR and LONGVARCHAR types.
     * @return array
     */
    public function getTextColumnNames()
    {
        return $this->getColumnNamesForTypes(array(
            PropelColumnTypes::VARCHAR,
            PropelColumnTypes::LONGVARCHAR,
        ));
    }

    /**
     * Create the ImageProcessor object.
     * Requires the Gallery package to be installed.
     * @see Packages -> Gallery
     *
     * @param integer $twd Width of the thumbnail in pixels
     * @param integer $tht Height of the thumbnail in pixels
     */
    protected static function getThumbnailProcessor($twd = 50, $tht = 50)
    {
        if (! self::$thumbnailProcessor) {
            if (! class_exists('ImageProcessor')) {
                throw new Silva_Exception('ImageProcessor not found. Please install the Gallery package.');
            }

            self::$thumbnailProcessor = new ImageProcessor();
            self::$thumbnailProcessor
                ->setOutputFolder(Silva_Helpers::getTempPath())
                ->setOutputFormat(ImageProcessor::FORMAT_PNG)
                ->setResize(true)
                ->setResizeType(ImageProcessor::RESIZE_FIT_BOX)
                ->setResizeWidth($twd)
                ->setResizeHeight($tht);
        }

        return self::$thumbnailProcessor;
    }

    /**
     * Return the HTML to display the thumbnail.
     * Do not use this method outside the class.
     * It's access level is public because it was intended to be a callback method.
     *
     * @param string $src
     * @param integer $twd
     * @param integer $tht
     * @param string $flexId
     * @param string $getter
     * @param string $pk: The flexigrid's identifier
     *
     * @return string
     */
    public static function getThumbnailHtml($src, $twd, $tht, $flexId, $getter = '', $pk = '')
    {
        return $src ?
        	'<a href="'.$src.'" class="'.($getter ? 'silva-image-edit' : 'silva-image-preview').'" data-flexid='.$flexId.($getter ? ' data-getter="'.$getter.'" data-pk="'.$pk.'"' : '').'>
        		<img src="'.self::getThumbnailProcessor($twd, $tht)->processImage($src).'" />
        	 </a>' :
        	 '[No Thumbnail]';
    }

    /**
     * Add a column to the flexigrid that shows a thumbnail.
     * @param string  $column: Column name
     * @param string  $display: Column header text
     * @param string  $getter: Dotted getter to retrieve the image path (e.g. Product.Image)
     * @param integer $twd: Thumbnail width in pixels
     * @param integer $tht: Thumbnail height in pixels
     * @param boolean $previewOnly: Whether to preview or edit image?
     *
     * @example $flexigrid->addThumbnail('thumb', 'Thumbnail', 'Product.Image');
     */
    public function addThumbnail($column, $display, $getter, $twd = 50, $tht = 50, $previewOnly = true)
    {
        $this->addRawColumn($column, $display);
        $phpGetter = join('->', array_map(
            create_function('$e', 'return "get".$e."()";'),
            explode('.', $getter)));
        $callback = create_function('$o', "return " . __CLASS__ . "::getThumbnailHtml(\$o->{$phpGetter}, $twd, $tht, '{$this->id}'".($previewOnly ? "" : ",'$getter', '{$this->primaryKey}'").");");
        $this->setColumnCallback($column, $callback);
    }
    
    /**
     * Show thumbnail for a column in this model.
     * @param string  $column
     * @param string|null  $display: The column heading
     * @param integer $twd: Thumbnail width (in pixels)
     * @param integer $tht: Thumbnail height (in pixels)
     * @param boolean $previewOnly: Whether to preview or edit image?
     */
    public function setThumbnail($column, $display = null, $twd = 50, $tht = 50, $previewOnly = true)
    {
    	if ($display === null) {
    		$display = ucwords(str_replace("_", " ", $column));
    	}
    	
    	$getter = str_replace(" ", '', ucwords(str_replace("_", " ", $column)));
    	$this->addThumbnail($column, $display, $getter, $twd, $tht, $previewOnly);
    }
    
    public function setEditableThumbnail($column, $display = null, $twd = 50, $tht = 50)
    {
        $this->setThumbnail($column, $display, $twd, $tht, false);
    }
    
    public function hideColumns(array $columns)
    {
        $this->setColumnOption($columns, 'hide', true);
    }
    
    public function moveColumnFirst($column)
    {
        $this->moveColumn($column, 0);
    }
    
    public function moveColumnLast($column)
    {
        $this->moveColumn($column, -1);
    }
    
    public function orderColumns(array $columns)
    {
        $index = 1;
        foreach ($columns as $column) {
            $this->moveColumn($column, $index);
            ++ $index;
        }
    }

    /**
     * Add a raw column (non-escaped contents) to the grid.
     * @param string $column
     * @param string $display
     * @param array  $columnOptions
     */
    public function addRawColumn($column, $display, array $columnOptions = array())
    {
        $this->addColumn($column, $display, array_merge(array('sortable' => false, 'escape' => false), $columnOptions));
    }
    
    /**
     * Convert an existing column to raw.
     * @param $column
     * @param $display
     * @param $columnOptions
     */
    public function setRawColumn($column, $display = null, array $columnOptions = array())
    {
    	if ($display === null) {
    		$display = ucwords(str_replace("_", " ", $column));
    	}
    	
    	$this->addRawColumn($column, $display, $columnOptions);
    }

    /**
     * Return the url passed to the Curry_Flexigrid_Propel constructor
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }


} //Silva_Grid
