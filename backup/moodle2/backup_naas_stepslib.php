<?php
 
/**
 * Define all the backup steps that will be used by the backup_naas_activity_task
 */


class backup_naas_activity_structure_step extends backup_activity_structure_step {
 
    protected function define_structure() {
 
        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');
 
        // Define each element separated 
        // Save the name of the nugget and the nugget_id
        $naas = new backup_nested_element('naas', array('id'), array(
            'name', 'nugget_id', 'intro', 'introformat', 'publish',
            'showresults', 'display', 'allowupdate', 'allowunanswered',
            'limitanswers', 'timeopen', 'timeclose', 'timemodified'));
  
        // Build the tree

        // Define sources
        $naas->set_source_table('naas', array('id' => backup::VAR_ACTIVITYID));

        // Define id annotations
 
        // Define file annotations
 
        // Return the root element (naas), wrapped into standard activity structure
        return $this->prepare_activity_structure($naas);

    }
}


