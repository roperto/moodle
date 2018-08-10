<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Library of internal classes and functions for module workshep
 *
 * All the workshep specific functions, needed to implement the module
 * logic, should go to here. Instead of having bunch of function named
 * workshep_something() taking the workshep instance as the first
 * parameter, we use a class workshep that provides all methods.
 *
 * @package    mod_workshep
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/lib.php');     // we extend this library here
require_once($CFG->libdir . '/gradelib.php');   // we use some rounding and comparing routines here
require_once($CFG->libdir . '/filelib.php');

/**
 * Full-featured workshep API
 *
 * This wraps the workshep database record with a set of methods that are called
 * from the module itself. The class should be initialized right after you get
 * $workshep, $cm and $course records at the begining of the script.
 */
class workshep {

    /** error status of the {@link self::add_allocation()} */
    const ALLOCATION_EXISTS             = -9999;

    /** the internal code of the workshep phases as are stored in the database */
    const PHASE_SETUP                   = 10;
    const PHASE_SUBMISSION              = 20;
    const PHASE_CALIBRATION             = 25;
    const PHASE_ASSESSMENT              = 30;
    const PHASE_EVALUATION              = 40;
    const PHASE_CLOSED                  = 50;

    /** the internal code of the examples modes as are stored in the database */
    const EXAMPLES_VOLUNTARY            = 0;
    const EXAMPLES_BEFORE_SUBMISSION    = 1;
    const EXAMPLES_BEFORE_ASSESSMENT    = 2;

    /** @var stdclass course module record */
    public $cm;

    /** @var stdclass course record */
    public $course;

    /** @var stdclass context object */
    public $context;

    /** @var int workshep instance identifier */
    public $id;

    /** @var string workshep activity name */
    public $name;

    /** @var string introduction or description of the activity */
    public $intro;

    /** @var int format of the {@link $intro} */
    public $introformat;

    /** @var string instructions for the submission phase */
    public $instructauthors;

    /** @var int format of the {@link $instructauthors} */
    public $instructauthorsformat;

    /** @var string instructions for the assessment phase */
    public $instructreviewers;

    /** @var int format of the {@link $instructreviewers} */
    public $instructreviewersformat;

    /** @var int timestamp of when the module was modified */
    public $timemodified;

    /** @var int current phase of workshep, for example {@link workshep::PHASE_SETUP} */
    public $phase;

    /** @var bool optional feature: students practise evaluating on example submissions from teacher */
    public $useexamples;

    /** @var bool optional feature: students perform peer assessment of others' work (deprecated, consider always enabled) */
    public $usepeerassessment;

    /** @var bool optional feature: students perform self assessment of their own work */
    public $useselfassessment;

    /** @var float number (10, 5) unsigned, the maximum grade for submission */
    public $grade;

    /** @var float number (10, 5) unsigned, the maximum grade for assessment */
    public $gradinggrade;

    /** @var string type of the current grading strategy used in this workshep, for example 'accumulative' */
    public $strategy;

    /** @var string the name of the evaluation plugin to use for grading grades calculation */
    public $evaluation;

    /** @var int number of digits that should be shown after the decimal point when displaying grades */
    public $gradedecimals;

    /** @var int number of allowed submission attachments and the files embedded into submission */
    public $nattachments;

    /** @var bool allow submitting the work after the deadline */
    public $latesubmissions;

    /** @var int maximum size of the one attached file in bytes */
    public $maxbytes;

    /** @var int mode of example submissions support, for example {@link workshep::EXAMPLES_VOLUNTARY} */
    public $examplesmode;

    /** @var int if greater than 0 then the submission is not allowed before this timestamp */
    public $submissionstart;

    /** @var int if greater than 0 then the submission is not allowed after this timestamp */
    public $submissionend;

    /** @var int if greater than 0 then the peer assessment is not allowed before this timestamp */
    public $assessmentstart;

    /** @var int if greater than 0 then the peer assessment is not allowed after this timestamp */
    public $assessmentend;

    /** @var bool automatically switch to the assessment phase after the submissions deadline */
    public $phaseswitchassessment;
    
    /** @var bool allows users to submit work as a group */
    public $teammode;

    /** @var string conclusion text to be displayed at the end of the activity */
    public $conclusion;

    /** @var int format of the conclusion text */
    public $conclusionformat;

    /** @var int the mode of the overall feedback */
    public $overallfeedbackmode;

    /** @var int maximum number of overall feedback attachments */
    public $overallfeedbackfiles;

    /** @var int maximum size of one file attached to the overall feedback */
    public $overallfeedbackmaxbytes;

    /** @var bool allows users to view and compare their assessment against the reference assessment for examples submissions */
    public $examplescompare;
    
    /** @var bool allows users to re-assess example submissions */
    public $examplesreassess;
    
    /** @var int number of example assessments to show to students */
    public $numexamples;
    
    /** @var bool using calibration */
    public $usecalibration;
    
    /** @var int 0 for no calibration; phase ID for "after x phase" */
    public $calibrationphase;
    
    /** @var string */
    public $calibrationmethod;
	
	/** @var bool allow submitters to flag assessments as unfair */
	public $submitterflagging;
    
    /**
     * @var workshep_strategy grading strategy instance
     * Do not use directly, get the instance using {@link workshep::grading_strategy_instance()}
     */
    protected $strategyinstance = null;

    /**
     * @var workshep_evaluation grading evaluation instance
     * Do not use directly, get the instance using {@link workshep::grading_evaluation_instance()}
     */
    protected $evaluationinstance = null;
    
    /**
     * @var workshep_calibration calibration instance
     * Do not use directly, get the instance using {@link workshep::calibration_instance()}
     */
    protected $calibrationinstance = null;

    /**
     * Initializes the workshep API instance using the data from DB
     *
     * Makes deep copy of all passed records properties. Replaces integer $course attribute
     * with a full database record (course should not be stored in instances table anyway).
     *
     * @param stdClass $dbrecord Workshop instance data from {workshep} table
     * @param stdClass $cm       Course module record as returned by {@link get_coursemodule_from_id()}
     * @param stdClass $course   Course record from {course} table
     * @param stdClass $context  The context of the workshep instance
     */
    public function __construct(stdclass $dbrecord, stdclass $cm, stdclass $course, stdclass $context=null) {
        foreach ($dbrecord as $field => $value) {
            if (property_exists('workshep', $field)) {
                $this->{$field} = $value;
            }
        }
        $this->cm           = $cm;
        $this->course       = $course;
        if (is_null($context)) {
            $this->context = context_module::instance($this->cm->id);
        } else {
            $this->context = $context;
        }
    }

    ////////////////////////////////////////////////////////////////////////////////
    // Static methods                                                             //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * Return list of available allocation methods
     *
     * @return array Array ['string' => 'string'] of localized allocation method names
     */
    public static function installed_allocators() {
        $installed = core_component::get_plugin_list('workshepallocation');
        $forms = array();
        foreach ($installed as $allocation => $allocationpath) {
            if (file_exists($allocationpath . '/lib.php')) {
                $forms[$allocation] = get_string('pluginname', 'workshepallocation_' . $allocation);
            }
        }
        // usability - make sure that manual allocation appears the first
        if (isset($forms['manual'])) {
            $m = array('manual' => $forms['manual']);
            unset($forms['manual']);
            $forms = array_merge($m, $forms);
        }
        return $forms;
    }
    
    /**
     * Returns the list of available grading evaluation methods
     *
     * @return array ['string' => 'string']
     */
    public function available_evaluation_methods_list() {
       $installed = get_plugin_list('workshepeval');
       $forms = array();
       foreach ($installed as $method => $methodpath) {
           if (file_exists($methodpath . '/lib.php')) {
    		   //put exceptions here
    		   if ($method == "calibrated") {
    			   if (($this->useexamples == false) || ($this->examplesmode == workshep::EXAMPLES_VOLUNTARY) || (count($this->get_examples_for_manager()) == 0))
    				   continue; 
    		   }
               $forms[$method] = get_string('pluginname', 'workshepeval_' . $method);
           }
       }
       return $forms;
    }

    /**
     * Returns an array of options for the editors that are used for submitting and assessing instructions
     *
     * @param stdClass $context
     * @uses EDITOR_UNLIMITED_FILES hard-coded value for the 'maxfiles' option
     * @return array
     */
    public static function instruction_editors_options(stdclass $context) {
        return array('subdirs' => 1, 'maxbytes' => 0, 'maxfiles' => -1,
                     'changeformat' => 1, 'context' => $context, 'noclean' => 1, 'trusttext' => 0);
    }

    /**
     * Given the percent and the total, returns the number
     *
     * @param float $percent from 0 to 100
     * @param float $total   the 100% value
     * @return float
     */
    public static function percent_to_value($percent, $total) {
        if ($percent < 0 or $percent > 100) {
            throw new coding_exception('The percent can not be less than 0 or higher than 100');
        }

        return $total * $percent / 100;
    }

    /**
     * Returns an array of numeric values that can be used as maximum grades
     *
     * @return array Array of integers
     */
    public static function available_maxgrades_list() {
        $grades = array();
        for ($i=100; $i>=0; $i--) {
            $grades[$i] = $i;
        }
        return $grades;
    }

    /**
     * Returns the localized list of supported examples modes
     *
     * @return array
     */
    public static function available_example_modes_list() {
        $options = array();
        $options[self::EXAMPLES_VOLUNTARY]         = get_string('examplesvoluntary', 'workshep');
        $options[self::EXAMPLES_BEFORE_SUBMISSION] = get_string('examplesbeforesubmission', 'workshep');
        $options[self::EXAMPLES_BEFORE_ASSESSMENT] = get_string('examplesbeforeassessment', 'workshep');
        return $options;
    }

    /**
     * Returns the list of available grading strategy methods
     *
     * @return array ['string' => 'string']
     */
    public static function available_strategies_list() {
        $installed = core_component::get_plugin_list('workshepform');
        $forms = array();
        foreach ($installed as $strategy => $strategypath) {
            if (file_exists($strategypath . '/lib.php')) {
                $forms[$strategy] = get_string('pluginname', 'workshepform_' . $strategy);
            }
        }
        return $forms;
    }

    /**
     * Returns a limited list of available grading evaluation methods
     *
     * @return array of (string)name => (string)localized title
     */
    public function limited_available_evaluators_list() {
        $evals = array();
        foreach (core_component::get_plugin_list_with_file('workshepeval', 'lib.php', false) as $eval => $evalpath) {
            $evals[$eval] = get_string('pluginname', 'workshepeval_' . $eval);
            if ($eval == "calibrated") {
                if ($this->usecalibration == false) {
                    unset($evals[$eval]);
                }
            }
        }
        return $evals;
    }

    /**
     * Returns the list of available grading evaluation methods
     *
     * @return array of (string)name => (string)localized title
     */
    public static function available_evaluators_list() {
        $evals = array();
        foreach (core_component::get_plugin_list_with_file('workshepeval', 'lib.php', false) as $eval => $evalpath) {
            $evals[$eval] = get_string('pluginname', 'workshepeval_' . $eval);
        }
        return $evals;
    }

    /**
     * Return an array of possible values of assessment dimension weight
     *
     * @return array of integers 0, 1, 2, ..., 16
     */
    public static function available_dimension_weights_list() {
        $weights = array();
        for ($i=16; $i>=0; $i--) {
            $weights[$i] = $i;
        }
        return $weights;
    }

    /**
     * Return an array of possible values of assessment weight
     *
     * Note there is no real reason why the maximum value here is 16. It used to be 10 in
     * workshep 1.x and I just decided to use the same number as in the maximum weight of
     * a single assessment dimension.
     * The value looks reasonable, though. Teachers who would want to assign themselves
     * higher weight probably do not want peer assessment really...
     *
     * @return array of integers 0, 1, 2, ..., 16
     */
    public static function available_assessment_weights_list() {
        $weights = array();
        for ($i=16; $i>=0; $i--) {
            $weights[$i] = $i;
        }
        return $weights;
    }

    /**
     * Helper function returning the greatest common divisor
     *
     * @param int $a
     * @param int $b
     * @return int
     */
    public static function gcd($a, $b) {
        return ($b == 0) ? ($a):(self::gcd($b, $a % $b));
    }

    /**
     * Helper function returning the least common multiple
     *
     * @param int $a
     * @param int $b
     * @return int
     */
    public static function lcm($a, $b) {
        return ($a / self::gcd($a,$b)) * $b;
    }
    
    public function user_group($userid) {
        global $DB;
        
        //todo: cache this result
        $rslt = groups_get_all_groups($this->cm->course, $userid, $this->cm->groupingid);
        if ( count($rslt) == 1 ) {
            $ret = current($rslt);
            return $ret;
        } else if ( count($rslt) > 1 ) {
            $user = $DB->get_record('user', array('id' => $userid));
            $fullname = fullname($user);
            print_error('teammode_multiplegroupswarning','workshep',new moodle_url('/group/groupings.php',array('id' => $this->course->id)),$fullname);
        }
        return null;
    }
    
    public function users_in_more_than_one_group() {
        global $DB;
        
        $groupingid = $this->cm->groupingid;
        if ($groupingid) {
            $groupingsql = 'AND g.id in (select groupid from {groupings_groups} where groupingid = ?)';
            $params = array($this->course->id, $this->cm->groupingid);
        } else {
            $groupingsql = '';
            $params = array($this->course->id);
        }
        
        $sql = <<<SQL
SELECT u.id, u.firstname, u.lastname, u.username from
{user} u, {groups} g, {groups_members} gm
WHERE g.courseid = ? $groupingsql
AND gm.groupid = g.id
AND u.id = gm.userid
GROUP BY u.id, u.firstname, u.lastname, u.username
HAVING count(u.id) > 1
ORDER BY u.lastname
SQL;

        return $DB->get_records_sql($sql,$params);

    }

    /**
     * Returns an object suitable for strings containing dates/times
     *
     * The returned object contains properties date, datefullshort, datetime, ... containing the given
     * timestamp formatted using strftimedate, strftimedatefullshort, strftimedatetime, ... from the
     * current lang's langconfig.php
     * This allows translators and administrators customize the date/time format.
     *
     * @param int $timestamp the timestamp in UTC
     * @return stdclass
     */
    public static function timestamp_formats($timestamp) {
        $formats = array('date', 'datefullshort', 'dateshort', 'datetime',
                'datetimeshort', 'daydate', 'daydatetime', 'dayshort', 'daytime',
                'monthyear', 'recent', 'recentfull', 'time');
        $a = new stdclass();
        foreach ($formats as $format) {
            $a->{$format} = userdate($timestamp, get_string('strftime'.$format, 'langconfig'));
        }
        $day = userdate($timestamp, '%Y%m%d', 99, false);
        $today = userdate(time(), '%Y%m%d', 99, false);
        $tomorrow = userdate(time() + DAYSECS, '%Y%m%d', 99, false);
        $yesterday = userdate(time() - DAYSECS, '%Y%m%d', 99, false);
        $distance = (int)round(abs(time() - $timestamp) / DAYSECS);
        if ($day == $today) {
            $a->distanceday = get_string('daystoday', 'workshep');
        } elseif ($day == $yesterday) {
            $a->distanceday = get_string('daysyesterday', 'workshep');
        } elseif ($day < $today) {
            $a->distanceday = get_string('daysago', 'workshep', $distance);
        } elseif ($day == $tomorrow) {
            $a->distanceday = get_string('daystomorrow', 'workshep');
        } elseif ($day > $today) {
            $a->distanceday = get_string('daysleft', 'workshep', $distance);
        }
        return $a;
    }

    ////////////////////////////////////////////////////////////////////////////////
    // Workshop API                                                               //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * Fetches all enrolled users with the capability mod/workshep:submit in the current workshep
     *
     * The returned objects contain properties required by user_picture and are ordered by lastname, firstname.
     * Only users with the active enrolment are returned.
     *
     * @param bool $musthavesubmission if true, return only users who have already submitted
     * @param int $groupid 0 means ignore groups, any other value limits the result by group id
     * @param int $limitfrom return a subset of records, starting at this point (optional, required if $limitnum is set)
     * @param int $limitnum return a subset containing this number of records (optional, required if $limitfrom is set)
     * @return array array[userid] => stdClass
     */
    public function get_potential_authors($musthavesubmission=true, $groupid=0, $limitfrom=0, $limitnum=0) {
        global $DB;

        list($sql, $params) = $this->get_users_with_capability_sql('mod/workshep:submit', $musthavesubmission, $groupid);

        if (empty($sql)) {
            return array();
        }

        list($sort, $sortparams) = users_order_by_sql('tmp');
        $sql = "SELECT *
                  FROM ($sql) tmp
              ORDER BY $sort";

        $users = $DB->get_records_sql($sql, array_merge($params, $sortparams), $limitfrom, $limitnum);

		if($this->teammode) {
        	return array_slice($this->get_grouped($users),1,null,true);
        }

		return $users;
    }

    /**
     * Returns the total number of users that would be fetched by {@link self::get_potential_authors()}
     *
     * @param bool $musthavesubmission if true, count only users who have already submitted
     * @param int $groupid 0 means ignore groups, any other value limits the result by group id
     * @return int
     */
    public function count_potential_authors($musthavesubmission=true, $groupid=0) {
        global $DB;
        
        if ($this->teammode) {
            //there's no shortcut: you just have to get it
            return count($this->get_potential_authors($musthavesubmission, $groupid));
        }

        list($sql, $params) = $this->get_users_with_capability_sql('mod/workshep:submit', $musthavesubmission, $groupid);

        if (empty($sql)) {
            return 0;
        }

        $sql = "SELECT COUNT(*)
                  FROM ($sql) tmp";

        return $DB->count_records_sql($sql, $params);
    }

    /**
     * Fetches all enrolled users with the capability mod/workshep:peerassess in the current workshep
     *
     * The returned objects contain properties required by user_picture and are ordered by lastname, firstname.
     * Only users with the active enrolment are returned.
     *
     * @param bool $musthavesubmission if true, return only users who have already submitted
     * @param int $groupid 0 means ignore groups, any other value limits the result by group id
     * @param int $limitfrom return a subset of records, starting at this point (optional, required if $limitnum is set)
     * @param int $limitnum return a subset containing this number of records (optional, required if $limitfrom is set)
     * @return array array[userid] => stdClass
     */
    public function get_potential_reviewers($musthavesubmission=false, $groupid=0, $limitfrom=0, $limitnum=0) {
        global $DB;

        list($sql, $params) = $this->get_users_with_capability_sql('mod/workshep:peerassess', $musthavesubmission, $groupid);

        if (empty($sql)) {
            return array();
        }

        list($sort, $sortparams) = users_order_by_sql('tmp');
        $sql = "SELECT *
                  FROM ($sql) tmp
              ORDER BY $sort";
        
        $rslt = $DB->get_records_sql($sql, array_merge($params, $sortparams), $limitfrom, $limitnum);
        
        if($this->teammode and $musthavesubmission) {
            //we need to add everyone's teammates
            //this is three more database hits but no joins so nice & fast
            $userids = array();
            foreach ($rslt as $k => $v) { 
                $userids[$k] = $k;
            }
            $groups = groups_get_all_groups($this->cm->course, $userids, $this->cm->groupingid, 'g.id');
            
            $members = $DB->get_records_list('groups_members','groupid',array_keys($groups),'','userid');
            $users = $DB->get_records_list('user','id',array_keys($members),'',user_picture::fields());
            
            foreach($users as $k => $v) {
                if (isset($rslt[$k]))
                    continue;
                $rslt[$k] = $v;
            }
        }

        return $rslt;
    }

    /**
     * Returns the total number of users that would be fetched by {@link self::get_potential_reviewers()}
     *
     * @param bool $musthavesubmission if true, count only users who have already submitted
     * @param int $groupid 0 means ignore groups, any other value limits the result by group id
     * @return int
     */
    public function count_potential_reviewers($musthavesubmission=false, $groupid=0) {
        global $DB;

        list($sql, $params) = $this->get_users_with_capability_sql('mod/workshep:peerassess', $musthavesubmission, $groupid);

        if (empty($sql)) {
            return 0;
        }

        $sql = "SELECT COUNT(*)
                  FROM ($sql) tmp";

        return $DB->count_records_sql($sql, $params);
    }
    
    //Always returns a flat list of users, even when in teammode
    public function get_all_participants() {
        // this is a little hacky :P
        $_teammode = $this->teammode;
        $this->teammode = false;
        $retval = $this->get_potential_authors() + $this->get_potential_reviewers();
        $this->teammode = $_teammode;
        return $retval;
    }
    
    public function get_ungrouped_users() {
    
         $users = $this->get_all_participants();
         $users = $this->get_grouped($users);
         if (isset($users[0])) {
             $nogroupusers = $users[0];
             foreach ($users as $groupid => $groupusers) {
                 if ($groupid == 0) {
                     continue;
                 }
                 foreach ($groupusers as $groupuserid => $groupuser) {
                     unset($nogroupusers[$groupuserid]);
                 }
             }
    		return $nogroupusers;
         }
    
    }

    /**
     * Fetches all enrolled users that are authors or reviewers (or both) in the current workshep
     *
     * The returned objects contain properties required by user_picture and are ordered by lastname, firstname.
     * Only users with the active enrolment are returned.
     *
     * @see self::get_potential_authors()
     * @see self::get_potential_reviewers()
     * @param bool $musthavesubmission if true, return only users who have already submitted
     * @param int $groupid 0 means ignore groups, any other value limits the result by group id
     * @param int $limitfrom return a subset of records, starting at this point (optional, required if $limitnum is set)
     * @param int $limitnum return a subset containing this number of records (optional, required if $limitfrom is set)
     * @return array array[userid] => stdClass
     */
    public function get_participants($musthavesubmission=false, $groupid=0, $limitfrom=0, $limitnum=0) {
        global $DB;

        list($sql, $params) = $this->get_participants_sql($musthavesubmission, $groupid);

        if (empty($sql)) {
            return array();
        }

        list($sort, $sortparams) = users_order_by_sql('tmp');
        $sql = "SELECT *
                  FROM ($sql) tmp
              ORDER BY $sort";

        return $DB->get_records_sql($sql, array_merge($params, $sortparams), $limitfrom, $limitnum);
    }

