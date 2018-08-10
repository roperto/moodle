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
 * This file defines a class with accumulative grading strategy logic
 *
 * @package    workshepform_accumulative
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(dirname(__FILE__)) . '/lib.php');  // interface definition
require_once($CFG->libdir . '/gradelib.php');           // to handle float vs decimal issues

/**
 * Server workshep files
 *
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool
 */
function workshepform_accumulative_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload, array $options=array()) {
    global $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, true, $cm);

    if ($filearea !== 'description') {
        return false;
    }

    $itemid = (int)array_shift($args); // the id of the assessment form dimension
    if (!$workshep = $DB->get_record('workshep', array('id' => $cm->instance))) {
        send_file_not_found();
    }

    if (!$dimension = $DB->get_record('workshepform_accumulative', array('id' => $itemid ,'workshepid' => $workshep->id))) {
        send_file_not_found();
    }

    // TODO now make sure the user is allowed to see the file
    // (media embedded into the dimension description)
    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/workshepform_accumulative/$filearea/$itemid/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }

    // finally send the file
    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Accumulative grading strategy logic.
 */
class workshep_accumulative_strategy implements workshep_strategy {

    /** @const default number of dimensions to show */
    const MINDIMS = 3;

    /** @const number of dimensions to add */
    const ADDDIMS = 2;

    /** @var workshep the parent workshep instance */
    protected $workshep;

    /** @var array definition of the assessment form fields */
    protected $dimensions = null;

    /** @var array options for dimension description fields */
    protected $descriptionopts;

    /**
     * Constructor
     *
     * @param workshep $workshep The workshep instance record
     * @return void
     */
    public function __construct(workshep $workshep) {
        $this->workshep         = $workshep;
        $this->dimensions       = $this->load_fields();
        $this->config           = $this->load_config();
        $this->descriptionopts  = array('trusttext' => true, 'subdirs' => false, 'maxfiles' => -1);
    }

    /**
     * Factory method returning an instance of an assessment form editor class
     *
     * @param $actionurl URL of form handler, defaults to auto detect the current url
     */
    public function get_edit_strategy_form($actionurl=null) {
        global $CFG;    // needed because the included files use it
        global $PAGE;

        require_once(dirname(__FILE__) . '/edit_form.php');

        $fields             = $this->prepare_form_fields($this->dimensions);
        $fields->config_algorithm = $this->config->additive;
        $nodimensions       = count($this->dimensions);
        $norepeatsdefault   = max($nodimensions + self::ADDDIMS, self::MINDIMS);
        $norepeats          = optional_param('norepeats', $norepeatsdefault, PARAM_INT);    // number of dimensions
        $noadddims          = optional_param('noadddims', '', PARAM_ALPHA);                 // shall we add more?
        if ($noadddims) {
            $norepeats += self::ADDDIMS;
        }

        // Append editor context to editor options, giving preference to existing context.
        $this->descriptionopts = array_merge(array('context' => $PAGE->context), $this->descriptionopts);

        // prepare the embeded files
        for ($i = 0; $i < $nodimensions; $i++) {
            // prepare all editor elements
            $fields = file_prepare_standard_editor($fields, 'description__idx_'.$i, $this->descriptionopts,
                $PAGE->context, 'workshepform_accumulative', 'description', $fields->{'dimensionid__idx_'.$i});
        }

        $customdata = array();
        $customdata['workshep'] = $this->workshep;
        $customdata['strategy'] = $this;
        $customdata['norepeats'] = $norepeats;
        $customdata['descriptionopts'] = $this->descriptionopts;
        $customdata['current']  = $fields;
        $attributes = array('class' => 'editstrategyform');

        return new workshep_edit_accumulative_strategy_form($actionurl, $customdata, 'post', '', $attributes);
    }

