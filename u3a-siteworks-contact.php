<?php

class contact
{
    public $fullname = "";
    public $given = "";
    public $family = "";
    public $email = "";

    function __construct($contactname, $contactemail)
    {
        $contactname = str_replace("<label>", "", $contactname);
        $contactname = str_replace("</label>", "", $contactname);
        $contactemail = str_replace("<email>", "", $contactemail);
        $contactemail = str_replace("</email>", "", $contactemail);
        $this->fullname = $contactname;
        $this->email = $contactemail;
        if (strlen($contactname) > 0) {
            $contactname = str_replace("ampersandapos;", "'", $contactname);
            $contactname = str_replace("ampersand", "&", $contactname);
            $leader = $contactname;
            if (strlen($leader > 0)) {
                $lead = explode(" ", $leader);
                if (count($lead) > 1) {
                    $this->given = $lead[0];
                    for ($i = 1; $i < count($lead) - 1; $i++) {
                        $this->given .= " " . $lead[$i];
                    }
                    $this->family = $lead[count($lead) - 1];
                } else {
                    $this->given = $leader;
                    $this->family = "";
                }
            }
        }
    }
    //adds contact if they are non-empty and not already added. Returns id of contact
    function addcontact()
    {
        $pid = "";
        if ((strlen($this->given) == 0) and (strlen($this->family) == 0)) {
            $this->given = "";
            $this->family = "";
        } else {
            $title = $this->given . " " . $this->family;
            // $contpost = get_page_by_title($title, OBJECT, 'u3a_contact');
            // get_page_by_title depracated
            $query = new WP_Query(['post_type' => 'u3a_contact', 'title' => $title]);
            $contpost = (empty($query->post)) ? null : $query->post;
            if (is_null($contpost)) {
                $newpage = array(
                    'post_title' => $title,
                    'post_status' => 'publish',
                    'post_type' => 'u3a_contact'
                );
                $pid = wp_insert_post($newpage, false, true);
                update_post_meta($pid, 'givenname', $this->given);
                update_post_meta($pid, 'familyname', $this->family);
                if (is_email($this->email)) {
                    update_post_meta($pid, 'email', $this->email);
                }
            } else {
                $pid = $contpost->ID;
            }
        }
        return $pid;
    }
}
