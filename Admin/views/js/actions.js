var customerID = '';
var cache = {};
var loggedInUser = '';
var lookup_active = false;
var lookup_cust_id = "";

var enquire_account = false;
var enquire_acct_val = "";

$(function () {
  addSideBoxAction();
  loggedInUser = $('input#loggedInUser').val();

  $('.button-left').click(function () {
    //$('.sidebar').toggleClass('fliph');
  });

  $('.menu-item').click(function () {

  });

  $.ajaxSetup({
        data: {
            request_type: 'async'
        },
        error:function(response) {
            if(response.status == 401 || response.status == 404 ) {
                location.reload();
            }
        }
    })
});


//function to create alert for the customer by passing the title of the alert and the message/content to this function when called
function createCustomAlert(alertTitle, alertContent) {
  $('body').append('<div class="alertOverlay"></div><div class="alert" align="center"><table width="100%"><tr><td><span class="title" align="left">' + alertTitle + '</span></td><td align="right"><a id="alert-OK" href="" class="red-button-small">x</a></td><tr></table><hr/><p>' + alertContent + '</p><p>&nbsp;<p></div>');
  $('div.alertOverlay').animate({
    'opacity': '0.5'
  }, 500);
  $('div.alert a#alert-OK').bind('click', function (e) {
    e.preventDefault();
    $('.alertOverlay').remove();
    $('.alert').remove();
  });
}


//this function checks to determine if additional meun(s) should be added to the dashboard. 
function addSideBoxAction() {
  $('.sub-menu li a').bind('click', function () {
    $('.sub-menu li a').removeClass('active');
    $(this).addClass('active');

    var title = $(this).attr('title');
    if (title) {
      $.ajax({
        url: '../src/util.php',
        data: {
          'action': title
        }, // calling util file
        type: 'post',
        success: function (response) {
          $('.content').html(response); //adding response from the ajax call
          getExtraActions(title);
        }
      });
    }
  });

  /*

  if ($('ul#side-nav li').children().length == 0) {
    $('li a[title="createChangePasswordForm"]').trigger('click');
  }
    */
}

//function to add more menu to the dashboard
function getExtraActions(val) {
  switch (val) {
    case "createNewUserSection": {
      createNewUserSection();
      break;
    }

    case "createAuthorizeAccountSetup":{
      createAuthorizeAccountSetup();
      break;
    }

    case "createAuthorizePasswordReset":{
      createAuthorizePasswordReset();
      break;
    }


    case "createNewCustomerSection": {
      createNewCustomerSection();
      break;
    }

    case "createManageCustomerSection": {
      createManageCustomerSection();
      break;
    }

    case "createTransactionReportSection": {
      createTransactionReportSection();
      break;
    }
    

    case "createAccessRightSection": {
      createAccessRightSection();
      break;
    }

  
    case "createDeleteUsersSection": {
      createDeleteUsersSection();
      break;
    }

    case "createChangePasswordForm": {
      createChangePasswordForm();
      break;
    }

    case "createChangePasswordForm": {
      createChangePasswordForm();
      break;
    }

    case "createAuditLogViewerSection": {
      createAuditLogViewerSection();
      break;
    }

    case "createLimitApprovalSection": {
      createLimitApprovalSection();
      break;
    }

    case "createAccountLinkSection": {
      createAccountLinkSection();
      break;
    }

  }
}


function createAuditLogViewerSection() {

    $('.datepicker').datepicker({ //date picker added to form
        dateFormat: 'yy-mm-dd',
        changeMonth:true,
        changeYear:true
    });

    // Load audit logs button
    $('#load-audit-logs').click(function() {
        loadAuditLogs();
    });

    
    
    // Auto-load logs on page load
    loadAuditLogs();
}

