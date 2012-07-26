<?php

/*
 * Custom functions
 * 
 */

require_once($CFG->dirroot . '/blocks/community/locallib.php');
/**
 * Prints the menus to add activities and resources.
 */
function print_section_add_menus_custom($course, $section, $modnames, $vertical=false, $return=false, $sectionreturn = false) {
    global $CFG, $OUTPUT, $USER;

    // check to see if user can add menus

//    if (!has_capability('moodle/course:manageactivities', get_context_instance(CONTEXT_COURSE, $course->id))) {
//        return false;
//    }

    // Retrieve all modules with associated metadata
    $modules = get_module_metadata_custom($course, $modnames);

    // We'll sort resources and activities into two lists
    $resources = array();
    $activities = array();

    // We need to add the section section to the link for each module
    $sectionlink = '&section=' . $section;

    // We need to add the section to return to
    if ($sectionreturn) {
        $sectionreturnlink = '&sr=' . $section;
    } else {
        $sectionreturnlink = '&sr=0';
    }

    foreach ($modules as $module) {
        if (isset($module->types)) {
            // This module has a subtype
            // NOTE: this is legacy stuff, module subtypes are very strongly discouraged!!
            $subtypes = array();
            foreach ($module->types as $subtype) {
                $subtypes[$subtype->link . $sectionlink . $sectionreturnlink] = $subtype->title;
            }

            // Sort module subtypes into the list
            if (!empty($module->title)) {
                // This grouping has a name
                if ($module->archetype == MOD_CLASS_RESOURCE) {
                    $resources[] = array($module->title=>$subtypes);
                } else {
                    $activities[] = array($module->title=>$subtypes);
                }
            } else {
                // This grouping does not have a name
                if ($module->archetype == MOD_CLASS_RESOURCE) {
                    $resources = array_merge($resources, $subtypes);
                } else {
                    $activities = array_merge($activities, $subtypes);
                }
            }
        } else {
            // This module has no subtypes
            if ($module->archetype == MOD_ARCHETYPE_RESOURCE) {
                $resources[$module->link . $sectionlink . $sectionreturnlink] = $module->title;
            } else if ($module->archetype === MOD_ARCHETYPE_SYSTEM) {
                // System modules cannot be added by user, do not add to dropdown
            } else {
                $activities[$module->link . $sectionlink . $sectionreturnlink] = $module->title;
            }
        }
    }

    $straddactivity = get_string('addactivity');
    $straddresource = get_string('addresource');

    $output = html_writer::start_tag('div', array('class' => 'section_add_menus', 'id' => 'add_menus-section-' . $section));

    if (!$vertical) {
        $output .= html_writer::start_tag('div', array('class' => 'horizontal'));
    }

    if (!empty($resources)) {
        $select = new url_select($resources, '', array(''=>$straddresource), "ressection$section");
        $select->set_help_icon('resources');
        $output .= $OUTPUT->render($select);
    }

    if (!empty($activities)) {
        $select = new url_select($activities, '', array(''=>$straddactivity), "section$section");
        $select->set_help_icon('activities');
        //x$output .= $OUTPUT->render($select);
    }

    if (!$vertical) {
        $output .= html_writer::end_tag('div');
    }

    $output .= html_writer::end_tag('div');   
    if (course_ajax_enabled_custom($course)) {      
        $straddeither = get_string('addresourceoractivity');
        // The module chooser link
        $modchooser = html_writer::start_tag('div', array('class' => 'mdl-right'));
        $modchooser.= html_writer::start_tag('div', array('class' => 'section-modchooser'));
        $icon = $OUTPUT->pix_icon('t/add', $straddeither);
        $span = html_writer::tag('span', $straddeither, array('class' => 'section-modchooser-text'));
        $modchooser .= html_writer::tag('span', $icon . $span, array('class' => 'section-modchooser-link'));
        $modchooser.= html_writer::end_tag('div');
        $modchooser.= html_writer::end_tag('div');
       
      

        // Wrap the normal output in a noscript div
        $usemodchooser = get_user_preferences('usemodchooser', 1);       
        if ($usemodchooser) {           
            $output = html_writer::tag('div', $output, array('class' => 'hiddenifjs addresourcedropdown'));
            $modchooser = html_writer::tag('div', $modchooser, array('class' => 'visibleifjs addresourcemodchooser'));
        } else {
            $output = html_writer::tag('div', $output, array('class' => 'visibleifjs addresourcedropdown'));
            $modchooser = html_writer::tag('div', $modchooser, array('class' => 'hiddenifjs addresourcemodchooser'));
        }
        $output = $modchooser . $output;
    }

    if ($return) {
        return $output;
    } else {
        echo $output;
    }
}


