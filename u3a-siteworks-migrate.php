<?php

$wpurl = get_site_url();
$uploads = wp_upload_dir();
$preurl = $uploads['url'] . '/';



function addgroups()
{
    $logtext = "";
    $missing = "";
    $pg = array();
    $wpurl = get_site_url();
    $uploads = wp_upload_dir();
    $preurl = $uploads['url'] . '/';
    #open xml object with group listing
    $xmlStr = file_get_contents(WP_CONTENT_DIR . "/migration/grouplist.xml") or die("Error: Cannot create object");
    $xml = new SimpleXMLIterator($xmlStr);
    #find the groups

    foreach ($xml->children() as $group) {
        $contactname = "";
        $contactemail = "";
        $name = trim((string) $group);
        $temp = (string)$group->grouppage->title;
        if (strlen($name) == 0) {
            $name = $temp;
        }
        if (strlen($name) == 0) {
            $name = "Group";
        }
        $day = (string)$group->day[0];
        $time = (string)$group->time[0];
        $frequency = (string)$group->extras[0];
        $status = (string)$group->status[0];
        $fname = basename($group->grouppage->file);
        $file = "";
        if (strlen($fname) > 0) {
            //print($fname);exit;
            $file = WP_CONTENT_DIR . "/migration/allgroups/" . $fname . ".xml";
            error_log("processing group file $file");
            if (file_exists($file)) {
                $gpxmlstr = file_get_contents($file) or die("Error: Cannot create object");
                $gpxmlstr = str_replace("&amp;", "ampersand", $gpxmlstr);
                $gpxmlstr = str_replace("&", "ampersand", $gpxmlstr);
                $gpxml = new SimpleXMLIterator($gpxmlstr);
                foreach ($gpxml->children() as $section) {
                    if ($section['id'] == 'contacts'  && isset($section->contact) ) {
                        $contactname = $section->contact->label[0]->asXML();
                        $contactemail = $section->contact->email[0]->asXML();
                    }
                }
            }
        }
        //create array of subpages
        $subpage = new process($group->asXML(), false);
        $subpage->doxml();
        if ($subpage->exists) {
            $subpage->findtag("subpage");
            $pg = $subpage->page;
            if (!empty($subpage->logtext)) {
                $logtext .= "Error in " . $name . " subpages.\n Details " . $subpage->logtext . "\n";
            }
            $missing .= $subpage->missing;
        }
        //process group using agroup.php
        $thisgroup = new agroup($name, $day, $time, $frequency, $status, $file, $pg, $fname);
        $thisgroup->addcontact($contactname, $contactemail);
        $thisgroup->addgroup();
        if (!empty($missing . $thisgroup->missing)) {
            $missing .= "Missing files in " . $name . "pages " . $thisgroup->missing;
        }

        if (!empty($thisgroup->logtext)) {
            $logtext .= "Error in " . $name . ".\n Details " . $thisgroup->logtext . "\n";
        }
    }
    $logfile = fopen(WP_CONTENT_DIR . "/migration/migrationlog.txt", "a") or die("Unable to open file!");
    fwrite($logfile, $logtext);
    fclose($logfile);
    if (!empty($missing)) {
        $missingfile = fopen(WP_CONTENT_DIR . "/migration/missing.txt", "a") or die("Unable to open file!");
        fwrite($missingfile, $missing);
        fclose($missingfile);
        echo ("Groups done with the following missing images and files\n" . $missing);
    } else {
        echo ("Groups done");
    }
}


// processes events.xml, finds events, processes the event text using html class and adds events using anevent class
function addevents()
{
    $exmlStr = file_get_contents(WP_CONTENT_DIR . "/migration/eventlist.xml") or die("Error: Cannot create object");
    $exml = new SimpleXMLIterator($exmlStr);
    $logtext = "";
    $missing = "";
    foreach ($exml->children() as $child) {
        $eventtext = "";
        $startdate = "";
        $starttime = "";
        $description = "";
        $type = (string)$child->type;
        $startdate = (string) $child->date;
        $durationdays = (int) $child->days;
        $description = $child->details->asXML();  // TODO Why check details when the description is in the tag <text>?
        if ($description == "") {
            $description = $child->text->asXML();
        }
        if ($description != "") {
            $myhtml = new html($description);
            $myhtml->process();
            $title = (string) $myhtml->findtitle($startdate);
            if (!empty($myhtml->logtext)) {
                $logtext .= "Error in events. \n Details: " . $myhtml->logtext;
            }
            if (strlen($title) == 0) {
                $title = "Event on " . $startdate;
            }
            if (!empty($myhtml->missing)) {
                $missing .= "Missing files in " . $title . " event:" . $myhtml->missing;
            }
            $contactname = $myhtml->contactname;
            $contactemail = $myhtml->contactemail;
            $eventtext = $myhtml->text;
        }

        error_log("processing event: $title");
        $event = new anevent("", $startdate, $starttime, $durationdays, $contactname, $contactemail, $type, $title, $eventtext);
        $event->addevent();
    }
    $logfile = fopen(WP_CONTENT_DIR . "/migration/migrationlog.txt", "a") or die("Unable to open file!");
    fwrite($logfile, $logtext);
    fclose($logfile);
    if (!empty($missing)) {
        $missingfile = fopen(WP_CONTENT_DIR . "/migration/missing.txt", "a") or die("Unable to open file!");
        fwrite($missingfile, $missing);
        fclose($missingfile);
        echo ("Events done with the following missing images and files\n" . $missing);
    } else {
        echo ("Events done");
    }
}



