<!DOCTYPE html>
<!--[if lt IE 7 ]> <html lang="en" class="ie6 ielt8"> <![endif]-->
<!--[if IE 7 ]>    <html lang="en" class="ie7 ielt8"> <![endif]-->
<!--[if IE 8 ]>    <html lang="en" class="ie8"> <![endif]-->
<!--[if (gte IE 9)|!(IE)]><!-->
<html lang="en">
	<!--<![endif]-->
	<head>
		<meta charset="utf-8">
		<title>KodeInfo Secure Login System</title>
		<link rel="stylesheet" type="text/css" href="styles.css" />
		<link rel="stylesheet" type="text/css" href="/zocial/zocial.css" />
		<!-- Latest compiled and minified CSS -->
		<link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap.min.css">
		<script src="http://code.jquery.com/jquery-1.11.0.min.js"></script>
		<script src="http://code.jquery.com/jquery-migrate-1.2.1.min.js"></script>
		<!-- Latest compiled and minified JavaScript -->
		<script src="//netdna.bootstrapcdn.com/bootstrap/3.1.1/js/bootstrap.min.js"></script>
		<style type="text/css">
			.zocial{
				width: 220px;
				margin-bottom: 20px;
			}
			
			.zocial a:hover{
				text-decoration:none;
				margin-bottom: 20px;
			}
		</style>

	</head>
	<body>
		<div class="container">
			<section id="content">
				@if($errors->has())
				<div class="alert alert-danger">
					<button type="button" class="close" data-dismiss="alert">
						×
					</button>
					@foreach ($errors->all() as $error)
					<li>
						{{ $error }}
					</li>
					@endforeach
				</div>
				@endif
				
				@if(Session::has('error_msg'))
				<div class="alert alert-danger">{{Session::get('error_msg')}}</div>
				@endif
				
				@if(Session::has('success_msg'))
				<div class="alert alert-success">{{Session::get('success_msg')}}</div>
				@endif
				
				@yield('content')
			</section>
		</div>
	</body>
</html>