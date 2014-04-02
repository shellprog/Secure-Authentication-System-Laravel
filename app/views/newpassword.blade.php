@extends('master')

@section('content')
{{Form::open(array('url'=>'/newpassword','method'=>'post'))}}
<h1>New Password</h1>
<input type="hidden" name="email" value="{{Input::get('email')}}">
<input type="hidden" name="resetcode" value="{{Input::get('resetcode')}}">
<div>
	<input type="password" placeholder="New Password" required="" id="password" name="password"/>
</div>
<div>
	<input type="password" placeholder="Confirm Password" required="" id="password" name="password_confirmation"/>
</div>

<div>
	<input type="submit" value="Save" />
	<a href="/register">Register</a>
	<a href="/login">Login</a>
</div>
{{Form::close()}}
@stop