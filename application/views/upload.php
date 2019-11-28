<link rel="stylesheet" href="<?php echo base_url(); ?>assets/plugins/dropzone/dropzone.css">
<style>
    .dropzone {
        background: white;
        border-radius: 5px;
        border: 2px dashed rgb(0, 135, 247);
        border-image: none;
        max-width: 100%;
        margin-left: auto;
        margin-right: auto;
    }
</style>
<script src="<?php echo base_url(); ?>assets/plugins/dropzone/dropzone.js"></script>

<div class="content-wrapper">    
    <section class="content-header">
      <h1>
        File Upload
        <small>Section</small>
      </h1>
    </section>
    <section class="content">
        <!-- Info boxes -->
      <div class="row">
        <div class="col-md-3 col-sm-6 col-xs-12">
          <div class="info-box">
            <span class="info-box-icon bg-aqua"><i class="fa fa-server"></i></span>

            <div class="info-box-content">
              <span class="info-box-text">Jobs Registered</span>
              <span class="info-box-number">90</span>
            </div>
            <!-- /.info-box-content -->
          </div>
          <!-- /.info-box -->
        </div>
        <!-- /.col -->
        <div class="col-md-3 col-sm-6 col-xs-12">
          <div class="info-box">
            <span class="info-box-icon bg-red"><i class="fa fa-upload"></i></span>

            <div class="info-box-content">
              <span class="info-box-text">Files Uploaded</span>
              <span class="info-box-number">41</span>
            </div>
            <!-- /.info-box-content -->
          </div>
          <!-- /.info-box -->
        </div>
        <!-- /.col -->

        <!-- fix for small devices only -->
        <div class="clearfix visible-sm-block"></div>

        <div class="col-md-3 col-sm-6 col-xs-12">
          <div class="info-box">
            <span class="info-box-icon bg-green"><i class="ion ion-ios-gear-outline"></i></span>

            <div class="info-box-content">
              <span class="info-box-text">Job Components</span>
              <span class="info-box-number">760</span>
            </div>
            <!-- /.info-box-content -->
          </div>
          <!-- /.info-box -->
        </div>
        <!-- /.col -->
        <div class="col-md-3 col-sm-6 col-xs-12">
          <div class="info-box">
            <span class="info-box-icon bg-yellow"><i class="fa fa-align-left"></i></span>

            <div class="info-box-content">
              <span class="info-box-text">Component Types</span>
              <span class="info-box-number">4</span>
            </div>
            <!-- /.info-box-content -->
          </div>
          <!-- /.info-box -->
        </div>
        <!-- /.col -->
      </div>
      <!-- /.row -->
<style>
    .box.box-primary {
        border-top-color: #4A00E0;
    }

    .box.box-secondary {
        border-top-color: #8E2DE2;
    }
</style>
    <div id="loading" class="row" style="margin-top: 20px; margin-bottom: 20px; display: none;">
            <div class="col-md-12 text-center" >
                <img src="<?php echo base_url(); ?>assets/images/gifs/line.gif" alt="robot" width="900" 
                style="border-radius: 25px; 
                  -webkit-box-shadow: 10px 10px 18px -3px rgba(0,0,0,0.33);
                    -moz-box-shadow: 10px 10px 18px -3px rgba(0,0,0,0.33);
                    box-shadow: 10px 10px 18px -3px rgba(0,0,0,0.33); ">
            </div>
    </div>

       <div id="form" class="row" style="margin-top: 30px;">
        <!-- left column -->
        <div class="col-md-6">
          <!-- general form elements -->
          <div class="box box-primary">
            <div class="box-header with-border">
              <h3 class="box-title">Job Selection</h3>
            </div>
            <!-- /.box-header -->
            <!-- form start -->
            <form role="form">
              <div class="box-body">
                <!-- select -->
                <div class="form-group">
                  <label>Available Jobs</label>
                  <select class="form-control">
                    <option>-- Please, Select a job to start --</option>
                    <option>option 2</option>
                    <option>option 3</option>
                    <option>option 4</option>
                    <option>option 5</option>
                  </select>
                </div>
                <!-- select -->
                <div class="form-group">
                  <label>Available Components</label>
                  <select class="form-control">
                    <option> </option>
                    <option>option 2</option>
                    <option>option 3</option>
                    <option>option 4</option>
                    <option>option 5</option>
                  </select>
                </div>
                <!-- select -->
                <div class="form-group">
                  <label>Avaiable Files Type</label>
                  <select class="form-control">
                    <option> </option>
                    <option>option 2</option>
                    <option>option 3</option>
                    <option>option 4</option>
                    <option>option 5</option>
                  </select>
                </div>
              </div>
              <!-- /.box-body -->

              <!-- <div class="box-footer">
                <button type="submit" class="btn btn-success">Next</button>
              </div> -->

            </form>
          </div>
          <!-- /.box --> 
      </div>


        <div class="col-md-6">
          <!-- general form elements -->
          <div class="box box-secondary">
            <div class="box-header with-border">
              <h3 class="box-title">File Upload</h3>
            </div>
            <!-- /.box-header -->
            <!-- form start -->
              <div class="box-body" style="padding: 20px;">
                <DIV id="dropzone">
                <FORM class="dropzone needsclick" id="demo-upload" action="/upload">
                  <DIV class="dz-message needsclick">    
                    Drop files here or click to upload.<BR>
                    <SPAN class="note needsclick">(This is just a demo dropzone. Selected 
                    files are <STRONG>not</STRONG> actually uploaded.)</SPAN>
                  </DIV>
                </FORM>
              </DIV>
              </div>
              <!-- /.box-body -->

              <div class="box-footer">
                <button id="submit" type="submit" class="btn btn-success">Send Files</button>
              </div>
          </div>
          <!-- /.box --> 
      </div>

        </div><!-- Row -->
    </section>
</div>

<script>
    $(document).ready(function(){
        $('#submit').click(function(){
            $('#form').hide();
            $('#loading').fadeIn(1500);
            $('#loading').delay(5500).fadeOut(1500);
            $('#form').delay(8500).fadeIn(1000);
            
        });
    });
</script>