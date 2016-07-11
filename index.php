<?php
	
	error_reporting( E_ERROR );

	ini_set( 'display_errors', 1 );

	require_once 'Scrape.php';

	$e = get_info( $_GET['isbn'], $_GET['site'] );
?>
<html>
<head>
	<title>Bookworm</title>
	<meta charset="utf-8">
	<link rel="icon" href="favicon.ico">
	<link rel="stylesheet" href="style.css">
</head>
<body>
	
	<ul class="<?= $e->site ?>">
	    
	    <li class="<?= $e->empty ?>">
			<content>
				
				<header>
					<?= $e->title ?>
					<a href="<?= $e->toggle ?>">
						<img src="<?= $e->icon ?>" class="busy">
					</a>
				</header>
				
				<img src="<?= $e->cover ?>" class="cover">
				
				<rating><?= $e->stars ?></rating>
				
				<form>
					<input type="number" name="isbn" value="<?= $e->isbn ?>" placeholder="ISBN">
					<input type="hidden" name="site" value="<?= $e->site ?>">
					<input type="submit" value="Get" class="busy">
				</form>
			
			</content>
	    </li>
		

		<? foreach( $e->reviews as $r ) : ?>
			<li>
			    <content>
			      
			      <rating><?= str_repeat( '<star>☆</star>', $r->rating ) ?></rating>
			      
			      <scroll><p><?= $r->content ?></p></scroll>
			      
			      <likes><?= $r->likes ?> likes</likes>

			    </content> 
		    </li>
	    <? endforeach ?>

	    <busy>loading</busy>

	</ul>

	<script>
		var els = document.querySelectorAll( '.busy' )
		
		for( var i = 0; i < els.length; i++ )
		{
			els[ i ].addEventListener( 'click', function() {
				document.querySelector('busy').style.display = 'block'
			})
		}
	</script>
</body>
</html>