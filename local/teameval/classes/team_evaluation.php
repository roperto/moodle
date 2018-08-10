<?php

namespace local_teameval;

/**
 * This is about all you have to call from your mod plugin to show teameval
 */

use renderable;
use core_plugin_manager;
use stdClass;
use context_module;
use context;
use coding_exception;
use local_searchable\searchable;
use moodle_url;
use cache;

define(__NAMESPACE__ . '\REPORT_PLUGIN_PREFERENCE', 'local_teameval_report_plugin');

define(__NAMESPACE__ . '\RELEASE_ALL', 0);
define(__NAMESPACE__ . '\RELEASE_GROUP', 1);
define(__NAMESPACE__ . '\RELEASE_USER', 2);

define(__NAMESPACE__ . '\FEEDBACK_RESCINDED', -1);
define(__NAMESPACE__ . '\FEEDBACK_UNSET', 0);
define(__NAMESPACE__ . '\FEEDBACK_APPROVED', 1);

define(__NAMESPACE__ . '\LOCKED_REASON_VISIBLE', -1);
define(__NAMESPACE__ . '\LOCKED_REASON_MARKED', -2);

// TODO
// This should be in its own class in /classes [DONE]
// In fact it should be a family of classes. There's way too much in here.
// At a first look, these things need to be factored out into controllers:
// questionnaire_controller (handling the questions)
// reset_controller (handling deletion and reset)
// evaluation_controller (handling the evaluators and grade updates)
// release_controller (handling mark release)
// rescind_controller (handling feedback approve/rescind)
// 
// Also all those namespaced constants should probably be class constants.

class team_evaluation {

    protected $id;

    protected $cm;

    protected $context;

    protected $evalcontext;

    protected $settings;

    private static $groupcache = [];

    protected $releases;

    protected $evaluator;

    // caches scores from evaluator. shouldn't change over the lifetime of the team_evaluation object.
    protected $_scores;

    // Note that this function will create & start a team evaluation if it does not already exist
    public static function from_cmid($cmid) {

        global $DB;

        $id = $DB->get_field('teameval', 'id', ['cmid' => $cmid]);

        return new team_evaluation($id, $cmid);

    }

    public static function new_with_contextid($contextid) {
        return new team_evaluation(0, null, $contextid);
    }

    public static function exists($id, $cmid = null, $contextid = null) {
        global $DB;
        if ($cmid != null) {
            return $DB->record_exists('teameval', ['cmid' => $cmid]);
        } else if ($contextid != null) {
            return $DB->record_exists('teameval', ['contextid' => $cmid]);
        }
        return $DB->record_exists('teameval', ['id' => $id]);
    }

    /**
     * When creating a teameval for the first time, pass in the cmid or the contextid
     * You should only ever call the constructor with cmid or contextid set from
     * within this class. PHP doesn't support constructor overloading so I can't force
     * that, but if you've only got a cmid or contextid use from_cmid or new_with_contextid.
     */
    public function __construct($id, $cmid = null, $contextid = null) {

        $this->id = $id;

        if (empty($id)) {

            if (empty($cmid) && empty($contextid)) {
                throw new coding_exception("Either id, cmid or contextid must be set.");
            }

            if ($cmid) {
                $this->cm = get_course_and_cm_from_cmid($cmid)[1];
                $this->context = context_module::instance($cmid);
            } else if ($contextid) {
                $this->context = context::instance_by_id($contextid);
            }

        }

        $this->get_settings();
    
    }

    public function get_evaluation_context() {
        global $CFG;

        // if this is a template, there's no evaluation context
        if (! isset($this->cm)) {
            return null;
        }

        if (! isset($this->evalcontext)) {
            $cache = cache::make('local_teameval', 'evalcontext');
            $this->evalcontext = $cache->get($this->cm->id);
            if (empty($this->evalcontext)) {
                $this->evalcontext = evaluation_context::context_for_module($this->cm);
                $cache->set($this->cm->id, $this->evalcontext);
            }
        }

        return $this->evalcontext;
    }

    /**
     * Gets the default settings for team evaluation. I want to explain why evaluation is enabled by
     * default. There have been a lot of times during development of this plugin when I have had to
     * check whether or not teameval is enabled on a given module, and nearly always the correct solution
     * would not have been to instantiate a teameval and check ->enabled. For starters, instiatiating 
     * a team evaluation can have significant side effects, some of which are unknown to the plugin
     * itself. But mostly, when you need to check if team evaluation is enabled, you probably want
     * to ask an evaluation context, not a team evaluation. The eval context is the object responsible 
     * for the module, and it's the one you should be talking to about module-side settings.
     * 
     * Basically, when you call new team_evaluation(), you know the ONLY reason it is disabled is if
     * the user disabled it deliberately.
     * 
     * @return type
     */
    protected static function default_settings() {

        //todo: these should probably be site-wide settings

        $settings = new stdClass;
        $settings->enabled = true;
        $settings->public = false;
        $settings->autorelease = true;
        $settings->self = true;
        $settings->fraction = 0.5;
        $settings->noncompletionpenalty = 0.1;
        $settings->deadline = null;
        $settings->title = "New Template";

        return $settings;
    }

