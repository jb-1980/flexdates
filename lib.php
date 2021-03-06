<?php

defined('MOODLE_INTERNAL') || die();

//require('../../config.php');
require_once($CFG->libdir . '/grade/constants.php');
require_once($CFG->libdir . '/grade/grade_category.php');
require_once($CFG->libdir . '/grade/grade_item.php');
require_once($CFG->libdir . '/grade/grade_grade.php');
require_once($CFG->libdir . '/grade/grade_scale.php');
require_once($CFG->libdir . '/grade/grade_outcome.php');
require_once($CFG->libdir . '/gradelib.php');


function local_flexdates_extend_navigation(global_navigation $navigation){
    global $CFG;
    // TODO Put permissions on these so they do not appear for everyone
    $navigation->add('Student Dashboard', new moodle_url($CFG->wwwroot.'/local/flexdates/student.php'), navigation_node::TYPE_CONTAINER);
    $navigation->add('Teacher Dashboard', new moodle_url($CFG->wwwroot.'/local/flexdates/teacher.php'), navigation_node::TYPE_CONTAINER);
    $navigation->add('Advisor Dashboard', new moodle_url($CFG->wwwroot.'/local/flexdates/advisor.php'), navigation_node::TYPE_CONTAINER);
    $navigation->add('Admin Dashboard', new moodle_url($CFG->wwwroot.'/local/flexdates/admin.php'), navigation_node::TYPE_CONTAINER);
}

/**
 * This function extends the navigation with the report items
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course to object for the report
 * @param stdClass $context The context of the course
 */
function local_flexdates_extend_settings_navigation(settings_navigation $navigation, context $context){
    global $CFG,$PAGE;
    if($PAGE->course->id > 1){
        if(has_capability('local/flexdates:modify', $PAGE->context)){
            $flex_node = $navigation->find('courseadmin', navigation_node::TYPE_COURSE)->add(
                              get_string('flexdatessettings','local_flexdates'),
                              navigation_node::TYPE_CONTAINER
                          );
            $flex_track = $flex_node->add(
                                  get_string('trackcourse','local_flexdates'),
                                  new moodle_url($CFG->wwwroot.'/local/flexdates/trackcourse/index.php',array('id'=>$PAGE->course->id)),
                                  navigation_node::TYPE_SETTING,
                                  null, null,
                                  new pix_icon('i/settings', '')
                              );
            $flex_enddates = $flex_node->add(
                                  get_string('enddates','local_flexdates'),
                                  new moodle_url($CFG->wwwroot.'/local/flexdates/completiondates/index.php',array('id'=>$PAGE->course->id)),
                                  navigation_node::TYPE_SETTING,
                                  null, null,
                                  new pix_icon('i/calendar', '')
                              );
            $flex_durations = $flex_node->add(
                                  get_string('duration','local_flexdates'),
                                  new moodle_url($CFG->wwwroot.'/local/flexdates/mod_duration/index.php',array('id'=>$PAGE->course->id)),
                                  navigation_node::TYPE_SETTING,
                                  null, null,
                                  new pix_icon('i/calendar', '')
                              );
        }
    }
}

/*
 * Returns a list of course ids that are set up for progress tracking
 * @param int $userid a single user id
 * @return array
 */
function flexdates_get_tracked_courses($userid){
    global $DB,$CFG;
    $records = enrol_get_users_courses($userid, true, 'summary', $sort = 'visible DESC,sortorder ASC');
    $courses = new stdClass;
    $courses->active = array();
    $courses->resources = array();
    foreach($records as $id=>$course){
        if($record = $DB->get_record('local_fd_trackcourse',array('courseid'=>$course->id))){
            if($record->track){
                $sql = "SELECT ue.timestart 
                          FROM {$CFG->prefix}user_enrolments ue
                          JOIN {$CFG->prefix}enrol e ON e.id=ue.enrolid 
                         WHERE e.enrol = 'manual'
                               AND e.courseid = {$course->id}
                               AND ue.userid = {$userid};";
                $enrol_record = $DB->get_record_sql($sql, array(), $strictness=MUST_EXIST);
                $data = new stdClass;
                $data->id  = $course->id;
                $data->title = $course->shortname;
                $data->name = $course->fullname;
                $data->summary = $course->summary;
                $data->startdate = $enrol_record->timestart;
                $data->grades = flexdates_get_student_grades($course->id,$userid);
                $courses->active[$data->id] = $data;
            } else{
                $data = new stdClass;
                $data->id = $course->id;
                $data->title = $course->shortname;
                $data->summary = $course->summary;
                $courses->resources[] = $data;
            }
        } else{
            $data = new stdClass;
            $data->id = $course->id;
            $data->title = $course->shortname;
            $data->summary = $course->summary;
            $courses->resources[] = $data;
        }
    }
    return $courses;
}

/**
 *
 */
function flexdates_get_mastery_level($courseid,$level){
    global $DB;
    $levels = $DB->get_record('local_fd_trackcourse',array('courseid'=>$courseid));
    if($level == null){
        return 'notstarted';
    } elseif($level >= $levels->mastered){
        return 'mastered';
    } elseif($level >= $levels->level2){
        return 'level2';
    } elseif($level >= $levels->level1){
        return 'level1';
    } elseif($level >= $levels->practiced){
        return 'practiced';
    } else{
        return 'struggling';
    }
}

/**
 * Returns grading information for student grade items in a course
 *
 * @param int $courseid ID of course
 * @param int $userid A single user ID
 * @return array Array of grade information objects (scaleid, name, grade and locked status, etc.) indexed with itemids
 */
