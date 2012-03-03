<?php # $Id$

if (IN_serendipity !== true) {
    die ("Don't hack!");
}

if (!serendipity_checkPermission('adminEntries')) {
    return;
}

$sort_order = array('timestamp'     => DATE,
                    'isdraft'       => PUBLISH . '/' . DRAFT,
                    'a.realname'    => AUTHOR,
                    'category_name' => CATEGORY,
                    'last_modified' => LAST_UPDATED,
                    'title'         => TITLE,
                    'id'            => 'ID');
$per_page = array('12', '16', '50', '100');

$data = array();

/**
 * Shows the entry panel overview
 *
 * Shows a list of existing entries, with pagination and cookie-remember settings.
 *
 * @access public
 * @return null
 */
function serendipity_drawList($data=array()) {
    global $serendipity, $sort_order, $per_page;

    $filter_import = array('author', 'category', 'isdraft');
    $sort_import   = array('perPage', 'ordermode', 'order');
    foreach($filter_import AS $f_import) {
        serendipity_restoreVar($serendipity['COOKIE']['entrylist_filter_' . $f_import], $serendipity['GET']['filter'][$f_import]);
        serendipity_JSsetCookie('entrylist_filter_' . $f_import, $serendipity['GET']['filter'][$f_import]);
    }

    foreach($sort_import AS $s_import) {
        serendipity_restoreVar($serendipity['COOKIE']['entrylist_sort_' . $s_import], $serendipity['GET']['sort'][$s_import]);
        serendipity_JSsetCookie('entrylist_sort_' . $s_import, $serendipity['GET']['sort'][$s_import]);
    }

    $perPage = (!empty($serendipity['GET']['sort']['perPage']) ? $serendipity['GET']['sort']['perPage'] : $per_page[0]);
    $page    = (int)$serendipity['GET']['page'];
    $offSet  = $perPage*$page;

    if (empty($serendipity['GET']['sort']['ordermode']) || $serendipity['GET']['sort']['ordermode'] != 'ASC') {
        $serendipity['GET']['sort']['ordermode'] = 'DESC';
    }

    if (!empty($serendipity['GET']['sort']['order']) && !empty($sort_order[$serendipity['GET']['sort']['order']])) {
        $orderby = serendipity_db_escape_string($serendipity['GET']['sort']['order'] . ' ' . $serendipity['GET']['sort']['ordermode']);
    } else {
        $orderby = 'timestamp ' . serendipity_db_escape_string($serendipity['GET']['sort']['ordermode']);
    }

    $filter = array();

    if (!empty($serendipity['GET']['filter']['author'])) {
        $filter[] = "e.authorid = '" . serendipity_db_escape_string($serendipity['GET']['filter']['author']) . "'";
    }

    if (!empty($serendipity['GET']['filter']['category'])) {
        $filter[] = "ec.categoryid = '" . serendipity_db_escape_string($serendipity['GET']['filter']['category']) . "'";
    }

    if (!empty($serendipity['GET']['filter']['isdraft'])) {
        if ($serendipity['GET']['filter']['isdraft'] == 'draft') {
            $filter[] = "e.isdraft = 'true'";
        } elseif ($serendipity['GET']['filter']['isdraft'] == 'publish') {
            $filter[] = "e.isdraft = 'false'";
        }
    }

    if (!empty($serendipity['GET']['filter']['body'])) {
        if ($serendipity['dbType'] == 'mysql') {
            $filter[] = "MATCH (title,body,extended) AGAINST ('" . serendipity_db_escape_string($serendipity['GET']['filter']['body']) . "')";
            $full     = true;
        }
    }

    $filter_sql = implode(' AND ', $filter);

    // Fetch the entries
    $entries = serendipity_fetchEntries(
                 false,
                 false,
                 serendipity_db_limit(
                   $offSet,
                   $perPage + 1
                 ),
                 true,
                 false,
                 $orderby,
                 $filter_sql
               );

    $users      = serendipity_fetchUsers('', 'hidden', true);
    $categories = serendipity_fetchCategories();
    $categories = serendipity_walkRecursive($categories, 'categoryid', 'parentid', VIEWMODE_THREADED);

    $serendipity['smarty']->assign( array(
                                'drawList'   => true,
                                'entries'    => $entries,
                                'sort_order' => $sort_order,
                                'per_page'   => $per_page,
                                'urltoken'   => serendipity_setFormToken('url'),
                                'formtoken'  => serendipity_setFormToken(),
                                'users'      => $users,
                                'categories' => $categories,
                                'offSet'     => $offSet,
                                'use_iframe' => $serendipity['use_iframe']
                                )
                            );

    if (is_array($entries)) {
        $data['is_entries'] = true;
        $data['count'] = count($entries);

        $qString = '?serendipity[adminModule]=entries&amp;serendipity[adminAction]=editSelect';
        foreach ((array)$serendipity['GET']['sort'] as $k => $v) {
            $qString .= '&amp;serendipity[sort]['. $k .']='. $v;
        }
        foreach ((array)$serendipity['GET']['filter'] as $k => $v) {
            $qString .= '&amp;serendipity[filter]['. $k .']='. $v;
        }
        $data['linkPrevious'] = $qString . '&amp;serendipity[page]=' . ($page-1);
        $data['linkNext']     = $qString . '&amp;serendipity[page]=' . ($page+1);

        // Print the entries
        $entry = array();
        foreach ($entries as $ey) {
            // Find out if the entry has been modified later than 30 minutes after creation
            if ($ey['timestamp'] <= ($ey['last_modified'] - 60*30)) {
                $lm = '<a href="#" title="' . LAST_UPDATED . ': ' . serendipity_formatTime(DATE_FORMAT_SHORT, $ey['last_modified']) . '" onclick="alert(this.title)"><img src="'. serendipity_getTemplateFile('admin/img/clock.png') .'" alt="*" /></a>';
            } else {
                $lm = '';
            }

            if (!$serendipity['showFutureEntries'] && $ey['timestamp'] >= serendipity_serverOffsetHour()) {
                $entry_pre = '<a href="#" title="' . ENTRY_PUBLISHED_FUTURE . '" onclick="alert(this.title)"><img src="'. serendipity_getTemplateFile('admin/img/clock_future.png') .'" alt="*" /></a> ';
            } else {
                $entry_pre = '';
            }

            if (serendipity_db_bool($ey['properties']['ep_is_sticky'])) {
                $entry_pre .= ' ' . STICKY_POSTINGS . ': ';
            }

            if (count($ey['categories'])) {
                $cats = array();
                foreach ($ey['categories'] as $cat) {
                    $caturl = serendipity_categoryURL($cat);
                    $cats[] = '<a href="' . $caturl . '">' . htmlspecialchars($cat['category_name']) . '</a>';
                }
                $entry_cats = implode(', ', $cats);
            }

            $entry[] = array(
                'clock'        => $entry_pre,
                'id'           => $ey['id'],
                'title'        => htmlspecialchars($ey['title']),
                'pubdate'      => date("c", (int)$ey['timestamp']),
                'stime'        => serendipity_formatTime(DATE_FORMAT_SHORT, $ey['timestamp']) . ' ' .$lm,
                'author'       => htmlspecialchars($ey['author']),
                'cats'         => $entry_cats,
                'link'         => serendipity_archiveURL($ey['id'], $ey['title'], 'serendipityHTTPPath', true, array('timestamp' => $ey['timestamp'])),
                'draft_pre'    => ((serendipity_db_bool($ey['isdraft']) || (!$serendipity['showFutureEntries'] && $ey['timestamp'] >= serendipity_serverOffsetHour())) ? true : false),
                'link'         => serendipity_archiveURL($ey['id'], $ey['title'], 'serendipityHTTPPath', true, array('timestamp' => $ey['timestamp'])),
                'preview_link' => '?serendipity[noBanner]=true&amp;serendipity[noSidebar]=true&amp;serendipity[action]=admin&amp;serendipity[adminModule]=entries&amp;serendipity[adminAction]=preview&amp;serendipity[id]=' . $ey['id']
            );

        } // end entries output

        $serendipity['smarty']->assign(
                        array(  'urltoken'          => serendipity_setFormToken('url'),
                                'formtoken'         => serendipity_setFormToken(),
                                'serverOffsetHours' => serendipity_serverOffsetHour(),
                                'showFutureEntries' => $serendipity['showFutureEntries']
                            ));

    } // entries end 

} // End function serendipity_drawList()

