<link rel="stylesheet" type="text/css" href="<?php echo base_url(); ?>assets/bower_components/chart.js/Chart.min.css">
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <h1>
        <i class="fa fa-home" aria-hidden="true"></i> Dashboard
        <small>Control panel</small>
      </h1>
    </section>
    
    <section class="content">

      <div class="row">
        <div class="col-lg-12 col-xs-12">
          <div class="box box-solid" style="padding: 10px; box-shadow: 10px 10px 5px -4px rgba(0,0,0,0.75);">
            <div class="text-center">
              <h4>Welcome <b><?php echo $name; ?></b> You're a <b><?php echo $role_text ?></b> user and today is <b><?php echo date("Y-m-d"); ?></b> at <b><?php echo date("h:i:sa"); ?></b></h4>
            </div>
          </div>
        </div>
      </div>

        <div class="row">
            <div class="col-lg-3 col-xs-6">
              <!-- small box -->
              <div class="small-box bg-aqua running animated">

                <div class="inner">
                 <span id="running"><h3></h3></span>
                  <p>Running Jobs</p>
                </div>
                <div class="icon">
                  <i class="fa fa-refresh"></i>
                </div>
                <a href="#" class="small-box-footer">More info <i class="fa fa-arrow-circle-right"></i></a>
              </div>

            </div><!-- ./col -->

            <div class="col-lg-3 col-xs-6">
              <!-- small box -->
              <div class="small-box bg-green ready animated">
                <div class="inner">
                  <h3 id="ready"></h3>
                  <p>Ready Jobs</p>
                </div>
                <div class="icon">
                  <i class="fa fa-check-square-o"></i>
                </div>
                <a href="#" class="small-box-footer">More info <i class="fa fa-arrow-circle-right"></i></a>
              </div>
            </div><!-- ./col -->
            <div class="col-lg-3 col-xs-6">
              <!-- small box -->
              <div class="small-box bg-yellow warning animated">
                <div class="inner">
                  <h3 id="warning"></h3>
                  <p>Warning Jobs</p>
                </div>
                <div class="icon">
                  <i class="fa fa-warning"></i>
                </div>
                <a href="#" class="small-box-footer">More info <i class="fa fa-arrow-circle-right"></i></a>
              </div>
            </div><!-- ./col -->
            <div class="col-lg-3 col-xs-6">
              <!-- small box -->
              <div class="small-box bg-red error animated">
                <div class="inner">
                  <h3 id="error"></h3>
                  <p>Error Jobs</p>
                </div>
                <div class="icon">
                  <i class="fa fa-thumbs-o-down"></i>
                </div>
                <a href="#" class="small-box-footer">More info <i class="fa fa-arrow-circle-right"></i></a>
              </div>
            </div><!-- ./col -->
          </div>

          <!-- Aqui entra os graficos estastítisco -->

          <div class="row" style="margin-top: 15px;">
            <div class="col-lg-12 col-xs-12">
              <div class="box box-primary">
            <div class="box-header with-border">
              <h3 class="box-title"><b>Jobs survey report</b></h3>

              <div class="box-tools pull-right">
                <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                </button>
                <div class="btn-group">
                  <button type="button" class="btn btn-box-tool dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                    <i class="fa fa-wrench"></i></button>
                  <ul class="dropdown-menu" role="menu">
                    <li><a href="#">Action</a></li>
                    <li><a href="#">Another action</a></li>
                    <li><a href="#">Something else here</a></li>
                    <li class="divider"></li>
                    <li><a href="#">Separated link</a></li>
                  </ul>
                </div>
                <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
              </div>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
              <div class="row">
                <div class="col-md-8">
                  <p class="text-center">
                    <strong id="date"></strong>
                  </p>

                  <div class="chart">
                    <!-- Sales Chart Canvas -->
                     <canvas id="chart" style="height: 200px; width: 600px;" height="230" width="600"></canvas> 
                   <!-- <div id="line-chart" style="height: 250px;"></div>-->
                  </div>
                  <!-- /.chart-responsive -->
                </div>
                <!-- /.col -->
                <div class="col-md-4">
                  <p class="text-center">
                    <strong>Jobs status amount per total</strong>
                  </p>

                  <div class="progress-group runningGraph animated">
                    <span class="progress-text">Running Jobs</span>
                    <span class="progress-number" id="runningGraph"></span>

                    <div class="progress sm">
                      <div id="runningGraphBar" class="progress-bar progress-bar-aqua"></div>
                    </div>
                  </div>
                  <!-- /.progress-group -->
                  <div class="progress-group readyGraph animated ">
                    <span class="progress-text">Successfuly ready Jobs</span>
                    <span class="progress-number" id="readyGraph"><b></b></span>

                    <div class="progress sm">
                      <div id="readyGraphBar" class="progress-bar progress-bar-green" style="width: 0%;"></div>
                    </div>
                  </div>
                     <!-- /.progress-group -->
                  <div class="progress-group warningGraph animated">
                    <span class="progress-text">Jobs with Warning</span>
                    <span class="progress-number" id="warningGraph"></span>

                    <div class="progress sm">
                      <div id="warningGraphBar" class="progress-bar progress-bar-yellow" style="width: 0%;"></div>
                    </div>
                  </div>
                  <!-- /.progress-group -->
                  <div class="progress-group errorGraph animated">
                    <span class="progress-text">Jobs with one or more error</span>
                    <span class="progress-number" id="errorGraph"></span>

                    <div class="progress sm">
                      <div id="errorGraphBar" class="progress-bar progress-bar-red"></div>
                    </div>
                  </div>
                </div>
                <!-- /.col -->
              </div>
              <!-- /.row -->
            </div>
            <!-- ./box-body -->
            <div class="box-footer">
              <div class="row">
                <div class="col-sm-12 col-xs-12">
                  <span class="text-center"><h5><b>Growth X Decline on Month</b></h5></span>
                </div>
                
                <hr>
                <div class="col-sm-3 col-xs-6">
                  <div class="description-block border-right">
                    <span class="description-percentage" id="readyGrowthDecline"> </span>
                    
                    <span class="description-text text-green">Ready </span> Growth
                  </div>
                  <!-- /.description-block -->
                </div>
                <!-- /.col -->
                <div class="col-sm-3 col-xs-6">
                  <div class="description-block border-right">
                    <span class="description-percentage" id="errorGrowthDecline"> </span>
                    
                    <span class="description-text text-red">Error</span> Growth
                  </div>
                  <!-- /.description-block -->
                </div>
                <!-- /.col -->
                <div class="col-sm-3 col-xs-6">
                  <div class="description-block border-right">
                    <span class="description-percentage" id="warningGrowthDecline"></span>
                    
                    <span class="description-text text-yellow">Warning</span> Growth
                  </div>
                  <!-- /.description-block -->
                </div>
                <!-- /.col -->
                <div class="col-sm-3 col-xs-6">
                  <div class="description-block border-right">
                    <span class="description-percentage" id="runningGrowthDecline"> </span>
                    
                    <span class="description-text text-blue">Running</span> Growth
                  </div>
                  <!-- /.description-block -->
                </div>
              </div>
              <!-- /.row -->
            </div>
            <!-- /.box-footer -->
          </div>
            </div>
          </div>



          <div class="row" style="margin-top: 30px;">

               <!-- Div last jobs -->
            <div class="col-lg-6 col-xs-6">
                <div class="box box-primary">
            <div class="box-header with-border">
              <h3 class="box-title">Recently Added Products</h3>

              <div class="box-tools pull-right">
                <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                </button>
                <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
              </div>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
              <ul class="products-list product-list-in-box">
                <li class="item">
                  <div class="product-img">
                    <img src="dist/img/default-50x50.gif" alt="Product Image">
                  </div>
                  <div class="product-info">
                    <a href="javascript:void(0)" class="product-title">Samsung TV
                      <span class="label label-warning pull-right">$1800</span></a>
                    <span class="product-description">
                          Samsung 32" 1080p 60Hz LED Smart HDTV.
                        </span>
                  </div>
                </li>
                <!-- /.item -->
                <li class="item">
                  <div class="product-img">
                    <img src="dist/img/default-50x50.gif" alt="Product Image">
                  </div>
                  <div class="product-info">
                    <a href="javascript:void(0)" class="product-title">Bicycle
                      <span class="label label-info pull-right">$700</span></a>
                    <span class="product-description">
                          26" Mongoose Dolomite Men's 7-speed, Navy Blue.
                        </span>
                  </div>
                </li>
                <!-- /.item -->
                <li class="item">
                  <div class="product-img">
                    <img src="dist/img/default-50x50.gif" alt="Product Image">
                  </div>
                  <div class="product-info">
                    <a href="javascript:void(0)" class="product-title">Xbox One <span class="label label-danger pull-right">$350</span></a>
                    <span class="product-description">
                          Xbox One Console Bundle with Halo Master Chief Collection.
                        </span>
                  </div>
                </li>
                <!-- /.item -->
                <li class="item">
                  <div class="product-img">
                    <img src="dist/img/default-50x50.gif" alt="Product Image">
                  </div>
                  <div class="product-info">
                    <a href="javascript:void(0)" class="product-title">PlayStation 4
                      <span class="label label-success pull-right">$399</span></a>
                    <span class="product-description">
                          PlayStation 4 500GB Console (PS4)
                        </span>
                  </div>
                </li>
                <!-- /.item -->
              </ul>
            </div>
            <!-- /.box-body -->
            <div class="box-footer text-center">
              <a href="javascript:void(0)" class="uppercase">View All Products</a>
            </div>
            <!-- /.box-footer -->
          </div>
            </div>
            <!-- End Div last jobs -->

            <!-- Div Graficos -->
            <div class="col-lg 6 col-xs-6">
                <div class="box box-primary">
            <div class="box-header with-border">
              <h3 class="box-title">Browser Usage</h3>

              <div class="box-tools pull-right">
                <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                </button>
                <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
              </div>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
              <div class="row">
                <div class="col-md-8">
                  <div class="chart-responsive">
                    <canvas id="pieChart" height="210" width="438" style="width: 438px; height: 210px;"></canvas>
                  </div>
                  <!-- ./chart-responsive -->
                </div>
                <!-- /.col -->
                <div class="col-md-4">
                  <ul class="chart-legend clearfix">
                    <li><i class="fa fa-circle-o text-red"></i> Chrome</li>
                    <li><i class="fa fa-circle-o text-green"></i> IE</li>
                    <li><i class="fa fa-circle-o text-yellow"></i> FireFox</li>
                    <li><i class="fa fa-circle-o text-aqua"></i> Safari</li>
                    <li><i class="fa fa-circle-o text-light-blue"></i> Opera</li>
                    <li><i class="fa fa-circle-o text-gray"></i> Navigator</li>
                  </ul>
                </div>
                <!-- /.col -->
              </div>
              <!-- /.row -->
            </div>
            <!-- /.box-body -->
            <div class="box-footer no-padding">
              <ul class="nav nav-pills nav-stacked">
                <li><a href="#">United States of America
                  <span class="pull-right text-red"><i class="fa fa-angle-down"></i> 12%</span></a></li>
                <li><a href="#">India <span class="pull-right text-green"><i class="fa fa-angle-up"></i> 4%</span></a>
                </li>
                <li><a href="#">China
                  <span class="pull-right text-yellow"><i class="fa fa-angle-left"></i> 0%</span></a></li>
              </ul>
            </div>
            <!-- /.footer -->
          </div>

            </div>
            <!-- End Div Graficos -->


          </div>

        
    </section>