function flexdates_get_student_grades($courseid, $userid) {
    global $CFG,$DB;

    $return = new stdClass();
    $return->items    = array();
    $return->outcomes = array();

    $course_item = grade_item::fetch_course_item($courseid);
    $needsupdate = array();
    if ($course_item->needsupdate) {
        $result = grade_regrade_final_grades($courseid);
        if ($result !== true) {
            $needsupdate = array_keys($result);
        }
    }

    $params = array('courseid' => $courseid);
    $sort = 0;
    if ($grade_items = grade_item::fetch_all($params)) {
        //print_object($grade_items);
        foreach ($grade_items as $grade_item) {
            if($grade_item->gradetype == GRADE_TYPE_NONE){
                continue;
            }
            if (empty($grade_item->outcomeid)) {
                // prepare information about grade item
                $item = new stdClass();
                $item->id = $grade_item->id;
                $item->categoryid = $grade_item->categoryid;
                $item->item_category = $grade_item->item_category;
                $item->parent_category = $grade_item->parent_category;
                $item->itemnumber = $grade_item->itemnumber;
                $item->itemtype  = $grade_item->itemtype;
                $item->itemmodule = $grade_item->itemmodule;
                $item->iteminstance = $grade_item->iteminstance;
                $item->scaleid    = $grade_item->scaleid;
                $item->aggregationcoef = $grade_item->aggregationcoef;
                $item->name       = $grade_item->get_name();
                $item->grademin   = $grade_item->grademin;
                $item->grademax   = $grade_item->grademax;
                $item->gradepass  = $grade_item->gradepass;
                $item->locked     = $grade_item->is_locked();
                $item->hidden     = $grade_item->is_hidden();
                if ($lessonduration = $DB->get_record('local_fd_mod_duration',array('courseid'=>$courseid,'gradeitemid'=>$grade_item->id))){
                    $item->sortorder    = $lessonduration->itemorder;
                    $item->duration     = $lessonduration->duration;
                } else{
                    $item->sortorder    = 0;
                    $item->duration     = 0;
                }
                $item->grades     = array();

                switch ($grade_item->gradetype){

                    case GRADE_TYPE_VALUE:
                        $item->scaleid = 0;
                        break;

                    case GRADE_TYPE_TEXT:
                        $item->scaleid   = 0;
                        $item->grademin   = 0;
                        $item->grademax   = 0;
                        $item->gradepass  = 0;
                        break;
                }

                $grade_grades = grade_grade::fetch_users_grades($grade_item, array($userid), true);
                $grade_grades[$userid]->grade_item =& $grade_item;
                
                $grade = new stdClass();
                $grade->grade          = $grade_grades[$userid]->finalgrade;
                $grade->locked         = $grade_grades[$userid]->is_locked();
                $grade->hidden         = $grade_grades[$userid]->is_hidden();
                $grade->excluded       = $grade_grades[$userid]->excluded;
                $grade->overridden     = $grade_grades[$userid]->overridden;
                $grade->feedback       = $grade_grades[$userid]->feedback;
                $grade->feedbackformat = $grade_grades[$userid]->feedbackformat;
                $grade->usermodified   = $grade_grades[$userid]->usermodified;
                $grade->datesubmitted  = $grade_grades[$userid]->get_datesubmitted();
                $grade->dategraded     = $grade_grades[$userid]->get_dategraded();
                $grade->masterylevel   = ((float)($grade_item->grademax) && $grade_item->gradetype) ? flexdates_get_mastery_level($courseid,$grade_grades[$userid]->finalgrade/$grade_item->grademax) : 'notstarted';
                if ($duedate = $DB->get_record('local_fd_student_due_dates',array('userid'=>$userid,'gradeitemid'=>$grade_item->id))){
                    $grade->duedate    = $duedate->duedate;
                } else{
                    $grade->duedate    = 0;
                }
                // create text representation of grade
                if ($grade_item->gradetype == GRADE_TYPE_TEXT){
#                    $grade->grade          = null;
#                    $grade->str_grade      = '-';
                    $grade->str_grade = grade_format_gradevalue($grade->grade, $grade_item, true,GRADE_DISPLAY_TYPE_LETTER);
                    $grade->str_long_grade = $grade->str_grade;

                } else if (in_array($grade_item->id, $needsupdate)){
                    $grade->grade          = false;
                    $grade->str_grade      = get_string('error');
                    $grade->str_long_grade = $grade->str_grade;

                } else if (is_null($grade->grade)){
                    $grade->str_grade      = '-';
                    $grade->str_long_grade = $grade->str_grade;

                } else if ($item->itemtype == 'course'){
                    $v = $grade_grades[$userid]->finalgrade/$grade_grades[$userid]->rawgrademax;
                    $grade->str_grade = grade_format_gradevalue($v*$grade_item->grademax, $grade_item, true,GRADE_DISPLAY_TYPE_LETTER);
                    $grade->str_long_grade = $grade->str_grade;
                } else{
                    $grade->str_grade = grade_format_gradevalue($grade->grade, $grade_item, true,GRADE_DISPLAY_TYPE_LETTER);
                    if ($grade_item->gradetype == GRADE_TYPE_SCALE or $grade_item->get_displaytype() != GRADE_DISPLAY_TYPE_REAL) {
                        $grade->str_long_grade = $grade->str_grade;
                    } else {
                        $a = new stdClass();
                        $a->grade = $grade->str_grade;
                        $a->max   = grade_format_gradevalue($grade_item->grademax, $grade_item);
                        $grade->str_long_grade = round($grade->grade).'/'. $grade_item->grademax;
                    }
                }

                // create html representation of feedback
                if (is_null($grade->feedback)) {
                    $grade->str_feedback = '';
                } else {
                    $grade->str_feedback = format_text($grade->feedback, $grade->feedbackformat);
                }

                $item->grades = $grade;
                //print_object($item);
                $return->items[$grade_item->id] = $item;

            } else {
                if (!$grade_outcome = grade_outcome::fetch(array('id'=>$grade_item->outcomeid))) {
                    debugging('Incorect outcomeid found');
                    continue;
                }

                // outcome info
                $outcome = new stdClass();
                $outcome->id = $grade_item->id;
                $outcome->itemnumber = $grade_item->itemnumber;
                $outcome->itemtype   = $grade_item->itemtype;
                $outcome->itemmodule = $grade_item->itemmodule;
                $outcome->iteminstance = $grade_item->iteminstance;
                $outcome->scaleid    = $grade_outcome->scaleid;
                $outcome->name       = $grade_outcome->get_name();
                $outcome->locked     = $grade_item->is_locked();
                $outcome->hidden     = $grade_item->is_hidden();


                $grade_grades = grade_grade::fetch_users_grades($grade_item, array($userid), true);
                $grade_grades[$userid]->grade_item =& $grade_item;

                $grade = new stdClass();
                $grade->grade          = $grade_grades[$userid]->finalgrade;
                $grade->locked         = $grade_grades[$userid]->is_locked();
                $grade->hidden         = $grade_grades[$userid]->is_hidden();
                $grade->feedback       = $grade_grades[$userid]->feedback;
                $grade->feedbackformat = $grade_grades[$userid]->feedbackformat;
                $grade->usermodified   = $grade_grades[$userid]->usermodified;

                // create text representation of grade
                if (in_array($grade_item->id, $needsupdate)) {
                    $grade->grade     = false;
                    $grade->str_grade = get_string('error');

                } else if (is_null($grade->grade)) {
                    $grade->grade = 0;
                    $grade->str_grade = get_string('nooutcome', 'grades');

                } else {
                    $grade->grade = (int)$grade->grade;
                    $scale = $grade_item->load_scale();
                    $grade->str_grade = format_string($scale->scale_items[(int)$grade->grade-1]);
                }

                // create html representation of feedback
                if (is_null($grade->feedback)) {
                    $grade->str_feedback = '';
                } else {
                    $grade->str_feedback = format_text($grade->feedback, $grade->feedbackformat);
                }

                $outcome->grades[$userid] = $grade;
                
                

                if (isset($return->outcomes[$grade_item->itemnumber])) {
                    // itemnumber duplicates - lets fix them!
                    $newnumber = $grade_item->itemnumber + 1;
                    while(grade_item::fetch(array('itemtype'=>$itemtype, 'itemmodule'=>$itemmodule, 'iteminstance'=>$iteminstance, 'courseid'=>$courseid, 'itemnumber'=>$newnumber))) {
                        $newnumber++;
                    }
                    $outcome->itemnumber    = $newnumber;
                    $grade_item->itemnumber = $newnumber;
                    $grade_item->update('system');
                }

                $return->outcomes[$grade_item->id] = $outcome;

            }
        }
    } else{
        echo 'no grade items<br/>';
    }

    // sort results using itemnumbers
    ksort($return->items, SORT_NUMERIC);
    ksort($return->outcomes, SORT_NUMERIC);

    return $return;
}

