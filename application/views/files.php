        <!-- jQuery UI (REQUIRED) -->
        <link rel="stylesheet" type="text/css" href="//cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css">

        <!-- elFinder CSS (REQUIRED) -->
        <link rel="stylesheet" type="text/css" href="<?php echo base_url(); ?>elfinder/css/elfinder.full.css?v=2">
        <link rel="stylesheet" type="text/css" href="<?php echo base_url(); ?>elfinder/css/theme.css">

        <!--[if gte IE 9]><!-->
        <script src="//cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
        <!--<![endif]-->
        <script src="//cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>

        <!-- elFinder JS (REQUIRED) -->
        <script src="<?php echo base_url(); ?>elfinder/js/elfinder.min.js"></script>

        <!-- Extra contents editors (OPTIONAL) -->
        <script src="<?php echo base_url(); ?>elfinder/js/extras/editors.default.min.js"></script>

        <!-- GoogleDocs Quicklook plugin for GoogleDrive Volume (OPTIONAL) -->
        <!--<script src="js/extras/quicklook.googledocs.js"></script>-->



<script type="text/javascript" charset="utf-8">
            // Documentation for client options:
            // https://github.com/Studio-42/elFinder/wiki/Client-configuration-options
            $(document).ready(function() {
                $('#elfinder').elfinder(
                    // 1st Arg - options
                    {
                        cssAutoLoad : false,               // Disable CSS auto loading
                        baseUrl : './',                    // Base URL to css/*, js/*
                        url : '<?php echo base_url(); ?>/elfinder/php/connector.minimal.php'  // connector URL (REQUIRED)
                        // , lang: 'ru'                    // language (OPTIONAL)
                        , height: '680'
                    },
                    // 2nd Arg - before boot up function
                    function(fm, extraObj) {
                        // `init` event callback function
                        fm.bind('init', function() {
                            // Optional for Japanese decoder "encoding-japanese.js"
                            if (fm.lang === 'ja') {
                                fm.loadScript(
                                    [ '//cdn.rawgit.com/polygonplanet/encoding.js/1.0.26/encoding.min.js' ],
                                    function() {
                                        if (window.Encoding && Encoding.convert) {
                                            fm.registRawStringDecoder(function(s) {
                                                return Encoding.convert(s, {to:'UNICODE',type:'string'});
                                            });
                                        }
                                    },
                                    { loadType: 'tag' }
                                );
                            }
                        });
                        // Optional for set document.title dynamically.
                        var title = document.title;
                        fm.bind('open', function() {
                            var path = '',
                                cwd  = fm.cwd();
                            if (cwd) {
                                path = fm.path(cwd.hash) || null;
                            }
                            document.title = path? path + ':' + title : title;
                        }).bind('destroy', function() {
                            document.title = title;
                        });
                    }
                );
            });
        </script>

  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <h1>
        File Manager
      </h1>
      <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
        <li><a href="#">Examples</a></li>
        <li class="active">Blank page</li>
      </ol>
    </section>

    <!-- Main content -->
    <section class="content">

      <!-- Default box -->
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">File Manager </h3>

          <div class="box-tools pull-right">
            <button type="button" class="btn btn-box-tool" data-widget="collapse" data-toggle="tooltip"
                    title="Collapse">
              <i class="fa fa-minus"></i></button>
            <button type="button" class="btn btn-box-tool" data-widget="remove" data-toggle="tooltip" title="Remove">
              <i class="fa fa-times"></i></button>
          </div>
        </div>
        <div class="box-body" style="height: 700px;">

              <!-- Element where elFinder will be created (REQUIRED) -->
            <div id="elfinder" height="800"></div>

        </div>
        <!-- /.box-body -->
      </div>
      <!-- /.box -->

    </section>
    <!-- /.content -->
  </div>
  <!-- /.content-wrapper -->