    /**
     * Save the assessment dimensions into database
     *
     * Saves data into the main strategy form table. If the record->id is null or zero,
     * new record is created. If the record->id is not empty, the existing record is updated. Records with
     * empty 'description' field are removed from database.
     * The passed data object are the raw data returned by the get_data().
     *
     * @uses $DB
     * @param stdClass $data Raw data returned by the dimension editor form
     * @return void
     */
    public function save_edit_strategy_form(stdclass $data) {
        global $DB, $PAGE;

        $workshepid = $data->workshepid;
        $norepeats  = $data->norepeats;

        $additive     = $data->config_algorithm;

        $data       = $this->prepare_database_fields($data);
        $records    = $data->accumulative;  // records to be saved into {workshepform_accumulative}
        $todelete   = array();              // dimension ids to be deleted

        if ($DB->record_exists('workshepform_accum_config', array('workshepid' => $this->workshep->id))) {
            $DB->set_field('workshepform_accum_config', 'additive', $additive, array('workshepid' => $this->workshep->id));
        } else {
            $record = new stdclass();
            $record->workshepid = $this->workshep->id;
            $record->additive   = $additive;
            $DB->insert_record('workshepform_accum_config', $record, false);
        }

        for ($i=0; $i < $norepeats; $i++) {
            $record = $records[$i];
            if (0 == strlen(trim($record->description_editor['text']))) {
                if (!empty($record->id)) {
                    // existing record with empty description - to be deleted
                    $todelete[] = $record->id;
                }
                continue;
            }
            if (empty($record->id)) {
                // new field
                $record->id         = $DB->insert_record('workshepform_accumulative', $record);
            } else {
                // exiting field
                $DB->update_record('workshepform_accumulative', $record);
            }
            // re-save with correct path to embeded media files
            $record = file_postupdate_standard_editor($record, 'description', $this->descriptionopts,
                                                      $PAGE->context, 'workshepform_accumulative', 'description', $record->id);
            $DB->update_record('workshepform_accumulative', $record);
        }
        $this->delete_dimensions($todelete);
    }

    /**
     * Factory method returning an instance of an assessment form
     *
     * @param moodle_url $actionurl URL of form handler, defaults to auto detect the current url
     * @param string $mode          Mode to open the form in: preview/assessment
     * @param stdClass $assessment  The current assessment
     * @param bool $editable
     * @param array $options
     */
    public function get_assessment_form(moodle_url $actionurl=null, $mode='preview', stdclass $assessment=null, $editable=true, $options=array()) {
        global $CFG;    // needed because the included files use it
        global $PAGE;
        global $DB;
        require_once(dirname(__FILE__) . '/assessment_form.php');

        $fields         = $this->prepare_form_fields($this->dimensions);
        $nodimensions   = count($this->dimensions);

        // rewrite URLs to the embeded files
        for ($i = 0; $i < $nodimensions; $i++) {
            $fields->{'description__idx_'.$i} = file_rewrite_pluginfile_urls($fields->{'description__idx_'.$i},
                'pluginfile.php', $PAGE->context->id, 'workshepform_accumulative', 'description', $fields->{'dimensionid__idx_'.$i});
        }

        if ('assessment' === $mode and !empty($assessment)) {
            // load the previously saved assessment data
            $grades = $this->get_current_assessment_data($assessment);
            $current = new stdclass();
            for ($i = 0; $i < $nodimensions; $i++) {
                $dimid = $fields->{'dimensionid__idx_'.$i};
                if (isset($grades[$dimid])) {
                    $current->{'gradeid__idx_'.$i}      = $grades[$dimid]->id;
                    $current->{'grade__idx_'.$i}        = $grades[$dimid]->grade;
                    $current->{'peercomment__idx_'.$i}  = $grades[$dimid]->peercomment;
                }
            }
        }

        // set up the required custom data common for all strategies
        $customdata['strategy'] = $this;
        $customdata['workshep'] = $this->workshep;
        $customdata['mode']     = $mode;
        $customdata['options']  = $options;

        // set up strategy-specific custom data
        $customdata['nodims']   = $nodimensions;
        $customdata['fields']   = $fields;
        $customdata['current']  = isset($current) ? $current : null;
        $attributes = array('class' => 'assessmentform accumulative');

        return new workshep_accumulative_assessment_form($actionurl, $customdata, 'post', '', $attributes, $editable);
    }