/**
 * Internal function that calculates the aggregated grade for this grade category
 *
 * @param array $items The array of grade_items
 * @return float The aggregate grade for this grade category
 */
function flexdates_aggregate_values($items,$aggregation) {
    //eliminate excluded, hidden, or locked items from the aggregation
    $grade_values = array();
    foreach($items as $item){
        if($item->grades->excluded or $item->grades->hidden or $item->grades->locked){
            unset($items[$item->id]);
        } else{
            $grade_values[$item->id] = $item->grades->grade;
        }
    }
    asort($grade_values);
    switch ($aggregation) {

        case GRADE_AGGREGATE_MEDIAN: // Middle point value in the set: ignores frequencies
            $num = count($items);
            $grades = array_values($grade_values);

            if ($num % 2 == 0) {
                $agg_grade = ($grades[intval($num/2)-1] + $grades[intval($num/2)]) / 2;

            } else {
                $agg_grade = $grades[intval(($num/2)-0.5)];
            }
            break;

        case GRADE_AGGREGATE_MIN:
            $agg_grade = reset($grade_values);
            break;

        case GRADE_AGGREGATE_MAX:
            $agg_grade = array_pop($grade_values);
            break;

        case GRADE_AGGREGATE_MODE:       // the most common value, average used if multimode
            // array_count_values only counts INT and STRING, so if grades are floats we must convert them to string
            $converted_grade_values = array();

            foreach ($grade_values as $k => $gv) {

                if (!is_int($gv) && !is_string($gv)) {
                    $converted_grade_values[$k] = (string) $gv;

                } else {
                    $converted_grade_values[$k] = $gv;
                }
            }

            $freq = array_count_values($converted_grade_values);
            arsort($freq);                      // sort by frequency keeping keys
            $top = reset($freq);               // highest frequency count
            $modes = array_keys($freq, $top);  // search for all modes (have the same highest count)
            rsort($modes, SORT_NUMERIC);       // get highest mode
            $agg_grade = reset($modes);
            break;

        case GRADE_AGGREGATE_WEIGHTED_MEAN: // Weighted average of all existing final grades, weight specified in coef
            $weightsum = 0;
            $sum       = 0;

            foreach ($grade_values as $itemid=>$grade_value) {

                if ($items[$itemid]->aggregationcoef <= 0) {
                    continue;
                }
                $weightsum += $items[$itemid]->aggregationcoef;
                $sum       += $items[$itemid]->aggregationcoef * $grade_value;
            }

            if ($weightsum == 0) {
                $agg_grade = null;

            } else {
                $agg_grade = $sum / $weightsum;
            }
            break;

        case GRADE_AGGREGATE_WEIGHTED_MEAN2:
            // Weighted average of all existing final grades with optional extra credit flag,
            // weight is the range of grade (usually grademax)
            $weightsum = 0;
            $sum       = null;

            foreach ($grade_values as $itemid=>$grade_value) {
                $weight = $items[$itemid]->grademax - $items[$itemid]->grademin;

                if ($weight <= 0) {
                    continue;
                }

                if ($items[$itemid]->aggregationcoef == 0) {
                    $weightsum += $weight;
                }
                $sum += $weight * $grade_value;
            }

            if ($weightsum == 0) {
                $agg_grade = $sum; // only extra credits

            } else {
                $agg_grade = $sum / $weightsum;
            }
            break;

        case GRADE_AGGREGATE_EXTRACREDIT_MEAN: // special average
            $num = 0;
            $sum = null;

            foreach ($grade_values as $itemid=>$grade_value) {

                if ($items[$itemid]->aggregationcoef == 0) {
                    $num += 1;
                    $sum += $grade_value;

                } else if ($items[$itemid]->aggregationcoef > 0) {
                    $sum += $items[$itemid]->aggregationcoef * $grade_value;
                }
            }

            if ($num == 0) {
                $agg_grade = $sum; // only extra credits or wrong coefs

            } else {
                $agg_grade = $sum / $num;
            }
            break;

        case GRADE_AGGREGATE_SUM:    // Add up all the items.
                $num = count($grade_values);
                // Excluded items can affect the grademax for this grade_item.
                $grademin = 0;
                $grademax = 0;
                $sum = 0;
                foreach ($grade_values as $itemid => $grade_value) {
                    $sum += $grade_value * ($items[$itemid]->grademax - $items[$itemid]->grademin);
                    $grademin += $items[$itemid]->grademin;
                    $grademax += $items[$itemid]->grademax;
                }

                $agg_grade = $sum / ($grademax - $grademin);
                break;

        case GRADE_AGGREGATE_MEAN:    // Arithmetic average of all grade items (if ungraded aggregated, NULL counted as minimum)
        default:
            $num = count($grade_values);
            $sum = array_sum($grade_values);
            $agg_grade = $sum / $num;
            break;
    }

    return $agg_grade;
}

