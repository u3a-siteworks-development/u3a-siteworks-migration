<?php

class page
{

    public $file = "";
    public $content = "";
    public $pageXML;
    public $name = "";
    public $slug = "";
    public $type = "post";
    public $parent = "0";
    public $contactname = "";
    public $contactemail = "";
    public $htmltext = "";
    public $imgtext = "";
    public $preurl = ""; //link to folder for images
    public $events = array();
    public $logtext = "";
    public $missing = "";
    public $done = array(); //links that have been done inline
    var $gid;
    var $pxml;




    function __construct($pfile, $ptype, $gid)
    {
        $this->file = $pfile;
        $this->type = $ptype;
        if (file_exists($pfile)) {
            $sourceFile = $pfile;
            $this->pageXML = simplexml_load_file($sourceFile);
            $pxmlstr = file_get_contents($pfile) or die("Error: Cannot create object");
            $this->pxml = new SimpleXMLIterator($pxmlstr);
            $this->name = $this->pageXML->title;
            //$this->name=str_replace("ampersand","&",$this->name);
            $this->slug = str_replace(".xml", "", basename($this->file));
            $uploads = wp_upload_dir();
            $this->preurl = $uploads['url'] . '/';
            $this->gid = $gid;
            $this->parent = "0";
            $this->contactname = "";
            $this->contactemail = "";
        }
    }

