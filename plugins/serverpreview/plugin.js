    /**
    * File Name: serverpreview\plugin.js
    *
    * Licensed under the terms of the GNU Lesser General Public License:
    *       http://www.opensource.org/licenses/lgpl-license.php
    *
    *    Plugin for CKeditor 3 to send the current data to the server so it can be previewed in a custom way
    *   Extended from the FCK plugin with the same name, by Alfonso Martínez de Lizarrondo
    *
    *  version
    *      0.1 15/02/2006
    *      0.2 02/09/2007 Replace the 'Preview' command instead of creating a custom one.
    *      0.3 14/07/2010 No longer replaces the Preview button, adds another one instead.      
    *
    * File Author:
    *       Vlad Fratila
    *       vlad.fratila@gmail.com
    *
    * == How to Use: ==
    *
    * Add the plugin in your config.js like other plugins:
    *    config.extraPlugins = 'serverpreview';
    *   
    * You will also need to specify the full URL of your server-side script like this:
    *      config.serverPreviewURL = 'http://www.mydomain.com/myfile.php';
    * In this file, you need to stripslashes and display the post variable "htmlData"
    *
    * To get an icon for the button, you will need to change your skin's CSS file
    * (e.g. if you're using the default: change /ckeditor/skins/kama/editor.css)
    * I just disabled the Preview plugin and did a Search/Replace to replace 'preview' with 'serverpreview'.
    *
    *  Lastly, don't forget to add the button 'ServerPreview' to your toolbars!
    *
    */


    (function(){
       
       var pluginName = 'serverpreview';

       var serverpreviewCmd =
       {
          modes : { wysiwyg:1, source:1 },
          canUndo : false,
          exec : function( editor )
          {
             var theForm = document.getElementById('serverPreviewForm') ;
             if (!theForm) {
                //it doesn't exist still, we create it here
                theForm = document.createElement('FORM') ;
                theForm.method = 'POST' ;
                theForm.name = 'serverPreviewForm' ;
                theForm.id=theForm.name ;
                theForm.style.display = 'none' ;

                theForm.action = editor.config.serverPreviewURL + jQuery( "#page_id" ).val();

                //new window please
                theForm.target='_blank';
                document.body.appendChild( theForm );
             }

             //clear previous data
             theForm.innerHTML = '' ;
             //set the new content
             var input = document.createElement('INPUT') ;
             input.type = 'hidden';
             //change the name as needed -->
             input.name = 'htmlData' ;
             //set the data
             input.value = editor.getData();
             //append the new input to the form
             theForm.appendChild( input );

             //send the data to the server
             theForm.submit();
          }
       }
       
       
       CKEDITOR.plugins.add( pluginName,
       {
          init : function( editor )
          {
             editor.addCommand( pluginName, serverpreviewCmd );
             editor.ui.addButton( 'Preview',
                {
                   label : 'Server Preview',
                   command : pluginName,
                });
          }
       });
       
    })();