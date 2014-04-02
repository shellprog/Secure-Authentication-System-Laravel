@extends('master')

@section('content')
{{Form::open(array('url'=>'/forgotpassword','method'=>'post'))}}
<h1>Forgot Password</h1>
<div>
	<input value="{{Input::old('email')}}" type="text" placeholder="Enter email" required="" id="username" name="email"/>
</div>
<div>
	<input style="width:130px;" type="submit" value="Reset Password" />
	<a href="/register">Register</a>
	<a href="/login">Login</a>
</div>
{{Form::close()}}
@stop