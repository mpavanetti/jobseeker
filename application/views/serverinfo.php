<style>
  body {
    margin: 0;
  }
  iframe {
    height:calc(89vh - 4px);
    width:calc(98vw - 4px);
    box-sizing: border-box;
  }

 
</style>
<script>
	$(document).ready(function(){

		$('body').addClass('sidebar-collapse');

	});
	
</script>
<div class="content-wrapper"> 
   <div class="container spin">
     <div class="row">
       <div class="text-center" style="margin-top: 300px;">
         <div class="overlay">
          <i class="fa fa-refresh fa-spin" style="font-size: 150px;"></i>
        </div> 
       </div>
     </div>
   </div> 
  <iframe src="<?php echo base_url() ?>serverstatistics" frameborder="0" allowfullscreen ></iframe style="display: none;"> 
</div>


<script type="text/javascript">
  
  $('.spin').fadeOut();
  $('iframe').fadeIn();

</script>