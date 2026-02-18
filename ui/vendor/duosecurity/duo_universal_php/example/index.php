<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Logger;
use Slim\Middleware\Session;
use Slim\Views\PhpRenderer;
use Slim\Factory\AppFactory;
use Duo\DuoUniversal\Client;
use Duo\DuoUniversal\DuoException;

require __DIR__ . '/vendor/autoload.php';


$config = parse_ini_file("duo.conf");

try {
    $duo_client = new Client(
        $config['client_id'],
        $config['client_secret'],
        $config['api_hostname'],
        $config['redirect_uri'],
        true,
        $config['http_proxy'] ?? null,
    );
} catch (DuoException $e) {
    throw new ErrorException("*** Duo config error. Verify the values in duo.conf are correct ***\n" . $e->getMessage());
}
$duo_failmode = strtoupper($config['failmode']);

$app = AppFactory::create();
$logger = new Logger();
$errorMiddleware = $app->addErrorMiddleware(true, true, true, $logger);
$app->add(new Session());

$app->get('/', function (Request $request, Response $response, $args) {
    $renderer = new PhpRenderer('./templates');
    $args["message"] = "This is a demo";
    return $renderer->render($response, "login.php", $args);
});

$app->post('/', function (Request $request, Response $response, $args) use ($app, $duo_client, $duo_failmode, $logger) {
    $renderer = new PhpRenderer('./templates');

    $request_body = $request->getParsedBody();
    $username = $request_body['username'];
    $password = $request_body['password'];

    # Check user's first factor
    if (empty($username) || empty($password)) {
        $args["message"] = "Incorrect username or password";
        return $renderer->render($response, "login.php", $args);
    }

    try {
        $duo_client->healthCheck();
    } catch (DuoException $e) {
        $logger->error($e->getMessage());
        if ($duo_failmode == "OPEN") {
            # If we're failing open, errors in 2FA still allow for success
            $args["message"] = "Login 'Successful', but 2FA Not Performed. Confirm Duo client/secret/host values are correct";
            $render_template = "success.php";
        } else {
            # Otherwise the login fails and redirect user to the login page
            $args["message"] = "2FA Unavailable. Confirm Duo client/secret/host values are correct";
            $render_template = "login.php";
        }
        return $renderer->render($response, $render_template, $args);
    }

    # Generate random string to act as a state for the exchange.
    # Store it in the session to be later used by the callback.
    # This example demonstrates use of the http session (cookie-based) 
    # for storing the state. In some applications, strict cookie 
    # controls or other session security measures will mean a different
    # mechanism to persist the state and username will be necessary.
    $state = $duo_client->generateState();
    $session = new \SlimSession\Helper();
    $session->set("state", $state);
    $session->set("username", $username);
    unset($session);

    # Redirect to prompt URI which will redirect to the client's redirect URI after 2FA
    $prompt_uri = $duo_client->createAuthUrl($username, $state);
    return $response
        ->withHeader('Location', $prompt_uri)
        ->withStatus(302);
});

# This route URL must match the redirect_uri passed to the duo client
$app->get('/duo-callback', function (Request $request, Response $response, $args) use ($duo_client, $logger) {
    $query_params = $request->getQueryParams();
    $renderer = new PhpRenderer('./templates');

    # Check for errors from the Duo authentication
    if (isset($query_params["error"])) {
        $error_msg = $query_params["error"] . ":" . $query_params["error_description"];
        $logger->error($error_msg);
        $response->getBody()->write("Got Error: " . $error_msg);
        return $response;
    }

    # Get authorization token to trade for 2FA
    $code = $query_params["duo_code"];

    # Get state to verify consistency and originality
    $state = $query_params["state"];

    # Retrieve the previously stored state and username from the session
    $session = new \SlimSession\Helper();
    $saved_state = $session->get("state");
    $username = $session->get("username");
    unset($session);

    if (empty($saved_state) || empty($username)) {
        # If the URL used to get to login.php is not localhost, (e.g. 127.0.0.1), then the sessions will be different
        # and the localhost session will not have the state.
        $args["message"] = "No saved state please login again";
        return $renderer->render($response, "login.php", $args);
    }

    # Ensure nonce matches from initial request
    if ($state != $saved_state) {
        $args["message"] = "Duo state does not match saved state";
        return $renderer->render($response, "login.php", $args);
    }

    try {
        $decoded_token = $duo_client->exchangeAuthorizationCodeFor2FAResult($code, $username);
    } catch (DuoException $e) {
        $logger->error($e->getMessage());
        $args["message"] = "Error decoding Duo result. Confirm device clock is correct.";
        return $renderer->render($response, "login.php", $args);
    }

    # Exchange happened successfully so render success page
    $args["message"] = json_encode($decoded_token, JSON_PRETTY_PRINT);
    return $renderer->render($response, "success.php", $args);
});

$app->run();
