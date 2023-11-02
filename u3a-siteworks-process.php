<?php


class process
{
    public $text; //the text being processed- to be kept as xml form
    public $newtext; // the replacement text - may no longer be xml
    public $fxml; // the iterator for the xml
    public $preurl; // the url where docs and images may be found
    public $sec; // is the request sent by a page with sections
    public $exists = true;
    // TODO - no longer using LETTER // public $letter = "";
    public $logtext;
    public $missing;
    public $done;
    var $imgtext; //the generated image text
    var $page; //array of subpages (found on groups.xml)
    var $contactname; // when found on page being processed
    var $contactemail; //whne found on page being processd
    var $imgcount;

    function __construct($text, $sec, $done = array())
    {
        $this->text = $text;
        $this->newtext = $text;
        $this->logtext = "";
        $this->done = $done;
        $this->imgtext = "";
        $this->imgcount = 0;
        // array of subpage filenames;
        $this->page = array();
        $uploads = wp_upload_dir();
        $this->preurl = $uploads['url'] . '/';
        // TODO use BASEURL instead to constuct URL to retrieve files
        // $pos = strpos(get_site_url(), "//");
        // $this->letter = substr(get_site_url(), $pos + 2, 1);
        $this->sec = $sec;
    }


    //dealing with line breaks inside table tag;
    function removelinebreakfromtable($text)
    {
        $result = "";
        $pos1 = strpos($text, "<table>", 0);
        while ($pos1 > -1) {
            $pos2 = strpos($text, "</table>", $pos1);
            $table = substr($text, $pos1, $pos2 - $pos1 + 8);
            $ntable = str_replace("LineBreak", "", $table);
            $result .= substr($text, 0, $pos1) . $ntable;
            $text = substr($text, $pos2 + 8);
            $pos1 = strpos($text, "<table>", 0);
        }
        $result = $result . $text;
        return $result;;
    }
    //html escape codes replaced with actual characters
    function modify($text)
    {

        // Only modify text sections
        if (!str_starts_with($text, '<section id="text">')) {
            return $text;
        }

        //first remove html characters to avoid xml issues
        $text = htmlspecialchars_decode($text);
        // convert symbol to ampersand to avoid XML errors
        $text = str_replace("&", "ampersand", $text);

        // Replace line ends with 'LineBreak' only within <p> tags
        $text = preg_replace('~(?s)(?<!<p>)\R(?!</p>)(?=((?!<p>).)*</p>)~ ', 'LineBreak', $text);

        // Remove stray <br> tags only within <table> tags
        $text = preg_replace('~(?s)(?<!<table>)<br>(?!</table>)(?=((?!<table>).)*</table>)~', '', $text);
        // Replace <br> in other text sections with 'LineBreak'
        $text = str_replace('<br>', 'LineBreak', $text);

        // Reduce any sequence of more than one LineBreak
        $text = preg_replace('~(LineBreak\s*){2,}~ms', 'LineBreak', $text);

        // $text=$this->removelinebreakfromtable($text);      // not needed as we don't add them in tables

        $match = $this->hasMatchedParenthesis($text);
        while (!$match) {
            $pos = strripos($text, "}");
            $text = substr_replace($text, "", $pos, 1);
            $match = $this->hasMatchedParenthesis($text);
        }

        // Convert all sequences that look like centred lines to <hr/>
        $text = preg_replace('/(?<!<p){\s*([_=+*-~])\1{8,}\s*}(?!<\/p>)/ms', '</p><hr/><p>', $text);
        // Convert all other sequences that look like line serparators to <hr/>
        $text = preg_replace('/(?<!<p)\s*([_=*+-~])\1{8,}\s*(?!<\/p>)/ms', '<hr/>', $text);

        // Replace centred headings within <p> tags to '</p><h3>heading</h3><p>'
        // $text = preg_replace('~(?<!<p>){(?!</p>)(?=((?!<p>).)*</p>)~ms', '</p><h3>', $text);
        // $text = str_replace("<p>{", '<h3>', $text);     // TODO - workaround because the above regex doesn't match this case
        // $text = preg_replace('~(?<!<p>)}(?!</p>)(?=((?!<p>).)*</p>)~ms', '</h3><p>', $text);
        // Bug 948.  Don't try and detect <h3> within <p> as any resulting incorrectly matched tags are resolved later.

        // Replace heading markup
        $text = str_replace("{", "<h3>", $text);
        $text = str_replace("}", "</h3>", $text);

        // Remove any LineBreak immediately before or after an <hr/>
        $text = preg_replace("~LineBreak\s*<hr/>~ms", '<hr/>', $text);
        $text = preg_replace("~<hr/>\s*LineBreak~ms", '<hr/>', $text);

        // Remove any self-closing <p/> tag
        $text = str_replace("<p/>", '', $text);

        // Remove any LineBreak before or after a <p> tag
        $text = preg_replace("~<p>\s*LineBreak~ms", '<p>', $text);
        $text = preg_replace("~LineBreak\s*<p>~ms", '<p>', $text);

        // Remove any <p> sections now empty
        $text = preg_replace('~<p>\s*</p>~ms', '', $text);

        // force <B> tags to lower case
        $text = str_replace("<B>", '<b>', $text);
        $text = str_replace("</B>", '</b>', $text);

        // replace all <b> tags with <strong> tags to avoid "unknown formatting" issue after conversion to blocks
        $text = str_replace("<b>", '<strong>', $text);
        $text = str_replace("</b>", '</strong>', $text);

        // Check that at the end of this process we have valid XML
        $prev = libxml_use_internal_errors(true);
        $testdoc = simplexml_load_string($text);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if ($testdoc === false) {
            $this->logtext .= "XML invalid after processing text section starting: " . substr($text, 0, 80);
        }

        return $text;
    }
    //check for matched curly brackets
    function hasMatchedParenthesis($string)
    {
        $len = strlen($string);
        $stack = array();
        for ($i = 0; $i < $len; $i++) {
            switch ($string[$i]) {
                case '{':
                    array_push($stack, 0);
                    break;
                case '}':
                    if (array_pop($stack) !== 0)
                        return false;
                    break;
                default:
                    break;
            }
        }
        return (empty($stack));
    }