function loadAuditLogs() {
    var startDate = $('#audit_start_date').val();
    var endDate = $('#audit_end_date').val();
    var category = $('#audit_category').val();
    var user = $('#audit_user').val();
    
    if (!startDate || !endDate) {
        alert('Please select both start and end dates');
        return;
    }
    
    // Show loading
    $('#audit-logs-content').html('<div style="text-align: center; padding: 20px;"><img src="../views/images/loading.gif" alt="Loading..."/> Loading audit logs...</div>');
    
    $.post('../src/util.php', {
        'action': 'getAuditLogsData',
        'startDate': startDate,
        'endDate': endDate,
        'category': category,
        'user': user
    }, function(data) {
        try {
            var response = JSON.parse(data);
            displayAuditLogs(response);
        } catch (e) {
            console.error('Error parsing audit logs data:', e);
            $('#audit-logs-content').html('<div style="color: red; text-align: center; padding: 20px;">Error loading audit logs. Please try again.</div>');
        }
    }).fail(function() {
        $('#audit-logs-content').html('<div style="color: red; text-align: center; padding: 20px;">Error loading audit logs. Please try again.</div>');
    });
}

function displayAuditLogs(response) {
    var logs = response.logs || [];
    var stats = response.stats || {};
    
    // Display stats
    displayAuditStats(stats);
    
    if (logs.length === 0) {
        $('#audit-logs-content').html('<div style="text-align: center; padding: 20px; color: #666;">No audit logs found for the selected criteria</div>');
        return;
    }
    
    // Prepare data for createTblFromArray
    var tableData = logs.map(function(log) {
        return {
            'logdate': log.logdate,
            'usr': log.usr,
            'activity': log.activity,
            'category': log.category || 'No Category',
            'ipaddress': log.ipaddress
        };
    });
    
    // Use AJAX to get the table HTML from PHP
    $.post('../src/util.php', {
        'action': 'createAuditLogTable',
        'data': JSON.stringify(tableData)
    }, function(tableHtml) {
        $('#audit-logs-content').html(tableHtml);
        var currentPage = 0;
        // Initialize DataTable
        $('#audit-logs-table').DataTable({
            "pageLength": 25,
            "order": [[0, "desc"]],
            "columnDefs": [
                { "orderable": false, "targets": [2, 4] }
            ],
            "dom": 'Bfrtip',
            "buttons": [
                'copy', 'csv', 'excel', 'pdf', 'print'
            ],
            "language": {
                "search": "Search logs:",
                "lengthMenu": "Show _MENU_ logs per page",
                "info": "Showing _START_ to _END_ of _TOTAL_ logs",
                "infoEmpty": "No logs available",
                "infoFiltered": "(filtered from _MAX_ total logs)"
            },
            drawCallback: function(settings) {
                var api = this.api();
                var pageInfo = api.page.info();
                if (pageInfo.page !== currentPage) {
                    currentPage = pageInfo.page;
                    addAuditLogStyling();
                }
            }
        });
        
        // Add custom styling after table is created
        addAuditLogStyling();
    }).fail(function() {
        $('#audit-logs-content').html('<div style="color: red; text-align: center; padding: 20px;">Error loading audit logs. Please try again.</div>');
    });
}