    public function get_settings()
    {
    
        global $DB;

        $cache = cache::make('local_teameval', 'settings');

        // initialise settings if they're not already
        if (!isset($this->settings)) {

            // retrieve from MUC or from the database
            if (isset($this->id)) {
                $this->settings = $cache->get($this->id);
                if (empty($this->settings)) {
                    $this->settings = $DB->get_record('teameval', array('id' => $this->id));
                }
            }
            
            if ($this->settings === false) {
                $settings = team_evaluation::default_settings();
                if (isset($this->cm)) {
                    $settings->cmid = $this->cm->id;
                    unset($settings->title); // real teamevals don't have titles
                } else if (isset($this->context)) {
                    $settings->contextid = $this->context->id;
                    $settings->title = $this->available_title($settings->title);
                } else {
                    throw new coding_exception("Team evaluation does not exist.");
                }
                
                $this->id = $DB->insert_record('teameval', $settings);

                $this->settings = $settings;
            } else {

                if (!empty($this->settings->cmid)) {
                    $this->cm = get_course_and_cm_from_cmid($this->settings->cmid)[1];
                    $this->context = context_module::instance($this->settings->cmid);
                } else if (!empty($this->settings->contextid)) {
                    $this->context = context::instance_by_id($this->settings->contextid);
                }

                // for reasons I cannot possibly understand
                // literally every numeric type comes back as a string
                // let's fix that
                $this->settings->enabled = (bool)$this->settings->enabled;
                $this->settings->public = (bool)$this->settings->public;
                $this->settings->autorelease = (bool)$this->settings->autorelease;
                $this->settings->self = (bool)$this->settings->self;
                $this->settings->fraction = (float)$this->settings->fraction;
                $this->settings->noncompletionpenalty = (float)$this->settings->noncompletionpenalty;
                if(!is_null($this->settings->deadline)) {
                    $this->settings->deadline = (int)$this->settings->deadline;
                }
            }

            $cache->set($this->id, $this->settings);

            // these aren't really part of the settings
            unset($this->settings->id);
            unset($this->settings->cmid);
            unset($this->settings->contextid);
        }

        // don't return our actual settings object, else it could be updated behind our back
        $s = clone $this->settings;
        return $s;
    }

    public function update_settings($settings) {
        global $DB;

        //fetch settings if they're not set
        $this->get_settings();

        foreach(['enabled', 'public', 'self', 'autorelease', 'fraction', 'noncompletionpenalty', 'deadline', 'title'] as $i) {
            if (isset($settings->$i)) {
                
                // validation
                switch($i) {
                    case 'title':
                        $settings->title = $this->available_title($settings->title);
                        break;
                }

                $this->settings->$i = $settings->$i;
            }
        }

        // insert that bad boy
        $record = clone $this->settings;
        $record->id = $this->id;
        
        $DB->update_record('teameval', $record);
        
        // set contextid if cm is empty, set cmid if cm is full
        $record->contextid = empty($this->cm) ? $this->context->id : null;
        $record->cmid = empty($this->cm) ? null : $this->cm->id;

        $cache = cache::make('local_teameval', 'settings');
        $cache->set($this->id, $record);

        // if this is a template or a public questionnaire
        if (($this->cm == null) || ($this->settings->public)) {
            $this->update_searchable();
        }

        // if you've changed a setting that could potentiall change grades
        // we need to trigger a grade update
        if (isset($settings->fraction) || isset($settings->noncompletionpenalty)) {
            $this->get_evaluation_context()->trigger_grade_update();
        }

        return $settings;

    }

    public function get_context() {
        return $this->context;
    }

    public function get_coursemodule() {
        if (isset($this->cm)) {
            return $this->cm;
        }
        return null;
    }

    public function get_title() {
        if (isset($this->cm)) {
            return $this->cm->name;
        } else if (isset($this->settings->title)) {
            return $this->settings->title;
        } else {
            return $this->get_context()->get_context_name();
        }
    }

