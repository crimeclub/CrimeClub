<?PHP

use src\Business\UserService;

require_once __DIR__ . '/.inc.head.php';

require_once __DIR__ . '/.inc.sliders.php';

if(isset($userData) && !empty($userData)) $route->headTo('game');
if(OFFLINE && !in_array($_SERVER['REMOTE_ADDR'], DEVELOPER_IPS)) $route->headTo('not_found');

$userService = new UserService();
$key = $route->requestGetParam(3);
if($key != FALSE) $recoverPasswordData = $userService->getRecoverPasswordDataByKey($key);

if(isset($recoverPasswordData) && $recoverPasswordData == FALSE) $route->headTo('not_found');

$langs = array_merge($langs, $language->recoverPasswordLangs());

if(isset($_POST) && !empty($_POST) && (isset($_POST['password']) || isset($_POST['email'])) && isset($_POST['captcha_code']))
{
    // Handle recover lost password form
    $response = $userService->validateRecoverPassword($_POST);
    if(is_bool($response) && $response === TRUE)
        $route->createActionMessage($route->successMessage($langs['RECOVER_PASSWORD_REQUEST_SUCCESS']));
    else
        $route->createActionMessage($route->errorMessage($response));
    
    $route->headTo('recover-password');
    exit(0);
}
elseif(isset($recoverPasswordData) && isset($_POST) && !empty($_POST) && (isset($_POST['new_password']) && isset($_POST['new_password_check'])) && isset($_POST['captcha_code']))
{
    // Handle recovery key form
    $response = $userService->validateNewRecoveredPassword($_POST, $recoverPasswordData);
    if(is_bool($response) && $response === TRUE) // Success
    {
        $route->createActionMessage($route->successMessage($langs['RECOVER_PASSWORD_SUCCESS']));
        $route->headTo('home');
    }
    else
    {
        $route->createActionMessage($route->errorMessage($response));
        header("HTTP/2 301 Moved Permanently");
        header('Location: ' . $route->getRoute(), TRUE, 301); // Redirect user to it's change pwd view with the correct key incase of errors
    }
    exit(0);
}

require_once __DIR__ . '/.inc.foot.php';

//$twigVars['langs'] = array_merge($twigVars['langs'], $language->recoverPasswordLangs()); // Done above already
if(isset($recoverPasswordData)) $twigVars['recoverPasswordData'] = $recoverPasswordData;

// Render view
echo $twig->render('/src/Views/recover-password.twig', $twigVars);
