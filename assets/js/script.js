jQuery(document).ready(function($) {
        /* --------------------------------------------------------------------------------------------------
         Settings 
         --------------------------------------------------------------------------------------------------*/
$("#bulk_add_all_post").click(function(e) {
    Swal.fire({
        title: 'Are you sure?',
        text: 'You will add the rating for all post Choose the palce',
        html: `
            <label for="position-select">Where to insert the rating?</label><br>
            <select id="position-select-bulk" class="swal2-select" style="width: 87%; padding: 8px; margin:0px; margin-top: 10px;">
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
            const select = document.getElementById('position-select-bulk');
            const value = select ? select.value : null;
            if (!value) {
                Swal.showValidationMessage('You must select a position');
            }
            return value;
        }
    }).then(function(result) {
        if (result.isConfirmed && result.value) {
            const position = result.value;

            $.ajax({
                type: 'POST',
                url: ajaxurl + '?action=myplugin_get_rating_data_bulk_add',
                data: { position: position },
                dataType: 'json',
                success: function (res) {
                    if (res.success === false){
                        Swal.fire("Sorry!", res.msg, "error");
                    } else {
                        Swal.fire("Done", res.msg, "success");
                        // setTimeout(function() {
                        //     location.reload();
                        // }, 700);
                    }
                },
                error: function () {
                    toastr.error("We have some error!");
                }
            });
        }
    });
});



              $("#bulk_delete_all_post").click(function(e) {
        // Display a confirmation message using SweetAlert
        swal.fire({
            title: 'Are you sure?',
            text: 'You will delete the rating for all post',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, submit it!',
            cancelButtonText: 'No, cancel it'
        }).then(function (e) {
            if (e.isConfirmed) {
                var type = "post";

                $.ajax({
                    type: type,
                    url:  ajaxurl + '?action=myplugin_get_rating_data_bulk_delete',
                    dataType: 'json',
                    success: function (res) {
                        if (res.success == false){
                            swal.fire("sorry!", res.msg, "error");
                        }else{
                            swal.fire("Done", res.msg, "success");
// Reload the page after 0.5 seconds
                            setTimeout(function() {
                                location.reload();
                            }, 700);
                        }
                    },
                    error: function (data) {
                        toastr.error("we have some error!");
                    }
                });
            }



        });
    });
              $("#activate_google_snippet").click(function(e) {
        // Display a confirmation message using SweetAlert
        swal.fire({
            title: 'Are you sure?',
            text: 'You will Activate the google snippet and add to all post',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, submit it!',
            cancelButtonText: 'No, cancel it'
        }).then(function (e) {
            if (e.isConfirmed) {
                var type = "post";

                $.ajax({
                    type: type,
                    url:  ajaxurl + '?action=dt_star_rating_create_default_settings',
                     data: { 
                        dt_google: true ,
                        types: ["SoftwareApplication"]

                      },
                    dataType: 'json',
                    success: function (res) {
                        if (res.success == false){
                            swal.fire("sorry!", res.msg, "error");
                        }else{
                            swal.fire("Done", res.msg, "success");
                            // Reload the page after 0.5 seconds
                            $("#activate_google_snippet").remove();
                        $(".add_post_setting").append(`
                            <button class="add_post_setting" id="disactivate_google_snippet">Disactivate Google Snippet</button>
                        `);
                            setTimeout(function() {
                                location.reload();
                            }, 700);
                        }
                    },
                    error: function (data) {
                        console.log(data);
                        toastr.error("we have some error!");
                    }
                });
            }



        });
    });
                $("#disactivate_google_snippet").click(function(e) {
        // Display a confirmation message using SweetAlert
        swal.fire({
            title: 'Are you sure?',
            text: 'You will Deactivate the google snippet and delete to all post',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, submit it!',
            cancelButtonText: 'No, cancel it'
        }).then(function (e) {
            if (e.isConfirmed) {
                var type = "post";

                $.ajax({
                    type: type,
                    url:  ajaxurl + '?action=dt_star_rating_create_default_settings',
                     data: { 
                        dt_google: false ,
                        types: [""]

                      },
                    dataType: 'json',
                    success: function (res) {
                        if (res.success == false){
                            swal.fire("sorry!", res.msg, "error");
                        }else{
                            swal.fire("Done", res.msg, "success");
                            // Reload the page after 0.5 seconds
                            $("#activate_google_snippet").remove();
                        $(".add_post_setting").append(`
                         <button class="add_post_setting" id="activate_google_snippet">Activate Google Snippet</button>
                        `);
                            setTimeout(function() {
                                location.reload();
                            }, 700);
                        }
                    },
                    error: function (data) {
                        console.log(data);
                        toastr.error("we have some error!");
                    }
                });
            }



        });
    });
$("#save_type_google_snippet").click(function(e) {
const selectedTypes = [];
document.querySelectorAll('#rating-types:checked').forEach(checkbox => {
    selectedTypes.push(checkbox.value);
});


    swal.fire({
        title: 'Are you sure?',
        text: 'You will activate the Google snippet and add it to all posts',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, submit it!',
        cancelButtonText: 'No, cancel it'
    }).then(function(result) {
        if (result.isConfirmed) {
            $.ajax({
                type: "POST",
                url: ajaxurl,
                data: { 
                    action: 'dt_star_rating_create_default_settings',
                    dt_google: true,
                    types: selectedTypes
                },
                dataType: 'json',
                success: function(res) {
                    if (res.success === false) {
                        swal.fire("Sorry!", res.msg, "error");
                    } else {
                        swal.fire("Done", res.msg, "success");
                        $("#activate_google_snippet").remove();
                        $(".add_post_setting").append(`
                            <button class="add_post_setting" id="disactivate_google_snippet">Disactivate Google Snippet</button>
                        `);
                        setTimeout(function() {
                            location.reload();
                        }, 700);
                    }
                },
                error: function(data) {
                    console.log(data);
                    toastr.error("We have some error!");
                }
            });
        }
    });
});

    });