    public function available_title($title) {
        global $DB;

        // if you haven't changed the title, then it's definitely available
        if (isset($this->settings->title) && ($title == $this->settings->title)) {
            return $title;
        }

        $contextid = $this->get_context()->id;

        if ($DB->record_exists('teameval', ['cmid' => null, 'contextid' => $contextid, 'title' => $title])) {
            $like = $DB->sql_like('title', '?');
            $strip = preg_match('/(.*)\s[\d+]/', $title, $matches);
            if ($strip) {
                $title = $matches[1];
            }
            $records = $DB->get_records_select('teameval', $like . ' AND contextid = ? AND id != ?', [$title . '%', $contextid, $this->id], '', 'id, title');
            $titles = [];
            foreach($records as $r) {
                $titles[] = $r->title;
            }

            // add numbers to the end of the title until it doesn't match one of the titles
            $i = 2;
            while(in_array("$title $i", $titles)) {
                $i++;
            }
            return "$title $i";
        }
        return $title;
    }

    public function __get($k) {
        switch($k) {
            case 'id':
                return $this->id;
            default:
                throw new coding_exception("Undefined property $k on class team_evaluation.");
        }
    }

    // These functions are designed to be called from question subplugins

    /**
     * Ask teameval if a user should be allowed to update a question. Must be called before
     * update_question as the transaction returned from this function must be passed to
     * update_question.
     * 
     * @param string $type The question subplugin type 
     * @param int $id The question ID. 0 if new question.
     * @param int $userid The ID of the user trying to update this question
     * @return moodle_transaction|null Transaction if allowed, or null if not allowed
     */
    public function should_update_question($type, $id, $userid) {
        global $DB;

        if (has_capability('local/teameval:createquestionnaire', $this->context, $userid)) {
            $transaction = $DB->start_delegated_transaction();
            return $transaction;    
        }

        return null;
    }

    /**
     * Update teameval's internal question table. You must pass a transaction returned from
     * should_update_question.
     * 
     * @param moodle_transaction $transaction The transaction returned from should_update_question
     * @param string $type The question subplugin type
     * @param int $id The question ID
     * @param int $ordinal The position of the question in order. This is passed to the save handler.
     */
    public function update_question($transaction, $type, $id, $ordinal) {
        global $DB;

        $record = $DB->get_record("teameval_questions", array("teamevalid" => $this->id, "qtype" => $type, "questionid" => $id));
        if ($record) {
            $record->ordinal = $ordinal;
            $DB->update_record("teameval_questions", $record);
        } else {
            $record = new stdClass;
            $record->teamevalid = $this->id;
            $record->qtype = $type;
            $record->questionid = $id;
            $record->ordinal = $ordinal;
            $DB->insert_record("teameval_questions", $record);
        }

        $transaction->allow_commit();
    }

    /**
     * Ask teameval if a user should be allowed to delete a question. Must be called before
     * delete_question. The transaction returned from this funciton must be passed to delete_question.
     * @param string $type The question subplugin type
     * @param int $id The question ID
     * @param int $userid The user ID
     * @return moodle_transaction|null A transaction if allowed, else null
     */
    public function should_delete_question($type, $id, $userid) {
        global $DB;

        if (has_capability('local/teameval:createquestionnaire', $this->context, $userid)) {
            $transaction = $DB->start_delegated_transaction();
            return $transaction;    
        }

        return null;
    }

    /**
     * Delete the question from teameval's internal question table. Must be passed a transaction
     * started in should_delete_question.
     * @param moodle_transaction $transaction The transaction from should_delete_question
     * @param string $type The question subplugin type
     * @param int $id The question ID
     */
    public function delete_question($transaction, $type, $id) {
        global $DB;
        $DB->delete_records("teameval_questions", array("teamevalid" => $this->id, "qtype" => $type, "questionid" => $id));
        
        $transaction->allow_commit();
    }

    public function can_submit($userid) {

        //does this teameval belong to a coursemodule
        if (!isset($this->cm)) {
            return false;
        }

        //does the user have the capability to submit in this teameval?
        if (has_capability('local/teameval:submitquestionnaire', $this->context, $userid, false) == false) {
            return false;
        }

        // if a deadline is set, has it passed?
        if (($this->get_settings()->deadline > 0) && ($this->get_settings()->deadline < time())) {
            return false;
        }

        // have the marks already been released?
        if ($this->marks_available($userid)) {
            return false;
        }

        return true;
    }

    public function can_submit_response($type, $id, $userid) {
        global $DB;

        if($this->can_submit($userid) == false) {
            return false;
        }

        //first verify that the quesiton is in this teameval
        $isquestion = $DB->count_records("teameval_questions", array("teamevalid" => $this->id, "qtype" => $type, "questionid" => $id));

        if ($isquestion == 0) {
            return false;
        }

        return true;
    }


    public function num_questions() {
        global $DB;
        return $DB->count_records("teameval_questions", array("teamevalid" => $this->id));
    }

    public function last_ordinal() {
        global $DB;
        return $DB->get_field("teameval_questions", "MAX(ordinal)", ['teamevalid' => $this->id]);
    }

