<?php
/*
 Plugin Name: Seo Friendly Table of Contents
 Plugin URI: http://www.webfish.se/wp/plugins/seo-friendly-table-of-contents
 Description: Adds a seo firendly table of contents anywhere you write [toc="2,3,4" title="Table of contents"].
 Version: 1.3.7
 Author: Tobias Nyholm
 Author URI: http://www.tnyholm.se
 Copyright: Tobias Nyholm 2010
/*
    This file is part of Seo Friendly Table of Contents.

    Seo Friendly Table of Contents is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Seo Friendly Table of Contents is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Seo Friendly Table of Contents.  If not, see <http://www.gnu.org/licenses/>.

 */


add_filter('the_content', 'seotocFilter');

//add css
add_action('wp_enqueue_scripts','seotok_addStyle');
function seotok_addStyle(){
	wp_enqueue_style('toc_css',WP_PLUGIN_URL.'/seo-friendly-table-of-contents/style.css');
}

/**
 * Verifies if a table of contents should be here. If so, then we add one.
 * @param String $content
 */
function seotocFilter($content){

	//reges to match if the "[toc=2]" exists
	$tolc_regex = '|(?:<p>)?\[toc=(?:["'."'".'])?([1-7,]+)(?:["'."'".'])?(?: title=(?:["'."'".'])?(.*?)(?:["'."'".'])?)? ?\](?:<br \/>)?(?:<\/p>)?|s';
	$tolc_match = preg_match_all($tolc_regex, $content, $tolc_matches);
	//echo ("<pre>".print_r($tolc_matches,true));

	//if [toc] exists
	if ($tolc_match){
		//split toc parameters into a array
		$tags=split(",",$tolc_matches[1][0]);

		if($tags[count($tags)-1]=="")//if the user has a tailoring coma
		unset($tags[count($tags)-1]);
		//sort
		sort($tags);

		//assert: $tags[0] is the first heading to appear.

		//get the title
		$toc_title=$tolc_matches[2][0];
		$list="";//allcolate some space

		//ad ids to the headings
		seotoc_addIds($tags,$content,$list);

	
		//wrapp the list into some divs and add the title if the list was not empty
		if($list!=""){
			global $post;
			$list="<div id='toc' class='post-".$post->ID."'><div id='toc_title'>$toc_title</div>$list</div>";
		}
			
		//replace the [toc] with the $html
		$content = preg_replace($tolc_regex, $list, $content,1);
	}

	return $content;
}

/**
 * Add the some ids on h#-tags. It also build a html list with links to those tags.
 * @param array $tagsArray, sorted
 * @param String $content, 
 * @param String $list, when the function has been called, this is a html list with heading links (if any)
 * @return nothing $content and $list has the result
 */
function seotoc_addIds(array $tagsArray,&$content,&$list){
	$tags="";
	//make a string form the array
	foreach($tagsArray as $tag)
		if($tag<8)
			$tags.="$tag";
		
	//some regex to recognize the h#-tags
	$tag_regex = '/<h(['.$tags.'])(.*?)>(.*?)(<\/h['.$tags.']>)/';

	$tag_match = preg_match_all($tag_regex, $content, $tag_matches);
	//die ("<pre>".print_r($tag_matches,true));
	
	$lastLevel=-1;//init leven
	$list="";//override $list
	
	//if a h#-tag found.
	if ($tag_match){
		foreach ($tag_matches[3] as $key => $title){
			/*
			 * Prepare some variables
			 */
			//original string
			$orgStr=$tag_matches[0][$key];
				
			//strip any unwanted html
			$noTagsTitle=strip_tags($title);
			
			//kind of ugly slugify. But it works well with special chars
			$replace=array("/\&#[0-9]*?;/"=>"","/[^a-z A-Z0-9]/"=>"","/ /"=>"-");
			$noSpecial= preg_replace(array_keys($replace),array_values($replace), $noTagsTitle);
			
			//this is the h#-tag
			$tag="h".$tag_matches[1][$key];
			
			//save any attributes that the h#-tag allredy has. and then add the id.
			$attributes=$tag_matches[2][$key]." id='$noSpecial'";
			
			//Set a level. 
			$thisLevel=$tag_matches[1][$key];
			

			/*
			 * prepare list
			*/
			if($thisLevel>$lastLevel){//start a sub list
				$list.="\n<ul>";
				$lastLevel=$thisLevel;
			}
			elseif($thisLevel<$lastLevel){//end a sublist
				$list.="</li>";//end the last li on prev listlevel. 
				for($i=$lastLevel;$i>$thisLevel;$i--)
					$list.="</ul></li>"."\n";
				$lastLevel=$thisLevel;
			}
			else{//end a list item
				$list.='</li>'."\n";
			}
			
			//add a list item
			$list.='<li><a href="#'.$noSpecial.'">'.$noTagsTitle.'</a>';//no closing li
				
			/*
			 * write id
			 */
			//replace the original heading with the one with an ID
			$content = str_replace($orgStr, "<$tag$attributes>$title</$tag>", $content);
				
		}
		$list.='</li>'."\n";//close the last opened li
		for($i=$lastLevel;$i>$tagsArray[0];$i--)//close all open tags
			$list.="</ul></li>";
		$list.= "</ul>\n";//close the last ul
	}
	//No need to return anyting. The $content and $list parameter is passed by reference.
}



/**
 * This will return a table of contents. The third param ($content) is send by reference. 
 * This function will add id attributes on the heading tag. You have to use the same content
 * variable to display your content as the one you use to this function. 
 * 
 * If you are using get_the_content() to get the content for this function, then you might
 * want to apply some filters before echoing out the content to the browser. Consider these lines:
 * 
 * $the_content = get_the_content();
 * $the_content = apply_filters('the_content', $the_content);
 * $the_content = str_replace(']]>', ']]&gt;', $the_content);
 * echo getSeo_toc(array(2,3,4),"Table of contents",$the_content);
 * echo $the_content;
 * 
 * @param array $tags like array(2,3,4)
 * @param String $title
 * @param String $content, the page content. The page content will be changed!
 */
function getSeo_toc(array $tags,$title, &$content){
	if($title==""||$title==false||$title==null){
		$title="";
	}
	else{
		global $post;
		$title="<div id='toc_title' class='post-".$post->ID."'>$title</div>";
	}
	
	$list="";
	seotoc_addIds($tags,$content,$list);
	if($list!="")
		$list="<div id='toc'>$title$list</div>";
	
	return $list;
}

?>