function displayAuditStats(stats) {
    if (!stats || Object.keys(stats).length === 0) {
        $('#audit-stats').hide();
        return;
    }
    
    var statsHtml = '<div style="display: flex; justify-content: space-around; flex-wrap: wrap;">' +
        '<div class="stat-item" style="text-align: center; margin: 5px; padding: 10px; background: white; border-radius: 5px; min-width: 120px;">' +
            '<div style="font-size: 24px; font-weight: bold; color: #2c3e50;">' + (stats.total_logs || 0) + '</div>' +
            '<div style="font-size: 12px; color: #7f8c8d;">Total Logs</div>' +
        '</div>' +
        '<div class="stat-item" style="text-align: center; margin: 5px; padding: 10px; background: white; border-radius: 5px; min-width: 120px;">' +
            '<div style="font-size: 24px; font-weight: bold; color: #27ae60;">' + (stats.unique_users || 0) + '</div>' +
            '<div style="font-size: 12px; color: #7f8c8d;">Unique Users</div>' +
        '</div>' +
        '<div class="stat-item" style="text-align: center; margin: 5px; padding: 10px; background: white; border-radius: 5px; min-width: 120px;">' +
            '<div style="font-size: 24px; font-weight: bold; color: #3498db;">' + (stats.unique_categories || 0) + '</div>' +
            '<div style="font-size: 12px; color: #7f8c8d;">Categories</div>' +
        '</div>' +
        '<div class="stat-item" style="text-align: center; margin: 5px; padding: 10px; background: white; border-radius: 5px; min-width: 120px;">' +
            '<div style="font-size: 12px; color: #7f8c8d;">Date Range</div>' +
            '<div style="font-size: 11px; color: #2c3e50;">' + formatDate(stats.earliest_log) + ' to ' + formatDate(stats.latest_log) + '</div>' +
        '</div>' +
    '</div>';
    
    $('#audit-stats-content').html(statsHtml);
    $('#audit-stats').show();
}


function formatDateTime(dateTimeStr) {
    if (!dateTimeStr) return '';
    var date = new Date(dateTimeStr);
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    var date = new Date(dateStr);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: '2-digit'
    });
}

function getTimeAgo(dateTimeStr) {
    if (!dateTimeStr) return '';
    var date = new Date(dateTimeStr);
    var now = new Date();
    var diffMs = now - date;
    var diffMins = Math.floor(diffMs / 60000);
    var diffHours = Math.floor(diffMins / 60);
    var diffDays = Math.floor(diffHours / 24);
    
    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return diffMins + ' min ago';
    if (diffHours < 24) return diffHours + ' hour' + (diffHours > 1 ? 's' : '') + ' ago';
    if (diffDays < 7) return diffDays + ' day' + (diffDays > 1 ? 's' : '') + ' ago';
    return formatDate(dateTimeStr);
}

