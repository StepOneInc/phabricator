<?php

final class s1_JiraTransitionTickets extends HeraldCustomAction{
  public function appliesToAdapter(HeraldAdapter $adapter){
    return $adapter instanceof HeraldDifferentialRevisionAdapter;
  }

  public function appliesToRuleType($rule_type){
    return $rule_type == HeraldRuleTypeConfig::RULE_TYPE_GLOBAL ||
      $rule_type == HeraldRuleTypeConfig::RULE_TYPE_OBJECT;
  }

  public function getActionKey(){
    return 'fixed-jira';
  }

  public function getActionName(){
    return pht('Update Jira Ticket to Fixed or In Review');
  }

  public function getActionType(){
    return HeraldAdapter::VALUE_TEXT;
  }
  
  /* Actual function part. */
  public function applyEffect(HeraldAdapter $adapter, $object, HeraldEffect $effect) {
    
    $diff = $object->loadActiveDiff();
    $author = $object->getUsersToNotifyOfTokenGiven();


    $user_dao = newv('PhabricatorUser', array());
    $users = $user_dao->loadAllWhere(
      'phid in (%Ls)',
      $author);
    foreach($users as $user){
      $cur_user = $user;
    }
    
   
   $config_fields = new PhabricatorUserConfiguredCustomField();
   $created_fields = $config_fields->createFields($cur_user); 
   $field_list = new PhabricatorCustomFieldList ($created_fields);
   
    try {
      $attachment = $cur_user->getCustomFields();
    } catch (PhabricatorDataNotAttachedException $ex) {
      $attachment = new PhabricatorCustomFieldAttachment();
      $attachment->addCustomFieldList('Jira', $field_list);
      $cur_user->attachCustomFields($attachment);
    }
    
  $custom_fields = $cur_user ->getCustomFields();
  $fields = $custom_fields ->getCustomFieldList('Jira');
   $get_custom_field_list = $fields->readFieldsFromStorage($cur_user);
   $get_userConfiguredCustomField_array= $get_custom_field_list->getFields();
   $jira_user_fieldobject = $get_userConfiguredCustomField_array[0];
   $user_handles = $jira_user_fieldobject->getRequiredHandlePHIDsForPropertyView();
   $jira_user = $jira_user_fieldobject->renderPropertyViewValue($user_handles);
   
   $jira_pass_fieldobject = $get_userConfiguredCustomField_array[1];
   $pass_handles = $jira_pass_fieldobject->getRequiredHandlePHIDsForPropertyView();
   $jira_pass = $jira_pass_fieldobject->renderPropertyViewValue($pass_handles);
   
    $commit_message = $diff->getDescription();
    $jira_to_fix = array();
    $jira_to_review = array();
    $exploded_message = array_filter(explode(" ",  str_replace(array(",",  ".", ":", ";", "?","!") ,  "", $commit_message)));
    
    /* Test to see if diff message contains any of the fix/close/resolve/review keywords with a Jira ticket(s). */
    foreach($exploded_message as $key=>$word){
      if(stripos($word, 'resolv') === 0|| stripos($word, 'fix') === 0||stripos($word, 'clos') === 0||stripos($word, 'review') === 0){
        $i = 1;
        while(preg_match("/[A-Z]-[0-9]/i", $exploded_message[$key + $i])){
          if(stripos($exploded_message[$key], "review") === 0){
            $jira_to_review[] = $exploded_message[$key + $i];
          }
          else{
            $jira_to_fix[] =  $exploded_message[$key + $i];
          }
          if(strcmp($exploded_message[$key + $i +1],  "and") == 0 || strcmp($exploded_message[$key + $i +1],  " ") == 0  ){
            $i += 2;
          }
          else{
            $i += 1;
          }
        }
        $key = $key + $i -1;
      }
    }


    /* Connect to Jira and update ticket(s). */
    if(count($jira_to_fix) != 0 || count($jira_to_review) != 0){
      $transition_id = "";
      require_once("s1_jira_utils.php");
      foreach($jira_to_fix as $key){
        $cur_status_array = get_status($key, $jira_user, $jira_pass);
        if (array_key_exists('errors', $cur_status_array)) {
          throw new Exception(print_r($cur_status_array, true));
        }else {
          $cur_status = $cur_status_array['fields']['status']['name'];
          
          if(strcmp($cur_status, "To Do") === 0 || strcmp($cur_status, "Doing") === 0 ){
            $transition_id = '61';
          } 
          else if(strcmp($cur_status, "In Review") === 0){
            $transition_id ='191';
          }
          else{
            echo('Error, unable to transition from current state to "Fixed."');
         }
        }
         //json for fixed tickets
        $fields = array(
          'update' => array(
            'comment' =>  array(array(
              'add' => array(
                'body' => 'Phabricator transitioned this issue to Status: Done, Resolution: Fixed.'
                )
              ))
            ),
          'fields' => array(
             'resolution' => array(
              'name' => 'Fixed'
              )
             ),
          'transition' => array(
            'id' =>  $transition_id
            )
        );
        
        $result =  transition_issue($key, $fields, $jira_user, $jira_pass);
        if ($result != NULL) {
          echo "Error(s) editting issue:\n";
          var_dump($result);
        } else {
          echo "Edit complete. Issue can be viewed at http://jira.steponeinc.com/browse/{$key}\n";
	}
      }
      
      foreach($jira_to_review as $key){
        $cur_status_array = get_status($key, $jira_user, $jira_pass);
        if (array_key_exists('errors', $cur_status_array)) {
            throw new Exception(print_r($cur_status_array));
        }else {
          $cur_status = $cur_status_array['fields']['status']['name'];
          if(strcmp($cur_status, "To Do") === 0){
            $transition_id = '261';
          } 
          else if(strcmp($cur_status, "Doing") === 0){
            $transition_id ='181';
          }
          else{
            throw new Exception(print_r($cur_status_array, true));
            throw new Exception('Error, unable to transition from current state to "In Review."');
          }
        }
        
        //json fields for review tickets 
        $fields = array(
          'update' => array(
            'comment' =>  array(array(
              'add' => array(
                'body' => 'Phabricator transitioned this issue to Status: In Review.'
                )
              ))
            ),
          'transition' => array(
            'id' =>  $transition_id
            )
        );
      
        //transitioning of  review tickets
        $result =  transition_issue($key, $fields, $jira_user, $jira_pass);
        if ($result != NULL) {
          echo "Error(s) editting issue:\n";
          var_dump($result);
        } else {
          echo "Edit complete. Issue can be viewed at http://jira.steponeinc.com/browse/{$key}\n";
	}
      }
    }

    return new HeraldApplyTranscript( $effect, true, pht('Jira tickets fixed.'));
  }

}
