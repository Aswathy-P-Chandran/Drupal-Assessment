(function($, Drupal, drupalSettings, once) {
    'use strict';
  
    Drupal.behaviors.dropzoneWidget = {
      attach: function(context, settings) {
        // Use the new once library instead of jQuery .once()
        once('dropzone-widget', '.dropzone-widget', context).forEach(function(element) {
          var $element = $(element);
          var fieldName = $element.data('field-name') || 'files';
          var maxFilesize = parseInt($element.data('max-filesize')) || 10;
          var maxFiles = parseInt($element.data('max-files')) || 10;
          
          // Disable Dropzone autodiscover
          Dropzone.autoDiscover = false;
          
          var dropzoneId = 'dropzone-' + fieldName;
          var $dropzoneContainer = $('#' + dropzoneId);
          
          if ($dropzoneContainer.length === 0) {
            return;
          }
          
          var dropzone = new Dropzone('#' + dropzoneId, {
            url: '/file/upload/dropzone',
            acceptedFiles: '.xlsx,.csv',
            maxFilesize: maxFilesize,
            maxFiles: maxFiles,
            addRemoveLinks: true,
            parallelUploads: 1,
            uploadMultiple: false,
            autoProcessQueue: true,
            
            // Messages
            dictDefaultMessage: '<div class="dz-icon">📁</div><h3>Drop files here or click to upload</h3><p>Only XLSX and CSV files are allowed (Max: ' + maxFilesize + 'MB each)</p>',
            dictFallbackMessage: 'Your browser does not support drag and drop file uploads.',
            dictFileTooBig: 'File is too big ({{filesize}}MB). Max filesize: {{maxFilesize}}MB.',
            dictInvalidFileType: 'You can only upload XLSX and CSV files.',
            dictResponseError: 'Server responded with {{statusCode}} code.',
            dictCancelUpload: 'Cancel upload',
            dictRemoveFile: 'Remove file',
            dictMaxFilesExceeded: 'You can not upload any more files (Max: ' + maxFiles + ').',
            
            init: function() {
              var myDropzone = this;
              
              // Add CSRF token to headers
              this.on('sending', function(file, xhr, formData) {
                var token = $('meta[name="csrf-token"]').attr('content');
                if (token) {
                  xhr.setRequestHeader('X-CSRF-TOKEN', token);
                }
                formData.append('field_name', fieldName);
              });
              
              // Handle successful upload
              this.on('success', function(file, response) {
                if (response.fid) {
                  // Add hidden input with file ID
                  var hiddenInput = $('<input>')
                    .attr('type', 'hidden')
                    .attr('name', fieldName + '[fids][]')
                    .attr('value', response.fid)
                    .attr('data-fid', response.fid);
                  
                  $element.append(hiddenInput);
                  
                  // Update file info
                  file.serverId = response.fid;
                  file.serverFilename = response.filename;
                  file.previewElement.setAttribute('data-fid', response.fid);
                  
                  // Add file info to preview
                  var fileInfo = $('<div class="file-info">')
                    .html('<strong>File ID:</strong> ' + response.fid + '<br>' +
                          '<strong>Size:</strong> ' + Drupal.behaviors.dropzoneWidget.formatFileSize(response.size));
                  $(file.previewElement).append(fileInfo);
                  
                  Drupal.behaviors.dropzoneWidget.showMessage('status', 'File uploaded successfully: ' + response.original_name);
                }
              });
              
              // Handle file removal
              this.on('removedfile', function(file) {
                if (file.serverId) {
                  // Remove hidden input
                  $('input[data-fid="' + file.serverId + '"]', $element).remove();
                  
                  // Send delete request to server
                  $.ajax({
                    url: '/file/delete/' + file.serverId,
                    type: 'DELETE',
                    headers: {
                      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                      if (response.success) {
                        Drupal.behaviors.dropzoneWidget.showMessage('status', 'File deleted successfully');
                      }
                    },
                    error: function(xhr, status, error) {
                      console.error('Delete error:', error);
                      Drupal.behaviors.dropzoneWidget.showMessage('error', 'Failed to delete file from server');
                    }
                  });
                }
              });
              
              // Handle upload errors
              this.on('error', function(file, errorMessage, xhr) {
                console.error('Upload error:', errorMessage);
                var message = typeof errorMessage === 'string' ? errorMessage : errorMessage.error || 'Upload failed';
                Drupal.behaviors.dropzoneWidget.showMessage('error', message);
              });
              
              // Handle upload progress
              this.on('uploadprogress', function(file, progress, bytesSent) {
                if (progress === 100) {
                  Drupal.behaviors.dropzoneWidget.showMessage('status', 'Processing file: ' + file.name);
                }
              });
              
              // Handle queue complete
              this.on('queuecomplete', function() {
                Drupal.behaviors.dropzoneWidget.showMessage('status', 'All files processed');
              });
            },
            
            // Custom preview template
            previewTemplate: [
              '<div class="dz-preview dz-file-preview">',
              '  <div class="dz-details">',
              '    <div class="dz-filename"><span data-dz-name></span></div>',
              '    <div class="dz-size" data-dz-size></div>',
              '  </div>',
              '  <div class="dz-progress"><span class="dz-upload" data-dz-uploadprogress></span></div>',
              '  <div class="dz-success-mark"><span>✓</span></div>',
              '  <div class="dz-error-mark"><span>✗</span></div>',
              '  <div class="dz-error-message"><span data-dz-errormessage></span></div>',
              '  <div class="dz-remove-container">',
              '    <a class="dz-remove" href="javascript:undefined;" data-dz-remove>Remove file</a>',
              '  </div>',
              '</div>'
            ].join('')
          });
          
          // Store dropzone instance
          $element.data('dropzone', dropzone);
          
          // Load existing files if any
          Drupal.behaviors.dropzoneWidget.loadExistingFiles($element, dropzone);
        });
      },
      
      /**
       * Show status/error messages.
       */
      showMessage: function(type, message) {
        var $messagesContainer = $('.dropzone-messages');
        if ($messagesContainer.length === 0) {
          $messagesContainer = $('<div class="dropzone-messages"></div>');
          $('.dropzone-container').first().prepend($messagesContainer);
        }
        
        var messageClass = type === 'error' ? 'messages--error' : 'messages--status';
        var messageHtml = '<div class="messages ' + messageClass + '">' + 
                         '<div class="messages__content">' + message + '</div>' +
                         '</div>';
        
        $messagesContainer.html(messageHtml);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
          $messagesContainer.find('.messages').fadeOut(function() {
            $(this).remove();
          });
        }, 5000);
      },
      
      /**
       * Format file size for display.
       */
      formatFileSize: function(bytes) {
        if (bytes === 0) return '0 Bytes';
        var k = 1024;
        var sizes = ['Bytes', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
      },
      
      /**
       * Load existing files into dropzone.
       */
      loadExistingFiles: function($element, dropzone) {
        var existingFiles = $element.find('input[name*="[fids]"]');
        
        existingFiles.each(function() {
          var fid = $(this).val();
          if (fid) {
            // You could make an AJAX call here to get file details
            // For now, just create a mock file object
            var mockFile = {
              name: 'Existing file (ID: ' + fid + ')',
              size: 0,
              serverId: fid,
              accepted: true
            };
            
            dropzone.emit('addedfile', mockFile);
            dropzone.emit('complete', mockFile);
          }
        });
      }
    };
  
  })(jQuery, Drupal, drupalSettings, once);