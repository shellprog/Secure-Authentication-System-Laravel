@extends('master')

@section('content')
{{Form::open(array('url'=>'/login','method'=>'post'))}}
<h1>Login Form</h1>
<div>
	<input value="{{Input::old('identity')}}" type="text" placeholder="Username/Email" required="" id="username" name="identity"/>
</div>
<div>
	<input type="password" placeholder="Password" required="" id="password" name="password"/>
</div>
<div>
	<input type="submit" value="Log in" />
	<a href="/forgotpassword">Lost your password?</a>
	<a href="/register">Register</a>
</div>
{{Form::close()}}

@stop