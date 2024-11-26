<?php



/*add_filter('nsl_google_register_new_user', 'uwp_RoleFunction', 11);
function uwp_RoleFunction($user_id){
     echo "($:";
        $user = new WP_User($user_id);
        $user->set_role('author');
        dıe;
        exit;
}*/

/*add_filter('nsl_register_new_user', function ($user_id) {
    echo "nsl_register_new_user:";
    print_r(NextendSocialLogin::getTrackerData());
    if (NextendSocialLogin::getTrackerData() == 'editor') {
        $user = new WP_User($user_id);
        $user->set_role('author');
    }
    die;
    exit;
});*/


/*
add_filter('nsl_pre_register_new_user___', function($a){
    print_r($_GET['loginSocial']);
    die;
    $log = new Logger();
    $log->logAction("nsl_pre_register_new_user", json_encode($b));
}, 9990);


add_filter('nsl_registration_user_data', function($user){
    $provider = $_GET['loginSocial'];
    $user["provider"] = $provider;
    $log = new Logger();
    $log->logAction("nsl_registration_user_data", $provider);
    return $user;
}, 9990);
*/


//add_filter('nsl_register_new_user', 'social_registration_set_role', 999999, 2);
function social_registration_set_role($user_id, $provider){
    $user = new User($user_id);
    $log = new Logger();
    $log->logAction("nsl_register_new_user", $provider);
    //echo "social_registration_set_role:";
    //print_r( $provider );
    //$role = get_option('default_role');
    
    //print_r($user);
    //echo $role;
    //$user->set_role($role);
    die;
    exit;
}

//add_filter('nsl_login', 'social_registration_login', 199990, 2);
function social_registration_login($user_id, $provider){
    $log = new Logger();
    $log->logAction("nsl_login", json_encode($provider));
    //print_r( $provider );
    $user = new WP_User($user_id);
    //echo 'nsl_login';
    //print_r($user);
    die;
}



add_filter('login_errors','login_error_message');
function login_error_message($error){
    $user_email = isset($_POST['username']) ? $_POST['username'] : '';
	if($user_email){
		$user = get_user_by( 'email', $user_email );
        if(isset($user->ID)){
            $user = new User($user->ID);
            $user_social_login = $user->get_social_login_providers();
            if ($user_social_login) {
                $error = 'Since you registered your membership using your <b>'.(implode(" or ", $user_social_login)).'</b> account'.(count($user_social_login)>1?'s':'').', please log in using those accounts.<br><br>Then, define your password through the <u>Profile->Security</u> page to be able to log in with your email and password later on.';
            }            
        }
	}
    return $error;
}
