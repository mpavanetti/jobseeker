function dashboardNumber(value) {
  var number = parseInt(value, 10);
  return isNaN(number) ? 0 : number;
}

function dashboardArray(value) {
  return Array.isArray(value) ? value : [];
}

function dashboardPayloadData(payload) {
  return payload && payload.data && typeof payload.data === 'object' ? payload.data : {};
}

function dashboardFirstActivity(rows) {
  rows = dashboardArray(rows);
  return rows.length && rows[0] ? rows[0].last_activity : '';
}

function dashboardAjaxJson(url) {
  return $.ajax({
    type: 'GET',
    url: url,
    dataType: 'json'
  });
}

function dashboardSyncJson(path) {
  var response = $.ajax({
    contentType: 'application/json',
    url: dashboardUrl(path),
    dataType: 'json',
    async: false
  });

  try {
    return $.parseJSON(response.responseText || '{}');
  } catch (error) {
    return {data: {}};
  }
}

function dashboardSelectedEnvironment() {
  var value = $('#globalEnvironmentSelector').val() || window.jobseekerDashboardEnvironment || 'all';

  if (window.JobSeekerGlobalEnvironment && window.JobSeekerGlobalEnvironment.normalize) {
    return window.JobSeekerGlobalEnvironment.normalize(value);
  }

  return String(value || 'all');
}

function dashboardUrl(path) {
  var environment = dashboardSelectedEnvironment();

  if (! environment || environment === 'all') {
    return path;
  }

  return path + (path.indexOf('?') === -1 ? '?' : '&') + 'environment=' + encodeURIComponent(environment);
}

function dashboardAppUrl(path) {
  var appBase = typeof baseURL !== 'undefined' ? baseURL : '';
  return appBase.replace(/\/+$/, '/') + path.replace(/^\/+/, '');
}

function dashboardJenkinsApiUrl(path) {
  var baseUrl = window.jobseekerJenkinsUrl || '';
  return baseUrl.replace(/\/+$/, '') + '/' + path.replace(/^\/+/, '');
}

function dashboardJenkinsStats(computerData) {
  var totalExecutors = 0;
  var busyExecutors = 0;

  $.each(computerData && computerData.computer ? computerData.computer : [], function(index, node) {
    var executors = node.executors || [];
    totalExecutors += dashboardNumber(node.numExecutors || executors.length);

    $.each(executors, function(executorIndex, executor) {
      if (executor && executor.idle === false) {
        busyExecutors++;
      }
    });
  });

  return {
    total: totalExecutors,
    busy: busyExecutors
  };
}

function dashboardBuildLabel(value) {
  return dashboardNumber(value) === 1 ? 'build' : 'builds';
}

function dashboardShowMain() {
  $('#loading').fadeOut();
  $('#main').delay(500).fadeIn();
}

function dashboardSetEmptyCounter(selector) {
  $(selector).html('<b>0 / 0</b>');
}

function dashboardShowEmptyPanel(contentSelector, emptySelector) {
  $(contentSelector).hide();
  $(emptySelector).show();
}

function dashboardDisableEmptyDataTable(tableSelector, emptySelector) {
  var table = $(tableSelector);

  if ($.fn.DataTable && $.fn.DataTable.isDataTable(tableSelector)) {
    table.DataTable().destroy();
  }

  table.removeClass('dataTable dataTableMobile table-config').hide();
  table.closest('.dataTables_wrapper').hide();
  $(emptySelector).show();
}

function dashboardInitDataTable(selector, order) {
  var table = $(selector);

  if (! table.length || ! table.is(':visible') || table.hasClass('dataTableMobile')) {
    return true;
  }

  if (! $.fn.DataTable) {
    return false;
  }

  if ($.fn.DataTable.isDataTable(selector)) {
    return true;
  }

  table.DataTable({
    autoWidth: false,
    lengthMenu: [10, 20, 50, 100, 200, 500],
    order: order,
    pageLength: 10,
    scrollX: true
  });

  return true;
}

function dashboardInitTables() {
  if (! dashboardInitDataTable('#dashboardJobsAmountTable', [[3, 'desc']]) ||
      ! dashboardInitDataTable('#dashboardJobsStatusAmountTable', [[4, 'desc']]) ||
      ! dashboardInitDataTable('#dashboardEnvironmentSummaryTable', [[1, 'desc']])) {
    setTimeout(dashboardInitTables, 100);
  }
}

function dashboardShowEmptyDashboard() {
  $('#dashboardEmptyState').show();
  $('#running').html('<h3>0</h3>');
  $('#ready, #warning, #error').text('0');
  $('#date').html('<b>No monitoring records available yet.</b>');

  dashboardSetEmptyCounter('#runningGraph');
  dashboardSetEmptyCounter('#readyGraph');
  dashboardSetEmptyCounter('#warningGraph');
  dashboardSetEmptyCounter('#errorGraph');
  $('#runningGraphBar, #readyGraphBar, #warningGraphBar, #errorGraphBar').css('width', '0%');
  $('#pecentTotalRunning, #pecentTotalReady, #pecentTotalWarning, #pecentTotalError').html('<b>Not Available</b>');
  $('#totalJobs').html('Total of: <b>0</b> Jobs');
  dashboardShowEmptyPanel('#dashboardSurveyContent, #dashboardSurveyFooter', '#dashboardSurveyEmptyState');
  dashboardShowEmptyPanel('#dashboardPercentContent, #dashboardPercentFooter', '#dashboardPercentEmptyState');
  dashboardDisableEmptyDataTable('#dashboardJobsAmountTable', '#dashboardJobsAmountEmptyState');
  dashboardDisableEmptyDataTable('#dashboardJobsStatusAmountTable', '#dashboardJobsStatusAmountEmptyState');
  dashboardDisableEmptyDataTable('#dashboardEnvironmentSummaryTable', '#dashboardEnvironmentSummaryEmptyState');
  dashboardShowEmptyPanel('#dashboardDwChartContent', '#dashboardDwChartEmptyState');
  dashboardShowEmptyPanel('#dashboardDmChartContent', '#dashboardDmChartEmptyState');
  dashboardShowEmptyPanel('#dashboardFactChartContent', '#dashboardFactChartEmptyState');
  dashboardShowEmptyPanel('#dashboardStgChartContent', '#dashboardStgChartEmptyState');

  $('#readyGrowthDecline, #errorGrowthDecline, #warningGrowthDecline, #runningGrowthDecline, #readyGrowthDeclineX90, #errorGrowthDeclineX90, #warningGrowthDeclineX90, #runningGrowthDeclineX90, #readyGrowthDeclineX180, #errorGrowthDeclineX180, #warningGrowthDeclineX180, #runningGrowthDeclineX180').html('<h4 class="description-header">Not Available</h4>');

  $('#dwAmount, #dimTableAmount, #factTableAmount, #stgTableAmount').html('<b>0 </b>');

  dashboardShowMain();
}

