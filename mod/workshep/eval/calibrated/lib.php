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
 * This file defines interface of all grading evaluation classes
 *
 * @package    mod
 * @subpackage workshep
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(dirname(__FILE__)) . '/lib.php');  // interface definition
require_once($CFG->libdir . '/gradelib.php');

/**
 * Defines all methods that grading evaluation subplugins has to implement
 *
 * @todo the final interface is not decided yet as we have only one implementation so far
 */
class workshep_calibrated_evaluation extends workshep_evaluation {

    /** @var workshep the parent workshep instance */
    protected $workshep;

    /** @var the recently used settings in this workshep */
    protected $settings;
	
	private $examples;
	
	public static $grading_curves = array(9 => 4.0, 8 => 3.0, 7 => 2.0, 6 => 1.5, 5 => 1.0, 4 => 0.666, 3 => 0.5, 2 => 0.333, 1 => 0.25, 0 => 0);
	
    public function __construct(workshep $workshep) {
        global $DB;
        $this->workshep = $workshep;
        $this->settings = $DB->get_record('workshepeval_calibrated', array('workshepid' => $this->workshep->id));
		$this->examples = array();
    }

    public function update_grading_grades(stdclass $settings, $restrict=null) {
		
        global $DB;
        
        $settings->adjustgrades = (int)!empty($settings->adjustgrades);
        
        // Remember the recently used settings for this workshep.
        if (empty($this->settings)) {
            $record = new stdclass();
            $record->workshepid = $this->workshep->id;
            $record->adjustgrades = $settings->adjustgrades;
            $DB->insert_record('workshepeval_calibrated', $record);
        } elseif ($this->settings->adjustgrades != $settings->adjustgrades) {
            $DB->set_field('workshepeval_calibrated', 'adjustgrades', $settings->adjustgrades,
                    array('workshepid' => $this->workshep->id));
        }
        
		$calibration_scores = $this->workshep->calibration_instance()->get_calibration_scores();

		$sql = <<<SQL
		        SELECT a.id, a.reviewerid, a.grade
		        FROM {workshep_assessments} a, {workshep_submissions} s 
		        WHERE a.submissionid = s.id AND s.workshepid = :workshepid AND s.example = 0 AND a.weight > 0;
SQL;
		
		$assessments = $DB->get_records_sql($sql, array("workshepid" => $this->workshep->id));
		
		foreach($assessments as $a) {
            $record = new stdclass();
            $record->id = $a->id;
            if (!is_null($a->grade) and isset($calibration_scores[$a->reviewerid])) {
                    $record->gradinggrade = grade_floatval($calibration_scores[$a->reviewerid]);
            } else {
                $record->gradinggrade = 0;
            }
            
            $DB->update_record('workshep_assessments', $record, false);  // bulk operations expected
        }
	}
    
    private function get_reference_assessments() {
        $grader = $this->workshep->grading_strategy_instance();
        $diminfo = $grader->get_dimensions_info();
        
		// cache the reference assessments
		$references = $this->workshep->get_examples_for_manager();
		$calibration_scores = array();
        
        //fetch grader recordset for examples
        $userkeys = array();
        foreach($references as $r) {
            $userkeys[$r->authorid] = $r->authorid;
        }

        $exemplars = $grader->get_assessments_recordset($userkeys,true);
        
        foreach($exemplars as $r) {
            if (array_key_exists($r->submissionid,$references)) {
                $references[$r->submissionid]->diminfo[$r->dimensionid] = $r->grade;
            }
        }
        
        return $references;
    }
    
