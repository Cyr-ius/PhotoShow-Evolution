<?php
/**
 * This file implements the class Video.
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
 * @author    Franck Royer <royer.franck@gmail.com>
 * @copyright 2012 Thibaud Rohmer
 * @license   http://www.gnu.org/licenses/
 * @link      http://github.com/thibaud-rohmer/PhotoShow
 */

/**
 * Image
 *
 * The Video is displayed in the ImagePanel. This file
 * implements its displaying.
 * Video File : only ogv (free licence)
 *
 * @category  Website
 * @package   Photoshow
 * @license   http://www.gnu.org/licenses/
 */

class Video implements HTMLObject
{
	/// URLencoded version of the relative path to file
	static private $fileweb;
	static private $targetpath;
	static private $filename;
	static private $extension;
	
     
	/**
	 * Create Video
	 *
	 * @param string $file 
	 * @author C�dric Levasseur
	 */
	public function __construct($file=NULL,$forcebig = false){
	
		if( !Judge::view($file)){
			return;
		}
		
		/// Check file type
		if(!isset($file) || !File::Type($file) || File::Type($file) != "Video") {
			return;
		}
		/// Absolute file path
		$this->file = $file;
		
		/// Set relative path (url encoded)
		$this->fileweb	=	urlencode(File::a2r($file));
		
		///Set target path
		$this->targetpath = Settings::$thumbs_dir.dirname(File::a2r($file));
		
		///Set filename without extension
		$this->filename = File::name($this->file);
		
		///Set  extension
		$this->extension = File::extension($this->file);
	}
	
	/**
	 * Create Asynchrone Execution (compatibles Linux/Windows)
	 *
	 * @param string $file 
	 * @return pid of the executed command (only linux)
	 * @author C�dric Levasseur/Franck Royer
	 */	
	public function ExecInBackground($cmd) {	
		error_log('DEBUG/Video: Background Execution : '.$cmd,0);
		$pid = 0;
		if (substr(php_uname(), 0, 7) == "Windows"){
		   $valti = rand();
		   exec("wmic process call create '".$cmd."','".File::root()."'",$output);
		   $out = explode('=',$output[5]);
		   $pid = intval($out[1]);
		} else {
		   exec($cmd . " > /dev/null 2>&1 & echo $!", $output);
		   $pid = intval($output[0]);
		}
        return $pid;
	} 

	/**
	* Compute the duration of a video using ffmpeg
	*
	* @return the duration in seconds
	* @author Franck Royer
	*/
	public function GetDuration($file){
	if(!File::Type($file) || File::Type($file) != "Video"){
	    return;
	}

	if (substr(php_uname(), 0, 7) == "Windows"){
		exec(Settings::$ffmpeg_path.' -i "'.$file.'" 2>&1|findstr Duration', $output);
	} else {
		exec(Settings::$ffmpeg_path.' -i "'.$file.'" 2>&1|grep Duration', $output);
	}

	$duration = $output[0];

	$duration_array = explode(':', $duration);
	$duration = intval($duration_array[1]) * 3600 + intval($duration_array[2]) * 60 + intval($duration_array[3]);

	//error_log('DEBUG/Video: duration of '.$file.' is '.$duration.' seconds');
	return $duration;
	}

	/**
	* Compute the dimension of a video using ffmpeg
	*
	* @return the dimension in a array of int
	* @author Franck Royer
	*/
	public function GetScaledDimension($file, $x = 0, $y = 0){

	if(!File::Type($file) || File::Type($file) != "Video"){
	    return;
	}

	if (substr(php_uname(), 0, 7) == "Windows"){
		exec(Settings::$ffmpeg_path.' -i "'.$file.'" 2>&1|findstr Video', $output);
	} else {
		exec(Settings::$ffmpeg_path.' -i "'.$file.'" 2>&1|grep Video', $output);
	}
	$line = $output[0];
	preg_match('/[0-9]{2,4}x[0-9]{2,4}/', $line, $matches);
	$match = $matches[0];


	$dimensions_array = explode('x', $match);
	$orig_x = intval($dimensions_array[0]);
	$orig_y = intval($dimensions_array[1]);
	error_log('DEBUG/Video: original dimension of '.$file.' is '.$orig_x.'x'.$orig_y);

	//If for some reason ffmpeg cannot get the dimension
	if ($orig_x == 0 || $orig_y == 0){
	    //~ error_log('ERROR/Video: dimension of '.$file.' is '.$orig_x.'x'.$orig_y);
	    $orig_x = 320;
	    $orig_y = 240;
	}


	$dimensions = array( 'x' => $orig_x, 'y' => $orig_y );

	if ($x != 0){// wants to know y for the given x
	    $y = ($x*$orig_y) / $orig_x;
	    $dimensions['x'] = $x;
	    $dimensions['y'] = intval($y);
	}
	elseif ($x != 0){// wants to know x for the given y
	    $x = ($y*$orig_x) / $orig_y;
	    $dimensions['x'] = intval($x);
	    $dimensions['y'] = $y;
	}// if both 0 we return original dimensions

	error_log('DEBUG/Video: *scaled* dimension of '.$file.' is '.$dimensions['x'].'x'.$dimensions['y']);
	return $dimensions;
	}
	/**
	* Envoce video
	*
	* @param string $file 
	* @author C�dric Levasseur
	*/
	public function Encode($file) {
	// We check that first to allow the clean of old job files
	if (self::NoJob($file)) {
		$video = new Video($file);
		// Check if thumb folder exist
		if(!file_exists($video->targetpath)){
			@mkdir($video->targetpath,0755,true);
		}
	     $target = $video->targetpath."/".$video->filename.'.'.Settings::$encode_type;
	    if (!file_exists($target) || filectime($file) > filectime($target)){
		if ($video->extension !=Settings::$encode_type) {
		    ///Convert video to Thumbs folder
		    //TODO: Max job limit
		    $u = Settings::$ffmpeg_path.' -i "'.$file.'" '.Settings::$ffmpeg_option.' -y "'.$target.'"';		
		    $pid = self::ExecInBackground($u);
		    self::CreateJob($file, $pid);
		}
		else {
		    ///Copy original video to Thumbs folder
		    copy($file,$target);
		}
	    }
	}
	}
	/**
	* Create Thumbnail  for a video file
	*
	* @param string $file 
	* @author C�dric Levasseur
	*/    
	public static function Thumb($file) {
		$video = new Video($file);
		// Check if thumb folder exist
		if(!file_exists($video->targetpath)){
			@mkdir($video->targetpath,0755,true);
		}
		$target = $video->targetpath."/".$video->filename.'_thumb.jpg';
		$offset = Video::GetDuration($file)/2;
		$dimensions = Video::GetScaledDimension($file, 320);
		$u=Settings::$ffmpeg_path.' -itsoffset -'.$offset.'  -i "'.$file.'" -vcodec mjpeg -vframes 1 -an -f rawvideo -s '.$dimensions['x'].'x'.$dimensions['y'].' -y "'.$target.'"';
		exec($u);
		self::Encode($file);
	}