    function process($linktext)
    {
        $htmltext = "";
        $eventtext = "";
        $linktext = $linktext;  // docrefs section
        $ltext = "";
        $link = "";
        $caption = "";
        $temp = "";
        $img = "";
        $contacttext = "";
        $exlinktext = "";
        $doctext = "";
        $pagerefsection = ''; // completed HTML for pageref section

        # FOR EACH section find the data


        foreach ($this->pxml->children() as $section) {

            if (!isset($section['id'])) {  // skip over anything that isn't a <section> with an id attribute
                continue;
            }

            try {
                //processes each section according to id. Passes to process with sec set to true. Results added to $htmltext (local to
                //function). function remove text removes outer tags <text</text> used to keep xml form. 
                if ($section['id'] == 'text') {
                    $htmltext = $section[0]->asXML();
                    $html = new html($htmltext, $this->done);
                    $html->process();
                    $this->contactname = $html->contactname;
                    $this->contactemail = $html->contactemail;
                    $this->imgtext .= $html->imgtext;
                    $this->htmltext .= $html->text;
                    if (!empty($html->logtext)) {
                        $this->logtext .= "Error in " . $this->file . "\n Details " . $html->logtext . "\n";
                    }
                    $this->missing .= $html->missing;
                    $this->done = $html->done;
                }
                if ($section['id'] == 'contacts' && isset($section->contact)) {
                    $contacttext = $section[0]->asXML();
                    $contacts = new process($contacttext, true, $this->done);
                    $contacts->doxml();
                    if ($contacts->exists) {
                        $contacts->findtag("contact", true);
                        $this->contactname = $contacts->contactname;
                        $this->contactemail = $contacts->contactemail;
                        // This code adds the contact after the group page contents.  Omitted for now.
                        // if (!empty($this->contactname) && !empty($this->contactemail)) {
                        //     $this->htmltext .= '<div>[u3a_contact name="' . $this->contactname . '" email="' . $contacts->contactemail . '"]</div>';
                        // }

                        $this->done = $contacts->done;
                    }
                    if (!empty($contacts->logtext)) {
                        $this->logtext .= "Error in " . $this->file . "\n Details " . $contacts->logtext . "\n";
                    }
                    $this->missing .= $contacts->missing;
                }
                if ($section['id'] == 'exlinks' && isset($section->exlink)) {
                    $exlinktext = $section[0]->asXML();
                    $exlinks = new process($exlinktext, true, $this->done);
                    $exlinks->doxml();
                    if ($exlinks->exists) {
                        $exlinks->findtag("exlink");
                        if (strlen($exlinks->newtext) > 0) {
                            // Strip off the <section> tags
                            $exlinks->newtext = str_replace('<section id="exlinks">', '', $exlinks->newtext);
                            $exlinks->newtext = str_replace('</section>', '', $exlinks->newtext);
                            $exlinktext = "<!-- wp:list --><ul>" . $exlinks->newtext . "</ul><!-- /wp:list -->";
                            // $this->htmltext .=  $exlinktext;  // wait and add later so we can choose order
                        }
                        $this->done = $exlinks->done;
                    }
                    if (!empty($exlinks->logtext)) {
                        $this->logtext .= "Error in " . $this->file . "\n Details " . $exlinks->logtext . "\n";
                    }
                    $this->missing .= $exlinks->missing;
                }
                $picid = "1";
                if ($section['id'] == 'picrefs' && isset($section->picref)) {
                    // adds all images and puts them in a gallery block for the end of the page.
                    //$imgtext is local to function, $this->imgtext is class field value
                    foreach ($section->children() as $child) {
                        $imgtext = "";
                        // SiteBuilder redirects http requests to https, so asking for a resource via http will fail
                        $img = (string) $child->imgsrc[0];
                        if (substr($img, 0, 5) != 'https') {
                            $img = str_replace('http://', 'https://', $img);
                        }

                        $caption = (string) $child->label[0];
                        $caption = str_replace("&", "&amp;", $caption);
                        $caption = str_replace(">", "", $caption);  // fix for temporary bug in xml

                        $details = $child->details[0];
                        $desc = new html($details);
                        $desc->process();
                        $desc = $desc->text;
                        if (strlen($desc) > 0) {
                            $details = $desc;
                        }
                        $image = new media($img, $caption, $details);
                        $picid = $image->importmedia($details);
                        if ($picid == -1) {
                            $this->missing .= "(Missing image" . $img . ")\n";
                        } else {
                            $imgstr = $image->filename;
                            $imgref = $this->preurl . $imgstr;
                            $imgtext .= '<!-- wp:image {"className":"size-thumbnail", "linkDestination":"media"} -->';
                            $imgtext .= '<figure class="wp-block-image size-thumbnail">';
                            $imgtext .= '<a href="' . $imgref . '" alt=""/>';
                            $imgtext .= '<img src="' . $imgref . '" alt=""/></a>';
                            $imgtext .= '<figcaption class="wp-element-caption">' . $caption;
                            $imgtext .= '</figcaption></figure><!-- /wp:image -->';
                            $this->imgtext .= $imgtext;
                        }
                    }
                    if (strlen($this->imgtext) > 0) {
                        $this->imgtext = '<!-- wp:gallery {"linkTo":"media"} --><figure class="wp-block-gallery has-nested-images columns-default is-cropped">' . $this->imgtext . '</figure><!-- /wp:gallery -->';
                    }
                }
                if ($section['id'] == 'pagerefs' && isset($section->pageref)) {
                    // adds page references as  list items
                    $pglinktext = $section[0]->asXML();
                    $links = new process($pglinktext, true, $this->done);
                    $links->doxml();
                    if ($links->exists) {
                        $links->findtag("pageref");
                        $pglinktext = $links->newtext;
                        // Strip off the <section> tags
                        $pglinktext = str_replace('<section id="pagerefs">', '', $pglinktext);
                        $pglinktext = str_replace('</section>', '', $pglinktext);
                        if (strlen($pglinktext > 0)) {
                            $pagerefsection .= "<!-- wp:list --><ul>" . $pglinktext . "</ul><!-- /wp:list -->";
                        }
                        $this->done = $links->done;
                    }
                    if (!empty($links->logtext)) {
                        $this->logtext .= "Error in " . $this->file . "\n Details " . $links->logtext . "\n";
                    }
                    $this->missing .= $links->missing;
                }
                if ($section['id'] == 'docrefs' && isset($section->docref)) {
                    // adds documents to media where possible and references as list items in a separate list
                    $doctext = $section[0]->asXML();
                    $docs = new process($doctext, true, $this->done);
                    $docs->doxml();
                    if ($docs->exists) {
                        $docs->findtag("docref");
                        $doctext = $docs->newtext;
                        $this->logtext .= $docs->logtext;
                        $doctext = str_replace('<section id="docrefs">', '', $doctext);
                        $doctext = str_replace('</section>', '', $doctext);
                        if (strlen($doctext > 0)) {
                            $linktext .= "<!-- wp:list --><ul>" . $doctext . "</ul><!-- /wp:list -->";
                        }
                        $this->done = $docs->done;
                    }
                    $this->logtext .= $docs->logtext;
                    if (!empty($docs->missing)) {
                        $this->missing .= $docs->missing . "\n";
                    }
                }
                if ($section['id'] == 'events' && isset($section->event)) {
                    // adds events from given details and passin to event class, $eventtext is generated from either description or details (should it be both?)
                    foreach ($section->children() as $child) {
                        $eventtext = "";
                        $startdate = "";
                        $starttime = "";
                        $description = "";
                        $startdate = (string) $child->date;
                        $durationdays = (int) $child->days;
                        $description = $child->details->asXML();
                        if ($description == "") {
                            $description = $child->text->asXML();
                        }
                        if ($description != "") {
                            $myhtml = new html($description);
                            $myhtml->process();
                            $title = $myhtml->findtitle($startdate);
                            $eventtext = $myhtml->text;
                            $this->logtext .= $myhtml->logtext;
                            $this->missing .= $myhtml->missing;
                        } else {
                            $title = "Event on " . $startdate;
                            $extract = "";
                        }

                        $event = new anevent($this->gid, $startdate, $starttime, $durationdays, $this->contactname, $this->contactemail, "Other", $title, $eventtext);
                        array_push($this->events, $event);
                    }
                }
            } catch (Exception $e) {
                //echo $e->getMessage() . "\n";
                $error = "NOTE: " . $e->getMessage() . "\n" . $this->file . " has xml issues\n";
                $this->logtext .= $error;
                //echo $this->file . " has xml issues\n";
            }
        }
        // Assemble page content by putting together results as $this->content

        $this->content = preparetoadd($this->htmltext);                             // text section

        // Assemble all Links and condense into one wp:list-item
        $the_links = $pagerefsection;   // page refs section
        $the_links .= $linktext;        // docrefs section
        $the_links .= $exlinktext;      // add exlinks section
        $the_links = str_replace('</ul><!-- /wp:list --><!-- wp:list --><ul>', '', $the_links); // merge into a single list
        if (!empty($the_links)) {
            $the_links = '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Links</h3><!-- /wp:heading -->' . $the_links;
        }
        $this->content .= $the_links;

        // Add picrefs to the end of the page
        $this->content .= $this->imgtext;

        if ((strcasecmp(basename($this->file, ".xml"), "groups")) == 0) {
            $this->content .= "<!-- wp:u3a/grouplist /-->";
        }
        if ((strcasecmp(basename($this->file, ".xml"), "events")) == 0) {
            $this->content .= "<!-- wp:u3a/eventlist /-->";
        }
    }
    // this function actually adds the page. 
    function addpage()
    {
        $newpage = array(
            'post_title' => $this->name,
            'post_name' => $this->slug,
            'post_status' => 'publish',
            'post_content' => $this->content,
            'post_type' => $this->type,
            'post_author'  => 1,
            'post_parent' => $this->parent
        );
        wp_insert_post($newpage, false, true);
    }
}
