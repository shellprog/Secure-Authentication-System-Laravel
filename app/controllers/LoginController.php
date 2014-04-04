<?php

class LoginController extends BaseController {

	public function __construct() {
		//We will implement Filters later
		$this -> beforeFilter('csrf', array('on' => 'post'));
	}

	public function dashboard() {
		return View::make('index');
	}

	public function loginWithSocial($social_provider, $action = "") {
		// check URL segment
		if ($action == "auth") {

			// process authentication
			try {
				Session::set('provider', $social_provider);
				Hybrid_Endpoint::process();
			} catch (Exception $e) {
				// redirect back to http://URL/social/
				return Redirect::route('loginWith');
			}
			return;
		}

		try {
			// create a HybridAuth object
			$socialAuth = new Hybrid_Auth(app_path() . '/config/hybridauth.php');
			// authenticate with Provider
			$provider = $socialAuth -> authenticate($social_provider);

			// fetch user profile
			$userProfile = $provider -> getUserProfile();
		} catch(Exception $e) {
			// exception codes can be found on HybBridAuth's web site
			Session::flash('error_msg', $e -> getMessage());
			return Redirect::to('/login');
		}

		$this -> createOAuthProfile($userProfile);

		return Redirect::to('/');
	}

	public function createOAuthProfile($userProfile) {

		if (isset($userProfile -> username)){
			$username = strlen($userProfile -> username) > 0 ? $userProfile -> username : "";
		}
		
		if (isset($userProfile -> screen_name)){
			$username = strlen($userProfile -> screen_name) > 0 ? $userProfile -> screen_name : "";
		}
		
		if (isset($userProfile -> displayName)){
			$username = strlen($userProfile -> displayName) > 0 ? $userProfile -> displayName : "";
		}

		$email = strlen($userProfile -> email) > 0 ? $userProfile -> email : "";
		$email = strlen($userProfile -> emailVerified) > 0 ? $userProfile -> emailVerified : "";

		$password = $this -> generatePassword();

		if (Profile::where('email', $email) -> count() <= 0) {
			$user = Sentry::register(array('email' => $email, 'password' => $password), true);

			try {
				$user_group = Sentry::findGroupById(1);
			} catch (Cartalyst\Sentry\Groups\GroupNotFoundException $e) {
				$this -> createGroup('users');
				$this -> createGroup('admin');
				$user_group = Sentry::findGroupById(1);
			}

			$user -> addGroup($user_group);

			$profile = new Profile();

			$profile -> user_id = $user -> getId();
			$profile -> email = $email;
			$profile -> username = $username;
			$profile -> save();
		}
		//Login user
		//Try to authenticate user
		try {
			$user = Sentry::findUserByLogin($email);

			$throttle = Sentry::getThrottleProvider() -> findByUserId($user -> id);

			$throttle -> check();

			//Authenticate user
			$credentials = array('email' => $email, 'password' => Input::get('password'));

			Sentry::login($user, false);

			//At this point we may get many exceptions lets handle all user management and throttle exceptions
		} catch (Cartalyst\Sentry\Users\LoginRequiredException $e) {
			Session::flash('error_msg', 'Login field is required.');
			return Redirect::to('/login');
		} catch (Cartalyst\Sentry\Users\PasswordRequiredException $e) {
			Session::flash('error_msg', 'Password field is required.');
			return Redirect::to('/login');
		} catch (Cartalyst\Sentry\Users\WrongPasswordException $e) {
			Session::flash('error_msg', 'Wrong password, try again.');
			return Redirect::to('/login');
		} catch (Cartalyst\Sentry\Users\UserNotFoundException $e) {
			Session::flash('error_msg', 'User was not found.');
			return Redirect::to('/login');
		} catch (Cartalyst\Sentry\Users\UserNotActivatedException $e) {
			Session::flash('error_msg', 'User is not activated.');
			return Redirect::to('/login');
		} catch (Cartalyst\Sentry\Throttling\UserSuspendedException $e) {
			Session::flash('error_msg', 'User is suspended ');
			return Redirect::to('/login');
		} catch (Cartalyst\Sentry\Throttling\UserBannedException $e) {
			Session::flash('error_msg', 'User is banned.');
			return Redirect::to('/login');
		}

	}