/**
 * used to calculate the raw grade for a class, see flexdates_get_raw_grade
 * @param flexdates_categorize_items object $flat_tree object of mod items in categories
 * @param int $courseid id of the course
 * @param int $parent id of the parent category
 * @return float (will return other $flat_tree object in recursions)
 */
function flexdates_calculate_raw_grade($flat_tree,$courseid,$parent=null){
    global $DB;
    // if no parent, this is root category
    $root = false;
    if(!$parent){
        $root = true;
        $parent = $DB->get_record('grade_categories',array('courseid'=>$courseid,'depth'=>1))->id;
        //print_object($parent);
    }
    //print_object($parent);
    if($children = $DB->get_records('grade_categories',array('courseid'=>$courseid,'parent'=>$parent))){
        //print_object($children);
        foreach($children as $child){
            //print_object($child);
            //echo "parent = {$parent}<br/>";
            $branch = flexdates_calculate_raw_grade($flat_tree,$courseid,$child->id);
            //echo "back in parent {$parent}<br/>";
            $flat_tree->$parent->items[$branch->id] = $branch;
            $out = new stdClass;
            $out->parent = $flat_tree->$parent;
            //print_object($out);
        }
    }
    
    if(!empty($flat_tree->$parent->items)){
        $aggregation = $DB->get_record('grade_categories',array('id'=>$parent))->aggregation;
        $flat_tree->$parent->grades->grade = flexdates_aggregate_values($flat_tree->$parent->items,$aggregation);
        //print_object($flat_tree->$parent->grades->grade);
        if($root){
            return $flat_tree->$parent->grades->grade;
        }
        return $flat_tree->$parent;
    }
    //print_object($flat_tree->$parent->grades->grade);
    if($root){
        return $flat_tree->$parent->grades->grade;
    }
    return $flat_tree->$parent;
}
/**
 * create a flat tree by placing mod items in their grade categories
 * @param array $grade_items array of gradeitem objects
 * @param int $courseid the id of the course we want to categorize
 * @return object
 */
function flexdates_categorize_items($grade_items,$courseid){
    global $DB;
    $categories = $DB->get_records('grade_categories',array('courseid'=>$courseid),'path');
    
    $tree = new stdClass;
    foreach($grade_items as $grade_item){
        if($grade_item->itemtype=='course' or $grade_item->itemtype=='category'){
            $id = $grade_item->iteminstance;
            $tree->$id = $grade_item;
            $tree->$id->aggregation = $categories[$id]->aggregation;
            $tree->$id->items = array();
        }
    }
    
    foreach($grade_items as $grade_item){
        if($grade_item->itemtype=='course' or $grade_item->itemtype=='category'){
            continue;
        } else{
            $id = $grade_item->categoryid;
            $tree->$id->items[$grade_item->id] = $grade_item;
        }
    }
    
    return $tree;
}