    public function update_submission_grades(stdclass $settings) {
    
	
		global $DB;
        
        if ($settings->adjustgrades) {

    		//fetch all the assessments for all the submissions in this 
    		$sql = "SELECT a.id, a.submissionid, a.weight, a.grade, a.gradinggrade, a.gradinggradeover, s.title
    				FROM {workshep_submissions} s, {workshep_assessments} a
    				WHERE s.workshepid = {$this->workshep->id}
    					AND s.example = 0
    					AND a.submissionid = s.id
    				ORDER BY a.submissionid";
		
    		$records = $DB->get_recordset_sql($sql);
		
    		$weighted_grades = array();
    		$total_weight = 0;
    		$current_submission = $records->current();
    		foreach($records as $v) {
			
    			//this is actually "last": if the submissionid has changed, then we're on to a new submission.
    			//it's kind of a stupid way of doing it but unfortunately there's no seeking in moodle recordsets, so
    			//we can't get the submissionid of the next record to check if this is the last one
    			if (($v->submissionid != $current_submission->submissionid)) {

    				$this->update_submission_grade($current_submission, $weighted_grades, $total_weight);
				
    				//reset our vital statistics
    				$weighted_grades = array();
    				$total_weight = 0;
    				$current_submission = $v;
				
    			}
			
    			//just add the submission to the queue. we do all the work in the above if statement.
    			$gradinggrade = is_null($v->gradinggradeover) ? $v->gradinggrade : $v->gradinggradeover;
    			$weighted_grade = $v->grade * $v->weight * $gradinggrade;
    			$weighted_grades[] = $weighted_grade;
    			$total_weight += $gradinggrade * $v->weight;
    		}
		
    		//do it for the last one
    		$this->update_submission_grade($current_submission, $weighted_grades, $total_weight);
        
    		$records->close();
            
        }
    }
	
	private function update_submission_grade($submission, $weighted_grades, $total_weight) {
		
		global $DB;
		
		//perform weighted average
        if ($total_weight > 0) {
    		$weighted_avg = array_sum($weighted_grades) / $total_weight;
        } else {
            $weighted_avg = null;
        }
        
			
		$DB->set_field('workshep_submissions','grade',$weighted_avg,array("id" => $submission->submissionid));
		
	}
	
	public function get_settings_form(moodle_url $actionurl=null) {
        global $CFG;    // needed because the included files use it
        global $DB;
        require_once(dirname(__FILE__) . '/settings_form.php');

        $customdata['workshep'] = $this->workshep;
        $customdata['current'] = $this->settings;
        $customdata['methodname'] = 'calibrated';
        $attributes = array('class' => 'evalsettingsform calibrated');

        return new workshep_calibrated_evaluation_settings_form($actionurl, $customdata, 'post', '', $attributes);
	}
    
	public function get_settings() {
		return $this->settings;
	}
	
	private function get_no_competent_reviewers() {

    	
    	global $DB;
    	
    	$sql = 'SELECT a.id, a.submissionid, a.reviewerid, s.authorid, a.gradinggrade, a.weight
    	          FROM {workshep_assessments} a
    	    INNER JOIN {workshep_submissions} s ON (a.submissionid = s.id)
    	         WHERE s.example = 0 AND s.workshepid = :workshepid';
    	$params = array('workshepid' => $this->workshep->id);
    	
    	$allocations = $DB->get_records_sql($sql, $params);
    	
    	$submission_scores = array();
    	foreach ($allocations as $a) {
        	if (!is_null($a->gradinggrade) and ($a->weight > 0)) {
            	if(!isset($submission_scores[$a->submissionid])) {
                	$submission_scores[$a->submissionid] = 0;
                }
                $submission_scores[$a->submissionid] += $a->gradinggrade;
            }
        }
        
        $no_competent_reviewers = array();
        foreach ($submission_scores as $sid => $value) {
            if ($value == 0) {
                $no_competent_reviewers[] = $sid;
            }
        }
        
        if (!empty($no_competent_reviewers)) {
            list($select, $params) = $DB->get_in_or_equal($no_competent_reviewers);        
            return $DB->get_records_select("workshep_submissions","id $select",$params,"id,title");
        }
        
        return array();
    	
    }
    
    public function has_messages() {
        $rslt = $this->get_no_competent_reviewers();
        
        if (count($rslt)) {
            return true;
        }
        return false;
    }
    
    public function display_messages() {
        //is this a hilariously incorrect way to do this?
        global $output, $PAGE;
        echo $output->box_start('no-competent-reviewers');

        echo get_string('nocompetentreviewers','workshepeval_calibrated');

        $rslt = $this->get_no_competent_reviewers();

        echo html_writer::start_tag('ul');
        foreach ($rslt as $k => $v) {
            echo html_writer::start_tag('li');
            $url = new moodle_url('/mod/workshep/submission.php',
                                  array('cmid' => $PAGE->context->instanceid, 'id' => $k));
            echo html_writer::link($url, $v->title, array('class'=>'title'));
            echo html_writer::end_tag('li');
        }
        echo html_writer::end_tag('ul');
        
        echo $output->box_end();
    }
	