    function doxml()
    {
        $this->text = $this->modify($this->text);
        $this->newtext = $this->text;
        //at this point text and newtext are the same
        try {
            $this->fxml = new SimpleXMLIterator($this->text);
        } catch (Exception $e) {
            //echo($this->text);
            //echo($e->getMessage());
            $this->logtext .= $e->getMessage() . "Details\n" . $this->newtext;
            $this->exists = false;
        }
    }
    // used for replacing text according to tags - checks all the tags in the array
    function replacetags()
    {
        $names = array("exlink", "contact", "pageref", "docref", "picref", "table", "p");
        foreach ($names as $name) {

            $this->findtag($name);
        }
    }

    // recursive function for finding tags and replacing text - the process function does the replacement
    function findtag($tag)
    {
        foreach ($this->fxml->children() as $child) {
            if ($child->getName() == $tag) {
                $this->changetag($child, $tag);
            }

            if ($child->children()->count() > 0) {
                $this->xmliter($child, $tag);
            }
        }
    }


    // for the recursion

    function xmliter($child, $tag)
    {
        foreach ($child->children() as $child) {
            if ($child->getName() == $tag) {
                $this->changetag($child, $tag);
            }
            if ($child->children()->count() > 0) {
                $this->xmliter($child, $tag);
            }
        }
    }