    protected function get_bare_questions() {
        global $DB;
        return $DB->get_records("teameval_questions", array("teamevalid" => $this->id), "ordinal ASC");
    }

    protected function has_questions_with_completion() {
        $questions = $this->get_questions();
        $has_value = false;
        foreach($questions as $question) {
            $has_value = $question->question->has_completion();
            if ($has_value) {
                break;
            }
        }
        return $has_value;
    }

    /**
     * Gets all the questions in this teameval questionnaire, along with some helpful context
     * @return question_info
     */
    public function get_questions() {
        global $DB;
        $barequestions = $this->get_bare_questions();
        
        $questions = [];
        $questionplugins = core_plugin_manager::instance()->get_plugins_of_type("teamevalquestion");
        foreach($barequestions as $bareq) {
            $questioninfo = new question_info($this, $bareq->id, $bareq->qtype, $bareq->questionid);
            $questions[] = $questioninfo;
        }

        return $questions;
    }

    public function questionnaire_set_order($order) {

        global $DB, $USER;

        require_capability('local/teameval:createquestionnaire', $this->context);

        //first assert that $order contains ALL the question IDs and ONLY the question IDs of this teameval
        $records = $DB->get_records("teameval_questions", array("teamevalid" => $this->id), '', 'id, qtype, questionid');

        if (count($records) != count($order)) {
            throw new moodle_error('questionidsoutofsync', 'teameval');
        }

        // flip the records so that we've got type => questionids => ids
        $questions = [];
        foreach($records as $r) {
            $questions[$r->qtype][$r->questionid] = $r;
        }        

        // set the ordinals according to $order

        foreach($order as $i => $qid) {
            $type = $qid['type'];
            $id = $qid['id'];
            if (empty($questions[$type][$id])) {
                throw new moodle_error('questionidsoutofsync', 'teameval');
            }
            $r = $questions[$type][$id];
            $r->ordinal = $i;
            $DB->update_record('teameval_questions', $r);
        }

    }

    /**
     * If you get back a LOCKED_REASON, ask questionnaire_locked_hint for help.
     * @return false or an array of [LOCKED_REASON constant, user object]
     */
    public function questionnaire_locked() {

        if (empty($this->cm)) {
            // Templates are never locked
            return false;
        }

        $this->get_evaluation_context();

        // The logic here is:
        
        // The questionnaire is locked if a single marking user has evaluation_permitted
        // This type of lock is not permanent – the questionnaire can be unlocked again
        // by changing availability
        
        // The questionnaire is locked permanently if a single marking user has completed
        // a question that has_completion

        $marking_users = $this->evalcontext->marking_users();

        // for the sake of efficiency, we're going to filter the list by users who can
        // actually see the module. this can trim the list to zero, if the module is
        // hidden.

        $info = new \core_availability\info_module($this->cm);
        $marking_users = $info->filter_user_list($marking_users);

        $reason = false;
        
        foreach($marking_users as $userid => $user) {
            if ($this->has_questions_with_completion()) {
                if ($this->user_completion($userid) > 0) {
                    return [LOCKED_REASON_MARKED, $user];
                }
            }
            if ($reason === false) {
                if ($this->evalcontext->evaluation_permitted($userid)) {
                    $reason = [LOCKED_REASON_VISIBLE, $user];
                }
                // we need to keep iterating here, because if we find a LOCKED_REASON_MARKED
                // we should return that instead
            }
        }

        return $reason;

    }

    /**
     * The reason why this questionnaire is locked.
     */
    public static function questionnaire_locked_reason($reason) {

        switch($reason) {
            case LOCKED_REASON_VISIBLE:
                return get_string('lockedreasonvisible', 'local_teameval');
            case LOCKED_REASON_MARKED:
                return get_string('lockedreasonmarked', 'local_teameval');
        }

    }


    /**
     * This function gives help text to the user on why their questionnaire is locked.
     * Why is this static, taking an evalcontext? Because evalcontexts can exist independent
     * of a team_evaluation.
     */
    public static function questionnaire_locked_hint($reason, $user, $evalcontext) {

        switch($reason) {
            case LOCKED_REASON_VISIBLE:
                return $evalcontext->questionnaire_locked_hint($user);
            case LOCKED_REASON_MARKED:
                return get_string('lockedhintmarked', 'local_teameval');
        }

    }

    /**
     * Is this group ready to receive their adjusted marks?
     * @param int $groupid The group in question
     * @return bool If the group is ready
     */ 
    protected function group_ready($groupid) {

        $members = $this->group_members($groupid);
        $questions = $this->get_questions();

        $ready = true;

        foreach($questions as $q) {
            if ($q->question->has_completion()) {
                foreach($members as $m) {
                    $response = $this->get_response($q, $m->id);
                    if( $response->marks_given() == false ) {
                        $ready = false;
                        break;
                    }
                }
            }

            if ($ready == false) break;
        }

        return $ready;

    }

