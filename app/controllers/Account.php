<?php
namespace Base\Controllers;

// Autoload dependencies
require_once __DIR__.'/../../vendor/autoload.php';

//////////////////////
// Standard classes //
//////////////////////
use Base\Core\Controller;
use Base\Core\DatabaseHandler;
use Base\Helpers\Session;
use Base\Helpers\Redirect;
use Base\Helpers\Format;
use \Valitron\Validator;

///////////////////////////
// File-specific classes //
///////////////////////////
use Base\Repositories\UserRepository;
use Base\Repositories\HouseholdRepository;
use Base\Helpers\Email;
use Base\Models\User;
use Base\Factories\UserFactory;
use Base\Factories\HouseholdFactory;

class Account extends Controller{
	protected $dbh,
        $session,
		$request;

	private $userRepository,
		$userFactory;

	public function __construct(DatabaseHandler $dbh, Session $session, $request){
		$this->dbh = $dbh;
		$this->session = $session;
		$this->request = $request;

        // TODO Use dependency injection
		$householdFactory = new HouseholdFactory();
		$householdRepository = new HouseholdRepository($this->dbh->getDB(), $householdFactory);

		$this->userFactory = new UserFactory($householdRepository);
		$this->userRepository = new UserRepository($this->dbh->getDB(), $this->userFactory);
  	}

	public function store(){
		if(isset($this->request['reg_username'])){
			$error = array();

			$input = $this->request;

			$this->validateRegistrationInput($input, 'create');

			$input['password'] = $this->pass_hash($input['password']);
			$user = $this->userFactory->make($input);

			$email = new Email();
			$email->sendEmailAddrConfirm($user->getEmail());
			$this->userRepository->save($user);

			$this->session->flashMessage('success', 'Your account has been created. A confirmation link has been sent to you. Please confirm your email address to activate your account.');
			Redirect::toControllerMethod('Account', 'showLogin');
		}
	}

	public function create(){
			$this->view('auth/register');
	}

	public function logout(){
		$this->session->remove('user');
		$this->session->remove('username');
		$this->session->remove('id');
		session_destroy();
		Redirect::toControllerMethod('Account', 'showLogin');
	}

	public function pass_hash($password){
		for($i = 0; $i < 1000; $i++) $password = hash('sha256',trim(addslashes($password)));
		return $password;
	}

	public function confirmEmail($email,$code){
		// Handle circumvention of email confirmation
		$salt = 'QM8z7AnkXUKQzwtK7UcA';
		if(urlencode(hash('sha256',$email.$salt) != $code)){
			$this->session->flashMessage('danger', 'Your password reset link is invalid. Please reset your password again.');
			Redirect::toControllerMethod('Account', 'showLogin');
		}

		// set as confirmed in the db
		$this->userRepository->confirmEmail($email);

		// Redirect to login
		$this->session->flashMessage('success', 'Your email address has been confirmed. Please log in.');
		Redirect::toControllerMethod('Account', 'showLogin');
	}

	public function forgotPassword(){
		// Get temp pass code and email
		$code = urlencode(hash('sha256',rand(1000000000,10000000000)));
		$email = addslashes(trim($this->request['email']));

		// Check if email exists in db
		$u = $this->userRepository->get('email',$email);

		if($email == ''){
			$this->view('auth/login',['message'=>'No email has been supplied.']);
		}
		else if(!$u){
			$this->view('auth/login',['message'=>'Not Found. An email has been sent with instructions to reset your password.']);
		}
		else {
			$this->userRepository->setPassTemp($email,$code);
			// send Email
			$emailHandler = new Email();
			$emailHandler->sendPasswordReset($email,$code);

			// Redirect to login
			$this->session->flashMessage('success', 'An email has been sent with instructions to reset your password..');
			Redirect::toControllerMethod('Account', 'showLogin');
		}
	}

	public function resetPassword($email,$code){
		// Check if email exists in db
		$u = $this->userRepository->get('email',$email);
		if(!$u)
			$this->view('auth/login',['message'=>'An error has occured. Please try again. Email.']);
		// Check if reset code has been set
		else if($u['passTemp'] == '')
			$this->view('auth/login',['message'=>'An error has occured. Please try again. tempPass not set.']);
		// Check if code matches db
		else if($u['passTemp'] != $code)
			$this->view('auth/login',['message'=>'An error has occured. Please try again. Code.']);
		else{
			// Reset page has been submitted
			if(isset($this->request['rst_password'])){
				// Reset password
				$this->userRepository->setValue('password',$this->pass_hash($this->request['rst_password']),'email',$email);
				// Reset temp pass
				$this->userRepository->setValue('passTemp','','email',$email);
				// Redirect to login
				$this->view('auth/login',['message'=>'Password has been reset. Please login.']);
			}
			else{
				// Direct to reset pass view
				$this->view('auth/resetPassword',['email'=>$email,'code'=>$code]);
			}
		}
	}

