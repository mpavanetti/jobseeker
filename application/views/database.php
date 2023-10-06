<style>
    body {
      margin: 0;
    }
    iframe {
      height:calc(89vh - 4px);
      width:calc(100vw - 4px);
      box-sizing: border-box;
    }
</style>
<script>
	$(document).ready(function(){

		$('body').addClass('sidebar-collapse');

	});
	
</script>
<div class="content-wrapper">   
      <iframe src="<?php echo base_url() ?>adminer/" frameborder="0" allowfullscreen></iframe>
</div>