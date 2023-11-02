<?php
class anevent
{

    public $gid = "";
    public $startdate = "";
    public $days = "1";
    public $extract = "";
    public $organiser = "No-one chosen";
    public $email = "";
    public $title = "Event";
    public $content = "";
    public $etype = '';
    public $etypeID = '';
    public $starttime = '';
    public $description = '';

    // this simply sets the fields  needed for adding the post
    function __construct($gid, $startdate, $starttime, $days, $organiser, $email, $etype, $title, $content)
    {
        $this->gid = $gid;
        $this->startdate = $startdate;
        $this->starttime = $starttime;
        $this->days = $days;
        $this->organiser = $organiser;
        $this->description = preparetoadd($content);
        $this->email = $email;
        $this->etype = $etype;
        $this->title = $title;
    }



    function addevent()
    {
        //add organiser to contacts if not aleady there
        $person = new contact($this->organiser, $this->email);
        $pid = $person->addcontact();
        //add post
        $this->title = trim($this->title, " ");
        $posttitle = sanitize_text_field($this->title);
        $newpage = array(
            'post_title' => $posttitle,
            'post_status' => 'publish',
            'post_content' => "$this->description<!-- wp:u3a/eventdata /-->",
            'post_type' => 'u3a_event',
            'post_author'  => 1,
        );

        $post_ID = wp_insert_post($newpage, false, true);

        // Collate post meta data

        // eventDate and eventEndDate must be set
        // eventType_ID (event taxonomy) must be set

        // The following can be set if detected
        //      eventTime, eventDays, eventGroup_ID

        // The following will always be undefined in a migrated event and can be ignored
        //      eventOrganiser_ID and eventVenue_ID, eventCost

        // eventBookingRequired should be set to 0

        //obtain eventType_ID from $this->etype;
        $types = array(
            'Meeting',
            'Outing',
            'Study Day',
            'Social',
            'Summer School',
            'Holiday',
            'Other'
        );
        $i = 0;
        $done = false;
        while ($i < 7 and !$done) {
            if (strcasecmp($this->etype, $types[$i]) == 0) {
                $done = true;
                $temp = get_term_by('name', $types[$i], U3A_EVENT_TAXONOMY);
            }
            $i = $i + 1;
        }
        if (!$done) {
            $temp = get_term_by('name', 'Other', U3A_EVENT_TAXONOMY);
        }
        $this->etype = $temp->name;
        $this->etypeID = $temp->term_id;

        $eventType_ID  = $this->etypeID;
        $eventDate = $this->startdate;
        $eventDays = $this->days;
        $eventTime = $this->starttime;
        $eventGroup_ID = $this->gid;

        // NT - Calculate value of eventEndDate from eventDate and eventDays
        if (isset($eventDays) and $eventDays > 1) {
            $eventEndDate = date("Y-m-d", strtotime($eventDate) + 86400 * ($eventDays - 1));
        } else {
            $eventEndDate = $eventDate;
        }

        // Save post meta data

        update_post_meta($post_ID, 'eventType_ID', $eventType_ID);
        wp_set_object_terms($post_ID, $eventType_ID, U3A_EVENT_TAXONOMY);
        // TODO is this required? update_term_meta($this->etypeID, 'name', $this->etype);

        if (!empty($eventDate)) {
            update_post_meta($post_ID, 'eventDate', $eventDate);
            update_post_meta($post_ID, 'eventEndDate', $eventEndDate);
        } else {
            // error - there should be an event date
            // TODO - report the missing date should this occur
        }

        if (!empty($eventGroup_ID)) {
            update_post_meta($post_ID, 'eventGroup_ID', $eventGroup_ID);
        }
        if (!empty($eventTime)) {
            update_post_meta($post_ID, 'eventTime', $eventTime);
        }
        if ($eventDays > 1) {
            update_post_meta($post_ID, 'eventDays', $eventDays);
        }
        update_post_meta($post_ID, 'eventBookingRequired', 0);
   
    }
}