    /**
     * Saves the filled assessment
     *
     * This method processes data submitted using the form returned by {@link get_assessment_form()}
     *
     * @param stdClass $assessment Assessment being filled
     * @param stdClass $data       Raw data as returned by the assessment form
     * @return float|null          Raw grade (0.00000 to 100.00000) for submission as suggested by the peer
     */
    public function save_assessment(stdclass $assessment, stdclass $data) {
        global $DB;

        if (!isset($data->nodims)) {
            throw new coding_exception('You did not send me the number of assessment dimensions to process');
        }
        for ($i = 0; $i < $data->nodims; $i++) {
            $grade = new stdclass();
            $grade->id = $data->{'gradeid__idx_' . $i};
            $grade->assessmentid = $assessment->id;
            $grade->strategy = 'accumulative';
            $grade->dimensionid = $data->{'dimensionid__idx_' . $i};
            $grade->grade = $data->{'grade__idx_' . $i};
            $grade->peercomment = $data->{'peercomment__idx_' . $i};
            $grade->peercommentformat = FORMAT_MOODLE;
            if (empty($grade->id)) {
                // new grade
                $grade->id = $DB->insert_record('workshep_grades', $grade);
            } else {
                // updated grade
                $DB->update_record('workshep_grades', $grade);
            }
        }
        return $this->update_peer_grade($assessment);
    }

    /**
     * Has the assessment form been defined and is ready to be used by the reviewers?
     *
     * @return boolean
     */
    public function form_ready() {
        if (count($this->dimensions) > 0) {
            return true;
        }
        return false;
    }

    /**
     * @see parent::get_assessments_recordset()
     */
    public function get_assessments_recordset($restrict=null,$include_examples=false) {
        global $DB;

        $sql = 'SELECT s.id AS submissionid,
                       a.id AS assessmentid, a.weight AS assessmentweight, a.reviewerid, a.gradinggrade,
                       g.dimensionid, g.grade, g.peercomment
                  FROM {workshep_submissions} s
                  JOIN {workshep_assessments} a ON (a.submissionid = s.id)
                  JOIN {workshep_grades} g ON (g.assessmentid = a.id AND g.strategy = :strategy)
                 WHERE s.workshepid=:workshepid'; // to be cont.
        $params = array('workshepid' => $this->workshep->id, 'strategy' => $this->workshep->strategy);

        if ($include_examples == false) {
            $sql .= " AND s.example=0";
        }
        
        if (is_null($restrict)) {
            // update all users - no more conditions
        } elseif (!empty($restrict)) {
            list($usql, $uparams) = $DB->get_in_or_equal($restrict, SQL_PARAMS_NAMED);
            $sql .= " AND a.reviewerid $usql";
            $params = array_merge($params, $uparams);
        } else {
            throw new coding_exception('Empty value is not a valid parameter here');
        }

        $sql .= ' ORDER BY s.id'; // this is important for bulk processing

        return $DB->get_recordset_sql($sql, $params);
    }

    /**
     * @see parent::get_dimensions_info()
     */
    public function get_dimensions_info() {
        global $DB;

        $sql = 'SELECT d.id, d.grade, d.weight, d.description AS title, s.scale
                  FROM {workshepform_accumulative} d
             LEFT JOIN {scale} s ON (d.grade < 0 AND -d.grade = s.id)
                 WHERE d.workshepid = :workshepid';
        $params = array('workshepid' => $this->workshep->id);
        $dimrecords = $DB->get_records_sql($sql, $params);
        $diminfo = array();
        foreach ($dimrecords as $dimid => $dimrecord) {
            $diminfo[$dimid] = new stdclass();
            $diminfo[$dimid]->id = $dimid;
            $diminfo[$dimid]->weight = $dimrecord->weight;
            $diminfo[$dimid]->title = $dimrecord->title;
            if ($dimrecord->grade < 0) {
                // the dimension uses a scale
                $diminfo[$dimid]->min = 1;
                $diminfo[$dimid]->max = count(explode(',', $dimrecord->scale));
            } else {
                // the dimension uses points
                $diminfo[$dimid]->min = 0;
                $diminfo[$dimid]->max = grade_floatval($dimrecord->grade);
            }
        }
        return $diminfo;
    }

    /**
     * Is a given scale used by the instance of workshep?
     *
     * @param int $scaleid id of the scale to check
     * @param int|null $workshepid id of workshep instance to check, checks all in case of null
     * @return bool
     */
    public static function scale_used($scaleid, $workshepid=null) {
        global $DB;

        $conditions['grade'] = -$scaleid;
        if (!is_null($workshepid)) {
            $conditions['workshepid'] = $workshepid;
        }
        return $DB->record_exists('workshepform_accumulative', $conditions);
    }

