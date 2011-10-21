<?php

    class qa_ma_cache_that_admin {
        
        var $directory;
        var $urltoroot;
        
        
        
        //this runs before the module is used.
        function load_module($directory, $urltoroot)
        {
            //file system path to the plugin directory
            $this->directory=$directory;
                        
            //url path to the plugin relative to the current page request.
            $this->urltoroot=$urltoroot;
            
        }
        
        //a request for the default value for $option
        function option_default($option)
        {
            if ($option == 'ma_cache_that_enabled') {
                HH_cache::enabledSave(false);
				
				return false;
            }
        }
		
		
        
        function admin_form()
        {
            
            //default form as unsaved
            $saved=false;
            
            //has the save button been pressed?
            if (qa_clicked('ma_cache_that_save_button')) {
                
                //save the checkbox value as an int value
                qa_opt('ma_cache_that_enabled', (int)qa_post_text('ma_cache_that_enabled'));
                HH_cache::enabledSave((int)qa_post_text('ma_cache_that_enabled'));
                
				//mark form as saved
                $saved=true;
            }
            
            //build the form.
            //'ok' displays a message above the form. Used here to display a success message if the form has been saved.
            //files contains an array of field options.
            $form=array(
                'ok' => $saved ? 'Cache That settings saved' : null,

                'fields' => array(
                    'ma_cache_that_enabled' => array(
                        'label' => 'Enable Cache That?',
                        'type' => 'checkbox',
                        'value' => (int)qa_opt('ma_cache_that_enabled'),
                        'tags' => 'NAME="ma_cache_that_enabled"',
                    ),

                ),
                
                'buttons' => array(
                    array(
                        'label' => 'Save Changes',
                        'tags' => 'NAME="ma_cache_that_save_button"',
                    ),
                ),
            );
            
            return $form;
        }
        
        
    
    };
    

/*
    Omit PHP closing tag to help avoid accidental output
*/