    /**
     * Returns the percentage completion of a user as 0..1
     * @param int $uid the id of the User
     * @return float the completion index
     */
    public function user_completion($uid) {
        $questions = $this->get_questions();
        $marks_given = 0;
        $num_questions = 0;
        foreach($questions as $q) {
            // if this question can't be completed, don't count it towards user completion
            if (!$q->question->has_completion()) {
                continue;
            }

            $num_questions++;

            $response = $this->get_response($q, $uid);
            if ($response->marks_given()) {
                $marks_given++;
            }
        }

        if ($num_questions == 0) {
            return 1;
        }

        return $marks_given / $num_questions;
    }

    public function get_evaluator() {

        if (! isset($this->evaluator)) {

            $this->get_evaluation_context();

            $evaluators = core_plugin_manager::instance()->get_plugins_of_type("teamevaluator");

            // in future, this will need to be changed to get the selected evaluator for this instance
            $plugininfo = current( $evaluators );
            $evaluator_cls = $plugininfo->get_evaluator_class();

            $markable_users = $this->evalcontext->marking_users();

            $questions = $this->get_questions();
            $responses = [];
            foreach($questions as $q) {
                foreach($markable_users as $m) {
                    $response = $this->get_response($q, $m->id);
                    $responses[$m->id][] = $response;
                }
            }

            $this->evaluator = new $evaluator_cls($this, $responses);

        }

        return $this->evaluator;

    }

    public function non_completion_penalty($uid) {
        $noncompletion = $this->get_settings()->noncompletionpenalty;
        $completion = $this->user_completion($uid);
        $penalty = $noncompletion * (1 - $completion);
        return $penalty;
    }

    /**
     * Takes a 0..1 score from an evaluator and turns it into a grade multiplier 
     */
    protected function score_to_multiplier($score, $uid) {
        $fraction = $this->get_settings()->fraction;
        $multiplier = (1 - $fraction) + ($score * $fraction);

        $penalty = $this->non_completion_penalty($uid);

        $multiplier -= $penalty;

        return $multiplier;
    }

    public function get_scores() {
        if (!isset($this->_scores)) {
            $this->_scores = $this->get_evaluator()->scores();
        }

        return $this->_scores;
    }

    public function multipliers() {
        $scores = $this->get_scores();

        $multipliers = [];

        foreach($scores as $uid => $score) {
            $multipliers[$uid] = $this->score_to_multiplier($score, $uid);
        }

        return $multipliers;
    }

    /**
     * Returns the score multipliers for a particular group
     * @param int $groupid The ID of the group in question
     * @return array(int => float) User ID to score multiplier
     */
    public function multipliers_for_group($groupid) {

        $users = $this->group_members($groupid);
        $scores = $this->get_scores();

        $multipliers = [];

        foreach($users as $uid => $user) {
            if (isset($scores[$uid])) {
                $multipliers[$uid] = $this->score_to_multiplier($scores[$uid], $uid);
            }
        }

        return $multipliers;

    }

    public function multiplier_for_user($userid) {
        $scores = $this->get_scores();

        if (! isset($scores[$userid])) {
            return null;
        }
        
        $score = $scores[$userid];

        return $this->score_to_multiplier($score, $userid);
    }

    // REPORTS

    public function set_report_plugin($plugin) {
        set_user_preference(REPORT_PLUGIN_PREFERENCE, $plugin);
    }

    public function get_report_plugin($plugin = null) {
        if ($plugin == null) {
            $plugin = get_user_preferences(REPORT_PLUGIN_PREFERENCE, 'scores');
        }
        return core_plugin_manager::instance()->get_plugin_info("teamevalreport_$plugin");
    }

    public function get_report($plugin = null) {
        // TODO: site-wide default report

        $plugininfo = $this->get_report_plugin($plugin);
        $cls = $plugininfo->get_report_class();

        $report = new $cls($this);

        return $report->generate_report();
    }

    public function report_download_link($plugin, $filename) {
        if(empty($this->cm)) {
            throw new coding_exception("Cannot download report for template.");
        }

        return moodle_url::make_pluginfile_url(
            $this->get_context()->id, 
            'local_teameval', 
            'report', 
            $this->cm->id,
            "/$plugin/", $filename);
    }

    public static function plugin_supports_renderer_subtype($plugin, $subtype = null) {
        $plugininfo = core_plugin_manager::instance()->get_plugin_info($plugin);
        $supported_subtypes = [];

        if (method_exists($plugininfo, 'supported_renderer_subtypes')) {
            $supported_subtypes = $plugininfo->supported_renderer_subtypes();
        }

        if ($subtype == null) {
            return $supported_subtypes;
        }

        if (is_array($subtype)) {
            return array_intersect($subtype, $supported_subtypes);
        }

        return in_array($subtype, $supported_subtypes);
    }

    


