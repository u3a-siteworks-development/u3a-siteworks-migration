<?php
// this class is for processing blocks of text from text tag to produce the heml code.

class html
{
    public $contactname = "";
    public $contactemail = "";
    public $text = "";
    public $newtext = "";
    public $imgtext = "";
    public $preurl = "";
    public $logtext = "";
    public $missing = "";
    public $done;



    function __construct($text, $done = array())
    {
        //$text is expected to be in xml format but not an xml object
        $this->text = $text;
        $this->newtext = $text;
        $uploads = wp_upload_dir();
        $this->preurl = $uploads['url'] . '/';
        $this->done = $done;
    }
    function innerHTML($text)
    {
        $result = $text;
        if (strpos($text, "<sect") > -1) {
            $posstart = strpos($text, '>') + 1;
            $result =  substr($text, $posstart);
            $result = str_replace("</section>", "", $result);
        }
        return $result;
    }
    //after processing when no longer xml, removes hashes and some tags
    /*function removesometags($text){
    //$text = $this->replacehash($text);
    $text=$this->innerHTML($text);
    //$text=preg_replace('/<p>\s*(LineBreak\s*)*<\/p>/i' , '' , $text);  
    $text = str_replace('<pic>',"",$text);
    $text = str_replace('</pic>',"",$text);
    $text=str_replace("<text>","",$text);
    $text=str_replace("</text>","",$text);
    $text=str_replace("ampersand","&",$text);
    //try and remove surplus space
    //$text=preg_replace("/<br\s*>/","",$text);
    // these attempted to deal with bullets but don't work at present so commented out.'
    //$text=str_replace("&#x2022;","</p><p>&#x2022;",$text);
    //$text=str_replace("<p><br />","<p>",$text);
    return $text;
}*/

    //processes the text using the process class
    //stores the contact names obtained via process class
    function process()
    {
        //$this->text=$this->modify($this->text);  
        if (!empty($this->text)) {
            $proc = new process($this->text, false, $this->done);
            $proc->doxml();
            //check errors in text
            if ($proc->exists) {
                $proc->replacetags();
                $this->imgtext = $proc->imgtext;
                $this->text = $this->innerHTML($proc->newtext);
                //$this->text=$this->removesometags($this->text);
                $this->contactname = $proc->contactname;
                $this->contactemail = $proc->contactemail;
            }
            $this->logtext .= $proc->logtext;
            $this->missing .= $proc->missing;
            $this->done = $proc->done;
            //echo($this->text);exit;
        }
    }


    // code to try and find event title from event text.
    /**
     * Extract a sensible title from an event's or notice's text description
     * At this point when called from u3a_migration_notices() or addevents() or process() $this->text has not been through modify() so line ends are still present
     * TODO - Check this function if line ends are removed
     *
     * @param [type] $startdate
     * @return void
     */
    function findtitle($startdate)
    {
        // Simple alternative - just work on the first line
        $matchlines = [];
        preg_match('~(?<=<p>).*\R~', $this->text, $matchlines);  // TODO This will break if the text is run through modify()
        $title = (empty($matchlines[0])) ? '' : $matchlines[0];
        // The above only works for an event from the eventlist file
        // For an event in a group, extract start of <text> 
        if (empty($title) &&  (substr($this->text, 0, 6) == '<text>')) {
            $title = substr($this->text, 6, 100);
        }
        if (empty($title)) {
            return "Event on " . $startdate;
        }

        // If we have some text, remove SiteBuilder markup and return max 8 words
        $title = str_replace(['#', '{', '}', '_', '|'], '', $title);
        return wp_trim_words($title, 8);

        // TODO rest of this function ignored for now


        $title = "";
        if (strlen($this->text) > 0) {
            $pos1 = 0;
            $pos2 = 0;
            if (strpos($this->text, "<br/>") > -1) {
                $temp = explode("<br/>", $this->text, 2);
                if (count($temp) > 0) {
                    $title = $temp[0];
                    $this->text = $temp[1];
                }
                $this->text = str_replace($title, "", $this->text);
            } elseif (strpos($this->text, "<br />") > -1) {
                $temp = explode("<br />", $this->text, 2);
                if (count($temp) > 0) {
                    $title = $temp[0];
                    $this->text = $temp[1];
                }
                $this->text = str_replace($title, "", $this->text);
            } elseif (strpos($this->text, "<h3>") > -1) {
                $pos1 = strpos($this->text, "<h3>");
                $pos2 = strpos($this->text, "</h3>");
                if ($pos2 > $pos1) {
                    $title = substr($this->text, $pos1 + 4, $pos2 - $pos1 - 4);
                }
                $this->text = str_replace($title, "", $this->text);
            } elseif (strpos($this->text, "<strong>") > -1) {
                $pos1 = strpos($this->text, "<strong>");
                $pos2 = strpos($this->text, "</strong>");
                if ($pos2 > $pos1) {
                    $title = substr($this->text, $pos1 + 8, $pos2 - $pos1 - 8);
                }
                $this->text = str_replace($title, "", $this->text);
            } elseif (strpos($this->text, "<b>") > -1) {
                $pos1 = strpos($this->text, "<b>");
                $pos2 = strpos($this->text, "</b>");
                if ($pos2 > $pos1) {
                    $title = substr($this->text, $pos1 + 8, $pos2 - $pos1 - 8);
                }
                $this->text = str_replace($title, "", $this->text);
            } else {
                $title = "Event on " . $startdate;
            }
            $title = str_replace("ampersand", "&", $title);
            if (strpos($title, "<a") > -1) {
                $this->text = $title . " " . $this->text;
                $pos0 = strpos($this->text, "<a");
                $pos1 = strpos($this->text, ">", $pos0);
                $pos2 = strpos($this->text, "<", $pos1);
                $title = substr($this->text, $pos1 + 1, $pos2 - $pos1 - 1);
                //now move link to end
                $pos3 = strpos($this->text, ">");
                $pos4 = strpos($this->text, "/a>");
                $link = substr($this->text, $pos3 + 1, $pos4 + 3 - $pos3);
                $this->text = str_replace($link, "", $this->text);
                $this->text = $this->text . $link . "<br />";
            }
            if (strpos($title, ".") > -1) {
                $temp = explode(".", $title, 2);
                if (count($temp) > 0) {
                    $title = $temp[0];
                    $this->text = $temp[1] . " " . $this->text;
                }
            }
            //This will restrict title length but result may well not be sensible
            /*if(strlen($title)>30){
               $title=wordwrap($title,30,'\n');
               $pos=strpos($title,'\n');
               $title=substr($title,0,$pos);
               $this->text=substr($title,$pos+1).$this->text;
           }*/
        }

        return $title;
    }
}