/**
 * Returns grade with all ungraded assignments as 0. This is essentially the
 * same as setting aggregateonlygraded to false in the gradebook
 * @param int $courseid the id of the course we are interested in
 * @param array $grade_items an array of gradeitem objects
 * @return float
 */
function flexdates_get_raw_grade($courseid,$grade_items){
    // categorize all grade items
    $categorized_items = flexdates_categorize_items($grade_items,$courseid);
    // calculate raw grade
    $raw_grade = flexdates_calculate_raw_grade($categorized_items,$courseid,$parent=null);

    return $raw_grade;
}

/**
 * Find number of days between two dates, excluding weekends, and other blackout dates
 * adapted from stackoverflow http://stackoverflow.com/a/9321849/1800122
 * @param int $startdate the beginning date in unix timestamp
 * @param int $enddate the ending date in unix timestamp
 * @param array $excluded_dates array of dates not to include in count
 * @return array of available dates as unix timestamps at midnight of date
 */
function flexdates_get_available_due_dates($startdate, $enddate, $excluded_dates){
    $start = DateTime::createFromFormat('U', $startdate);
    $end = DateTime::createFromFormat('U', $enddate);
    $oneday = new DateInterval("P1D");

    /* Iterate from $start up to $end+1 day, one day in each iteration.
    We add one day to the $end date, because the DatePeriod only iterates up to,
    not including, the end date. */
    $dates = new DatePeriod($start, $oneday, $end->add($oneday));
    
    // Create a bucket of days with day[0] = today, day[1]=next school day, etc.
    foreach($dates as $day){
        // floor date to midnight to ensure it will match $excluded_dates timestamps
        $day_timestamp = $day->getTimestamp() - ($day->getTimestamp() % 86400); 
        if(!in_array($day_timestamp,$excluded_dates)){
            if($day->format("N") < 6) { /* weekday */
                $days[] = $day_timestamp;
            }
        }
    }

    return $days;
}

/**
 * Find total number of school days that student has been enroled in course
 *
 * @param int $startdate unix timestamp, date student enroled in course;
 * @param array $excluded_dates array of unix timestamps for non-school dates (vacations, holidays, teacher training, etc.)
 * @return int
 */
function flexdates_get_days_in_course($startdate,$excluded_dates = array()){
    $start = DateTime::createFromFormat('U', $startdate);
    $today = new DateTime();
    $oneday = new DateInterval("P1D");
    $dates = new DatePeriod($start, $oneday, $today);
    $counter = 0;
    foreach($dates as $day) {
        // floor date to midnight to ensure it will match $excluded_dates timestamps
        $day_timestamp = $day->getTimestamp() - ($day->getTimestamp() % 86400); 
        if(!in_array($day_timestamp,$excluded_dates)){
            if($day->format("N") < 6) { /* weekday */
                $counter ++;
            }
        }
    }
    return $counter;
}


/**
 * Find the length of a student's semester in school days
 * @param int $startdate unix timestamp, date student enroled in course;
 * @param int $enddate unix timestamp, date student should complete course
 * @param array $excluded_dates array of unix timestamps for non-school dates (vacations,
 *                              holidays, teacher training, etc.)
 * @return int
 */
function flexdates_get_days_in_semester($startdate,$enddate,$excluded_dates = array()){
    $start = DateTime::createFromFormat('U', $startdate);
    $end = DateTime::createFromFormat('U', $enddate);
    $oneday = new DateInterval("P1D");

    /* Iterate from $start up to $end+1 day, one day in each iteration.
    We add one day to the $end date, because the DatePeriod only iterates up to,
    not including, the end date. */
    $dates = new DatePeriod($start, $oneday, $end->add($oneday));

    $counter = 0;
    foreach($dates as $day) {
        // floor date to midnight to ensure it will match $excluded_dates timestamps
        $day_timestamp = $day->getTimestamp() - ($day->getTimestamp() % 86400); 
        if(!in_array($day_timestamp,$excluded_dates)){
            if($day->format("N") < 6) { /* weekday */
                $counter ++;
            }
        }
    }

    return $counter;
}



/**
 * find the project completion date based on work done and work to do
 * the idea is that all work should be completed in $sem_length, and each task
 * should take up a portion of that time. So we will sum up the normalized time
 * for each task, and project that total time as the amount of time left for 
 * expected course completion.
 *
 * NOTE: This can have an error as big as the longest lesson duration value,
 * i.e. if there are only three tasks, and each are given 2 months to complete
 * then for every x days into the 2 month period, this will project a completion
 * date of x days behind the actual date up to 2 months when the assignment is due,
 * at which point it will be correct once more. Thus, it should be encouraged to
 * make the tasks more atomic to better reflect the individual tasks expected in a larger assignment
 *
 * @param array $lessondurations array of lessonduration objects
 * @param int $sem_length number of school days between start to end dates
 * @param array $excluded_dates array of unix timestamps for dates that are not school days or weekends, i.e. spring break, christmas, etc.
 * @return DateTime object
 */
function flexdates_get_projected_completion_date($lessondurations,$sem_length,$excluded_dates=array()){
    // Sum all lesson duration values
    $total_duration = 0.0;
    foreach($lessondurations as $item){
        $total_duration += $item->duration;
    }
    
    // Normalize due dates for ungraded assignments and sum them up
    $time_total = 0;
    foreach($lessondurations as $item){
        if($item->notsubmitted){
            $item->duration = $item->duration*($sem_length/$total_duration); //normalize duration
            $time_total += $item->duration;
        }
    }
    
    // create bucket of available dates for next 36 weeks
    $today = time();
    $future = $today + 21772800*2; // Get dates for next 36 weeks
    $school_days = flexdates_get_available_due_dates($today, $future, $excluded_dates);
    
    //make 2 years the ceiling of projected completion date
    if(round($time_total)>730){
        $projected_date = DateTime::createFromFormat('U', $today + 63072000);
        return $projected_date;
    }
    
    while(true){
        // check to make sure we have gone far enough, else go another 36 weeks
        if($school_days[round($time_total)]){
            // look up projected_date date in school_days and make it a DateTime object
            $projected_date = DateTime::createFromFormat('U', $school_days[round($time_total)]);
            return $projected_date;
        }
        $school_days = flexdates_get_available_due_dates($today, $future+21772800*2, $excluded_dates);
   }
}

