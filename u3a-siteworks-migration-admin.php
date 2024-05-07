<?php
// Admin menu pages

add_action('admin_menu', 'u3a_migrate_admin_menu');
function u3a_migrate_admin_menu()
{
    add_menu_page(
        'u3a Migration',
        'u3a Migration',
        'manage_options',
        'u3a-migrate-menu',
        'u3a_show_migrate_menu',
        'dashicons-migrate',
        50
    );
}

function u3a_show_migrate_menu()
{

    // Build Admin Page for Migration

    $status = isset($_GET['status']) ? $_GET['status'] : "";
    $status_text = '';
    if ($status == "1") {
        $missing_link = content_url('/migration/missing.txt');
        $log_link = content_url('/migration/migrationlog.txt');
        // only show missing files link if the file has content
        $missingFileSize = filesize(WP_CONTENT_DIR . "/migration/missing.txt");
        if ($missingFileSize > 0) {
            $missingText = '<a href="' . $missing_link . '" target="_blank">Missing files</a>';
        } else {
            $missingText = 'No missing files';
        }
        $status_text = <<< END
        <div class="notice notice-error is-dismissible inline">
        <p><b>Migration Completed</b> &nbsp; - &nbsp; 
        <a href="$log_link" target="_blank">Migration log</a> &nbsp;
        $missingText
        </p></div>
END;
    }
    if ($status == "99") {
        $message = get_transient('u3a_fname_errormsg');
        delete_transient('u3a_fname_errormsg');
        $status_text = <<< END
        <div class="notice notice-error is-dismissible inline">
        <p><b>Migration issue with tags in fname or contact found</b></p>
        $message
        <p><b>You can continue with migration after correcting these issues</b></p>
        </div>
END;
    }

    // Form components

    $submit_button = <<< END

    <p class="submit">
    <input type="submit" name="submit" id="submit" class="button button-primary button-large" value="Migrate Selected Items">
    </p>
    <script>
    // disable when clicked
    var sb = document.getElementById('submit');
    sb.addEventListener('click', function(event) { 
    setTimeout(function () {
        event.target.disabled = true;
        }, 0);
    });
    </script>
END;

    $import_form = <<< END

    <form method="POST" action="admin-post.php">
    <input type="hidden" name="action" value="u3a_do_migration">
    
    <p><label for="importgroups">Import Groups</label>
    <input type="checkbox" id="importgroups" name="importgroups" value="1" checked></p>
    
    <p><label for="importevents">Import Events</label>
    <input type="checkbox" id="importevents" name="importevents" value="1" checked></p>
    
    <p><label for="importothers">Import other content</label>
    <input type="checkbox" id="importothers" name="importothers" value="1" checked></p>
    
    <p><label for="importmedia">Download Media while migrating?</label>
    <input type="checkbox" id="importmedia" name="importmedia" value="1" checked></p>
    
    $submit_button
    </form>
END;

    // Display source name or prompt for upload

    $import_source = '';
    $import_title = '';
    $prompt = 'Select a Site Builder export file';
    $import_btn_text = "Upload zip file";
    if (file_exists(WP_CONTENT_DIR . "/migration/extras.xml")) {
        $extras = simplexml_load_file(WP_CONTENT_DIR . "/migration/extras.xml");
        $name = $extras->xpath('section[@id="sitename"]/name');
        $sitename = (string) $name[0];
        $sitename = htmlspecialchars_decode($sitename); // anticipate the ampersand!
        $import_title = "<h3>Importing from Site Builder export file for <strong>$sitename</strong></h3>";
        $import_btn_text = "Upload new zip file";
        $prompt = 'Select a different Site Builder export file';
    } else {
        $import_form = '';  // no import form shown unless contents present in migration folder
    }
    $import_source = <<< END
        <form method="POST" action="admin-post.php" enctype="multipart/form-data">
        <input type="hidden" name="action" value="u3a_upload_migration_zip">
        <h2><label for="zipfile">$prompt</label></h2>
        <p><input type="file" name="zipfile" id="zipfile" accept="application/x-zip"></p>
        <p><input type="submit" class="button button-primary button-large" value="$import_btn_text" name="upload"></p>
        </form>
        <hr style="margin-top:10px;margin-bottom:10px;">
END;



    // Display Admin Page

    echo <<<END
<div class="wrap">
<h1 class="wp-heading-inline">Site Builder Migration</h1>
$status_text

$import_source

$import_title

$import_form

</div>

END;
}