function loadDashboardJenkinsOverview() {
  if (! window.jobseekerJenkinsUrl) {
    $('#dashboardJenkinsCapacity').text('--');
    $('#dashboardJenkinsQueue').text('--');
    $('#dashboardJenkinsDetail').text('Jenkins integration is unavailable.');
    return;
  }

  dashboardAjaxJson(dashboardUrl(dashboardAppUrl('jenkins/environmentSlots'))).done(function(payload) {
    var selectedEnvironment = dashboardSelectedEnvironment();
    var environments = payload && payload.environments ? payload.environments : {};
    var rows = [];

    if (selectedEnvironment && selectedEnvironment !== 'all') {
      rows.push($.extend({environment: selectedEnvironment}, environments[selectedEnvironment] || {running: 0, queued: 0, active: 0, limit: payload.defaultLimit || 1, available: payload.defaultLimit || 1}));
    } else {
      $.each(environments, function(environment, row) {
        rows.push($.extend({environment: environment}, row));
      });
    }

    var active = 0;
    var queued = 0;
    var running = 0;
    var limited = 0;
    var scope = selectedEnvironment && selectedEnvironment !== 'all' ? selectedEnvironment : 'All environments';
    var routingText = payload && payload.environmentAgentsEnabled === true ? ' Environment-agent routing is enabled.' : ' Environment-agent routing is disabled.';

    $.each(rows, function(index, row) {
      active += dashboardNumber(row.active);
      queued += dashboardNumber(row.queued);
      running += dashboardNumber(row.running);
      if (dashboardNumber(row.limit) > 0) {
        limited += dashboardNumber(row.limit);
      }
    });

    $('#dashboardJenkinsCapacity').text(limited > 0 ? active + ' / ' + limited : active + ' / unlimited');
    $('#dashboardJenkinsQueue').text(queued);
    $('#dashboardJenkinsDetail').text(scope + ' JobSeeker trigger slots: ' + running + ' running, ' + queued + ' queued.' + routingText + ' Last updated ' + moment().format('h:mm:ss a') + '.');
  }).fail(function() {
    $('#dashboardJenkinsCapacity').text('N/A');
    $('#dashboardJenkinsQueue').text('N/A');
    $('#dashboardJenkinsDetail').text('Unable to reach Jenkins.');
  });
}

 function running() {
      
      $.ajax({    //create an ajax request
        type: "GET",
        url: dashboardUrl("Dashboard/query/running"),
        dataType: "html",   //expect html to be returned   
         beforeSend: function() {

          $('#running h3').remove();
          $('.running').removeClass("flipInX");
         
        },             
        success: function(data){  
         $('.running').addClass("flipInX");
         $('#running').append('<h3>' + data + '</h3>');
        }
    });

}

  function ready() {
      
      $.ajax({    //create an ajax request
        type: "GET",
        url: dashboardUrl("Dashboard/query/ready"),
        dataType: "html",   //expect html to be returned   
         beforeSend: function() {

          $('#ready h3').remove();
          $('.ready').removeClass("flipInX");
         
        },             
        success: function(data){  
         $('.ready').addClass("flipInX");
         $('#ready').append('<h3>' + data + '</h3>');
        }
    });

}

  function warning() {
      
      $.ajax({    //create an ajax request
        type: "GET",
        url: dashboardUrl("Dashboard/query/warning"),
        dataType: "html",   //expect html to be returned   
         beforeSend: function() {

          $('#warning h3').remove();
          $('.warning').removeClass("flipInX");
         
        },             
        success: function(data){  
         $('.warning').addClass("flipInX");
         $('#warning').append('<h3>' + data + '</h3>');
        }
    });

}

  function error() {
      
      $.ajax({    //create an ajax request
        type: "GET",
        url: dashboardUrl("Dashboard/query/error"),
        dataType: "html",   //expect html to be returned   
         beforeSend: function() {

          $('#error h3').remove();
          $('.error').removeClass("flipInX");
         
        },             
        success: function(data){  
         $('.error').addClass("flipInX");
         $('#error').append('<h3>' + data + '</h3>');
        }
    });

}