	public function generatePassword($length = 9, $strength = 4) {
		$vowels = 'aeiouy';
		$consonants = 'bcdfghjklmnpqrstvwxz';
		if ($strength & 1) {
			$consonants .= 'BCDFGHJKLMNPQRSTVWXZ';
		}
		if ($strength & 2) {
			$vowels .= "AEIOUY";
		}
		if ($strength & 4) {
			$consonants .= '23456789';
		}
		if ($strength & 8) {
			$consonants .= '@#$%';
		}

		$password = '';
		$alt = time() % 2;
		for ($i = 0; $i < $length; $i++) {
			if ($alt == 1) {
				$password .= $consonants[(rand() % strlen($consonants))];
				$alt = 0;
			} else {
				$password .= $vowels[(rand() % strlen($vowels))];
				$alt = 1;
			}
		}
		return $password;
	}

	//Show login Form
	public function showLogin() {
		//Show login is user not loggedin already or else go to dashboard
		if (Sentry::check()) {
			return Redirect::to('/');
		}

		return View::make('login');
	}

	//Authenticate User
	public function storeLogin() {
		$inputs = array('identity' => Input::get('identity'), 'password' => Input::get('password'));
		//Since user can enter username,email we cannot have email validator
		$rules = array('identity' => 'required|min:4|max:32', 'password' => 'required|min:6');

		//Find is that username or password and change identity validation rules
		//Lets use regular expressions
		if (filter_var(Input::get('identity'), FILTER_VALIDATE_EMAIL)) {
			//It is email
			$rules['identity'] = 'required|min:4|max:32|email';
		} else {
			//It is username . Check if username exist in profile table
			if (Profile::where('username', Input::get('identity')) -> count() > 0) {
				//User exist so get email address
				$user = Profile::where('username', Input::get('identity')) -> first();
				$inputs['identity'] = $user -> email;

			} else {
				Session::flash('error_msg', 'User does not exist');
				return Redirect::to('/login') -> withInput(Input::except('password'));
			}
		}

		$v = Validator::make($inputs, $rules);

		if ($v -> fails()) {
			return Redirect::to('/login') -> withErrors($v) -> withInput(Input::except('password'));
		} else {
			try {
				//Try to authenticate user
				$user = Sentry::getUserProvider() -> findByLogin(Input::get('identity'));

				$throttle = Sentry::getThrottleProvider() -> findByUserId($user -> id);

				$throttle -> check();

				//Authenticate user
				$credentials = array('email' => Input::get('identity'), 'password' => Input::get('password'));

				//For now auto activate users
				$user = Sentry::authenticate($credentials, false);

				//At this point we may get many exceptions lets handle all user management and throttle exceptions
			} catch (Cartalyst\Sentry\Users\LoginRequiredException $e) {
				Session::flash('error_msg', 'Login field is required.');
				return Redirect::to('/login');
			} catch (Cartalyst\Sentry\Users\PasswordRequiredException $e) {
				Session::flash('error_msg', 'Password field is required.');
				return Redirect::to('/login');
			} catch (Cartalyst\Sentry\Users\WrongPasswordException $e) {
				Session::flash('error_msg', 'Wrong password, try again.');
				return Redirect::to('/login');
			} catch (Cartalyst\Sentry\Users\UserNotFoundException $e) {
				Session::flash('error_msg', 'User was not found.');
				return Redirect::to('/login');
			} catch (Cartalyst\Sentry\Users\UserNotActivatedException $e) {
				Session::flash('error_msg', 'User is not activated.');
				return Redirect::to('/login');
			} catch (Cartalyst\Sentry\Throttling\UserSuspendedException $e) {
				Session::flash('error_msg', 'User is suspended ');
				return Redirect::to('/login');
			} catch (Cartalyst\Sentry\Throttling\UserBannedException $e) {
				Session::flash('error_msg', 'User is banned.');
				return Redirect::to('/login');
			}

			Session::flash('success_msg', 'Loggedin Successfully');
			return Redirect::to('/');

		}

	}