    /**
     * Delete all data related to a given workshep module instance
     *
     * @see workshep_delete_instance()
     * @param int $workshepid id of the workshep module instance being deleted
     * @return void
     */
    public static function delete_instance($workshepid) {
        global $DB;

        $DB->delete_records('workshepform_accumulative', array('workshepid' => $workshepid));
        $DB->delete_records('workshepform_accum_config', array('workshepid' => $workshepid));
    }

    ////////////////////////////////////////////////////////////////////////////////
    // Internal methods                                                           //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * Loads the fields of the assessment form currently used in this workshep
     *
     * @return array definition of assessment dimensions
     */
    protected function load_fields() {
        global $DB;

        $sql = 'SELECT *
                  FROM {workshepform_accumulative}
                 WHERE workshepid = :workshepid
                 ORDER BY sort';
        $params = array('workshepid' => $this->workshep->id);

        return $DB->get_records_sql($sql, $params);
    }

    protected function load_config() {
        global $DB;

        if (!$config = $DB->get_record('workshepform_accum_config', array('workshepid' => $this->workshep->id), 'additive')) {
            $config = (object)array('additive' => 0);
        }
        return $config;
    }

    /**
     * Maps the dimension data from DB to the form fields
     *
     * @param array $raw Array of raw dimension records as returned by {@link load_fields()}
     * @return array Array of fields data to be used by the mform set_data
     */
    protected function prepare_form_fields(array $raw) {

        $formdata = new stdclass();
        $key = 0;
        foreach ($raw as $dimension) {
            $formdata->{'dimensionid__idx_' . $key}             = $dimension->id;
            $formdata->{'description__idx_' . $key}             = $dimension->description;
            $formdata->{'description__idx_' . $key.'format'}    = $dimension->descriptionformat;
            $formdata->{'grade__idx_' . $key}                   = $dimension->grade;
            $formdata->{'weight__idx_' . $key}                  = $dimension->weight;
            $key++;
        }
        return $formdata;
    }

    /**
     * Deletes dimensions and removes embedded media from its descriptions
     *
     * todo we may check that there are no assessments done using these dimensions and probably remove them
     *
     * @param array $masterids
     * @return void
     */
    protected function delete_dimensions(array $ids) {
        global $DB, $PAGE;

        $fs = get_file_storage();
        foreach ($ids as $id) {
            if (!empty($id)) {   // to prevent accidental removal of all files in the area
                $fs->delete_area_files($PAGE->context->id, 'workshepform_accumulative', 'description', $id);
            }
        }
        $DB->delete_records_list('workshepform_accumulative', 'id', $ids);
    }

    /**
     * Prepares data returned by {@link workshep_edit_accumulative_strategy_form} so they can be saved into database
     *
     * It automatically adds some columns into every record. The sorting is
     * done by the order of the returned array and starts with 1.
     * Called internally from {@link save_edit_strategy_form()} only. Could be private but
     * keeping protected for unit testing purposes.
     *
     * @param stdClass $raw Raw data returned by mform
     * @return array Array of objects to be inserted/updated in DB
     */
    protected function prepare_database_fields(stdclass $raw) {
        global $PAGE;

        $cook               = new stdclass(); // to be returned
        $cook->accumulative = array();        // records to be stored in {workshepform_accumulative}

        for ($i = 0; $i < $raw->norepeats; $i++) {
            $cook->accumulative[$i]                     = new stdclass();
            $cook->accumulative[$i]->id                 = $raw->{'dimensionid__idx_'.$i};
            $cook->accumulative[$i]->workshepid         = $this->workshep->id;
            $cook->accumulative[$i]->sort               = $i + 1;
            $cook->accumulative[$i]->description_editor = $raw->{'description__idx_'.$i.'_editor'};
            $cook->accumulative[$i]->grade              = $raw->{'grade__idx_'.$i};
            $cook->accumulative[$i]->weight             = $raw->{'weight__idx_'.$i};
        }
        return $cook;
    }