function addotherpages()
{
    $logtext = "";
    $missing = "";
    //obtains array of page xml files from nongroups directory
    $pages = scandir(WP_CONTENT_DIR . "/migration/nongroups");
    array_splice($pages, 0, 2);
    $num = count($pages);
    //code for testing a particular page
/*     $page=new page(WP_CONTENT_DIR ."/migration/nongroups/groups.xml","page","");
         $page->process("");
        $page->addpage();
        echo("done");
        exit; */

    for ($i = 0; $i < $num; $i++) {
        $page = new page(WP_CONTENT_DIR . "/migration/nongroups/" . $pages[$i], "page", "");
        error_log("processing other page: ". $pages[$i]);
        $page->process("");
        $page->addpage();
        if (!empty($page->logtext)) {
            $logtext .= "Error in " . $pages[$i] . "\n. Details " . $page->logtext . "\n";
        }
        $logtext .= $pages[$i] . " processed\n";
        if (!empty($page->missing)) {
            $missing .= "Missing files on page " . $pages[$i] . $page->missing . "\n";
        }
    }
    u3a_migration_notices();

    u3a_migration_create_menu();
    $logfile = fopen(WP_CONTENT_DIR . "/migration/migrationlog.txt", "a") or die("Unable to open file!");
    fwrite($logfile, $logtext);
    fclose($logfile);
    $missingfile = fopen(WP_CONTENT_DIR . "/migration/missing.txt", "a") or die("Unable to open file!");
    fwrite($missingfile, $missing);
    fclose($missingfile);
}

function u3a_migration_notices()
{
    $missing = "";
    $logtext = "";
    $sourceFile = WP_CONTENT_DIR . "/migration/extras.xml";
    $extrasXML = simplexml_load_file($sourceFile);
    $catid = wp_create_category("Notices");
    $noticesSection = $extrasXML->xpath('section[@id="notices"]/notice');
    foreach ($noticesSection as $noticeItem) {
        $note = $noticeItem->item->p[0]->asXML();
        $enddate = $noticeItem->until[0]->asXML();
        $enddate = str_replace("<until>", "", $enddate);
        $enddate = str_replace("</until>", "", $enddate);
        $startdate = wp_date('Y-m-d');
        $notehtml = new html("<text>" . $note . "</text>");
        $notehtml->process();
        $missing .= $notehtml->missing;
        $title = (string) $notehtml->findtitle($startdate);
        $title = wp_strip_all_tags($title);
        error_log("processing notice: $title");
        //$title="Until ".$enddate;
        if (!empty($notehtml->logtext)) {
            $logtext .= "Error in notices.\n Details " . $notehtml->logtext . "\n";
        }
        $text = $notehtml->text;
        u3a_notice_post($title, $startdate, $enddate, $text, $catid);
    }
    $logfile = fopen(WP_CONTENT_DIR . "/migration/migrationlog.txt", "a") or die("Unable to open file!");
    fwrite($logfile, $logtext);
    fclose($logfile);
    $missingfile = fopen(WP_CONTENT_DIR . "/migration/missing.txt", "a") or die("Unable to open file!");
    fwrite($missingfile, $missing);
    fclose($missingfile);
}