/**
 * Retrieve all metadata for the requested modules
 *
 * @param object $course The Course
 * @param array $modnames An array containing the list of modules and their
 * names
 * @param int $sectionreturn The section to return to
 * @return array A list of stdClass objects containing metadata about each
 * module
 */
function get_module_metadata_custom($course, $modnames, $sectionreturn = 0) {
    global $CFG, $OUTPUT;

    // get_module_metadata will be called once per section on the page and courses may show
    // different modules to one another
    static $modlist = array();
    
    if (!isset($modlist[$course->id])) {
        $modlist[$course->id] = array();
    }
    
    $return = array();
    $urlbase = "/course/mod.php?id=$course->id&sesskey=".sesskey().'&sr='.$sectionreturn.'&add=';    
    foreach($modnames as $modname => $modnamestr) {
//        if (!course_allowed_module($course, $modname)) {
//            continue;
//        }
        if (isset($modlist[$modname])) {
            // This module is already cached
            $return[$modname] = $modlist[$course->id][$modname];
            continue;
        }

        // Include the module lib
        $libfile = "$CFG->dirroot/mod/$modname/lib.php";
        if (!file_exists($libfile)) {
            continue;
        }
        include_once($libfile);

        // NOTE: this is legacy stuff, module subtypes are very strongly discouraged!!
        $gettypesfunc =  $modname.'_get_types';
        if (function_exists($gettypesfunc)) {
            if ($types = $gettypesfunc()) {
                $group = new stdClass();
                $group->name = $modname;
                $group->icon = $OUTPUT->pix_icon('icon', '', $modname, array('class' => 'icon'));
                foreach($types as $type) {
                    if ($type->typestr === '--') {
                        continue;
                    }
                    if (strpos($type->typestr, '--') === 0) {
                        $group->title = str_replace('--', '', $type->typestr);
                        continue;
                    }
                    // Set the Sub Type metadata
                    $subtype = new stdClass();
                    $subtype->title = $type->typestr;
                    $subtype->type = str_replace('&amp;', '&', $type->type);
                    $subtype->name = preg_replace('/.*type=/', '', $subtype->type);
                    $subtype->archetype = $type->modclass;

                    // The group archetype should match the subtype archetypes and all subtypes
                    // should have the same archetype
                    $group->archetype = $subtype->archetype;

                    if (get_string_manager()->string_exists('help' . $subtype->name, $modname)) {
                        $subtype->help = get_string('help' . $subtype->name, $modname);
                    }
                    $subtype->link = $urlbase . $subtype->type;
                    $group->types[] = $subtype;
                }
                $modlist[$course->id][$modname] = $group;
            }
        } else {
            $module = new stdClass();
            $module->title = get_string('modulename', $modname);
            $module->name = $modname;
            $module->link = $urlbase . $modname;
            $module->icon = $OUTPUT->pix_icon('icon', '', $module->name, array('class' => 'icon'));
            $sm = get_string_manager();
            if ($sm->string_exists('modulename_help', $modname)) {
                $module->help = get_string('modulename_help', $modname);
                if ($sm->string_exists('modulename_link', $modname)) {  // Link to further info in Moodle docs
                    $link = get_string('modulename_link', $modname);
                    $linktext = get_string('morehelp');
                    $module->help .= html_writer::tag('div', $OUTPUT->doc_link($link, $linktext), array('class' => 'helpdoclink'));
                }
            }
            $module->archetype = plugin_supports('mod', $modname, FEATURE_MOD_ARCHETYPE, MOD_ARCHETYPE_OTHER);
            $modlist[$course->id][$modname] = $module;
        }
        $return[$modname] = $modlist[$course->id][$modname];
    }

    return $return;
}

/**
 * Determine whether course ajax should be enabled for the specified course
 *
 * @param stdClass $course The course to test against
 * @return boolean Whether course ajax is enabled or note
 */
function course_ajax_enabled_custom($course) {
    global $CFG, $PAGE, $SITE;   
    // Ajax must be enabled globall

    if (!$CFG->enableajax) {       
        return false;
    }

    // The user must be editing for AJAX to be included
    if (!$PAGE->user_is_editing()) {
        return false;
    }

    // Check that the theme suports
    if (!$PAGE->theme->enablecourseajax) {
        return false;
    }

    // Check that the course format supports ajax functionality
    // The site 'format' doesn't have information on course format support
    if ($SITE->id !== $course->id) {
        $courseformatajaxsupport = course_format_ajax_support($course->format);
        if (!$courseformatajaxsupport->capable) {
            return false;
        }
    }

    // All conditions have been met so course ajax should be enabled
    return true;
}
/**
 * Produces the editing buttons for a module
 *
 * @global core_renderer $OUTPUT
 * @staticvar type $str
 * @param stdClass $mod The module to produce editing buttons for
 * @param bool $absolute_ignored ignored - all links are absolute
 * @param bool $moveselect If true a move seleciton process is used (default true)
 * @param int $indent The current indenting
 * @param int $section The section to link back to
 * @return string XHTML for the editing buttons
 */