</div>
<script type="text/javascript" src="<?php echo base_url(); ?>assets/bower_components/chart.js/Chart.min.js"></script>
<script type="text/javascript" src="<?php echo base_url(); ?>assets/bower_components/moment/moment.min.js"></script>
<script type="text/javascript">
  
  function running() {
      
      $.ajax({    //create an ajax request
        type: "GET",
        url: "<?php echo base_url(); ?>Dashboard/query/running",             
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
        url: "<?php echo base_url(); ?>Dashboard/query/ready",             
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
        url: "<?php echo base_url(); ?>Dashboard/query/warning",             
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
        url: "<?php echo base_url(); ?>Dashboard/query/error",             
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
        url: "<?php echo base_url(); ?>Dashboard/query/running",             
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
        }
    });

}

function readyGraph(result) {
      
      $.ajax({    //create an ajax request
        type: "GET",
        url: "<?php echo base_url(); ?>Dashboard/query/ready",             
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
        }
    });

}

function warningGraph(result) {
      
      $.ajax({    //create an ajax request
        type: "GET",
        url: "<?php echo base_url(); ?>Dashboard/query/warning",             
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
        }
    });

}

function errorGraph(result) {
      
      $.ajax({    //create an ajax request
        type: "GET",
        url: "<?php echo base_url(); ?>Dashboard/query/error",             
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
        }
    });

}



  $(document).ready(function(){

    var result;

      $.ajax({
        type: "GET",
        url: "<?php echo base_url(); ?>Dashboard/result",
        datatype: "json",
        async: false,
        success: function(data){
          result = data;              
        }
      });


  running();
  ready();
  warning();
  error();
  runningGraph(result);
  readyGraph(result);
  warningGraph(result);
  errorGraph(result);

 //setInterval(running,30000);
 //setInterval(ready,30000);
 //setInterval(warning,30000);
 //setInterval(error,30000);
 //setInterval(runningGraph,30000,result);
 //setInterval(readyGraph,30000,result);
 //setInterval(warningGraph,30000,result);
 //setInterval(errorGraph,30000,result);



var dateRequest = $.parseJSON($.ajax({
      contentType: "application/json",
        url:  '<?php echo base_url(); ?>Dashboard/getdate',
        dataType: "json", 
        async: false,
        beforeSend: function() {
        },
        success: function() {
        },
        complete: function(data) {
            dateRequest = data;
        }

    }).responseText); 


// Get Amount Value
 var firstdate = moment(dateRequest.data.firstDate[0].last_activity).format('dddd, MMMM Do YYYY');
var lastDate = moment(dateRequest.data.lastDate[0].last_activity).format('dddd, MMMM Do YYYY');


//console.log(firstdate);
//console.log(lastDate);

$('#date').append('<b>From: </b>' + firstdate + '<b> To: </b>' + lastDate);



    var request2 = $.parseJSON($.ajax({
      contentType: "application/json",
        url:  '<?php echo base_url(); ?>Dashboard/graphMonth',
        dataType: "json", 
        async: false,
        beforeSend: function() {
            $('.loading').show();
        },
        success: function() {
        },
        complete: function(data) {
            request2 = data;
        }

    }).responseText); 

// Get Amount Value
 var data2 = request2.data.ready.map(function(e) {
     return e.AMOUNT;
  });

var data3 = request2.data.error.map(function(e) {
     return e.AMOUNT;
  });

var data4 = request2.data.warning.map(function(e) {
     return e.AMOUNT;
  });

var data5 = request2.data.running.map(function(e) {
     return e.AMOUNT;
  });

//MONTHS
var months = request2.data.months.map(function(e) {
     return e.MONTH;
  });

//GROWTHS
var readyGrowth = request2.data.readyGrowth.map(function(e) {
     return e.AMOUNT;
  });

var errorGrowth = request2.data.errorGrowth.map(function(e) {
     return e.AMOUNT;
  });

var warningGrowth = request2.data.warningGrowth.map(function(e) {
     return e.AMOUNT;
  });

var runningGrowth = request2.data.runningGrowth.map(function(e) {
     return e.AMOUNT;
  });


// Logic for Ready Growth or Decline
if (readyGrowth[1] != null && readyGrowth[0] != null){
    if(readyGrowth[1] > readyGrowth[0]){
      var readyGrowthPercent = 0;
      readyGrowthPercent = Math.round((((readyGrowth[1] / readyGrowth[0])  - 1 ) * 100));

      $('#readyGrowthDecline').append('<h4 class="description-header text-green"><i class="fa fa-caret-up"></i> ' + readyGrowthPercent + ' %</h4>')
    } else {
      var readyDeclinePercent = 0;
      readyDeclinePercent = ((1 - (readyGrowth[0]/readyGrowth[1]))*100);

      $('#readyGrowthDecline').append('<h4 class="description-header text-red"><i class="fa fa-caret-down"></i> ' + readyDeclinePercent + ' %</h4>')
    } 

 } else {
   $('#readyGrowthDecline').append('<h4 class="description-header">Not Available</h4>')
 }


// Logic for Error Growth or Decline
if (errorGrowth[1] != null && errorGrowth[0] != null){
    if(errorGrowth[1] > errorGrowth[0]){
      var errorGrowthPercent = 0;
      errorGrowthPercent = (((errorGrowth[1] / errorGrowth[0])  - 1 ) * 100);
      $('#errorGrowthDecline').append('<h4 class="description-header text-green"><i class="fa fa-caret-up"></i> ' + errorGrowthPercent + ' %</h4>')
    }  else {
      var errorGrowthPercent = 0;
      errorGrowthPercent = ((1 - (errorGrowth[0]/errorGrowth[1]))*100);
      $('#errorGrowthDecline').append('<h4 class="description-header text-red"><i class="fa fa-caret-down"></i> ' + errorGrowthPercent + ' %</h4>')

    } 
} else {
   $('#errorGrowthDecline').append('<h4 class="description-header">Not Available</h4>')
}


// Logic for Warning Growth or Decline
if (warningGrowth[1] != null && warningGrowth[0] != null){
    if(warningGrowth[1] > warningGrowth[0]){
      var warningGrowthPercent = 0;
      warningGrowthPercent = (((warningGrowth[1] / warningGrowth[0])  - 1 ) * 100);
      $('#warningGrowthDecline').append('<h4 class="description-header text-green"><i class="fa fa-caret-up"></i> ' + warningGrowthPercent + ' %</h4>')
    }  else {
      var warningGrowthPercent = 0;
      warningGrowthPercent = ((1 - (warningGrowth[0]/warningGrowth[1]))*100);
      $('#warningGrowthDecline').append('<h4 class="description-header text-red"><i class="fa fa-caret-down"></i> ' + warningGrowthPercent + ' %</h4>')

    } 
} else {
   $('#warningGrowthDecline').append('<h4 class="description-header">Not Available</h4>')
}

// Logic for Running Growth or Decline
if (runningGrowth[1] != null && runningGrowth[0] != null){
    if(runningGrowth[1] > runningGrowth[0]){
      var runningGrowthPercent = 0;
      runningGrowthPercent = (((runningGrowth[1] / runningGrowth[0])  - 1 ) * 100);
      $('#runningGrowthDecline').append('<h4 class="description-header text-green"><i class="fa fa-caret-up"></i> ' + runningGrowthPercent + ' %</h4>')
    }  else {
      var runningGrowthPercent = 0;
      runningGrowthPercent = ((1 - (runningGrowth[0]/runningGrowth[1]))*100);
      $('#runningGrowthDecline').append('<h4 class="description-header text-red"><i class="fa fa-caret-down"></i> ' + runningGrowthPercent + ' %</h4>')

    } 
} else {
   $('#runningGrowthDecline').append('<h4 class="description-header">Not Available</h4>')
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

 });

</script>

