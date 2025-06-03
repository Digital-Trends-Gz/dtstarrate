jQuery(document).ready(function($) {
    // Initialize the DataTable
    
    if (jQuery('#dt-rate-post-table').length) {
        jQuery('#dt-rate-post-table').DataTable({
            "lengthMenu": [[10, 25, 50, 100, 500], [10, 25, 50, 100, "500"]],
            "processing": true,
            "serverSide": true,
            stateSave: true,
            stateLoad: function (settings) {
                var savedState = JSON.parse(localStorage.getItem('DataTables_' + window.location.pathname));
                return savedState;
            },
            "ajax": {
                url: ajaxurl + '?action=myplugin_get_rating_data',
                type: "get",
                data: {
                    "filterring": $('#filterring').val(),
                },
                dataType: "json",
                contentType: "application/json; charset=utf-8",
                error: function () {
                    jQuery("#dt-rate-post-table").append('<tbody class="grid-error"><tr><th colspan="2">error.</th></tr></tbody>');
                }
            },
            "autoWidth": false,
            "columnDefs": [{"defaultContent": "-", "targets": "_all"}],
            "columns": [
                {data: 'index', orderable: false},
                {data: 'title', orderable: true},
                {data: 'avg', orderable: true},
                {data: 'total', orderable: true},
                          ],
    
        });
        

    }
    if (jQuery('#dt-rate-post-table_in_modal').length) {
        jQuery('#dt-rate-post-table_in_modal').DataTable({
            "lengthMenu": [[10, 25, 50, 100, 500], [10, 25, 50, 100, "500"]],
            "processing": true,
            "serverSide": true,
            stateSave: true,
            stateLoad: function (settings) {
                var savedState = JSON.parse(localStorage.getItem('DataTables_' + window.location.pathname));
                return savedState;
            },
            "ajax": {
                url: ajaxurl + '?action=dt_rate_specific_post',
                type: "get",
                data: {
                    "filterring": $('#filterring').val(),
                },
                dataType: "json",
                contentType: "application/json; charset=utf-8",
                error: function () {
                    jQuery("#dt-rate-post-table_in_modal").append('<tbody class="grid-error"><tr><th colspan="2">error.</th></tr></tbody>');
                }
            },
            "autoWidth": false,
            "columnDefs": [{"defaultContent": "-", "targets": "_all"}],
            "columns": [
                {data: 'ID', orderable: true},
                {data: 'title', orderable: true},
                {data: 'action', orderable: false},
                          ],
    
        });
         // Single event handler for all buttons
    $('#dt-rate-post-table_in_modal').on('click', '.add_short_code', function() {
        const button = $(this);
        const postId = button.data('id');
        const action = button.data('action');
        var shortcode = '[star_rating]'; // Your shortcode here

        
        // Add loading state
        button.prop('disabled', true).html('<i class="dashicons dashicons-update spin"></i>');
                swal.fire({
                  title: 'Are you sure?',
        text: 'You will add the rating for all post Choose the palce',
        html: `
            <label for="position-select">Where to insert the rating?</label><br>
            <select id="position-select" class="swal2-select" style="width: 87%; padding: 8px; margin:0px; margin-top: 10px;">
                <option value="0">First</option>
                <option value="1">Middle</option>
                <option value="2">End</option>
            </select>
        `,
        showCancelButton: true,
             icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, submit it!',
            cancelButtonText: 'No, cancel it',
        focusConfirm: false,
         preConfirm: () => {
            const select = document.getElementById('position-select');
            const value = select ? select.value : null;
            if (!value) {
                Swal.showValidationMessage('You must select a position');
            }
            return value;
        }
    }).then(function (e) {
            if (e.isConfirmed) {
            if (e.isConfirmed && e.value) {
                const position = e.value;
      $.ajax({
            url: ajaxurl +'?action=dt_rate_specific_post_add',
            type: 'POST',
         data: {
            post_id: postId,
            shortcode: shortcode,
            position: position
            },
            success: function(response) {
                 if (response.success == false){
                            swal.fire("sorry!", response.msg, "error");
                        }else{
                            swal.fire("Done", response.msg, "success");
                                   setTimeout(() => {
                        $('#dt-rate-post-table_in_modal').DataTable().ajax.reload();
                    }, 1000);
                        }
          
            },
            error: function(xhr) {
                button.html('Failed!').css('color', 'red');
                console.error('Error:', xhr.responseText);
            }
        });
            }}});
        
  
    });

    }

        });