	public function registerActivate($userId, $activationCode) {
		try {
			// Find the user using the user id
			$user = Sentry::findUserById($userId);

			// Attempt to activate the user
			if ($user -> attemptActivation($activationCode)) {
				Session::flash('success_msg', 'User Activation Successfull Please login below.');
				return Redirect::to('/login');
			} else {
				Session::flash('error_msg', 'Unable to activate user Try again later or contact Support Team.');
				return Redirect::to('/register');
			}
		} catch (Cartalyst\Sentry\Users\UserNotFoundException $e) {
			Session::flash('error_msg', 'User was not found.');
			return Redirect::to('/register');
		} catch (Cartalyst\Sentry\Users\UserAlreadyActivatedException $e) {
			Session::flash('error_msg', 'User is already activated.');
			return Redirect::to('/register');
		}
	}

	//Show register Form
	public function showRegister() {
		return View::make('register');
	}

	//Register User
	public function storeRegister() {
		// Gather Sanitized Input
		$input = array('username' => Input::get('username'), 'email' => Input::get('email'), 'password' => Input::get('password'), 'password_confirmation' => Input::get('password_confirmation'));

		// Set Validation Rules
		$rules = array('username' => 'required|min:4|max:20|unique:profile,username', 'email' => 'required|min:4|max:32|email', 'password' => 'required|min:6|confirmed', 'password_confirmation' => 'required');

		//Run input validation
		$v = Validator::make($input, $rules);

		if ($v -> fails()) {
			return Redirect::to('/register') -> withErrors($v) -> withInput(Input::except(array('password', 'password_confirmation')));
		} else {

			try {
				//Pre activate user
				$user = Sentry::register(array('email' => $input['email'], 'password' => $input['password']), true);
				//$user = Sentry::register(array('email' => $input['email'], 'password' => $input['password']));

				//Get the activation code & prep data for email
				$data['activationCode'] = $user -> GetActivationCode();
				$data['email'] = $input['email'];
				$data['userId'] = $user -> getId();

				//send email with link to activate.
				/*Mail::send('emails.register_confirm', $data, function($m) use ($data) {
				 $m -> to($data['email']) -> subject('Thanks for Registration - Support Team');
				 });*/

				//If no groups created then create new groups
				try {
					$user_group = Sentry::findGroupById(1);
				} catch (Cartalyst\Sentry\Groups\GroupNotFoundException $e) {
					$this -> createGroup('users');
					$this -> createGroup('admin');
					$user_group = Sentry::findGroupById(1);
				}

				$user -> addGroup($user_group);

				$user = new Profile();

				$user -> user_id = $data['userId'];
				$user -> email = $data['email'];
				$user -> username = $input['username'];
				$user -> save();

				//success!
				Session::flash('success_msg', 'Thanks for sign up . Please activate your account by clicking activation link in your email');
				return Redirect::to('/register');

			} catch (Cartalyst\Sentry\Users\LoginRequiredException $e) {
				Session::flash('error_msg', 'Username/Email Required.');
				return Redirect::to('/register') -> withErrors($v) -> withInput(Input::except(array('password', 'password_confirmation')));
			} catch (Cartalyst\Sentry\Users\UserExistsException $e) {
				Session::flash('error_msg', 'User Already Exist.');
				return Redirect::to('/register') -> withErrors($v) -> withInput(Input::except(array('password', 'password_confirmation')));
			}

		}
	}

	public function createGroup($groupName) {
		$input = array('newGroup' => $groupName);

		// Set Validation Rules
		$rules = array('newGroup' => 'required|min:4');

		//Run input validation
		$v = Validator::make($input, $rules);

		if ($v -> fails()) {
			return false;
		} else {
			try {
				$group = Sentry::getGroupProvider() -> create(array('name' => $input['newGroup'], 'permissions' => array('admin' => Input::get('adminPermissions', 0), 'users' => Input::get('userPermissions', 0), ), ));

				if ($group) {
					return true;
				} else {
					return false;
				}

			} catch (Cartalyst\Sentry\Groups\NameRequiredException $e) {
				return false;
			} catch (Cartalyst\Sentry\Groups\GroupExistsException $e) {
				return false;
			}
		}
	}

