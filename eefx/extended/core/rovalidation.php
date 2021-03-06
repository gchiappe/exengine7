<?php
/**
@file rovalidation.php
@author Giancarlo Chiappe <gch@linkfastsa.com> <gchiappe@gmail.com>
@version 1.0.0.0

@section LICENSE

ExEngine is free software; you can redistribute it and/or modify it under the
terms of the GNU Lesser Gereral Public Licence as published by the Free Software
Foundation; either version 2 of the Licence, or (at your opinion) any later version.
ExEngine is distributed in the hope that it will be usefull, but WITHOUT ANY WARRANTY;
without even the implied warranty of merchantability or fitness for a particular purpose.
See the GNU Lesser General Public Licence for more details.

You should have received a copy of the GNU Lesser General Public Licence along with ExEngine;
if not, write to the Free Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, Ma 02111-1307 USA.

@section DESCRIPTION

Wrapper for class version of http://www.phpro.org/classes/Validation-Class.html

@section TODO

Add some special functions exclusive to ExEngine about PHPRO.org's Validation Class.

*/

class rovalidation {
	private $ee;
	
	function __construct($ee=null) {
		if ($ee == null)		
			$this->ee = &ee_gi();	
		else
			$this->ee = &$ee;
		include_once($this->ee->eeResPath().'vclass/'.'validation-class.php');
	}
}
?>