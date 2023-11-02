<?php
class agroup
{
    // group fields collected from sitebuilder
    // $linktext is the block text for any links to subgroup pages - added to overall linktext for the group when page is added.
    public $gid = "";
    public $name = "";
    public $day = "";
    public $time = "";
    public $frequency = "";
    public $fname = "";
    public $slug ="";
    public $given = "";
    public $family = "";
    public $pid = "";
    public $email = "";
    public $linktext = "";
    public $status = "";
    public $logtext = "";
    public $missing = "";
    public $timelist = array("a.m." => "Morning", "p.m." => "Afternoon");
    public $statuslist = array("Show" => 1, "New" => 1, "Full" => 2, "Hide" => 4);
    public $freqlist = array("Weekly", "Fortnightly", "Monthly");
    public $daylist = array("Mon" => 1, "Tue" => 2, "Wed" => 3, "Thu" => 4, "Fri" => 5, "Sat" => 6, "Sun" => 7, "Monday" => 1, "Tuesday" => 2, "Wednesday" => 3, "Thursday" => 4, "Friday" => 5, "Saturday" => 6, "Sunday" => 7);
    var $pg;
    var $gpage;


    //group fields set where known
    // NT - pass in the group slug from the <grouppage><file>.  
    function __construct($name, $day, $time, $frequency, $status, $file, $pg, $slug)
    {
        //$this->name=str_replace(" ","-",$name);
        $this->name = $name;
        if (strlen($this->name) == 0) {
            $this->name = "Anon";
        }
        $this->day = $day;
        $this->time = $time;
        //note: this is now added to the post-meta 'when' value as it is not always frequency.
        $this->frequency = $frequency;
        $this->status = $status;
        //group file containing xml code
        $this->fname = $file;
        //array of subpages (the names of the xml file) linked to group
        $this->pg = $pg;
        // Slug might be empty if the group does not have a group page
        $this->slug = $slug;
    }


    //adds group contact details from xml page 
    function addcontact($organiser, $email)
    {
        $this->email = str_replace("<email>", "", $email);
        $this->email = str_replace("</email>", "", $email);
        //add this person to contacts
        $person = new contact($organiser, $this->email);
        $this->pid = $person->addcontact();
    }

    // this is implemented after the group has been added and the gid set.
    //
    function addsubpages($done)
    {
        foreach ($this->pg as $subp) {
            if (file_exists($subp[0])) {
                //page created 
                $spage = new page($subp[0], "page", $this->gid);
                $spage->process("");
                $spage->addpage();
                if (!empty($spage->logtext)) {
                    $this->logtext .= $spage->logtext;
                }
                $this->missing .= $spage->missing;
                //link to subpage created and added to $linktext
                $link = str_replace(".xml", "", basename($subp[0]));
                if (!in_array($link, $done)) {
                    $this->linktext = $this->linktext . "<!-- wp:list-item --><li>";
                    $temp = "<a href='" . $link . "'>" . $subp[1];
                    $temp1 = "</a>";
                    $this->linktext = $this->linktext . $temp . $temp1;
                    $this->linktext = $this->linktext . "</li><!-- /wp:list-item -->";
                }
            }
        }
    }

    //adds post for Group and gets id of post, adds line with group details to csv
    function addgroup()
    {
        $contactname = "";
        $contactemail = "";
        $result = "";
        // If the SiteBuilder status was 'Hide', add post with status 'draft'
        $group_post_status = ("Hide" == ($this->status)) ? 'draft' :'publish';

        #create post for group
        $newpage = array(
            'post_title' => sanitize_text_field($this->name),
            'post_status' => $group_post_status,
            'post_content' => "",
            'post_type' => 'u3a_group',
            'post_author'  => 1
        );
        // Set the group slug if we have one.  If not, will default to using the post_title.
        if (!empty($this->slug)) {
            $newpage += [ 'post_name' => $this->slug] ;
        }
        $groupcontent = "";
        $this->gid = wp_insert_post($newpage, false, true);

        if (file_exists($this->fname)) {
            //group page (an instance of page) created
            //this just creates content to be added to the post
            $this->gpage = new page($this->fname, "post", $this->gid);


            //$this->addsubpages();
            $this->gpage->process($this->linktext);
            if (!empty($this->gpage->logtext)) {
                $this->logtext .= $this->gpage->logtext;
            }
            $this->missing .= $this->gpage->missing;
            $done = $this->gpage->done;

            $groupcontent = $this->gpage->content;
            //addlinks
            $this->addsubpages($done);
            //$groupcontent .= $this->linktext; 
            //find groupleader if in page content. Maybe different from leader already found but takes priority.  leaders can occur either in the groups.xml file or in the group page file or both
            //this takes the one in the group page file so rplaces the other if it exists.
            //similarly for the email address
            $contactname = $this->gpage->contactname;
            $contactemail = $this->gpage->contactemail;
            $person = new contact($contactname, $contactemail);
            $this->pid = $person->addcontact();
            $this->email = $person->email;
        } else {
            //file not found so use defaults and message added to $missing
            $groupcontent = "";
            $this->email = "";
            $notfound = "xml file for " . sanitize_text_field($this->name) . " not found\n";
            $this->missing .= $notfound;
            //echo("xml file for ".sanitize_text_field($this->name)." not found\n");
        }

        $groupcontent = preparetoadd($groupcontent);

        $content = array('ID' => $this->gid, 'post_title' => $this->name, 'post_status' => $group_post_status, 'post_content' => "<!-- wp:u3a/groupdata /-->$groupcontent<!-- wp:u3a/eventlist /-->");
        wp_update_post($content, false, true);
        //print($this->gid.",".$this->pid.",".$this->email.",".$this->day.",".$this->time.",".$this->frequency."<br>");
        //now add the meta data to the group post
        if (!empty($this->pid)) {
            update_post_meta($this->gid, 'coordinator_ID', $this->pid);
        }
        if (is_email($this->email)) {    // Inappropriate to duplicate the contact email as the group email?
            update_post_meta($this->gid, 'email', $this->email);
        }
        if (array_key_exists($this->day, $this->daylist)) {
            update_post_meta($this->gid, 'day_NUM', $this->daylist[$this->day]);
        }
        if (array_key_exists($this->time, $this->timelist)) {
            update_post_meta($this->gid, 'time', $this->timelist[$this->time]);
        }
        if (array_key_exists($this->status, $this->statuslist)) {
            update_post_meta($this->gid, 'status_NUM', $this->statuslist[$this->status]);
        } else {
            update_post_meta($this->gid, 'status_NUM', 1);
        }
        foreach ($this->freqlist as $key) {
            if (stristr($this->frequency, $key)) {
                update_post_meta($this->gid, 'frequency', $key);
            }
        }
        update_post_meta($this->gid, 'when', $this->frequency);

        wp_set_object_terms($this->gid, 'general', U3A_GROUP_TAXONOMY);

        // this adds the events to events and to the Group 
        if (isset($this->gpage)) {
            $num = count($this->gpage->events);
            if ($num > 0) {
                for ($i = 0; $i < $num; $i++) {
                    $evt = $this->gpage->events[$i];
                    $evt->addevent();
                }
            }
        }
    }
}
