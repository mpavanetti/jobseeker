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
<div class="content-wrapper" style="height: 80vh;">   
      <?php echo $view[0]->code; ?>
</div>