	//Show forgotpassword Form
	public function showForgotpassword() {
		return View::make('forgotpassword');
	}

	//Send email for forgot password
	public function storeForgotpassword() {
		if (Input::has('email')) {

			$email = Input::get('email');

			try {
				// Find the user using the user email address
				$user = Sentry::findUserByLogin($email);

				// Get the password reset code
				$resetCode = $user -> getResetPasswordCode();

				Mail::send("emails.resetpassword", array("email" => $email, "resetCode" => $resetCode), function($message) use ($email, $resetCode) {
					$message -> to($email) -> subject('Follow the link to reset your password');
				});

				Session::flash('success_msg', 'We have sent a link to your email account please follow that to reset your password');
				return Redirect::to('/forgotpassword');

				// Now you can send this code to your user via email for example.
			} catch (Cartalyst\Sentry\Users\UserNotFoundException $e) {
				Session::flash('error_msg', 'User not found');
				return Redirect::to('/forgotpassword');
			}
		} else {
			Session::flash('error_msg', 'User not found');
			return Redirect::to('/forgotpassword');
		}

	}

	//Show newpassword Form
	public function showNewPassword() {
		if (Input::has('email') && Input::has('resetcode')) {

			try {
				// Find the user using the user id
				$user = Sentry::findUserByLogin(Input::get('email'));

				// Check if the reset password code is valid
				if ($user -> checkResetPasswordCode(Input::get('resetcode'))) {
					return View::make('newpassword');

				} else {
					Session::flash('error_msg', 'Invalid request . Please enter email to reset your password');
					return Redirect::to('/forgotpassword');
				}
			} catch (Cartalyst\Sentry\Users\UserNotFoundException $e) {
				Session::flash('error_msg', 'User not found');
				return Redirect::to('/forgotpassword');
			}
		} else {
			Session::flash('error_msg', 'Invalid request . Please enter email to reset your password');
			return Redirect::to('/forgotpassword');
		}
	}

	//Store new password
	public function storeNewPassword() {
		//Validator to check password ,password confirmation
		$input = array('password' => Input::get('password'), 'password_confirmation' => Input::get('password_confirmation'));

		$rules = array('password' => 'required|min:4|max:32|confirmed', 'password_confirmation' => 'required|min:4|max:32');

		$v = Validator::make($input, $rules);

		if ($v -> passes()) {
			if (Input::has('email') && Input::has('resetcode')) {

				try {
					// Find the user using the user id
					$user = Sentry::findUserByLogin(Input::get('email'));

					// Check if the reset password code is valid
					if ($user -> checkResetPasswordCode(Input::get('resetcode'))) {
						// Attempt to reset the user password
						if ($user -> attemptResetPassword(Input::get('resetcode'), Input::get('password'))) {
							Session::flash('success_msg', 'Password changed successfully . Please login below');
							return Redirect::to('/login');
						} else {
							Session::flash('error_msg', 'Unable to reset password . Please try again');
							return Redirect::to('/forgotpassword');
						}
					} else {
						Session::flash('error_msg', 'Unable to reset password . Please try again');
						return Redirect::to('/forgotpassword');
					}
				} catch (Cartalyst\Sentry\Users\UserNotFoundException $e) {
					Session::flash('error_msg', 'User not found');
					return Redirect::to('/forgotpassword');
				}
			} else {
				Session::flash('error_msg', 'Invalid request . Please enter email to reset your password');
				return Redirect::to('/forgotpassword');
			}
		} else {
			return Redirect::to('/newpassword?email=' . Input::get('email') . '&resetcode=' . Input::get('resetcode')) -> withErrors($v) -> withInput();
		}
	}

	public function getLogout() {
		Sentry::logout();
		return Redirect::to('/login');
	}

}