// Add function to upload the Site Builder export file

add_action('admin_post_u3a_upload_migration_zip', 'u3a_upload_migration_zip');

function u3a_upload_migration_zip()
{

    // not bothering with security checks as this plugin is only for migration team use

    // create migration folder, clear if it exists
    $migrationFolder = WP_CONTENT_DIR . "/migration";
    if (is_dir($migrationFolder)) {
        $di = new RecursiveDirectoryIterator($migrationFolder, FilesystemIterator::SKIP_DOTS);
        $ri = new RecursiveIteratorIterator($di, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($ri as $file) {
            $file->isDir() ?  rmdir($file) : unlink($file);
        }
    } else {
        mkdir($migrationFolder);
    }

    // If it exists, extract uploaded file
    if (file_exists($_FILES['zipfile']['tmp_name'])) {
        $zip = new ZipArchive;
        if ($zip->open($_FILES['zipfile']['tmp_name'])) {
            $zip->extractTo($migrationFolder);
            $zip->close();
        }
    }

    // Check xml files in allgoups and nongoups folders for <fname> bug
    $fnameErrors = array();

    foreach (glob($migrationFolder . '/allgroups/*.xml') as $xmlfile) {
        $contents = file_get_contents($xmlfile);
        preg_match_all('/<fname>.*?<\/fname>/', $contents, $matches);

        foreach ($matches[0] as $match) {
            if (preg_match('/<\/?[ib]>/', $match)) {
                $fnameErrors[] = $xmlfile;
                $fnameErrors[] = $match;
            }
        }
    }
    foreach (glob($migrationFolder . '/nongroups/*.xml') as $xmlfile) {
        $contents = file_get_contents($xmlfile);
        preg_match_all('/<fname>.*?<\/fname>/', $contents, $matches);
        foreach ($matches[0] as $match) {
            if (preg_match('/<\/?[ib]>/', $match)) {
                $fnameErrors[] = $xmlfile;
                $fnameErrors[] = $match;
            }
        }
    }

    //Repeat the check for the same problem in the <contact> section

    foreach (glob($migrationFolder . '/allgroups/*.xml') as $xmlfile) {
        $contents = file_get_contents($xmlfile);
        preg_match_all('/<contact>.*?<\/contact>/', $contents, $matches);

        foreach ($matches[0] as $match) {
            if (preg_match('/<\/?[ib]>/', $match)) {
                $fnameErrors[] = $xmlfile;
                $fnameErrors[] = $match;
            }
        }
    }
    foreach (glob($migrationFolder . '/nongroups/*.xml') as $xmlfile) {
        $contents = file_get_contents($xmlfile);
        preg_match_all('/<contact>.*?<\/contact>/', $contents, $matches);
        foreach ($matches[0] as $match) {
            if (preg_match('/<\/?[ib]>/', $match)) {
                $fnameErrors[] = $xmlfile;
                $fnameErrors[] = $match;
            }
        }
    }

    // Check for STX character in xml files 

    foreach (glob($migrationFolder . '/allgroups/*.xml') as $xmlfile) {
        $contents = file($xmlfile);
        $lc = 1;
        foreach ($contents as $line) {
            if (strstr($line, chr(2))) {
                $fnameErrors[] = $xmlfile;
                $fnameErrors[] = "STX found in line $lc";
            }
            $lc++;
        }
    }
    foreach (glob($migrationFolder . '/nongroups/*.xml') as $xmlfile) {
        $contents = file($xmlfile);
        $lc = 1;
        foreach ($contents as $line) {
            if (strstr($line, chr(2))) {
                $fnameErrors[] = $xmlfile;
                $fnameErrors[] = "STX found in line $lc";
            }
            $lc++;
        }
    }
    $xmlfile = $migrationFolder . '/eventlist.xml';
    $contents = file($xmlfile);
    $lc = 1;
    foreach ($contents as $line) {
        if (strstr($line, chr(2))) {
            $fnameErrors[] = $xmlfile;
            $fnameErrors[] = "STX found in line $lc";
        }
        $lc++;
    }

    // If we have errors, save as transient and set status value
    if (count($fnameErrors)) {
        $fnameErrorMsg = '';
        foreach ($fnameErrors as $errorline) {
            $fnameErrorMsg .= '<p>' . htmlentities($errorline) . '</p>';
        }
        set_transient('u3a_fname_errormsg', $fnameErrorMsg);
        wp_redirect(admin_url('admin.php?page=u3a-migrate-menu&status=99'));
        exit;
    }

    wp_redirect(admin_url('admin.php?page=u3a-migrate-menu'));
    
}

// Add function to process the Migration form submission

add_action('admin_post_u3a_do_migration', 'u3a_do_migration');

function u3a_do_migration()
{
    // not bothering with security checks as this plugin is only for migration team use

    if (isset($_POST['importmedia'])) {
        define('IMPORTMEDIA', true);
    }

    // Set Site Name and letter for constructing fname urls from extras.xml file
    u3a_define_sitename();

    // Get a list of all group slugs from the groups file to construct page references correctly
    u3a_create_groupsluglist();

    if (isset($_POST['importgroups'])) {
        addgroups();
        error_log("** groups finished **");
    }

    if (isset($_POST['importevents'])) {
        addevents();
        error_log("** events finished **");
    }

    if (isset($_POST['importothers'])) {
        addotherpages();
        error_log("** other pages finished **");
    }

    wp_redirect(admin_url('admin.php?page=u3a-migrate-menu&status=1'));
}

/**
 * Read the extras.xml file and extract the sitename and base URL for downloading files
 * Define constants SITENAME and BASEURL
 * Set the WordPress sitename
 *
 * @return void
 */

function u3a_define_sitename()
{
    $extras = simplexml_load_file(WP_CONTENT_DIR . "/migration/extras.xml");
    $name = $extras->xpath('section[@id="sitename"]/name');
    $sitename = (string) $name[0];
    if (!empty($sitename)) {
        $sitename = htmlspecialchars_decode($sitename); // anticipate the ampersand!
        define('SITENAME', $sitename);
        update_option('blogname', $sitename);
    } else {
        wp_die("extras.xml does not contain sitename name section");
    }
    $url = $extras->xpath('section[@id="sitename"]/baseurl');
    $siteurl = (string) $url[0];
    if (!empty($siteurl)) {
        define('BASEURL', $siteurl . '/docs');
    } else {
        wp_die("extras.xml does not contain sitename baseurl section");
    }
}

/**
 * There is a problem constructing page links.  The "Stroud" problem.
 * This function retrieves a list of group slugs from the grouplist.xml file
 * that we can refer to to see if a pagref refers to a Group or a regular Page
 *
 * @return void
 */
$group_slug_list = array(); // Declare in global scope

function u3a_create_groupsluglist()
{
    global $group_slug_list;

    $xmlStr = file_get_contents(WP_CONTENT_DIR . "/migration/grouplist.xml") or die("Error: Cannot create object");
    $groupfile = new SimpleXMLIterator($xmlStr);
    foreach ($groupfile->children() as $group) {
        $slug = basename((string) $group->grouppage->file);
        if (!empty($slug)) {
            $group_slug_list[] = $slug;
        }
    }
}