    // interface to evalcontext

    public function group_for_user($userid) {
        return $this->get_evaluation_context()->group_for_user($userid);
    }

    public function all_groups() {
        return $this->get_evaluation_context()->all_groups();
    }

    public function marking_users() {
        return $this->get_evaluation_context()->marking_users();
    }

    // convenience functions

    /**
     * Gets the teammates in a user's team.
     * @param int $userid User to get the teammates for
     * @param bool $include_self Include user in teammates. Defaults to $this->settings->self.
     * @return type
     */
    public function teammates($userid, $include_self=null) {

        if (is_null($include_self)) {
            $include_self = $this->get_settings()->self;
        }

        $group = $this->group_for_user($userid);

        if ($group == null) {
            return [];
        }

        $members = $this->group_members($group->id);
        
        if($include_self == false) {
            unset($members[$userid]);
        } else {
            $self = $members[$userid];
            unset($members[$userid]);
            $members = [$userid => $self] + $members;
        }

        return $members;
    }

    /**
     * Cached and filtered version of groups_get_members.
     * @param type $groupid 
     * @return type
     */
    public function group_members($groupid) {
        $groupcache = self::$groupcache;
        if (!isset($groupcache[$groupid])) {
            $members = groups_get_members($groupid);
            $members = array_filter($members, function($u) {
                return has_capability('local/teameval:submitquestionnaire', $this->context, $u->id);
            });
            $groupcache[$groupid] = $members; 
        } else {
            $members = $groupcache[$groupid];
        }
        return $members;
    }

    /**
     * It's only two lines, but it gets called a lot, so now it's a convenience function.
     * @param stdClass $questioninfo The question object from from get_questions()
     * @param int $userid The ID of the user who's response we need
     */
    public function get_response($questioninfo, $userid) {
        $response_cls = $questioninfo->plugininfo->get_response_class();
        return new $response_cls($this, $questioninfo->question, $userid);
    }

    /**
     * Get the final adjusted grade, if available
     * @param int $userid The ID of the user whose grade you want
     * @return float The adjusted grade, in terms of the evaluation context
     */

    public function adjusted_grade($userid) {

        $evalcontext = $this->get_evaluation_context();

        $group = $evalcontext->group_for_user($userid);

        $unadjusted = $evalcontext->grade_for_group($group->id);

        if ($this->marks_available($userid)) {

            return $unadjusted * $this->multiplier_for_user($userid);

        }

        return null;

    }

    public function all_feedback($userid) {

        $questions = $this->get_questions();

        $feedbacks = [];
        foreach($questions as $qi) {
            if ($qi->question->has_feedback() == false) {
                continue;
            }

            $q = new stdClass;
            $q->title = $qi->question->get_title();
            $q->feedbacks = [];

            foreach($this->teammates($userid) as $tm) {
                $fb = new stdClass;

                $response = $this->get_response($qi, $tm->id);
                $fb->feedback = trim( $response->feedback_for($userid) );
                if (strlen($fb->feedback) == 0) {
                    continue;
                }

                if($qi->question->is_feedback_anonymous() == false) {
                    if ($userid == $tm->id) {
                        $fb->from = get_string('yourself', 'local_teameval');
                    } else {
                        $fb->from = fullname($tm);
                    }
                }

                $q->feedbacks[] = $fb;
            }

            if (count($q->feedbacks)) {
                $feedbacks[] = $q;
            }

        }

        return $feedbacks;

    }

    public function questionnaire_is_visible($userid = null) {
        $blockinstalled = !is_null(get_capability_info('blocks/teameval_templates:viewtemplate'));
        return (
            ($blockinstalled && has_capability('blocks/teameval_templates:viewtemplate', $this->context, $userid)) ||
            has_capability('local/teameval:viewtemplate', $this->context, $userid)
            );
    }

    // TEMPLATES

    public function update_searchable() {
        $weights = [];

        if (!empty($this->settings->title)) {
            $titletags = str_word_count($this->settings->title, 1);
        } else if (!empty($this->cm)) {
            $titletags = str_word_count($this->cm->name, 1);
        }

        foreach($titletags as $tag) {
            $tag = strtolower($tag);
            $weights[$tag] += 100;
        }

        $contexttags = str_word_count($this->context->get_context_name(), 1);
        foreach($contexttags as $tag) {
            $tag = strtolower($tag);
            $weights[$tag] += 20;
        }

        searchable::set_weights('teameval', $this->id, $weights);
    }