/**
 * Calculate due dates for each grade_item for a student in a given course
 * @param lessonduration object $lessondurations an object from the report_lessonduration table, ordered by itemorder
 * @param int $courseid the course id associated with the grade_items
 * @param int $userid the user id to calculate due dates for
 * @param array $excluded_dates array of dates that assignments cannot be counted due (holidays, testing days, etc.)
 * @return array
 */
function flexdates_calculate_student_due_dates($lessondurations,$courseid,$userid,$excluded_dates=array()){
    global $DB;
    //get student record with start and end date
    if(!$user_enrolment = $DB->get_record('local_fd_completion_dates',array('userid'=>$userid,'courseid'=>$courseid))){
        echo 'no record found for user '.$userid.' in course '.$courseid.' in the local_fd_completion_dates table<br/>';
        return null;
    }
    //calculate class days as number of days between start and end date, excluding weekends, holidays, etc.
    
    $class_days = flexdates_get_available_due_dates($user_enrolment->startdate, $user_enrolment->completiondate, $excluded_dates);
    //get sum of all lesson durations
    $total_time = 0;
    foreach($lessondurations as $key=>$value){
        $total_time += $value->duration;
    }
    //find a percent of total for each item in lesson duration table
    $iter_date = 0;
    $user = new stdClass;
    $user->startdate = $user_enrolment->startdate;
    $user->enddate = $user_enrolment->completiondate;
    $user->duedates = new stdClass;
    $counter = 0;
    // Due date for grade_item_x found as (count_class_days-1)*(sum_of_lessonduration_from_0_to_x)/(sum_all_lessondurations)
    foreach($lessondurations as $key=>$course_module){
        $gradeitemid = $course_module->gradeitemid;
        $iter_date += $course_module->duration;
        $due_date = $total_time ? round((count($class_days)-1)*($iter_date)/$total_time) : 1;
        $user->duedates->$gradeitemid = $class_days[(int)$due_date];
    }
    return $user;
}

function flexdates_update_student_due_dates($userid,$courseid,$lessondurations,$excluded_dates=array()){
    global $DB;
    //get student due dates
    $due_dates = flexdates_calculate_student_due_dates($lessondurations,$courseid,$userid,$excluded_dates);
    //update database
    $transaction = $DB->start_delegated_transaction();
    foreach($due_dates->duedates as $item=>$date){
        if(!$record = $DB->get_record('local_fd_student_due_dates',array('userid'=>$userid,'gradeitemid'=>$item))){
          $dataobject = new stdClass;
          $dataobject->userid = $userid;
          $dataobject->gradeitemid = $item;
          $dataobject->duedate = $date;
          $DB->insert_record('local_fd_student_due_dates',$dataobject);
        } else{
            $record->duedate = $date;
            $DB->update_record('local_fd_student_due_dates', $record);
        }
    }
    $transaction->allow_commit();
}

/**
 * determine how far behind or ahead student is and return correct styling properties
 * this is used with flexdates_make_svg to style the expected circle
 */
function flexdates_find_amount_behind($percomplete,$perexpected){
    if($percomplete == 0 and $perexpected == 0){
        // Boostrap success state
        return array(0,'#5CB85C','#4CAE4C');
    }
    $diff = ($percomplete - $perexpected);
    if(abs($diff) < 0.03){
        // Boostrap success state
        return array($diff,'#5CB85C','#4CAE4C');
    }
    if($diff < 0){
        if($diff < -0.1){
            // Bootstrap danger state
            return array($diff,'#D9534F','#D43F3A');
        } else{
            // Bootstrap warning state
            return array($diff,'#F0AD4E','#EEA236');
        }
    } else{
        if($diff > 0.1){
            // Boostrap primary state
            return array($diff,'#428BCA','#357EBD');
        } else{
            // Boostrap success state
            return array($diff,'#5CB85C','#4CAE4C');
        }
    }
}
/**
 * Convert polar to cartesian coordinates
 * @param int $x the initial x position in the cartesian plane
 * @param int $y the initial y position in the cartesian plane
 * @param int $radius the radius of the circle
 * @param int $angle the angle in radians
 * @return array
 */
function flexdates_polar_to_cartesian($x,$y,$radius,$angle){
    return array ($x - ($radius*sin($angle)),$y-($radius*cos($angle)));
}

/**
 * Create string for d attribute of svg path
 * @param int $x the initial x position in the cartesian plane
 * @param int $y the initial y position in the cartesian plane
 * @param int $radius the radius of the circle
 * @param int $start_angle the starting angle of the arc, can be in radians, degrees, or a percent of the circle expressed as a value between 0 and 1
 * @param int $end_angle the starting angle of the arc, can be in radians, degrees, or a percent of the circle expressed as a value between 0 and 1
 * @param bool $radians, boolean to express angles in radians or degrees
 * @param bool $percent, boolean to express angles as percent of circle
 * @return string
 */