function escapeHtml(text) {
    if (!text) return '';
    var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

function addAuditLogStyling() {
    // Add custom styling to the generated table
    $('#audit-logs-table tbody tr').each(function() {
        var $row = $(this);
        var category = $row.find('td:eq(3)').text().trim();
        var user = $row.find('td:eq(1)').text().trim();
        var activity = $row.find('td:eq(2)').text().trim();
        
        // Add category badge
        if (category && category !== 'No Category') {
            var badgeClass = getCategoryBadgeClass(category);
            $row.find('td:eq(3)').html('<span class="badge ' + badgeClass + '">' + escapeHtml(category) + '</span>');
        }
        
        // Add user badge
        $row.find('td:eq(1)').html('<span class="user-badge">' + escapeHtml(user) + '</span>');
        
        // Add time ago to date column
        var dateText = $row.find('td:eq(0)').text().trim();
        if (dateText) {
            var timeAgo = getTimeAgo(dateText);
            $row.find('td:eq(0)').html(
                '<div style="font-weight: bold;">' + formatDateTime(dateText) + '</div>' +
                '<div style="font-size: 11px; color: #666;">' + timeAgo + '</div>'
            );
        }
        
        // Style IP address
        var ipAddress = $row.find('td:eq(4)').text().trim();
        $row.find('td:eq(4)').html('<code>' + escapeHtml(ipAddress) + '</code>');
        
        // Style activity column
        $row.find('td:eq(2)').css({
            'max-width': '300px',
            'word-wrap': 'break-word'
        });
    });
}

function getCategoryBadgeClass(category) {
    if (!category) return 'badge-secondary';
    
    var categoryLower = category.toLowerCase();
    
    if (categoryLower.includes('user') || categoryLower.includes('management')) {
        return 'badge-primary';
    } else if (categoryLower.includes('loan') || categoryLower.includes('credit')) {
        return 'badge-success';
    } else if (categoryLower.includes('transaction') || categoryLower.includes('payment')) {
        return 'badge-info';
    } else if (categoryLower.includes('card') || categoryLower.includes('debit')) {
        return 'badge-warning';
    } else if (categoryLower.includes('admin') || categoryLower.includes('system')) {
        return 'badge-dark';
    } else if (categoryLower.includes('error') || categoryLower.includes('failed')) {
        return 'badge-danger';
    }
    
    return 'badge-secondary';
}

function createTransactionReportSection() {
  $(".datepicker").datepicker({
    dateFormat: "yy-mm-dd",
    changeMonth: true,
    changeYear: true,
  });



  $('#get-transaction-report').bind('click', function () {
    $.ajax({
      url: '../src/util.php',
      data: {
        action : 'getTransactionReport',
        start_date: $('#start_date').val(),
        end_date: $('#end_date').val()
      },
      type: 'post',
      success: function (response) {
         $('#topup-application-content').html(response); 

         $('table.listing').DataTable({
          dom: 'QBlfrtip',
          buttons: [
              'copyHtml5',
              'excelHtml5',
              'csvHtml5'
          ]
        });
      }
    });
  })
  
}


function createLimitApprovalSection() {
  $(".datepicker").datepicker({
    dateFormat: "yy-mm-dd",
    changeMonth: true,
    changeYear: true,
  });



  $('#get-limit-approval').bind('click', function () {
    $.ajax({
      url: '../src/util.php',
      data: {
        action : 'getPendingLimitApproval',
        start_date: $('#start_date').val(),
        end_date: $('#end_date').val()
      },
      type: 'post',
      success: function (response) {
         $('#topup-application-content').html(response); 

         $('a.green-button-small, a.red-button-small').bind('click', function () {
            if(confirm('Are you sure you want to continue')) {
              $.ajax({
                url: '../src/util.php',
                data: {
                  action : 'authorizeLimitChange',
                  rowId: $(this).attr('id'),
                  status: $(this).attr('alt'),
                },
                type: 'post',
                success: function (response) {
                  alert("Account has been updated ");
                }
              });
            }
         });

      }
    });
  });
}

function createAccountLinkSection() {
  $(".datepicker").datepicker({
    dateFormat: "yy-mm-dd",
    changeMonth: true,
    changeYear: true,
  });



  $('#get-link-approval').bind('click', function () {
    $.ajax({
      url: '../src/util.php',
      data: {
        action : 'getPendingLinkApproval',
        start_date: $('#start_date').val(),
        end_date: $('#end_date').val()
      },
      type: 'post',
      success: function (response) {
         $('#topup-application-content').html(response); 

         $('a.green-button-small, a.red-button-small').bind('click', function () {
            if(confirm('Are you sure you want to continue')) {
              $.ajax({
                url: '../src/util.php',
                data: {
                  action : 'linkAccount',
                  rowId: $(this).attr('id'),
                  status: $(this).attr('alt'),
                },
                type: 'post',
                success: function (response) {
                  alert("Account has been updated");
                }
              });
            }
         });

      }
    });
  });
}


function createManageCustomerSection() {
  $('#get-customers').bind('click', function () {
    if( $('#customerID').val().trim() != "" && $(this).val() !='Processing') {
      $.ajax({
        url: '../src/util.php',
        data: {
          action : 'getAllUsersByCustomerID',
          customerID: $('#customerID').val()
        },
        type: 'post',
        success: function (response) {
           $('#topup-application-content').html(response); 

          $('span.green-button-small[alt!="ChangeTxnLimit"][alt!="AddNew"], span.red-button-small[alt!="UnlinkAccount"]').bind('click', function () {
            if(confirm('Are you sure you want to continue')) {
              $.ajax({
                url: '../src/util.php',
                data: {
                  action : 'updateUserStatus',
                  userID: $(this).attr('title'),
                  status: $(this).attr('alt'),
                },
                type: 'post',
                success: function (response) {
                  alert("Account has been updated ");
                   $('#get-customers').trigger('click');
                }
              });
            }
         });


         $('span.red-button-small[alt="UnlinkAccount"]').bind('click', function () {
            if(confirm('Are you sure you want to continue')) {
              $.ajax({
                url: '../src/util.php',
                data: {
                  action : 'unLinkAccount',
                  rowId: $(this).attr('title')
                },
                type: 'post',
                success: function (response) {
                  alert("Account has been updated ");
                   $('#get-customers').trigger('click');
                }
              });
            }
         });


         $('span.green-button-small[alt="AddNew"]').bind('click', function () {
            $.ajax({
                url: '../src/util.php',
                data: {
                  action : 'getLinkedAccountUI', 
                  cod_cust: $(this).attr('title')
                },
                type: 'post',
                success: function (response) {
                    createCustomAlert('Add Other CustomerID',response);

                      $('#linkedCustomerID').bind('blur', function () {
                        //alert($(this).val());
                        if($(this).val() != $('#mainCustomerID').val()) {
                          $.ajax({
                            url: '../src/util.php',
                            data: {
                              action : 'validateCustomerByID',
                              customerID: $(this).val()
                            },
                            type: 'post',
                            success: function (response) {
                              if(response) {
                                $('#linkedCustomerName').val(response);
                              }
                            }
                          });
                        }else {
                          alert("Invalid CustomerID, you can't link an account to itself");
                        }
                      });



                    $('#linkCustomer').bind('click', function() {
                      if(confirm('Are you sure you want to proceed?') && $('#linkedCustomerName').val() != "") {
                          $.ajax({
                            url: '../src/util.php',
                            data: {
                              action : 'linkAccountRequest',
                              customerID: $('#mainCustomerID').val(),
                              linkedCustomerID:$('#linkedCustomerID').val()
                            },
                            type: 'post',
                            success: function (response) {
                              alert("Logged for Approval");
                            }
                          });
                      }
                    });
                }
            });
         });

         $('span.green-button-small[alt="ChangeTxnLimit"]').bind('click', function () {
            $.ajax({
                url: '../src/util.php',
                data: {
                  action : 'getChangeTxnLimitUI',
                  userID: $(this).attr('title')
                },
                type: 'post',
                success: function (response) {
                    createCustomAlert('Change Transaction Limit',response);

                    $('#changeLimit').bind('click', function() {
                      if(confirm('Are you sure you want to proceed?')) {
                          $.ajax({
                            url: '../src/util.php',
                            data: {
                              action : 'updateTxnLimit',
                              userID: $('#userID').val(),
                              singleTxnLimit:$('#singleTxnLimit').val(),
                              dailyTxnLimit:$('#dailyTxnLimit').val()
                            },
                            type: 'post',
                            success: function (response) {
                              alert("Change has been logged for approval");

                            }
                          });
                      }
                    });
                }
            });
         });
        }
      });
    }
  });
}

function createAuthorizeAccountSetup() {
  $('#get-customers').bind('click', function () {
    if( $('#customerID').val().trim() != "" && $(this).val() !='Processing') {
      $.ajax({
        url: '../src/util.php',
        data: {
          action : 'getPendingSetup',
          customerID: $('#customerID').val()
        },
        type: 'post',
        success: function (response) {
           $('#topup-application-content').html(response); 

           $('span.green-button-small, span.red-button-small').bind('click', function () {
              if(confirm('Are you sure you want to continue')) {
                $.ajax({
                  url: '../src/util.php',
                  data: {
                    action : 'updateUserStatus',
                    userID: $(this).attr('title'),
                    status: $(this).attr('alt'),
                  },
                  type: 'post',
                  success: function (response) {
                    alert("Done");
                  }
                });
              }
           });
        }
      });
    }
  });
}


function createAuthorizePasswordReset() {
  $('#get-customers').bind('click', function () {
    if( $('#customerID').val().trim() != "" && $(this).val() !='Processing') {
      $.ajax({
        url: '../src/util.php',
        data: {
          action : 'getPendingAuthorization',
          customerID: $('#customerID').val()
        },
        type: 'post',
        success: function (response) {
           $('#topup-application-content').html(response); 

           $('span.green-button-small, span.red-button-small').bind('click', function () {
            if(confirm('Are you sure you want to continue')) {
              $.ajax({
                url: '../src/util.php',
                data: {
                  action : 'updateUserStatus',
                  userID: $(this).attr('title'),
                  status: $(this).attr('alt'),
                },
                type: 'post',
                success: function (response) {
                  alert("Done");
                }
              });
            }
         });
        }
      });
    }
  });
}



function createNewCustomerSection() {

  $('#customerID').bind('blur', function () {
    $.ajax({
      url: '../src/util.php',
      data: {
        action : 'validateCustomerByID',
        customerID: $(this).val()
      },
      type: 'post',
      success: function (response) {
        if(response) {
          $('#customerName').val(response);
        }
      }
    });
  });


    $('#userID').bind('blur', function () {
    $.ajax({
      url: '../src/util.php',
      data: {
        action : 'validateUserID',
        userID: $(this).val()
      },
      dataType:'JSON',
      type: 'post',
      success: function (response) {
        if(response.success == true) {
            $('#createNewCustomer').prop('disabled', false);
        }else {
          $('#createNewCustomer').prop('disabled', true);
        }
        alert(response.message);
      }
    });
  });


  $('#createNewCustomer').bind('click', function () {
    var etarget = $(this);
    $("#createNewCustomerForm").validate({
      rules: {
        email: {
          required: true,
          email: true
        },
        customerName: {
          required: true
        },
        Firstname: {
          required: true
        },
        Lastname: {
          required: true
        },
        mobile: {
          required: true, 
          digits: true,
          minlength: 9,
          maxlength: 11
        },
        txnLimit: {
          required:true,
          number:true
        },
        customerID: {
          required: true
        }
      },
      messages: {
        email: {
          required: "Please enter an email address",
          email: "Please enter a valid email address"
        },
        txnLimit: {
          required: "Please enter a valid amount",
          number:"Only valid Amount is required"
        }
      }
    });

    if($("#createNewCustomerForm").valid() && $(this).val() !='Processing' && confirm('Are you sure you want to continue')) {
      $.ajax({
        url: '../src/util.php',
        data: {
          'action': 'createNewCustomer',
          'formContent': $('#createNewCustomerForm').serialize()
        },
        beforeSend: function () {
          $(etarget).val('Processing');
        },
        type: 'post',
        success: function (response) {
          $(etarget).val('Create New Customer');
          alert(response);
          if(response =='Customer has been created and now waiting for Authorization') {
            $('ul#admin li a[title="createNewCustomerSection"]').trigger('click');
          }
        }
      });
    }
  });
}
    


function createDeleteUsersSection() {
  $('a.red-button-small').bind('click', function() {
      var userID = $(this).attr('alt');
      if (confirm("Are you sure you want to remove this user?")) {
          $.ajax({
              url: '../src/util.php',
              data: {
                  'action': 'removeUser',
                  'userID': userID
              },
              type: 'post',
              success: function(response) {
                  alert(response);
              }
          });
      }
  });

  $('a.green-button-small').bind('click', function() {
      var userID = $(this).attr('alt');
      if (confirm("Are you sure you want to reset user's password")) {
          $.ajax({
              url: '../src/util.php',
              data: {
                  'action': 'resetUserPassword',
                  'userID': userID
              },
              type: 'post',
              success: function(response) {
                  alert(response);
              }
          });
      }
  });
}


function createNewUserSection() {

  $('#saveSubmit').bind('click', function () {
    var etarget = $(this);
    if ($(this).val() != 'Processing' && confirm('Are you sure you want to continue')) {
      $.ajax({
        url: '../src/util.php',
        data: {
          'action': 'createNewUser',
          'formContent': $(etarget).parents('form').serialize()
        },
        beforeSend: function () {
          $(etarget).val('Processing');
        },
        type: 'post',
        dataType: 'json',
        success: function (response) {
          $(etarget).val('Create New User');
          alert(response.message);
        }
      });
    } else {
      alert('System busy');
    }
  });
}


function createAccessRightSection() {
  $('#get-role-rights').bind('click', function () {
    $.ajax({
      url: '../src/util.php',
      data: {
        'action': 'getRoleRights',
        'roleID': $('#roleID').val()
      },
      type: 'post',
      beforeSend: function () {
        $('#get-role-rights').val('Procesing...');
      },
      success: function (response) {
        $('#get-role-rights').val('Load Rights');
        $('#topup-application-content').html(response);

        $('a.red-button-small').bind('click', function () {
          var el = $(this);
          if (confirm("Are you sure you want to revoke access?")) {
            $.ajax({
              url: '../src/util.php',
              data: {
                'action': 'revokeAccessRights',
                'serviceID': $(this).attr('id'),
                'roleID': $(this).attr('title')
              },
              beforeSend: function () {
                $(el).html("Processing...")
              },
              type: 'post',
              success: function (response) {
                alert(response);
                $('#get-role-rights').trigger('click');
              }
            });
          }
        });

        $('#add-role-rights').bind('click', function () {
          if (confirm("Are you sure you want to grant access?")) {
            $.ajax({
              url: '../src/util.php',
              data: {
                'action': 'grantAccessRights',
                'serviceID': $('#new_right_ID').val(),
                'roleID': $('#role_id_val').val()
              },
              type: 'post',
              beforeSend: function () {
                $('#add-role-rights').val('Processing...');
              },
              success: function (response) {
                alert(response);
                $('#get-role-rights').trigger('click');
              }
            });
          }
        });
      }
    });
  });
}
 
function validateRequiredFields(desc) {
  message = false;
  $(desc).each(function () {
    if ($(this).val().trim() == "") {
      $(this).css({
        'border-color': '#f00'
      });
      valid = true;
      message = "Please fill all the compulsory fields";
    }

    if (message == false) {
      $(this).css({
        'border-color': '#ccc'
      });
    }

    if ($(this).attr('id').indexOf('Mobile') != -1) {
      if ($(this).val().length > 16 || isNaN($(this).val())) {
        $(this).css({
          'border-color': '#f00'
        });
        valid = true;
        message = "Please supply a valid mobile number";
      }
    }

    if ($(this).attr('id').indexOf('Email') != -1) {
      if (!isValidEmailAddress($(this).val())) {
        $(this).css({
          'border-color': '#f00'
        });
        valid = true;
        message = "Please supply a valid Email address";
      }
    }

  });

  return message;
}

function createChangePasswordForm() {
  $('#changePasswordSubmit').bind('click', function () {
    var msg = validateRequiredFields($('#changePassword').children().find('.required'));
    if (msg) {
      alert(msg);
    } else {
      if ($('#password').val().length < 8) {
        alert('Password should be at least 8 characters long');
      } else if (($('#password').val().length >= 8) && ($.trim($('#confirmpassword').val()) == $.trim($('#password').val())) && ($.trim($('#oldpassword').val()) != $.trim($('#password').val()))) {
        $.ajax({
          url: '../src/util.php',
          data: {
            'action': 'changePassword',
            'oldPassword': $('#oldpassword').val(),
            'Password': $('#password').val()
          },
          type: 'post',
          success: function (response) {
            alert(response);
            location.reload(true);
          }
        });
      } else {
        if ($('#confirmpassword').val() != $('#password').val()) {
          alert('Please confirm the new password');
        }

        if ($('#oldpassword').val() == $('#password').val()) {
          alert('Please use a new password');
        }
      }
    }
  });
}