    public static function templates_for_context($contextid) {
        global $DB;
        $ids = $DB->get_records('teameval', ['contextid' => $contextid], 'UPPER(title) ASC', 'id');
        return array_map(function($id) {
            return new team_evaluation($id);
        }, array_keys($ids));
    }

    public function add_questions_from_template($template) {
        global $USER;

        $questions = $template->get_questions();
        $base = $this->num_questions();

        foreach($questions as $ordinal => $question) {
            $transaction = $this->should_update_question($question->type, 0, $USER->id);
            if ($transaction) {
                $qclass = $question->plugininfo->get_question_class();
                $newquestionid = $qclass::duplicate_question($question->questionid);
                if ($newquestionid) {
                    $this->update_question($transaction, $question->type, $newquestionid, $base + $ordinal);
                } else {
                    $exc = new coding_exception("Question type $question->type does not implement duplicate_question correctly.");
                    $transaction->rollback($exc);
                }
            }
        }
    }

    // MARK RELEASE

    public function release_marks_for($target, $level, $set) {
        global $DB;

        $this->get_evaluation_context();
        $this->get_releases();

        $release = new stdClass;
        $release->cmid = $this->cm->id;
        $release->target = $target;
        $release->level = $level;

        // try to get a record which matches this.
        $record = $DB->get_record('teameval_release', (array)$release);

        if (($set == true) && ($record === false)) {
            $DB->insert_record('teameval_release', $release);
        }

        if (($set == false) && ($record !== false)) {
            $DB->delete_records('teameval_release', (array)$record);
        }
        
        $this->releases[] = $release;

        // figure who we need to trigger grades for
        if ($level == RELEASE_ALL) {
            $this->evalcontext->trigger_grade_update();
        } else if ($level == RELEASE_GROUP) {
            $users = $this->group_members($target);
            $this->evalcontext->trigger_grade_update(array_keys($users));
        } else if ($level == RELEASE_USER) {
            $this->evalcontext->trigger_grade_update([$target]);
        }
    }

    public function release_marks_for_all($set = true) {
        $this->release_marks_for(0, RELEASE_ALL, $set);
    }

    public function release_marks_for_group($groupid, $set = true) {
        $this->release_marks_for($groupid, RELEASE_GROUP, $set);
    }

    public function release_marks_for_user($userid, $set = true) {
        $this->release_marks_for($userid, RELEASE_USER, $set);
    }

    protected function get_releases() {
        if (!isset($this->cm)) {
            return [];
        }

        global $DB;
        if (!isset($this->releases)) {
            $this->releases = $DB->get_records('teameval_release', ['cmid' => $this->cm->id], 'level ASC');
        }
        return $this->releases;
    }

    public function marks_released($userid) {
        global $DB;

        $grp = $this->group_for_user($userid);
        $is_released = false;

        if ($this->get_settings()->autorelease) {
            $is_released = true;
        } else {
            $releases = $this->get_releases();
            foreach($releases as $release) {
                if ($release->level == RELEASE_ALL) {
                    $is_released = true;
                    break;
                }

                if ($release->level == RELEASE_GROUP) {
                    if ($release->target == $grp->id) {
                        $is_released = true;
                        break;
                    }
                }

                if ($release->level == RELEASE_USER) {
                    if ($release->target == $userid) {
                        $is_released = true;
                        break;
                    }
                }
            }
        }

        return $is_released;
    }

    public function marks_available($userid) {
        // First check if the marks are released.
        if (!$this->marks_released($userid)) {
            return false;
        }

        // Next check if everyone in their group has submitted OR the deadline has passed

        if (($this->get_settings()->deadline > 0) && ($this->get_settings()->deadline < time())) {
            return true;
        }

        $grp = $this->group_for_user($userid);

        if ($this->group_ready($grp->id)) {
            return true;
        }

        return false;

    }

    // FEEDBACK CONTROL

    // In the context of feedback control, questionid refers to the id column of teameval_questions.
    // It does NOT refer to the questionid column, which is a plugin-specific reference to a given question.
    // When calling these functions, make sure you're using the ->id property of the questioninfo object
    // you got back from get_questions.

    public function rescind_feedback_for($questionid, $markerid, $targetid, $state=FEEDBACK_RESCINDED) {
        global $DB;
        $rslt = $DB->get_record('teameval_rescind', ['questionid' => $questionid, 'markerid' => $markerid, 'targetid' => $targetid]);
        if ($rslt) {
            $rslt->state = $state;
            $DB->update_record('teameval_rescind', $rslt);
        } else {
            $record = new stdClass;
            $record->questionid = $questionid;
            $record->markerid = $markerid;
            $record->targetid = $targetid;
            $record->state = $state;
            $DB->insert_record('teameval_rescind', $record);
        }
    }

