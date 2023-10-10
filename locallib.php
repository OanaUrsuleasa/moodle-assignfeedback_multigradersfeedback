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
 * This file contains the definition for the library class for file feedback plugin
 *
 *
 * @package   assignfeedback_multigradersfeedback
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/moodlelib.php');
require_once($CFG->dirroot.'/mod/assign/assignmentplugin.php');
require_once($CFG->dirroot.'/backup/cc/validator.php');
require_once($CFG->dirroot.'/grade/grading/lib.php');

if (file_exists($CFG->dirroot .'/grade/grading/form/multigraders/lib.php')) {
    require_once($CFG->dirroot . '/grade/grading/form/multigraders/lib.php');
}

/**
 * Library class for file feedback plugin extending feedback plugin base class.
 *
 * @package   assignfeedback_multigradersfeedback
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_feedback_multigradersfeedback extends assign_feedback_plugin
{
     /** @var array Array of error messages encountered during the execution of assignment related operations. */
     private $errors = array();

    /**
     * Get the name of the file feedback plugin.
     *
     * @return string
     */
    public function get_name()
    {
        return get_string('file', 'assignfeedback_multigradersfeedback');
    }
  

/**
     * Automatically enable or disable this plugin based on "$CFG->commentsenabled"
     *
     * @return bool
     */
    public function is_enabled() {
        global $CFG;
        return (!empty($CFG->usecomments));
    }


    /**
     * Override to indicate a plugin supports quickgrading
     *
     * @return boolean - True if the plugin supports quickgrading
     */
    public function supports_quickgrading()
    {
        return false;
    }

  
    /**
     * Has the plugin quickgrading form element been modified in the current form submission?
     *
     * @param int $userid The user id in the table this quickgrading element relates to
     * @param stdClass $grade The grade
     * @return boolean - true if the quickgrading form element has been modified
     */
    public function is_quickgrading_modified($userid, $grade)
    {
        return false;
    }

    /**
     * Save quickgrading changes
     *
     * @param int $userid The user id in the table this quickgrading element relates to
     * @param stdClass $grade The grade
     * @return boolean - true if the grade changes were saved correctly
     */
    public function save_quickgrading_changes($userid, $grade)
    {
        return false;
    }

    /**
     * Run cron for this plugin
     */
    public static function cron()
    {
    }


    /**
     * Return a list of the grading actions supported by this plugin.
     *
     * A grading action is a page that is not specific to a user but to the whole assignment.
     * @return array - An array of action and description strings.
     *                 The action will be passed to grading_action.
     */
    public function get_grading_actions()
    {
        return [];
    }

    /**
     * Show a grading action form
     *
     * @param string $gradingaction The action chosen from the grading actions menu
     * @return string The page containing the form
     */
    public function grading_action($gradingaction)
    {
        return '';
    }


    /**
     * Return a list of the batch grading operations supported by this plugin.
     *
     * @return array - An array of action and description strings.
     *                 The action will be passed to grading_batch_operation.
     */
    public function get_grading_batch_operations()
    {
        $context=$this->assignment->get_context();     
        $manager = get_grading_manager($context,'mod_assign','submissions');
        $method = $manager->get_active_method();

        if($method == 'multigraders'){
            return array('multigradersfiles' => get_string('multigradersfiles', 'assignfeedback_multigradersfeedback'));
        }else{
            return '';
        }
    }


    /**
     * Show a batch operations form
     * 
     * @param string $action The action chosen from the batch operations menu
     * @param array $users The list of selected userids
     * @return string The page containing the form
     * 
     */
    
    public function grading_batch_operation($action, $users)
    {
        global $USER;

        foreach ($users as $userid) {
                $grade = $this->assignment->get_user_grade($userid, true); 

                $formparams = array('cm'=>$this->assignment->get_course_module()->id,
                                'userid'=>$userid,
                                'context'=>$this->assignment->get_context()->id,
                                'itemid' =>$grade->id,
                                'grader'=>$grade->grader);                               
                $error = null;
                $grade_final=gradingform_multigraders_controller::update_multigraders_feedback($formparams,$error);

            if($error){
                $this->set_error_message($error);  
            }else{
                if($grade_final != 0){
                    $grader=$USER->id;  

                    $grade->grade=$grade_final;
                    $grade->grader=$grader;
                    $update_grade=$this->assignment->update_grade($grade,false);   
                                       
                }
            }               
        }
        

        $errors = $this->get_error_messages();
        
         if($errors!=null){
            $messages = html_writer::alist($errors, ['class' => 'mb-1 mt-1']);
            $messagetype = \core\output\notification::NOTIFY_ERROR;

            redirect(new moodle_url('view.php',
                    array('id'=>$this->assignment->get_course_module()->id,
                    'action'=>'grading')), $messages,null,$messagetype);
        }else{
            redirect(new moodle_url('view.php',
                    array('id'=>$this->assignment->get_course_module()->id,
                    'action'=>'grading')));
        }
        
        return; 
    }

    /**
     * Return subtype name of the plugin.
     *
     * @return string
     */
    public function get_subtype()
    {
        return 'assignfeedback';
    }
   
    /**
     * Set error message.
     *
     * @param string $message The error message
     */
    protected function set_error_message(string $message) {
        $this->errors[] = $message;
    }

    /**
     * Get error messages.
     *
     * @return array The array of error messages
     */
    protected function get_error_messages(): array {
        return $this->errors;
    }
}
