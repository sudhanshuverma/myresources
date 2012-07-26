<?php

class block_site_main_menu extends block_list {

    function init() {
        $this->title = get_string('pluginname', 'block_site_main_menu');
    }

    function applicable_formats() {
        return array('site' => true);
    }

    function get_content() {
        global $USER, $CFG, $DB, $OUTPUT, $SITE, $FULLME, $ME;


        if ($this->content !== NULL) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->items = array();
        $this->content->icons = array();
        $this->content->footer = '';
        require_once('lib.php');
        if (empty($this->instance)) {
            return $this->content;
        }

        // get the front page course
        $course = $DB->get_record('course', array('id' => $SITE->id), '*', MUST_EXIST);

        require_once($CFG->dirroot . '/course/lib.php');
        $context = get_context_instance(CONTEXT_COURSE, $course->id);
        $isediting = $this->page->user_is_editing() && has_capability('moodle/course:manageactivities', $context);
        $modinfo = get_fast_modinfo($course);
        $user_own_section = $USER->id + 1000;
        $grouped_section = 500; // store the activities here for indiviual or group of student
        // if user id exist        
        if (($USER->id) && ($USER->id != 0)) {
            if ($USER->id != 1) {
                if (!$section = $DB->get_record('course_sections', array('course' => $course->id, 'section' => $user_own_section))) {
                    $section = new stdClass();
                    $section->course = $SITE->id;   // Create a default section.
                    $section->section = $user_own_section;
                    $section->visible = 1;
                    $section->summaryformat = FORMAT_HTML;
                    $section->id = $DB->insert_record('course_sections', $section);
                }
            }
        }

/// slow & hacky editing mode
        $customediting = "on";
        if ($customediting == 'on') {   
            
            $section = get_course_section($user_own_section, $course->id);
            get_all_mods($course->id, $mods, $modnames, $modnamesplural, $modnamesused);
            /// Casting $course->modinfo to string prevents one notice when the field is null
            $editbuttons = '';
            
            if (!empty($section->sequence)) {                
                $sectionmods = explode(',', $section->sequence);
                $options = array('overflowdiv' => true);
                foreach ($sectionmods as $modnumber) {
                    if (empty($mods[$modnumber])) {
                        continue;
                    }
                    $mod = $mods[$modnumber];
//                    if (!$ismoving) {
//                        if ($groupbuttons) {
//                            if (!$mod->groupmodelink = $groupbuttonslink) {
//                                $mod->groupmode = $course->groupmode;
//                            }
//                        } else {
//                            $mod->groupmode = false;
//                        }
                        $editbuttons = '<div class="buttons">' . make_editing_buttons_custom($mod, true, true) . '</div>';
//                    } else {
//                        $editbuttons = '';
//                    }
                    if ($mod->visible) {

                        list($content, $instancename) =
                                get_print_section_cm_text($modinfo->cms[$modnumber], $course);
                        $linkcss = $mod->visible ? '' : ' class="dimmed" ';

                        if (!($url = $mod->get_url())) {
                            $this->content->items[] = $content . $editbuttons;
                            $this->content->icons[] = '';
                        } else {
                            //Accessibility: incidental image - should be empty Alt text
                            $icon = '<img src="' . $mod->get_icon_url() . '" class="icon" alt="" />&nbsp;';
                            $this->content->items[] = '<a title="' . $mod->modfullname . '" ' . $linkcss . ' ' . $mod->extra .
                                    ' href="' . $url . '">' . $icon . $instancename . '</a>' . $editbuttons;
                        }
                    }
                }
            }


            if (!empty($modnames)) {
                $this->content->footer = print_section_add_menus($course, 0, $modnames, true, true);
            } else {
                $this->content->footer = '';
            }

            return $this->content;
        }

/// extra fast view mode
        if (!$isediting) {            
            if (!empty($modinfo->sections[$user_own_section])) {
                $options = array('overflowdiv' => true);
                $resources = $modinfo->sections[$user_own_section]; //merge the two resources                
                foreach ($resources as $cmid) {
                    $cm = $modinfo->cms[$cmid];
                    if (!$cm->uservisible) {
                        continue;
                    }

                    list($content, $instancename) =
                            get_print_section_cm_text($cm, $course);

                    if (!($url = $cm->get_url())) {
                        $this->content->items[] = $content;
                        $this->content->icons[] = '';
                    } else {
                        $linkcss = $cm->visible ? '' : ' class="dimmed" ';
                        //Accessibility: incidental image - should be empty Alt text
                        $icon = '<img src="' . $cm->get_icon_url() . '" class="icon" alt="" />&nbsp;';
                        $this->content->items[] = '<a title="' . $cm->modplural . '" ' . $linkcss . ' ' . $cm->extra .
                                ' href="' . $url . '">' . $icon . $instancename . '</a>';
                    }
                }
                //custom code to show editing on and off button
                $editurl = $this->page->url;
                $editingstatus = optional_param("blockediting", "", PARAM_ALPHA);
                $editurl = rtrim($editurl, "/");
                print_object($editingstatus);
                if ($editingstatus == "on") {
                    // $editurl  = $editurl.'#&blockediting=off'; 
                    $editurl = $editurl->param("blockediting", 'off');
                } else if ($editingstatus == "off") {
                    // $editurl = $editurl.'#&blockediting=on'; 
                    $editurl = $editurl->param("blockediting", 'on');
                } else {
                    $editurl = $editurl . '#&blockediting=on';
                }
                print_object($editingstatus);
                //$editurl = $editurl->param("blockediting", 'on');             

                if (isloggedin()) {
                    $this->content->footer = '<a title="Edit resources" href="' . $editurl . '">' . get_string('editresource', 'block_site_main_menu') . '</a>';
                }
            }
            get_all_mods($course->id, $mods, $modnames, $modnamesplural, $modnamesused);
            if (isloggedin()) {
                if (!empty($modnames)) {
                    $this->content->footer .= print_section_add_menus_custom($course, $user_own_section, $modnames, true, true);
                } else {
                    $this->content->footer .= "";
                }
            }
            return $this->content;
        }
    }

}

