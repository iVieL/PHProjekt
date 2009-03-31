/**
 * This software is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License version 2.1 as published by the Free Software Foundation
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * @copyright  Copyright (c) 2008 Mayflower GmbH (http://www.mayflower.de)
 * @license    LGPL 2.1 (See LICENSE file)
 * @version    $Id$
 * @author     Sven Rautenberg <sven.rautenberg@mayflower.de>
 * @package    PHProjekt
 * @link       http://www.phprojekt.com
 * @since      File available since Release 6.0
 */

dojo.provide("phpr.Minutes.Grid");

dojo.declare("phpr.Minutes.Grid", phpr.Default.Grid, {
    customGridLayout:function(meta) {
        for (var i = 0; i < this.gridLayout.length; i++) {
            switch (this.gridLayout[i].field) {
                case 'title':
                    this.gridLayout[i].formatter = function(value){ return value; };
                    this.gridLayout[i].width = '80%';
                    break;
                case 'created':
                    this.gridLayout[i].width = '20%';
                    break;
            }
        }
    }
});