function u3a_notice_post($title, $startdate, $enddate, $text, $catid)
{
    if (strpos($title, "Event") > -1) {
        $title = "Until " . $enddate;
    }
    $post_ID = wp_insert_post(array(
        'post_content' => $text,
        'post_title' => $title,
        'post_status' => 'publish',
        'post_type' => U3A_NOTICE_CPT,
        'post_category' => array($catid),
    ));

    update_post_meta($post_ID, 'notice_start_date', $startdate);
    update_post_meta($post_ID, 'notice_end_date', $enddate);
}
function u3a_migration_create_menu()
{
    $logtext = "";
    $missing = "";
    $navEntries = array();
    $base_URL = get_site_url() . '/';
    $sourceFile = WP_CONTENT_DIR . "/migration/extras.xml";

    $extrasXML = simplexml_load_file($sourceFile);

    // extract all <button> elements
    // use <caption> as menu text
    // use <pagelink> to retrieve the actual page title from the referenced xml file

    $menuSection = $extrasXML->xpath('section[@id="menu"]/button');

    foreach ($menuSection as $menuItem) {

        $menuItemText = $menuItem->caption;
        $menuTargetTitle = getTitleFromFile(WP_CONTENT_DIR . "/migration/nongroups/" . $menuItem->pagelink);
        // $menuTargetPage = get_page_by_title($menuTargetTitle);
        // get_page_by_title deprecated
        $query = new WP_Query(['post_type' => 'page', 'title' => $menuTargetTitle, 'post_status' => 'publish']);
        $menuTargetPage = (empty($query->post)) ? null : $query->post;

        // build indivitual menu entry for this page
        if ($menuTargetPage != null) {
            $navEntries[] = '<!-- wp:navigation-link {"label":"' . $menuItemText . '","type":"page","id":' . $menuTargetPage->ID . ',"url":"' . $base_URL . $menuTargetPage->post_name . '/","kind":"post-type","isTopLevelLink":true} /-->';
        }
    }
    // Concatenate the individual menu entries to create the post content format expected by WordPress
    $navigationContent =  implode("\n\n", $navEntries);

    // Add the wp_navigation post, checking for navigation post(s) already present
    $postTitle = 'SiteBuilder Menu';
    $titleSuffix = '';
    $titleNum = 1;
    while (post_exists($postTitle . $titleSuffix, '', '', 'wp_navigation')) {
        $titleSuffix = ' ' . $titleNum;
        $titleNum++;
    }

    $navID = wp_insert_post(array(
        'post_content' => $navigationContent,
        'post_title' => $postTitle . $titleSuffix,
        'post_name' => sanitize_title($postTitle . $titleSuffix),
        'post_status' => 'publish',
        'post_type' => 'wp_navigation',
        'guid' => $base_URL . 'navigation/'
    ));

    if ($navID == 0) {
        //echo "Failed to add wp_navigation post";
        $logtext .= "Failed to add wp_navigation post\n";
    }
    $logfile = fopen(WP_CONTENT_DIR . "/migration/migrationlog.txt", "a") or die("Unable to open file!");
    fwrite($logfile, $logtext);
    fclose($logfile);
    $missingfile = fopen(WP_CONTENT_DIR . "/migration/missing.txt", "a") or die("Unable to open file!");
    fwrite($missingfile, $missing);
    fclose($missingfile);
}

// open the page file and extract the title

function getTitleFromFile($fileref)
{
    $xml = simplexml_load_file($fileref);
    $title = $xml->xpath('/page/title');
    return (string) $title[0];
}

//remove hashes
function replacehash($text)
{
    $pos = strpos($text, "#");
    if ($pos >= 0) {
        $text1 = substr($text, 0, $pos);
        $text2 = substr($text, $pos);
        $text2 = preg_replace("/#\s*#/", "##", $text2);
        $textarr = preg_split("/#/", $text2);
        $num = count($textarr);
        $text = $text1 . $textarr[0];
        $i = 1;
        while ($i < $num) {
            if ($i % 2 == 1) {
                $text = $text . "<strong>" . $textarr[$i] . "</strong>";
            } else {
                $text = $text . $textarr[$i];
            }
            $i = $i + 1;
        }
    }
    $text = str_replace("#", "", $text);

    $pos = strpos($text, "|");
    /*if($pos>=0)
    { 
      $text1=substr($text,0,$pos);
      $text2=substr($text,$pos);
      $text2=preg_replace("/|\s*|/","||",$text2);
      $textarr=preg_split("/|/",$text2);
      $num=count($textarr);
      $text=$text1.$textarr[0];
      $i=1;
      while($i<$num){
          if($i%2==1){
               $text=$text."<i>".$textarr[$i]."</i>";
           }
           else{
               $text=$text.$textarr[$i];
           }
           $i=$i+1;
       }          
    }*/
    $text = str_replace("|", "", $text);
    return $text;
}
//This function is an attempt to remove glitches and tags, blank table lines, etc
// all in one place - to be called before adding contet to a page etc.
function preparetoadd($content)
{
    if (!empty("content")) {
        $content = str_replace('<pic>', "", $content);
        $content = str_replace('</pic>', "", $content);
        $content = str_replace("<text>", "", $content);
        $content = str_replace("</text>", "", $content);
        $content = str_replace("ampersand", "&", $content);
        $content = str_replace('&lt;br&gt;', 'LineBreak', $content);
        $content = preg_replace('/(LineBreak\s*)+/i', '</p> <p>', $content);
        $content = replacehash($content);

        // stray punctuation at start or end of paragraph
        $content = str_replace('<p>_','<p>', $content);
        $content = str_replace('<p>.','<p>', $content);
        $content = str_replace('_</p>','</p>', $content);

        $content = preg_replace('/<p>\s*<\/p>/i', '', $content);
        $content = preg_replace('$<tr>\s*(<td>\s*<\/td>\s*)*\s*<\/tr>$', '', $content);

        // fix for block errors due to <section> tags not being removed
        $content = preg_replace('~<section\s+id="[a-z]+"[\/]*>~ms', '', $content);
        $content = str_replace('</section>', '', $content);
    }
    return $content;
}