    // essentially a switch function with appropriate code to replace tag with html.
    function changetag($child, $tag)
    {
        switch ($tag) {
            case "picref":
                /* quite detailed as image tags  have subtags of caption and details. $caption assumed to be short text - this may need processing to be added. 
        Details are processed as html text and put in the image description (Via media cass) on wordpress img file added to missing if not uploaded*/
                $oldtext = $child->asXML();
                $imgtext = $this->imgtext;
                $img = $child->imgsrc;
                $caption = $child->label;
                $caption = str_replace(">", "", $caption);
                $caption = str_replace("&", "&amp;", $caption);
                $details = $child->details;
                $detailstext = new html("<text>" . $details . "</text>");
                $detailstext->process();
                $details = $detailstext->text;
                $details = replacehash($details);
                // image added (if it is found) by media class
                $image = new media($img, $caption);
                $picid = $image->importmedia($details);
                if ($picid == -1) {
                    $this->missing .= "(Missing image: " . $img . ")\n";
                    $this->newtext = str_replace($oldtext, "(Missing image: " . basename($img) . ")", $this->newtext);
                } else {
                    $imgstr = $image->filename;
                    $imgref = $this->preurl . $imgstr;
                    $imgtext .= '<!-- wp:image {"className":"size-thumbnail", "linkDestination":"media"} -->';
                    $imgtext .= '<figure class="wp-block-image size-thumbnail">';
                    $imgtext .= '<a href="' . $imgref . '" alt=""/>';
                    $imgtext .= '<img src="' . $imgref . '" alt=""/></a>';
                    $imgtext .= '<figcaption class="wp-element-caption">' . $caption;
                    $imgtext .= '</figcaption></figure><!-- /wp:image -->';
                    $this->imgtext = $imgtext;
                    $this->newtext = str_replace($oldtext, "(see " . $caption . " in picture gallery below)", $this->newtext);
                }
                break;
            case "contact":
                //contact tag replaced with email short code.
                $this->contactname = (string)$child->label[0];
                $this->contactname = str_replace(">", "", $this->contactname);
                $this->contactemail = (string)$child->email[0];
                // PJA Edit
                $person = new contact($this->contactname, $this->contactemail);
                $person->addcontact();
                $xmltostring = $child->asXML();
                $xmltostring = str_replace("&gt;", ">", $xmltostring);
                $newstring = ' [u3a_contact  name="' . $this->contactname . '"] ';
                if ($this->sec) {
                    $newstring = "<div>" . $newstring . "</div>";
                }
                // PJA Edit
                //   $this->newtext=str_replace($child->asXML(),$newstring,$this->newtext);
                $this->newtext = str_replace($xmltostring, $newstring, $this->newtext);
                break;
            case "docref":
                //document uploaded or added to missing. Link to document added to $doctext as a list item.
                $ltext = (string) $child->label[0];
                $link = sanitize_text_field($ltext);
                if (isset($child->fname)) {
                    $url = (string) $child->fname[0];
                } else {
                    $url = (string) $child->url[0];
                }
                if (is_numeric($url)) {
                    $this->missing .= "Broken Link for " . $link . "\n";
                }
                $SBurl = 'https://u3asites.org.uk/files/';
                if (strpos($url, $SBurl) === false) {
                    $url = BASEURL . "/" . $url;
                }
                $doc = new media($url, $link);
                $docid = $doc->importmedia("");
                if ($docid == -1) {
                    $this->missing .= "(Missing Document: " . $url . ")\n";
                    if (isset($child->fname)) {   // if this is an inline link, use an inline error message
                        $doctext = "(Missing Document: " . basename($url) . ")";
                    } else {
                        $doctext = "<li>(Missing Document: " . basename($url) . ")</li>";
                    }
                } else {
                    $docstr = $doc->filename;
                    $docref = $this->preurl . $docstr;
                    $temp = "<a href='" . $docref . "'>" . $ltext;
                    $temp1 = "</a>";
                    $doctext = "";
                    if ($this->sec) {
                        // if (!in_array($docstr, $this->done)) {
                            $doctext = "<!-- wp:list-item --><li>" . $temp . $temp1 . "</li><!-- /wp:list-item -->";
                            array_push($this->done, $docstr);
                        // }
                    } else {
                        // $doctext = " " . $temp . $temp1 . " ";// NT - This introduces extra unwanted spaces
                        $doctext = $temp . $temp1;
                        array_push($this->done, $docstr);
                    }
                }
                $this->newtext = str_replace($child->asXML(), $doctext, $this->newtext);
                break;
            case "pageref":
                //pageref replaced with href text and link added as (a list item when from a section) and inline otherwise
                $linktext = "";
                $ltext = $child->label[0];
                $link = $child->url[0];
                if (empty($link)) {
                    $link=$child->fname[0];
                    if(empty($link)){
                        $link = strtolower($ltext);// should this be sanitize_key?
                    }
                }
                if (is_numeric($link)) {
                    $this->missing .= "Broken Link for " . $ltext . "\n";
                }
                // If the link is to a page then mapping to the root of the site will work
                // If the link is to a group page, we need to prepend u3a_groups/ to the link
                global $group_slug_list;
                if (in_array($link, $group_slug_list)) {
                    $link = 'u3a_groups/' . $link;
                }

                $link = get_site_url() . "/" . $link . "/";
                $temp = "<a href='" . $link . "'>" . $ltext;
                $temp1 = "</a>";
                if ($this->sec) {
                    // if (!in_array($link, $this->done)) {    // Allow a pagelink to be added to page link list even if it's already in the page text
                    $linktext = "<!-- wp:list-item --><li>" . $temp . $temp1 . "</li><!-- /wp:list-item -->";
                    array_push($this->done, $link);
                    // }
                } else {
                    // $linktext = " " . $temp . $temp1 . " "; // NT - This introduces extra unwanted spaces.
                    $linktext = $temp . $temp1;
                    array_push($this->done, $link);
                }
                $this->newtext = str_replace($child->asXML(), $linktext, $this->newtext);
                break;

            case "subpage":
                //adds page files to the page array (only used when groups.xml shows supbages of a group.)
                $subfile = $child->file;
                $subtitle = $child->title[0];
                $subfile = basename($subfile);
                $pg = array(WP_CONTENT_DIR . "/migration/allgroups/" . $subfile . ".xml", $subtitle);
                array_push($this->page, $pg);
                break;

            case "exlink":
                // adds external links as list items when from a section and inline otherwise.
                $oldtext = $child->asXML();
                if ($this->sec) {
                    $url = $child->url;
                } else {
                    $url = $child->fname;
                }
                $url = str_replace("#top", "", $url);
                $label = $child->label;
                $newltext = "<a href=\"$url\">$label</a>";

                if ($this->sec) {
                    if (!in_array($label, $this->done)) {
                        $exlinktext = "<!-- wp:list-item --><li>" . $newltext . "</li><!-- /wp:list-item -->";
                        array_push($this->done, $label);
                    }
                } else {
                    $exlinktext = $newltext;
                    array_push($this->done, $label);
                }

                $exlinktext = str_replace("ampersand", "&", $exlinktext);
                $exlinktext = str_replace("&amp;", "&", $exlinktext);
                $this->newtext = str_replace($oldtext, $exlinktext, $this->newtext);
                break;

            case "table":
                // not used since newtext may no longer contain oldtext .

                break;
            case "p":
                // do this last it attempts to remove inappropriate nested 
                //elements inside <p>.            
                foreach ($child->children() as $child) {

                    if ($child->getName() == "h3") {
                        $oldtext = $child->asXML();
                        $ntext = $oldtext;
                        $heading = $child->asXML();
                        $newheading = "</p>\n" . $heading . "\n<p>";
                        $ntext = str_replace($heading, $newheading, $ntext);


                        $this->newtext = str_replace($oldtext, $ntext, $this->newtext);
                    }
                    if ($child->getName() == "h4") {
                        $oldtext = $child->asXML();
                        $ntext = $oldtext;
                        $heading = $child->asXML();
                        $newheading = "</p>\n" . $heading . "\n<p>";
                        $ntext = str_replace($heading, $newheading, $ntext);


                        $this->newtext = str_replace($oldtext, $ntext, $this->newtext);
                    }
                    if ($child->getName() == "ul") {
                        $oldtext = $child->asXML();
                        $ntext = $oldtext;
                        $list = $child->asXML();
                        $newlist = "</p>" . "list" . "<p>";
                        $ntext = str_replace($list, $newlist, $ntext);
                        $ntext = str_replace("<p></p>", "", $ntext);
                        $this->newtext = str_replace($oldtext, $ntext, $this->newtext);
                    }
                }

                break;
        }
    }
}