	public function settings(){
		// $user = $this->session->get('user');
		$this->view('auth/settings', compact($user));
	}

	public function update(){
		$user = $this->session->get('user');

		// Check for blank fields
		$fields = array('firstName','lastName','email');
		foreach($fields as $f){
			if(!isset($this->request[$f])){
				die('All fields are required');
			}
		}
		// Handle password update
		if(isset($this->request['password'])){
			if($this->request['password'] != $this->request['confirmPassword']){
				die('Passwords don\'t match');
			}
			$user->setPassword($this->pass_hash($this->request['password']));
		}
		// Handle name updated
		if($this->request['firstName'].' '.$this->request['lastName'] != $user->getFirstName().' '.$user->getLastName()){
			$user->setFirstname($this->request['firstName']);
			$user->setLastName($this->request['lastName']);
		}

		$this->userRepository->save($user);

		// Update user in the session
		$this->session->add('user', $user);

		// Handle email updated
		if($this->request['email'] != $user->getEmail()){
			// send Email
			$emailHandler = new Email();
			$emailHandler->sendEmailUpdateConfirm($this->request['email'],$user->getEmail());
			$this->session->flashMessage('success', 'A confirmation email has been sent to '.$this->request['email'].'. Please confirm to update.');
			Redirect::toControllerMethod('Account', 'settings');
			return;
		}

		$this->session->flashMessage('success', 'Your account has been updated. Return to <a href="/Account/dashboard/">Dashboard</a>.');
		Redirect::toControllerMethod('Account', 'settings');

	}

	public function confirmNewEmail($email,$old_email,$code){
		// Handle circumvention of email confirmation
		$salt = 'QM8z7AnkXUKQzwtK7UcA';
		if(urlencode(hash('sha256',$email.$salt.$old_email) != $code)){
			$this->session->flashMessage('danger', 'Your email confirmation link is invalid.');
			Redirect::toControllerMethod('Account', 'showLogin');
		}

		// update in the db
		$this->userRepository->setValue('email',$email,'email',$old_email);

		// Redirect to login
		$this->session->flashMessage('success', 'Your email address has been updated.');
		Redirect::toControllerMethod('Account', 'dashboard');

	}

	public function delete(){
		$user = $this->session->get('user');

		$this->userRepository->remove($user);
		// Remove everything from session
		$this->session->flush();

		$this->session->flashMessage('success', 'Your account has been deleted.');
		Redirect::toControllerMethod('Account', 'showLogin');

	}

	public function dashboard(){
		$user = $this->session->get('user');

		if(!$user){
			Redirect::toControllerMethod('Account', 'showLogin');
			return;
		}

		if(empty($user->getHouseholds())){
			$this->view('/auth/newHousehold');
			return;
		}
		$this->view('/dashboard/index', ['username' => $user->getUsername(), 'name' => $user->getName(), 'profilePic' => $user->getProfilePic()]);
	}

	public function showLogin(){
		$user = $this->session->get('user');

		// Active session
		if($user){
			Redirect::toControllerMethod('Account', 'dashboard');
			return;
		}
		$this->view('/auth/login',['message'=>'']);
	}

	public function logInUser(){
		$user = $this->session->get('user');
		$input = $this->request;

		// Redirect to dashboard if user is already logged in
		if($user){
			Redirect::toControllerMethod('Account', 'dashboard');
			return;
		}

		// Validate input
		$this->validateLoginInput($input, 'showLogin');

		// Hash password
		$password = $this->pass_hash($input['login_password']);

		// Check credentials
		$user = $this->userRepository->checkUser($input['login_username'],$password);

		if(!$user) {
			// If credentials are not valid, set error message
			$message = 'Incorrect username or password.';
		}
		else if(!$user->getActivated()){
			// If credentials are valid, but user is inactive, set error message
			$message = 'Please confirm your email before you can log in.';
		}
		else {
			// If credentials are valid and user is active, log in user
			// $this->session->add('username', $user->getUsername());
			// $this->session->add('id', $user->getId());
			$this->session->add('user', $user);

			Redirect::toControllerMethod('Account', 'dashboard');
			return;
		}

		$this->session->flashMessage('danger', $message);
		Redirect::toControllerMethod('Account', 'showLogin');
	}