function make_editing_buttons_custom(stdClass $mod, $absolute_ignored = true, $moveselect = true, $indent=-1, $section=-1) {
    global $CFG, $OUTPUT, $COURSE, $USER;

    static $str;
    $section = $USER->id + 1000;
    $coursecontext = get_context_instance(CONTEXT_COURSE, $mod->course);
    $modcontext = get_context_instance(CONTEXT_MODULE, $mod->id);

    $editcaps = array('moodle/course:manageactivities', 'moodle/course:activityvisibility', 'moodle/role:assign');
    $dupecaps = array('moodle/backup:backuptargetimport', 'moodle/restore:restoretargetimport');

    // no permission to edit anything
//    if (!has_any_capability($editcaps, $modcontext) and !has_all_capabilities($dupecaps, $coursecontext)) {
//        return false;
//    }

  //  $hasmanageactivities = has_capability('moodle/course:manageactivities', $modcontext);

    if (!isset($str)) {
        $str = new stdClass;
        $str->assign         = get_string("assignroles", 'role');
        $str->delete         = get_string("delete");
        $str->move           = get_string("move");
        $str->moveup         = get_string("moveup");
        $str->movedown       = get_string("movedown");
        $str->moveright      = get_string("moveright");
        $str->moveleft       = get_string("moveleft");
        $str->update         = get_string("update");
        $str->duplicate      = get_string("duplicate");
        $str->hide           = get_string("hide");
        $str->show           = get_string("show");
        $str->groupsnone     = get_string('clicktochangeinbrackets', 'moodle', get_string("groupsnone"));
        $str->groupsseparate = get_string('clicktochangeinbrackets', 'moodle', get_string("groupsseparate"));
        $str->groupsvisible  = get_string('clicktochangeinbrackets', 'moodle', get_string("groupsvisible"));
        $str->forcedgroupsnone     = get_string('forcedmodeinbrackets', 'moodle', get_string("groupsnone"));
        $str->forcedgroupsseparate = get_string('forcedmodeinbrackets', 'moodle', get_string("groupsseparate"));
        $str->forcedgroupsvisible  = get_string('forcedmodeinbrackets', 'moodle', get_string("groupsvisible"));
        $str->edittitle = get_string('edittitle', 'moodle');
    }

    $baseurl = new moodle_url('/course/mod.php', array('sesskey' => sesskey()));

    if ($section >= 0) {
        $baseurl->param('sr', $section);
    }
    $actions = array();
    // AJAX edit title
    if ($mod->modname !== 'label'  && course_ajax_enabled($COURSE)) {
        $actions[] = new action_link(
            new moodle_url($baseurl, array('update' => $mod->id)),
            new pix_icon('t/editstring', $str->edittitle, 'moodle', array('class' => 'iconsmall visibleifjs')),
            null,
            array('class' => 'editing_title', 'title' => $str->edittitle)
        );
    }

    // leftright
//    if ($hasmanageactivities) {
//        if (right_to_left()) {   // Exchange arrows on RTL
//            $rightarrow = 't/left';
//            $leftarrow  = 't/right';
//        } else {
//            $rightarrow = 't/right';
//            $leftarrow  = 't/left';
//        }
//
//        if ($indent > 0) {
//            $actions[] = new action_link(
//                new moodle_url($baseurl, array('id' => $mod->id, 'indent' => '-1')),
//                new pix_icon($leftarrow, $str->moveleft, 'moodle', array('class' => 'iconsmall')),
//                null,
//                array('class' => 'editing_moveleft', 'title' => $str->moveleft)
//            );
//        }
//        if ($indent >= 0) {
//            $actions[] = new action_link(
//                new moodle_url($baseurl, array('id' => $mod->id, 'indent' => '1')),
//                new pix_icon($rightarrow, $str->moveright, 'moodle', array('class' => 'iconsmall')),
//                null,
//                array('class' => 'editing_moveright', 'title' => $str->moveright)
//            );
//        }
//    }

    // move
//    if ($hasmanageactivities) {
//        if ($moveselect) {
//            $actions[] = new action_link(
//                new moodle_url($baseurl, array('copy' => $mod->id)),
//                new pix_icon('t/move', $str->move, 'moodle', array('class' => 'iconsmall')),
//                null,
//                array('class' => 'editing_move', 'title' => $str->move)
//            );
//        } else {
//            $actions[] = new action_link(
//                new moodle_url($baseurl, array('id' => $mod->id, 'move' => '-1')),
//                new pix_icon('t/up', $str->moveup, 'moodle', array('class' => 'iconsmall')),
//                null,
//                array('class' => 'editing_moveup', 'title' => $str->moveup)
//            );
//            $actions[] = new action_link(
//                new moodle_url($baseurl, array('id' => $mod->id, 'move' => '1')),
//                new pix_icon('t/down', $str->movedown, 'moodle', array('class' => 'iconsmall')),
//                null,
//                array('class' => 'editing_movedown', 'title' => $str->movedown)
//            );
//        }
//    }

    // Update
     $hasmanageactivities = true;
    if ($hasmanageactivities) {
        $actions[] = new action_link(
            new moodle_url($baseurl, array('update' => $mod->id, 'section'=>$section, 'fromblock'=>'myresources')),
            new pix_icon('t/edit', $str->update, 'moodle', array('class' => 'iconsmall')),
            null,
            array('class' => 'editing_update', 'title' => $str->update)
        );
    }

    // Duplicate (require both target import caps to be able to duplicate, see modduplicate.php)
    if (!has_all_capabilities($dupecaps, $coursecontext)) {
        $actions[] = new action_link(
            new moodle_url($baseurl, array('duplicate' => $mod->id)),
            new pix_icon('t/copy', $str->duplicate, 'moodle', array('class' => 'iconsmall')),
            null,
            array('class' => 'editing_duplicate', 'title' => $str->duplicate)
        );
    }

    // Delete
    if ($hasmanageactivities) {
        $actions[] = new action_link(
            new moodle_url($baseurl, array('delete' => $mod->id)),
            new pix_icon('t/delete', $str->delete, 'moodle', array('class' => 'iconsmall')),
            null,
            array('class' => 'editing_delete', 'title' => $str->delete)
        );
    }

    // hideshow
    if (!has_capability('moodle/course:activityvisibility', $modcontext)) {
        if ($mod->visible) {
            $actions[] = new action_link(
                new moodle_url($baseurl, array('hide' => $mod->id)),
                new pix_icon('t/hide', $str->hide, 'moodle', array('class' => 'iconsmall')),
                null,
                array('class' => 'editing_hide', 'title' => $str->hide)
            );
        } else {
            $actions[] = new action_link(
                new moodle_url($baseurl, array('show' => $mod->id)),
                new pix_icon('t/show', $str->show, 'moodle', array('class' => 'iconsmall')),
                null,
                array('class' => 'editing_show', 'title' => $str->show)
            );
        }
    }

    // groupmode
//    if ($hasmanageactivities and $mod->groupmode !== false) {
//        if ($mod->groupmode == SEPARATEGROUPS) {
//            $groupmode = 0;
//            $grouptitle = $str->groupsseparate;
//            $forcedgrouptitle = $str->forcedgroupsseparate;
//            $groupclass = 'editing_groupsseparate';
//            $groupimage = 't/groups';
//        } else if ($mod->groupmode == VISIBLEGROUPS) {
//            $groupmode = 1;
//            $grouptitle = $str->groupsvisible;
//            $forcedgrouptitle = $str->forcedgroupsvisible;
//            $groupclass = 'editing_groupsvisible';
//            $groupimage = 't/groupv';
//        } else {
//            $groupmode = 2;
//            $grouptitle = $str->groupsnone;
//            $forcedgrouptitle = $str->forcedgroupsnone;
//            $groupclass = 'editing_groupsnone';
//            $groupimage = 't/groupn';
//        }
//        if ($mod->groupmodelink) {
//            $actions[] = new action_link(
//                new moodle_url($baseurl, array('id' => $mod->id, 'groupmode' => $groupmode)),
//                new pix_icon($groupimage, $grouptitle, 'moodle', array('class' => 'iconsmall')),
//                null,
//                array('class' => $groupclass, 'title' => $grouptitle)
//            );
//        } else {
//            $actions[] = new pix_icon($groupimage, $forcedgrouptitle, 'moodle', array('title' => $forcedgrouptitle, 'class' => 'iconsmall'));
//        }
//    }

    // Assign
//    if (has_capability('moodle/role:assign', $modcontext)){
//        $actions[] = new action_link(
//            new moodle_url('/'.$CFG->admin.'/roles/assign.php', array('contextid' => $modcontext->id)),
//            new pix_icon('i/roles', $str->assign, 'moodle', array('class' => 'iconsmall')),
//            null,
//            array('class' => 'editing_assign', 'title' => $str->assign)
//        );
//    }

    $output = html_writer::start_tag('span', array('class' => 'commands'));
    foreach ($actions as $action) {
        if ($action instanceof renderable) {
            $output .= $OUTPUT->render($action);
        } else {
            $output .= $action;
        }
    }
    $output .= html_writer::end_tag('span');
    return $output;
}

?>