    public static function delete_instance($workshepid) {
		//TODO
	}
    
    public function prepare_explanation_for_assessor($userid) {
        $grader = $this->workshep->grading_strategy_instance();
        $diminfo = $grader->get_dimensions_info();
        $exxx = $grader->get_assessments_recordset(array($userid),true);
        $references = $this->get_reference_assessments();
        $options = array(
            'gradedecimals' => $this->workshep->gradedecimals,
            'accuracy' => $this->settings->comparison,
            'consistency' => $this->settings->consistency,
            'finalscoreoutof' => $this->workshep->gradinggrade
        );
        
        return new workshep_calibrated_evaluation_explanation($userid, $exxx, $references, $diminfo, $options);
    }
}

class workshep_calibrated_evaluation_explanation implements renderable {
    
    public function __construct($user, $examples, $references, $diminfo, $options = array()) {
        $this->user = $user;
        $this->examples = array();
        foreach($examples as $k => $v) {
            $this->examples[$v->submissionid][$v->dimensionid] = $v;
        }
        $this->references = array();
        foreach($references as $k => $v) {
            $this->references[$k] = $v;
        }
        $this->diminfo = $diminfo;
        foreach(array('gradedecimals' => 0, 'accuracy' => 5, 'consistency' => 5, 'finalscoreoutof' => null) as $k => $default) {
            $this->$k = empty($options[$k]) ? $default : $options[$k];
        }
        
        //make the table
        
        $reference_values = array();
        
        foreach($this->references as $k => $a) {
            
            foreach($a->diminfo as $dimid => $mydimval) {
                if (!empty($this->examples[$k])) {
                    $theirdimval = $this->examples[$k][$dimid];
                    $dim = $diminfo[$dimid];
                
                    $diff = abs( $mydimval - $theirdimval->grade ); 
                    $reference_values[$k][$dimid] = array($diminfo[$dimid], $theirdimval->grade, $mydimval, $diff);
                }
            }
        }
        
        $this->reference_values = $reference_values;
        
        //calculate the needed values
        
        $abs_devs = array();
        foreach($this->reference_values as $k => $v) {
            foreach($v as $i => $a) {
                $dim = $a[0];
                $diff = $a[3];
                $abs_devs[] = $this->normalize_grade($dim,$diff);
            }
        }
        
        $this->raw_average = array_sum($abs_devs) / count($abs_devs);
        $x = 100 - $this->raw_average;
        
        $grading_curve = workshep_calibrated_evaluation::$grading_curves[$this->accuracy];
        
        
        $x /= 100;
        if ($grading_curve >= 1) {
            $scaled_average = 1 - pow(1-$x, $grading_curve);
        } else {
            $scaled_average = pow($x, 1 / $grading_curve);
        }
        $scaled_average *= 100;
        
        $this->scaled_average = $scaled_average;
        
        $mean = array_sum($abs_devs) / count($abs_devs);
        $numerator = 0; //top half of the MAD fraction
        foreach($abs_devs as $z) {
            $numerator += abs($z - $mean);
        }
        $mad = $numerator / count($abs_devs);
        
        // $mad /= 50;
        // if ($mad < 0.01) $mad = 0;
        // if ($mad > 1) $mad = 1;
        // $mad = 1 - $mad;
        
        $this->mad = $mad;
        
        $consistency_curve = workshep_calibrated_evaluation::$grading_curves[9 - $this->consistency];
        
        $consistency_multiplier = $consistency_curve * (1 - ($mad / 100)) - $consistency_curve + 1;
        
        $this->consistency_multiplier = $consistency_multiplier;
        
        $this->final_score = $consistency_multiplier * $this->scaled_average;
        
        if ($this->final_score > 100) $this->final_score = 100;
        if ($this->final_score < 0) $this->final_score = 0;
    }
    
    public function normalize_grade($dim,$grade) {
        //todo: weight? is weight a factor here? probably should be...
        $dimmin = $dim->min;
        $dimmax = $dim->max;
        if ($dimmin == $dimmax) {
            return grade_floatval($dimmax);
        } else {
            return grade_floatval(($grade - $dimmin) / ($dimmax - $dimmin) * 100);
        }
    }
    
}