	/**
     * Validates user input from login form
     * @param array $input  	Login form input
     * @param string $method 	Method to redirect to
     * @param array $params 	Parameters for the redirection method
     */
    private function validateLoginInput($input, $method, $params = NULL):void {
        $this->session->flashOldInput($input);

        // Validate input
        $validator = new Validator($input);
        $rules = [
            'required' => [
				['login_username'],
                ['login_password'],
            ],
            'slug' => [
                ['login_username'],
            ],
			'lengthMin' => [
				['login_username', 5],
		        ['login_password', 6]
		    ],
			'lengthMax' => [
		        ['login_password', 30]
		    ]
        ];
        $validator->rules($rules);
        $validator->labels(array(
            'login_username' => 'Username',
            'login_password' => 'Password'
        ));

        if(!$validator->validate()) {

            $errorMessage = Format::validatorErrors($validator->errors());
            // Flash danger message
            $this->session->flashMessage('danger', $errorMessage);

            // Redirect back with errors
            Redirect::toControllerMethod('Account', $method, $params);
            return;
        }
    }

	/**
     * Validates user input from registration form
     * @param array $input  	Login form input
     * @param string $method 	Method to redirect to
     * @param array $params 	Parameters for the redirection method
     */
    private function validateRegistrationInput($input, $method, $params = NULL):void {
        $this->session->flashOldInput($input);

        // Validate input
        $validator = new Validator($input);
        $rules = [
            'required' => [
				['reg_username'],
				['reg_namefirst'],
				['reg_namelast'],
				['reg_email'],
				['reg_password'],
				['reg_password2']
            ],
            'equals' => [
                ['reg_password', 'reg_password2'],
            ],
			'email' => [
                ['reg_email'],
            ],
			'slug' => [
                ['reg_username'],
            ],
			'lengthMin' => [
				['reg_username', 5],
				['reg_namefirst', 2],
				['reg_namelast', 2],
				['reg_email', 5],
				['reg_password', 6],
		        ['reg_password2', 6]
		    ],
			'lengthMax' => [
				['reg_username', 32],
				['reg_namefirst', 32],
				['reg_namelast', 32],
				['reg_email', 64],
				['reg_password', 30],
		        ['reg_password2', 30]
		    ]
        ];
        $validator->rules($rules);
        $validator->labels(array(
			'reg_username' => 'Username',
			'reg_namefirst' => 'First Name',
			'reg_namelast' => 'Last Name',
			'reg_email' => 'Email Address',
			'reg_password' => 'Password',
			'reg_password2' => 'Password Confirmation'
        ));

        if(!$validator->validate()) {

            $errorMessage = Format::validatorErrors($validator->errors());
            // Flash danger message
            $this->session->flashMessage('danger', $errorMessage);

            // Redirect back with errors
            Redirect::toControllerMethod('Account', $method, $params);
            return;
        }
    }
		public function changePicture(){
			// show form
			if(($this->request['submit'] ?? NULL) == ''){
				$this->view('/auth/changePic',['message'=>'']);
			}
			// upload
			else{
				$user = $this->session->get('user');

				$target_dir = __DIR__.'/../../public/images/users/';
				$target_file = $target_dir . basename($_FILES["fileToUpload"]["name"]);
				$newFilename = $this->pass_hash($user->getId());
				$uploadOk = 1;
				$imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
				// Check if image file is a actual image or fake image
				if(isset($_POST["submit"])) {
				    $check = getimagesize($_FILES["fileToUpload"]["tmp_name"]);
				    if($check !== false) {
				        $uploadOk = 1;
				    } else {
				        die("File is not an image.");
				        $uploadOk = 0;
				    }
				}
				// Check file size
				if ($_FILES["fileToUpload"]["size"] > 500000) {
				    die("Sorry, your file is too large.");
				    $uploadOk = 0;
				}
				// Allow certain file formats
				if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
				&& $imageFileType != "gif" ) {
				    die("Sorry, only JPG, JPEG, PNG & GIF files are allowed.");
				    $uploadOk = 0;
				}
				// Check if $uploadOk is set to 0 by an error
				if ($uploadOk == 0) {
				    die("Sorry, your file was not uploaded.");
				// if everything is ok, try to upload file
				} else {
				    if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_dir .$newFilename)) {
								$this->userRepository->setProfilePicture($user,$newFilename);
								$updatedUser = $this->userRepository->find($user->getUsername());
								$this->session->add('user',$updatedUser);
								Redirect::toControllerMethod('Account', 'Dashboard');
				    } else {
				       die("Sorry, there was an error uploading your file.");
				    }
				}
			}
		}


}
?>