	/**
	 * Check if a job is running for the conversion
	 * of a video described in the argument
	 * Clean existing job files if necessary
	 *   
	 * @return bool if No Job is running for this video
	 * @author Franck Royer
	 */
    public static function NoJob($file) {
        $file_file	       = new File($file);
        $job_filename = Settings::$thumbs_dir.dirname(File::a2r($file))."/".$file_file->name.'.job';

        if (!file_exists($job_filename))
        {
            return true;
        }

        $job_file = fopen($job_filename, "r");

        if (!$job_file)
        {
            error_log('ERROR/Video: Cannot read '.$job_filename.', deleting if possible.');
            unlink($job_filename);
            return true;
        }

        $pid = fgets($job_file);
        fclose($job_file);

	if (substr(php_uname(), 0, 7) == "Windows"){
		exec('tasklist |find /N /C "'.$pid.' "', $output);
	} else {
		exec('ps ax | grep '.$pid.' | grep -v grep -c', $output);
	}
        if ($pid && $pid != '' && $pid != '0' && intval($output[0]) > 0) {
            // Process is still running
            error_log('DEBUG/Video: job '.$pid.' is still running for '.$file);
            return false;
        } else { // Process is not running, delete job file
            error_log('DEBUG/Video: job '.$pid.' is not running, deleting '.$job_filename);
            unlink($job_filename);
            return true;
        }
    }

	/**
     * Create a job file
	 *   
	 * @return void
	 * @author Franck Royer
	 */
    public static function CreateJob($file, $pid) {
        if (!self::NoJob($file)){
            error_log('ERROR/Video: job for '.$file.' already exists, not creating second job file');
            return;
        } 
        if ( !$pid || $pid == '' || $pid == '0'){
            error_log('ERROR/Video: pid for '.$file.' is invalid, not creating job file');
            return;
        }
	
        // Open file
        $file_file	       = new File($file);
        $job_filename = Settings::$thumbs_dir.dirname(File::a2r($file))."/".$file_file->name.'.job';
        $job_file = fopen($job_filename, "w");

        if (!$job_file) {
            error_log('ERROR/Video: Cannot write on '.$job_filename.'.');
            return;
        }

        error_log('DEBUG/Video: store PID '.$pid.' in '.$job_filename);
        fwrite($job_file, $pid);
        fclose($job_file);
    }

    //TODO: center the video on y axis
    public function VideoDiv($width='',$height='100%',$control=false){
	$c = null;
	$wh = ' height="'.$height.'" width="'.$width.'"';
        if ($control) {
            $c = ' autobuffer preload="none" poster="?t=Thb&f='.$this->fileweb.'"';
        }
        echo '<video id="video"'.$wh.$c.' class="video-js vjs-default-skin vjs-big-play-centered">
		<source src="?t=Vid&f='.$this->fileweb.'" type="video/'.Settings::$encode_type.'" />
		Your browser does not support the video tag.<br />
		Please upgrade your brower or Download the codec <a href="http://tools.google.com/dlpage/webmmf">Download</a>
		</video>';
	}	
	
	/**
	 * Display the video on the website
	 *
	 * @return void
	 * @author C�dric Levasseur
	 */
	public function toHTML(){	
		echo "<div id='c_video' class='current'>";
		self::VideoDiv('','',true);
		echo "</div>";
	}

}

?>