    /**
     * Returns the total number of records that would be returned by {@link self::get_participants()}
     *
     * @param bool $musthavesubmission if true, return only users who have already submitted
     * @param int $groupid 0 means ignore groups, any other value limits the result by group id
     * @return int
     */
    public function count_participants($musthavesubmission=false, $groupid=0) {
        global $DB;

        list($sql, $params) = $this->get_participants_sql($musthavesubmission, $groupid);

        if (empty($sql)) {
            return 0;
        }

        $sql = "SELECT COUNT(*)
                  FROM ($sql) tmp";

        return $DB->count_records_sql($sql, $params);
    }

    /**
     * Checks if the given user is an actively enrolled participant in the workshep
     *
     * @param int $userid, defaults to the current $USER
     * @return boolean
     */
    public function is_participant($userid=null) {
        global $USER, $DB;

        if (is_null($userid)) {
            $userid = $USER->id;
        }

        list($sql, $params) = $this->get_participants_sql();

        if (empty($sql)) {
            return false;
        }

        $sql = "SELECT COUNT(*)
                  FROM {user} uxx
                  JOIN ({$sql}) pxx ON uxx.id = pxx.id
                 WHERE uxx.id = :uxxid";
        $params['uxxid'] = $userid;

        if ($DB->count_records_sql($sql, $params)) {
            return true;
        }

        return false;
    }

    /**
     * Groups the given users by the group membership
     *
     * This takes the module grouping settings into account. If "Available for group members only"
     * is set, returns only groups withing the course module grouping. Always returns group [0] with
     * all the given users.
     *
     * @param array $users array[userid] => stdclass{->id ->lastname ->firstname}
     * @return array array[groupid][userid] => stdclass{->id ->lastname ->firstname}
     */
    public function get_grouped($users) {
        global $DB;
        global $CFG;

        $grouped = array();  // grouped users to be returned
        if (empty($users)) {
            return $grouped;
        }
        if ((!empty($CFG->enablegroupmembersonly) and $this->cm->groupmembersonly) or ($this->teammode and $this->cm->groupingid)) {
            // Available for group members only - the workshep is available only
            // to users assigned to groups within the selected grouping, or to
            // any group if no grouping is selected.
            $groupingid = $this->cm->groupingid;
            // All users that are members of at least one group will be
            // added into a virtual group id 0
            $grouped[0] = array();
        } else {
            $groupingid = 0;
            // there is no need to be member of a group so $grouped[0] will contain
            // all users
            $grouped[0] = $users;
        }

        $gmemberships = groups_get_all_groups($this->cm->course, array_keys($users), $groupingid,
                            'gm.id,gm.groupid,gm.userid');
        foreach ($gmemberships as $gmembership) {
            if (!isset($grouped[$gmembership->groupid])) {
                $grouped[$gmembership->groupid] = array();
            }
            $grouped[$gmembership->groupid][$gmembership->userid] = $users[$gmembership->userid];
            $grouped[0][$gmembership->userid] = $users[$gmembership->userid];
        }
        return $grouped;
    }