function flexdates_parametize_arc($x,$y,$radius,$start_angle,$end_angle,$radians = true,$percent = true){
    $angles = $percent ? array(2*pi()*$start_angle,2*pi()*$end_angle) : ($radians ? array($start_angle,$end_angle) : array($start_angle*180/pi(),$end_angle*180/pi()));
    $start = flexdates_polar_to_cartesian($x,$y,$radius,$angles[0]);
    $end = flexdates_polar_to_cartesian($x,$y,$radius,$angles[1]);
    $arcsweep = abs($angles[1] - $angles[0]) <= pi() ? 0 : 1;
    $d = "M {$start[0]}, {$start[1]} A {$radius},{$radius} 0 {$arcsweep},0 {$end[0]},{$end[1]}";
    return $d;
}

function flexdates_make_anglelist($num_assign,$completed,$mastered,$expected,$course_grade){
    $per_complete = $num_assign ? $completed/$num_assign : 0;
    $per_master = $num_assign ? $mastered/$num_assign : 0;
    $per_expected = $num_assign ? $expected/$num_assign : 0;
    $grade_graph = new stdClass;
    $grade_graph->anglelist = array($per_master,$per_complete,$per_expected);
    $grade_graph->course_grade = $course_grade;
    return $grade_graph;
}
/**
 * Used to create an svg graphic of the grade with completion/mastery percentages encircling it
 * @param array $anglelist a two element array of decimals between 0 and 1, like ($mastery_percent,$completion_percent)
 * @param string $grade the course grade
 * @param int $cx the x-coordinate of the center of the circle in the svg element
 * @param int $cy the y-coordinate of the center of the circle in the svg element
 * @param int $radius the radius of the circle
 * @return string
 */
function flexdates_makesvg($anglelist, $grade, $cx = 100, $cy = 100, $radius=95){
    $graph = "<svg xmlns='http://www.w3.org/2000/svg' version='1.1' width='200' height='200'>";
    $mastered_angle = $anglelist[0] ==  1 ? 0.99999:$anglelist[0];
    $completed_angle = $anglelist[1] == 1 ? 0.99999:$anglelist[1];
    $expected_angle = $anglelist[2] == 1 ? 0.99999:$anglelist[2];
    $arc1 = flexdates_parametize_arc($cx,$cy,$radius,0,$mastered_angle);
    $arc2 = flexdates_parametize_arc($cx,$cy,$radius,$mastered_angle,$completed_angle);
    $end_angle = $anglelist[1] ? 1 :0.99999;
    $arc3 = flexdates_parametize_arc($cx,$cy,$radius,$completed_angle,$end_angle);
    $arc4 = flexdates_parametize_arc($cx,$cy,$radius-10,0,$expected_angle);
    $complete_percent = round($anglelist[1]*100,1);
    $mastered_percent = round($anglelist[0]*100,1);
    $expected_percent = round($anglelist[2]*100,1);
    $style = flexdates_find_amount_behind($anglelist[1],$anglelist[2]);
    $graph .= "<path id='mygrades-arc1' fill='none' stroke='#1C758A' stroke-width='10' d='{$arc1}'/>";
    $graph .= "<path id='mygrades-arc2' fill='none' stroke='#29ABCA' stroke-width='10' d='{$arc2}'/>";
    $graph .= "<path id='mygrades-arc3' fill='none' stroke='#DDD' stroke-width='10' d='{$arc3}'/>";
    $graph .= "<path id='mygrades-arc3' fill='none' stroke='{$style[2]}' stroke-width='10' d='{$arc4}'/>";
    $graph .= "<text id='mygrades-grade' x='100' y='85' style='fill:black;stroke:none;font-size:50pt;text-anchor:middle;alignment-baseline:bottom'>{$grade}</text>";
    $graph .= "<text id='mygrades-expected' x='100' y='105' style='fill:{$style[1]};stroke:none;font-size:12pt;text-anchor:middle;alignment-baseline:bottom'>{$expected_percent}% expected</text>";
    $graph .= "<text id='mygrades-completed' x='100' y='125' style='fill:#29ABCA;stroke:none;font-size:12pt;text-anchor:middle;alignment-baseline:bottom'>{$complete_percent}% completed</text>";
    $graph .= "<text id='mygrades-mastered' x='100' y='145' style='fill:#1C758A;stroke:none;font-size:12pt;text-anchor:middle;alignment-baseline:bottom'>{$mastered_percent}% mastered</text>";
    $graph .= "</svg>";
    return $graph;
}

/**
 * filter students
 *
 */
function flexdates_filter_students($teacher=null,$advisor=null,$site=null,$course=null){

}

/**
 * Used to transform a coursename to a html element id
 * @param string $string the string to be transformed
 * @return string
 */
function flexdates_string_to_url($string){
    return preg_replace('/[\s\W]+/', '_', $string);
}

/**
 * This function is called by 'usort' method to sort objects in array by property 'sortorder'
 *
 * @param grade_item $item1 object 1 to compare
 * @param grade_item $item2 object 2 to compare with object 1
 */
function flexdates_sort_array_by_sortorder($item1, $item2) {
    if ($item1->sortorder == $item2->sortorder) {
        return 0;
    }

    return ($item1->sortorder < $item2->sortorder) ? -1 : 1;
}

/**
 * This function is called by 'usort' method to sort objects in array by property 'sortorder'
 *
 * @param grade_item $item1 object 1 to compare
 * @param grade_item $item2 object 2 to compare with object 1
 */
function flexdates_sort_array_by_lastname($item1, $item2) {
    if ($item1->lastname == $item2->lastname) {
        return 0;
    }

    return ($item1->lastname < $item2->lastname) ? -1 : 1;
}