function runningGraph(result) {
      
      $.ajax({    //create an ajax request
        type: "GET",
        url: dashboardUrl("Dashboard/query/running"),
        dataType: "html",   //expect html to be returned   
         beforeSend: function() {

         $('#runningGraph b').remove();
         $('.runningGraph').removeClass("fadeIn");
         $("#runningGraphBar").css("width", "0%");
         
        },             
        success: function(data){  
          var bar =  Math.round(data / result * 100) + '%';
          //console.log(bar)
         $('.runningGraph').addClass("fadeIn");
         $('#runningGraph').append('<b>' + data + ' / ' + result + '</b>');
         $("#runningGraphBar").css("width", bar);
         $('#pecentTotalRunning').append('<b>' + Math.round(((data*100) / result)) + ' %</b>');
        }
    });

}

function readyGraph(result) {
      
      $.ajax({    //create an ajax request
        type: "GET",
        url: dashboardUrl("Dashboard/query/ready"),
        dataType: "html",   //expect html to be returned   
         beforeSend: function() {

         $('#readyGraph b').remove();
         $('.readyGraph').removeClass("fadeIn");
         $("#readyGraphBar").css("width", "0%");
         
        },             
        success: function(data){  
          var bar =  Math.round(data / result * 100) + '%';
         // console.log(bar)
         $('.readyGraph').addClass("fadeIn");
         $('#readyGraph').append('<b>' + data + ' / ' + result + '</b>');
         $("#readyGraphBar").css("width", bar);

          $('#pecentTotalReady').append('<b>' + Math.round(((data*100) / result)) + ' %</b>');
          $('#totalJobs').append('Total of: <b>' + result + '</b> Jobs');
        }
    });

}

function warningGraph(result) {
      
      $.ajax({    //create an ajax request
        type: "GET",
        url: dashboardUrl("Dashboard/query/warning"),
        dataType: "html",   //expect html to be returned   
         beforeSend: function() {

         $('#warningGraph b').remove();
         $('.warningGraph').removeClass("fadeIn");
         $("#warningGraphBar").css("width", "0%");
         
        },             
        success: function(data){  
          var bar =  Math.round(data / result * 100) + '%';
        //  console.log(bar)
         $('.warningGraph').addClass("fadeIn");
         $('#warningGraph').append('<b>' + data + ' / ' + result + '</b>');
         $("#warningGraphBar").css("width", bar);
         $('#pecentTotalWarning').append('<b>' + Math.round(((data*100) / result)) + ' %</b>');
        }
    });

}