    public function rescinded($questionid, $markerid, $targetid) {
        global $DB;
        $rslt = $DB->get_record('teameval_rescind', ['questionid' => $questionid, 'markerid' => $markerid, 'targetid' => $targetid]);
        if ($rslt) {
            return $rslt->state;
        }
        return 0;
    }

    public function all_rescind_states() {
        global $DB;

        $qids = array_map(function($q) {
            return $q->id;
        }, $this->get_questions());

        if (count($qids) == 0) {
            return [];
        }

        list($sql, $params) = $DB->get_in_or_equal($qids);
        $rescinds = $DB->get_records_select('teameval_rescind', "questionid $sql", $params);
        return $rescinds;
    }

    // DELETE/RESET

    public static function delete_teameval($id = null, $cmid = null) {
        global $DB;

        if (empty($id) && empty($cmid)) {
            throw new coding_exception("id or cmid must be set");
        }

        if (!empty($cmid)) {
            $teameval = $DB->get_record('teameval', ['cmid' => $cmid]);
        } else {
            $teameval = $DB->get_record('teameval', ['id' => $id]);
        }

        if (empty($teameval)) {
            return false;
        }

        $barequestions = $DB->get_records('teameval_questions', ['teamevalid' => $id]);

        self::delete_questionnaire_f($barequestions);

        if ($cmid) {
            $DB->delete_records('teameval_release', ['cmid' => $cmid]);
            $DB->delete_records_list('teameval_rescind', 'questionid', array_keys($barequestions));
        }

        $DB->delete_records('teameval', ['id' => $teameval->id]);

        return true;

    }

    public function reset_userdata() {
        global $DB;

        if (isset($this->cm)) {
            //delete release data
            $DB->delete_records('teameval_release', ['cmid' => $this->cm->id ]);

            //delete rescinds
            $questions = array_keys($DB->get_records('teameval_questions', ['teamevalid' => $this->id], '', 'id'));
            $DB->delete_records_list('teameval_rescind', 'questionid', $questions);
        }

        $evalcontext = $this->get_evaluation_context();
        return ['component' => $evalcontext::component_string(), 'item' => get_string('resetresponses', 'local_teameval'), 'error' => false];
    }

    protected static function delete_questionnaire_f($barequestions) {
        global $DB;        

        $sorted = [];
        foreach($barequestions as $barequestion) {
            $sorted[$barequestion->qtype][] = $barequestion->questionid;
        }

        $questionplugins = core_plugin_manager::instance()->get_plugins_of_type("teamevalquestion");
        foreach($sorted as $qtype => $ids) {
            $plugin = $questionplugins[$qtype]->get_question_class();
            $plugin::delete_questions($ids);
        }

        $DB->delete_records_list('teameval_questions','id',array_keys($barequestions));

    }

    public function delete_questionnaire() {
        global $DB;

        // We're not using get_questions because that actually instantiates a copy of our question
        // And since we're in the middle of tearing down our teameval that could be problematic.

        $barequestions = $this->get_bare_questions();
        
        self::delete_questionnaire_f($barequestions);

        $evalcontext = $this->get_evaluation_context();
        return ['component' => $evalcontext::component_string(), 'item' => get_string('resetquestionnaire', 'local_teameval'), 'error' => false];

    }

    public function reset_questionnaire() {
        global $DB;

        // Yes, this IS a lot of repeated code. The reason we're not DRYing this out is because
        // delete_questionnaire is so destructive.

        $barequestions = $this->get_bare_questions();
        $sorted = [];
        foreach($barequestions as $barequestion) {
            $sorted[$barequestion->qtype][] = $barequestion->questionid;
        }

        $questionplugins = core_plugin_manager::instance()->get_plugins_of_type("teamevalquestion");
        foreach($sorted as $qtype => $ids) {
            $plugin = $questionplugins[$qtype]->get_question_class();
            $plugin::reset_userdata($ids);
        }
    }

    // IMPORT/EXPORT

    public function template_file_name() {
        return $this->get_title() . ".mbz";
    }

    public function export_questionnaire() {
        $task = new export_task('export', $this->id, $this->context->id, $this->template_file_name());
        $task->build();
        $task->execute();
        $file = $task->file;
        
        return $file;
    }

    public function import_questionnaire($file) {
        $task = new import_task('import', $this, $file);
        $task->build();
        $task->execute();
    }

}

//copied from core_component. why this is not a global function...
function is_developer() {
    global $CFG;

    // Note we can not rely on $CFG->debug here because DB is not initialised yet.
    if (isset($CFG->config_php_settings['debug'])) {
        $debug = (int)$CFG->config_php_settings['debug'];
    } else {
        $debug = $CFG->debug;
    }

    if ($debug & E_ALL and $debug & E_STRICT) {
        return true;
    }

    return false;
}