if (!empty($serendipity['GET']['editSubmit'])) {
    $serendipity['GET']['adminAction'] = 'edit'; // does this change smarty.get vars?
}

$preview_only = false;

// very sticky smartification to origin, could be done better, I assume!

switch($serendipity['GET']['adminAction']) {
    case 'preview':
        $entry        = serendipity_fetchEntry('id', $serendipity['GET']['id'], 1, 1);
        $serendipity['POST']['preview'] = true;
        $preview_only = true;

    case 'save':
        if (!$preview_only) {
            $entry = array(
                       'id'                 => $serendipity['POST']['id'],
                       'title'              => $serendipity['POST']['title'],
                       'timestamp'          => $serendipity['POST']['timestamp'],
                       'body'               => $serendipity['POST']['body'],
                       'extended'           => $serendipity['POST']['extended'],
                       'categories'         => $serendipity['POST']['categories'],
                       'isdraft'            => $serendipity['POST']['isdraft'],
                       'allow_comments'     => $serendipity['POST']['allow_comments'],
                       'moderate_comments'  => $serendipity['POST']['moderate_comments'],
                       'exflag'             => (!empty($serendipity['POST']['extended']) ? true : false),
                       // Messing with other attributes causes problems when entry is saved

            );
        }

        if ($entry['allow_comments'] != 'true' && $entry['allow_comments'] !== true) {
            $entry['allow_comments'] = 'false';
        }

        if ($entry['moderate_comments'] != 'true' && $entry['moderate_comments'] !== true) {
            $entry['moderate_comments'] = 'false';
        }

        // Check if the user changed the timestamp.
        if (isset($serendipity['allowDateManipulation']) && $serendipity['allowDateManipulation'] && isset($serendipity['POST']['new_timestamp']) && $serendipity['POST']['new_timestamp'] != date(DATE_FORMAT_2, $serendipity['POST']['chk_timestamp'])) {
            // The user changed the timestamp, now set the DB-timestamp to the user's date
            $entry['timestamp'] = strtotime($serendipity['POST']['new_timestamp']);

            if ($entry['timestamp'] == -1) {
                $data['switched_output'] = true;
                $data['dateval'] = false;
                // The date given by the user is not convertable. Reset the timestamp.
                $entry['timestamp'] = $serendipity['POST']['timestamp'];
            }
        }

        // Save server timezone in database always, so substract the offset we added for display; otherwise it would be added time and again
        if (!empty($entry['timestamp'])) {
            $entry['timestamp'] = serendipity_serverOffsetHour($entry['timestamp'], true);
        }

        // Save the entry, or just display a preview
        $use_legacy = true;
        $data['use_legacy'] = $use_legacy;
        serendipity_plugin_api::hook_event('backend_entry_iframe', $use_legacy);

        if ($use_legacy) {
            $data['switched_output'] = true;
            if ($serendipity['POST']['preview'] != 'true') {
                /* We don't need an iframe to save a draft */
                if ( $serendipity['POST']['isdraft'] == 'true' ) {
                    $data['is_draft'] = true;
                    serendipity_updertEntry($entry);
                } else {
                    if ($serendipity['use_iframe']) {
                        $data['is_iframe'] = true;
                        serendipity_iframe_create('save', $entry);
                    } else {
                        serendipity_iframe($entry, 'save');
                    }
                }
            } else {
                // Only display the preview
                $serendipity['hidefooter'] = true;
                // Advanced templates use this to show update status and elapsed time
                if (!is_numeric($entry['last_modified'])) {
                    $entry['last_modified'] = time();
                }

                if (!is_numeric($entry['timestamp'])) {
                    $entry['timestamp']  = time();
                }

                if (!isset($entry['trackbacks']) || !$entry['trackbacks']) {
                    $entry['trackbacks'] = 0;
                }

                if (!isset($entry['comments']) || !$entry['comments']) {
                    $entry['comments']   = 0;
                }

                if (!isset($entry['realname']) || !$entry['realname']) {
                    if (is_numeric($entry['id'])) {
                        $_entry = serendipity_fetchEntry('id', $entry['id'], 1, 1);
                        $entry['realname']   = $_entry['author'];
                    } elseif (!empty($serendipity['realname'])) {
                        $entry['realname']   = $serendipity['realname'];
                    } else {
                        $entry['realname']   = $serendipity['serendipityUser'];
                    }
                }

                $categories = (array)$entry['categories'];
                $entry['categories'] = array();
                foreach ($categories as $catid) {
                    if ($catid == 0) {
                        continue;
                    }
                    $entry['categories'][] = serendipity_fetchCategoryInfo($catid);
                }

                if (count($entry['categories']) < 1) {
                    unset($entry['categories']);
                }

                if (isset($entry['id'])) {
                    $serendipity['GET']['id'] = $entry['id'];
                } else {
                    $serendipity['GET']['id'] = 1;
                }

                if ($serendipity['use_iframe']) {
                    $data['is_iframepreview'] = true;
                    serendipity_iframe_create('preview', $entry);
                } else {
                    serendipity_iframe($entry, 'preview');
                }
            }
        }

        // serendipity_updertEntry sets this global variable to store the entry id. Couldn't pass this
        // by reference or as return value because it affects too many places inside our API and dependant
        // function calls.
        if (!empty($serendipity['lastSavedEntry'])) {
            $entry['id'] = $serendipity['lastSavedEntry'];
        }

        if (!$preview_only) {
            include_once S9Y_INCLUDE_PATH . 'include/functions_entries_admin.inc.php';
            serendipity_printEntryForm(
                '?',
                array(
                  'serendipity[action]'      => 'admin',
                  'serendipity[adminModule]' => 'entries',
                  'serendipity[adminAction]' => 'save',
                  'serendipity[timestamp]'   => $entry['timestamp']
                ),
                $entry
            );
        }

        break;

    case 'doDelete':
        if (!serendipity_checkFormToken()) {
            break;
        }

        $entry = serendipity_fetchEntry('id', $serendipity['GET']['id'], 1, 1);
        serendipity_deleteEntry((int)$serendipity['GET']['id']);
        $data['switched_output'] = true;
        $data['is_doDelete']     = true;
        $data['del_entry']       = sprintf(RIP_ENTRY, $entry['id'] . ' - ' . htmlspecialchars($entry['title']));
        $cont_draw = true;

    case 'doMultiDelete':
        if (!isset($cont_draw)) {
            if (!serendipity_checkFormToken() || !isset($serendipity['GET']['id'])) {
                break;
            }

            $parts = explode(',', $serendipity['GET']['id']);
            $data['switched_output'] = true;
            $data['del_entry']       = array();
            foreach($parts AS $id) {
                $id = (int)$id;
                if ($id > 0) {
                    $entry = serendipity_fetchEntry('id', $id, 1, 1);
                    serendipity_deleteEntry((int)$id);
                    $data['is_doMultiDelete'] = true;
                    $data['del_entry'][]      = sprintf(RIP_ENTRY, $entry['id'] . ' - ' . htmlspecialchars($entry['title']));
                }
            }
        }

    case 'editSelect':
        serendipity_drawList($data);
        break;

    case 'delete':
        if (!serendipity_checkFormToken()) {
            break;
        }
        $newLoc = '?' . serendipity_setFormToken('url') . '&amp;serendipity[action]=admin&amp;serendipity[adminModule]=entries&amp;serendipity[adminAction]=doDelete&amp;serendipity[id]=' . (int)$serendipity['GET']['id'];

        $entry = serendipity_fetchEntry('id', $serendipity['GET']['id'], 1, 1);
        $data['switched_output'] = true;
        $data['is_delete']       = true;
        $data['newLoc']          = $newLoc;
        // for smartification printf had to turn into sprintf!!
        $data['rip_entry']       = sprintf(DELETE_SURE, $entry['id'] . ' - ' . htmlspecialchars($entry['title']));
        break;

    case 'multidelete':
        if (!serendipity_checkFormToken() || !is_array($serendipity['POST']['multiDelete'])) {
            break;
        }

        $ids = '';
        $data['rip_entry'] = array();
        foreach($serendipity['POST']['multiDelete'] AS $idx => $id) {
            $ids .= (int)$id . ',';
            $entry = serendipity_fetchEntry('id', $id, 1, 1);
            $data['is_multidelete'] = true;
            $data['rip_entry'][]    = sprintf(DELETE_SURE, $entry['id'] . ' - ' . htmlspecialchars($entry['title']));
        }
        $newLoc = '?' . serendipity_setFormToken('url') . '&amp;serendipity[action]=admin&amp;serendipity[adminModule]=entries&amp;serendipity[adminAction]=doMultiDelete&amp;serendipity[id]=' . $ids;
        $data['switched_output'] = true;
        $data['newLoc']          = $newLoc;
        break;

    case 'edit':
        $entry = serendipity_fetchEntry('id', $serendipity['GET']['id'], 1, 1);

    default:
        include_once S9Y_INCLUDE_PATH . 'include/functions_entries_admin.inc.php';
        // edit entry mode
        serendipity_printEntryForm(
            '?',
            array(
            'serendipity[action]'      => 'admin',
            'serendipity[adminModule]' => 'entries',
            'serendipity[adminAction]' => 'save'
            ),
            (isset($entry) ? $entry : array())
        );
}

$data['get']       = $serendipity['GET']; // don't trust {$smarty.get.vars} if not proofed, as we often change GET vars via serendipty['GET'] by runtime
// make sure we've got these
$data['urltoken']  = serendipity_setFormToken('url');
$data['formtoken'] = serendipity_setFormToken();

if (!is_object($serendipity['smarty'])) {
    serendipity_smarty_init();
}

$serendipity['smarty']->assign($data);

$tfile = dirname(__FILE__) . "/tpl/entries.inc.tpl";

$content = $serendipity['smarty']->fetch('file:'. $tfile); // short notation with Smarty3 in S9y 1.7 and up

echo $content;

/* vim: set sts=4 ts=4 expandtab : */