    /**
     * Returns the list of current grades filled by the reviewer indexed by dimensionid
     *
     * @param stdClass $assessment Assessment record
     * @return array [int dimensionid] => stdclass workshep_grades record
     */
    protected function get_current_assessment_data(stdclass $assessment) {
        global $DB;

        if (empty($this->dimensions)) {
            return array();
        }
        list($dimsql, $dimparams) = $DB->get_in_or_equal(array_keys($this->dimensions), SQL_PARAMS_NAMED);
        // beware! the caller may rely on the returned array is indexed by dimensionid
        $sql = "SELECT dimensionid, wg.*
                  FROM {workshep_grades} wg
                 WHERE assessmentid = :assessmentid AND strategy= :strategy AND dimensionid $dimsql";
        $params = array('assessmentid' => $assessment->id, 'strategy' => 'accumulative');
        $params = array_merge($params, $dimparams);

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Aggregates the assessment form data and sets the grade for the submission given by the peer
     *
     * @param stdClass $assessment Assessment record
     * @return float|null          Raw grade (from 0.00000 to 100.00000) for submission as suggested by the peer
     */
    protected function update_peer_grade(stdclass $assessment) {
        $grades     = $this->get_current_assessment_data($assessment);
        $suggested  = $this->calculate_peer_grade($grades);
        if (!is_null($suggested)) {
            $this->workshep->set_peer_grade($assessment->id, $suggested);
        }
        return $suggested;
    }

    /**
     * Calculates the aggregated grade given by the reviewer
     *
     * @param array $grades Grade records as returned by {@link get_current_assessment_data}
     * @uses $this->dimensions
     * @return float|null   Raw grade (from 0.00000 to 100.00000) for submission as suggested by the peer
     */
    protected function calculate_peer_grade(array $grades) {

        if (empty($grades)) {
            return null;
        }
        $sumgrades  = 0;
        $sumweights = 0;
        foreach ($grades as $grade) {
            $dimension = $this->dimensions[$grade->dimensionid];
            if ($dimension->weight < 0) {
                throw new coding_exception('Negative weights are not supported any more. Something is wrong with your data');
            }
            if (grade_floats_equal($dimension->weight, 0) or grade_floats_equal($dimension->grade, 0)) {
                // does not influence the final grade
                continue;
            }

            if ($this->config->additive) {
                if ($dimension->grade >= 0) {
                    $sumgrades  += $grade->grade * $dimension->weight * 100;
                    $sumweights += $dimension->weight * $dimension->grade;
                } // todo - handle scales
            } else {
                if ($dimension->grade < 0) {
                    // this is a scale
                    $scaleid    = -$dimension->grade;
                    $sumgrades  += $this->scale_to_grade($scaleid, $grade->grade) * $dimension->weight * 100;
                    $sumweights += $dimension->weight;
                } else {
                    // regular grade
                    $sumgrades  += ($grade->grade / $dimension->grade) * $dimension->weight * 100;
                    $sumweights += $dimension->weight;
                }
            }
        }

        if ($sumweights === 0) {
            return 0;
        }
        return grade_floatval($sumgrades / $sumweights);
    }

    /**
     * Convert scale grade to numerical grades
     *
     * In accumulative grading strategy, scales are considered as grades from 0 to M-1, where M is the number of scale items.
     *
     * @throws coding_exception
     * @param string $scaleid Scale identifier
     * @param int    $item    Selected scale item number, numbered 1, 2, 3, ... M
     * @return float
     */
    protected function scale_to_grade($scaleid, $item) {
        global $DB;

        /** @var cache of numbers of scale items */
        static $numofscaleitems = array();

        if (!isset($numofscaleitems[$scaleid])) {
            $scale = $DB->get_field('scale', 'scale', array('id' => $scaleid), MUST_EXIST);
            $items = explode(',', $scale);
            $numofscaleitems[$scaleid] = count($items);
            unset($scale);
            unset($items);
        }

        if ($numofscaleitems[$scaleid] <= 1) {
            throw new coding_exception('Invalid scale definition, no scale items found');
        }

        if ($item <= 0 or $numofscaleitems[$scaleid] < $item) {
            throw new coding_exception('Invalid scale item number');
        }

        return ($item - 1) / ($numofscaleitems[$scaleid] - 1);
    }
}