function errorGraph(result) {
      
      $.ajax({    //create an ajax request
        type: "GET",
        url: dashboardUrl("Dashboard/query/error"),
        dataType: "html",   //expect html to be returned   
         beforeSend: function() {

         $('#errorGraph b').remove();
         $('.errorGraph').removeClass("fadeIn");
         $("#errorGraphBar").css("width", "0%");
         
        },             
        success: function(data){  
          var bar =  Math.round(data / result * 100) + '%';
        //  console.log(bar)
         $('.errorGraph').addClass("fadeIn");
         $('#errorGraph').append('<b>' + data + ' / ' + result + '</b>');
         $("#errorGraphBar").css("width", bar);
         $('#pecentTotalError').append('<b>' + Math.round(((data*100) / result)) + ' %</b>');
        }
    });

}


  $(document).ready(function(){

    var result;

    loadDashboardJenkinsOverview();
    window.dashboardJenkinsRefreshTimer = window.dashboardJenkinsRefreshTimer || setInterval(loadDashboardJenkinsOverview, 30000);

      $.ajax({
        type: "GET",
        url: dashboardUrl("Dashboard/result"),
        datatype: "json",
        async: false,
        success: function(data){
          result = dashboardNumber(data);
        }
      });

  if (result === 0) {
    dashboardShowEmptyDashboard();
    return;
  }


  running();
  ready();
  warning();
  error();
  runningGraph(result);
  readyGraph(result);
  warningGraph(result);
  errorGraph(result);
  dashboardInitTables();



var tableAmount = dashboardSyncJson('Dashboard/getAmount');


var tableAmountData = dashboardPayloadData(tableAmount);

$('#dwAmount').append('<b>' + dashboardNumber(tableAmountData.dwAmount) + ' </b>');
$('#dimTableAmount').append('<b>' + dashboardNumber(tableAmountData.dimTableAmount) + ' </b>');
$('#factTableAmount').append('<b>' + dashboardNumber(tableAmountData.factTableAmount) + ' </b>');
$('#stgTableAmount').append('<b>' + dashboardNumber(tableAmountData.stgTableAmount) + ' </b>');

// label for dm amount exec dimension
 var dmAmountExecLabel = dashboardArray(tableAmountData.dmAmountExec).map(function(e) {
     return e.DIMENSION;
  });

//data for dm amount exec amount
 var dmAmountExecAmount = dashboardArray(tableAmountData.dmAmountExec).map(function(e) {
     return e.AMOUNT;
  });

 // label for dim amount exec dimension
 var dimAmountExecLabel = dashboardArray(tableAmountData.dimAmountExec).map(function(e) {
     return e.DIM;
  });

//data for dim amount exec amount
 var dimAmountExecAmount = dashboardArray(tableAmountData.dimAmountExec).map(function(e) {
     return e.AMOUNT;
  });

  // label for fact amount exec FACT
 var factAmountExecLabel = dashboardArray(tableAmountData.factAmountExec).map(function(e) {
     return e.FACT;
  });

//data for fact amount exec AMOUNT
 var factAmountExecAmount = dashboardArray(tableAmountData.factAmountExec).map(function(e) {
     return e.AMOUNT;
  });

   // label for stg amount exec stg
 var stgAmountExecLabel = dashboardArray(tableAmountData.stgAmountExec).map(function(e) {
     return e.STG;
  });

//data for stg amount exec amount
 var stgAmountExecAmount = dashboardArray(tableAmountData.stgAmountExec).map(function(e) {
     return e.AMOUNT;
  });

var dateRequest = dashboardSyncJson('Dashboard/getdate');


  var dateRequestData = dashboardPayloadData(dateRequest);
  var firstActivity = dashboardFirstActivity(dateRequestData.firstDate);
  var lastActivity = dashboardFirstActivity(dateRequestData.lastDate);

// Get Amount Value
   var firstdate = firstActivity ? moment(firstActivity).format('dddd, MMMM Do YYYY') : '';
  var lastDate = lastActivity ? moment(lastActivity).format('dddd, MMMM Do YYYY') : '';



  $('#date').append(firstdate && lastDate ? '<b>From: </b>' + firstdate + '<b> To: </b>' + lastDate : '<b>No monitoring records available yet.</b>');



    $('.loading').show();
    var request2 = dashboardSyncJson('Dashboard/graphMonth');

  var request2Data = dashboardPayloadData(request2);

// Get Amount Value
   var data2 = dashboardArray(request2Data.ready).map(function(e) {
     return e.AMOUNT;
  });

  var data3 = dashboardArray(request2Data.error).map(function(e) {
     return e.AMOUNT;
  });

  var data4 = dashboardArray(request2Data.warning).map(function(e) {
     return e.AMOUNT;
  });

  var data5 = dashboardArray(request2Data.running).map(function(e) {
     return e.AMOUNT;
  });

//MONTHS
  var months = dashboardArray(request2Data.months).map(function(e) {
     return e.MONTH;
  });

//GROWTHS X 30 Days
  var readyGrowth = dashboardArray(request2Data.readyGrowth).map(function(e) {
     return e.AMOUNT;
  });

  var errorGrowth = dashboardArray(request2Data.errorGrowth).map(function(e) {
     return e.AMOUNT;
  });

  var warningGrowth = dashboardArray(request2Data.warningGrowth).map(function(e) {
     return e.AMOUNT;
  });

  var runningGrowth = dashboardArray(request2Data.runningGrowth).map(function(e) {
     return e.AMOUNT;
  });


//GROWTHS X 90 Days
var readyGrowthX90 = dashboardArray(request2Data.readyGrowthX90).map(function(e) {
     return e.AMOUNT;
  });

var errorGrowthX90 = dashboardArray(request2Data.errorGrowthX90).map(function(e) {
     return e.AMOUNT;
  });

var warningGrowthX90 = dashboardArray(request2Data.warningGrowthX90).map(function(e) {
     return e.AMOUNT;
  });

var runningGrowthX90 = dashboardArray(request2Data.runningGrowthX90).map(function(e) {
     return e.AMOUNT;
  });

//GROWTHS X 180 Days
var readyGrowthX180 = dashboardArray(request2Data.readyGrowthX180).map(function(e) {
     return e.AMOUNT;
  });

var errorGrowthX180 = dashboardArray(request2Data.errorGrowthX180).map(function(e) {
     return e.AMOUNT;
  });

var warningGrowthX180 = dashboardArray(request2Data.warningGrowthX180).map(function(e) {
     return e.AMOUNT;
  });

var runningGrowthX180 = dashboardArray(request2Data.runningGrowthX180).map(function(e) {
     return e.AMOUNT;
  });



var statusLabel = dashboardArray(request2Data.statusGraph).map(function(e) {
     return e.STATUS;
  });

var statusAmount = dashboardArray(request2Data.statusGraph).map(function(e) {
     var status = String(e.STATUS || '').toLowerCase();
     return {"labels": [e.STATUS],
             "values": [e.AMOUNT],
             "colors": [status == 'ready' ? 'rgba(0, 166, 90, 1)':
                        status == 'error' ? 'rgba(221, 75, 57, 1)':
      status == 'running' ? 'rgba(0, 192, 239, 1)':
      status == 'warning' ? 'rgba(243, 156, 18, 1)':
      status == 'cancelled' ? 'rgba(108, 117, 125, 1)':
      'rgba(120, 130, 140, 1)']};
  });


// Logic for Ready Growth or Decline x 30 days
if (readyGrowth[0] != null && readyGrowth[readyGrowth.length -1] != null){

    readyGrowth[0] = parseInt(readyGrowth[0]);
    readyGrowth[readyGrowth.length -1] = parseInt(readyGrowth[readyGrowth.length -1]);

    if(readyGrowth[0] > readyGrowth[readyGrowth.length -1]){
      var readyGrowthPercent = 0;
      readyGrowthPercent = Math.round((((readyGrowth[0] / readyGrowth[readyGrowth.length -1])  - 1 ) * 100));

      $('#readyGrowthDecline').append('<h4 class="description-header text-green"><i class="fa fa-caret-up"></i> ' + readyGrowthPercent + ' %</h4>')
    } else {
      var readyDeclinePercent = 0;
      readyDeclinePercent = Math.round(((1 - (readyGrowth[0] / readyGrowth[readyGrowth.length -1]))*100));

      $('#readyGrowthDecline').append('<h4 class="description-header text-red"><i class="fa fa-caret-down"></i> ' + readyDeclinePercent + ' %</h4>')
    } 

 } else {
   $('#readyGrowthDecline').append('<h4 class="description-header">Not Available</h4>')
 }



// Logic for Error Growth or Decline
if (errorGrowth[0] != null && errorGrowth[errorGrowth.length -1] != null){

    errorGrowth[0] = parseInt(errorGrowth[0]);
    errorGrowth[errorGrowth.length -1] = parseInt(errorGrowth[errorGrowth.length -1]);

    if(errorGrowth[0] > errorGrowth[errorGrowth.length -1]){
      var errorGrowthPercent = 0;
      errorGrowthPercent = Math.round((((errorGrowth[0] / errorGrowth[errorGrowth.length -1])  - 1 ) * 100));
      $('#errorGrowthDecline').append('<h4 class="description-header text-green"><i class="fa fa-caret-up"></i> ' + errorGrowthPercent + ' %</h4>')
    }  else {
      var errorGrowthPercent = 0;
      errorGrowthPercent = Math.round(((1 - (errorGrowth[0] / errorGrowth[errorGrowth.length -1]))*100));
      $('#errorGrowthDecline').append('<h4 class="description-header text-red"><i class="fa fa-caret-down"></i> ' + errorGrowthPercent + ' %</h4>')

    } 
} else {
   $('#errorGrowthDecline').append('<h4 class="description-header">Not Available</h4>')
}



// Logic for Warning Growth or Decline
if (warningGrowth[0] != null && warningGrowth[warningGrowth.length -1] != null){

    warningGrowth[0] = parseInt(warningGrowth[0]);
    warningGrowth[warningGrowth.length -1] = parseInt(warningGrowth[warningGrowth.length -1]);

    if(warningGrowth[0] > warningGrowth[warningGrowth.length -1]){
      var warningGrowthPercent = 0;
      warningGrowthPercent = Math.round((((warningGrowth[0] / warningGrowth[warningGrowth.length -1])  - 1 ) * 100));
      $('#warningGrowthDecline').append('<h4 class="description-header text-green"><i class="fa fa-caret-up"></i> ' + warningGrowthPercent + ' %</h4>')
    }  else {
      var warningGrowthPercent = 0;
      warningGrowthPercent = Math.round(((1 - (warningGrowth[0] / warningGrowth[warningGrowth.length -1]))*100));
      $('#warningGrowthDecline').append('<h4 class="description-header text-red"><i class="fa fa-caret-down"></i> ' + warningGrowthPercent + ' %</h4>')

    } 
} else {
   $('#warningGrowthDecline').append('<h4 class="description-header">Not Available</h4>')
}



// Logic for Running Growth or Decline
if (runningGrowth[0] != null && runningGrowth[runningGrowth.length -1] != null){

    runningGrowth[0] = parseInt(runningGrowth[0]);
    runningGrowth[runningGrowth.length -1] = parseInt(runningGrowth[runningGrowth.length -1]);

    if(runningGrowth[0] > runningGrowth[runningGrowth.length -1]){
      var runningGrowthPercent = 0;
      runningGrowthPercent = Math.round((((runningGrowth[0] / runningGrowth[runningGrowth.length -1])  - 1 ) * 100));
      $('#runningGrowthDecline').append('<h4 class="description-header text-green"><i class="fa fa-caret-up"></i> ' + runningGrowthPercent + ' %</h4>')
    }  else {
      var runningGrowthPercent = 0;
      runningGrowthPercent = Math.round(((1 - (runningGrowth[0] / runningGrowth[runningGrowth.length -1]))*100));
      $('#runningGrowthDecline').append('<h4 class="description-header text-red"><i class="fa fa-caret-down"></i> ' + runningGrowthPercent + ' %</h4>')

    } 
} else {
   $('#runningGrowthDecline').append('<h4 class="description-header">Not Available</h4>')
}




// Logic for Ready Growth or Decline x 90 days
if (readyGrowthX90[0] != null && readyGrowthX90[readyGrowthX90.length -1] != null){

    readyGrowthX90[0] = parseInt(readyGrowthX90[0]);
    readyGrowthX90[readyGrowthX90.length -1] = parseInt(readyGrowthX90[readyGrowthX90.length -1]);

    if(readyGrowthX90[0] > readyGrowthX90[readyGrowthX90.length -1]){
      var readyGrowthPercentX90 = 0;
      readyGrowthPercentX90 = Math.round((((readyGrowthX90[0] / readyGrowthX90[readyGrowthX90.length -1])  - 1 ) * 100));

      $('#readyGrowthDeclineX90').append('<h4 class="description-header text-green"><i class="fa fa-caret-up"></i> ' + readyGrowthPercentX90 + ' %</h4>')
    } else {
      var readyDeclinePercentX90 = 0;
      readyDeclinePercentX90 = Math.round(((1 - (readyGrowthX90[0] / readyGrowthX90[readyGrowthX90.length -1]))*100));

      $('#readyGrowthDeclineX90').append('<h4 class="description-header text-red"><i class="fa fa-caret-down"></i> ' + readyDeclinePercentX90 + ' %</h4>')
    } 

 } else {
   $('#readyGrowthDeclineX90').append('<h4 class="description-header">Not Available</h4>')
 }



// Logic for Error Growth or Decline
if (errorGrowthX90[0] != null && errorGrowthX90[errorGrowthX90.length -1] != null){

    errorGrowthX90[0] = parseInt(errorGrowthX90[0]);
    errorGrowthX90[errorGrowthX90.length -1] = parseInt(errorGrowthX90[errorGrowthX90.length -1]);

    if(errorGrowthX90[0] > errorGrowthX90[errorGrowthX90.length -1]){
      var errorGrowthPercentX90 = 0;
      errorGrowthPercentX90 = Math.round((((errorGrowthX90[0] / errorGrowthX90[errorGrowthX90.length -1])  - 1 ) * 100));
      $('#errorGrowthDeclineX90').append('<h4 class="description-header text-green"><i class="fa fa-caret-up"></i> ' + errorGrowthPercentX90 + ' %</h4>')
    }  else {
      var errorGrowthPercentX90 = 0;
      errorGrowthPercentX90 = Math.round(((1 - (errorGrowthX90[0]/errorGrowthX90[errorGrowthX90.length -1]))*100));
      $('#errorGrowthDeclineX90').append('<h4 class="description-header text-red"><i class="fa fa-caret-down"></i> ' + errorGrowthPercentX90 + ' %</h4>')

    } 
} else {
   $('#errorGrowthDeclineX90').append('<h4 class="description-header">Not Available</h4>')
}




// Logic for Warning Growth or Decline
if (warningGrowthX90[0] != null && warningGrowthX90[warningGrowthX90.length -1] != null){

    warningGrowthX90[0] = parseInt(warningGrowthX90[0]);
    warningGrowthX90[warningGrowthX90.length -1] = parseInt(warningGrowthX90[warningGrowthX90.length -1]);

    if(warningGrowthX90[0] > warningGrowthX90[warningGrowthX90.length -1]){
      var warningGrowthPercentX90 = 0;
      warningGrowthPercentX90 = Math.round((((warningGrowthX90[0] / warningGrowthX90[warningGrowthX90.length -1])  - 1 ) * 100));
      $('#warningGrowthDeclineX90').append('<h4 class="description-header text-green"><i class="fa fa-caret-up"></i> ' + warningGrowthPercentX90 + ' %</h4>')
    }  else {
      var warningGrowthPercentX90 = 0;
      warningGrowthPercentX90 = Math.round(((1 - (warningGrowthX90[0]/ warningGrowthX90[warningGrowthX90.length -1]))*100));
      $('#warningGrowthDeclineX90').append('<h4 class="description-header text-red"><i class="fa fa-caret-down"></i> ' + warningGrowthPercentX90 + ' %</h4>')

    } 
} else {
   $('#warningGrowthDeclineX90').append('<h4 class="description-header">Not Available</h4>')
}



// Logic for Running Growth or Decline
if (runningGrowthX90[0] != null && runningGrowthX90[runningGrowthX90.length -1] != null){

    runningGrowthX90[0] = parseInt(runningGrowthX90[0]);
    runningGrowthX90[runningGrowthX90.length -1] = parseInt(runningGrowthX90[runningGrowthX90.length -1]);

    if(runningGrowthX90[0] > runningGrowthX90[runningGrowthX90.length -1]){
      var runningGrowthPercentX90 = 0;
      runningGrowthPercentX90 = Math.round((((runningGrowthX90[0] / runningGrowthX90[runningGrowthX90.length -1])  - 1 ) * 100));
      $('#runningGrowthDeclineX90').append('<h4 class="description-header text-green"><i class="fa fa-caret-up"></i> ' + runningGrowthPercentX90 + ' %</h4>')
    }  else {
      var runningGrowthPercentX90 = 0;
      runningGrowthPercentX90 = Math.round(((1 - (runningGrowthX90[0]/ runningGrowthX90[runningGrowthX90.length -1]))*100));
      $('#runningGrowthDeclineX90').append('<h4 class="description-header text-red"><i class="fa fa-caret-down"></i> ' + runningGrowthPercentX90 + ' %</h4>')

    } 
} else {
   $('#runningGrowthDeclineX90').append('<h4 class="description-header">Not Available</h4>')
}


// Logic for Ready Growth or Decline x 180 days
if (readyGrowthX180[0] != null && readyGrowthX180[readyGrowthX180.length -1] != null){

    readyGrowthX180[0] = parseInt(readyGrowthX180[0]);
    readyGrowthX180[readyGrowthX180.length -1] = parseInt(readyGrowthX180[readyGrowthX180.length -1]);

    if(readyGrowthX180[0] > readyGrowthX180[readyGrowthX180.length -1]){
      var readyGrowthPercentX180 = 0;
      readyGrowthPercentX180 = Math.round((((readyGrowthX180[0] / readyGrowthX180[readyGrowthX180.length -1])  - 1 ) * 100));

      $('#readyGrowthDeclineX180').append('<h4 class="description-header text-green"><i class="fa fa-caret-up"></i> ' + readyGrowthPercentX180 + ' %</h4>')
    } else {
      var readyDeclinePercentX180 = 0;
      readyDeclinePercentX180 = Math.round(((1 - (readyGrowthX180[0] / readyGrowthX180[readyGrowthX180.length -1]))*100));

      $('#readyGrowthDeclineX180').append('<h4 class="description-header text-red"><i class="fa fa-caret-down"></i> ' + readyDeclinePercentX180 + ' %</h4>')
    } 

 } else {
   $('#readyGrowthDeclineX180').append('<h4 class="description-header">Not Available</h4>')
 }



// Logic for Error Growth or Decline
if (errorGrowthX180[0] != null && errorGrowthX180[errorGrowthX180.length -1] != null){

    errorGrowthX180[0] = parseInt(errorGrowthX180[0]);
    errorGrowthX180[errorGrowthX180.length -1] = parseInt(errorGrowthX180[errorGrowthX180.length -1]);

    if(errorGrowthX180[0] > errorGrowthX180[errorGrowthX180.length -1]){
      var errorGrowthPercentX180 = 0;
      errorGrowthPercentX180 = Math.round((((errorGrowthX180[0] / errorGrowthX180[errorGrowthX180.length -1])  - 1 ) * 100));
      $('#errorGrowthDeclineX180').append('<h4 class="description-header text-green"><i class="fa fa-caret-up"></i> ' + errorGrowthPercentX180 + ' %</h4>')
    }  else {
      var errorGrowthPercentX180 = 0;
      errorGrowthPercentX180 = Math.round(((1 - (errorGrowthX180[0]/errorGrowthX180[errorGrowthX180.length -1]))*100));
      $('#errorGrowthDeclineX180').append('<h4 class="description-header text-red"><i class="fa fa-caret-down"></i> ' + errorGrowthPercentX180 + ' %</h4>')

    } 
} else {
   $('#errorGrowthDeclineX180').append('<h4 class="description-header">Not Available</h4>')
}




// Logic for Warning Growth or Decline
if (warningGrowthX180[0] != null && warningGrowthX180[warningGrowthX180.length -1] != null){

    warningGrowthX180[0] = parseInt(warningGrowthX180[0]);
    warningGrowthX180[warningGrowthX180.length -1] = parseInt(warningGrowthX180[warningGrowthX180.length -1]);

    if(warningGrowthX180[0] > warningGrowthX180[warningGrowthX180.length -1]){
      var warningGrowthPercentX180 = 0;
      warningGrowthPercentX180 = Math.round((((warningGrowthX180[0] / warningGrowthX180[warningGrowthX180.length -1])  - 1 ) * 100));
      $('#warningGrowthDeclineX180').append('<h4 class="description-header text-green"><i class="fa fa-caret-up"></i> ' + warningGrowthPercentX180 + ' %</h4>')
    }  else {
      var warningGrowthPercentX180 = 0;
      warningGrowthPercentX180 = Math.round(((1 - (warningGrowthX180[0]/ warningGrowthX180[warningGrowthX180.length -1]))*100));
      $('#warningGrowthDeclineX180').append('<h4 class="description-header text-red"><i class="fa fa-caret-down"></i> ' + warningGrowthPercentX180 + ' %</h4>')

    } 
} else {
   $('#warningGrowthDeclineX180').append('<h4 class="description-header">Not Available</h4>')
}



// Logic for Running Growth or Decline
if (runningGrowthX180[0] != null && runningGrowthX180[runningGrowthX180.length -1] != null){

    runningGrowthX180[0] = parseInt(runningGrowthX180[0]);
    runningGrowthX180[runningGrowthX180.length -1] = parseInt(runningGrowthX180[runningGrowthX180.length -1]);

    if(runningGrowthX180[0] > runningGrowthX180[runningGrowthX180.length -1]){
      var runningGrowthPercentX180 = 0;
      runningGrowthPercentX180 = Math.round((((runningGrowthX180[0] / runningGrowthX180[runningGrowthX180.length -1])  - 1 ) * 100));
      $('#runningGrowthDeclineX180').append('<h4 class="description-header text-green"><i class="fa fa-caret-up"></i> ' + runningGrowthPercentX180 + ' %</h4>')
    }  else {
      var runningGrowthPercentX180 = 0;
      runningGrowthPercentX180 = Math.round(((1 - (runningGrowthX180[0]/ runningGrowthX180[runningGrowthX180.length -1]))*100));
      $('#runningGrowthDeclineX180').append('<h4 class="description-header text-red"><i class="fa fa-caret-down"></i> ' + runningGrowthPercentX180 + ' %</h4>')

    } 
} else {
   $('#runningGrowthDeclineX180').append('<h4 class="description-header">Not Available</h4>')
}


var ctx = document.getElementById('chart').getContext('2d');
var myChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: months,
        datasets: [{
            label: 'Ready',
            data: data2,
            backgroundColor: [
                'rgba(0, 166, 90, 0.0)', // verde
                
            ],
            borderColor: [
                'rgba(0, 166, 90, 1)', // verde
            ],
            borderWidth: 3
        },
        {
            label: 'Error',
            data: data3,
            backgroundColor: [
                'rgba(221, 75, 57, 0.0)', // vermelho
                
            ],
            borderColor: [
                'rgba(221, 75, 57, 1)', // vermelho
            ],
            borderWidth: 3
        },
        {
            label: 'Warning',
            data: data4,
            backgroundColor: [
                'rgba(243, 156, 18, 0.0)', // amarelo
                
            ],
            borderColor: [
                'rgba(243, 156, 18, 1)', // amarelo
            ],
            borderWidth: 3
        },
        {
            label: 'Running',
            data: data5,
            backgroundColor: [
                'rgba(0, 192, 239, 0.0)', // azul
                
            ],
            borderColor: [
                'rgba(0, 192, 239, 1)', // azul
            ],
            borderWidth: 3
        }]
    },
    options: {
        responsive: true,
        tooltips: {
          mode: 'index',
          intersect: false,
        },
        hover: {
          mode: 'nearest',
          intersect: true
        },
        scales: {
          xAxes: [{
            display: true,
            scaleLabel: {
              display: true,
              labelString: 'Month'
            }
          }],
          yAxes: [{

            display: true,
            scaleLabel: {
              display: true,
              labelString: 'Amount'
            }
          }]
        }
      }
});





 // Pie Chart 
  var ctx1 = document.getElementById('pieChart').getContext('2d');

  labels = [];
  values = [];
  colors = [];
  for (i = 0; i < statusAmount.length; i++) {
    let label = statusAmount[i].labels
    let value = statusAmount[i].values
    let color = statusAmount[i].colors
    for(j = 0; j < label.length; j++){
        labels.push(label[j]);
    }
    for(k = 0; k < value.length; k++){
      values.push(value[k]);
    }
    for(l = 0; l < color.length; l++){
      colors.push(color[l]);
  }
  }

  var myChart = new Chart(ctx1, {
    type: 'doughnut',
    data: {
        labels: labels,
        datasets: [{
            label: 'Summary',
            data: values,
            backgroundColor: colors,
            borderColor: colors,
            borderWidth: 1
        }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false
    }
});// END Pie Chart

// DW execution chart
var ctx2 = document.getElementById('dwChart').getContext('2d');
var myChart = new Chart(ctx2, {
    type: 'bar',
    data: {
        labels: dmAmountExecLabel,
        datasets: [{
            label: 'Execution Amount',
            data: dmAmountExecAmount,
           "backgroundColor":[  
            "rgba(75, 192, 192, 0.2)",
            "rgba(54, 162, 235, 0.2)",
            "rgba(153, 102, 255, 0.2)",
            "rgba(201, 203, 207, 0.2)",
            "rgba(255, 99, 132, 0.2)",
            "rgba(255, 159, 64, 0.2)",
            "rgba(255, 205, 86, 0.2)",
         ],
         "borderColor":[  
            "rgb(75, 192, 192)",
            "rgb(54, 162, 235)",
            "rgb(153, 102, 255)",
            "rgb(201, 203, 207)",
            "rgb(255, 99, 132)",
            "rgb(255, 159, 64)",
            "rgb(255, 205, 86)"
         ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        tooltips: {
          mode: 'index',
          intersect: false,
        },
        hover: {
          mode: 'nearest',
          intersect: true
        },
        scales: {
          xAxes: [{
            display: true,
            scaleLabel: {
              display: true,
              labelString: 'Data Warehouses / Data Marts'
            }
          }],
          yAxes: [{
            ticks: {
                suggestedMin: 0
            },
            display: true,
            scaleLabel: {
              display: true,
              labelString: 'Amount of Execution',
              
            }
          }]
        }
      }
});

//dm execution char
var ctx3 = document.getElementById('dmChart').getContext('2d');
var myChart = new Chart(ctx3, {
    type: 'bar',
    data: {
        labels: dimAmountExecLabel,
        datasets: [{
            label: 'Execution Amount',
            data: dimAmountExecAmount,
           "backgroundColor":[  
            "rgba(255, 99, 132, 0.2)",
            "rgba(255, 159, 64, 0.2)",
            "rgba(255, 205, 86, 0.2)",
            "rgba(75, 192, 192, 0.2)",
            "rgba(54, 162, 235, 0.2)",
            "rgba(153, 102, 255, 0.2)",
            "rgba(201, 203, 207, 0.2)"
         ],
         "borderColor":[  
            "rgb(255, 99, 132)",
            "rgb(255, 159, 64)",
            "rgb(255, 205, 86)",
            "rgb(75, 192, 192)",
            "rgb(54, 162, 235)",
            "rgb(153, 102, 255)",
            "rgb(201, 203, 207)"
         ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        tooltips: {
          mode: 'index',
          intersect: false,
        },
        hover: {
          mode: 'nearest',
          intersect: true
        },
        scales: {
          xAxes: [{
            display: true,
            scaleLabel: {
              display: true,
              labelString: 'Dimensions from a Data Warehouse / Data Mart'
            }
          }],
          yAxes: [{
            ticks: {
                suggestedMin: 0
            },
            display: true,
            scaleLabel: {
              display: true,
              labelString: 'Amount of Execution',
              
            }
          }]
        }
      }
});


//fact execution char
var ctx4 = document.getElementById('factChart').getContext('2d');
var myChart = new Chart(ctx4, {
    type: 'bar',
    data: {
        labels: factAmountExecLabel,
        datasets: [{
            label: 'Execution Amount',
            data: factAmountExecAmount,
           "backgroundColor":[  
           "rgba(153, 102, 255, 0.2)",
           "rgba(75, 192, 192, 0.2)",
           "rgba(54, 162, 235, 0.2)",
            "rgba(255, 99, 132, 0.2)",
            "rgba(255, 159, 64, 0.2)",
            "rgba(255, 205, 86, 0.2)",
            "rgba(201, 203, 207, 0.2)"
         ],
         "borderColor":[  
         "rgb(153, 102, 255)",
         "rgb(75, 192, 192)",
           "rgb(54, 162, 235)",
            "rgb(255, 99, 132)",
            "rgb(255, 159, 64)",
            "rgb(255, 205, 86)",
            "rgb(201, 203, 207)"
         ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        tooltips: {
          mode: 'index',
          intersect: false,
        },
        hover: {
          mode: 'nearest',
          intersect: true
        },
        scales: {
          xAxes: [{
            display: true,
            scaleLabel: {
              display: true,
              labelString: 'Metrics from a Data Warehouse / Data Mart'
            }
          }],
          yAxes: [{
            ticks: {
                suggestedMin: 0
            },
            display: true,
            scaleLabel: {
              display: true,
              labelString: 'Amount of Execution',
              
            }
          }]
        }
      }
});

//fact execution char
var ctx5 = document.getElementById('stgChart').getContext('2d');
var myChart = new Chart(ctx5, {
    type: 'bar',
    data: {
        labels: stgAmountExecLabel,
        datasets: [{
            label: 'Execution Amount',
            data: stgAmountExecAmount,
           "backgroundColor":[  
            "rgba(54, 162, 235, 0.2)",
            "rgba(255, 99, 132, 0.2)",
            "rgba(255, 159, 64, 0.2)",
            "rgba(255, 205, 86, 0.2)",
            "rgba(201, 203, 207, 0.2)",
            "rgba(75, 192, 192, 0.2)",
            "rgba(153, 102, 255, 0.2)"
            
            
         ],
         "borderColor":[  
            "rgb(54, 162, 235)",
            "rgb(255, 99, 132)",
            "rgb(255, 159, 64)",
            "rgb(255, 205, 86)",
            "rgb(201, 203, 207)",
            "rgb(75, 192, 192)",
            "rgb(153, 102, 255)"
            
            
         ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        tooltips: {
          mode: 'index',
          intersect: false,
        },
        hover: {
          mode: 'nearest',
          intersect: true
        },
        scales: {
          xAxes: [{
            display: true,
            scaleLabel: {
              display: true,
              labelString: 'Stagging from a Data Warehouse / Data Mart'
            }
          }],
          yAxes: [{
            ticks: {
                suggestedMin: 0
            },
            display: true,
            scaleLabel: {
              display: true,
              labelString: 'Amount of Execution',
              
            }
          }]
        }
      }
});

dashboardShowMain();

 });