/**
 * This function is called by 'usort' method to sort objects in array by property 'sortorder'
 *
 * @param grade_item $item1 object 1 to compare
 * @param grade_item $item2 object 2 to compare with object 1
 */
function flexdates_sort_array_by_duedate($item1, $item2){
    if($item1->grades->duedate == $item2->grades->duedate){
        return 0;
    }

    return ($item1->grades->duedate < $item2->grades->duedate) ? -1 : 1;
}



/**
 * This function is called by 'usort' method to sort objects in array by property 'depth'
 *
 * @param grade_item $item1 object 1 to compare
 * @param grade_item $item2 object 2 to compare with object 1
 */
function flexdates_sort_categories_by_depth($item1, $item2) {
    if ($item1->depth == $item2->depth) {
        return 0;
    }

    return ($item1->depth < $item2->depth) ? 1 : -1;
}

class flexdates_course{
    /**
     * @var float the number of assignments completed in a course
     */
    public $completed = 0.0;
    /**
     * @var float the number of assignments assigned in a course
     */
    public $num_assign = 0.0;
    /**
     * @var float the number of assignments mastered in a course
     */
    public $mastered = 0.0;
    /**
     * @var float the number of assignments level2 in a course
     */
    public $level2 = 0.0;
    /**
     * @var float the number of assignments level1 in a course
     */
    public $level1 = 0.0;
    /**
     * @var float the number of assignments practiced in a course
     */
    public $practiced = 0.0;
    /**
     * @var float the number of assignments struggling in a course
     */
    public $struggling = 0.0;
    /**
     * @var float the number of assignments struggling in a course
     */
    public $notstarted = 0.0;
    /**
     * @var float the number of assignments that are expected
     * to be completed in a course by a given date
     */
    public $expected = 0.0;
    /**
     * @var DateTime object the projected date to complete the course;
     */
    public $projected_completion_date = null;
    /**
     * @var DateTime object the assigned date the user is expected to complete the course by
     */
    public $completion_date = null;
    /**
     * @var int number of seconds between projected and expected completion dates.
     */
    public $date_diff = null;
    /**
     * @var float a number between 0 and 100 representing the accumulated grade
     */
    public $raw_grade = 0.0;
    /**
     * @var str a URL, html class, and html id friendly version of the course name
     */
    public $link = '';
    /**
     * @var int the number os school days in the course
     */
    public $days_in_course = 0;
    /**
     * @var str the A-F representation of the course grade
     */
    public $course_grade = '';
    public $id = null;
    public $title = null;
    public $name = null;
    public $summary = null;
    public $grades = null;
    public $userid = null;
    
    public function __construct($data,$userid){
        $this->id  = $data->id;
        $this->title = $data->title;
        $this->name = $data->name;
        $this->summary = $data->summary;
        $this->startdate = $data->startdate;
        $this->grades = flexdates_get_student_grades($data->id,$userid);
        $this->userid = $userid;
        $this->set_properties($data);
    }
    
    public function set_properties($data){
        global $DB;
        $today = new DateTime();
        usort($data->grades->items,'flexdates_sort_array_by_sortorder');
        $lessondurations = new stdClass;
        foreach($data->grades->items as $assignment){
            $grades = $assignment->grades;
            if($assignment->itemtype == 'course'){
                $this->course_grade = $grades->str_grade;
                continue;
            } else if($assignment->itemtype == 'category'){
                continue;
            } else{
                $item_id = $assignment->id;
                $lessondurations->$item_id = new stdClass;
                $lessondurations->$item_id->duration = $assignment->duration;
                $this->num_assign ++;
                if($grades->masterylevel != 'notstarted'){
                    $this->completed ++;
                    if($grades->masterylevel == 'mastered'){
                        $this->mastered ++;
                    } elseif($grades->masterylevel == 'level2'){
                        $this->level2 ++;
                    } elseif($grades->masterylevel == 'level1'){
                        $this->level1 ++;
                    } elseif($grades->masterylevel == 'practiced'){
                        $this->practiced ++;
                    } elseif($grades->masterylevel == 'struggling'){
                        $this->struggling ++;
                    }
                    $lessondurations->$item_id->notsubmitted = false;
                } else if($grades->datesubmitted or $grades->dategraded){
                    $this->completed ++;
                    $lessondurations->$item_id->notsubmitted = false;
                } else{
                    $this->notstarted ++;
                    $lessondurations->$item_id->notsubmitted = true;
                }
                if($grades->duedate < $today->getTimestamp()){
                    $this->expected ++;
                }
            }
        }
        
        $this->link = flexdates_string_to_url($this->title);
        
        if($completion_record = $DB->get_record('local_fd_completion_dates',array('userid'=>$this->userid,'courseid'=>$this->id))){
            $sem_length = flexdates_get_days_in_semester($completion_record->startdate,$completion_record->completiondate,$excluded_dates = array());
            //print_object($sem_length);
            $this->projected_completion_date = flexdates_get_projected_completion_date($lessondurations,$sem_length,$excluded_dates=array());
            $this->completion_date = DateTime::createFromFormat('U', $completion_record->completiondate);
            $this->date_diff = $this->projected_completion_date->getTimestamp() - $this->completion_date->getTimestamp();
        } else{
            $this->completion_date = null;
            $this->projected_completion_date = null;
            $this->date_diff = 0;
        }
        $this->days_in_course = flexdates_get_days_in_course($data->startdate);
        $this->raw_grade = round(flexdates_get_raw_grade($data->id,$data->grades->items),1);
    }
}
