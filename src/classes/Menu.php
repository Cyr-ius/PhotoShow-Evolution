<?php
/**
 * This file implements the class Menu.
 * 
 * PHP versions 4 and 5
 *
 * LICENSE:
 * 
 * This file is part of PhotoShow.
 *
 * PhotoShow is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * PhotoShow is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PhotoShow.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Website
 * @package   Photoshow
 * @author    Thibaud Rohmer <thibaud.rohmer@gmail.com>
 * @copyright 2011 Thibaud Rohmer
 * @license   http://www.gnu.org/licenses/
 * @link      http://github.com/thibaud-rohmer/PhotoShow
 */

/**
 * Menu
 *
 * Creates a menu, by creating Menu instances for 
 * each directory in $dir.
 *
 * @category  Website
 * @package   Photoshow
 * @author    Thibaud Rohmer <thibaud.rohmer@gmail.com>
 * @copyright Thibaud Rohmer
 * @license   http://www.gnu.org/licenses/
 * @link      http://github.com/thibaud-rohmer/PhotoShow
 */
class Menu implements HTMLObject
{
	/// Name of current directory
	public $title;
	
	/// HTML Class of the div : "selected" or empty
	public $class;
		
	/// HTML-formatted relative path to file
	private $webdir;
	
	/// Array of Menu instances, one per directory inside $dir
	private $items=array();
	private $categories=array();
	
	/// Aray Menu
	private $menu=array();
	
	/// Relative path to file
	private $path = "";
	
	/// Token path
	private $tokenpath;
	
	
	
	private $html;
	
	public function __construct($dir=null,$level=0){
		$this->menu = $this->CsMenu();
		if(CurrentUser::$token){$this->tokenpath=GuestToken::get_path(CurrentUser::$token);}
	}
	
	public function toHTML(){
		echo $this->ListFolder('',0,$this->menu);	
	}

	public function CsMenu($dir=null,$level=0,$item_prec=null){	
		/// Init Menu 
		if($dir == null)
			$dir = Settings::$photos_dir;
		
		/// Check rights
		if(!(Judge::view($dir) || Judge::searchDir($dir))){
			return;
		}	

		if(!CurrentUser::$admin && !CurrentUser::$uploader && sizeof($this->list_files($dir,true,false,true)) == 0){
			return;
		}
		
		/// Set variables
		$title = basename($dir);
		$webdir= urlencode(File::a2r($dir));
		$apath  = File::a2r($dir);	

		try{
			/// Check if selected dir is in $dir
			File::a2r(CurrentUser::$path,$dir);
			$selected 	="selected";
			$actived 		="active";
			
		}catch(Exception $e){
			/// Selected dir not in $dir, or nothing is selected			
			$selected 	="";
			$actived 		="";
		}

		$this->categories[$level][] = array('title'=>$title,'categorie_id'=>$title,'parent_id'=>$item_prec,'path' => $apath ,'selected' => $selected,'active'=>$actived);
		$item_prec = $title;
		$subdirs = $this->list_dirs($dir);

		if(Settings::$reverse_menu){
			$subdirs = array_reverse($subdirs);
		}
		foreach($subdirs as $d){
				self::CsMenu($d,$level+1,$item_prec);
		}
		return $this->categories;
	}	
	
	private function ListFolder($parent, $niveau, $array) {
			$niveau_precedent = 0;
			if (!$niveau && !$niveau_precedent) {
				$html = "<a data-target='.nav-menu' data-toggle='collapse' class='btn btn-navbar btn-navbar-menu '>Menu</a>";				
				$html .= "<div  class='nav-menu collapse'>\n
						<ul class='nav nav-pills nav-stacked  root' style='margin-bottom:0px;'>\n";
			}
			//~ if (!is_array($array[$niveau])) {return $html;} //Cette ligne corrige le fait qu'un r�pertoire fils peut avoir le m�me nom que son p�re
			foreach($array[$niveau] as $item) {
				if ($parent == $item['parent_id']) {
					if ($niveau_precedent < $niveau) $html .= "<ul class='nav nav-pills nav-stacked' style='margin-bottom:0px;'>\n";
					$html .= "<li class='submenu menu_title ".$item['selected']." ".$item['active']."'>";
					$html .= "<span class='name hide'>".htmlentities($item['title'], ENT_QUOTES ,'UTF-8')."</span>";
					$html .= "<span class='path hide'>".htmlentities($item['path'], ENT_QUOTES ,'UTF-8')."</span>";
					if ($this->tokenpath == $item['path']) {
						$html .= "<a href='?f=".urlencode($item['path'])."&token=".CurrentUser::$token."'>".htmlentities($item['title'], ENT_QUOTES ,'UTF-8')."</a>";					
					} else {
						$html .= "<a href='?f=".urlencode($item['path'])."'>".htmlentities($item['title'], ENT_QUOTES ,'UTF-8')."</a>";					
					}
					$niveau_precedent = $niveau;
					$html .= self::ListFolder($item['categorie_id'], ($niveau + 1), $array);
				} 
			}
			if (($niveau_precedent == $niveau) && ($niveau_precedent != 0)) $html .= "</ul>\n</li>\n";
			else if ($niveau_precedent == $niveau) $html .= "</ul>\n</div>\n";
			else $html .= "</li>\n";	
			return $html;
	}
	
	/**
	 * List directories in $dir, omit hidden directories
	 *
	 * @param string $dir 
	 * @return void
	 * @author Thibaud Rohmer
	 */
	public static function list_dirs($dir,$rec=false, $hidden=false){
		
		/// Directories list
		$list=array();

		/// Check that $dir is a directory, or throw exception
		if(!is_dir($dir)) 
			throw new jsonRPCException("'".$dir."' is not exist");
			
		/// Directory content
		$dir_content = scandir($dir);

		if (empty($dir_content)){
		    // Directory is empty or no right to read
		    return $list;
		}
		
		/// Check each content
		foreach ($dir_content as $content){
			
			/// Content isn't hidden and is a directory
			if(	($content[0] != '.' || $hidden) && is_dir($path=$dir."/".$content)){
				
				/// Add content to list
				$list[]=$path;

				if($rec){
					$list = array_merge($list,Menu::list_dirs($dir."/".$content,true));
				}
			}
		}
		/// Return directories list
		return $list;
	}
	
	/**
	 * List files in $dir, omit hidden files
	 *
	 * @param string $dir 
	 * @return void
	 * @author Thibaud Rohmer
	 */
	public static function list_files($dir,$rec = false, $hidden = false, $stopatfirst = false){
		/// Directories list
		$list=array();
		
		/// Check that $dir is a directory, or throw exception
		if(!is_dir($dir)) 
			throw new jsonRPCException("'".$dir."' is not exist");
			
		/// Directory content
		$dir_content = scandir($dir);

		if (empty($dir_content)){
		    // Directory is empty or no right to read
		    return $list;
		}
		
		/// Check each content
		foreach ($dir_content as $content){
			/// Content isn't hidden and is a file
			if($content[0] != '.' || $hidden){
				if(is_file($path=$dir."/".$content)){
					if(File::Type($path) && (File::Type($path) == "Image" || File::Type($path)=="Video")){
						/// Add content to list
						$list[]=$path;

						/// We found the first one
						if($stopatfirst){
							return $list;
						}
					}
				}else{
					if($rec){
						$list = array_merge($list,Menu::list_files($dir."/".$content,true));
					}
				}
			}
		}
		/// Return files list
		return $list;
	}
	
}
?>