    /**
     * Returns the list of all allocations (i.e. assigned assessments) in the workshep
     *
     * Assessments of example submissions are ignored
     *
     * @return array
     */
    public function get_allocations() {
        global $DB;

        $sql = 'SELECT a.id, a.submissionid, a.reviewerid, s.authorid
                  FROM {workshep_assessments} a
            INNER JOIN {workshep_submissions} s ON (a.submissionid = s.id)
                 WHERE s.example = 0 AND s.workshepid = :workshepid';
        $params = array('workshepid' => $this->id);

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Returns the total number of records that would be returned by {@link self::get_submissions()}
     *
     * @param mixed $authorid int|array|'all' If set to [array of] integer, return submission[s] of the given user[s] only
     * @param int $groupid If non-zero, return only submissions by authors in the specified group
     * @return int number of records
     */
    public function count_submissions($authorid='all', $groupid=0) {
        global $DB;

        $params = array('workshepid' => $this->id);
        $sql = "SELECT COUNT(s.id)
                  FROM {workshep_submissions} s
                  JOIN {user} u ON (s.authorid = u.id)";
        if ($groupid) {
            $sql .= " JOIN {groups_members} gm ON (gm.userid = u.id AND gm.groupid = :groupid)";
            $params['groupid'] = $groupid;
        }
        $sql .= " WHERE s.example = 0 AND s.workshepid = :workshepid";

        if ('all' === $authorid) {
            // no additional conditions
        } elseif (!empty($authorid)) {
            list($usql, $uparams) = $DB->get_in_or_equal($authorid, SQL_PARAMS_NAMED);
            $sql .= " AND authorid $usql";
            $params = array_merge($params, $uparams);
        } else {
            // $authorid is empty
            return 0;
        }

        return $DB->count_records_sql($sql, $params);
    }


    /**
     * Returns submissions from this workshep
     *
     * Fetches data from {workshep_submissions} and adds some useful information from other
     * tables. Does not return textual fields to prevent possible memory lack issues.
     *
     * @see self::count_submissions()
     * @param mixed $authorid int|array|'all' If set to [array of] integer, return submission[s] of the given user[s] only
     * @param int $groupid If non-zero, return only submissions by authors in the specified group
     * @param int $limitfrom Return a subset of records, starting at this point (optional)
     * @param int $limitnum Return a subset containing this many records in total (optional, required if $limitfrom is set)
     * @return array of records or an empty array
     */
    public function get_submissions($authorid='all', $groupid=0, $limitfrom=0, $limitnum=0) {
        global $DB;

        $authorfields      = user_picture::fields('u', null, 'authoridx', 'author');
        $gradeoverbyfields = user_picture::fields('t', null, 'gradeoverbyx', 'over');
        $params            = array('workshepid' => $this->id);
        $sql = "SELECT s.id, s.workshepid, s.example, s.authorid, s.timecreated, s.timemodified,
                       s.title, s.grade, s.gradeover, s.gradeoverby, s.published,
                       $authorfields, $gradeoverbyfields
                  FROM {workshep_submissions} s
                  JOIN {user} u ON (s.authorid = u.id)";
        if ($groupid) {
            $sql .= " JOIN {groups_members} gm ON (gm.userid = u.id AND gm.groupid = :groupid)";
            $params['groupid'] = $groupid;
        }
        $sql .= " LEFT JOIN {user} t ON (s.gradeoverby = t.id)
                 WHERE s.example = 0 AND s.workshepid = :workshepid";

        if ('all' === $authorid) {
            // no additional conditions
        } elseif (!empty($authorid)) {
            list($usql, $uparams) = $DB->get_in_or_equal($authorid, SQL_PARAMS_NAMED);
            $sql .= " AND authorid $usql";
            $params = array_merge($params, $uparams);
        } else {
            // $authorid is empty
            return array();
        }
        list($sort, $sortparams) = users_order_by_sql('u');
        $sql .= " ORDER BY $sort";

        return $DB->get_records_sql($sql, array_merge($params, $sortparams), $limitfrom, $limitnum);
    }
    
    
    //TODO: documentation
    //TODO: pagination
    public function get_submissions_grouped($authorid='all',$groupid=0) {
       $rslt = $this->get_submissions($authorid);
       
       //todo: pay attention to the $groupid parameter
       
       $groups = array();
       foreach($rslt as $a) {
    	    $grslt = $this->user_group($a->authorid);
    	    $g = $grslt->id;
    	    if (isset($groups[$g])) {
    		    if($groups[$g]->timemodified < $a->timemodified) {
    			    $groups[$g] = $a;
    			    $groups[$g]->group = $grslt;
    		    }
    	    } else {
    		    $groups[$g] = $a;
    		    $groups[$g]->group = $grslt;
    	    }
       }
       
    	$submissions = array();
    	foreach($groups as $k => $v) {
    		$submissions[$v->id] = $v;
    	}
    	
       return $submissions;
    }

    /**
     * Returns a submission record with the author's data
     *
     * @param int $id submission id
     * @return stdclass
     */
    public function get_submission_by_id($id) {
        global $DB;

        // we intentionally check the workshepid here, too, so the workshep can't touch submissions
        // from other instances
        $authorfields      = user_picture::fields('u', null, 'authoridx', 'author');
        $gradeoverbyfields = user_picture::fields('g', null, 'gradeoverbyx', 'gradeoverby');
        $sql = "SELECT s.*, $authorfields, $gradeoverbyfields
                  FROM {workshep_submissions} s
            INNER JOIN {user} u ON (s.authorid = u.id)
             LEFT JOIN {user} g ON (s.gradeoverby = g.id)
                 WHERE s.example = 0 AND s.workshepid = :workshepid AND s.id = :id";
        $params = array('workshepid' => $this->id, 'id' => $id);
        return $DB->get_record_sql($sql, $params, MUST_EXIST);
    }

    /**
     * Returns a submission submitted by the given author
     *
     * @param int $id author id
     * @return stdclass|false
     */
    public function get_submission_by_author($authorid, $fields='s.*') {
        global $DB;

        if (empty($authorid)) {
            return false;
        }
        
        // TEAMMODE :: Morgan Harris
        if ($this->teammode) {
           $group = $this->user_group($authorid);
           if (empty($group)) {
               return false;
           }
           $authorids = array_keys( groups_get_members($group->id, "u.id") );
           $authorids[] = $authorid;
           $authorids_str = implode($authorids, ", ");
           $authorclause = "s.authorid IN ($authorids_str)";
        } else {
           $authorclause = "s.authorid = :authorid";
        }
        
        
        $authorfields      = user_picture::fields('u', null, 'authoridx', 'author');
        $gradeoverbyfields = user_picture::fields('g', null, 'gradeoverbyx', 'gradeoverby');
        $sql = "SELECT $fields, $authorfields, $gradeoverbyfields
                  FROM {workshep_submissions} s
            INNER JOIN {user} u ON (s.authorid = u.id)
             LEFT JOIN {user} g ON (s.gradeoverby = g.id)
                 WHERE s.example = 0 AND s.workshepid = :workshepid AND $authorclause
              ORDER BY s.timemodified DESC
                 LIMIT 1";
        $params = array('workshepid' => $this->id, 'authorid' => $authorid);
        return $DB->get_record_sql($sql, $params);
    }

    /**
     * Returns published submissions with their authors data
     *
     * @return array of stdclass
     */
    public function get_published_submissions($orderby='finalgrade DESC') {
        global $DB;

        $authorfields = user_picture::fields('u', null, 'authoridx', 'author');
        $sql = "SELECT s.id, s.authorid, s.timecreated, s.timemodified,
                       s.title, s.grade, s.gradeover, COALESCE(s.gradeover,s.grade) AS finalgrade,
                       $authorfields
                  FROM {workshep_submissions} s
            INNER JOIN {user} u ON (s.authorid = u.id)
                 WHERE s.example = 0 AND s.workshepid = :workshepid AND s.published = 1
              ORDER BY $orderby";
        $params = array('workshepid' => $this->id);
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Returns full record of the given example submission
     *
     * @param int $id example submission od
     * @return object
     */
    public function get_example_by_id($id) {
        global $DB;
        return $DB->get_record('workshep_submissions',
                array('id' => $id, 'workshepid' => $this->id, 'example' => 1), '*', MUST_EXIST);
    }

    /**
     * Returns the list of example submissions in this workshep with reference assessments attached
     *
     * @param string $orderby the ordering of examples
     * @return array of objects or an empty array
     * @see workshep::prepare_example_summary()
     */
    public function get_examples_for_manager($orderby='s.title') {
        global $DB;

        $sql = "SELECT s.id, s.title, s.authorid,
                       a.id AS assessmentid, a.grade, a.gradinggrade
                  FROM {workshep_submissions} s
             LEFT JOIN {workshep_assessments} a ON (a.submissionid = s.id AND a.weight = 1)
                 WHERE s.example = 1 AND s.workshepid = :workshepid
              ORDER BY $orderby";
        return $DB->get_records_sql($sql, array('workshepid' => $this->id));
    }

    /**
     * Returns the list of all example submissions in this workshep with the information of assessments done by the given user
     *
     * @param int $reviewerid user id
     * @return array of objects, indexed by example submission id
     * @see workshep::prepare_example_summary()
     */
    public function get_examples_for_reviewer($reviewerid) {
        global $DB;

        if (empty($reviewerid)) {
            return false;
        }

        $where = ''; $params = array();
        
        if ($this->numexamples > 0) {
            $exampleids = $this->get_n_examples_for_reviewer($this->numexamples,$reviewerid);
            list($where, $params) = $DB->get_in_or_equal($exampleids,SQL_PARAMS_NAMED,'ex');
            $where = " AND s.id $where";
        }
        
        $sql = "SELECT s.id, s.title,
                       a.id AS assessmentid, a.grade, a.gradinggrade
                  FROM {workshep_submissions} s
             LEFT JOIN {workshep_assessments} a ON (a.submissionid = s.id AND a.reviewerid = :reviewerid AND a.weight = 0)
                 WHERE s.example = 1 AND s.workshepid = :workshepid $where
              ORDER BY s.title";
        $params['workshepid'] = $this->id;
        $params['reviewerid'] = $reviewerid;

        $retval = $DB->get_records_sql($sql, $params);

        return $retval;
    }
    
    protected function get_n_examples_for_reviewer($n,$reviewer) {
        /*
        Here's how this algorithm works:
        
        1. Order all the example assessments by grade
        2. Split them evenly into $n arrays
        3. Pick an example submission randomly from each slice
            3.1 If you pick one of the top submissions from a slice, make sure not to pick one of the bottom submissions from the next slice
        4. Store those submissions associated with each user in workshep_user_assessments
        
        */
        
        global $DB;
        
        //we sort by id ASC because we want a consistent ordering, which is the order the examples were added
        $rslt = $DB->get_records('workshep_user_examples',array('userid' => $reviewer, 'workshepid' => $this->id),'id ASC');
        
        if (count($rslt) == $n) {
            //the ideal result: just got the examples we wanted
            $examples = array();
            foreach($rslt as $k => $v) $examples[] = $v->submissionid;
            return $examples;
        }
        
        //otherwise, we need to either create, expand or shrink the user's examples
        //first we need to get a list of all of our example assessments
        
        //this sort order is important because we need an absolutely, rigidly identical result every single time we do this fetch
        $all_examples = $this->get_examples_for_manager('a.grade, s.title, s.id');
        
        //First we handle a user error: if they've asked for more examples than they've created
        if ($n > count($all_examples)) {
            return array_keys($all_examples);
        }
        
        //I call these slices, although 'brackets' may have been a better term
        //They're $n roughly even groups of example submissions, having similar assessment grades
        //In other words, if $n is 3, $slices will contain three arrays, of the poor, average
        //and good submissions, in that order.
        
        //Factored this out because we need it again elsewhere.
        $slices = $this->slice_example_submissions($all_examples,$n);
        
        //Examples is just a flat array of submission IDs. This is the kind of thing we'll
        //be returning.
        $examples = array();
        foreach($rslt as $k => $v) $examples[] = $v->submissionid;
        
        if (count($rslt) < $n) {
            
            //We don't have enough examples. Better add some more.
            //This includes the first set of examples, ie when count($rslt) == 0
            
            $x = $n - count($rslt);
            
            //we need to add $x examples.
            
            $slices_to_skip = array();
            foreach($slices as $i => $s) {
                $intersection = array_intersect(array_keys($s),$examples);
                if (count($intersection) > 0) {
                    $slices_to_skip[$i] = count($intersection);
                }
            }
            
            //EDGE CASE:
            //We need to check if there's multiple submissions in one skipped slice,
            //in which case we need to skip some more on either side
            //This can happen if two close submissions were picked and they're both in this slice.
            //For the most part you can ignore this, it's handling a fairly rare edge case
            foreach($slices_to_skip as $i => $s) {
                if ($s > 1) {

                    //There's more than one submission in this slice, so let's skip some more
                    $number_to_skip = $s - 1;
                    for($j = 1; $j < count($slices); $j++) {
                        $next_slice = $i + $j;
                        $previous_slice = $i - $j;
                        
                        if (($next_slice < count($slices)) && (!in_array($next_slice,$slices_to_skip))) {
                            $slices_to_skip[] = $next_slice;
                            $number_to_skip--;
                            if ($number_to_skip == 0) {
                                break;
                            }
                        }
                            
                        if (($previous_slice > 0) && (!in_array($previous_slice,$slices_to_skip))) {
                            $slices_to_skip[] = $previous_slice;
                            $number_to_skip--;
                            if ($number_to_skip == 0) {
                                break;
                            }
                        }
                            
                    }
                        
                    if ($number_to_skip > 0) {
                        //this SHOULD be impossible
                        print_error('Impossible mathematics: skipped more slices than exist.');
                    }
                }
            }
            
            //Here we do a bit of biasing
            //Basically, we don't want students assessing two assessments with the same score
            //So we make sure that if you randomly got the top assessment in one slice,
            //you don't get it in the next one, AND we make sure the assessment you next
            //get doesn't have the same score as the one you just got.
            
            $newexamples = array();
            $picked_top_submission = false;
            $last_submission_score = null;
            foreach($slices as $i => $s) {
                if (!array_key_exists($i,$slices_to_skip)) { //if we don't have to skip this slice
                    
                    //BIASING
                    //first we have to trim the slice 
                    $keys_to_remove = array();
                    $s_keys = array_keys($s);
                    
                    //remove the bottom submission if necessary
                    if ($picked_top_submission) {
                        $k = $s_keys[0];
                        $keys_to_remove[$k] = $s[$k];
                    }
                    
                    //remove submissions with the same score
                    foreach($s as $k => $a) {
                        if ($a->grade === $last_submission_score) {
                            $keys_to_remove[$k] = $a;
                        }
                    }
                    
                    if (count($keys_to_remove) < count($s)) {
                        $s = array_diff_key($s,$keys_to_remove);
                    }
                    
                    //THE THING THIS LOOP DOES (populate $newexamples)
                    $pick = array_rand($s);
                    $newexamples[] = $pick;
                    
                    //STATE
                    //set our state for the next iteration
                    $s_keys = array_keys($s); // $s has changed, (and $pick is based the new $s) so we need to check it again
                    if ($pick == $s_keys[count($s)-1]) {
                        $picked_top_submission = true;
                    } else {
                        $picked_top_submission = false;
                    }
                    
                    $last_submission_score = $s[$pick]->grade;
                }
            }
            
            // this is important, otherwise the examples will always be in worst-to-best order
            shuffle($newexamples);
            
            foreach($newexamples as $e) {
                $record = new stdClass;
                $record->userid = $reviewer;
                $record->submissionid = $e;
                $record->workshepid = $this->id;
                $DB->insert_record('workshep_user_examples',$record);
            }
            
            return array_merge($examples, $newexamples);
            
        } elseif (count($rslt) > $n) {
            
            //We don't actually remove any records here. What we do is pick the *first*
            //example already assigned from each slice. Why do we do this? Well, if the
            //teacher reduces the number of examples then increases it again, we don't want
            //to delete any student's hard work assessing the example submissions.
                        
            $returned_examples = array();
            
            foreach($slices as $i => $s) {
                $intersection = array_intersect($examples,array_keys($s));
                //pick the first key
                $returned_examples[] = current($intersection);
            }
            
            return $returned_examples;
            
        }
        
    }
    
    /**
     * Yes it's weird to make this public but we actually need it in another class
     * (workshep_random_examples_helper), so we need it to be public and static.
     */
    public static function slice_example_submissions($examples,$n) {
        $slices = array();
        
        //This might seem an odd way to do this loop, but think about it this way
        //If we have ten examples and need four slices, we want to slice it like 3,2,3,2
        //not 3,3,3,1
        
        $f = count($examples) / $n; //examples per slice. not an integer!
        for($i = 0; $i < $n; $i++) {
            $lo = round($i * $f);
            $hi = round(($i + 1) * $f);
            
            $slices[] = array_slice($examples,$lo,$hi - $lo,true);
        }
        
        return $slices;
    }

    /**
     * Prepares renderable submission component
     *
     * @param stdClass $record required by {@see workshep_submission}
     * @param bool $showauthor show the author-related information
     * @return workshep_submission
     */
    public function prepare_submission(stdClass $record, $showauthor = false) {

        $submission         = new workshep_submission($this, $record, $showauthor);
        $submission->url    = $this->submission_url($record->id);
        if($this->teammode) {
            $submission->group = $this->user_group($record->authorid);
        }

        return $submission;
    }

    /**
     * Prepares renderable submission summary component
     *
     * @param stdClass $record required by {@see workshep_submission_summary}
     * @param bool $showauthor show the author-related information
     * @return workshep_submission_summary
     */
    public function prepare_submission_summary(stdClass $record, $showauthor = false) {

		//todo: give workshep_group_submission_summary a $this param
        if($this->teammode) {
        	$summary		= new workshep_group_submission_summary($this, $record, $showauthor);
        	$summary->group	= $this->user_group($record->authorid);
        	$summary->url   = $this->submission_url($record->id);
        } else {
            $summary        = new workshep_submission_summary($this, $record, $showauthor);
            $summary->url   = $this->submission_url($record->id);
        }   

        return $summary;
    }

    /**
     * Prepares renderable example submission component
     *
     * @param stdClass $record required by {@see workshep_example_submission}
     * @return workshep_example_submission
     */
    public function prepare_example_submission(stdClass $record) {

        $example = new workshep_example_submission($this, $record);

        return $example;
    }

    /**
     * Prepares renderable example submission summary component
     *
     * If the example is editable, the caller must set the 'editable' flag explicitly.
     *
     * @param stdClass $example as returned by {@link workshep::get_examples_for_manager()} or {@link workshep::get_examples_for_reviewer()}
     * @return workshep_example_submission_summary to be rendered
     */
    public function prepare_example_summary(stdClass $example) {

        $summary = new workshep_example_submission_summary($this, $example);

        if (is_null($example->grade)) {
            $summary->status = 'notgraded';
            $summary->assesslabel = get_string('assess', 'workshep');
        } else {
            $summary->status = 'graded';
            if ($this->examplesreassess) {
                $summary->assesslabel = get_string('reassess', 'workshep');
            } else {
                $summary->assesslabel = get_string('review', 'workshep');
            }
        }

        $summary->gradeinfo           = new stdclass();
        $summary->gradeinfo->received = $this->real_grade($example->grade);
        $summary->gradeinfo->max      = $this->real_grade(100);

        $summary->url       = new moodle_url($this->exsubmission_url($example->id));
        $summary->editurl   = new moodle_url($this->exsubmission_url($example->id), array('edit' => 'on'));
        $summary->assessurl = new moodle_url($this->exsubmission_url($example->id), array('assess' => 'on', 'sesskey' => sesskey()));

        return $summary;
    }

    /**
     * Prepares renderable assessment component
     *
     * The $options array supports the following keys:
     * showauthor - should the author user info be available for the renderer
     * showreviewer - should the reviewer user info be available for the renderer
     * showform - show the assessment form if it is available
     * showweight - should the assessment weight be available for the renderer
     *
     * @param stdClass $record as returned by eg {@link self::get_assessment_by_id()}
     * @param workshep_assessment_form|null $form as returned by {@link workshep_strategy::get_assessment_form()}
     * @param array $options
     * @return workshep_assessment
     */
    public function prepare_assessment(stdClass $record, $form, array $options = array()) {
		return $this->prepare_assessment_with_submission($record, null, $form, $options);
	}
	
	public function prepare_assessment_with_submission(stdClass $record, $submission, $form, array $options = array()) {

        $assessment             = new workshep_assessment($this, $record, $options);
        $assessment->url        = $this->assess_url($record->id);
        $assessment->maxgrade   = $this->real_grade(100);
		
		if (!is_null($submission)) {
			$assessment->submission = $submission;
		}

        if (!empty($options['showform']) and !($form instanceof workshep_assessment_form)) {
            debugging('Not a valid instance of workshep_assessment_form supplied', DEBUG_DEVELOPER);
        }

        if (!empty($options['showform']) and ($form instanceof workshep_assessment_form)) {
            $assessment->form = $form;
        }

        if (empty($options['showweight'])) {
            $assessment->weight = null;
        }

        if (!is_null($record->grade)) {
            $assessment->realgrade = $this->real_grade($record->grade);
        }
		
		if (!empty($options['showflaggingresolution'])) {
			$assessment->resolution = true;
		}

        return $assessment;
    }

    /**
     * Prepares renderable example submission's assessment component
     *
     * The $options array supports the following keys:
     * showauthor - should the author user info be available for the renderer
     * showreviewer - should the reviewer user info be available for the renderer
     * showform - show the assessment form if it is available
     *
     * @param stdClass $record as returned by eg {@link self::get_assessment_by_id()}
     * @param workshep_assessment_form|null $form as returned by {@link workshep_strategy::get_assessment_form()}
     * @param array $options
     * @return workshep_example_assessment
     */
    public function prepare_example_assessment(stdClass $record, $form = null, array $options = array()) {

        $assessment             = new workshep_example_assessment($this, $record, $options);
        $assessment->url        = $this->exassess_url($record->id);
        $assessment->maxgrade   = $this->real_grade(100);

        if (!empty($options['showform']) and !($form instanceof workshep_assessment_form)) {
            debugging('Not a valid instance of workshep_assessment_form supplied', DEBUG_DEVELOPER);
        }

        if (!empty($options['showform']) and ($form instanceof workshep_assessment_form)) {
            $assessment->form = $form;
        }

        if (!is_null($record->grade)) {
            $assessment->realgrade = $this->real_grade($record->grade);
        }

        $assessment->weight = null;

        return $assessment;
    }

    /**
     * Prepares renderable example submission's reference assessment component
     *
     * The $options array supports the following keys:
     * showauthor - should the author user info be available for the renderer
     * showreviewer - should the reviewer user info be available for the renderer
     * showform - show the assessment form if it is available
     *
     * @param stdClass $record as returned by eg {@link self::get_assessment_by_id()}
     * @param workshep_assessment_form|null $form as returned by {@link workshep_strategy::get_assessment_form()}
     * @param array $options
     * @return workshep_example_reference_assessment
     */
    public function prepare_example_reference_assessment(stdClass $record, $form = null, array $options = array()) {

        $assessment             = new workshep_example_reference_assessment($this, $record, $options);
        $assessment->maxgrade   = $this->real_grade(100);

        if (!empty($options['showform']) and !($form instanceof workshep_assessment_form)) {
            debugging('Not a valid instance of workshep_assessment_form supplied', DEBUG_DEVELOPER);
        }

        if (!empty($options['showform']) and ($form instanceof workshep_assessment_form)) {
            $assessment->form = $form;
        }

        if (!is_null($record->grade)) {
            $assessment->realgrade = $this->real_grade($record->grade);
        }

        $assessment->weight = null;

        return $assessment;
    }

    /**
     * Removes the submission and all relevant data
     *
     * @param stdClass $submission record to delete
     * @return void
     */
    public function delete_submission(stdclass $submission) {
        global $DB;

        $assessments = $DB->get_records('workshep_assessments', array('submissionid' => $submission->id), '', 'id');
        $this->delete_assessment(array_keys($assessments));

        $fs = get_file_storage();
        $fs->delete_area_files($this->context->id, 'mod_workshep', 'submission_content', $submission->id);
        $fs->delete_area_files($this->context->id, 'mod_workshep', 'submission_attachment', $submission->id);

        $DB->delete_records('workshep_submissions', array('id' => $submission->id));
    }

    /**
     * Returns the list of all assessments in the workshep with some data added
     *
     * Fetches data from {workshep_assessments} and adds some useful information from other
     * tables. The returned object does not contain textual fields (i.e. comments) to prevent memory
     * lack issues.
     *
     * @return array [assessmentid] => assessment stdclass
     */
    public function get_all_assessments() {
        global $DB;

        $reviewerfields = user_picture::fields('reviewer', null, 'revieweridx', 'reviewer');
        $authorfields   = user_picture::fields('author', null, 'authorid', 'author');
        $overbyfields   = user_picture::fields('overby', null, 'gradinggradeoverbyx', 'overby');
        list($sort, $params) = users_order_by_sql('reviewer');
        $sql = "SELECT a.id, a.submissionid, a.reviewerid, a.timecreated, a.timemodified,
                       a.grade, a.gradinggrade, a.gradinggradeover, a.gradinggradeoverby,
                       $reviewerfields, $authorfields, $overbyfields,
                       s.title
                  FROM {workshep_assessments} a
            INNER JOIN {user} reviewer ON (a.reviewerid = reviewer.id)
            INNER JOIN {workshep_submissions} s ON (a.submissionid = s.id)
            INNER JOIN {user} author ON (s.authorid = author.id)
             LEFT JOIN {user} overby ON (a.gradinggradeoverby = overby.id)
                 WHERE s.workshepid = :workshepid AND s.example = 0
              ORDER BY $sort";
        $params['workshepid'] = $this->id;

        return $DB->get_records_sql($sql, $params);
    }
	
    public function get_flagged_assessments() {
        global $DB;

        $reviewerfields = user_picture::fields('reviewer', null, 'revieweridx', 'reviewer');
        $authorfields   = user_picture::fields('author', null, 'authorid', 'author');
        $overbyfields   = user_picture::fields('overby', null, 'gradinggradeoverbyx', 'overby');
        list($sort, $params) = users_order_by_sql('reviewer');
        $sql = "SELECT a.*,
                       $reviewerfields, $authorfields, $overbyfields,
                       s.title, s.content as submissioncontent, 
					   s.contentformat as submissionformat, s.attachment as submissionattachment
                  FROM {workshep_assessments} a
            INNER JOIN {user} reviewer ON (a.reviewerid = reviewer.id)
            INNER JOIN {workshep_submissions} s ON (a.submissionid = s.id)
            INNER JOIN {user} author ON (s.authorid = author.id)
             LEFT JOIN {user} overby ON (a.gradinggradeoverby = overby.id)
                 WHERE s.workshepid = :workshepid AND s.example = 0 AND a.submitterflagged = 1
              ORDER BY $sort";
        $params['workshepid'] = $this->id;

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get the complete information about the given assessment
     *
     * @param int $id Assessment ID
     * @return stdclass
     */
    public function get_assessment_by_id($id) {
        global $DB;

        $reviewerfields = user_picture::fields('reviewer', null, 'revieweridx', 'reviewer');
        $authorfields   = user_picture::fields('author', null, 'authorid', 'author');
        $overbyfields   = user_picture::fields('overby', null, 'gradinggradeoverbyx', 'overby');
        $sql = "SELECT a.*, s.title, $reviewerfields, $authorfields, $overbyfields
                  FROM {workshep_assessments} a
            INNER JOIN {user} reviewer ON (a.reviewerid = reviewer.id)
            INNER JOIN {workshep_submissions} s ON (a.submissionid = s.id)
            INNER JOIN {user} author ON (s.authorid = author.id)
             LEFT JOIN {user} overby ON (a.gradinggradeoverby = overby.id)
                 WHERE a.id = :id AND s.workshepid = :workshepid";
        $params = array('id' => $id, 'workshepid' => $this->id);

        return $DB->get_record_sql($sql, $params, MUST_EXIST);
    }

    /**
     * Get the complete information about the user's assessment of the given submission
     *
     * @param int $sid submission ID
     * @param int $uid user ID of the reviewer
     * @return false|stdclass false if not found, stdclass otherwise
     */
    public function get_assessment_of_submission_by_user($submissionid, $reviewerid) {
        global $DB;

        $reviewerfields = user_picture::fields('reviewer', null, 'revieweridx', 'reviewer');
        $authorfields   = user_picture::fields('author', null, 'authorid', 'author');
        $overbyfields   = user_picture::fields('overby', null, 'gradinggradeoverbyx', 'overby');
        $sql = "SELECT a.*, s.title, $reviewerfields, $authorfields, $overbyfields
                  FROM {workshep_assessments} a
            INNER JOIN {user} reviewer ON (a.reviewerid = reviewer.id)
            INNER JOIN {workshep_submissions} s ON (a.submissionid = s.id AND s.example = 0)
            INNER JOIN {user} author ON (s.authorid = author.id)
             LEFT JOIN {user} overby ON (a.gradinggradeoverby = overby.id)
                 WHERE s.id = :sid AND reviewer.id = :rid AND s.workshepid = :workshepid";
        $params = array('sid' => $submissionid, 'rid' => $reviewerid, 'workshepid' => $this->id);

        return $DB->get_record_sql($sql, $params, IGNORE_MISSING);
    }

    /**
     * Get the complete information about all assessments of the given submission
     *
     * @param int $submissionid
     * @return array
     */
    public function get_assessments_of_submission($submissionid) {
        global $DB;

        $reviewerfields = user_picture::fields('reviewer', null, 'revieweridx', 'reviewer');
        $overbyfields   = user_picture::fields('overby', null, 'gradinggradeoverbyx', 'overby');
        list($sort, $params) = users_order_by_sql('reviewer');
        $sql = "SELECT a.*, s.title, $reviewerfields, $overbyfields
                  FROM {workshep_assessments} a
            INNER JOIN {user} reviewer ON (a.reviewerid = reviewer.id)
            INNER JOIN {workshep_submissions} s ON (a.submissionid = s.id)
             LEFT JOIN {user} overby ON (a.gradinggradeoverby = overby.id)
                 WHERE s.example = 0 AND s.id = :submissionid AND s.workshepid = :workshepid
              ORDER BY $sort";
        $params['submissionid'] = $submissionid;
        $params['workshepid']   = $this->id;

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get the complete information about all assessments allocated to the given reviewer
     *
     * @param int $reviewerid
     * @return array
     */
    public function get_assessments_by_reviewer($reviewerid) {
        global $DB;

        $reviewerfields = user_picture::fields('reviewer', null, 'revieweridx', 'reviewer');
        $authorfields   = user_picture::fields('author', null, 'authorid', 'author');
        $overbyfields   = user_picture::fields('overby', null, 'gradinggradeoverbyx', 'overby');
        $sql = "SELECT a.*, $reviewerfields, $authorfields, $overbyfields,
                       s.id AS submissionid, s.title AS submissiontitle, s.timecreated AS submissioncreated,
                       s.timemodified AS submissionmodified
                  FROM {workshep_assessments} a
            INNER JOIN {user} reviewer ON (a.reviewerid = reviewer.id)
            INNER JOIN {workshep_submissions} s ON (a.submissionid = s.id)
            INNER JOIN {user} author ON (s.authorid = author.id)
             LEFT JOIN {user} overby ON (a.gradinggradeoverby = overby.id)
                 WHERE s.example = 0 AND reviewer.id = :reviewerid AND s.workshepid = :workshepid";
        $params = array('reviewerid' => $reviewerid, 'workshepid' => $this->id);

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get allocated assessments not graded yet by the given reviewer
     *
     * @see self::get_assessments_by_reviewer()
     * @param int $reviewerid the reviewer id
     * @param null|int|array $exclude optional assessment id (or list of them) to be excluded
     * @return array
     */
    public function get_pending_assessments_by_reviewer($reviewerid, $exclude = null) {

        $assessments = $this->get_assessments_by_reviewer($reviewerid);

        foreach ($assessments as $id => $assessment) {
            if (!is_null($assessment->grade)) {
                unset($assessments[$id]);
                continue;
            }
            if (!empty($exclude)) {
                if (is_array($exclude) and in_array($id, $exclude)) {
                    unset($assessments[$id]);
                    continue;
                } else if ($id == $exclude) {
                    unset($assessments[$id]);
                    continue;
                }
            }
        }

        return $assessments;
    }

    /**
     * Allocate a submission to a user for review
     *
     * @param stdClass $submission Submission object with at least id property
     * @param int $reviewerid User ID
     * @param int $weight of the new assessment, from 0 to 16
     * @param bool $bulk repeated inserts into DB expected
     * @return int ID of the new assessment or an error code {@link self::ALLOCATION_EXISTS} if the allocation already exists
     */
    public function add_allocation(stdclass $submission, $reviewerid, $weight=1, $bulk=false) {
        global $DB;

        $weight = (int)$weight;
        if ($weight < 0) {
            $weight = 0;
        }
        if ($weight > 16) {
            $weight = 16;
        }

        $now = time();
        $assessment = new stdclass();
        $assessment->submissionid           = $submission->id;
        $assessment->reviewerid             = $reviewerid;
        $assessment->timecreated            = $now;         // do not set timemodified here
        $assessment->weight                 = $weight;
        $assessment->feedbackauthorformat   = editors_get_preferred_format();
        $assessment->feedbackreviewerformat = editors_get_preferred_format();

        // Attempt to insert the new record
        try {
            return $DB->insert_record('workshep_assessments', $assessment, true, $bulk);
        } catch (dml_exception $ex) {
            // Insert can fail if it violates unique key constrain, check if this is the case
            if ($DB->record_exists('workshep_assessments', array('submissionid' => $submission->id, 'reviewerid' => $reviewerid))) {
                // Yes.
                return self::ALLOCATION_EXISTS;
            } else {
                // No.
                throw $ex;
            }
        }

    }

    /**
     * Delete assessment record or records.
     *
     * Removes associated records from the workshep_grades table, too.
     *
     * @param int|array $id assessment id or array of assessments ids
     * @todo Give grading strategy plugins a chance to clean up their data, too.
     * @return bool true
     */
    public function delete_assessment($id) {
        global $DB;

        if (empty($id)) {
            return true;
        }

        $fs = get_file_storage();

        if (is_array($id)) {
            $DB->delete_records_list('workshep_grades', 'assessmentid', $id);
            foreach ($id as $itemid) {
                $fs->delete_area_files($this->context->id, 'mod_workshep', 'overallfeedback_content', $itemid);
                $fs->delete_area_files($this->context->id, 'mod_workshep', 'overallfeedback_attachment', $itemid);
            }
            $DB->delete_records_list('workshep_assessments', 'id', $id);

        } else {
            $DB->delete_records('workshep_grades', array('assessmentid' => $id));
            $fs->delete_area_files($this->context->id, 'mod_workshep', 'overallfeedback_content', $id);
            $fs->delete_area_files($this->context->id, 'mod_workshep', 'overallfeedback_attachment', $id);
            $DB->delete_records('workshep_assessments', array('id' => $id));
        }

        return true;
    }

    /**
     * Returns instance of grading strategy class
     *
     * @return stdclass Instance of a grading strategy
     */
    public function grading_strategy_instance() {
        global $CFG;    // because we require other libs here

        if (is_null($this->strategyinstance)) {
            $strategylib = dirname(__FILE__) . '/form/' . $this->strategy . '/lib.php';
            if (is_readable($strategylib)) {
                require_once($strategylib);
            } else {
                throw new coding_exception('the grading forms subplugin must contain library ' . $strategylib);
            }
            $classname = 'workshep_' . $this->strategy . '_strategy';
            $this->strategyinstance = new $classname($this);
            if (!in_array('workshep_strategy', class_implements($this->strategyinstance))) {
                throw new coding_exception($classname . ' does not implement workshep_strategy interface');
            }
        }
        return $this->strategyinstance;
    }

    /**
     * Sets the current evaluation method to the given plugin.
     *
     * @param string $method the name of the workshepeval subplugin
     * @return bool true if successfully set
     * @throws coding_exception if attempting to set a non-installed evaluation method
     */
    public function set_grading_evaluation_method($method) {
        global $DB;

        $evaluationlib = dirname(__FILE__) . '/eval/' . $method . '/lib.php';

        if (is_readable($evaluationlib)) {
            $this->evaluationinstance = null;
            $this->evaluation = $method;
            $DB->set_field('workshep', 'evaluation', $method, array('id' => $this->id));
            return true;
        }

        throw new coding_exception('Attempt to set a non-existing evaluation method.');
    }

    /**
     * Returns instance of grading evaluation class
     *
     * @return stdclass Instance of a grading evaluation
     */
    public function grading_evaluation_instance() {
        global $CFG;    // because we require other libs here
		//todo: verify this works with multiple eval methods
        if (is_null($this->evaluationinstance)) {
            if (empty($this->evaluation)) {
                $this->evaluation = 'best';
            }
            $evaluationlib = dirname(__FILE__) . '/eval/' . $this->evaluation . '/lib.php';
            if (is_readable($evaluationlib)) {
                require_once($evaluationlib);
            } else {
                // Fall back in case the subplugin is not available.
                $this->evaluation = 'best';
                $evaluationlib = dirname(__FILE__) . '/eval/' . $this->evaluation . '/lib.php';
                if (is_readable($evaluationlib)) {
                    require_once($evaluationlib);
                } else {
                    // Fall back in case the subplugin is not available any more.
                    throw new coding_exception('Missing default grading evaluation library ' . $evaluationlib);
                }
            }
            $classname = 'workshep_' . $this->evaluation . '_evaluation';
            $this->evaluationinstance = new $classname($this);
            if (!in_array('workshep_evaluation', class_parents($this->evaluationinstance))) {
                throw new coding_exception($classname . ' does not extend workshep_evaluation class');
            }
        }
        return $this->evaluationinstance;
    }

    /**
     * Returns instance of submissions allocator
     *
     * @param string $method The name of the allocation method, must be PARAM_ALPHA
     * @return stdclass Instance of submissions allocator
     */
    public function allocator_instance($method) {
        global $CFG;    // because we require other libs here

        $allocationlib = dirname(__FILE__) . '/allocation/' . $method . '/lib.php';
        if (is_readable($allocationlib)) {
            require_once($allocationlib);
        } else {
            throw new coding_exception('Unable to find the allocation library ' . $allocationlib);
        }
        $classname = 'workshep_' . $method . '_allocator';
        if ($this->teammode) {
            $teammode_class = $classname::teammode_class(); //teammode class
            if (!is_null($teammode_class)) { //if not null or void
                return new $teammode_class($this);
            }
        }
        return new $classname($this);
    }
    
    /**
     * Returns instance of calibration plugin
     *
     * @param string $method The name of the calibration method
     * @return workshep_calibration Calibration instance
     */
    public function calibration_instance() {
        global $CFG;    // because we require other libs here
        if (is_null($this->calibrationinstance)) {
            if (empty($this->calibrationmethod)) {
                $this->calibrationmethod = 'examples';
            }
            $calibrationlib = dirname(__FILE__) . '/calibration/' . $this->calibrationmethod . '/lib.php';
            if (is_readable($calibrationlib)) {
                require_once($calibrationlib);
            } else {
                // Fall back in case the subplugin is not available.
                $this->calibrationmethod = 'examples';
                $calibrationlib = dirname(__FILE__) . '/calibration/' . $this->calibrationmethod . '/lib.php';
                if (is_readable($calibrationlib)) {
                    require_once($calibrationlib);
                } else {
                    // Fall back in case the subplugin is not available any more.
                    throw new coding_exception('Missing default grading calibration library ' . $calibrationlib);
                }
            }
            $classname = 'workshep_' . $this->calibrationmethod . '_calibration_method';
            $this->calibrationinstance = new $classname($this);
            if (!($this->calibrationinstance instanceof workshep_calibration_method)) {
                throw new coding_exception($classname . ' does not extend workshep_calibration_method class');
            }
        }
        return $this->calibrationinstance;
    }
    

    /**
     * @return moodle_url of this workshep's view page
     */
    public function view_url() {
        global $CFG;
        return new moodle_url('/mod/workshep/view.php', array('id' => $this->cm->id));
    }

    /**
     * @return moodle_url of the page for editing this workshep's grading form
     */
    public function editform_url() {
        global $CFG;
        return new moodle_url('/mod/workshep/editform.php', array('cmid' => $this->cm->id));
    }

    /**
     * @return moodle_url of the page for previewing this workshep's grading form
     */
    public function previewform_url() {
        global $CFG;
        return new moodle_url('/mod/workshep/editformpreview.php', array('cmid' => $this->cm->id));
    }

    /**
     * @param int $assessmentid The ID of assessment record
     * @return moodle_url of the assessment page
     */
    public function assess_url($assessmentid) {
        global $CFG;
        $assessmentid = clean_param($assessmentid, PARAM_INT);
        return new moodle_url('/mod/workshep/assessment.php', array('asid' => $assessmentid));
    }

    /**
     * @param int $assessmentid The ID of assessment record
     * @return moodle_url of the example assessment page
     */
    public function exassess_url($assessmentid) {
        global $CFG;
        $assessmentid = clean_param($assessmentid, PARAM_INT);
        return new moodle_url('/mod/workshep/exassessment.php', array('asid' => $assessmentid));
    }

    /**
     * @return moodle_url of the page to view a submission, defaults to the own one
     */
    public function submission_url($id=null) {
        global $CFG;
        return new moodle_url('/mod/workshep/submission.php', array('cmid' => $this->cm->id, 'id' => $id));
    }

    /**
     * @param int $id example submission id
     * @return moodle_url of the page to view an example submission
     */
    public function exsubmission_url($id) {
        global $CFG;
        return new moodle_url('/mod/workshep/exsubmission.php', array('cmid' => $this->cm->id, 'id' => $id));
    }

    /**
     * @param int $sid submission id
     * @param array $aid of int assessment ids
     * @return moodle_url of the page to compare assessments of the given submission
     */
    public function compare_url($sid, array $aids) {
        global $CFG;

        $url = new moodle_url('/mod/workshep/compare.php', array('cmid' => $this->cm->id, 'sid' => $sid));
        $i = 0;
        foreach ($aids as $aid) {
            $url->param("aid{$i}", $aid);
            $i++;
        }
        return $url;
    }

    /**
     * @param int $sid submission id
     * @param int $aid assessment id
     * @return moodle_url of the page to compare the reference assessments of the given example submission
     */
    public function excompare_url($sid, $aid) {
        global $CFG;
        return new moodle_url('/mod/workshep/excompare.php', array('cmid' => $this->cm->id, 'sid' => $sid, 'aid' => $aid));
    }

    /**
     * @return moodle_url of the mod_edit form
     */
    public function updatemod_url() {
        global $CFG;
        return new moodle_url('/course/modedit.php', array('update' => $this->cm->id, 'return' => 1));
    }

    /**
     * @param string $method allocation method
     * @return moodle_url to the allocation page
     */
    public function allocation_url($method=null) {
        global $CFG;
        $params = array('cmid' => $this->cm->id);
        if (!empty($method)) {
            $params['method'] = $method;
        }
        return new moodle_url('/mod/workshep/allocation.php', $params);
    }

    /**
     * @param int $phasecode The internal phase code
     * @return moodle_url of the script to change the current phase to $phasecode
     */
    public function switchphase_url($phasecode) {
        global $CFG;
        $phasecode = clean_param($phasecode, PARAM_INT);
        return new moodle_url('/mod/workshep/switchphase.php', array('cmid' => $this->cm->id, 'phase' => $phasecode));
    }

    /**
     * @return moodle_url to the aggregation page
     */
    public function aggregate_url() {
        global $CFG;
        return new moodle_url('/mod/workshep/aggregate.php', array('cmid' => $this->cm->id));
    }
    
    public function calibrate_url() {
        global $CFG;
        return new moodle_url('/mod/workshep/calibrate.php', array('id' => $this->id));
    }

    /**
     * @return moodle_url of this workshep's toolbox page
     */
    public function toolbox_url($tool) {
        global $CFG;
        return new moodle_url('/mod/workshep/toolbox.php', array('id' => $this->cm->id, 'tool' => $tool));
    }
    
    /**
     * @param int $assessmentid The ID of assessment record
     * @param moodle_url $redirect URL to redirect to after flagging
     * @return moodle_url that will flag this assessment for review
     */
    public function flag_url($assessmentid, $redirect, $unflag = false) {
        global $CFG;
        $assessmentid = clean_param($assessmentid, PARAM_INT);
        return new moodle_url('/mod/workshep/flag_assessment.php', array('asid' => $assessmentid, 'redirect' => $redirect->out(), 'unflag' => $unflag));
    }
	
	public function flagged_assessments_url() {
		return new moodle_url('/mod/workshep/flagged_assessments.php', array('id' => $this->cm->id));
	}

    /**
     * Workshop wrapper around {@see add_to_log()}
     * @deprecated since 2.7 Please use the provided event classes for logging actions.
     *
     * @param string $action to be logged
     * @param moodle_url $url absolute url as returned by {@see workshep::submission_url()} and friends
     * @param mixed $info additional info, usually id in a table
     * @param bool $return true to return the arguments for add_to_log.
     * @return void|array array of arguments for add_to_log if $return is true
     */
    public function log($action, moodle_url $url = null, $info = null, $return = false) {
        debugging('The log method is now deprecated, please use event classes instead', DEBUG_DEVELOPER);

        if (is_null($url)) {
            $url = $this->view_url();
        }

        if (is_null($info)) {
            $info = $this->id;
        }

        $logurl = $this->log_convert_url($url);
        $args = array($this->course->id, 'workshep', $action, $logurl, $info, $this->cm->id);
        if ($return) {
            return $args;
        }
        call_user_func_array('add_to_log', $args);
    }

    /**
     * Is the given user allowed to create their submission?
     *
     * @param int $userid
     * @return bool
     */
    public function creating_submission_allowed($userid) {

        $now = time();
        $ignoredeadlines = has_capability('mod/workshep:ignoredeadlines', $this->context, $userid);

        if ($this->latesubmissions) {
            if ($this->phase != self::PHASE_SUBMISSION and $this->phase != self::PHASE_ASSESSMENT) {
                // late submissions are allowed in the submission and assessment phase only
                return false;
            }
            if (!$ignoredeadlines and !empty($this->submissionstart) and $this->submissionstart > $now) {
                // late submissions are not allowed before the submission start
                return false;
            }
            return true;

        } else {
            if ($this->phase != self::PHASE_SUBMISSION) {
                // submissions are allowed during the submission phase only
                return false;
            }
            if (!$ignoredeadlines and !empty($this->submissionstart) and $this->submissionstart > $now) {
                // if enabled, submitting is not allowed before the date/time defined in the mod_form
                return false;
            }
            if (!$ignoredeadlines and !empty($this->submissionend) and $now > $this->submissionend ) {
                // if enabled, submitting is not allowed after the date/time defined in the mod_form unless late submission is allowed
                return false;
            }
            return true;
        }
    }

    /**
     * Is the given user allowed to modify their existing submission?
     *
     * @param int $userid
     * @return bool
     */
    public function modifying_submission_allowed($userid) {

        $now = time();
        $ignoredeadlines = has_capability('mod/workshep:ignoredeadlines', $this->context, $userid);

        if ($this->phase != self::PHASE_SUBMISSION) {
            // submissions can be edited during the submission phase only
            return false;
        }
        if (!$ignoredeadlines and !empty($this->submissionstart) and $this->submissionstart > $now) {
            // if enabled, re-submitting is not allowed before the date/time defined in the mod_form
            return false;
        }
        if (!$ignoredeadlines and !empty($this->submissionend) and $now > $this->submissionend) {
            // if enabled, re-submitting is not allowed after the date/time defined in the mod_form even if late submission is allowed
            return false;
        }
        return true;
    }

    /**
     * Is the given reviewer allowed to create/edit their assessments?
     *
     * @param int $userid
     * @return bool
     */
    public function assessing_allowed($userid) {

        if ($this->phase != self::PHASE_ASSESSMENT) {
            // assessing is allowed in the assessment phase only, unless the user is a teacher
            // providing additional assessment during the evaluation phase
            if ($this->phase != self::PHASE_EVALUATION or !has_capability('mod/workshep:overridegrades', $this->context, $userid)) {
                return false;
            }
        }

        $now = time();
        $ignoredeadlines = has_capability('mod/workshep:ignoredeadlines', $this->context, $userid);

        if (!$ignoredeadlines and !empty($this->assessmentstart) and $this->assessmentstart > $now) {
            // if enabled, assessing is not allowed before the date/time defined in the mod_form
            return false;
        }
        if (!$ignoredeadlines and !empty($this->assessmentend) and $now > $this->assessmentend) {
            // if enabled, assessing is not allowed after the date/time defined in the mod_form
            return false;
        }
        // here we go, assessing is allowed
        return true;
    }

    /**
     * Are reviewers allowed to create/edit their assessments of the example submissions?
     *
     * Returns null if example submissions are not enabled in this workshep. Otherwise returns
     * true or false. Note this does not check other conditions like the number of already
     * assessed examples, examples mode etc.
     *
     * @return null|bool
     */
    public function assessing_examples_allowed() {
        if (empty($this->useexamples)) {
            return null;
        }
        if (self::EXAMPLES_VOLUNTARY == $this->examplesmode) {
            return true;
        }
        if (self::EXAMPLES_BEFORE_SUBMISSION == $this->examplesmode and self::PHASE_SUBMISSION == $this->phase) {
            return true;
        }
        if (self::EXAMPLES_BEFORE_ASSESSMENT == $this->examplesmode and self::PHASE_ASSESSMENT == $this->phase) {
            return true;
        }
        
        //TODO: make this work properly for calibration
        if (self::PHASE_CALIBRATION == $this->phase) {
            return true;
        }
        
        return false;
    }

    /**
     * Are the peer-reviews available to the authors?
     *
     * @return bool
     */
    public function assessments_available() {
        return $this->phase == self::PHASE_CLOSED;
    }

    /**
     * Switch to a new workshep phase
     *
     * Modifies the underlying database record. You should terminate the script shortly after calling this.
     *
     * @param int $newphase new phase code
     * @return bool true if success, false otherwise
     */
    public function switch_phase($newphase) {
        global $DB;

        $known = $this->available_phases_list();
        if (!in_array($newphase,$known)) {
            return false;
        }

        if (self::PHASE_CLOSED == $newphase) {
            // push the grades into the gradebook
            $workshep = new stdclass();
            foreach ($this as $property => $value) {
                $workshep->{$property} = $value;
            }
            $workshep->course     = $this->course->id;
            $workshep->cmidnumber = $this->cm->id;
            $workshep->modname    = 'workshep';
            workshep_update_grades($workshep);
        }

        $DB->set_field('workshep', 'phase', $newphase, array('id' => $this->id));
        $this->phase = $newphase;
        $eventdata = array(
            'objectid' => $this->id,
            'context' => $this->context,
            'other' => array(
                'workshepphase' => $this->phase
            )
        );
        $event = \mod_workshep\event\phase_switched::create($eventdata);
        $event->trigger();
        return true;
    }

    /**
     * Saves a raw grade for submission as calculated from the assessment form fields
     *
     * @param array $assessmentid assessment record id, must exists
     * @param mixed $grade        raw percentual grade from 0.00000 to 100.00000
     * @return false|float        the saved grade
     */
    public function set_peer_grade($assessmentid, $grade) {
        global $DB;

        if (is_null($grade)) {
            return false;
        }
        $data = new stdclass();
        $data->id = $assessmentid;
        $data->grade = $grade;
        $data->timemodified = time();
        $DB->update_record('workshep_assessments', $data);
        return $grade;
    }

    /**
     * Prepares data object with all workshep grades to be rendered
     *
     * @param int $userid the user we are preparing the report for
     * @param int $groupid if non-zero, prepare the report for the given group only
     * @param int $page the current page (for the pagination)
     * @param int $perpage participants per page (for the pagination)
     * @param string $sortby lastname|firstname|submissiontitle|submissiongrade|gradinggrade
     * @param string $sorthow ASC|DESC
     * @return stdclass data for the renderer
     */
    public function prepare_grading_report_data($userid, $groupid, $page, $perpage, $sortby, $sorthow) {
        global $CFG, $DB;

        $canviewall     = has_capability('mod/workshep:viewallassessments', $this->context, $userid);
        $isparticipant  = $this->is_participant($userid);

        if (!$canviewall and !$isparticipant) {
            // who the hell is this?
            return array();
        }

        if (!in_array($sortby, array('lastname','firstname','submissiontitle','submissiongrade','gradinggrade'))) {
            $sortby = 'lastname';
        }

        if (!($sorthow === 'ASC' or $sorthow === 'DESC')) {
            $sorthow = 'ASC';
        }

        // get the list of user ids to be displayed
        if ($canviewall) {
            $participants = $this->get_participants(false, $groupid);
        } else {
            // this is an ordinary workshep participant (aka student) - display the report just for him/her
            $participants = array($userid => (object)array('id' => $userid));
        }

        // we will need to know the number of all records later for the pagination purposes
        $numofparticipants = count($participants);

        if ($numofparticipants > 0) {
            // load all fields which can be used for sorting and paginate the records
            list($participantids, $params) = $DB->get_in_or_equal(array_keys($participants), SQL_PARAMS_NAMED);
            $params['workshepid1'] = $this->id;
            $params['workshepid2'] = $this->id;
            $sqlsort = array();
            $sqlsortfields = array($sortby => $sorthow) + array('lastname' => 'ASC', 'firstname' => 'ASC', 'u.id' => 'ASC');
            foreach ($sqlsortfields as $sqlsortfieldname => $sqlsortfieldhow) {
                $sqlsort[] = $sqlsortfieldname . ' ' . $sqlsortfieldhow;
            }
            $sqlsort = implode(',', $sqlsort);
            $picturefields = user_picture::fields('u', array(), 'userid');
            $sql = "SELECT $picturefields, s.title AS submissiontitle, s.grade AS submissiongrade, ag.gradinggrade
                      FROM {user} u
                 LEFT JOIN {workshep_submissions} s ON (s.authorid = u.id AND s.workshepid = :workshepid1 AND s.example = 0)
                 LEFT JOIN {workshep_aggregations} ag ON (ag.userid = u.id AND ag.workshepid = :workshepid2)
                     WHERE u.id $participantids
                  ORDER BY $sqlsort";
            $participants = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
        } else {
            $participants = array();
        }

        // this will hold the information needed to display user names and pictures
        $userinfo = array();

        // get the user details for all participants to display
        $additionalnames = get_all_user_name_fields();
        foreach ($participants as $participant) {
            if (!isset($userinfo[$participant->userid])) {
                $userinfo[$participant->userid]            = new stdclass();
                $userinfo[$participant->userid]->id        = $participant->userid;
                $userinfo[$participant->userid]->picture   = $participant->picture;
                $userinfo[$participant->userid]->imagealt  = $participant->imagealt;
                $userinfo[$participant->userid]->email     = $participant->email;
                foreach ($additionalnames as $addname) {
                    $userinfo[$participant->userid]->$addname = $participant->$addname;
                }
            }
        }

        // load the submissions details
        $submissions = $this->get_submissions(array_keys($participants));

        // get the user details for all moderators (teachers) that have overridden a submission grade
        foreach ($submissions as $submission) {
            if (!isset($userinfo[$submission->gradeoverby])) {
                $userinfo[$submission->gradeoverby]            = new stdclass();
                $userinfo[$submission->gradeoverby]->id        = $submission->gradeoverby;
                $userinfo[$submission->gradeoverby]->picture   = $submission->overpicture;
                $userinfo[$submission->gradeoverby]->imagealt  = $submission->overimagealt;
                $userinfo[$submission->gradeoverby]->email     = $submission->overemail;
                foreach ($additionalnames as $addname) {
                    $temp = 'over' . $addname;
                    $userinfo[$submission->gradeoverby]->$addname = $submission->$temp;
                }
            }
        }

        // get the user details for all reviewers of the displayed participants
        $reviewers = array();

        if ($submissions) {
            list($submissionids, $params) = $DB->get_in_or_equal(array_keys($submissions), SQL_PARAMS_NAMED);
            list($sort, $sortparams) = users_order_by_sql('r');
            $picturefields = user_picture::fields('r', array(), 'reviewerid');
            $sql = "SELECT a.id AS assessmentid, a.submissionid, a.grade, a.gradinggrade, a.gradinggradeover, a.weight, a.submitterflagged,
                           $picturefields, s.id AS submissionid, s.authorid
                      FROM {workshep_assessments} a
                      JOIN {user} r ON (a.reviewerid = r.id)
                      JOIN {workshep_submissions} s ON (a.submissionid = s.id AND s.example = 0)
                     WHERE a.submissionid $submissionids
                  ORDER BY a.weight DESC, $sort";
            $reviewers = $DB->get_records_sql($sql, array_merge($params, $sortparams));
            
            //Highlight discrepancies
            $flags = array();
            
            //First get the submission scores
            $reviewer_submissions = array();
            foreach ($reviewers as $r) {
                if ((!is_null($r->grade)) and ($r->weight > 0)) {
                    $reviewer_submissions[$r->submissionid][$r->assessmentid] = $r->grade;
                }
            }
            
            foreach ($reviewer_submissions as $submissionid => $s) {
                if (count($s) > 2) {
                    //Calculate the standard deviation of the assessment grades for this submission
                    $mean = array_sum($s) / count($s);
                    $diffs = array();
                    foreach ($s as $v) {
                        $diffs[] = pow($v - $mean, 2);
                    }
                    
                    $diffmean = array_sum($diffs) / count($diffs);
                    $stddev = sqrt($diffmean);
                    
                    //Get the median of our marks
                    
                    $s2 = $s; // Don't muck up our original array
                    
                    sort($s2,SORT_NUMERIC); 
                    $median = (count($s2) % 2) ? 
                     $s2[floor(count($s2)/2)] : 
                     ($s2[floor(count($s2)/2)] + $s2[floor(count($s2)/2) - 1]) / 2;
                    
                    //Now if there's any outside ±2 std dev flag them
                    foreach ($s as $assessmentid => $grade) {
                        if (($grade < $median - 2 * $stddev) or ($grade > $median + 2 * $stddev)) {
                            $flags[$assessmentid] = true;
                        }
                    }
                }
            }
            
            foreach ($reviewers as $reviewer) {
                if (!isset($userinfo[$reviewer->reviewerid])) {
                    $userinfo[$reviewer->reviewerid]            = new stdclass();
                    $userinfo[$reviewer->reviewerid]->id        = $reviewer->reviewerid;
                    $userinfo[$reviewer->reviewerid]->picture   = $reviewer->picture;
                    $userinfo[$reviewer->reviewerid]->imagealt  = $reviewer->imagealt;
                    $userinfo[$reviewer->reviewerid]->email     = $reviewer->email;
                    foreach ($additionalnames as $addname) {
                        $userinfo[$reviewer->reviewerid]->$addname = $reviewer->$addname;
                    }
                }
                
                if (isset($flags[$reviewer->assessmentid])) {
                    $reviewer->flagged = true;
                }
            }
        }

        // get the user details for all reviewees of the displayed participants
        $reviewees = array();
        if ($participants) {
            list($participantids, $params) = $DB->get_in_or_equal(array_keys($participants), SQL_PARAMS_NAMED);
            list($sort, $sortparams) = users_order_by_sql('e');
            $params['workshepid'] = $this->id;
            $picturefields = user_picture::fields('e', array(), 'authorid');
            $sql = "SELECT a.id AS assessmentid, a.submissionid, a.grade, a.gradinggrade, a.gradinggradeover, a.reviewerid, a.weight,
                           s.id AS submissionid, $picturefields
                      FROM {user} u
                      JOIN {workshep_assessments} a ON (a.reviewerid = u.id)
                      JOIN {workshep_submissions} s ON (a.submissionid = s.id AND s.example = 0)
                      JOIN {user} e ON (s.authorid = e.id)
                     WHERE u.id $participantids AND s.workshepid = :workshepid
                  ORDER BY a.weight DESC, $sort";
            $reviewees = $DB->get_records_sql($sql, array_merge($params, $sortparams));
            
            foreach ($reviewees as $reviewee) {
                if (!isset($userinfo[$reviewee->authorid])) {
                    $userinfo[$reviewee->authorid]            = new stdclass();
                    $userinfo[$reviewee->authorid]->id        = $reviewee->authorid;
                    $userinfo[$reviewee->authorid]->picture   = $reviewee->picture;
                    $userinfo[$reviewee->authorid]->imagealt  = $reviewee->imagealt;
                    $userinfo[$reviewee->authorid]->email     = $reviewee->email;
                    foreach ($additionalnames as $addname) {
                        $userinfo[$reviewee->authorid]->$addname = $reviewee->$addname;
                    }
                }
            }
        }

        // finally populate the object to be rendered
        $grades = $participants;

        foreach ($participants as $participant) {
            // set up default (null) values
            $grades[$participant->userid]->submissionid = null;
            $grades[$participant->userid]->submissiontitle = null;
            $grades[$participant->userid]->submissiongrade = null;
            $grades[$participant->userid]->submissiongradeover = null;
            $grades[$participant->userid]->submissiongradeoverby = null;
            $grades[$participant->userid]->submissionpublished = null;
            $grades[$participant->userid]->reviewedby = array();
            $grades[$participant->userid]->reviewerof = array();
        }
        unset($participants);
        unset($participant);

        foreach ($submissions as $submission) {
            $grades[$submission->authorid]->submissionid = $submission->id;
            $grades[$submission->authorid]->submissiontitle = $submission->title;
            $grades[$submission->authorid]->submissiongrade = $this->real_grade($submission->grade);
            $grades[$submission->authorid]->submissiongradeover = $this->real_grade($submission->gradeover);
            $grades[$submission->authorid]->submissiongradeoverby = $submission->gradeoverby;
            $grades[$submission->authorid]->submissionpublished = $submission->published;
        }
        unset($submissions);
        unset($submission);

        foreach($reviewers as $reviewer) {
            $info = new stdclass();
            $info->userid = $reviewer->reviewerid;
            $info->assessmentid = $reviewer->assessmentid;
            $info->submissionid = $reviewer->submissionid;
            $info->grade = $this->real_grade($reviewer->grade);
            $info->gradinggrade = $this->real_grading_grade($reviewer->gradinggrade);
            $info->gradinggradeover = $this->real_grading_grade($reviewer->gradinggradeover);
            $info->weight = $reviewer->weight;
            $info->flagged = isset($reviewer->flagged);
            $info->submitterflagged = $reviewer->submitterflagged;
            $grades[$reviewer->authorid]->reviewedby[$reviewer->reviewerid] = $info;
        }
        unset($reviewers);
        unset($reviewer);

        foreach($reviewees as $reviewee) {
            $info = new stdclass();
            $info->userid = $reviewee->authorid;
            $info->assessmentid = $reviewee->assessmentid;
            $info->submissionid = $reviewee->submissionid;
            $info->grade = $this->real_grade($reviewee->grade);
            $info->gradinggrade = $this->real_grading_grade($reviewee->gradinggrade);
            $info->gradinggradeover = $this->real_grading_grade($reviewee->gradinggradeover);
            $info->weight = $reviewee->weight;
            $grades[$reviewee->reviewerid]->reviewerof[$reviewee->authorid] = $info;
        }
        unset($reviewees);
        unset($reviewee);

        foreach ($grades as $grade) {
            $grade->gradinggrade = $this->real_grading_grade($grade->gradinggrade);
        }

        $data = new stdclass();
        $data->grades = $grades;
        $data->userinfo = $userinfo;
        $data->totalcount = $numofparticipants;
        $data->maxgrade = $this->real_grade(100);
        $data->maxgradinggrade = $this->real_grading_grade(100);

        $susers = array();
        if (!empty($CFG->enablesuspendeduserdisplay)) {
            $susers = get_suspended_userids($this->context);
            foreach ($susers as $userid) {
                if (isset($data->userinfo[$userid])) {
                    $data->userinfo[$userid]->suspended = true;
                }
            }
        }
        return $data;
    }
    
    
    /**
     * Prepares the grading report data as above, but this is grouped.
     * @param int $userid the user we are preparing the report for
     * @param mixed $groups single group or array of groups - only show users who are in one of these group(s). Defaults to all
     * @param int $page the current page (for the pagination)
     * @param int $perpage participants per page (for the pagination)
     * @param string $sortby name|submissiontitle|submissiongrade|gradinggrade
     * @param string $sorthow ASC|DESC
     * @return stdclass data for the renderer
     */
    public function prepare_grading_report_data_grouped($userid, $groups, $page, $perpage, $sortby, $sorthow) {
        global $CFG, $DB;

    	//First we're going to check permissions
        $canviewall     = has_capability('mod/workshep:viewallassessments', $this->context, $userid);
        $isparticipant  = has_any_capability(array('mod/workshep:submit', 'mod/workshep:peerassess'), $this->context, $userid);
    
        if (!$canviewall and !$isparticipant) {
            // who the hell is this?
            return array();
        }
    	
    	//initialise some vars
        if (!in_array($sortby, array('name','submissiontitle','submissiongrade','gradinggrade'))) {
            $sortby = 'name';
        }
    
        if (!($sorthow === 'ASC' or $sorthow === 'DESC')) {
            $sorthow = 'ASC';
        }
    	$sorthow_const = $sorthow == 'ASC' ? SORT_ASC : SORT_DESC;
    	
    	
    	//We return two arrays: "grades" and "userinfo"
    	//Grades contains information about each submission, its reviewers and the grades they have been given
    	//Userinfo contains data about the reviewers for normalisation purposes
    	$grades = array();
    	$userinfo = array();
    	
    	//First thing we have to do is initialise the "grades" array with the group id and name
    	//We do this because the group might not have submitted anything
    	
    	$groups = groups_get_all_groups($this->course->id,0,$this->cm->groupingid);
    	foreach($groups as $k => $g) {            
    		$gradeitem = new stdClass;
    		$gradeitem->groupid = $k;
    		$gradeitem->name = $g->name;
    		$gradeitem->submissionid = null;
    		$gradeitem->submissiontitle = null;
    		$gradeitem->submissiongrade = null;
    		$gradeitem->submissiongradeover = null;
    		$gradeitem->submissiongradeoverby = null;
    		$gradeitem->submissionpublished = null;
    		$gradeitem->reviewedby = array();
    		$gradeitem->reviewerof = array();
    		$grades[$k] = $gradeitem;
    	}
    	
    	//first get all the submissions
    	$submissions = $this->get_submissions_grouped();
        
        //if we're getting this for one student then we just get their stuff
    	if (!$canviewall) {
    		$group = $this->user_group($userid);
            $usersub = null;
            foreach($submissions as $s) {
                if ($s->group->id == $group->id) {
                    $usersub = $s;
                    break;
                }
            }
    		$submissions = array($group->id => $usersub);
            
            //we actually just wipe out the array
            $grades = array();
            
            if($usersub == null) {
        		$gradeitem = new stdClass;
        		$gradeitem->groupid = $k;
        		$gradeitem->name = $g->name;
        		$gradeitem->submissionid = null;
        		$gradeitem->submissiontitle = null;
        		$gradeitem->submissiongrade = null;
        		$gradeitem->submissiongradeover = null;
        		$gradeitem->submissiongradeoverby = null;
        		$gradeitem->submissionpublished = null;
        		$gradeitem->reviewedby = array();
        		$gradeitem->reviewerof = array();
        		$grades[$group->id] = $gradeitem;
            }
    	}
    	
    	//pack out $grades
    	foreach($submissions as $k => $v) {
            if (empty($v)) continue;
            
    		$gradeitem = isset($grades[$v->group->id]) ? $grades[$v->group->id] : new stdClass;
    		$gradeitem->groupid = $v->group->id;
    		$gradeitem->name = $v->group->name;
    		$gradeitem->submissiontitle = $v->title;
    		$gradeitem->submissiongrade = $this->real_grade($v->grade);
    		$gradeitem->submissionid = $v->id;
    		$gradeitem->submissiongradeover = $this->real_grade($v->gradeover);
    		$gradeitem->submissiongradeoverby = $v->gradeoverby;
    		$gradeitem->submissionpublished = $v->published;
    		
    		$grades[$v->group->id] = $gradeitem;
    	}
        
        
        
    	// do sorting and paging now
    	foreach($grades as $k => $v) {
    		$sortfield[$k] = $v->$sortby;
    	}
    	array_multisort($sortfield, $sorthow_const, $grades);
    	
    	//ok now paging
    	$grades = array_slice($grades, $page * $perpage, $perpage);
    		
    	//now put our indices back the way they were
    	foreach($grades as $k => $v) {
    		$grades2[$v->groupid] = $v;
    	}
    	$grades = $grades2;
    	
        
        
    	//yep yep now we good let's get some reviewers
    	$findusers = array(); // we'll use this later to look up our userinfo
        $reviewer_submissions = array(); // we'll use this later to calculate outliers
    	foreach($grades as $k => $v) {
    		if(!empty($v->submissionid)) { //if this group has a submission
    			$vals = $DB->get_records("workshep_assessments", array("submissionid" => $v->submissionid), 'weight DESC', 'reviewerid AS userid, id AS assessmentid, submissionid, grade, gradinggrade, gradinggradeover, weight, submitterflagged');
                foreach($vals as $userid => $val) {
                    $val->grade = $this->real_grade($val->grade);
                    $val->gradinggrade = $this->real_grading_grade($val->gradinggrade);
                    $val->gradinggradeover = $this->real_grading_grade($val->gradinggradeover);

    				$findusers[] = $val->userid;
                    $reviewer_submissions[$v->submissionid][$val->assessmentid] = $val->grade;
    			}
    			$v->reviewedby = $vals;
    		} else {
    			$v->reviewedby = array();
    		}
    	}
        
        
        
        
        
        
        //Highlight discrepancies
        $flags = array();
        
        foreach ($reviewer_submissions as $submissionid => $s) {
            if (count($s) > 2) {
                //Calculate the standard deviation of the assessment grades for this submission
                $mean = array_sum($s) / count($s);
                $diffs = array();
                foreach ($s as $v) {
                    $diffs[] = pow($v - $mean, 2);
                }
                
                $diffmean = array_sum($diffs) / count($diffs);
                $stddev = sqrt($diffmean);
                
                //Get the median of our marks
                
                $s2 = $s; // Don't muck up our original array
                
                sort($s2,SORT_NUMERIC); 
                $median = (count($s2) % 2) ? 
                 $s2[floor(count($s2)/2)] : 
                 ($s2[floor(count($s2)/2)] + $s2[floor(count($s2)/2) - 1]) / 2;
                
                //Now if there's any outside ±2 std dev flag them
                foreach ($s as $assessmentid => $grade) {
                    if (($grade < $median - 2 * $stddev) or ($grade > $median + 2 * $stddev)) {
                        $flags[$assessmentid] = true;
                    }
                }
            }
        }
        
        
        foreach($grades as $groupid => $values) {
            foreach($values->reviewedby as $k => $v) {
                $v->flagged = isset($flags[$v->assessmentid]);
            }
        }
        
    	$userinfo = $DB->get_records_list("user","id",$findusers,'',user_picture::fields());
    	
        if (!empty($findusers)) {            
            list($select, $params) = $DB->get_in_or_equal($findusers, SQL_PARAMS_NAMED);
            $params['workshepid'] = $this->id;
        
            $usergradinggrades = $DB->get_records_select("workshep_aggregations", "workshepid = :workshepid AND userid $select", $params, '', 'userid,gradinggrade');
        }
    	
    	foreach($userinfo as $k => $v) {
    		if(!empty($usergradinggrades[$k]))
    			$v->gradinggrade = $this->real_grading_grade($usergradinggrades[$k]->gradinggrade);
    	}
    	
        $data = new stdclass();
        $data->grades = $grades;
        $data->userinfo = $userinfo;
        $data->totalcount = count($submissions);
        $data->maxgrade = $this->real_grade(100);
        $data->maxgradinggrade = $this->real_grading_grade(100);
        $susers = array();
        if (!empty($CFG->enablesuspendeduserdisplay)) {
            $susers = get_suspended_userids($this->context);
            foreach ($susers as $userid) {
                if (isset($data->userinfo[$userid])) {
                    $data->userinfo[$userid]->suspended = true;
                }
            }
        }

        return $data;
    }
    

    /**
     * Calculates the real value of a grade
     *
     * @param float $value percentual value from 0 to 100
     * @param float $max   the maximal grade
     * @return string
     */
    public function real_grade_value($value, $max) {
        $localized = true;
        if (is_null($value) or $value === '') {
            return null;
        } elseif ($max == 0) {
            return 0;
        } else {
            return format_float($max * $value / 100, $this->gradedecimals, $localized);
        }
    }

    /**
     * Calculates the raw (percentual) value from a real grade
     *
     * This is used in cases when a user wants to give a grade such as 12 of 20 and we need to save
     * this value in a raw percentual form into DB
     * @param float $value given grade
     * @param float $max   the maximal grade
     * @return float       suitable to be stored as numeric(10,5)
     */
    public function raw_grade_value($value, $max) {
        if (is_null($value) or $value === '') {
            return null;
        }
        if ($max == 0 or $value < 0) {
            return 0;
        }
        $p = $value / $max * 100;
        if ($p > 100) {
            return $max;
        }
        return grade_floatval($p);
    }

    /**
     * Calculates the real value of grade for submission
     *
     * @param float $value percentual value from 0 to 100
     * @return string
     */
    public function real_grade($value) {
        return $this->real_grade_value($value, $this->grade);
    }

    /**
     * Calculates the real value of grade for assessment
     *
     * @param float $value percentual value from 0 to 100
     * @return string
     */
    public function real_grading_grade($value) {
        return $this->real_grade_value($value, $this->gradinggrade);
    }

    /**
     * Sets the given grades and received grading grades to null
     *
     * This does not clear the information about how the peers filled the assessment forms, but
     * clears the calculated grades in workshep_assessments. Therefore reviewers have to re-assess
     * the allocated submissions.
     *
     * @return void
     */
    public function clear_assessments() {
        global $DB;

        $submissions = $this->get_submissions();
        if (empty($submissions)) {
            // no money, no love
            return;
        }
        $submissions = array_keys($submissions);
        list($sql, $params) = $DB->get_in_or_equal($submissions, SQL_PARAMS_NAMED);
        $sql = "submissionid $sql";
        $DB->set_field_select('workshep_assessments', 'grade', null, $sql, $params);
        $DB->set_field_select('workshep_assessments', 'gradinggrade', null, $sql, $params);
    }

    /**
     * Sets the grades for submission to null
     *
     * @param null|int|array $restrict If null, update all authors, otherwise update just grades for the given author(s)
     * @return void
     */
    public function clear_submission_grades($restrict=null) {
        global $DB;

        $sql = "workshepid = :workshepid AND example = 0";
        $params = array('workshepid' => $this->id);

        if (is_null($restrict)) {
            // update all users - no more conditions
        } elseif (!empty($restrict)) {
            list($usql, $uparams) = $DB->get_in_or_equal($restrict, SQL_PARAMS_NAMED);
            $sql .= " AND authorid $usql";
            $params = array_merge($params, $uparams);
        } else {
            throw new coding_exception('Empty value is not a valid parameter here');
        }

        $DB->set_field_select('workshep_submissions', 'grade', null, $sql, $params);
    }

    /**
     * Calculates grades for submission for the given participant(s) and updates it in the database
     *
     * @param null|int|array $restrict If null, update all authors, otherwise update just grades for the given author(s)
     * @return void
     */
    public function aggregate_submission_grades($restrict=null) {
        global $DB;

        // fetch a recordset with all assessments to process
        $sql = 'SELECT s.id AS submissionid, s.grade AS submissiongrade,
                       a.weight, a.grade
                  FROM {workshep_submissions} s
             LEFT JOIN {workshep_assessments} a ON (a.submissionid = s.id)
                 WHERE s.example=0 AND s.workshepid=:workshepid'; // to be cont.
        $params = array('workshepid' => $this->id);

        if (is_null($restrict)) {
            // update all users - no more conditions
        } elseif (!empty($restrict)) {
            list($usql, $uparams) = $DB->get_in_or_equal($restrict, SQL_PARAMS_NAMED);
            $sql .= " AND s.authorid $usql";
            $params = array_merge($params, $uparams);
        } else {
            throw new coding_exception('Empty value is not a valid parameter here');
        }

        $sql .= ' ORDER BY s.id'; // this is important for bulk processing

        $rs         = $DB->get_recordset_sql($sql, $params);
        $batch      = array();    // will contain a set of all assessments of a single submission
        $previous   = null;       // a previous record in the recordset

        foreach ($rs as $current) {
            if (is_null($previous)) {
                // we are processing the very first record in the recordset
                $previous   = $current;
            }
            if ($current->submissionid == $previous->submissionid) {
                // we are still processing the current submission
                $batch[] = $current;
            } else {
                // process all the assessments of a sigle submission
                $this->aggregate_submission_grades_process($batch);
                // and then start to process another submission
                $batch      = array($current);
                $previous   = $current;
            }
        }
        // do not forget to process the last batch!
        $this->aggregate_submission_grades_process($batch);
        $rs->close();
    }

    /**
     * Sets the aggregated grades for assessment to null
     *
     * @param null|int|array $restrict If null, update all reviewers, otherwise update just grades for the given reviewer(s)
     * @return void
     */
    public function clear_grading_grades($restrict=null) {
        global $DB;

        $sql = "workshepid = :workshepid";
        $params = array('workshepid' => $this->id);

        if (is_null($restrict)) {
            // update all users - no more conditions
        } elseif (!empty($restrict)) {
            list($usql, $uparams) = $DB->get_in_or_equal($restrict, SQL_PARAMS_NAMED);
            $sql .= " AND userid $usql";
            $params = array_merge($params, $uparams);
        } else {
            throw new coding_exception('Empty value is not a valid parameter here');
        }

        $DB->set_field_select('workshep_aggregations', 'gradinggrade', null, $sql, $params);
    }

    /**
     * Calculates grades for assessment for the given participant(s)
     *
     * Grade for assessment is calculated as a simple mean of all grading grades calculated by the grading evaluator.
     * The assessment weight is not taken into account here.
     *
     * @param null|int|array $restrict If null, update all reviewers, otherwise update just grades for the given reviewer(s)
     * @return void
     */
    public function aggregate_grading_grades($restrict=null) {
        global $DB;

        // fetch a recordset with all assessments to process
        $sql = 'SELECT a.reviewerid, a.gradinggrade, a.gradinggradeover,
                       ag.id AS aggregationid, ag.gradinggrade AS aggregatedgrade
                  FROM {workshep_assessments} a
            INNER JOIN {workshep_submissions} s ON (a.submissionid = s.id)
             LEFT JOIN {workshep_aggregations} ag ON (ag.userid = a.reviewerid AND ag.workshepid = s.workshepid)
                 WHERE s.example=0 AND s.workshepid=:workshepid'; // to be cont.
        $params = array('workshepid' => $this->id);

        if (is_null($restrict)) {
            // update all users - no more conditions
        } elseif (!empty($restrict)) {
            list($usql, $uparams) = $DB->get_in_or_equal($restrict, SQL_PARAMS_NAMED);
            $sql .= " AND a.reviewerid $usql";
            $params = array_merge($params, $uparams);
        } else {
            throw new coding_exception('Empty value is not a valid parameter here');
        }

        $sql .= ' ORDER BY a.reviewerid'; // this is important for bulk processing

        $rs         = $DB->get_recordset_sql($sql, $params);
        $batch      = array();    // will contain a set of all assessments of a single submission
        $previous   = null;       // a previous record in the recordset

        foreach ($rs as $current) {
            if (is_null($previous)) {
                // we are processing the very first record in the recordset
                $previous   = $current;
            }
            if ($current->reviewerid == $previous->reviewerid) {
                // we are still processing the current reviewer
                $batch[] = $current;
            } else {
                // process all the assessments of a sigle submission
                $this->aggregate_grading_grades_process($batch);
                // and then start to process another reviewer
                $batch      = array($current);
                $previous   = $current;
            }
        }
        // do not forget to process the last batch!
        $this->aggregate_grading_grades_process($batch);
        $rs->close();
    }

    /**
     * Returns the mform the teachers use to put a feedback for the reviewer
     *
     * @param moodle_url $actionurl
     * @param stdClass $assessment
     * @param array $options editable, editableweight, overridablegradinggrade
     * @return workshep_feedbackreviewer_form
     */
    public function get_feedbackreviewer_form(moodle_url $actionurl, stdclass $assessment, $options=array()) {
        global $CFG;
        require_once(dirname(__FILE__) . '/feedbackreviewer_form.php');

        $current = new stdclass();
        $current->asid                      = $assessment->id;
        $current->weight                    = $assessment->weight;
        $current->gradinggrade              = $this->real_grading_grade($assessment->gradinggrade);
        $current->gradinggradeover          = $this->real_grading_grade($assessment->gradinggradeover);
        $current->feedbackreviewer          = $assessment->feedbackreviewer;
        $current->feedbackreviewerformat    = $assessment->feedbackreviewerformat;
        if (is_null($current->gradinggrade)) {
            $current->gradinggrade = get_string('nullgrade', 'workshep');
        }
        if (!isset($options['editable'])) {
            $editable = true;   // by default
        } else {
            $editable = (bool)$options['editable'];
        }

        // prepare wysiwyg editor
        $current = file_prepare_standard_editor($current, 'feedbackreviewer', array());

        return new workshep_feedbackreviewer_form($actionurl,
                array('workshep' => $this, 'current' => $current, 'editoropts' => array(), 'options' => $options),
                'post', '', null, $editable);
    }

    /**
     * Returns the mform the teachers use to put a feedback for the author on their submission
     *
     * @param moodle_url $actionurl
     * @param stdClass $submission
     * @param array $options editable
     * @return workshep_feedbackauthor_form
     */
    public function get_feedbackauthor_form(moodle_url $actionurl, stdclass $submission, $options=array()) {
        global $CFG;
        require_once(dirname(__FILE__) . '/feedbackauthor_form.php');

        $current = new stdclass();
        $current->submissionid          = $submission->id;
        $current->published             = $submission->published;
        $current->grade                 = $this->real_grade($submission->grade);
        $current->gradeover             = $this->real_grade($submission->gradeover);
        $current->feedbackauthor        = $submission->feedbackauthor;
        $current->feedbackauthorformat  = $submission->feedbackauthorformat;
        if (is_null($current->grade)) {
            $current->grade = get_string('nullgrade', 'workshep');
        }
        if (!isset($options['editable'])) {
            $editable = true;   // by default
        } else {
            $editable = (bool)$options['editable'];
        }

        // prepare wysiwyg editor
        $current = file_prepare_standard_editor($current, 'feedbackauthor', array());

        return new workshep_feedbackauthor_form($actionurl,
                array('workshep' => $this, 'current' => $current, 'editoropts' => array(), 'options' => $options),
                'post', '', null, $editable);
    }

    /**
     * Returns the information about the user's grades as they are stored in the gradebook
     *
     * The submission grade is returned for users with the capability mod/workshep:submit and the
     * assessment grade is returned for users with the capability mod/workshep:peerassess. Unless the
     * user has the capability to view hidden grades, grades must be visible to be returned. Null
     * grades are not returned. If none grade is to be returned, this method returns false.
     *
     * @param int $userid the user's id
     * @return workshep_final_grades|false
     */
    public function get_gradebook_grades($userid) {
        global $CFG;
        require_once($CFG->libdir.'/gradelib.php');

        if (empty($userid)) {
            throw new coding_exception('User id expected, empty value given.');
        }

        // Read data via the Gradebook API
        $gradebook = grade_get_grades($this->course->id, 'mod', 'workshep', $this->id, $userid);

        $grades = new workshep_final_grades();

        if (has_capability('mod/workshep:submit', $this->context, $userid)) {
            if (!empty($gradebook->items[0]->grades)) {
                $submissiongrade = reset($gradebook->items[0]->grades);
                if (!is_null($submissiongrade->grade)) {
                    if (!$submissiongrade->hidden or has_capability('moodle/grade:viewhidden', $this->context, $userid)) {
                        $grades->submissiongrade = $submissiongrade;
                    }
                }
            }
        }

        if (has_capability('mod/workshep:peerassess', $this->context, $userid)) {
            if (!empty($gradebook->items[1]->grades)) {
                $assessmentgrade = reset($gradebook->items[1]->grades);
                if (!is_null($assessmentgrade->grade)) {
                    if (!$assessmentgrade->hidden or has_capability('moodle/grade:viewhidden', $this->context, $userid)) {
                        $grades->assessmentgrade = $assessmentgrade;
                    }
                }
            }
        }

        if (!is_null($grades->submissiongrade) or !is_null($grades->assessmentgrade)) {
            return $grades;
        }

        return false;
    }

    /**
     * Return the editor options for the overall feedback for the author.
     *
     * @return array
     */
    public function overall_feedback_content_options() {
        return array(
            'subdirs' => 0,
            'maxbytes' => $this->overallfeedbackmaxbytes,
            'maxfiles' => $this->overallfeedbackfiles,
            'changeformat' => 1,
            'context' => $this->context,
        );
    }

    /**
     * Return the filemanager options for the overall feedback for the author.
     *
     * @return array
     */
    public function overall_feedback_attachment_options() {
        return array(
            'subdirs' => 1,
            'maxbytes' => $this->overallfeedbackmaxbytes,
            'maxfiles' => $this->overallfeedbackfiles,
            'return_types' => FILE_INTERNAL,
        );
    }

    /**
     * Performs the reset of this workshep instance.
     *
     * @param stdClass $data The actual course reset settings.
     * @return array List of results, each being array[(string)component, (string)item, (string)error]
     */
    public function reset_userdata(stdClass $data) {

        $componentstr = get_string('pluginname', 'workshep').': '.format_string($this->name);
        $status = array();

        if (!empty($data->reset_workshep_assessments) or !empty($data->reset_workshep_submissions)) {
            // Reset all data related to assessments, including assessments of
            // example submissions.
            $result = $this->reset_userdata_assessments($data);
            if ($result === true) {
                $status[] = array(
                    'component' => $componentstr,
                    'item' => get_string('resetassessments', 'mod_workshep'),
                    'error' => false,
                );
            } else {
                $status[] = array(
                    'component' => $componentstr,
                    'item' => get_string('resetassessments', 'mod_workshep'),
                    'error' => $result,
                );
            }
        }

        if (!empty($data->reset_workshep_submissions)) {
            // Reset all remaining data related to submissions.
            $result = $this->reset_userdata_submissions($data);
            if ($result === true) {
                $status[] = array(
                    'component' => $componentstr,
                    'item' => get_string('resetsubmissions', 'mod_workshep'),
                    'error' => false,
                );
            } else {
                $status[] = array(
                    'component' => $componentstr,
                    'item' => get_string('resetsubmissions', 'mod_workshep'),
                    'error' => $result,
                );
            }
        }

        if (!empty($data->reset_workshep_phase)) {
            // Do not use the {@link workshep::switch_phase()} here, we do not
            // want to trigger events.
            $this->reset_phase();
            $status[] = array(
                'component' => $componentstr,
                'item' => get_string('resetsubmissions', 'mod_workshep'),
                'error' => false,
            );
        }

        return $status;
    }


    ////////////////////////////////////////////////////////////////////////////////
    // Internal methods (implementation details)                                  //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * Given an array of all assessments of a single submission, calculates the final grade for this submission
     *
     * This calculates the weighted mean of the passed assessment grades. If, however, the submission grade
     * was overridden by a teacher, the gradeover value is returned and the rest of grades are ignored.
     *
     * @param array $assessments of stdclass(->submissionid ->submissiongrade ->gradeover ->weight ->grade)
     * @return void
     */
    protected function aggregate_submission_grades_process(array $assessments) {
        global $DB;

        $submissionid   = null; // the id of the submission being processed
        $current        = null; // the grade currently saved in database
        $finalgrade     = null; // the new grade to be calculated
        $sumgrades      = 0;
        $sumweights     = 0;

        foreach ($assessments as $assessment) {
            if (is_null($submissionid)) {
                // the id is the same in all records, fetch it during the first loop cycle
                $submissionid = $assessment->submissionid;
            }
            if (is_null($current)) {
                // the currently saved grade is the same in all records, fetch it during the first loop cycle
                $current = $assessment->submissiongrade;
            }
            if (is_null($assessment->grade)) {
                // this was not assessed yet
                continue;
            }
            if ($assessment->weight == 0) {
                // this does not influence the calculation
                continue;
            }
            $sumgrades  += $assessment->grade * $assessment->weight;
            $sumweights += $assessment->weight;
        }
        if ($sumweights > 0 and is_null($finalgrade)) {
            $finalgrade = grade_floatval($sumgrades / $sumweights);
        }
        // check if the new final grade differs from the one stored in the database
        if (grade_floats_different($finalgrade, $current)) {
            // we need to save new calculation into the database
            $record = new stdclass();
            $record->id = $submissionid;
            $record->grade = $finalgrade;
            $record->timegraded = time();
            $DB->update_record('workshep_submissions', $record);
        }
    }

    /**
     * Given an array of all assessments done by a single reviewer, calculates the final grading grade
     *
     * This calculates the simple mean of the passed grading grades. If, however, the grading grade
     * was overridden by a teacher, the gradinggradeover value is returned and the rest of grades are ignored.
     *
     * @param array $assessments of stdclass(->reviewerid ->gradinggrade ->gradinggradeover ->aggregationid ->aggregatedgrade)
     * @param null|int $timegraded explicit timestamp of the aggregation, defaults to the current time
     * @return void
     */
    protected function aggregate_grading_grades_process(array $assessments, $timegraded = null) {
        global $DB;

        $reviewerid = null; // the id of the reviewer being processed
        $current    = null; // the gradinggrade currently saved in database
        $finalgrade = null; // the new grade to be calculated
        $agid       = null; // aggregation id
        $sumgrades  = 0;
        $count      = 0;

        if (is_null($timegraded)) {
            $timegraded = time();
        }

        foreach ($assessments as $assessment) {
            if (is_null($reviewerid)) {
                // the id is the same in all records, fetch it during the first loop cycle
                $reviewerid = $assessment->reviewerid;
            }
            if (is_null($agid)) {
                // the id is the same in all records, fetch it during the first loop cycle
                $agid = $assessment->aggregationid;
            }
            if (is_null($current)) {
                // the currently saved grade is the same in all records, fetch it during the first loop cycle
                $current = $assessment->aggregatedgrade;
            }
            if (!is_null($assessment->gradinggradeover)) {
                // the grading grade for this assessment is overridden by a teacher
                $sumgrades += $assessment->gradinggradeover;
                $count++;
            } else {
                if (!is_null($assessment->gradinggrade)) {
                    $sumgrades += $assessment->gradinggrade;
                    $count++;
                }
            }
        }
        if ($count > 0) {
            $finalgrade = grade_floatval($sumgrades / $count);
        }

        // Event information.
        $params = array(
            'context' => $this->context,
            'courseid' => $this->course->id,
            'relateduserid' => $reviewerid
        );

        // check if the new final grade differs from the one stored in the database
        if (grade_floats_different($finalgrade, $current)) {
            $params['other'] = array(
                'currentgrade' => $current,
                'finalgrade' => $finalgrade
            );

            // we need to save new calculation into the database
            if (is_null($agid)) {
                // no aggregation record yet
                $record = new stdclass();
                $record->workshepid = $this->id;
                $record->userid = $reviewerid;
                $record->gradinggrade = $finalgrade;
                $record->timegraded = $timegraded;
                $record->id = $DB->insert_record('workshep_aggregations', $record);
                $params['objectid'] = $record->id;
                $event = \mod_workshep\event\assessment_evaluated::create($params);
                $event->trigger();
            } else {
                $record = new stdclass();
                $record->id = $agid;
                $record->gradinggrade = $finalgrade;
                $record->timegraded = $timegraded;
                $DB->update_record('workshep_aggregations', $record);
                $params['objectid'] = $agid;
                $event = \mod_workshep\event\assessment_reevaluated::create($params);
                $event->trigger();
            }
        }
    }

    /**
     * Returns SQL to fetch all enrolled users with the given capability in the current workshep
     *
     * The returned array consists of string $sql and the $params array. Note that the $sql can be
     * empty if groupmembersonly is enabled and the associated grouping is empty.
     *
     * @param string $capability the name of the capability
     * @param bool $musthavesubmission ff true, return only users who have already submitted
     * @param int $groupid 0 means ignore groups, any other value limits the result by group id
     * @return array of (string)sql, (array)params
     */
    protected function get_users_with_capability_sql($capability, $musthavesubmission, $groupid) {
        global $CFG;
        /** @var int static counter used to generate unique parameter holders */
        static $inc = 0;
        $inc++;

        // If the caller requests all groups and we are using a selected grouping,
        // recursively call this function for each group in the grouping (this is
        // needed because get_enrolled_sql only supports a single group).
        if (empty($groupid) and $this->cm->groupingid) {
            $groupingid = $this->cm->groupingid;
            $groupinggroupids = array_keys(groups_get_all_groups($this->cm->course, 0, $this->cm->groupingid, 'g.id'));
            $sql = array();
            $params = array();
            foreach ($groupinggroupids as $groupinggroupid) {
                if ($groupinggroupid > 0) { // just in case in order not to fall into the endless loop
                    list($gsql, $gparams) = $this->get_users_with_capability_sql($capability, $musthavesubmission, $groupinggroupid);
                    $sql[] = $gsql;
                    $params = array_merge($params, $gparams);
                }
            }
            $sql = implode(PHP_EOL." UNION ".PHP_EOL, $sql);
            return array($sql, $params);
        }

        list($esql, $params) = get_enrolled_sql($this->context, $capability, $groupid, true);

        $userfields = user_picture::fields('u');
        
        //there is no reason not to include this, stops another DB roundtrip / join
        if ($musthavesubmission)
            $userfields .= ",ws.id as submissionid";

        $sql = "SELECT $userfields
                  FROM {user} u
                  JOIN ($esql) je ON (je.id = u.id AND u.deleted = 0) ";

        if ($musthavesubmission) {
            $sql .= " JOIN {workshep_submissions} ws ON (ws.authorid = u.id AND ws.example = 0 AND ws.workshepid = :workshepid{$inc}) ";
            $params['workshepid'.$inc] = $this->id;
        }

        return array($sql, $params);
    }

    /**
     * Returns SQL statement that can be used to fetch all actively enrolled participants in the workshep
     *
     * @param bool $musthavesubmission if true, return only users who have already submitted
     * @param int $groupid 0 means ignore groups, any other value limits the result by group id
     * @return array of (string)sql, (array)params
     */
    protected function get_participants_sql($musthavesubmission=false, $groupid=0) {

        list($sql1, $params1) = $this->get_users_with_capability_sql('mod/workshep:submit', $musthavesubmission, $groupid);
        list($sql2, $params2) = $this->get_users_with_capability_sql('mod/workshep:peerassess', $musthavesubmission, $groupid);

        if (empty($sql1) or empty($sql2)) {
            if (empty($sql1) and empty($sql2)) {
                return array('', array());
            } else if (empty($sql1)) {
                $sql = $sql2;
                $params = $params2;
            } else {
                $sql = $sql1;
                $params = $params1;
            }
        } else {
            $sql = $sql1.PHP_EOL." UNION ".PHP_EOL.$sql2;
            $params = array_merge($params1, $params2);
        }

        return array($sql, $params);
    }

    /**
     * @return array of available workshep phases
     */
    public function available_phases_list() {
        
        $phases = array(
            self::PHASE_SETUP,
            self::PHASE_SUBMISSION,
            self::PHASE_ASSESSMENT,
            self::PHASE_EVALUATION,
            self::PHASE_CLOSED
        );
        if ($this->usecalibration) {
            $index = array_search($this->calibrationphase, $phases);
            if ($index !== false) {
                array_splice($phases, $index + 1, 0, self::PHASE_CALIBRATION);
            }
        }
        return $phases;
    }

    /**
     * Converts absolute URL to relative URL needed by {@see add_to_log()}
     *
     * @param moodle_url $url absolute URL
     * @return string
     */
    protected function log_convert_url(moodle_url $fullurl) {
        static $baseurl;

        if (!isset($baseurl)) {
            $baseurl = new moodle_url('/mod/workshep/');
            $baseurl = $baseurl->out();
        }

        return substr($fullurl->out(), strlen($baseurl));
    }

    /**
     * Removes all user data related to assessments (including allocations).
     *
     * This includes assessments of example submissions as long as they are not
     * referential assessments.
     *
     * @param stdClass $data The actual course reset settings.
     * @return bool|string True on success, error message otherwise.
     */
    protected function reset_userdata_assessments(stdClass $data) {
        global $DB;

        $sql = "SELECT a.id
                  FROM {workshep_assessments} a
                  JOIN {workshep_submissions} s ON (a.submissionid = s.id)
                 WHERE s.workshepid = :workshepid
                       AND (s.example = 0 OR (s.example = 1 AND a.weight = 0))";

        $assessments = $DB->get_records_sql($sql, array('workshepid' => $this->id));
        $this->delete_assessment(array_keys($assessments));

        $DB->delete_records('workshep_aggregations', array('workshepid' => $this->id));

        return true;
    }

    /**
     * Removes all user data related to participants' submissions.
     *
     * @param stdClass $data The actual course reset settings.
     * @return bool|string True on success, error message otherwise.
     */
    protected function reset_userdata_submissions(stdClass $data) {
        global $DB;

        $submissions = $this->get_submissions();
        foreach ($submissions as $submission) {
            $this->delete_submission($submission);
        }

        return true;
    }

    /**
     * Hard set the workshep phase to the setup one.
     */
    protected function reset_phase() {
        global $DB;

        $DB->set_field('workshep', 'phase', self::PHASE_SETUP, array('id' => $this->id));
        $this->phase = self::PHASE_SETUP;
    }
}

////////////////////////////////////////////////////////////////////////////////
// Renderable components
////////////////////////////////////////////////////////////////////////////////

/**
 * Represents the user planner tool
 *
 * Planner contains list of phases. Each phase contains list of tasks. Task is a simple object with
 * title, link and completed (true/false/null logic).
 */
class workshep_user_plan implements renderable {

    /** @var int id of the user this plan is for */
    public $userid;
    /** @var workshep */
    public $workshep;
    /** @var array of (stdclass)tasks */
    public $phases = array();
    /** @var null|array of example submissions to be assessed by the planner owner */
    protected $examples = null;

    /**
     * Prepare an individual workshep plan for the given user.
     *
     * @param workshep $workshep instance
     * @param int $userid whom the plan is prepared for
     */
    public function __construct(workshep $workshep, $userid) {
        global $DB;

        $this->workshep = $workshep;
        $this->userid   = $userid;
        
        //get all the groups in this module. better than doing it repeatedly, just store it in memory.
        if ($workshep->cm->groupingid) {
            $groups = groups_get_all_groups($workshep->course->id,0,$workshep->cm->groupingid);
        } else {
            $groups = groups_get_all_groups($workshep->course->id);
        }
        
        //before we get started, check if we have any users in more than one group
        if ($workshep->teammode) {
            $rslt = $workshep->users_in_more_than_one_group();

            if(count($rslt)) {
                $users = array();
                foreach($rslt as $u) {
                    $users[] = fullname($u);
                }
                print_error('teammode_multiplegroupswarning','workshep',new moodle_url('/group/groupings.php',array('id' => $workshep->course->id)),implode($users,', '));
            }
            
            if (count($groups) == 0) {
                $workshep->teammode = false;
                $DB->set_field('workshep','teammode',0,array('id' => $workshep->id));
                print_error('teammode_nogroupswarning','workshep',new moodle_url('/mod/workshep/view.php',array('id' => $workshep->cm->id)));
            }
        }
        
        if (count($groups)) {
            list($sql, $params) = $DB->get_in_or_equal(array_keys($groups), SQL_PARAMS_NAMED);
            $groupmembers = $DB->get_records_select('groups_members','groupid '.$sql,$params,'','id,userid,groupid');
        
            if (count($groupmembers)) {
                $userids = array();
                foreach($groupmembers as $v) {
                    $userids[] = $v->userid;
                }
                list($sql, $params) = $DB->get_in_or_equal($userids); //all students
                $studentdata = $DB->get_records_select('user','id '.$sql,$params,'',user_picture::fields());
        
                foreach($groupmembers as $v) {
                    $groups[$v->groupid]->members[$v->userid] = $studentdata[$v->userid];
                }
            }
        }

        //---------------------------------------------------------
        // * SETUP | submission | assessment | evaluation | closed
        //---------------------------------------------------------
        $phase = new stdclass();
        $phase->title = get_string('phasesetup', 'workshep');
        $phase->tasks = array();
        if (has_capability('moodle/course:manageactivities', $workshep->context, $userid)) {
            $task = new stdclass();
            $task->title = get_string('taskintro', 'workshep');
            $task->link = $workshep->updatemod_url();
            $task->completed = !(trim($workshep->intro) == '');
            $phase->tasks['intro'] = $task;
        }
        if (has_capability('moodle/course:manageactivities', $workshep->context, $userid)) {
            $task = new stdclass();
            $task->title = get_string('taskinstructauthors', 'workshep');
            $task->link = $workshep->updatemod_url();
            $task->completed = !(trim($workshep->instructauthors) == '');
            $phase->tasks['instructauthors'] = $task;
        }
        if (has_capability('mod/workshep:editdimensions', $workshep->context, $userid)) {
            $task = new stdclass();
            $task->title = get_string('editassessmentform', 'workshep');
            $task->link = $workshep->editform_url();
            if ($workshep->grading_strategy_instance()->form_ready()) {
                $task->completed = true;
            } elseif ($workshep->phase > workshep::PHASE_SETUP) {
                $task->completed = false;
            }
            $phase->tasks['editform'] = $task;
        }
        if ($workshep->useexamples and has_capability('mod/workshep:manageexamples', $workshep->context, $userid)) {
            $task = new stdclass();
            $task->title = get_string('prepareexamples', 'workshep');
            $examplescount = $DB->count_records('workshep_submissions', array('example' => 1, 'workshepid' => $workshep->id));
            if ($examplescount > 0) {
                $countsql = "SELECT count(*) FROM {workshep_assessments} a, {workshep_submissions} s WHERE a.submissionid = s.id AND a.weight = 1 AND a.grade IS NOT NULL AND s.example = 1 AND s.workshepid = :workshepid";
                $examplesassessed = $DB->count_records_sql($countsql, array('workshepid' => $workshep->id));
                if ($examplescount <= $examplesassessed) {
                    $task->completed = true;
                } elseif ($workshep->phase > workshep::PHASE_SETUP) {
                    $task->completed = false;
                }
            } elseif ($workshep->phase > workshep::PHASE_SETUP) {
                $task->completed = false;
            }
            $phase->tasks['prepareexamples'] = $task;
        }
        if (empty($phase->tasks) and $workshep->phase == workshep::PHASE_SETUP) {
            // if we are in the setup phase and there is no task (typical for students), let us
            // display some explanation what is going on
            $task = new stdclass();
            $task->title = get_string('undersetup', 'workshep');
            $task->completed = 'info';
            $phase->tasks['setupinfo'] = $task;
        }
        $this->phases[workshep::PHASE_SETUP] = $phase;

        //---------------------------------------------------------
        // setup | * SUBMISSION | assessment | evaluation | closed
        //---------------------------------------------------------
        $phase = new stdclass();
        $phase->title = get_string('phasesubmission', 'workshep');
        $phase->tasks = array();
        if (has_capability('moodle/course:manageactivities', $workshep->context, $userid)) {
            $task = new stdclass();
            $task->title = get_string('taskinstructreviewers', 'workshep');
            $task->link = $workshep->updatemod_url();
            if (trim($workshep->instructreviewers)) {
                $task->completed = true;
            } elseif ($workshep->phase >= workshep::PHASE_ASSESSMENT) {
                $task->completed = false;
            }
            $phase->tasks['instructreviewers'] = $task;
        }
        if ($workshep->useexamples and $workshep->examplesmode == workshep::EXAMPLES_BEFORE_SUBMISSION
                and has_capability('mod/workshep:submit', $workshep->context, $userid, false)
                    and !has_capability('mod/workshep:manageexamples', $workshep->context, $userid)) {
            $task = new stdclass();
            $task->title = get_string('exampleassesstask', 'workshep');
            $examples = $this->get_examples();
            $a = new stdclass();
            $a->expected = count($examples);
            $a->assessed = 0;
            foreach ($examples as $exampleid => $example) {
                if (!is_null($example->grade)) {
                    $a->assessed++;
                }
            }
            $task->details = get_string('exampleassesstaskdetails', 'workshep', $a);
            if ($a->assessed == $a->expected) {
                $task->completed = true;
            } elseif ($workshep->phase >= workshep::PHASE_ASSESSMENT) {
                $task->completed = false;
            }
            $phase->tasks['examples'] = $task;
        }
        if (has_capability('mod/workshep:submit', $workshep->context, $userid, false)) {
            $task = new stdclass();
            $task->title = get_string('tasksubmit', 'workshep');
            $task->link = $workshep->submission_url();
            if ($DB->record_exists('workshep_submissions', array('workshepid'=>$workshep->id, 'example'=>0, 'authorid'=>$userid))) {
                $task->completed = true;
            } elseif ($workshep->teammode && $workshep->get_submission_by_author($userid,'s.id') !== false) {
              	$task->completed = true;
            } elseif ($workshep->phase >= workshep::PHASE_ASSESSMENT) {
                $task->completed = false;
            } else {
                $task->completed = null;    // still has a chance to submit
            }
            $phase->tasks['submit'] = $task;
        }
        if (has_capability('mod/workshep:allocate', $workshep->context, $userid)) {
            if ($workshep->phaseswitchassessment) {
                $task = new stdClass();
                $allocator = $DB->get_record('workshepallocation_scheduled', array('workshepid' => $workshep->id));
                if (empty($allocator)) {
                    $task->completed = false;
                } else if ($allocator->enabled and is_null($allocator->resultstatus)) {
                    $task->completed = true;
                } else if ($workshep->submissionend > time()) {
                    $task->completed = null;
                } else {
                    $task->completed = false;
                }
                $task->title = get_string('setup', 'workshepallocation_scheduled');
                $task->link = $workshep->allocation_url('scheduled');
                $phase->tasks['allocatescheduled'] = $task;
            }
            $task = new stdclass();
            $task->title = get_string('allocate', 'workshep');
            $task->link = $workshep->allocation_url();
            $numofauthors = $workshep->count_potential_authors(false);
            
            //these two counting methods need different code for teammode and normal mode
            if ($workshep->teammode) {
                $submissions_grouped = $workshep->get_submissions_grouped();
                $numofsubmissions = count($submissions_grouped);
            } else {
                $numofsubmissions = $DB->count_records('workshep_submissions', array('workshepid'=>$workshep->id, 'example'=>0));
            }
            
            //common sql to teammode and non-teammode count
            if ($workshep->teammode) {
                if (count($submissions_grouped)) {
                    list($inorequal, $params) = $DB->get_in_or_equal(array_keys($submissions_grouped));
                    $sql = "SELECT COUNT(DISTINCT submissionid) FROM {workshep_assessments} WHERE submissionid $inorequal";
                    $numnonallocated = $numofsubmissions - $DB->count_records_sql($sql,$params);
                } else {
                    $numnonallocated = 0;
                }
            } else {
                $sql = 'SELECT COUNT(s.id) FROM {workshep_submissions} s
                 LEFT JOIN {workshep_assessments} a ON (a.submissionid=s.id)
                     WHERE s.workshepid = :workshepid AND s.example=0 AND a.submissionid IS NULL';
                $params['workshepid'] = $workshep->id;
                $numnonallocated = $DB->count_records_sql($sql, $params);
            }
            
            
            
            if ($numofsubmissions == 0) {
                $task->completed = null;
            } elseif ($numnonallocated == 0) {
                $task->completed = true;
            } elseif ($workshep->phase > workshep::PHASE_SUBMISSION) {
                $task->completed = false;
            } else {
                $task->completed = null;    // still has a chance to allocate
            }
            $a = new stdclass();
            $a->expected    = $numofauthors;
            $a->submitted   = $numofsubmissions;
            $a->allocate    = $numnonallocated;
            $task->details  = get_string('allocatedetails', 'workshep', $a);
            unset($a);
            $phase->tasks['allocate'] = $task;

            if ($numofsubmissions < $numofauthors and $workshep->phase >= workshep::PHASE_SUBMISSION) {
                $task = new stdclass();
                $task->title = get_string('someuserswosubmission', 'workshep');
                $task->completed = 'info';
                $phase->tasks['allocateinfo'] = $task;
            }

        }
        if ($workshep->submissionstart) {
            $task = new stdclass();
            $task->title = get_string('submissionstartdatetime', 'workshep', workshep::timestamp_formats($workshep->submissionstart));
            $task->completed = 'info';
            $phase->tasks['submissionstartdatetime'] = $task;
        }
        if ($workshep->submissionend) {
            $task = new stdclass();
            $task->title = get_string('submissionenddatetime', 'workshep', workshep::timestamp_formats($workshep->submissionend));
            $task->completed = 'info';
            $phase->tasks['submissionenddatetime'] = $task;
        }
        if (($workshep->submissionstart < time()) and $workshep->latesubmissions) {
            $task = new stdclass();
            $task->title = get_string('latesubmissionsallowed', 'workshep');
            $task->completed = 'info';
            $phase->tasks['latesubmissionsallowed'] = $task;
        }
        if (isset($phase->tasks['submissionstartdatetime']) or isset($phase->tasks['submissionenddatetime'])) {
            if (has_capability('mod/workshep:ignoredeadlines', $workshep->context, $userid)) {
                $task = new stdclass();
                $task->title = get_string('deadlinesignored', 'workshep');
                $task->completed = 'info';
                $phase->tasks['deadlinesignored'] = $task;
            }
        }
        $this->phases[workshep::PHASE_SUBMISSION] = $phase;

        //----------------------------------------------------------
        // setup | submission | * CALIBRATION | assessment | closed
        //----------------------------------------------------------
        
        $phase = new stdclass();
        $phase->title = get_string('phasecalibration', 'workshep');
        $phase->tasks = array();
        
        if (has_capability('mod/workshep:submit', $workshep->context, $userid, false) and ! has_capability('mod/workshep:manageexamples', $workshep->context)) {
            $task = new stdclass();
            $task->title = get_string('exampleassesstask','workshep');
            $phase->tasks[] = $task;
        }
        
        if (has_capability('mod/workshep:overridegrades', $workshep->context)) {
            $task = new stdclass();
            $task->title = get_string('calculatecalibrationscores', 'workshep');
            $phase->tasks[] = $task;
			
			$task = new stdclass();
			$reviewers = $workshep->get_potential_reviewers();
			$numexamples = (int)$workshep->numexamples ?: count($workshep->get_examples_for_manager());
			$sql = <<<SQL
SELECT a.reviewerid, count(a) 
FROM {workshep_submissions} s 
	LEFT JOIN {workshep_assessments} a 
	ON a.submissionid = s.id 
WHERE s.workshepid = :workshepid 
	AND s.example = 1 
	AND a.weight = 0 
	AND a.grade IS NOT NULL
GROUP BY a.reviewerid 
HAVING count(a) >= :numexamples
SQL;

			$reviewcounts = $DB->get_records_sql($sql, array('workshepid' => $workshep->id, 'numexamples' => $numexamples));
			
			$task->title = get_string('calibrationcompletion', 'workshep', array('num' => count($reviewcounts), 'den' => count($reviewers)));
			$task->completed = 'info';
			$phase->tasks[] = $task;
        }

        $this->phases[workshep::PHASE_CALIBRATION] = $phase;

        //---------------------------------------------------------
        // setup | submission | * ASSESSMENT | evaluation | closed
        //---------------------------------------------------------
        $phase = new stdclass();
        $phase->title = get_string('phaseassessment', 'workshep');
        $phase->tasks = array();
        $phase->isreviewer = has_capability('mod/workshep:peerassess', $workshep->context, $userid);
        if ($workshep->phase == workshep::PHASE_SUBMISSION and $workshep->phaseswitchassessment
                and has_capability('mod/workshep:switchphase', $workshep->context, $userid)) {
            $task = new stdClass();
            $task->title = get_string('switchphase30auto', 'mod_workshep', workshep::timestamp_formats($workshep->submissionend));
            $task->completed = 'info';
            $phase->tasks['autoswitchinfo'] = $task;
        }
        if ($workshep->useexamples and $workshep->examplesmode == workshep::EXAMPLES_BEFORE_ASSESSMENT
                and $phase->isreviewer and !has_capability('mod/workshep:manageexamples', $workshep->context, $userid)) {
            $task = new stdclass();
            $task->title = get_string('exampleassesstask', 'workshep');
            $examples = $workshep->get_examples_for_reviewer($userid);
            $a = new stdclass();
            $a->expected = count($examples);
            $a->assessed = 0;
            foreach ($examples as $exampleid => $example) {
                if (!is_null($example->grade)) {
                    $a->assessed++;
                }
            }
            $task->details = get_string('exampleassesstaskdetails', 'workshep', $a);
            if ($a->assessed == $a->expected) {
                $task->completed = true;
            } elseif ($workshep->phase > workshep::PHASE_ASSESSMENT) {
                $task->completed = false;
            }
            $phase->tasks['examples'] = $task;
        }
        if (empty($phase->tasks['examples']) or !empty($phase->tasks['examples']->completed)) {
            $phase->assessments = $workshep->get_assessments_by_reviewer($userid);
            $numofpeers     = 0;    // number of allocated peer-assessments
            $numofpeerstodo = 0;    // number of peer-assessments to do
            $numofself      = 0;    // number of allocated self-assessments - should be 0 or 1
            $numofselftodo  = 0;    // number of self-assessments to do - should be 0 or 1
            foreach ($phase->assessments as $a) {
                if ($a->authorid == $userid) {
                    $numofself++;
                    if (is_null($a->grade)) {
                        $numofselftodo++;
                    }
                } else {
                    $numofpeers++;
                    if (is_null($a->grade)) {
                        $numofpeerstodo++;
                    }
                }
            }
            unset($a);
            if ($numofpeers) {
                $task = new stdclass();
                if ($numofpeerstodo == 0) {
                    $task->completed = true;
                } elseif ($workshep->phase > workshep::PHASE_ASSESSMENT) {
                    $task->completed = false;
                }
                $a = new stdclass();
                $a->total = $numofpeers;
                $a->todo  = $numofpeerstodo;
                $task->title = get_string('taskassesspeers', 'workshep');
                $task->details = get_string('taskassesspeersdetails', 'workshep', $a);
                unset($a);
                $phase->tasks['assesspeers'] = $task;
            }
            if ($workshep->useselfassessment and $numofself) {
                $task = new stdclass();
                if ($numofselftodo == 0) {
                    $task->completed = true;
                } elseif ($workshep->phase > workshep::PHASE_ASSESSMENT) {
                    $task->completed = false;
                }
                $task->title = get_string('taskassessself', 'workshep');
                $phase->tasks['assessself'] = $task;
            }
        }
        if ($workshep->assessmentstart) {
            $task = new stdclass();
            $task->title = get_string('assessmentstartdatetime', 'workshep', workshep::timestamp_formats($workshep->assessmentstart));
            $task->completed = 'info';
            $phase->tasks['assessmentstartdatetime'] = $task;
        }
        if ($workshep->assessmentend) {
            $task = new stdclass();
            $task->title = get_string('assessmentenddatetime', 'workshep', workshep::timestamp_formats($workshep->assessmentend));
            $task->completed = 'info';
            $phase->tasks['assessmentenddatetime'] = $task;
        }
        if (isset($phase->tasks['assessmentstartdatetime']) or isset($phase->tasks['assessmentenddatetime'])) {
            if (has_capability('mod/workshep:ignoredeadlines', $workshep->context, $userid)) {
                $task = new stdclass();
                $task->title = get_string('deadlinesignored', 'workshep');
                $task->completed = 'info';
                $phase->tasks['deadlinesignored'] = $task;
            }
        }
        $this->phases[workshep::PHASE_ASSESSMENT] = $phase;

        //---------------------------------------------------------
        // setup | submission | assessment | * EVALUATION | closed
        //---------------------------------------------------------
        $phase = new stdclass();
        $phase->title = get_string('phaseevaluation', 'workshep');
        $phase->tasks = array();
        if (has_capability('mod/workshep:overridegrades', $workshep->context)) {
            $expected = $workshep->count_potential_authors(false);
            $calculated = $DB->count_records_select('workshep_submissions',
                    'workshepid = ? AND (grade IS NOT NULL OR gradeover IS NOT NULL)', array($workshep->id));
            $task = new stdclass();
            $task->title = get_string('calculatesubmissiongrades', 'workshep');
            $a = new stdclass();
            $a->expected    = $expected;
            $a->calculated  = $calculated;
            $task->details  = get_string('calculatesubmissiongradesdetails', 'workshep', $a);
            if ($calculated >= $expected) {
                $task->completed = true;
            } elseif ($workshep->phase > workshep::PHASE_EVALUATION) {
                $task->completed = false;
            }
            $phase->tasks['calculatesubmissiongrade'] = $task;

            $expected = $workshep->count_potential_reviewers(false);
            $calculated = $DB->count_records_select('workshep_aggregations',
                    'workshepid = ? AND gradinggrade IS NOT NULL', array($workshep->id));
            $task = new stdclass();
            $task->title = get_string('calculategradinggrades', 'workshep');
            $a = new stdclass();
            $a->expected    = $expected;
            $a->calculated  = $calculated;
            $task->details  = get_string('calculategradinggradesdetails', 'workshep', $a);
            if ($calculated >= $expected) {
                $task->completed = true;
            } elseif ($workshep->phase > workshep::PHASE_EVALUATION) {
                $task->completed = false;
            }
            $phase->tasks['calculategradinggrade'] = $task;

        } elseif ($workshep->phase == workshep::PHASE_EVALUATION) {
            $task = new stdclass();
            $task->title = get_string('evaluategradeswait', 'workshep');
            $task->completed = 'info';
            $phase->tasks['evaluateinfo'] = $task;
        }

        if (has_capability('moodle/course:manageactivities', $workshep->context, $userid)) {
            $task = new stdclass();
            $task->title = get_string('taskconclusion', 'workshep');
            $task->link = $workshep->updatemod_url();
            if (trim($workshep->conclusion)) {
                $task->completed = true;
            } elseif ($workshep->phase >= workshep::PHASE_EVALUATION) {
                $task->completed = false;
            }
            $phase->tasks['conclusion'] = $task;
        }

        $this->phases[workshep::PHASE_EVALUATION] = $phase;

        //---------------------------------------------------------
        // setup | submission | assessment | evaluation | * CLOSED
        //---------------------------------------------------------
        $phase = new stdclass();
        $phase->title = get_string('phaseclosed', 'workshep');
        $phase->tasks = array();
        $this->phases[workshep::PHASE_CLOSED] = $phase;
        
        $orderedphases = $workshep->available_phases_list();
        $phases = array();
        foreach ($orderedphases as $k => $v) {
            $phases[$v] = $this->phases[$v];
        }
        $this->phases = $phases;

        // Polish data, set default values if not done explicitly
        foreach ($this->phases as $phasecode => $phase) {
            $phase->title       = isset($phase->title)      ? $phase->title     : '';
            $phase->tasks       = isset($phase->tasks)      ? $phase->tasks     : array();
            if ($phasecode == $workshep->phase) {
                $phase->active = true;
            } else {
                $phase->active = false;
            }
            if (!isset($phase->actions)) {
                $phase->actions = array();
            }

            foreach ($phase->tasks as $taskcode => $task) {
                $task->title        = isset($task->title)       ? $task->title      : '';
                $task->link         = isset($task->link)        ? $task->link       : null;
                $task->details      = isset($task->details)     ? $task->details    : '';
                $task->completed    = isset($task->completed)   ? $task->completed  : null;
            }
        }

        // Add phase switching actions
        if (has_capability('mod/workshep:switchphase', $workshep->context, $userid)) {
            foreach ($this->phases as $phasecode => $phase) {
                if (! $phase->active) {
                    $action = new stdclass();
                    $action->type = 'switchphase';
                    $action->url  = $workshep->switchphase_url($phasecode);
                    $phase->actions[] = $action;
                }
            }
        }
    }

    /**
     * Returns example submissions to be assessed by the owner of the planner
     *
     * This is here to cache the DB query because the same list is needed later in view.php
     *
     * @see workshep::get_examples_for_reviewer() for the format of returned value
     * @return array
     */
    public function get_examples() {
        if (is_null($this->examples)) {
            $this->examples = $this->workshep->get_examples_for_reviewer($this->userid);
        }
        return $this->examples;
    }
}

/**
 * Common base class for submissions and example submissions rendering
 *
 * Subclasses of this class convert raw submission record from
 * workshep_submissions table (as returned by {@see workshep::get_submission_by_id()}
 * for example) into renderable objects.
 */
abstract class workshep_submission_base {

    /** @var bool is the submission anonymous (i.e. contains author information) */
    protected $anonymous;

    /* @var array of columns from workshep_submissions that are assigned as properties */
    protected $fields = array();

    /** @var workshep */
    protected $workshep;

    /**
     * Copies the properties of the given database record into properties of $this instance
     *
     * @param workshep $workshep
     * @param stdClass $submission full record
     * @param bool $showauthor show the author-related information
     * @param array $options additional properties
     */
    public function __construct(workshep $workshep, stdClass $submission, $showauthor = false) {

        $this->workshep = $workshep;

        foreach ($this->fields as $field) {
            if (!property_exists($submission, $field)) {
                throw new coding_exception('Submission record must provide public property ' . $field);
            }
            if (!property_exists($this, $field)) {
                throw new coding_exception('Renderable component must accept public property ' . $field);
            }
            $this->{$field} = $submission->{$field};
        }

        if ($showauthor) {
            $this->anonymous = false;
        } else {
            $this->anonymize();
        }
    }

    /**
     * Unsets all author-related properties so that the renderer does not have access to them
     *
     * Usually this is called by the contructor but can be called explicitely, too.
     */
    public function anonymize() {
        $authorfields = explode(',', user_picture::fields());
        foreach ($authorfields as $field) {
            $prefixedusernamefield = 'author' . $field;
            unset($this->{$prefixedusernamefield});
        }
        $this->anonymous = true;
    }

    /**
     * Does the submission object contain author-related information?
     *
     * @return null|boolean
     */
    public function is_anonymous() {
        return $this->anonymous;
    }
}

/**
 * Renderable object containing a basic set of information needed to display the submission summary
 *
 * @see workshep_renderer::render_workshep_submission_summary
 */
class workshep_submission_summary extends workshep_submission_base implements renderable {

    /** @var int */
    public $id;
    /** @var string */
    public $title;
    /** @var string graded|notgraded */
    public $status;
    /** @var int */
    public $timecreated;
    /** @var int */
    public $timemodified;
    /** @var int */
    public $authorid;
    /** @var string */
    public $authorfirstname;
    /** @var string */
    public $authorlastname;
    /** @var string */
    public $authorfirstnamephonetic;
    /** @var string */
    public $authorlastnamephonetic;
    /** @var string */
    public $authormiddlename;
    /** @var string */
    public $authoralternatename;
    /** @var int */
    public $authorpicture;
    /** @var string */
    public $authorimagealt;
    /** @var string */
    public $authoremail;
    /** @var moodle_url to display submission */
    public $url;

    /**
     * @var array of columns from workshep_submissions that are assigned as properties
     * of instances of this class
     */
    protected $fields = array(
        'id', 'title', 'timecreated', 'timemodified',
        'authorid', 'authorfirstname', 'authorlastname', 'authorfirstnamephonetic', 'authorlastnamephonetic',
        'authormiddlename', 'authoralternatename', 'authorpicture',
        'authorimagealt', 'authoremail');
}

class workshep_group_submission_summary extends workshep_submission_base implements renderable {

    /** @var int */
    public $id;
    /** @var string */
    public $title;
    /** @var string graded|notgraded */
    public $status;
    /** @var int */
    public $timecreated;
    /** @var int */
    public $timemodified;
    /** @var int */
    public $authorid;
    /** @var string */
    public $authorfirstname;
    /** @var string */
    public $authorlastname;
    /** @var int */
    public $authorpicture;
    /** @var string */
    public $authorimagealt;
    /** @var string */
    public $authoremail;
    /** @var moodle_url to display submission */
    public $url;
    public $group;

    /**
     * @var array of columns from workshep_submissions that are assigned as properties
     * of instances of this class
     */
    protected $fields = array(
        'id', 'title', 'timecreated', 'timemodified',
        'authorid', 'authorfirstname', 'authorlastname', 'authorpicture',
        'authorimagealt', 'authoremail');
}

/**
 * Renderable object containing all the information needed to display the submission
 *
 * @see workshep_renderer::render_workshep_submission()
 */
class workshep_submission extends workshep_submission_summary implements renderable {

    /** @var string */
    public $content;
    /** @var int */
    public $contentformat;
    /** @var bool */
    public $contenttrust;
    /** @var array */
    public $attachment;
    /** @var group */
    public $group; //set if teammode

    /**
     * @var array of columns from workshep_submissions that are assigned as properties
     * of instances of this class
     */
    protected $fields = array(
        'id', 'title', 'timecreated', 'timemodified', 'content', 'contentformat', 'contenttrust',
        'attachment', 'authorid', 'authorfirstname', 'authorlastname', 'authorfirstnamephonetic', 'authorlastnamephonetic',
        'authormiddlename', 'authoralternatename', 'authorpicture', 'authorimagealt', 'authoremail');
}

/**
 * Renderable object containing a basic set of information needed to display the example submission summary
 *
 * @see workshep::prepare_example_summary()
 * @see workshep_renderer::render_workshep_example_submission_summary()
 */
class workshep_example_submission_summary extends workshep_submission_base implements renderable {

    /** @var int */
    public $id;
    /** @var string */
    public $title;
    /** @var string graded|notgraded */
    public $status;
    /** @var stdClass */
    public $gradeinfo;
    /** @var moodle_url */
    public $url;
    /** @var moodle_url */
    public $editurl;
    /** @var string */
    public $assesslabel;
    /** @var moodle_url */
    public $assessurl;
    /** @var bool must be set explicitly by the caller */
    public $editable = false;

    /**
     * @var array of columns from workshep_submissions that are assigned as properties
     * of instances of this class
     */
    protected $fields = array('id', 'title');

    /**
     * Example submissions are always anonymous
     *
     * @return true
     */
    public function is_anonymous() {
        return true;
    }
}

/**
 * Renderable object containing all the information needed to display the example submission
 *
 * @see workshep_renderer::render_workshep_example_submission()
 */
class workshep_example_submission extends workshep_example_submission_summary implements renderable {

    /** @var string */
    public $content;
    /** @var int */
    public $contentformat;
    /** @var bool */
    public $contenttrust;
    /** @var array */
    public $attachment;

    /**
     * @var array of columns from workshep_submissions that are assigned as properties
     * of instances of this class
     */
    protected $fields = array('id', 'title', 'content', 'contentformat', 'contenttrust', 'attachment');
}


/**
 * Common base class for assessments rendering
 *
 * Subclasses of this class convert raw assessment record from
 * workshep_assessments table (as returned by {@see workshep::get_assessment_by_id()}
 * for example) into renderable objects.
 */
abstract class workshep_assessment_base {

    /** @var string the optional title of the assessment */
    public $title = '';

    /** @var workshep_assessment_form $form as returned by {@link workshep_strategy::get_assessment_form()} */
    public $form;

    /** @var moodle_url */
    public $url;

    /** @var float|null the real received grade */
    public $realgrade = null;

    /** @var float the real maximum grade */
    public $maxgrade;

    /** @var stdClass|null reviewer user info */
    public $reviewer = null;

    /** @var stdClass|null assessed submission's author user info */
    public $author = null;
    
    /** @var array of actions */
    public $actions = array();

    /* @var array of columns that are assigned as properties */
    protected $fields = array();

    /** @var workshep */
    protected $workshep;

    /**
     * Copies the properties of the given database record into properties of $this instance
     *
     * The $options keys are: showreviewer, showauthor
     * @param workshep $workshep
     * @param stdClass $assessment full record
     * @param array $options additional properties
     */
    public function __construct(workshep $workshep, stdClass $record, array $options = array()) {

        $this->workshep = $workshep;
        $this->validate_raw_record($record);

        foreach ($this->fields as $field) {
            if (!property_exists($record, $field)) {
                throw new coding_exception('Assessment record must provide public property ' . $field);
            }
            if (!property_exists($this, $field)) {
                throw new coding_exception('Renderable component must accept public property ' . $field);
            }
            $this->{$field} = $record->{$field};
        }

        if (!empty($options['showreviewer'])) {
            $this->reviewer = user_picture::unalias($record, null, 'revieweridx', 'reviewer');
        }

        if (!empty($options['showauthor'])) {
            $this->author = user_picture::unalias($record, null, 'authorid', 'author');
        }
    }

    /**
     * Adds a new action
     *
     * @param moodle_url $url action URL
     * @param string $label action label
     * @param string $method get|post
     */
    public function add_action(moodle_url $url, $label, $method = 'get') {

        $action = new stdClass();
        $action->url = $url;
        $action->label = $label;
        $action->method = $method;

        $this->actions[] = $action;
    }

    /**
     * Makes sure that we can cook the renderable component from the passed raw database record
     *
     * @param stdClass $assessment full assessment record
     * @throws coding_exception if the caller passed unexpected data
     */
    protected function validate_raw_record(stdClass $record) {
        // nothing to do here
    }
}


/**
 * Represents a rendarable full assessment
 */
class workshep_assessment extends workshep_assessment_base implements renderable {

    /** @var int */
    public $id;

    /** @var int */
    public $submissionid;
	
	/** @var stdClass */
	public $submission;

    /** @var int */
    public $weight;

    /** @var int */
    public $timecreated;

    /** @var int */
    public $timemodified;

    /** @var float */
    public $grade;

    /** @var float */
    public $gradinggrade;

    /** @var float */
    public $gradinggradeover;

    /** @var string */
    public $feedbackauthor;

    /** @var int */
    public $feedbackauthorformat;

    /** @var int */
    public $feedbackauthorattachment;
	
	/** @var bool Show flagged item resolution options */
	public $resolution;

    /** @var array */
    protected $fields = array('id', 'submissionid', 'weight', 'timecreated',
        'timemodified', 'grade', 'gradinggrade', 'gradinggradeover', 'feedbackauthor',
        'feedbackauthorformat', 'feedbackauthorattachment');
	
    /**
     * Format the overall feedback text content
     *
     * False is returned if the overall feedback feature is disabled. Null is returned
     * if the overall feedback content has not been found. Otherwise, string with
     * formatted feedback text is returned.
     *
     * @return string|bool|null
     */
    public function get_overall_feedback_content() {

        if ($this->workshep->overallfeedbackmode == 0) {
            return false;
        }

        if (trim($this->feedbackauthor) === '') {
            return null;
        }

        $content = file_rewrite_pluginfile_urls($this->feedbackauthor, 'pluginfile.php', $this->workshep->context->id,
            'mod_workshep', 'overallfeedback_content', $this->id);
        $content = format_text($content, $this->feedbackauthorformat,
            array('overflowdiv' => true, 'context' => $this->workshep->context));

        return $content;
    }

    /**
     * Prepares the list of overall feedback attachments
     *
     * Returns false if overall feedback attachments are not allowed. Otherwise returns
     * list of attachments (may be empty).
     *
     * @return bool|array of stdClass
     */
    public function get_overall_feedback_attachments() {

        if ($this->workshep->overallfeedbackmode == 0) {
            return false;
        }

        if ($this->workshep->overallfeedbackfiles == 0) {
            return false;
        }

        if (empty($this->feedbackauthorattachment)) {
            return array();
        }

        $attachments = array();
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->workshep->context->id, 'mod_workshep', 'overallfeedback_attachment', $this->id);
        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }
            $filepath = $file->get_filepath();
            $filename = $file->get_filename();
            $fileurl = moodle_url::make_pluginfile_url($this->workshep->context->id, 'mod_workshep',
                'overallfeedback_attachment', $this->id, $filepath, $filename, true);
            $previewurl = new moodle_url(moodle_url::make_pluginfile_url($this->workshep->context->id, 'mod_workshep',
                'overallfeedback_attachment', $this->id, $filepath, $filename, false), array('preview' => 'bigthumb'));
            $attachments[] = (object)array(
                'filepath' => $filepath,
                'filename' => $filename,
                'fileurl' => $fileurl,
                'previewurl' => $previewurl,
                'mimetype' => $file->get_mimetype(),

            );
        }

        return $attachments;
    }
}


/**
 * Represents a renderable training assessment of an example submission
 */
class workshep_example_assessment extends workshep_assessment implements renderable {

    /** @var stdClass if set, the assessment will also show the reference assessment for comparison */
    public $reference_form;
    
    /** @var stdClass if set, the assessment will also show the reference assessment's overall feedback */
    public $reference_assessment;

    /**
     * @see parent::validate_raw_record()
     */
    protected function validate_raw_record(stdClass $record) {
        if ($record->weight != 0) {
            throw new coding_exception('Invalid weight of example submission assessment');
        }
        parent::validate_raw_record($record);
    }
}


/**
 * Represents a renderable reference assessment of an example submission
 */
class workshep_example_reference_assessment extends workshep_assessment implements renderable {

    /**
     * @see parent::validate_raw_record()
     */
    protected function validate_raw_record(stdClass $record) {
        if ($record->weight != 1) {
            throw new coding_exception('Invalid weight of the reference example submission assessment');
        }
        parent::validate_raw_record($record);
    }
}


/**
 * Renderable message to be displayed to the user
 *
 * Message can contain an optional action link with a label that is supposed to be rendered
 * as a button or a link.
 *
 * @see workshep::renderer::render_workshep_message()
 */
class workshep_message implements renderable {

    const TYPE_INFO     = 10;
    const TYPE_OK       = 20;
    const TYPE_ERROR    = 30;

    /** @var string */
    protected $text = '';
    /** @var int */
    protected $type = self::TYPE_INFO;
    /** @var moodle_url */
    protected $actionurl = null;
    /** @var string */
    protected $actionlabel = '';

    /**
     * @param string $text short text to be displayed
     * @param string $type optional message type info|ok|error
     */
    public function __construct($text = null, $type = self::TYPE_INFO) {
        $this->set_text($text);
        $this->set_type($type);
    }

    /**
     * Sets the message text
     *
     * @param string $text short text to be displayed
     */
    public function set_text($text) {
        $this->text = $text;
    }

    /**
     * Sets the message type
     *
     * @param int $type
     */
    public function set_type($type = self::TYPE_INFO) {
        if (in_array($type, array(self::TYPE_OK, self::TYPE_ERROR, self::TYPE_INFO))) {
            $this->type = $type;
        } else {
            throw new coding_exception('Unknown message type.');
        }
    }

    /**
     * Sets the optional message action
     *
     * @param moodle_url $url to follow on action
     * @param string $label action label
     */
    public function set_action(moodle_url $url, $label) {
        $this->actionurl    = $url;
        $this->actionlabel  = $label;
    }

    /**
     * Returns message text with HTML tags quoted
     *
     * @return string
     */
    public function get_message() {
        return s($this->text);
    }

    /**
     * Returns message type
     *
     * @return int
     */
    public function get_type() {
        return $this->type;
    }

    /**
     * Returns action URL
     *
     * @return moodle_url|null
     */
    public function get_action_url() {
        return $this->actionurl;
    }

    /**
     * Returns action label
     *
     * @return string
     */
    public function get_action_label() {
        return $this->actionlabel;
    }
}


/**
 * Renderable component containing all the data needed to display the grading report
 */
class workshep_grading_report implements renderable {

    /** @var stdClass returned by {@see workshep::prepare_grading_report_data()} */
    protected $data;
    /** @var stdClass rendering options */
    protected $options;

    /**
     * Grades in $data must be already rounded to the set number of decimals or must be null
     * (in which later case, the [mod_workshep,nullgrade] string shall be displayed)
     *
     * @param stdClass $data prepared by {@link workshep::prepare_grading_report_data()}
     * @param stdClass $options display options (showauthornames, showreviewernames, sortby, sorthow, showsubmissiongrade, showgradinggrade)
     */
    public function __construct(stdClass $data, stdClass $options) {
        $this->data     = $data;
        $this->options  = $options;
    }

    /**
     * @return stdClass grading report data
     */
    public function get_data() {
        return $this->data;
    }

    /**
     * @return stdClass rendering options
     */
    public function get_options() {
        return $this->options;
    }
}

//clone of above

class workshep_grouped_grading_report implements renderable {

    protected $data;
    protected $options;

    /**
     * Grades in $data must be already rounded to the set number of decimals or must be null
     * (in which later case, the [mod_workshep,nullgrade] string shall be displayed)
     *
     * @param stdClass $data prepared by {@link workshep::prepare_grading_report_data_grouped()}
     * @param stdClass $options display options (showauthornames, showreviewernames, sortby, sorthow, showsubmissiongrade, showgradinggrade)
     */
    public function __construct(stdClass $data, stdClass $options) {
        $this->data     = $data;
        $this->options  = $options;
    }
    
    public function get_data() {
        return $this->data;
    }
    public function get_options() {
        return $this->options;
    }
}



/**
 * Base class for renderable feedback for author and feedback for reviewer
 */
abstract class workshep_feedback {

    /** @var stdClass the user info */
    protected $provider = null;

    /** @var string the feedback text */
    protected $content = null;

    /** @var int format of the feedback text */
    protected $format = null;

    /**
     * @return stdClass the user info
     */
    public function get_provider() {

        if (is_null($this->provider)) {
            throw new coding_exception('Feedback provider not set');
        }

        return $this->provider;
    }

    /**
     * @return string the feedback text
     */
    public function get_content() {

        if (is_null($this->content)) {
            throw new coding_exception('Feedback content not set');
        }

        return $this->content;
    }

    /**
     * @return int format of the feedback text
     */
    public function get_format() {

        if (is_null($this->format)) {
            throw new coding_exception('Feedback text format not set');
        }

        return $this->format;
    }
}


/**
 * Renderable feedback for the author of submission
 */
class workshep_feedback_author extends workshep_feedback implements renderable {

    /**
     * Extracts feedback from the given submission record
     *
     * @param stdClass $submission record as returned by {@see self::get_submission_by_id()}
     */
    public function __construct(stdClass $submission) {

        $this->provider = user_picture::unalias($submission, null, 'gradeoverbyx', 'gradeoverby');
        $this->content  = $submission->feedbackauthor;
        $this->format   = $submission->feedbackauthorformat;
    }
}


/**
 * Renderable feedback for the reviewer
 */
class workshep_feedback_reviewer extends workshep_feedback implements renderable {

    /**
     * Extracts feedback from the given assessment record
     *
     * @param stdClass $assessment record as returned by eg {@see self::get_assessment_by_id()}
     */
    public function __construct(stdClass $assessment) {

        $this->provider = user_picture::unalias($assessment, null, 'gradinggradeoverbyx', 'overby');
        $this->content  = $assessment->feedbackreviewer;
        $this->format   = $assessment->feedbackreviewerformat;
    }
}


/**
 * Holds the final grades for the activity as are stored in the gradebook
 */
class workshep_final_grades implements renderable {

    /** @var object the info from the gradebook about the grade for submission */
    public $submissiongrade = null;

    /** @var object the infor from the gradebook about the grade for assessment */
    public $assessmentgrade = null;
}

/**
 * Helpful info for setting up random examples 
 */
class workshep_random_examples_helper implements renderable {

    public $slices;
    
    public static $descriptors = array(
        2 => array('Bad','Good'),
        3 => array('Poor','Average','Good'),
        4 => array('Poor','Average','Good','Exceptional'),
        5 => array('Poor','Average','Good','Very Good','Exceptional'),
        6 => array('Poor','Below Average','Average','Good','Very Good','Exceptional'),
        6 => array('Poor','Below Average','Average','Above Average','Good','Very Good','Exceptional'),
        7 => array('Very Poor','Poor','Below Average','Average','Above Average','Good','Very Good','Exceptional'),
        8 => array('Very Poor','Poor','Below Average','Average','Above Average','Good','Very Good','Exceptional','Exemplary'),
        9 => array('Very Poor','Poor','Below Average','Average','Above Average','Good','Very Good','Near-Exceptional','Exceptional','Exemplary'),
        10 => array('Extremely Poor','Very Poor','Poor','Below Average','Average','Above Average','Good','Very Good','Near-Exceptional','Exceptional','Exemplary'),
        11 => array('Extremely Poor','Very Poor','Poor','Passable','Below Average','Average','Above Average','Good','Very Good','Near-Exceptional','Exceptional','Exemplary'),
        12 => array('Extremely Poor','Very Poor','Poor','Just Passable','Passable','Below Average','Average','Above Average','Good','Very Good','Near-Exceptional','Exceptional','Exemplary'),
        13 => array('Extremely Poor','Very Poor','Poor','Just Passable','Passable','Below Average','Average','Above Average','Fairly Good','Good','Very Good','Near-Exceptional','Exceptional','Exemplary'),
        14 => array('Unacceptable','Extremely Poor','Very Poor','Poor','Just Passable','Passable','Below Average','Average','Above Average','Fairly Good','Good','Very Good','Near-Exceptional','Exceptional','Exemplary'),
        15 => array('Unacceptable','Extremely Poor','Very Poor','Poor','Just Passable','Passable','Below Average','Average','Above Average','Fairly Good','Good','Very Good','Near-Exceptional','Exceptional','Exemplary','Perfect')
    ); 

    /**
     * @param array $examples all the examples in the workshep
     * @param int $n the number of examples that will be shown to users ($workshep->numexamples)
     */
    public function __construct($examples,$n) {

        $slices = workshep::slice_example_submissions($examples,$n);
        
        $this->slices = array();
        foreach ($slices as $i => $s) {
            
            $slice = new stdClass;
            
            $slice->min = (float)(reset($s)->grade);
            $slice->max = (float)(end($s)->grade);
            
            $slice->colour = $this->get_colour($i,$n,1);
            $slice->title = workshep_random_examples_helper::$descriptors[count($slices)][$i];
            $slice->width = $slice->max - $slice->min . "%";
            
            $grades = array();
            foreach($s as $v) $grades[] = $v->grade;
            $slice->mean = array_sum($grades) / count($grades);
            $slice->meancolour = $this->get_colour($i,$n,-1);

            $slice->submissions = $s;
            $slice->subcolour = $this->get_colour($i,$n,0);
            
            //identify warnings
            
            if ($i > 0) {
                $prev = $this->slices[$i - 1];
                if ($slice->min == $prev->max) {
                    //overlap
                    $prev->warnings[] = get_string('randomexamplesoverlapwarning','workshep',array("prev" => $prev->title, "next" => $slice->title));
                }
            }
            
            $this->slices[$i] = $slice;
        }
        
    }
    
    protected function get_colour($i,$n,$darklight=0) {
        //base hue: 0, max hue: 120
        $hue = $i / ($n - 1) * 120;
        $hue = pow($hue,1.5)/sqrt(120); // this biases the curve a little bit toward the red/yellow end
        if($darklight == -1) {
            $s = 1.0; $v = 0.8;
        } elseif ($darklight == 0) {
            $s = 0.9; $v = 0.9;
        } elseif ($darklight == 1) {
            $s = 0.5; $v = 1.0;
        }
        return $this->hsv_to_rgb($hue,$s,$v);
    }
    
    /**
     * @param float $h from 0 to 360
     * @param float $s from 0 to 1
     * @param float $v from 0 to 1
     */
    private function hsv_to_rgb($h,$s,$v) {
        //folowing the formulae found at http://en.wikipedia.org/wiki/HSV_color_space#Converting_to_RGB
        $c = $v * $s; //chroma
        $hp = $h / 60;
        

        if($hp < 0) {
            return array(0,0,0); //fucked the input, return black
        } elseif ($hp < 1) {
            $x = $c * $hp;
            $rgb = array( $c, $x, 0);
        } elseif ($hp < 2) {
            $x = $c * (1 - ($hp - 1));
            $rgb = array( $x, $c, 0);
        } elseif ($hp < 3) {
            $x = $c * ($hp - 2);
            $rgb = array( 0, $c, $x);
        } elseif ($hp < 4) {
            $x = $c * (1 - ($hp - 3));
            $rgb = array( 0, $x, $c);
        } elseif ($hp < 5) {
            $x = $c * ($hp - 4);
            $rgb = array( $x, 0, $c);
        } elseif ($hp <= 6) {
            $x = $c * (1 - ($hp - 5));
            $rgb = array( $c, 0, $x);
        }
        
        $m = $v - $c;
        foreach($rgb as $k => $v) {
            $rgb[$k] = $v + $m;
        }
        
        return $this->rgb_to_hex($rgb);
        
    }
    
    private function rgb_to_hex($rgb) {
        list($r,$g,$b) = $rgb;
        return sprintf('%02X%02X%02X',$r * 255,$g * 255,$b * 255);
    }
    
}

class workshep_calibration_report implements renderable {
    
    public $reviewers;
    
    public $examples;
    
    public $scores;
    
    public $options;
    
    function __construct(workshep $workshep, stdclass $options) {
        
        global $DB;
        
        //what we need: all of our reviewers (we don't care about submitters)
        //all of those users' assessments of their assigned example submissions
        //and all of their calibration scores (if they have been calculated)
        
        $reviewers = $workshep->get_potential_reviewers();
        $exemplars = $workshep->get_examples_for_manager();
        
        //For clarity, we're going to prefix all the example "grade" and "gradinggrade" in the examples
        //with "reference"
        
        foreach($exemplars as $k => $v) {
            $v->referenceassessmentid = $v->assessmentid;
            $v->referencegrade = $v->grade;
            $v->referencegradinggrade = $v->gradinggrade;
            unset($v->assessmentid);
            unset($v->grade);
            unset($v->gradinggrade);
        }
        
        
        list($userids, $params) = $DB->get_in_or_equal(array_keys($reviewers), SQL_PARAMS_NAMED);
        
        $userexamples = array();
        
        if ($workshep->numexamples > 0) {
            $where = "workshepid = :workshepid AND userid $userids";
            $params['workshepid'] = $workshep->id;
            $rslt = $DB->get_records_select("workshep_user_examples", $where, $params);
            foreach($rslt as $v) {
                $ex = $exemplars[$v->submissionid];
                $userexamples[$v->userid][$ex->id] = clone $ex;
            }
        } else {
            foreach($reviewers as $v) {
                foreach($exemplars as $ex) {
                    $userexamples[$v->id][$ex->id] = clone $ex;
                }
            }
        }

        // Prevent an unitialised variable warning.
        $scores = array();
        
        // Get user's example results
        if (empty($userexamples)) {
        	$this->examples = array();
        } else {
	        list($submissionids, $sparams) = $DB->get_in_or_equal(array_keys($exemplars), SQL_PARAMS_NAMED, 'sub');
	        list($reviewerids, $rparams) = $DB->get_in_or_equal(array_keys($userexamples), SQL_PARAMS_NAMED, 'usr');
        
	        $params = array_merge($sparams, $rparams);
        
	        $where = "submissionid $submissionids AND reviewerid $reviewerids AND weight = 0";
	        $rslt = $DB->get_records_select("workshep_assessments",$where,$params);
        
	        foreach($rslt as $v) {
	            $ex = $userexamples[$v->reviewerid][$v->submissionid];
	            $ex->grade = $v->grade;
	            $ex->gradinggrade = $v->gradinggrade;
	            $ex->feedbackauthor = $v->feedbackauthor;
	        }
        
        
	        // Finally get their calibration results
        
	        // We actually ask for these from the calibration plugin.
        
	        $calibration = $workshep->calibration_instance();
        
	        $scores = $calibration->get_calibration_scores();
        
	        $sortby = $options->sortby; $sorthow = $options->sorthow;
	        if (($sortby == 'lastname') || ($sortby == 'firstname')) {
	            uasort($reviewers, function ($a, $b) use ($sortby, $sorthow) {
	                if ($sorthow == 'ASC')
	                    return strcmp($a->$sortby, $b->$sortby);
	                else
	                    return strcmp($b->$sortby, $a->$sortby);
	            });
	        }
        
	        // We also get a moodle_url for each reviewer's grade breakdown
        
	        foreach($reviewers as $r) {
	            $url = $calibration->user_calibration_url($r->id);
	            if (!empty($url)) {
	                $r->calibrationlink = $url;
	            }
	        }
		}
		
        $this->reviewers = $reviewers;
        $this->examples = $userexamples;
        $this->scores = $scores;
        $this->options = $options;
        
    }
    
}

